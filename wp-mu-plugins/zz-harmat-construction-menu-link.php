<?php
/**
 * Plugin Name: Harmat Construction Menu Link
 * Description: Adds the public construction log to the clean header menu.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

function harmat_construction_menu_link_is_public(): bool
{
    return !is_admin() && !wp_doing_ajax() && !wp_is_json_request() && !is_feed() && !is_robots();
}

add_action('wp_footer', static function (): void {
    if (!harmat_construction_menu_link_is_public()) {
        return;
    }
    ?>
<script id="harmat-construction-menu-link">
(function(){
  if(window.__harmatConstructionMenuLinkReady){return;}
  window.__harmatConstructionMenuLinkReady=true;
  var observer=null;

  function installLink(){
    var nav=document.querySelector('#harmat-clean-menu-modal nav');
    if(!nav){return false;}
    if(nav.querySelector('a[href="/epitesi-naplo/"],a[href$="/epitesi-naplo/"]')){return true;}
    var link=document.createElement('a');
    link.href='/epitesi-naplo/';
    link.textContent='Építési napló';
    var contact=nav.querySelector('a[href="/elerhetosegeink/"],a[href$="/elerhetosegeink/"]');
    nav.insertBefore(link,contact||null);
    return true;
  }

  function installAndStop(){
    if(installLink()&&observer){observer.disconnect();observer=null;}
  }

  document.addEventListener('click',function(){window.setTimeout(installAndStop,0);});
  if(document.body){
    observer=new MutationObserver(installAndStop);
    observer.observe(document.body,{childList:true});
  }
  installAndStop();
})();
</script>
    <?php
}, 1010);
