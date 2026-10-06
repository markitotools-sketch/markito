<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Theme Style Pro
Description: Global colors and typography controls for Perfex CRM.
Version: 4.1.0-rc5
Requires at least: 3.0.*
*/

define('THEME_STYLE_MODULE_NAME', 'theme_style');

$CI = &get_instance();
$CI->load->helper(THEME_STYLE_MODULE_NAME . '/theme_style');

/*
 * Keep newly introduced Theme Style options backward-compatible when the
 * module was already active before these options existed. This never
 * overwrites a configured value; it only initializes an empty/missing option.
 */
if ((string) get_option('theme_style_font_family') === '') {
    update_option('theme_style_font_family', 'System UI');
}
if ((string) get_option('theme_style_font_size') === '') {
    update_option('theme_style_font_size', '14');
}

register_activation_hook(THEME_STYLE_MODULE_NAME, 'theme_style_activation_hook');

function theme_style_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

register_language_files(THEME_STYLE_MODULE_NAME, [THEME_STYLE_MODULE_NAME]);

hooks()->add_action('app_admin_head', 'theme_style_admin_head');
hooks()->add_action('app_admin_authentication_head', 'theme_style_auth_head');
hooks()->add_action('app_customers_head', 'theme_style_clients_area_head');
hooks()->add_action('app_customers_footer', 'theme_style_clients_area_footer_debug');
hooks()->add_action('app_external_form_head', 'theme_style_external_head');
hooks()->add_action('app_admin_footer', 'theme_style_admin_footer_runtime_fixes');
hooks()->add_filter('module_theme_style_action_links', 'module_theme_style_action_links');
hooks()->add_action('admin_init', 'theme_style_init_menu_items');

function module_theme_style_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('theme_style') . '">' . _l('settings') . '</a>';
    return $actions;
}

function theme_style_admin_head()
{
    theme_style_render_font();
    theme_style_render(['general','tabs','buttons','admin','modals','tags','tables'], 'admin');
    theme_style_admin_modal_usability_css();
    theme_style_custom_css('theme_style_custom_admin_area');
}

function theme_style_auth_head()
{
    theme_style_render_font();
    theme_style_render(['general','buttons'], 'auth');
    theme_style_custom_css('theme_style_custom_admin_area');
}

function theme_style_clients_area_head()
{
    theme_style_render_font();
    theme_style_render(['general','tabs','buttons','customers','modals','tables'], 'customers');
    theme_style_custom_css('theme_style_custom_clients_area');
}

function theme_style_external_head()
{
    theme_style_render_font();
    theme_style_render(['general','buttons','tables'], 'external');
    theme_style_custom_css('theme_style_custom_clients_area');
}

function theme_style_custom_css($area)
{
    $specific = get_option($area);
    $shared = get_option('theme_style_custom_clients_and_admin_area');

    if ($specific === '' && $shared === '') {
        return;
    }

    echo '<style id="theme_style_custom_css">' . PHP_EOL;
    if ($specific !== '') {
        echo clear_textarea_breaks($specific) . PHP_EOL;
    }
    if ($shared !== '') {
        echo clear_textarea_breaks($shared) . PHP_EOL;
    }
    echo '</style>' . PHP_EOL;
}

