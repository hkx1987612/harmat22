<?php
defined('ABSPATH') || exit;

final class Harmat_CRM_Attribution_Report {
    private const CHANNELS = array('youtube', 'search', 'facebook', 'unknown');
    // Verified against the account's campaign report. Do not infer Search from a click ID alone.
    private const GOOGLE_CAMPAIGNS = array('24449147750' => 'search', '24215666538' => 'search', '24415124625' => 'youtube');
    private const TAGS = array(
        'harmat_v4_leads_202611' => array('youtube', 'youtube', 'paid_video'),
        'harmat_v4_fb_202611' => array('facebook', 'facebook', 'paid_social'),
    );

    private static function text($v): string {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    private static function unpack($v): array {
        $value = is_string($v) ? @unserialize($v, array('allowed_classes' => false)) : null;
        return is_array($value) ? $value : array();
    }

    public static function date(string $day): ?DateTimeImmutable {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('Europe/Budapest'));
        return $date && $date->format('Y-m-d') === $day ? $date : null;
    }

    private static function token($v): string {
        return is_string($v) && preg_match('/^[A-Za-z0-9_-]{8,350}$/D', $v) ? $v : '';
    }

    public static function signals(array $meta): array {
        $parts = array();
        $urls = array($meta['_harmat_offer_source_page'] ?? '');
        foreach (array('_harmat_offer_posted', '_harmat_offer_tracking', '_harmat_offer_utm') as $key) {
            $m = self::unpack($meta[$key] ?? null);
            if (is_array($m['utm'] ?? null)) $parts[] = $m['utm'];
            $parts[] = $m;
            foreach (array('source', 'source_url', 'source_page', 'landing_page', 'referrer') as $field) $urls[] = $m[$field] ?? '';
        }
        foreach ($urls as $raw) {
            if (!is_string($raw) || strlen($raw) > 4096) continue;
            $u = parse_url($raw);
            if (!is_array($u) || !in_array(strtolower($u['host'] ?? ''), array('harmat22.hu','www.harmat22.hu'), true)
                || !in_array($u['scheme'] ?? '', array('https','http'), true) || isset($u['user'])) continue;
            parse_str($u['query'] ?? '', $query);
            $parts[] = $query;
        }
        return $parts;
    }

    public static function classify(array $signals, array $log_campaigns = array()): array {
        $claims = array(); $matched_tag = null; $unknown_tag = false; $click = false; $conflict = false;
        foreach ($signals as $q) {
            $tag = strtolower(self::text($q['utm_campaign'] ?? ''));
            $src = strtolower(self::text($q['utm_source'] ?? ''));
            $medium = strtolower(self::text($q['utm_medium'] ?? ''));
            if (isset(self::TAGS[$tag])) {
                [$channel, $source, $expected_medium] = self::TAGS[$tag];
                $conflict = $conflict || ($src !== '' && $src !== $source) || ($medium !== '' && $medium !== $expected_medium);
                $claims[$channel] = true;
                $matched_tag = $tag;
            } elseif ($tag !== '') {
                $unknown_tag = true;
            }
            $id = self::text($q['gad_campaignid'] ?? '');
            if (ctype_digit($id)) $claims[self::GOOGLE_CAMPAIGNS[$id] ?? 'other'] = true;
            foreach (array('gclid','gbraid','wbraid') as $marker) $click = $click || self::token($q[$marker] ?? '') !== '';
        }
        foreach ($log_campaigns as $id) $claims[self::GOOGLE_CAMPAIGNS[$id] ?? 'other'] = true;
        if ($conflict || count($claims) > 1) return array('unknown', 'conflict', null);
        $channel = array_key_first($claims);
        if (in_array($channel, self::CHANNELS, true)) {
            return array($channel, $matched_tag ? 'tag' : ($log_campaigns ? 'log' : 'campaign'), $matched_tag);
        }
        return array('unknown', $unknown_tag || $channel === 'other' ? 'unmapped' : ($click ? 'clickOnly' : 'missing'), null);
    }

