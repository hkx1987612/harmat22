<?php
/**
 * Plugin Name: Harmat Home Apartment Rotation
 * Description: Homepage-only progressive rotation of six public apartment cards.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function harmat_har_url($value, $image = false): bool {
    if (!is_string($value) || preg_match('/[\x00-\x20\x7f]/', $value)) return false;
    $parts = parse_url($value);
    return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
        && ($parts['host'] ?? '') === 'harmat22.hu'
        && !isset($parts['port'])
        && !isset($parts['user']) && !isset($parts['pass'])
        && !isset($parts['query']) && !isset($parts['fragment'])
        && strpos($parts['path'] ?? '', $image ? '/wp-content/' : '/property/') === 0;
}

function harmat_har_inventory(): array {
    if (!function_exists('harmat_sai_property_summary_data')
        || !function_exists('hm_migrated_property_floorplan_image_from_uploads')) return array();
    try {
        $posts = get_posts(array('post_type'=>'property','post_status'=>'publish','has_password'=>false,
            'numberposts'=>501,'fields'=>'all','orderby'=>'title','order'=>'ASC','cache_results'=>true,
            'update_post_meta_cache'=>true,'update_post_term_cache'=>false,'suppress_filters'=>true));
        if (!is_array($posts) || count($posts)>500) return array();
        $rows = array();
        foreach ($posts as $post) {
            if (!($post instanceof WP_Post)) return array();
            if ($post->post_type!=='property' || $post->post_status!=='publish' || $post->post_password!=='') continue;
            $data = harmat_sai_property_summary_data((int)$post->ID);
            if (!is_array($data) || ($data['status'] ?? '')!=='current') continue;
            $code = $data['title'] ?? null;
            if (!is_string($code) || !preg_match('/\AA[1-4]-(F|[1-9])-L[0-9]+\z/D',$code)) continue;
            if (isset($rows[$code])) return array();
            foreach (array('rooms'=>20,'sales_area'=>2000) as $key=>$max) {
                $n = $data[$key] ?? null;
                if ((!is_int($n) && !is_float($n)) || !is_finite((float)$n) || $n<=0 || $n>$max) continue 2;
            }
            if (!isset($data['hide_price'],$data['price']) || !is_bool($data['hide_price'])
                || !is_int($data['price']) || $data['price']>1000000000000) continue;
            $url = get_permalink($post->ID);
            $image = hm_migrated_property_floorplan_image_from_uploads($code,get_post_meta($post->ID,'property_floorplan',true));
            if (!harmat_har_url($url) || !harmat_har_url($image,true)) continue;
            $rows[$code] = array('code'=>$code,'url'=>$url,'image'=>$image,'rooms'=>$data['rooms'],
                'area'=>$data['sales_area'],'price'=>!$data['hide_price'] && $data['price']>0 ? $data['price'] : null);
        }
        $ordered = array();
        foreach (array('A1-1-L6','A2-4-L5','A1-1-L5','A3-F-L3','A4-F-L1','A3-2-L2') as $code) {
            if (!isset($rows[$code])) return array();
            $ordered[] = $rows[$code];
            unset($rows[$code]);
        }
        return count($rows)>0 ? array_merge($ordered,array_values($rows)) : array();
    } catch (Throwable $error) {
        return array();
    }
}

function harmat_har_footer(): void {
    static $emitted = false;
    if ($emitted || !function_exists('harmat_home_assistant_inline_context')
        || !harmat_home_assistant_inline_context()) return;
    $rows = harmat_har_inventory();
    if (!$rows) return;
    $json = json_encode($rows,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || strlen($json)>350000) return;
    $emitted = true;
    echo '<script type="application/json" id="harmat-home-rotation-data">'.$json.'</script>';
    echo <<<'HHAR'
<style id="harmat-home-rotation-style">
#harmat-home-family #hhf-lakasok .hhar-controls{display:flex;justify-content:flex-end;align-items:center;gap:8px;max-width:100%;margin:0 0 18px}
#harmat-home-family #hhf-lakasok .hhar-controls button{display:inline-flex;justify-content:center;align-items:center;width:44px;height:44px;min-width:44px;flex:0 0 44px;margin:0;padding:10px;border:1px solid #bccbc1;border-radius:4px;background:#fff;color:#214638;box-shadow:none}
#harmat-home-family #hhf-lakasok .hhar-controls button:hover{background:#e9f0eb}
#harmat-home-family #hhf-lakasok .hhar-controls button:focus-visible{outline:2px solid #214638;outline-offset:3px}
#harmat-home-family #hhf-lakasok .hhar-controls svg{display:block;width:20px;height:20px}
#harmat-home-family #hhf-lakasok .hhar-controls .hhar-prev svg{transform:rotate(180deg)}
#harmat-home-family #hhf-lakasok .hhar-counter{min-width:60px;text-align:center;font:500 14px/1.4 Arial,sans-serif;font-variant-numeric:tabular-nums;color:#214638}
#harmat-home-family #hhf-lakasok[data-hhar-ready] .hhf-apartment-floor{min-height:30px}
#harmat-home-family #hhf-lakasok[data-hhar-ready] .hhf-price strong{min-height:40px;white-space:normal;overflow-wrap:anywhere}
#harmat-home-family #hhf-lakasok[data-hhar-ready] .hhf-price strong span{white-space:nowrap}
</style>
<script id="harmat-home-rotation-script">
(() => {
  'use strict';
  function init() {
    const one = (scope, selector) => {
      const nodes = scope.querySelectorAll(selector);
      return nodes.length === 1 ? nodes[0] : null;
    };
    const root = one(document, '#harmat-home-family');
    const section = root && one(root, '#hhf-lakasok');
    const grid = section && one(section, '#hhf-apartment-grid');
    const data = one(document, '#harmat-home-rotation-data');
    if (!root || !section || !grid || !data || section.hasAttribute('data-hhar-ready')
        || section.closest('form') || !root.dataset.homeFamilyReady || !window.matchMedia) return;
    const cards = Array.from(grid.children);
    if (cards.length !== 6 || cards.some(card => !card.matches('article.hhf-apartment[data-status="current"]'))) return;
    let rows;
    try { rows = JSON.parse(data.textContent); } catch { return; }
    const safeUrl = (value, image) => {
      if (typeof value !== 'string' || /[\x00-\x20\x7f]/.test(value)) return false;
      try {
        const u = new URL(value);
        return u.origin === 'https://harmat22.hu' && !u.username && !u.password && !u.search && !u.hash
          && u.pathname.startsWith(image ? '/wp-content/' : '/property/');
      } catch { return false; }
    };
    if (!Array.isArray(rows) || rows.length < 7 || rows.length > 500
        || new Set(rows.map(row => row && row.code)).size !== rows.length
        || rows.some(row => !row || typeof row.code !== 'string' || !/^A[1-4]-(F|[1-9])-L\d+$/.test(row.code)
          || !safeUrl(row.url, false) || !safeUrl(row.image, true)
          || !Number.isFinite(row.rooms) || row.rooms <= 0 || row.rooms > 20
          || !Number.isFinite(row.area) || row.area <= 0 || row.area > 2000
          || !(row.price === null || Number.isSafeInteger(row.price) && row.price > 0 && row.price <= 1e12))) return;
    const fields = cards.map((card, index) => ({
      card, title:one(card,'h3'),status:one(card,'.hhf-status'),floor:one(card,'.hhf-apartment-floor'),
      image:one(card,'.hhf-floorplan img'),plan:one(card,'a.hhf-floorplan'),detail:one(card,'a.hhf-apartment-link'),
      rooms:one(card,'.hhf-apartment-facts>div:first-child dd'),area:one(card,'.hhf-apartment-facts>div:last-child dd'),
      price:one(card,'.hhf-price strong'),index
    }));
    if (fields.some(f => Object.values(f).some(value => value === null)
        || f.card.dataset.code !== rows[f.index].code || f.plan.href !== rows[f.index].url
        || f.detail.href !== rows[f.index].url)) return;
    // Reuse the site's existing Lucide icons; do not introduce another icon dependency.
    const arrow = one(root,'.hhf-hero-booking svg');
    const pauseIcon = one(root,'.hhf-video-pause-icon');
    const playIcon = one(root,'.hhf-video-play-icon');
    if (!arrow || !pauseIcon || !playIcon || typeof IntersectionObserver !== 'function') return;
    const controls = document.createElement('div');
    controls.className = 'hhar-controls';
    controls.setAttribute('role','group');
    const button = (name, icon) => {
      const node = document.createElement('button');
      node.type = 'button'; node.className = 'hhar-'+name;
      node.setAttribute('aria-controls','hhf-apartment-grid');
      const svg = icon.cloneNode(true); svg.removeAttribute('class'); svg.removeAttribute('hidden');
      node.appendChild(svg); return node;
    };
    const prev = button('prev',arrow), next = button('next',arrow), toggle = button('toggle',pauseIcon);
    const counter = document.createElement('span'); counter.className = 'hhar-counter';
    counter.setAttribute('aria-live','off');
    controls.append(prev,counter,next,toggle);
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pages = Math.ceil(rows.length / 6);
    let page = 0, paused = motion.matches, hover = false, visible = false, timer = null;
    const isZh = () => root.lang === 'zh-Hans';
    const number = (n, digits = 0) => new Intl.NumberFormat(isZh() ? 'zh-CN' : 'hu-HU',{
      minimumFractionDigits:digits, maximumFractionDigits:digits
    }).format(n);
    function label(node, text) { node.setAttribute('aria-label',text); node.title = text; }
    function updateControls() {
      controls.setAttribute('aria-label',isZh() ? '户型切换' : 'Lakások váltása');
      label(prev,isZh() ? '上一组户型' : 'Előző lakások');
      label(next,isZh() ? '下一组户型' : 'Következő lakások');
      label(toggle,isZh() ? paused ? '开始自动轮换' : '暂停自动轮换'
        : paused ? 'Automatikus váltás indítása' : 'Automatikus váltás szüneteltetése');
      toggle.setAttribute('aria-pressed',String(!paused));
      const icon = (paused ? playIcon : pauseIcon).cloneNode(true);
      icon.removeAttribute('class'); icon.removeAttribute('hidden'); toggle.replaceChildren(icon);
      counter.textContent = (page+1)+' / '+pages;
      counter.setAttribute('aria-label',isZh() ? `第 ${page+1} 组，共 ${pages} 组` : `${page+1}. csoport / ${pages}`);
    }
    function render() {
      const start = Math.min(page*6,rows.length-6);
      fields.forEach((f,i) => {
        const row = rows[start+i], parts = row.code.split('-'), floor = parts[1];
        f.card.dataset.code = row.code;
        f.title.textContent = row.code;
        f.status.textContent = isZh() ? '可售' : 'Elérhető';
        f.floor.textContent = isZh() ? `${parts[0]} 栋 · ${floor === 'F' ? '底层' : floor+' 层'}`
          : `${parts[0]} épület · ${floor === 'F' ? 'Földszint' : floor+'. emelet'}`;
        f.plan.href = f.detail.href = row.url;
        if (f.image.getAttribute('src') !== row.image) f.image.src = row.image;
        f.image.alt = row.code;
        f.rooms.textContent = new Intl.NumberFormat(isZh() ? 'zh-CN' : 'hu-HU',{maximumFractionDigits:2}).format(row.rooms)
          +(isZh() ? ' 个房间' : ' szoba');
        f.area.textContent = number(row.area,2)+' m²';
        f.price.classList.toggle('hhf-price-hidden',row.price === null);
        f.price.removeAttribute('data-i18n');
        if (row.price === null) f.price.textContent = isZh() ? '价格面议' : 'Ár egyeztetés alapján';
        else {
          const currency = document.createElement('span'); currency.textContent = ' Ft';
          f.price.replaceChildren(document.createTextNode(number(row.price)),currency);
        }
      });
      updateControls();
    }
    function blocked() {
      const launcher = document.querySelector('.harmat-local-ai-launch');
      const panel = document.querySelector('#harmat-local-ai-panel');
      return launcher && launcher.getAttribute('data-hap-suspended') === '1'
        || panel && panel.classList.contains('is-open');
    }
    function stop() { if (timer !== null) window.clearTimeout(timer); timer = null; }
    function schedule() {
      stop();
      if (!visible || paused || hover || document.hidden
          || section.contains(document.activeElement) && document.activeElement !== toggle) return;
      timer = window.setTimeout(() => {
        timer = null;
        if (!blocked()) { page = (page+1)%pages; render(); }
        schedule();
      },12000);
    }
    function step(delta) { page = (page+delta+pages)%pages; render(); schedule(); }
    prev.addEventListener('click',() => step(-1));
    next.addEventListener('click',() => step(1));
    toggle.addEventListener('click',() => { paused = !paused; updateControls(); schedule(); });
    section.addEventListener('pointerenter',event => { if (event.pointerType === 'mouse') { hover = true; schedule(); } });
    section.addEventListener('pointerleave',event => { if (event.pointerType === 'mouse') { hover = false; schedule(); } });
    section.addEventListener('pointerdown',event => {
      if (event.pointerType === 'touch' && !controls.contains(event.target)) { paused = true; updateControls(); schedule(); }
    },{passive:true});
    section.addEventListener('focusin',event => {
      if (!controls.contains(event.target)) { paused = true; updateControls(); }
      schedule();
    });
    section.addEventListener('focusout',() => window.queueMicrotask(schedule));
    document.addEventListener('visibilitychange',schedule);
    motion.addEventListener('change',() => { if (motion.matches) paused = true; updateControls(); schedule(); });
    new MutationObserver(() => { render(); schedule(); }).observe(root,{attributes:true,attributeFilter:['lang']});
    const observer = new IntersectionObserver(entries => {
      visible = entries.some(entry => entry.isIntersecting && entry.intersectionRatio >= .1);
      schedule();
    },{threshold:[0,.1]});
    grid.before(controls);
    section.setAttribute('data-hhar-ready','1');
    render(); observer.observe(section);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
</script>
HHAR;
}
add_action('wp_footer','harmat_har_footer',1250);
