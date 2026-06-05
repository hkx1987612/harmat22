<?php
/**
 * Plugin Name: Harmat Local Assistant
 * Plugin URI: https://harmat22.hu
 * Description: Local knowledge-base assistant for Harmat Lakópark apartment questions, prices, FAQ, and sales handoff.
 * Version: 0.1.9
 * Author: Harmat22 Maintenance
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null) {
        $chars = preg_split('//u', (string) $string, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            return $length === null ? substr((string) $string, $start) : substr((string) $string, $start, $length);
        }
        $slice = $length === null ? array_slice($chars, $start) : array_slice($chars, $start, $length);
        return implode('', $slice);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null) {
        $string = strtr((string) $string, array(
            'Á' => 'á',
            'É' => 'é',
            'Í' => 'í',
            'Ó' => 'ó',
            'Ö' => 'ö',
            'Ő' => 'ő',
            'Ú' => 'ú',
            'Ü' => 'ü',
            'Ű' => 'ű',
        ));
        return strtolower($string);
    }
}

if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) {
        return strpos((string) $haystack, (string) $needle, (int) $offset);
    }
}

final class Harmat_Local_Assistant {
    const VERSION = '0.1.9';
    const REST_NAMESPACE = 'harmat-local-assistant/v1';
    const CONTACT_EMAIL = 'ertekesites@harmat22.hu';
    const CONTACT_PHONE = '+36-30-641-03-58';

    private static $apartments = null;

    public function __construct() {
        remove_action('wp_footer', 'harmat_perf_ai_customer_assistant', 130);
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_footer', array($this, 'render_widget'), 120);
        add_shortcode('harmat_local_assistant', array($this, 'render_shortcode'));
    }

    public function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/ask', array(
            'methods' => 'POST',
            'callback' => array($this, 'answer_request'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
    }

    public function answer_request(WP_REST_Request $request) {
        $message = trim((string) $request->get_param('message'));
        $message = wp_strip_all_tags($message);
        $message = mb_substr($message, 0, 260, 'UTF-8');

        if ($message === '') {
            return rest_ensure_response(array(
                'ok' => false,
                'answer' => $this->text('empty', 'hu'),
                'cards' => array(),
                'suggestions' => $this->default_suggestions('hu'),
            ));
        }

        $lang = $this->detect_language($message);
        $result = $this->answer_message($message, $lang);
        return rest_ensure_response($result);
    }

    public function render_shortcode() {
        ob_start();
        $this->render_widget(true);
        return ob_get_clean();
    }

    public function render_widget($shortcode = false) {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!$shortcode && $this->is_private_portal()) {
            return;
        }

        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        $endpoint = esc_url_raw(rest_url(self::REST_NAMESPACE . '/ask'));
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <style id="harmat-local-assistant-style">
          .harmat-local-ai-launch {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 99990;
            min-height: 48px;
            padding: 5px 17px 5px 6px;
            border: 0;
            border-radius: 999px;
            background: #9a6a2a;
            color: #fff;
            box-shadow: 0 16px 38px rgba(35, 33, 28, .26);
            font: 800 14px/1.2 Montserrat, Arial, sans-serif;
            letter-spacing: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
          }
          .harmat-local-ai-avatar {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff7e9;
            color: #9a6a2a;
            box-shadow: inset 0 0 0 1px rgba(154, 106, 42, .2);
            flex: 0 0 38px;
          }
          .harmat-local-ai-avatar svg {
            width: 24px;
            height: 24px;
            display: block;
          }
          .harmat-local-ai-panel {
            position: fixed;
            right: 18px;
            bottom: 78px;
            z-index: 99991;
            width: min(390px, calc(100vw - 24px));
            max-height: min(690px, calc(100vh - 108px));
            display: none;
            grid-template-rows: auto minmax(0, 1fr) auto;
            overflow: hidden;
            border: 1px solid rgba(154, 106, 42, .28);
            border-radius: 8px;
            background: #fffaf2;
            color: #273136;
            box-shadow: 0 22px 58px rgba(34, 30, 22, .22);
          }
          .harmat-local-ai-panel.is-open { display: grid; }
          .harmat-local-ai-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            background: #283137;
            color: #fff;
          }
          .harmat-local-ai-head strong { display: block; font-size: 15px; line-height: 1.2; }
          .harmat-local-ai-head small { display: block; margin-top: 3px; color: rgba(255,255,255,.72); font-size: 11px; }
          .harmat-local-ai-close {
            width: 32px;
            height: 32px;
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 999px;
            background: transparent;
            color: #fff;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
          }
          .harmat-local-ai-body {
            overflow: auto;
            padding: 14px;
            display: grid;
            gap: 10px;
            background: #fffaf2;
          }
          .harmat-local-ai-msg {
            max-width: 92%;
            padding: 11px 12px;
            border-radius: 8px;
            font: 500 14px/1.48 Montserrat, Arial, sans-serif;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
          }
          .harmat-local-ai-msg.bot { justify-self: start; background: #fff; border: 1px solid #eadcc7; }
          .harmat-local-ai-msg.user { justify-self: end; background: #9a6a2a; color: #fff; }
          .harmat-local-ai-cards { display: grid; gap: 8px; margin-top: 8px; }
          .harmat-local-ai-card {
            display: block;
            padding: 10px;
            border: 1px solid #eadcc7;
            border-radius: 7px;
            background: #fff;
            color: #273136;
            text-decoration: none;
          }
          .harmat-local-ai-card b { display: block; margin-bottom: 5px; color: #9a6a2a; }
          .harmat-local-ai-card span { display: block; color: #5d6468; font-size: 12px; line-height: 1.45; }
          .harmat-local-ai-suggestions { display: flex; flex-wrap: wrap; gap: 7px; padding: 0 14px 12px; background: #fffaf2; }
          .harmat-local-ai-suggestions button {
            min-height: 31px;
            padding: 0 10px;
            border: 1px solid #d8bd8d;
            border-radius: 999px;
            background: #fff;
            color: #6c4a1d;
            font: 700 12px/1 Montserrat, Arial, sans-serif;
            cursor: pointer;
          }
          .harmat-local-ai-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            padding: 12px 14px 14px;
            border-top: 1px solid #eadcc7;
            background: #fff;
          }
          .harmat-local-ai-input {
            min-height: 42px;
            border: 1px solid #d8bd8d;
            border-radius: 7px;
            padding: 0 12px;
            color: #273136;
            background: #fff;
            font: 500 14px/1 Montserrat, Arial, sans-serif;
            outline: none;
          }
          .harmat-local-ai-send {
            min-height: 42px;
            padding: 0 13px;
            border: 0;
            border-radius: 7px;
            background: #283137;
            color: #fff;
            font: 800 13px/1 Montserrat, Arial, sans-serif;
            cursor: pointer;
          }
          .harmat-local-ai-footnote {
            padding: 0 14px 10px;
            background: #fff;
            color: #8a7a64;
            font: 500 11px/1.4 Montserrat, Arial, sans-serif;
          }
          @media (max-width: 520px) {
            .harmat-local-ai-launch { right: 12px; bottom: 12px; padding-right: 14px; }
            .harmat-local-ai-panel { right: 12px; bottom: 68px; max-height: calc(100vh - 86px); }
          }
        </style>
        <button class="harmat-local-ai-launch" type="button" aria-controls="harmat-local-ai-panel" aria-expanded="false">
          <span class="harmat-local-ai-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <path fill="currentColor" d="M12 12.4a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2.1c-3.5 0-6.6 1.8-7.9 4.5-.3.7.2 1.5 1 1.5h13.8c.8 0 1.3-.8 1-1.5-1.3-2.7-4.4-4.5-7.9-4.5Z"/>
            </svg>
          </span>
          <span>Kérdezzen</span>
        </button>
        <section class="harmat-local-ai-panel" id="harmat-local-ai-panel" aria-label="Harmat Lakópark asszisztens">
          <div class="harmat-local-ai-head">
            <div>
              <strong>Harmat asszisztens</strong>
              <small>Lakás, ár, alaprajz és időpont</small>
            </div>
            <button class="harmat-local-ai-close" type="button" aria-label="Bezárás">×</button>
          </div>
          <div class="harmat-local-ai-body" data-harmat-local-ai-body></div>
          <div class="harmat-local-ai-suggestions" data-harmat-local-ai-suggestions></div>
          <form class="harmat-local-ai-form" data-harmat-local-ai-form>
            <input class="harmat-local-ai-input" name="message" autocomplete="off" maxlength="260" placeholder="Írja be kérdését..." aria-label="Kérdés">
            <button class="harmat-local-ai-send" type="submit">Küldés</button>
          </form>
          <div class="harmat-local-ai-footnote">Automatizált, tájékoztató válaszok. Nem hivatalos ajánlat, jogi vagy pénzügyi tanácsadás; a végleges adatokat az értékesítés és a szerződés erősíti meg.</div>
        </section>
        <script id="harmat-local-assistant-script">
        (function () {
          if (window.__harmatLocalAssistantReady) return;
          window.__harmatLocalAssistantReady = true;

          var endpoint = <?php echo wp_json_encode($endpoint); ?>;
          var nonce = <?php echo wp_json_encode($nonce); ?>;
          var panel = null;
          var launch = null;
          var close = null;
          var body = null;
          var form = null;
          var input = null;
          var suggestions = null;

          function refreshElements() {
            panel = document.getElementById('harmat-local-ai-panel');
            launch = document.querySelector('.harmat-local-ai-launch');
            close = panel ? panel.querySelector('.harmat-local-ai-close') : null;
            body = panel ? panel.querySelector('[data-harmat-local-ai-body]') : null;
            form = panel ? panel.querySelector('[data-harmat-local-ai-form]') : null;
            input = form ? form.querySelector('input[name="message"]') : null;
            suggestions = panel ? panel.querySelector('[data-harmat-local-ai-suggestions]') : null;
            return !!(panel && launch && close && body && form && input && suggestions);
          }

          function esc(text) {
            return String(text || '').replace(/[&<>"']/g, function (c) {
              return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
          }

          function addMessage(kind, text, cards) {
            if (!body && !refreshElements()) return;
            var msg = document.createElement('div');
            msg.className = 'harmat-local-ai-msg ' + kind;
            msg.innerHTML = esc(text);
            if (cards && cards.length) {
              var list = document.createElement('div');
              list.className = 'harmat-local-ai-cards';
              cards.forEach(function (card) {
                var link = document.createElement('a');
                link.className = 'harmat-local-ai-card';
                link.href = card.url || '#';
                link.innerHTML = '<b>' + esc(card.title) + '</b><span>' + esc(card.meta) + '</span>';
                list.appendChild(link);
              });
              msg.appendChild(list);
            }
            body.appendChild(msg);
            if (kind === 'bot') {
              window.setTimeout(function () {
                body.scrollTop = Math.max(0, msg.offsetTop - body.offsetTop - 8);
              }, 0);
            } else {
              body.scrollTop = body.scrollHeight;
            }
          }

          function setSuggestions(items) {
            if (!suggestions && !refreshElements()) return;
            suggestions.innerHTML = '';
            (items || []).slice(0, 4).forEach(function (text) {
              var btn = document.createElement('button');
              btn.type = 'button';
              btn.textContent = text;
              btn.onclick = function () {
                input.value = text;
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
              };
              suggestions.appendChild(btn);
            });
          }

          function openPanel() {
            if (!refreshElements()) return;
            panel.classList.add('is-open');
            launch.setAttribute('aria-expanded', 'true');
            if (!body.dataset.started) {
              body.dataset.started = '1';
              addMessage('bot', 'Üdvözlöm! Automatizált Harmat asszisztensként segítek lakást keresni. Kérdezhet árakról, szobaszámról, alapterületről, vásárlási folyamatról vagy konkrét lakásról, például A1-F-L1. A válaszok tájékoztató jellegűek, a végleges adatokat az értékesítés erősíti meg.');
              setSuggestions(['Vásárlás menete?', '2 szobás 70 millió Ft körül', 'Mennyi az A1-F-L1 ára?', 'Időpontot kérek']);
            }
            setTimeout(function () { input && input.focus(); }, 80);
          }

          function closePanel() {
            if (!refreshElements()) return;
            panel.classList.remove('is-open');
            launch.setAttribute('aria-expanded', 'false');
          }

          async function ask(text) {
            if (!refreshElements()) return;
            addMessage('user', text);
            var pending = document.createElement('div');
            pending.className = 'harmat-local-ai-msg bot';
            pending.textContent = 'Válasz készül...';
            body.appendChild(pending);
            body.scrollTop = body.scrollHeight;
            try {
              var response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-WP-Nonce': nonce
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: text })
              });
              var data = await response.json();
              pending.remove();
              addMessage('bot', data.answer || 'Nem sikerült választ adni.', data.cards || []);
              setSuggestions(data.suggestions || []);
            } catch (err) {
              pending.remove();
              addMessage('bot', 'Most nem sikerült elérni az asszisztenst. Kérem, próbálja újra később, vagy írjon az ertekesites@harmat22.hu címre.');
            }
          }

          function handleSubmit(event) {
            event.preventDefault();
            if (!refreshElements()) return;
            var text = input.value.trim();
            if (!text) return;
            input.value = '';
            ask(text);
          }

          refreshElements();
          document.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.closest) return;
            if (target.closest('.harmat-local-ai-launch')) {
              event.preventDefault();
              openPanel();
              return;
            }
            if (target.closest('.harmat-local-ai-close')) {
              event.preventDefault();
              closePanel();
            }
          }, true);
          document.addEventListener('submit', function (event) {
            if (event.target && event.target.matches && event.target.matches('[data-harmat-local-ai-form]')) {
              handleSubmit(event);
            }
          }, true);
          window.addEventListener('load', refreshElements, { once: true });
        })();
        </script>
        <?php
    }

    private function is_private_portal() {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');
        return in_array($path, array('sales', 'agent', 'client', 'customer', 'ugyfel', 'sales-admin'), true);
    }

    private function answer_message($message, $lang) {
        $normalized = $this->normalize($message);
        $apartments = $this->apartments();
        $profile = $this->extract_buyer_profile($message, $normalized);

        $code = $this->extract_apartment_code($message);
        if ($code) {
            $apartment = $this->find_apartment($code, $apartments);
            if ($apartment) {
                return $this->response($this->apartment_answer($apartment, $lang, $profile), array($this->card($apartment, $profile)), $lang);
            }
        }

        $filters = $this->extract_filters($message, $normalized);
        $filters['profile'] = $profile;
        $ground_floor_search = $filters['ground_floor'] && $this->has_any($normalized, array('ajanl', 'keres', 'lakas', 'lakast', 'recommend', 'available', 'melyik', '预算', '推荐', '房源', '有哪些'));
        if ($filters['has_search'] && ($filters['rooms'] || $filters['budget'] || $filters['area'] || $filters['cheap'] || $ground_floor_search)) {
            $matches = $this->search_apartments($apartments, $filters);
            if ($matches) {
                return $this->response($this->recommendation_answer($matches, $filters, $lang), $this->cards_for_matches($matches, $profile), $lang);
            }
            return $this->response($this->text('no_match', $lang), array(), $lang);
        }

        $faq = $this->faq_answer($normalized, $lang);
        if ($faq !== null) {
            return $this->response($faq, array(), $lang);
        }

        if ($filters['has_search']) {
            return $this->response($this->selection_guidance_answer($lang), array(), $lang);
        }

        if ($this->has_price_intent($normalized)) {
            return $this->response($this->price_overview_answer($apartments, $lang), array(), $lang);
        }

        return $this->response($this->text('fallback', $lang), array(), $lang);
    }

    private function response($answer, $cards, $lang) {
        return array(
            'ok' => true,
            'answer' => $answer,
            'cards' => $cards,
            'suggestions' => $this->default_suggestions($lang),
        );
    }

    private function apartments() {
        if (self::$apartments !== null) {
            return self::$apartments;
        }

        $path = plugin_dir_path(__FILE__) . 'data/harmat_apartments_kb.json';
        if (!is_readable($path)) {
            self::$apartments = array();
            return self::$apartments;
        }

        $data = json_decode((string) file_get_contents($path), true);
        self::$apartments = is_array($data) ? $data : array();
        return self::$apartments;
    }

    private function detect_language($message) {
        if (preg_match('/\p{Han}/u', $message)) {
            return 'zh';
        }

        if (preg_match('/\b(price|prices|amount|cost|payment|pay|installment|quote|offer|address|apartment|flat|handover|floor|room|rooms|investment|investor|rental|rent|family|child|children|floorplan|layout|pdf|reserve|reservation|status|available|sold|budget|parking|storage|loan|mortgage|when|where|have|nearby|opening|hours|weekend|dog|pet|park|school|shopping|transport|contact|appointment|viewing|visit|heating|cooling|green|process|purchase|choose|advantage|benefit|discount|expensive|cheaper|launch|garden|ground)\b/i', $message)) {
            return 'en';
        }

        return 'hu';
    }

    private function normalize($text) {
        $text = mb_strtolower($text, 'UTF-8');
        $from = array('á','é','í','ó','ö','ő','ú','ü','ű');
        $to = array('a','e','i','o','o','o','u','u','u');
        $text = str_replace(
            array('á','à','ä','â','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ő','ô','ú','ù','ü','ű','û'),
            array('a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','u'),
            $text
        );
        return str_replace($from, $to, $text);
    }

    private function extract_apartment_code($message) {
        if (!preg_match('/\b(A[1-4])[\s_-]*(F|FSZ|[1-4])[\s_-]*(L[1-8])\b/iu', $message, $match)) {
            return null;
        }

        $floor = strtoupper($match[2]);
        if ($floor === 'FSZ') {
            $floor = 'F';
        }

        return strtoupper($match[1]) . '-' . $floor . '-' . strtoupper($match[3]);
    }

    private function find_apartment($code, $apartments) {
        $needle = mb_strtolower($code, 'UTF-8');
        foreach ($apartments as $apartment) {
            if (mb_strtolower((string) ($apartment['apartment'] ?? ''), 'UTF-8') === $needle) {
                return $apartment;
            }
        }
        return null;
    }

    private function faq_answer($text, $lang) {
        if ($this->has_any($text, array('nyitvatart', 'nyitva', 'hetfo', 'pentek', 'szombat', 'vasarnap', 'hetveg', 'opening hour', 'opening hours', 'weekend', 'open on', 'hours', '营业', '营业时间', '开门', '周末', '上班时间'))) {
            return $this->by_lang($lang,
                'Az értékesítési iroda hétfőtől péntekig 09:00-17:00 között érhető el. Szombaton és vasárnap zárva tart. Időpont egyeztetéshez írjon az ertekesites@harmat22.hu címre vagy hívja a +36-30-641-03-58 számot.',
                '销售办公室营业时间：星期一至星期五 09:00-17:00，星期六和星期天不营业。如需预约看房，可以联系 ertekesites@harmat22.hu 或 +36-30-641-03-58。',
                'The sales office is available Monday to Friday, 09:00-17:00. It is closed on Saturday and Sunday. For a viewing appointment, contact ertekesites@harmat22.hu or +36-30-641-03-58.'
            );
        }

        if ($this->has_any($text, array('ertekesites indul', 'ertekesitesi indul', 'nyito', 'nyitas', 'mikor indul', 'start', 'sales launch', 'launch date', 'opening date', 'launch', '开盘', '开售', '发售', '什么时候卖', '什么时候开'))) {
            return $this->by_lang($lang,
                'A Harmat Lakópark értékesítési indulása 2026. június 12. A pontos napi programot, foglalási sorrendet és aktuális feltételeket az értékesítési csapat erősíti meg.',
                'Harmat Lakópark 项目开盘时间为 2026年6月12日。当天具体安排、选房/保留顺序和实时条件，以销售团队确认为准。',
                'The Harmat Lakópark sales launch date is June 12, 2026. The exact daily program, reservation order and current terms should be confirmed by the sales team.'
            );
        }

        if ($this->has_any($text, array('foldszinti kert', 'foldszint kert', 'kert ajandek', 'ajandek kert', 'foldszint', 'garden', 'ground floor garden', 'ground-floor garden', 'gift garden', 'included garden', '底楼花园', '底层花园', '花园赠送', '赠送花园', '送花园', '底楼', '底层'))) {
            return $this->by_lang($lang,
                'A jelenlegi értékesítési információ szerint a földszinti lakások kertje ajándékként jár, külön kertár nélkül. A kert méretét, használati részleteit és szerződéses rögzítését mindig az adott lakásnál az értékesítés erősíti meg.',
                '目前销售信息：底楼房源的花园为赠送，不单独计算花园价格。具体花园面积、使用规则和合同写法，需要按具体房号由销售团队确认。',
                'According to current sales information, gardens for ground-floor apartments are included as a gift, with no separate garden price. Garden size, use details and contractual wording should be confirmed by sales for the specific apartment.'
            );
        }

        if ($this->has_any($text, array('kutyafuttato', 'kutya', 'kisallat', 'allatbarat', 'dog park', 'pet park', 'pets', 'dog', '宠物公园', '宠物', '狗公园', '遛狗', '狗'))) {
            return $this->by_lang($lang,
                'Igen, a Harmat utca 22. közelében, körülbelül 200 méterre található kutyafuttató. Ez erős plusz azoknak, akik kisállattal költöznének; emellett az Óhegy park is kb. 600 méterre van.',
                '有的。Harmat utca 22. 附近约 200 米有宠物/遛狗公园，适合养宠家庭日常散步。Óhegy park 也在约 600 米范围内，周边绿地资源比较好。',
                'Yes. There is a nearby dog park about 200 m from Harmat utca 22, useful for residents with pets. Óhegy park is also about 600 m away.'
            );
        }

        if ($this->has_any($text, array('kornyek', 'kozelben', 'ohegy', 'iskola', 'egyetem', 'bevasarlas', 'kozlekedes', 'busz', 'villamos', 'onkormanyzat', 'polgarmesteri', 'nearby', 'surrounding', 'school', 'university', 'mall', 'shopping', 'transport', 'district office', '周边', '附近', '学校', '小学', '中学', '大学', '商场', '购物', '交通', '区政府', '政府'))) {
            return $this->by_lang($lang,
                'A környék főbb pontjai: kutyafuttató kb. 200 m, Óhegy park kb. 600 m, Kőbányai Szent László Gimnázium kb. 700 m, Kőbányai Harmat Általános Iskola kb. 1,2 km, Szent László tér / kerületi központ kb. 800 m, ÁRKÁD Budapest kb. 1,9 km, Sugár Üzletközpont kb. 2,1 km, KÖKI Terminál kb. 2,6 km. A távolságok tájékoztató jellegűek.',
                '周边重点配套：宠物公园约 200 米，Óhegy park 约 600 米，Kőbányai Szent László Gimnázium 约 700 米，Kőbányai Harmat Általános Iskola 约 1.2 公里，区中心/Szent László tér 约 800 米，ÁRKÁD Budapest 约 1.9 公里，Sugár 商场约 2.1 公里，KÖKI Terminál 约 2.6 公里。距离为参考值。',
                'Nearby highlights: dog park about 200 m, Óhegy park about 600 m, Kőbányai Szent László Gimnázium about 700 m, Kőbányai Harmat Általános Iskola about 1.2 km, Szent László tér / district centre about 800 m, ÁRKÁD Budapest about 1.9 km, Sugár shopping centre about 2.1 km, and KÖKI Terminál about 2.6 km. Distances are indicative.'
            );
        }

        if ($this->has_any($text, array('alaprajz', 'floorplan', 'floor plan', 'pdf', 'layout', 'virtualis', 'lakasvalaszto', '户型图', '平面图', 'pdf', '虚拟选房', '房号怎么看', '怎么看房号', '房源详情'))) {
            return $this->by_lang($lang,
                'A lakáskód felépítése például A1-F-L1: A1 épület, Fsz vagy emelet, L1 lakás. A részletes adatlap és alaprajz a lakás oldalán érhető el, a böngészéshez használható a Lakáskereső és a Virtuális lakásválasztó is.',
                '房号示例 A1-F-L1：A1 是楼栋，F/FSZ 表示底层，数字表示楼层，L1 是房号。每套房源详情页里可以看面积、参考价格和户型图/PDF；也可以通过“Lakáskereső”或“虚拟选房”按楼栋、楼层筛选。',
                'Apartment codes work like A1-F-L1: A1 is the building, F/FSZ or a number is the floor, and L1 is the unit. The apartment detail page contains area, indicative price and floor plan/PDF where available; you can also browse via the apartment search or virtual selector.'
            );
        }

        if ($this->has_any($text, array('elerheto', 'elerhetoseg', 'foglalhato', 'foglalas', 'statusz', 'szabad', 'available', 'availability', 'reserve', 'reservation', 'status', 'sold', 'hold', '可售', '还有吗', '能预订', '预订', '保留', '状态', '卖掉', '已售'))) {
            return $this->by_lang($lang,
                'Az elérhetőség gyorsan változhat. Ha megad egy konkrét lakáskódot, például A1-F-L1, meg tudom mutatni a jelenlegi adatbázis szerinti státuszt és irányárat; a foglalást és végleges elérhetőséget az értékesítés erősíti meg.',
                '可售状态会变化。如果你给我具体房号，例如 A1-F-L1，我可以按当前房源库显示状态、面积和参考金额；是否能预订、保留条件和最终可售状态需要销售确认。',
                'Availability can change quickly. If you provide a specific apartment code, for example A1-F-L1, I can show the current database status, area and indicative price; reservation and final availability must be confirmed by sales.'
            );
        }

        if ($this->has_any($text, array('befektetes', 'kiadas', 'befekteto', 'investment', 'investor', 'rental', 'rent out', 'yield', '投资', '出租', '租金', '回报', '收益'))) {
            return $this->by_lang($lang,
                'Befektetési szempontból általában a jó ár-értékű 1-2 szobás lakások, a közlekedési kapcsolatok és a könnyen érthető környék számítanak erősnek. Hozamot vagy bérleti díjat nem ígérek; ha megad árkeretet és szobaszámot, konkrét lakásokat tudok ajánlani az aktuális árlistából.',
                '投资角度通常更关注总价、面积效率、1-2 房、交通和周边配套。AI 不承诺租金或收益率；如果你告诉我预算和房间数，我可以按当前价格表推荐具体房号和金额。',
                'For investment, buyers often compare total price, efficient layout, 1-2 room flats, transport and easy-to-understand surroundings. I cannot promise rent or yield; if you share budget and room count, I can recommend specific units from the current price list.'
            );
        }

        if ($this->has_any($text, array('csalad', 'gyerek', 'gyerekes', 'sajat lakhat', 'family', 'children', 'kids', 'own use', 'live in', '自住', '家庭', '孩子', '小孩', '老人', '宠物家庭'))) {
            return $this->by_lang($lang,
                'Saját lakhatásra és családoknak a zöld környezet, az Óhegy park kb. 600 m-re, a közeli kutyafuttató kb. 200 m-re, valamint a környékbeli iskolák lehetnek fontos szempontok. Ilyenkor gyakran 2-4 szobás, kényelmesebb alapterületű lakásokat érdemes nézni.',
                '自住和家庭客户可以重点看：绿地环境、约 600 米的 Óhegy park、约 200 米的宠物公园，以及周边学校。通常 2-4 房、面积更舒适的户型更适合家庭，我可以按预算继续筛选。',
                'For own use and families, useful points include the green setting, Óhegy park about 600 m away, the dog park about 200 m away, and nearby schools. Families often compare 2-4 room flats with more comfortable area; I can filter by budget.'
            );
        }

        if ($this->has_any($text, array('hoszivattyu', 'futes', 'hutes', 'energia', 'zoldfelulet', 'uj epites', 'heating', 'cooling', 'heat pump', 'energy', 'green ratio', 'new build', '采暖', '制冷', '热泵', '新房', '绿化', '绿地', '配置'))) {
            return $this->by_lang($lang,
                'A projekt új építésű lakópark, a jelenlegi ismert fő pontok: 75% zöldfelület, hőszivattyús fűtés-hűtés, teremgarázs és tárolók. A műszaki tartalom részleteit és szerződéses vállalásait az értékesítési dokumentáció rögzíti.',
                '项目是新建住宅，当前已知重点包括：约 75% 绿化/绿地比例、热泵采暖制冷、地下车位和储藏室。具体技术配置、交付标准和合同承诺需以销售资料和正式文件为准。',
                'The project is a new-build residential development. Known highlights include about 75% green area, heat-pump heating/cooling, underground parking and storage units. Technical specifications and contractual commitments should be checked in the official sales documents.'
            );
        }

        if ($this->has_any($text, array('fejleszto', 'beruhazo', 'investor company', 'developer', 'company', '开发商', '投资方', '公司'))) {
            return $this->by_lang($lang,
                'A projekthez a weboldalon megjelenített fejlesztő / beruházó: Cooperation Power Kft. Szerződéses vagy cégjogi részletekben az értékesítés tud pontos tájékoztatást adni.',
                '网站显示的项目开发/投资方为 Cooperation Power Kft.。如涉及合同主体或公司法律信息，需要销售团队提供正式确认。',
                'The developer/investor shown on the website is Cooperation Power Kft. For contractual or corporate legal details, please ask the sales team for official confirmation.'
            );
        }

        if ($this->has_any($text, array('mit tudsz', 'miben segitesz', 'segitseg', 'help', 'what can you do', 'how can you help', 'assistant', '你能做什么', '你会什么', '怎么使用', '客服', 'ai功能', 'ai 功能'))) {
            return $this->by_lang($lang,
                'Lakáskeresésben, ár- és státuszellenőrzésben, alaprajzokban, környék-információban, fizetési irányokban és időpontkérésben tudok segíteni. A leggyorsabb út: írjon szobaszámot, árkeretet, alapterületet vagy konkrét lakáskódot, például A1-F-L1.',
                '我可以帮你做几件事：按预算/房间数/面积筛房源，查具体房号价格和状态，解释户型图、周边、付款方向，并引导预约或报价。最快的问法是告诉我：预算、房间数、面积，或直接给房号，例如 A1-F-L1。',
                'I can help with apartment search, indicative price/status checks, floor plans, surroundings, payment directions and viewing or offer requests. The fastest way is to share room count, budget, size, or a specific code such as A1-F-L1.'
            );
        }

        if ($this->has_any($text, array('vasarlas menete', 'vasarlasi folyamat', 'hogyan tudok vasarolni', 'kovetkezo lepes', 'buying process', 'purchase process', 'how to buy', 'next step', '买房流程', '购买流程', '怎么购买', '怎么买', '下一步', '购买步骤', '流程'))) {
            return $this->by_lang($lang,
                'A javasolt folyamat: 1. cél és árkeret tisztázása, 2. lakáskeresés szobaszám, méret és ár alapján, 3. konkrét lakás státuszának és tájékoztató árának ellenőrzése, 4. ajánlat- vagy időpontkérés, 5. foglalási és fizetési feltételek egyeztetése az értékesítéssel, 6. szerződéses dokumentáció. A végleges feltételeket mindig az értékesítés és a szerződés rögzíti.',
                '建议流程：1. 明确自住/投资、预算和房间数；2. 按面积、楼层、房号筛选；3. 确认目标房源的状态和参考金额；4. 预约看房或索取报价；5. 和销售确认保留、付款节奏和所需资料；6. 进入正式合同流程。最终条件以销售和正式文件为准。',
                'Suggested process: 1. clarify own-use/investment, budget and room count, 2. shortlist by size, floor and apartment code, 3. check status and indicative amount, 4. request an offer or viewing, 5. confirm reservation/payment details with sales, 6. proceed with contractual documents. Final terms are always set by sales and the contract.'
            );
        }

        if ($this->has_any($text, array('nem tudom melyik', 'melyiket valasszam', 'valasztasi logika', 'help me choose', 'not sure which', 'which one', 'choose', '怎么选', '不知道选', '帮我选', '选择逻辑', '推荐逻辑'))) {
            return $this->selection_guidance_answer($lang);
        }

        if ($this->has_any($text, array('elony', 'miert jo', 'miert erdemes', 'miert vegyek', 'osszehasonlit', 'advantage', 'benefit', 'why buy', 'why harmat', 'worth', 'compare', '优点', '优势', '为什么买', '值得买吗', '卖点', '对比'))) {
            return $this->by_lang($lang,
                'A Harmat Lakópark fő érvei: új építésű projekt Budapest X. kerületében, Harmat utca 22. cím, kb. 75% zöldfelület, hőszivattyús fűtés-hűtés, 1-5 szobás választék, teremgarázs és tároló lehetőség, Óhegy park kb. 600 m, kutyafuttató kb. 200 m, valamint több iskola és bevásárlási pont a közelben. Ha megad célt és árkeretet, konkrét lakásokat is össze tudok hasonlítani.',
                '项目主要优势：Budapest X 区 Harmat utca 22 的新建住宅项目，约 75% 绿化/绿地比例，热泵采暖制冷，1-5 房可选，地下车位和储藏室配套，Óhegy park 约 600 米，宠物公园约 200 米，周边有学校、商场和区中心。你告诉我用途和预算后，我可以进一步对比具体房源。',
                'Key strengths: a new-build project at Harmat utca 22 in Budapest District X, about 75% green area, heat-pump heating/cooling, 1-5 room options, garage/storage possibilities, Óhegy park about 600 m away, a dog park about 200 m away, plus nearby schools, shopping and district services. Share your goal and budget and I can compare specific units.'
            );
        }

        if ($this->has_any($text, array('tul draga', 'draga', 'olcsobbat', 'engedmeny', 'kedvezmeny', 'akcio', 'alku', 'too expensive', 'expensive', 'cheaper', 'discount', 'negotiate', '太贵', '贵了', '便宜点', '折扣', '优惠', '优惠价', '砍价', '讲价'))) {
            return $this->by_lang($lang,
                'Ha az árkeret szűk, érdemes először szobaszámot, minimális alapterületet és maximum keretet megadni. Így a jelenlegi listából a kerethez legközelebbi lakásokat tudom ajánlani. Kedvezményt vagy alkut nem ígérhetek; ezt konkrét lakásra az értékesítés erősíti meg.',
                '如果觉得价格偏高，建议先明确：最高预算、最少房间数、最低接受面积，以及是否必须要车位/储藏室。这样我可以按当前房源表找最接近预算的房号。AI 不承诺折扣或议价，具体优惠只能由销售按房号确认。',
                'If the budget feels tight, share your maximum budget, minimum room count, minimum size and whether parking/storage is needed. I can then suggest the closest matches from the current list. I cannot promise discounts or negotiation terms; sales must confirm them for a specific unit.'
            );
        }
        if ($this->has_any($text, array('hol', 'talalhato', 'cim', 'address', 'where', '位置', '地址'))) {
            return $this->by_lang($lang,
                'A Harmat Lakópark Budapest X. kerületében, a Harmat utca 22. szám alatt található. Ha szeretné, segítek lakást keresni szobaszám, méret vagy árkeret alapján.',
                'Harmat Lakópark 位于 Budapest X 区，地址是 Harmat utca 22。你可以告诉我预算、面积或房间数，我可以推荐合适房源。',
                'Harmat Lakópark is located at Harmat utca 22, Budapest District X. Tell me your budget, size or room preference and I can suggest apartments.'
            );
        }

        if ($this->has_any($text, array('atadas', 'hatarido', 'handover', 'delivery', '交付', '交房', '什么时候'))) {
            return $this->by_lang($lang,
                'Az első ütem várható átadása jelenlegi információink szerint 2028 második negyedéve. A pontos szerződéses határidőt az értékesítés erősíti meg.',
                '目前资料显示，第一期预计 2028 年第二季度交付。具体合同日期需要销售团队最终确认。',
                'The expected handover for the first phase is currently 2028 Q2. The contractual date should be confirmed by the sales team.'
            );
        }

        if ($this->has_any($text, array('fizetes', 'fizetesi', 'utemezes', 'reszlet', 'teljes fizetes', 'payment', 'pay', 'installment', 'schedule', '付款', '付款方式', '怎么付款', '分期', '全款', '首付', '50-50'))) {
            return $this->by_lang($lang,
                'A fizetési lehetőségekről az értékesítési csapat ad pontos tájékoztatást. Jelenleg egyeztethető irány lehet a teljes fizetés, az 50%-50% ütemezés vagy más részletfizetési ütemezés. A végleges fizetési határidőket és feltételeket mindig a szerződés rögzíti.',
                '付款方式需要由销售团队按房号和客户情况确认。通常可以沟通的方向包括全款、50%-50% 节奏，或其他分期付款安排；最终付款节点和条件以正式合同为准。',
                'Payment options should be confirmed by the sales team for the selected apartment and buyer situation. Possible discussion points may include full payment, a 50%-50% schedule, or another staged payment plan. Final dates and terms are contractual.'
            );
        }

        if ($this->has_any($text, array('hany lakas', 'osszesen', 'darab', 'how many', '多少套', '几套'))) {
            return $this->by_lang($lang,
                'A teljes projekt tervezetten 398 lakásból áll, az első ütemben 124 lakás szerepel. Az első ütemhez 124 teremgarázs-beálló és 92 tároló tartozik.',
                '项目总规划 398 套，第一期 124 套；第一期规划地下车位 124 个、储藏室 92 个。',
                'The full project is planned with 398 apartments, with 124 apartments in the first phase. The first phase includes 124 garage spaces and 92 storage units.'
            );
        }

        if ($this->has_any($text, array('parkolo', 'garazs', 'tarolo', 'parking', 'storage', '车位', '停车', '储藏'))) {
            return $this->by_lang($lang,
                'Az első ütemhez 124 teremgarázs-beálló és 92 tároló tartozik a jelenlegi projektadatok szerint. A parkoló és tároló aktuális elérhetőségét, árát és foglalási feltételeit az értékesítés erősíti meg; ha megad egy lakáskódot, az ajánlatkérésben együtt lehet egyeztetni.',
                '目前项目资料显示，第一期有 124 个地下车位和 92 个储藏室。车位/储藏室的实时可选情况、金额和预订条件需要销售确认；如果你已有目标房号，可以和房源报价一起咨询。',
                'The first phase currently includes 124 garage spaces and 92 storage units. Current availability, price and reservation terms for parking/storage should be confirmed by sales; if you have a target apartment code, they can be discussed together with the offer.'
            );
        }

        if ($this->has_any($text, array('hitel', 'bank', 'loan', 'mortgage', '贷款', '按揭'))) {
            return $this->by_lang($lang,
                'Új építésű lakásnál banki finanszírozás is szóba jöhet, de a hitelképesség, önerő, kamat és banki feltételek egyediek. Az asszisztens nem ígérhet hiteljóváhagyást; pontos választ az értékesítési csapat és a bank tud adni.',
                '新房通常可以考虑银行贷款，但贷款资格、首付比例、利率和银行条件因人而异。AI 不能承诺贷款获批或具体利率，准确方案需要销售团队和银行确认。',
                'Bank financing may be possible for new-build apartments, but eligibility, down payment, interest rate and bank terms are individual. The assistant cannot promise loan approval; details should be confirmed with sales and the bank.'
            );
        }

        if ($this->has_any($text, array('csok', 'tamogatas', 'subsidy', '补贴'))) {
            return $this->by_lang($lang,
                'A CSOK és egyéb támogatások jogosultsága személyes helyzettől és jogszabályoktól függ. Ebben nem szeretnék pontatlan ígéretet tenni, az értékesítés tud segíteni a pontos egyeztetésben.',
                'CSOK 或其他补贴取决于个人条件和法规，AI 不应承诺。建议联系销售团队确认。',
                'CSOK or other subsidies depend on personal eligibility and regulations. I should not make promises; the sales team can help verify details.'
            );
        }

        if ($this->has_any($text, array('idopont', 'megtekintes', 'ajanlat', 'ajanlatkeres', 'contact', 'appointment', 'visit', 'viewing', 'quote', 'offer', '预约', '看房', '联系', '报价', '询价'))) {
            return $this->by_lang($lang,
                'Szívesen segítünk ajánlatot vagy időpontot kérni. A gyors egyeztetéshez érdemes megadni: név, telefon vagy e-mail, kiválasztott lakáskód, szobaszám, árkeret és kívánt időpont. Elérhetőség: ertekesites@harmat22.hu, +36-30-641-03-58.',
                '可以预约看房或索取报价。为了销售更快回复，建议留下：姓名、电话或邮箱、目标房号、房间数、预算和方便的时间。联系方式：ertekesites@harmat22.hu，+36-30-641-03-58。',
                'We can help start an offer request or viewing appointment. For a faster reply, please provide: name, phone or email, target apartment code, room count, budget and preferred time. Contact: ertekesites@harmat22.hu, +36-30-641-03-58.'
            );
        }

        if ($this->has_any($text, array('kulfol', 'foreign', '中国人', '外国人', '海外'))) {
            return $this->by_lang($lang,
                'Külföldi vásárlók esetén a vásárlás feltételei állampolgárságtól és jogi körülményektől függhetnek. Jogilag pontos választ ügyvéd vagy az értékesítés tud adni.',
                '外国买家购买条件可能取决于国籍和法律情况，准确答案需要律师或销售团队确认。',
                'For foreign buyers, purchase conditions can depend on nationality and legal details. Please confirm with a lawyer or the sales team.'
            );
        }

        return null;
    }

    private function extract_filters($message, $normalized) {
        $filters = array(
            'rooms' => null,
            'budget' => null,
            'area' => null,
            'cheap' => false,
            'ground_floor' => false,
            'profile' => array(),
            'has_search' => false,
        );

        if ($this->has_any($normalized, array('ket szob', '2 szob', 'two room', 'two-room')) || preg_match('/(?:两|二|2)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 2;
        } elseif ($this->has_any($normalized, array('egy szob', '1 szob', 'one room', 'one-room')) || preg_match('/(?:一|1)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 1;
        } elseif ($this->has_any($normalized, array('harom szob', '3 szob', 'three room', 'three-room')) || preg_match('/(?:三|3)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 3;
        } elseif ($this->has_any($normalized, array('negy szob', '4 szob', 'four room', 'four-room')) || preg_match('/(?:四|4)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 4;
        } elseif ($this->has_any($normalized, array('ot szob', '5 szob', 'five room', 'five-room')) || preg_match('/(?:五|5)\s*(?:房|室|居|个房间)/u', $message)) {
            $filters['rooms'] = 5;
        } elseif (preg_match('/([1-5])\s*(szob|room|rooms|房|居)/iu', $message, $match)) {
            $filters['rooms'] = (int) $match[1];
        } elseif ($this->has_any($normalized, array('egy szob', '1 szob'))) {
            $filters['rooms'] = 1;
        } elseif ($this->has_any($normalized, array('ket szob', '2 szob'))) {
            $filters['rooms'] = 2;
        } elseif ($this->has_any($normalized, array('harom szob', '3 szob'))) {
            $filters['rooms'] = 3;
        } elseif ($this->has_any($normalized, array('negy szob', '4 szob'))) {
            $filters['rooms'] = 4;
        } elseif ($this->has_any($normalized, array('ot szob', '5 szob'))) {
            $filters['rooms'] = 5;
        }

        if (preg_match('/(\d{2,3}(?:[,.]\d+)?)\s*(?:m2|m²|nm|négyzet|平方米|平米|平)/iu', $message, $match)) {
            $filters['area'] = (float) str_replace(',', '.', $match[1]);
        }

        if (preg_match('/(\d{2,3}(?:[,.]\d+)?)\s*(?:millio|millió|million|m\b)/iu', $normalized, $match)) {
            $filters['budget'] = (int) round(((float) str_replace(',', '.', $match[1])) * 1000000);
        } elseif (preg_match('/(\d{3,5})\s*万/u', $message, $match)) {
            $filters['budget'] = ((int) $match[1]) * 10000;
        } elseif (preg_match('/(\d[\d\s]{6,})\s*(?:ft|huf)?/iu', $message, $match)) {
            $filters['budget'] = (int) preg_replace('/\D/', '', $match[1]);
        }

        $filters['cheap'] = $this->has_any($normalized, array('olcso', 'legolcsobb', 'cheap', 'cheapest', '便宜', '最低', '低价'));
        $filters['ground_floor'] = $this->has_any($normalized, array('foldszint', 'fsz', 'garden', 'ground floor', 'ground-floor', '底楼', '底层', '花园'));

        $filters['has_search'] = $filters['rooms'] || $filters['budget'] || $filters['area'] || $filters['cheap'] || $filters['ground_floor'] ||
            $this->has_any($normalized, array('ajanl', 'keres', 'lakast', 'lakas', 'recommend', 'available', 'budget', 'buy', '预算', '推荐', '买房', '房源'));

        return $filters;
    }

    private function extract_buyer_profile($message, $normalized) {
        return array(
            'investment' => $this->has_any($normalized, array('befektetes', 'kiadas', 'befekteto', 'investment', 'investor', 'rental', 'rent out', 'yield', '投资', '出租', '租金', '回报', '收益')),
            'family' => $this->has_any($normalized, array('csalad', 'gyerek', 'gyerekes', 'family', 'children', 'kids', 'child', '家庭', '孩子', '小孩', '老人', '宠物家庭')),
            'own_use' => $this->has_any($normalized, array('sajat lakhat', 'sajat cel', 'own use', 'live in', 'for myself', '自住', '自己住')),
            'pet' => $this->has_any($normalized, array('kutyafuttato', 'kutya', 'kisallat', 'allatbarat', 'dog', 'pet', 'pets', '宠物', '狗', '遛狗')),
            'garden' => $this->has_any($normalized, array('kert', 'garden', 'ground floor', 'ground-floor', '底楼', '底层', '花园', '赠送花园')),
            'price_sensitive' => $this->has_any($normalized, array('olcso', 'legolcsobb', 'tul draga', 'olcsobbat', 'cheap', 'cheapest', 'expensive', 'cheaper', 'budget', '低价', '便宜', '太贵', '预算', '低总价')),
        );
    }

    private function search_apartments($apartments, $filters) {
        $matches = array();
        foreach ($apartments as $apartment) {
            if ($filters['rooms'] && (int) ($apartment['rooms'] ?? 0) !== (int) $filters['rooms']) {
                continue;
            }
            if ($filters['budget'] && (int) ($apartment['price_huf'] ?? 0) > (int) $filters['budget']) {
                continue;
            }
            if (!empty($filters['ground_floor']) && (string) ($apartment['floor'] ?? '') !== 'Fsz') {
                continue;
            }
            $matches[] = $apartment;
        }

        if (!$matches) {
            return array();
        }

        usort($matches, function ($a, $b) use ($filters) {
            $sa = $this->buyer_match_score($a, $filters['profile'] ?? array());
            $sb = $this->buyer_match_score($b, $filters['profile'] ?? array());
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            if ($filters['area']) {
                $da = abs((float) $a['sales_area_m2'] - (float) $filters['area']);
                $db = abs((float) $b['sales_area_m2'] - (float) $filters['area']);
                if ($da !== $db) {
                    return $da <=> $db;
                }
            }

            if ($filters['budget']) {
                $da = abs((int) $filters['budget'] - (int) ($a['price_huf'] ?? 0));
                $db = abs((int) $filters['budget'] - (int) ($b['price_huf'] ?? 0));
                if ($da !== $db) {
                    return $da <=> $db;
                }
            }

            return (int) $a['price_huf'] <=> (int) $b['price_huf'];
        });

        return array_slice($matches, 0, 8);
    }

    private function buyer_match_score($item, $profile) {
        $score = 0;
        $rooms = (int) ($item['rooms'] ?? 0);
        $area = (float) ($item['sales_area_m2'] ?? 0);
        $price = (int) ($item['price_huf'] ?? 0);
        $sqm = (int) ($item['sqm_price_huf'] ?? 0);
        $floor = (string) ($item['floor'] ?? '');

        if (!empty($profile['investment'])) {
            $score += ($rooms <= 2) ? 6 : 0;
            $score += ($area > 0 && $area <= 55) ? 3 : 0;
            $score += ($price > 0 && $price <= 70000000) ? 3 : 0;
            $score += ($sqm > 0 && $sqm <= 1400000) ? 2 : 0;
        }
        if (!empty($profile['family'])) {
            $score += ($rooms >= 3) ? 6 : 0;
            $score += ($area >= 70) ? 3 : 0;
            $score += ($rooms >= 4) ? 2 : 0;
        }
        if (!empty($profile['own_use'])) {
            $score += ($rooms >= 2 && $rooms <= 4) ? 3 : 0;
            $score += ($area >= 55) ? 2 : 0;
        }
        if (!empty($profile['pet'])) {
            $score += ($floor === 'Fsz') ? 2 : 0;
        }
        if (!empty($profile['price_sensitive'])) {
            $score += ($price > 0 && $price <= 65000000) ? 5 : 0;
            $score += ($sqm > 0 && $sqm <= 1400000) ? 2 : 0;
            $score += ($rooms <= 2) ? 1 : 0;
        }

        return $score;
    }

    private function selection_guidance_answer($lang) {
        return $this->by_lang($lang,
            'Szívesen ajánlok lakást, de pontosabb lesz, ha előbb 3-4 szempontot megad: saját lakhatás vagy befektetés, hány szoba, körülbelüli árkeret, kívánt alapterület vagy emelet, illetve kell-e parkoló/tároló. Példa: "2 szobás lakás 70 millió Ft körül" vagy "3 szobás saját lakhatásra".',
            '可以，我先按购房逻辑帮你缩小范围。请尽量告诉我 3-4 个条件：自住还是投资、几房、预算、希望面积或楼层、是否需要车位/储藏室。比如可以直接问：“7000 万福林左右两房推荐”或“自住三房有哪些”。',
            'I can recommend units more accurately if you share 3-4 points first: own-use or investment, room count, approximate budget, preferred size or floor, and whether parking/storage is needed. For example: "2-room flat around 70 million Ft" or "3-room for own use".'
        );
    }

    private function recommendation_answer($matches, $filters, $lang) {
        $top = array_slice($matches, 0, 3);
        $lines = array();
        foreach ($top as $item) {
            $lines[] = $this->recommendation_line($item, $lang, $filters['profile'] ?? array());
        }

        $prefix = $this->by_lang($lang,
            'A jelenlegi árlista alapján ezeket ajánlom:',
            '根据当前价格表，我先推荐这些：',
            'Based on the current price list, I would suggest these options:'
        );
        $profile_note = $this->profile_note($filters['profile'] ?? array(), $lang);

        $suffix = $this->by_lang($lang,
            'Az árak tájékoztató jellegűek; végleges elérhetőséget és árat az értékesítés erősít meg.',
            '以上价格为当前资料参考价，最终可售状态和价格需要销售团队确认。',
            'Prices are indicative; final availability and price should be confirmed by sales.'
        );

        return $prefix . ($profile_note ? "\n" . $profile_note : '') . "\n\n" . implode("\n", $lines) . "\n\n" . $suffix;
    }

    private function recommendation_line($item, $lang, $profile = array()) {
        $sqm = $this->format_money($item['sqm_price_huf'] ?? 0) . ' / m²';
        $status = (string) ($item['status'] ?? '');
        $tags = $this->apartment_tags($item, $profile, $lang);
        $tag_text = $tags ? $this->tag_prefix($lang) . implode($this->tag_separator($lang), $tags) . $this->sentence_end($lang) : '';

        if ($lang === 'zh') {
            return sprintf(
                '%s：%s，%s，%s，销售面积 %s m²，参考总价 %s，参考单价 %s，状态：%s。%s',
                $item['apartment'],
                $item['building'],
                $this->floor_label($item['floor'], 'zh'),
                $this->room_label($item, 'zh'),
                $this->format_area($item['sales_area_m2']),
                $this->format_money($item['price_huf']),
                $sqm,
                $status,
                $tag_text
            );
        }

        if ($lang === 'en') {
            return sprintf(
                '%s: building %s, %s, %s, %s m² sales area, indicative total price %s, indicative sqm price %s, status: %s. %s',
                $item['apartment'],
                $item['building'],
                $this->floor_label($item['floor'], 'en'),
                $this->room_label($item, 'en'),
                $this->format_area($item['sales_area_m2']),
                $this->format_money($item['price_huf']),
                $sqm,
                $status,
                $tag_text
            );
        }

        return sprintf(
            '%s: %s épület, %s, %s, %s m² eladási terület, tájékoztató teljes ár %s, négyzetméterár %s, státusz: %s. %s',
            $item['apartment'],
            $item['building'],
            $this->floor_label($item['floor'], 'hu'),
            $this->room_label($item, 'hu'),
            $this->format_area($item['sales_area_m2']),
            $this->format_money($item['price_huf']),
            $sqm,
            $status,
            $tag_text
        );
    }

    private function profile_note($profile, $lang) {
        if (empty(array_filter($profile))) {
            return '';
        }
        if (!empty($profile['investment'])) {
            return $this->by_lang($lang,
                'Befektetési szándéknál előrébb sorolom a kompaktabb, alacsonyabb teljes árú és kedvezőbb négyzetméterárú lakásokat.',
                '你提到投资，我会优先看总价、面积效率、1-2 房和相对友好的单价。',
                'For investment intent, I prioritize compact units with lower total price and more favorable sqm price.'
            );
        }
        if (!empty($profile['pet'])) {
            return $this->by_lang($lang,
                'Kisállatos szempontnál fontos, hogy a közelben kb. 200 m-re kutyafuttató található; lakásszinten csak a valós emeletadat alapján súlyozok.',
                '你提到宠物，我会结合项目附近约 200 米宠物公园这一点；单套房源只按真实楼层信息做轻微排序，不会编花园或朝向。',
                'For pet-related searches, the nearby dog park about 200 m away is useful; at apartment level I only weigh real floor data and do not invent garden or orientation details.'
            );
        }
        if (!empty($profile['family'])) {
            return $this->by_lang($lang,
                'Családi használatnál előrébb sorolom a több szobás és kényelmesebb alapterületű lakásokat.',
                '你提到家庭/自住，我会优先看房间数更够用、面积更舒适的户型。',
                'For family use, I prioritize more rooms and more comfortable floor area.'
            );
        }
        if (!empty($profile['own_use'])) {
            return $this->by_lang($lang,
                'Saját lakhatásnál az alapterület, a szobaszám és a hosszabb távú használhatóság kap nagyobb súlyt.',
                '你提到自住，我会更重视面积、房间数和长期使用舒适度。',
                'For own use, I give more weight to size, room count and long-term usability.'
            );
        }
        if (!empty($profile['garden'])) {
            return $this->by_lang($lang,
                'Kertes vagy földszinti keresésnél csak a valós földszinti lakásokat sorolom előre; a földszinti kert a jelenlegi információ szerint ajándék.',
                '你提到底楼/花园，我会优先显示真实底楼房源；目前信息为底楼花园赠送，不单独计算花园价格。',
                'For garden or ground-floor searches, I prioritize real ground-floor units; current information says the ground-floor garden is included as a gift.'
            );
        }
        if (!empty($profile['price_sensitive'])) {
            return $this->by_lang($lang,
                'Árérzékeny keresésnél az alacsonyabb teljes ár és a kedvezőbb négyzetméterár kerül előre.',
                '你提到预算/价格敏感，我会优先看低总价和相对友好的单价。',
                'For budget-sensitive searches, I prioritize lower total price and more favorable sqm price.'
            );
        }
        return '';
    }

    private function apartment_answer($item, $lang, $profile = array()) {
        $floor_hu = $this->floor_label($item['floor'], 'hu');
        $floor_zh = $this->floor_label($item['floor'], 'zh');
        $floor_en = $this->floor_label($item['floor'], 'en');
        $detail_url = (string) ($item['property_url'] ?? '');
        $floorplan_url = (string) ($item['floorplan_pdf'] ?? '');
        $tags_hu = $this->tags_sentence($item, $profile, 'hu');
        $tags_zh = $this->tags_sentence($item, $profile, 'zh');
        $tags_en = $this->tags_sentence($item, $profile, 'en');
        $hu = sprintf(
            '%s jelenlegi adatai: %s épület, %s, %s, %s m² eladási terület. Tájékoztató teljes ár: %s. Négyzetméterár: %s. Státusz: %s.%s Adatlap: %s. Alaprajz/PDF: %s. A végleges árat és elérhetőséget az értékesítés erősíti meg.',
            $item['apartment'],
            $item['building'],
            $floor_hu,
            $this->room_label($item, 'hu'),
            $this->format_area($item['sales_area_m2']),
            $this->format_money($item['price_huf']),
            $this->format_money($item['sqm_price_huf']) . ' / m²',
            (string) ($item['status'] ?? ''),
            $tags_hu ? ' ' . $tags_hu : '',
            $detail_url,
            $floorplan_url
        );
        $zh = sprintf(
            '%s 当前资料：%s 楼，%s，%s，销售面积 %s m²。参考总价：%s；参考单价：%s / m²。状态：%s。%s详情页：%s。户型图/PDF：%s。最终价格和可售状态请以销售团队确认为准。',
            $item['apartment'],
            $item['building'],
            $floor_zh,
            $this->room_label($item, 'zh'),
            $this->format_area($item['sales_area_m2']),
            $this->format_money($item['price_huf']),
            $this->format_money($item['sqm_price_huf']),
            (string) ($item['status'] ?? ''),
            $tags_zh ? $tags_zh . ' ' : '',
            $detail_url,
            $floorplan_url
        );
        $en = sprintf(
            '%s current data: building %s, %s, %s, %s m² sales area. Indicative total price: %s. Indicative sqm price: %s / m². Status: %s.%s Detail page: %s. Floor plan/PDF: %s. Final availability and price should be confirmed by sales.',
            $item['apartment'],
            $item['building'],
            $floor_en,
            $this->room_label($item, 'en'),
            $this->format_area($item['sales_area_m2']),
            $this->format_money($item['price_huf']),
            $this->format_money($item['sqm_price_huf']),
            (string) ($item['status'] ?? ''),
            $tags_en ? ' ' . $tags_en : '',
            $detail_url,
            $floorplan_url
        );

        return $this->by_lang($lang, $hu, $zh, $en);
    }

    private function cards_for_matches($matches, $profile) {
        $cards = array();
        foreach (array_slice($matches, 0, 4) as $item) {
            $cards[] = $this->card($item, $profile);
        }
        return $cards;
    }

    private function card($item, $profile = array()) {
        $tags = $this->apartment_tags($item, $profile, 'hu');
        $tag_text = $tags ? ' · ' . implode(', ', array_slice($tags, 0, 2)) : '';
        return array(
            'title' => (string) $item['apartment'],
            'url' => (string) $item['property_url'],
            'meta' => sprintf('%s · %s szoba · %s m² · %s · %s / m²%s', $item['building'], $item['rooms'], $this->format_area($item['sales_area_m2']), $this->format_money($item['price_huf']), $this->format_money($item['sqm_price_huf']), $tag_text),
        );
    }

    private function tags_sentence($item, $profile, $lang) {
        $tags = $this->apartment_tags($item, $profile, $lang);
        if (!$tags) {
            return '';
        }
        return $this->tag_prefix($lang) . implode($this->tag_separator($lang), $tags) . $this->sentence_end($lang);
    }

    private function apartment_tags($item, $profile, $lang) {
        $tags = array();
        $rooms = (int) ($item['rooms'] ?? 0);
        $area = (float) ($item['sales_area_m2'] ?? 0);
        $price = (int) ($item['price_huf'] ?? 0);
        $sqm = (int) ($item['sqm_price_huf'] ?? 0);
        $floor = (string) ($item['floor'] ?? '');

        if ($floor === 'Fsz') {
            $tags[] = $this->by_lang($lang, 'ajándék kert', '底楼赠送花园', 'gift garden');
        }
        if (!empty($profile['investment']) && $rooms <= 2) {
            $tags[] = $this->by_lang($lang, 'befektetési shortlist', '投资优先', 'investment shortlist');
        }
        if (!empty($profile['family']) && $rooms >= 3) {
            $tags[] = $this->by_lang($lang, 'családi jelölt', '家庭候选', 'family candidate');
        }
        if (!empty($profile['own_use']) && $rooms >= 2 && $area >= 55) {
            $tags[] = $this->by_lang($lang, 'saját lakhatásra is kényelmes', '自住舒适', 'comfortable for own use');
        }
        if (!empty($profile['pet']) && $floor === 'Fsz') {
            $tags[] = $this->by_lang($lang, 'földszinti elérés', '底层出入方便', 'ground-floor access');
        }
        if ($price > 0 && $price <= 65000000) {
            $tags[] = $this->by_lang($lang, 'alacsonyabb teljes ár', '低总价', 'lower total price');
        }
        if ($rooms <= 2 && $area > 0 && $area <= 55) {
            $tags[] = $this->by_lang($lang, 'kompakt alapterület', '紧凑户型', 'compact layout');
        }
        if ($rooms >= 3 && $area >= 60) {
            $tags[] = $this->by_lang($lang, 'több szobás használat', '多房间实用', 'multi-room usability');
        }
        if ($rooms >= 4 || $area >= 79) {
            $tags[] = $this->by_lang($lang, 'nagyobb családi méret', '大户型', 'larger family size');
        }
        if ($sqm > 0 && $sqm <= 1400000) {
            $tags[] = $this->by_lang($lang, 'kedvezőbb m² ár', '单价较友好', 'more favorable sqm price');
        }
        return array_slice(array_values(array_unique($tags)), 0, 3);
    }

    private function tag_prefix($lang) {
        return $this->by_lang($lang, 'AI címkék: ', 'AI 标签：', 'AI tags: ');
    }

    private function tag_separator($lang) {
        return $lang === 'zh' ? '、' : ', ';
    }

    private function sentence_end($lang) {
        return $lang === 'zh' ? '。' : '.';
    }

    private function floor_label($floor, $lang) {
        $floor = (string) $floor;
        if ($floor === 'Fsz') {
            return $this->by_lang($lang, 'Fsz', '底层', 'ground floor');
        }
        if ($lang === 'zh') {
            return $floor . ' 楼';
        }
        if ($lang === 'en') {
            return 'floor ' . $floor;
        }
        return $floor . '. emelet';
    }

    private function room_label($item, $lang) {
        $rooms = (int) ($item['rooms'] ?? 0);
        if ($lang === 'zh') {
            return $rooms . ' 房';
        }
        if ($lang === 'en') {
            return $rooms . ' room' . ($rooms === 1 ? '' : 's');
        }
        return $rooms . ' szobás';
    }

    private function format_money($value) {
        return number_format((float) $value, 0, ',', ' ') . ' Ft';
    }

    private function format_area($value) {
        return number_format((float) $value, 2, ',', ' ');
    }

    private function has_price_intent($text) {
        return $this->has_any($text, array('ar', 'ara', 'arak', 'mennyibe', 'price', 'prices', 'amount', 'cost', 'huf', 'ft', '多少钱', '价格', '价钱', '金额', '总价', '单价', '预算'));
    }

    private function price_overview_answer($apartments, $lang) {
        $ranges = $this->price_ranges_by_room($apartments);
        if (!$ranges) {
            return $this->by_lang($lang,
                'Az árak elérhetőségét az értékesítés tudja pontosan megerősíteni.',
                '价格需要销售团队根据实时房源确认。',
                'Prices should be confirmed by the sales team based on current availability.'
            );
        }

        $lines = array();
        foreach ($ranges as $rooms => $range) {
            if ($lang === 'zh') {
                $lines[] = sprintf(
                    '%d 房：%d 套，面积 %s-%s m²，参考总价 %s-%s，参考单价 %s-%s / m²。',
                    $rooms,
                    $range['count'],
                    $this->format_area($range['min_area']),
                    $this->format_area($range['max_area']),
                    $this->format_money($range['min_price']),
                    $this->format_money($range['max_price']),
                    $this->format_money($range['min_sqm']),
                    $this->format_money($range['max_sqm'])
                );
                continue;
            }

            if ($lang === 'en') {
                $lines[] = sprintf(
                    '%d-room: %d flats, %s-%s m², indicative total price %s-%s, sqm price %s-%s / m².',
                    $rooms,
                    $range['count'],
                    $this->format_area($range['min_area']),
                    $this->format_area($range['max_area']),
                    $this->format_money($range['min_price']),
                    $this->format_money($range['max_price']),
                    $this->format_money($range['min_sqm']),
                    $this->format_money($range['max_sqm'])
                );
                continue;
            }

            $lines[] = sprintf(
                '%d szobás: %d lakás, %s-%s m², tájékoztató teljes ár %s-%s, négyzetméterár %s-%s / m².',
                $rooms,
                $range['count'],
                $this->format_area($range['min_area']),
                $this->format_area($range['max_area']),
                $this->format_money($range['min_price']),
                $this->format_money($range['max_price']),
                $this->format_money($range['min_sqm']),
                $this->format_money($range['max_sqm'])
            );
        }

        $prefix = $this->by_lang($lang,
            'A jelenlegi árlistában ezek a fő sávok látszanak:',
            '当前价格表里的主要区间如下：',
            'The current price list shows these main ranges:'
        );
        $suffix = $this->by_lang($lang,
            'Az összegek tájékoztató jellegűek; a végleges árat és elérhetőséget az értékesítés erősíti meg.',
            '以上金额为当前资料参考，最终价格和可售状态需要销售团队确认。',
            'Amounts are indicative; final price and availability should be confirmed by sales.'
        );

        return $prefix . "\n\n" . implode("\n", $lines) . "\n\n" . $suffix;
    }

    private function price_ranges_by_room($apartments) {
        $ranges = array();
        foreach ($apartments as $item) {
            $rooms = (int) ($item['rooms'] ?? 0);
            $price = (int) ($item['price_huf'] ?? 0);
            if ($rooms < 1 || $price <= 0) {
                continue;
            }

            if (!isset($ranges[$rooms])) {
                $ranges[$rooms] = array(
                    'count' => 0,
                    'min_price' => $price,
                    'max_price' => $price,
                    'min_area' => (float) ($item['sales_area_m2'] ?? 0),
                    'max_area' => (float) ($item['sales_area_m2'] ?? 0),
                    'min_sqm' => (int) ($item['sqm_price_huf'] ?? 0),
                    'max_sqm' => (int) ($item['sqm_price_huf'] ?? 0),
                );
            }

            $ranges[$rooms]['count'] += 1;
            $ranges[$rooms]['min_price'] = min($ranges[$rooms]['min_price'], $price);
            $ranges[$rooms]['max_price'] = max($ranges[$rooms]['max_price'], $price);
            $ranges[$rooms]['min_area'] = min($ranges[$rooms]['min_area'], (float) ($item['sales_area_m2'] ?? 0));
            $ranges[$rooms]['max_area'] = max($ranges[$rooms]['max_area'], (float) ($item['sales_area_m2'] ?? 0));
            $ranges[$rooms]['min_sqm'] = min($ranges[$rooms]['min_sqm'], (int) ($item['sqm_price_huf'] ?? 0));
            $ranges[$rooms]['max_sqm'] = max($ranges[$rooms]['max_sqm'], (int) ($item['sqm_price_huf'] ?? 0));
        }

        ksort($ranges);
        return $ranges;
    }

    private function has_any($text, $needles) {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($text, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    private function by_lang($lang, $hu, $zh, $en) {
        if ($lang === 'zh') {
            return $zh;
        }
        if ($lang === 'en') {
            return $en;
        }
        return $hu;
    }

    private function text($key, $lang) {
        $texts = array(
            'empty' => array(
                'hu' => 'Kérem, írja be kérdését.',
                'zh' => '请输入你的问题。',
                'en' => 'Please enter your question.',
            ),
            'no_match' => array(
                'hu' => 'Erre a szűrésre most nem találtam megfelelő lakást. Próbáljon más árkeretet, szobaszámot vagy alapterületet megadni.',
                'zh' => '按这个条件暂时没有找到合适房源。可以换一个预算、房间数或面积范围。',
                'en' => 'I could not find a matching apartment for these filters. Try another budget, room count or size.',
            ),
            'fallback' => array(
                'hu' => 'Ebben tudok segíteni: lakáskeresés szobaszám, árkeret vagy alapterület alapján; konkrét lakás ára, például A1-F-L1; vásárlási folyamat, alaprajz, átadás, környék, parkoló, tároló, fizetés és időpontkérés.',
                'zh' => '我可以帮你查：按预算/房间数/面积推荐房源；查询具体房号价格，比如 A1-F-L1；也可以回答买房流程、户型图、交付时间、周边、车位、储藏室、付款方式和预约问题。',
                'en' => 'I can help with apartment search by room count, budget or size; specific apartment prices such as A1-F-L1; buying process, floor plans, handover, surroundings, parking, storage, payment and appointments.',
            ),
        );

        return $texts[$key][$lang] ?? $texts[$key]['hu'];
    }

    private function default_suggestions($lang) {
        if ($lang === 'zh') {
            return array('买房流程是什么？', '7000万两房推荐', '适合投资吗？', '怎么预约看房？');
        }
        if ($lang === 'en') {
            return array('Buying process?', '2-room around 70M Ft', 'Good for investment?', 'Book a viewing');
        }
        return array('Vásárlás menete?', '2 szobás 70 millió Ft körül', 'Befektetésre jó?', 'Időpontot kérek');
    }
}

$GLOBALS['harmat_local_assistant'] = new Harmat_Local_Assistant();