    private static function contacts($email, $phone): array {
        $mail = strtolower(self::text($email));
        $phone = preg_replace('/[^0-9]/', '', self::text($phone));
        if (strpos($phone, '0036') === 0) $phone = substr($phone, 2);
        elseif (strpos($phone, '06') === 0 && strlen($phone) === 11) $phone = '36' . substr($phone, 2);
        elseif (preg_match('/^(?:20|30|31|50|70)\d{7}$/D', $phone)) $phone = '36' . $phone;
        return array(filter_var($mail, FILTER_VALIDATE_EMAIL) ? $mail : '', strlen($phone) >= 8 && strlen($phone) <= 15 ? $phone : '');
    }

    public static function summarize(array $rows): array {
        $counts = array_fill_keys(self::CHANNELS, array('records'=>0, 'qualified'=>0));
        $parent = array_keys($rows);
        $find = static function ($index) use (&$parent): int {
            $root = $index;
            while ($parent[$root] !== $root) $root = $parent[$root];
            while ($parent[$index] !== $index) { $next = $parent[$index]; $parent[$index] = $root; $index = $next; }
            return $root;
        };
        $keys = array(); $groups = array(); $qualified = array();
        foreach ($rows as $i => $row) {
            $counts[$row['channel']]['records']++;
            if ($row['status'] === 'invalid') continue;
            foreach (array('email','phone','lead') as $field) {
                if (empty($row[$field])) continue;
                $key = $field . ':' . $row[$field];
                if (isset($keys[$key])) $parent[$find($i)] = $find($keys[$key]);
                else $keys[$key] = $i;
            }
        }
        foreach ($rows as $i => $row) if ($row['status'] !== 'invalid') $groups[$find($i)][] = $i;
        foreach ($groups as $members) {
            $identities = array('email'=>array(),'phone'=>array(),'lead'=>array());
            $known = array(); $confirmed = false; $conflict = false; $ambiguous_identity = false;
            foreach ($members as $i) {
                $row = $rows[$i];
                foreach ($identities as $key => $_) if (!empty($row[$key])) $identities[$key][$row[$key]] = true;
                $confirmed = $confirmed || $row['status'] === 'confirmed';
                $ambiguous_identity = $ambiguous_identity || $row['status'] === 'identity';
                $conflict = $conflict || $row['reason'] === 'conflict';
                if ($row['channel'] !== 'unknown') $known[$row['channel']] = true;
            }
            $unique = array_map('count', $identities);
            if (!$confirmed) continue;
            if ($ambiguous_identity || max($unique) !== 1) {
                foreach ($members as $i) if ($rows[$i]['status'] === 'confirmed') $rows[$i]['status'] = 'identity';
                continue;
            }
            $conflict = $conflict || count($known) > 1;
            $channel = !$conflict && count($known) === 1 ? array_key_first($known) : 'unknown';
            $counts[$channel]['qualified']++;
            $qualified[] = array('channel'=>$channel, 'conflict'=>$conflict, 'date'=>substr($rows[$members[0]]['date'],0,10),
                'records'=>array_map(static fn($i)=>array('kind'=>$rows[$i]['kind'],'id'=>$rows[$i]['id']), $members));
        }
        // Remove contact identifiers before handing any data to the presentation layer.
        foreach ($rows as &$row) unset($row['email'], $row['phone'], $row['lead'], $row['signals']);
        unset($row);
        return array('rows'=>$rows, 'qualified'=>$qualified, 'counts'=>$counts);
    }