function theme_style_init_menu_items()
{
    if (!is_admin()) {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_setup_menu_item('theme-style', [
        'href' => admin_url('theme_style'),
        'name' => _l('theme_style'),
        'position' => 65,
    ]);
}


function theme_style_clients_area_footer_debug()
{
    echo '<!-- Theme Style Pro: customers hook active -->';

    if (isset($_GET['theme_style_debug']) && $_GET['theme_style_debug'] == '1' && is_admin()) {
        echo '<div style="position:fixed;right:12px;bottom:12px;z-index:99999;background:#111827;color:#fff;padding:10px 12px;border-radius:8px;font-size:12px;">Theme Style customer hook: ACTIVE</div>';
    }

    theme_style_customer_runtime_qa();
}

/**
 * Customer Portal Runtime QA.
 *
 * Read-only and only rendered when a logged-in client explicitly opens a
 * customer page with ?theme_style_qa=1. It checks computed styles against
 * the currently saved Theme Style values and clearly labels whether a target
 * came from the live portal DOM or the hidden QA fixture.
 */
function theme_style_customer_runtime_qa()
{
    if (!isset($_GET['theme_style_qa']) || (string) $_GET['theme_style_qa'] !== '1') {
        return;
    }

    if (!function_exists('is_client_logged_in') || !is_client_logged_in()) {
        return;
    }

    $types = ['customers', 'general', 'tabs', 'buttons', 'modals', 'tables'];
    $areas = [];

    foreach ($types as $type) {
        foreach (get_styling_areas($type) as $area) {
            // This control belongs to Admin authentication, not Customer Portal.
            if (($area['id'] ?? '') === 'admin-login-background') {
                continue;
            }

            $area['group'] = $type;
            $areas[] = $area;
        }
    }

    $saved = [];
    foreach (get_applied_styling_area() as $item) {
        if (isset($item->id, $item->color)) {
            $saved[(string) $item->id] = (string) $item->color;
        }
    }

    $config = [
        'version' => '1.0',
        'areas' => $areas,
        'saved' => $saved,
        'fontFamily' => (string) get_option('theme_style_font_family'),
        'fontSize' => (string) get_option('theme_style_font_size'),
        'customCss' => [
            'customers' => (string) get_option('theme_style_custom_clients_area'),
            'shared' => (string) get_option('theme_style_custom_clients_and_admin_area'),
        ],
    ];

    echo '<script>window.ThemeStyleCustomerRuntimeQA=' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';

    echo <<<'HTML'
<style id="theme-style-customer-runtime-qa-ui">
#tscrqa-launch{position:fixed;right:18px;bottom:18px;z-index:2147483000;border:0;border-radius:999px;background:#111827;color:#fff;padding:11px 17px;font:600 13px/1.2 system-ui,-apple-system,Segoe UI,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.28);cursor:pointer}
#tscrqa-panel{position:fixed;inset:18px;z-index:2147483001;background:#fff;color:#111827;border-radius:14px;box-shadow:0 24px 80px rgba(0,0,0,.35);display:none;overflow:hidden;font:13px/1.45 system-ui,-apple-system,Segoe UI,sans-serif}
#tscrqa-panel *{box-sizing:border-box}
#tscrqa-head{height:58px;background:#111827;color:#fff;padding:11px 16px;display:flex;align-items:center;gap:10px}
#tscrqa-head strong{font-size:16px}.tscrqa-spacer{flex:1}
#tscrqa-head button{border:1px solid rgba(255,255,255,.25);background:#1f2937;color:#fff;padding:7px 10px;border-radius:7px;cursor:pointer}
#tscrqa-body{height:calc(100% - 58px);overflow:auto;padding:16px;background:#f8fafc}
#tscrqa-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:14px}
.tscrqa-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px}.tscrqa-card b{display:block;font-size:22px}.tscrqa-card span{color:#64748b}
#tscrqa-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb}
#tscrqa-table th,#tscrqa-table td{border-bottom:1px solid #e5e7eb;padding:8px;vertical-align:top;text-align:left}#tscrqa-table th{position:sticky;top:0;background:#f1f5f9;z-index:1}
.tscrqa-pass{color:#15803d;font-weight:700}.tscrqa-fail{color:#b91c1c;font-weight:700}.tscrqa-warn{color:#a16207;font-weight:700}.tscrqa-na{color:#64748b;font-weight:700}
#tscrqa-fixtures{position:fixed!important;left:-100000px!important;top:-100000px!important;width:1200px!important;height:900px!important;overflow:hidden!important;visibility:hidden!important;pointer-events:none!important}
@media(max-width:800px){#tscrqa-summary{grid-template-columns:repeat(2,minmax(0,1fr))}#tscrqa-panel{inset:6px}}
</style>
<button id="tscrqa-launch" type="button">Theme Style Runtime QA</button>
<div id="tscrqa-panel" role="dialog" aria-modal="true">
  <div id="tscrqa-head"><strong>Customer Portal Runtime QA</strong><span id="tscrqa-state">Ready</span><span class="tscrqa-spacer"></span><button id="tscrqa-run" type="button">Run Full Runtime QA</button><button id="tscrqa-export" type="button">Export JSON</button><button id="tscrqa-close" type="button">Close</button></div>
  <div id="tscrqa-body">
    <div id="tscrqa-summary">
      <div class="tscrqa-card"><b id="tscrqa-total">0</b><span>Checks</span></div>
      <div class="tscrqa-card"><b id="tscrqa-pass">0</b><span>PASS</span></div>
      <div class="tscrqa-card"><b id="tscrqa-warn">0</b><span>WARN</span></div>
      <div class="tscrqa-card"><b id="tscrqa-fail">0</b><span>FAIL</span></div>
      <div class="tscrqa-card"><b id="tscrqa-live">0</b><span>Live DOM checks</span></div>
    </div>
    <table id="tscrqa-table"><thead><tr><th>Status</th><th>Group</th><th>ID</th><th>Source</th><th>Property</th><th>Expected</th><th>Computed</th><th>Target / Details</th></tr></thead><tbody></tbody></table>
  </div>
</div>
<div id="tscrqa-fixtures" aria-hidden="true">
  <nav class="navbar navbar-default header"><a class="navbar-brand" href="#">Brand</a><button class="navbar-toggle"><span class="icon-bar"></span></button><ul class="navbar-nav"><li><a href="#">Nav</a></li></ul></nav>
  <div class="customer-top-submenu"><a href="#">Submenu</a></div>
  <div class="panel panel_s"><div class="panel-body"><p class="text-muted">Muted</p><label>Label</label><div class="form-group"><input class="form-control" placeholder="Placeholder"></div></div></div>
  <div class="card">Card</div><div class="widget">Widget</div><div class="well">Well</div><div class="dropdown-menu">Menu</div>
  <div class="bootstrap-select"><button class="dropdown-toggle">Select</button></div><div class="select2-container"><span class="select2-selection"><span class="select2-selection__rendered">Selected</span></span></div><div class="input-group-addon">Addon</div>
  <div class="dataTables_wrapper"><table class="table dataTable"><thead><tr><th>Heading</th></tr></thead><tbody><tr><td><a id="tscrqa-focus-link" href="#tscrqa-fixtures">Table link</a></td></tr></tbody></table></div>
  <ul class="nav nav-tabs"><li><a href="#">Tab</a></li><li class="active"><a href="#">Active tab</a></li></ul><ul class="nav nav-tabs-segmented"><li><a href="#">Segmented normal</a></li><li class="active"><a href="#">Segmented active</a></li></ul>
  <div class="modal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close">×</button><h4 class="modal-title">Modal</h4><span>Header text</span></div><div class="modal-body">Modal body</div></div></div></div>
  <button class="btn btn-default">Default</button><button class="btn btn-primary">Primary</button><button class="btn btn-info">Info</button><button class="btn btn-success">Success</button><button class="btn btn-danger">Danger</button>
  <p class="text-danger">Danger</p><p class="text-warning">Warning</p><p class="text-info">Info</p><p class="text-success">Success</p><a id="tscrqa-general-link" href="#tscrqa-fixtures">General link</a>
  <footer class="footer"><a href="#">Footer</a></footer>
</div>
<script id="theme-style-customer-runtime-qa-script">
(function(){
'use strict';
var cfg=window.ThemeStyleCustomerRuntimeQA||{};
var lastReport=null;
var fixture=document.getElementById('tscrqa-fixtures');
var panel=document.getElementById('tscrqa-panel');
var tbody=document.querySelector('#tscrqa-table tbody');
function q(id){return document.getElementById(id)}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})}
function normColor(v){var d=document.createElement('span');d.style.color=v;document.body.appendChild(d);var c=getComputedStyle(d).color;d.remove();return String(c||'').replace(/\s+/g,'').toLowerCase()}
function expectedFor(prop,value){if(prop==='background-image')return 'none';if(!value)return '';return value}
function computedProp(el,prop,pseudo){var cs=getComputedStyle(el,pseudo||null);if(prop==='background'||prop==='background-color')return cs.backgroundColor;if(prop==='border-color')return cs.borderTopColor;if(prop==='border-bottom-color')return cs.borderBottomColor;return cs.getPropertyValue(prop)}
function equivalent(prop,a,b){a=String(a||'').trim();b=String(b||'').trim();if(prop==='background-image')return a==='none'&&b==='none';if(prop.indexOf('color')!==-1||prop==='background'){return normColor(a)===normColor(b)}return a.replace(/\s+/g,' ').toLowerCase()===b.replace(/\s+/g,' ').toLowerCase()}
function splitSelectors(s){return String(s||'').split(',').map(function(x){return x.trim()}).filter(Boolean)}
function firstMatch(selector,root){var parts=splitSelectors(selector);for(var i=0;i<parts.length;i++){try{var el=(root||document).querySelector(parts[i]);if(el)return {el:el,selector:parts[i]}}catch(e){}}return null}
function liveMatch(selector){var m=firstMatch(selector,document);if(m&&fixture.contains(m.el))m=null;return m}
function fixtureMatch(selector){return firstMatch(selector,fixture)}
function baseForPseudo(selector){return String(selector).replace(/:hover|:focus/g,'')}
function focusMatch(selector,root){var parts=splitSelectors(selector);for(var i=0;i<parts.length;i++){if(parts[i].indexOf(':focus')===-1)continue;var base=baseForPseudo(parts[i]);try{var el=(root||document).querySelector(base);if(el&&typeof el.focus==='function'){el.focus();if(el.matches(parts[i]))return {el:el,selector:parts[i]}}}catch(e){}}return null}
function row(status,group,id,source,prop,expected,computed,target,details){return {status:status,group:group,id:id,source:source,property:prop,expected:expected,computed:computed,target:target,details:details||''}}
function testOne(a){var saved=(cfg.saved||{})[a.id]||'';if(!saved)return row('PASS',a.group,a.id,'N/A',a.css,'','',a.target,'Not configured; selector definition retained.');
 var prop=a.css, expected=expectedFor(prop,saved), match=liveMatch(a.target),source='LIVE DOM';
 if(!match){match=fixtureMatch(a.target);source='FIXTURE'}
 if(!match && (a.target.indexOf(':focus')!==-1||a.target.indexOf(':hover')!==-1)){match=focusMatch(a.target,document);source='LIVE FOCUS';if(!match){match=focusMatch(a.target,fixture);source='FIXTURE FOCUS'}}
 if(!match)return row('WARN',a.group,a.id,'NONE',prop,expected,'',a.target,'No matching DOM element; server QA should cover generated CSS.');
 var got=computedProp(match.el,prop), ok=equivalent(prop,expected,got);return row(ok?'PASS':'FAIL',a.group,a.id,source,prop,expected,got,match.selector,ok?'Computed style matches saved value.':'Computed style differs from saved value.');}
 function additionalRows(a){var out=[],saved=(cfg.saved||{})[a.id]||'';if(!saved||!a.additional_selectors)return out;String(a.additional_selectors).split('+').filter(Boolean).forEach(function(part){var x=part.lastIndexOf('|');if(x<1)return;var sel=part.slice(0,x),prop=part.slice(x+1),exp=expectedFor(prop,saved),m=liveMatch(sel),source='LIVE DOM';if(!m){m=fixtureMatch(sel);source='FIXTURE'};if(!m){out.push(row('WARN',a.group,a.id+' (additional)','NONE',prop,exp,'',sel,'No matching element for additional selector.'));return}var got=computedProp(m.el,prop,prop==='color'&&sel.indexOf('::placeholder')!==-1?'::placeholder':null),ok=equivalent(prop,exp,got);out.push(row(ok?'PASS':'FAIL',a.group,a.id+' (additional)',source,prop,exp,got,sel,ok?'Additional runtime style matches.':'Additional runtime style differs.'))});return out}
function envRows(){var out=[];var bodyOk=document.body.classList.contains('customers')||document.body.classList.contains('mk-client-theme');out.push(row(bodyOk?'PASS':'FAIL','environment','customer-body-class','LIVE DOM','class','customers / mk-client-theme',document.body.className,'body',bodyOk?'Customer scope class is active.':'Customer scope class missing.'));var gen=q('theme_style_generated');var hasSavedStyles=Object.keys(cfg.saved||{}).length>0;var genOk=hasSavedStyles?(!!gen&&gen.getAttribute('data-scope')==='customers'):(!gen||gen.getAttribute('data-scope')==='customers');out.push(row(genOk?'PASS':'FAIL','environment','generated-style-tag','LIVE DOM','data-scope',hasSavedStyles?'customers':'optional while no colors are configured',gen?(gen.getAttribute('data-scope')||'present'):'absent','#theme_style_generated',hasSavedStyles?'Customer renderer style tag is required because saved colors exist.':'No saved Theme Style colors; an absent generated style tag is a valid default state.'));var fs=getComputedStyle(document.body).fontSize;out.push(row(parseInt(fs,10)===parseInt(cfg.fontSize||'14',10)?'PASS':'FAIL','font','font-size','LIVE DOM','font-size',(cfg.fontSize||'14')+'px',fs,'body','Computed body font size.'));var ff=getComputedStyle(document.body).fontFamily;var fam=String(cfg.fontFamily||'System UI');var fontOk=fam==='System UI'||ff.toLowerCase().indexOf(fam.toLowerCase())!==-1;out.push(row(fontOk?'PASS':'WARN','font','font-family','LIVE DOM','font-family',fam,ff,'body',fontOk?'Configured family appears in computed stack.':'Configured family name not visible in computed stack.'));var cc=q('theme_style_custom_css');var shouldCustom=String((cfg.customCss||{}).customers||'').trim()!==''||String((cfg.customCss||{}).shared||'').trim()!=='';out.push(row((shouldCustom&&cc)||(!shouldCustom&&!cc)?'PASS':'FAIL','custom-css','customer-custom-css','LIVE DOM','style-tag',shouldCustom?'present':'absent',cc?'present':'absent','#theme_style_custom_css','Custom CSS injection state.'));return out}
function render(report){tbody.innerHTML='';report.results.forEach(function(r){var cls=r.status==='PASS'?'tscrqa-pass':r.status==='FAIL'?'tscrqa-fail':r.status==='WARN'?'tscrqa-warn':'tscrqa-na';tbody.insertAdjacentHTML('beforeend','<tr><td class="'+cls+'">'+esc(r.status)+'</td><td>'+esc(r.group)+'</td><td>'+esc(r.id)+'</td><td>'+esc(r.source)+'</td><td>'+esc(r.property)+'</td><td><code>'+esc(r.expected)+'</code></td><td><code>'+esc(r.computed)+'</code></td><td><code>'+esc(r.target)+'</code><br>'+esc(r.details)+'</td></tr>')});q('tscrqa-total').textContent=report.totals.checks;q('tscrqa-pass').textContent=report.totals.pass;q('tscrqa-warn').textContent=report.totals.warn;q('tscrqa-fail').textContent=report.totals.fail;q('tscrqa-live').textContent=report.totals.live;q('tscrqa-state').textContent=report.totals.fail?'FAIL '+report.totals.fail:(report.totals.warn?'PASS with '+report.totals.warn+' WARN':'ALL PASS')}
function run(){var results=envRows();(cfg.areas||[]).forEach(function(a){results.push(testOne(a));results=results.concat(additionalRows(a))});var totals={checks:results.length,pass:0,warn:0,fail:0,live:0,fixture:0};results.forEach(function(r){if(r.status==='PASS')totals.pass++;else if(r.status==='WARN')totals.warn++;else if(r.status==='FAIL')totals.fail++;if(String(r.source).indexOf('LIVE')===0)totals.live++;if(String(r.source).indexOf('FIXTURE')===0)totals.fixture++});lastReport={version:'1.0',generatedAt:new Date().toISOString(),page:location.href,portalPath:location.pathname,totals:totals,bodyClass:document.body.className,fontFamily:cfg.fontFamily,fontSize:cfg.fontSize,results:results};render(lastReport)}
q('tscrqa-launch').addEventListener('click',function(){panel.style.display='block'});q('tscrqa-close').addEventListener('click',function(){panel.style.display='none'});q('tscrqa-run').addEventListener('click',run);q('tscrqa-export').addEventListener('click',function(){if(!lastReport)run();var blob=new Blob([JSON.stringify(lastReport,null,2)],{type:'application/json'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='theme-style-customer-runtime-qa-'+new Date().toISOString().replace(/[:.]/g,'-')+'.json';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove()},1000)});
})();
</script>
HTML;
}