    public static function log_campaigns(array $tokens, string $directory, DateTimeImmutable $start, DateTimeImmutable $end): array {
        if (!$tokens || !is_dir($directory) || is_link($directory) || !function_exists('gzopen')) return array();
        $found = array(); $scanned = 0;
        $minimum = $start->modify('-7 days');
        $zone = $start->getTimezone();
        for ($month = $end->modify('first day of this month'); $month >= $minimum->modify('first day of this month'); $month = $month->modify('-1 month')) {
            foreach (array('harmat22.hu-', 'harmat22.hu-ssl_log-') as $prefix) {
                $file = rtrim($directory, '/') . '/' . $prefix . $month->format('M-Y') . '.gz';
                if (!is_file($file) || is_link($file) || filesize($file) > 8 * 1024 * 1024) continue;
                $fp = @gzopen($file, 'rb');
                if (!$fp) continue;
                try {
                    while (($line = gzgets($fp, 65536)) !== false) {
                        $scanned += strlen($line);
                        if ($scanned > 96 * 1024 * 1024) return array();
                        if (strpos($line,'gclid=') === false || strpos($line,'gad_campaignid=') === false
                            || !preg_match('~\[([^\]]+)\]\s+"GET ([^ ]+) HTTP/[^\"]+"\s+[23]\d{2}\s~', $line, $m)) continue;
                        $url = parse_url($m[2]);
                        if (!is_array($url) || (!empty($url['host']) && !in_array($url['host'], array('harmat22.hu','www.harmat22.hu'), true))) continue;
                        parse_str($url['query'] ?? '', $q);
                        $token = self::token($q['gclid'] ?? ''); $id = self::text($q['gad_campaignid'] ?? '');
                        if (!isset($tokens[$token]) || !ctype_digit($id)) continue;
                        $event = DateTimeImmutable::createFromFormat('d/M/Y:H:i:s O', $m[1]);
                        if (!$event || $event < $minimum) continue;
                        $time = $event->setTimezone($zone)->format('Y-m-d H:i:s');
                        foreach ($tokens[$token] as $record => $received) if ($time <= $received) $found[$record][$id] = true;
                    }
                } finally { gzclose($fp); }
            }
        }
        return array_map('array_keys', $found);
    }