function theme_style_admin_modal_usability_css()
{
    echo '<style id="theme_style_admin_modal_usability">' . PHP_EOL;

    /*
     * Generic stacking safety for all admin modals.
     */
    echo 'body.admin .modal-backdrop,body.mk-admin-shell .modal-backdrop{z-index:1890 !important;}' . PHP_EOL;
    echo 'body.admin .modal,body.mk-admin-shell .modal{z-index:1900 !important;overflow-x:hidden !important;overflow-y:auto !important;}' . PHP_EOL;

    /*
     * CONTACT MODAL — IMPORTANT
     * Perfex markup is:
     * .modal-content > form#contact-form > .modal-header/.modal-body/.modal-footer
     *
     * v4.0.1 incorrectly made .modal-content the flex container. Because the
     * header/body/footer are inside the form, the form itself must be flex.
     */
    echo 'body.admin #contact .modal-dialog,body.mk-admin-shell #contact .modal-dialog{width:calc(100% - 32px) !important;max-width:820px !important;margin:78px auto 28px !important;transform:none !important;}' . PHP_EOL;

    echo 'body.admin #contact .modal-content,body.mk-admin-shell #contact .modal-content{display:block !important;max-height:none !important;overflow:visible !important;}' . PHP_EOL;

    echo 'body.admin #contact .modal-content>form#contact-form,body.mk-admin-shell #contact .modal-content>form#contact-form{display:flex !important;flex-direction:column !important;width:100% !important;max-height:calc(100vh - 106px) !important;overflow:hidden !important;}' . PHP_EOL;

    echo 'body.admin #contact #contact-form>.modal-header,body.mk-admin-shell #contact #contact-form>.modal-header{flex:0 0 auto !important;}' . PHP_EOL;

    echo 'body.admin #contact #contact-form>.modal-body,body.mk-admin-shell #contact #contact-form>.modal-body{flex:1 1 auto !important;min-height:0 !important;max-height:none !important;overflow-y:auto !important;overflow-x:hidden !important;overscroll-behavior:contain !important;padding-bottom:24px !important;}' . PHP_EOL;

    echo 'body.admin #contact #contact-form>.modal-footer,body.mk-admin-shell #contact #contact-form>.modal-footer{flex:0 0 auto !important;position:relative !important;bottom:auto !important;z-index:3 !important;margin:0 !important;}' . PHP_EOL;

    echo 'body.admin #contact .form-control,body.admin #contact .bootstrap-select,body.admin #contact .select2-container,body.mk-admin-shell #contact .form-control,body.mk-admin-shell #contact .bootstrap-select,body.mk-admin-shell #contact .select2-container{width:100% !important;max-width:100% !important;}' . PHP_EOL;

    echo 'body.admin #contact .modal-header .close,body.mk-admin-shell #contact .modal-header .close{position:relative !important;z-index:4 !important;opacity:1 !important;pointer-events:auto !important;}body.admin #contact,body.mk-admin-shell #contact{pointer-events:auto !important;}body.admin #contact .modal-dialog,body.admin #contact .modal-content,body.admin #contact form,body.mk-admin-shell #contact .modal-dialog,body.mk-admin-shell #contact .modal-content,body.mk-admin-shell #contact form{pointer-events:auto !important;}' . PHP_EOL;

    /*
     * Undo the v4.0.1 generic max-height/flex behavior on other modal-content
     * structures so standard Perfex modals keep their native layout.
     */
    echo 'body.admin .modal:not(#contact) .modal-content,body.mk-admin-shell .modal:not(#contact) .modal-content{max-height:none !important;overflow:visible;}' . PHP_EOL;

    echo '@media (max-width:767px){' . PHP_EOL;
    echo 'body.admin #contact .modal-dialog,body.mk-admin-shell #contact .modal-dialog{width:calc(100% - 16px) !important;margin:70px 8px 16px !important;}' . PHP_EOL;
    echo 'body.admin #contact .modal-content>form#contact-form,body.mk-admin-shell #contact .modal-content>form#contact-form{max-height:calc(100vh - 86px) !important;}' . PHP_EOL;
    echo '}' . PHP_EOL;

    echo '</style>' . PHP_EOL;
}


/**
 * Runtime safety fixes for the premium admin shell.
 *
 * Keep Bootstrap modals as direct children of <body> when they are shown.
 * This prevents fixed/transformed admin-shell containers from creating a
 * stacking context that can put the backdrop above the modal.
 *
 * No Perfex core files are modified.
 */
function theme_style_admin_footer_runtime_fixes()
{
    echo <<<'HTML'
<script id="theme-style-admin-runtime-fixes">
(function($){
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    $(document)
        .off('show.bs.modal.themeStyleRuntime')
        .on('show.bs.modal.themeStyleRuntime', '.modal', function(){
            var $modal = $(this);

            if (!$modal.parent().is('body')) {
                $modal.appendTo(document.body);
            }
        });

    $(document)
        .off('hidden.bs.modal.themeStyleRuntime')
        .on('hidden.bs.modal.themeStyleRuntime', '#contact', function(){
            // Contact is AJAX-loaded in Perfex. Remove the moved instance after close
            // so the next AJAX load creates a clean modal.
            $(this).remove();
            $('#contact_data').empty();
        });
})(jQuery);
</script>
HTML;
}