    public static function load(DateTimeImmutable $start, DateTimeImmutable $end, string $log_dir): array {
        global $wpdb;
        if ($start > $end || $start->diff($end)->days > 30) throw new RangeException('Invalid report window');
        $from = $start->format('Y-m-d'); $through = $end->format('Y-m-d');
        $posts = $wpdb->get_results($wpdb->prepare("SELECT ID,post_date,post_title FROM {$wpdb->posts}
            WHERE post_type='harmat_offer_lead' AND post_status IN ('private','publish','draft') AND post_date>=%s AND post_date<%s
            ORDER BY post_date DESC,ID DESC LIMIT 1501", $from, $end->modify('+1 day')->format('Y-m-d')), ARRAY_A);
        if ($wpdb->last_error || !is_array($posts) || count($posts)>1500) throw new RuntimeException('Inquiry report unavailable');
        $meta = array();
        if ($posts) {
            $ids = implode(',', array_map('intval', array_column($posts, 'ID')));
            $fields = "'_harmat_offer_posted','_harmat_offer_tracking','_harmat_offer_utm','_harmat_offer_source_page','_harmat_offer_email','_harmat_offer_phone'";
            $data = $wpdb->get_results("SELECT post_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($ids) AND meta_key IN ($fields) ORDER BY meta_id ASC", ARRAY_A);
            if ($wpdb->last_error || !is_array($data)) throw new RuntimeException('Inquiry metadata unavailable');
            foreach ($data as $m) if (!isset($meta[$m['post_id']][$m['meta_key']])) $meta[$m['post_id']][$m['meta_key']] = $m['meta_value'];
        }
        $options = $wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('harmat_broker_leads_v1','harmat_sales_deals_v1')", ARRAY_A);
        if ($wpdb->last_error || !is_array($options)) throw new RuntimeException('CRM stages unavailable');
        $options = array_column($options, 'option_value', 'option_name');
        $deals_by_inquiry = array(); $deals_by_lead = array();
        foreach (self::unpack($options['harmat_sales_deals_v1'] ?? '') as $deal) {
            if (!is_array($deal)) continue;
            if (!empty($deal['inquiry_id'])) $deals_by_inquiry[(int)$deal['inquiry_id']][] = $deal;
            if (!empty($deal['lead_id'])) $deals_by_lead[(int)$deal['lead_id']][] = $deal;
        }
        $rows = array(); $tokens = array();
        foreach ($posts as $post) {
            $id = (int)$post['ID']; $date = $post['post_date']; $m = $meta[$id] ?? array();
            $posted = self::unpack($m['_harmat_offer_posted'] ?? '');
            [$email, $phone] = self::contacts(($m['_harmat_offer_email'] ?? '') ?: ($posted['your-email'] ?? ''), ($m['_harmat_offer_phone'] ?? '') ?: ($posted['your-phone'] ?? ''));
            $deals = $deals_by_inquiry[$id] ?? array();
            [$status, $lead] = self::deal_status($deals);
            if (self::test_record($posted['your-name'] ?? $post['post_title'], $posted['your-message'] ?? '', $email)) $status = 'invalid';
            $signals = self::signals($m);
            foreach ($signals as $q) {
                $token = self::token($q['gclid'] ?? '');
                if ($token !== '') $tokens[$token][$id] = $date;
            }
            $rows[] = compact('id','date','email','phone','lead','status','signals') + array('kind'=>'website');
        }
        foreach (self::unpack($options['harmat_broker_leads_v1'] ?? '') as $key => $row) {
            if (!is_array($row)) continue;
            $date = self::text($row['created_at'] ?? ''); $day = substr($date,0,10);
            if ($day < $from || $day > $through || !self::date($day)) continue;
            $id = (int)($row['id'] ?? $key);
            if (!$id) continue;
            [$email,$phone] = self::contacts($row['email'] ?? '', $row['phone'] ?? '');
            [$status] = self::deal_status($deals_by_lead[$id] ?? array());
            $stage = self::text($row['status'] ?? '');
            if ($status !== 'invalid' && in_array($stage,array('reserved','closed'),true)) $status = 'confirmed';
            if ($stage === 'lost' || self::test_record($row['client_name'] ?? '', $row['note'] ?? '', $email)) $status = 'invalid';
            $rows[] = compact('id','date','email','phone','status') + array('kind'=>'registered', 'lead'=>$id, 'signals'=>array());
        }
        if (count($rows)>2500) throw new RuntimeException('CRM report exceeds bound');
        $matches = self::log_campaigns($tokens, $log_dir, $start, $end);
        foreach ($rows as &$row) {
            [$row['channel'],$row['reason'],$row['tag']] = self::classify($row['signals'], $row['kind']==='website' ? ($matches[$row['id']] ?? array()) : array());
        }
        unset($row);
        usort($rows,static fn($a,$b)=>strcmp($b['date'],$a['date']) ?: $b['id'] <=> $a['id']);
        return self::summarize($rows);
    }

    private static function deal_status(array $deals): array {
        $leads = array(); $stages = array_column($deals,'stage');
        foreach ($deals as $deal) if (!empty($deal['lead_id'])) $leads[(int)$deal['lead_id']] = true;
        $status = in_array('lost',$stages,true) ? 'invalid' : (array_intersect($stages,array('reserved','contract','closed')) ? 'confirmed' : 'pending');
        if (count($leads)>1 && $status==='confirmed') $status='identity';
        return array($status,count($leads)===1?array_key_first($leads):0);
    }

    private static function test_record($name, $note, string $email): bool {
        $name = self::text($name); $note = self::text($note);
        return (bool)(preg_match('/^(?:(?:teszt|test)(?:[ :_-]|$)|\x{6d4b}\x{8bd5}|harmat-qa-)|https?:\/\/|www\./iu', $name)
            || preg_match('/^(?:HARMAT-QA-|(?:TESZT|TEST)\s*[:_-])/i', $note)
            || preg_match('/@(?:example\.(?:com|net|org|test)|[^@]+\.(?:test|invalid))$/i', $email));
    }

    public static function render(string $lang, array $query, string $url, string $log_dir): void {
        $hu = $lang === 'hu';
        $labels = $hu ? array(
            'title'=>'Érdeklődések és hirdetési források','raw'=>'Eredeti érdeklődések','effective'=>'Egyedi minősített ügyfelek',
            'unknown'=>'Forrás nem igazolt','youtube'=>'YouTube V4 érdeklődés','search'=>'Google keresés','facebook'=>'Facebook',
            'total'=>'Összesen','source'=>'Hirdetési forrás','from'=>'Kezdő dátum','to'=>'Záró dátum','apply'=>'Szűrés','all'=>'Minden forrás',
            'details'=>'Érdeklődések','qualified'=>'Minősített ügyfelek','date'=>'Rögzítés dátuma','recent'=>'Utolsó rögzítés',
            'record'=>'Rekord','customer'=>'Ügyfél','links'=>'Kapcsolódó rekordok','progress'=>'Minősítés','evidence'=>'Forrás alapja',
            'website'=>'Weboldal','registered'=>'CRM','none'=>'Nincs találat.','pending'=>'Ellenőrzésre vár','identity'=>'Azonosság ellenőrzendő',
            'invalid'=>'Nem számít minősítettnek','confirmed'=>'Igazolt vételi szándék','tag'=>'Egyező kampányjelölés',
            'log'=>'Kattintás és Google-kampány egyezése','campaign'=>'Igazolt Google-kampányazonosító','clickOnly'=>'Ismeretlen kampányú kattintás',
            'unmapped'=>'Nem igazolt kampány','missing'=>'Nincs igazolható jelölés','conflict'=>'Ellentmondó források',
            'rules'=>'Számítási szabályok','badRange'=>'Válasszon legfeljebb 31 befejezett napot.','unavailable'=>'Az adatok most nem ellenőrizhetők.',
            'definition'=>'A rögzítés napja számít. Az egyedi ügyfélhez azonosítható kapcsolat és meglévő foglalási szándék, foglalás, szerződés vagy lezárt ügy szükséges. A kapcsolatfelvétel, az időpont vagy a lakáskészlet nem minősítés.',
            'policy'=>'A hiányzó vagy ellentmondó kampányjelölés nem kerül hirdetéshez. Az azonos ügyfél rekordjai megmaradnak; a jelentésen belül egyszer számítjuk őket.',
        ) : array(
            'title'=>'询价与广告来源','raw'=>'原始询价','effective'=>'去重有效客户','unknown'=>'来源待核实',
            'youtube'=>'YouTube V4 询价','search'=>'Google 搜索','facebook'=>'Facebook',
            'total'=>'总计','source'=>'广告来源','from'=>'开始日期','to'=>'结束日期','apply'=>'筛选','all'=>'全部来源',
            'details'=>'询价明细','qualified'=>'有效客户','date'=>'登记日期','recent'=>'最近登记','record'=>'记录','customer'=>'客户',
            'links'=>'关联询价','progress'=>'销售核实','evidence'=>'归因依据','website'=>'网站','registered'=>'CRM','none'=>'当前筛选无记录。',
            'pending'=>'待销售核实','identity'=>'身份待核实','invalid'=>'不计入有效客户','confirmed'=>'购房意向已确认','tag'=>'活动标记匹配',
            'log'=>'点击标识匹配 Google 系列','campaign'=>'已核实 Google 系列编号','clickOnly'=>'有点击标识，系列不明',
            'unmapped'=>'活动尚未核实','missing'=>'缺少可核实的广告标记','conflict'=>'来源冲突',
            'rules'=>'统计口径','badRange'=>'请选择不超过 31 个完整自然日。','unavailable'=>'当前无法核对数据，未按零统计。',
            'definition'=>'按登记日期统计。只计身份可去重且已有意向预订、预订、合同或成交记录的客户。仅联系、预约或房源库存状态不计入。',
            'policy'=>'标记缺失或来源冲突的记录保留待核实。去重只影响报表计数，不改变任何原始记录。',
        );
        $label = static fn($key) => esc_html($labels[$key]);
        $till = new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Budapest'));
        $start = self::date(self::text($query['attribution_from'] ?? $till->modify('-13 days')->format('Y-m-d')));
        $end = self::date(self::text($query['attribution_to'] ?? $till->format('Y-m-d')));
        $valid = $start && $end && $start <= $end && $end <= $till && $start->diff($end)->days <= 30;
        $view = ($query['attribution_view'] ?? '') === 'qualified' ? 'qualified' : 'details';
        $source = in_array($query['attribution_source'] ?? '', self::CHANNELS, true) ? $query['attribution_source'] : '';
        $from_value = $start ? $start->format('Y-m-d') : '';
        $to_value = $end ? $end->format('Y-m-d') : '';
        $params = array('view'=>'inquiries','report'=>'attribution','sales_lang'=>$lang);
        $link = static fn($v) => add_query_arg($params + array('attribution_from'=>$from_value,'attribution_to'=>$to_value,
            'attribution_view'=>$v,'attribution_source'=>$source),$url);
        ?>
        <style>
          .ha-report{color:#243c34;font-variant-numeric:lining-nums tabular-nums}.ha-head,.ha-filter,.ha-tabs{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:end}.ha-head{justify-content:space-between;margin-bottom:14px}.ha-head h2{margin:0;font-size:23px;line-height:1.35}.ha-filter label{display:grid;gap:3px;color:#526659;font-size:13px}.ha-filter input,.ha-filter select,.ha-filter button{height:38px;max-width:100%;min-width:0;font:inherit;border-radius:4px}.ha-filter button{padding:0 15px}.ha-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-block:1px solid #cbd9ce;background:#f3f6ef;margin:0 0 18px}.ha-kpis>div{min-width:0;padding:11px 15px}.ha-kpis>div+div{border-left:1px solid #d3ded3}.ha-kpis strong{display:block;font-size:26px;line-height:1.3}.ha-kpis span{font-size:13px;color:#526659}.ha-scroll{overflow:auto;border-block:1px solid #d3ded3;max-width:100%}.ha-table{width:100%;border-collapse:collapse;font-size:14px;text-align:left;background:#fafbf7}.ha-table th,.ha-table td{padding:8px 12px;vertical-align:middle;border-bottom:1px solid #dce4d9;overflow-wrap:anywhere}.ha-table thead,.ha-table tfoot{background:#e8efe7}.ha-table tfoot th,.ha-table tfoot td{font-weight:650}.ha-num{text-align:right;width:25%}.ha-detail{min-width:610px}.ha-tabs{margin:17px 0 8px;border-bottom:1px solid #d3ded3}.ha-tabs a{display:block;padding:8px 2px;border-bottom:2px solid transparent;font-weight:650;color:#536559}.ha-tabs a[aria-current=true]{border-color:#315d4c;color:#233b31}.ha-note{margin-top:14px;color:#536559}.ha-note summary{cursor:pointer;color:#2b513f}.ha-note p{margin:5px 0}.ha-message{padding:10px 0;color:#67491f}.ha-review{color:#795920}
          @media(max-width:560px){.ha-head h2{font-size:20px}.ha-filter label{max-width:49%}.ha-kpis{grid-template-columns:1fr}.ha-kpis>div{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 10px}.ha-kpis>div+div{border-left:0;border-top:1px solid #d3ded3}.ha-kpis strong{font-size:21px}.ha-table th,.ha-table td{padding:7px 8px}.ha-num{width:28%}}
        </style>
        <section class="harmat-sales-panel ha-report" aria-labelledby="ha-title">
          <header class="ha-head"><h2 id="ha-title"><?= $label('title') ?></h2>
            <form class="ha-filter" method="get" action="<?= esc_url($url) ?>">
              <?php foreach ($params + array('attribution_view'=>$view) as $key=>$value): ?>
                <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
              <?php endforeach; ?>
              <label><?= $label('from') ?><input type="date" required max="<?= esc_attr($till->format('Y-m-d')) ?>" name="attribution_from" value="<?= esc_attr($from_value) ?>"></label>
              <label><?= $label('to') ?><input type="date" required max="<?= esc_attr($till->format('Y-m-d')) ?>" name="attribution_to" value="<?= esc_attr($to_value) ?>"></label>
              <label><?= $label('source') ?><select name="attribution_source"><option value=""><?= $label('all') ?></option>
              <?php foreach (self::CHANNELS as $key): ?><option value="<?= esc_attr($key) ?>"<?= $source === $key ? ' selected' : '' ?>><?= $label($key) ?></option><?php endforeach; ?>
              </select></label><button type="submit"><?= $label('apply') ?></button>
            </form>
          </header>
          <?php
          if (!$valid) { echo '<p class="ha-message" role="status">' . $label('badRange') . '</p></section>'; return; }
          try { $report = self::load($start, $end, $log_dir); }
          catch (Throwable $error) { echo '<p class="ha-message" role="status">' . $label('unavailable') . '</p></section>'; return; }
          $raw = count($report['rows']); $qualified = count($report['qualified']);
          ?>
          <div class="ha-kpis">
            <div><span><?= $label('raw') ?></span><strong id="ha-count-raw"><?= $raw ?></strong></div>
            <div><span><?= $label('effective') ?></span><strong id="ha-count-effective"><?= $qualified ?></strong></div>
            <div><span><?= $label('unknown') ?></span><strong id="ha-count-unknown"><?= $report['counts']['unknown']['records'] ?></strong></div>
          </div>
          <div class="ha-scroll"><table class="ha-table" aria-labelledby="ha-title"><thead><tr><th scope="col"><?= $label('source') ?></th><th scope="col" class="ha-num"><?= $label('raw') ?></th><th scope="col" class="ha-num"><?= $label('effective') ?></th></tr></thead><tbody>
              <?php foreach ($report['counts'] as $key=>$counts): ?>
                <tr data-channel="<?= esc_attr($key) ?>"><th scope="row"><?= $label($key) ?></th><td class="ha-num"><?= $counts['records'] ?></td><td class="ha-num"><?= $counts['qualified'] ?></td></tr>
              <?php endforeach; ?>
          </tbody><tfoot><tr><th scope="row"><?= $label('total') ?></th><td class="ha-num"><?= $raw ?></td><td class="ha-num"><?= $qualified ?></td></tr></tfoot></table></div>
          <nav class="ha-tabs" aria-label="<?= $label('title') ?>">
            <?php foreach (array('details','qualified') as $tab): ?><a href="<?= esc_url($link($tab)) ?>"<?= $tab===$view ? ' aria-current="true"' : '' ?>><?= $label($tab) ?></a><?php endforeach; ?>
          </nav>
          <div class="ha-scroll" tabindex="0"><table class="ha-table ha-detail"><thead><tr>
            <?php foreach ($view==='qualified' ? array('customer','recent','source','links') : array('record','date','source','progress','evidence') as $column): ?><th scope="col"><?= $label($column) ?></th><?php endforeach; ?>
          </tr></thead><tbody id="ha-detail-rows">
          <?php
          $shown = 0;
          foreach ($view==='qualified' ? $report['qualified'] : $report['rows'] as $seq=>$row):
              if ($source !== '' && $row['channel'] !== $source) continue;
              $shown++;
              $reference = static fn($record) => $label($record['kind']) . ' #' . (int)$record['id'];
              ?>
              <tr>
              <?php if ($view==='qualified'): ?>
                <td><?= $label('customer') . ' ' . ($seq + 1) ?></td><td><?= esc_html($row['date']) ?></td>
                <td><?= $label($row['channel']) ?><?= $row['conflict'] ? '<small>' . $label('conflict') . '</small>' : '' ?></td><td><?= implode(' · ',array_map($reference,$row['records'])) ?></td>
              <?php else: ?>
                <td><?= $reference($row) ?></td><td><?= esc_html(substr($row['date'],0,10)) ?></td><td><?= $label($row['channel']) ?></td>
                <td class="ha-review"><?= $label($row['status']) ?></td><td>
                <?php if ($row['tag']): ?><details><summary><?= $label($row['reason']) ?></summary><code><?= esc_html($row['tag']) ?></code></details>
                <?php else: echo $label($row['reason']); endif; ?></td>
              <?php endif; ?>
              </tr>
          <?php endforeach; if (!$shown): ?><tr><td colspan="<?= $view==='qualified' ? 4 : 5 ?>"><?= $label('none') ?></td></tr><?php endif; ?>
          </tbody></table></div>
          <details class="ha-note"><summary><?= $label('rules') ?></summary><p><?= $label('definition') ?></p><p><?= $label('policy') ?></p></details>
        </section>
        <?php
    }
}
