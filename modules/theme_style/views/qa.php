<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">
<div class="row"><div class="col-md-12">
<div class="panel_s"><div class="panel-body">
    <div class="tw-flex tw-items-center tw-justify-between tw-gap-3 tw-flex-wrap">
        <div>
            <h3 class="tw-m-0 tw-font-bold">Theme Style One-Click Full QA v2.0</h3>
            <p class="text-muted tw-mt-2 tw-mb-0">Read-only QA for definitions, saved data, generated CSS, scopes, fonts, hooks and custom CSS.</p>
        </div>
        <div>
            <button id="tsqa-run" type="button" class="btn btn-primary"><i class="fa-solid fa-vial-circle-check"></i> Run Full QA</button>
            <button id="tsqa-export" type="button" class="btn btn-default" disabled><i class="fa-solid fa-download"></i> Export JSON</button>
        </div>
    </div>

    <hr>
    <div id="tsqa-summary" class="alert alert-info">Press <strong>Run Full QA</strong>. This page does not modify Theme Style settings.</div>

    <div class="row">
        <div class="col-md-3"><div class="panel_s"><div class="panel-body text-center"><div class="text-muted">PASS</div><div id="tsqa-pass" style="font-size:28px;font-weight:700">0</div></div></div></div>
        <div class="col-md-3"><div class="panel_s"><div class="panel-body text-center"><div class="text-muted">WARN</div><div id="tsqa-warn" style="font-size:28px;font-weight:700">0</div></div></div></div>
        <div class="col-md-3"><div class="panel_s"><div class="panel-body text-center"><div class="text-muted">FAIL</div><div id="tsqa-fail" style="font-size:28px;font-weight:700">0</div></div></div></div>
        <div class="col-md-3"><div class="panel_s"><div class="panel-body text-center"><div class="text-muted">CHECKS</div><div id="tsqa-total" style="font-size:28px;font-weight:700">0</div></div></div></div>
    </div>

    <h4 class="tw-font-bold">System Health</h4>
    <div class="table-responsive">
        <table class="table table-bordered" id="tsqa-health-table">
            <thead><tr><th>Status</th><th>Check</th><th>Details</th></tr></thead><tbody></tbody>
        </table>
    </div>

    <h4 class="tw-font-bold tw-mt-6">Theme Areas</h4>
    <div class="table-responsive">
        <table class="table table-bordered" id="tsqa-table">
            <thead><tr>
                <th>Status</th><th>Scope</th><th>ID</th><th>Property</th><th>Saved Value</th><th>Rendered In</th><th>Details</th>
            </tr></thead><tbody></tbody>
        </table>
    </div>

    <h4 class="tw-font-bold tw-mt-6">Custom CSS</h4>
    <div class="table-responsive">
        <table class="table table-bordered" id="tsqa-custom-table">
            <thead><tr><th>Status</th><th>Area</th><th>Length</th><th>Parser Result</th></tr></thead><tbody></tbody>
        </table>
    </div>

    <div class="alert alert-info tw-mt-4">
        <strong>How to read this:</strong> Generated CSS and hook/scope checks are real server-output checks. Selector syntax is checked by the browser. A selector with zero matches on this QA page is not automatically a failure because some targets only exist on specific CRM pages.
    </div>
</div></div>
</div></div>
</div>
</div>

<!-- Hidden fixtures give common Perfex selectors something safe to match without changing the CRM UI. -->
<div id="tsqa-fixtures" aria-hidden="true" style="position:fixed;left:-100000px;top:-100000px;width:1200px;height:900px;overflow:hidden;">
    <div class="panel panel_s"><div class="panel-body">
        <div class="table-responsive"><table class="table dataTable items"><thead><tr><th>Heading</th></tr></thead><tbody><tr><td><a href="#">Link</a></td></tr></tbody></table></div>
        <input class="form-control" placeholder="Input">
        <div class="input-group-addon">Addon</div>
        <div class="bootstrap-select"><button class="dropdown-toggle btn btn-default">Select</button></div>
        <div class="select2-container"><span class="select2-selection"><span class="select2-selection__rendered">Selected</span></span></div>
        <ul class="nav nav-tabs"><li class="active"><a href="#"><i class="fa fa-check"></i> Active</a></li><li><a href="#">Tab</a></li></ul>
        <p class="text-muted">Muted</p><p class="text-danger">Danger</p><p class="text-warning">Warning</p><p class="text-info">Info</p><p class="text-success">Success</p>
        <button class="btn btn-default">Default</button><button class="btn btn-primary">Primary</button><button class="btn btn-info">Info</button><button class="btn btn-success">Success</button><button class="btn btn-danger">Danger</button>
        <span class="label label-default">Tag</span>
        <div class="dropdown-menu">Dropdown</div>
        <div class="modal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Modal</h4></div><div class="modal-body">Body</div></div></div></div>
    </div></div>
</div>

<?php init_tail(); ?>
<script>
(function(){
'use strict';

var areas = <?= json_encode($qa_areas, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var applied = <?= json_encode($qa_applied, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var scopeCss = <?= json_encode($qa_scope_css, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var scopeGroups = <?= json_encode($qa_scope_groups, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var fontCss = <?= json_encode($qa_font_css, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var fontFamily = <?= json_encode($qa_font_family, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var fontSize = <?= json_encode($qa_font_size, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var fonts = <?= json_encode(array_keys($qa_fonts), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var storage = <?= json_encode($qa_storage, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var wiring = <?= json_encode($qa_wiring, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
var customCss = {
    admin: <?= json_encode($qa_custom_admin, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>,
    customers: <?= json_encode($qa_custom_clients, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>,
    both: <?= json_encode($qa_custom_both, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>
};
var lastReport = null;

function esc(s){ return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function compact(s){ return String(s || '').replace(/\s+/g,' ').trim(); }
function noSpace(s){ return String(s || '').replace(/\s+/g,'').toLowerCase(); }
function reEsc(s){ return String(s).replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }
function validColor(value){
    if(!value) return false;
    var d=document.createElement('div');
    d.style.color=''; d.style.color=value;
    return d.style.color !== '';
}
function selectorValid(selector){
    try { var nodes=document.querySelectorAll(selector); return {ok:true,count:nodes.length}; }
    catch(e){ return {ok:false,count:0,error:e.message}; }
}
function propertyAccepts(prop,value){
    var d=document.createElement('div');
    try { d.style.setProperty(prop,value); return d.style.getPropertyValue(prop)!==''; }
    catch(e){ return false; }
}
function hasDeclaration(css, prop, value){
    var p=reEsc(String(prop).trim());
    var v=reEsc(String(value).trim());
    var rx=new RegExp(p+'\\s*:\\s*'+v+'\\s*(?:!important\\s*)?;','i');
    return rx.test(String(css||''));
}
function selectorFragmentPresent(css,target){
    var norm=compact(css).toLowerCase();
    var parts=String(target||'').split(',').map(function(x){return compact(x).toLowerCase();}).filter(Boolean);
    if(!parts.length) return false;
    return parts.some(function(p){ return norm.indexOf(p)!==-1; });
}
function groupInScope(group,scope){ return (scopeGroups[scope]||[]).indexOf(group)!==-1; }
function expectedScopesFor(group){
    return Object.keys(scopeGroups).filter(function(scope){ return scope!=='all' && groupInScope(group,scope); });
}
function additionalRules(raw,saved){
    var out=[];
    if(!raw) return out;
    String(raw).split('+').filter(Boolean).forEach(function(part){
        var ix=part.lastIndexOf('|');
        if(ix<1){ out.push({ok:false,raw:part,error:'Missing selector|property separator'}); return; }
        var selector=part.slice(0,ix), prop=part.slice(ix+1), value=(prop==='background-image'?'none':saved);
        out.push({selector:selector,property:prop,value:value});
    });
    return out;
}
function renderStatusFor(area,group,saved){
    var scopes=['all'].concat(expectedScopesFor(group));
    var rendered=[], failures=[];
    scopes.forEach(function(scope){
        var css=String(scopeCss[scope]||'');
        var valueOk=hasDeclaration(css,area.css,saved);
        var selectorOk=selectorFragmentPresent(css,area.target);
        var addOk=true;
        additionalRules(area.additional_selectors,saved).forEach(function(r){
            if(!r.error && (!hasDeclaration(css,r.property,r.value) || !selectorFragmentPresent(css,r.selector))) addOk=false;
            if(r.error) addOk=false;
        });
        if(valueOk && selectorOk && addOk) rendered.push(scope);
        else failures.push(scope+': '+(!selectorOk?'selector ':'')+(!valueOk?'value/property ':'')+(!addOk?'additional-rule ':'')+'missing');
    });
    return {ok:failures.length===0,rendered:rendered,failures:failures};
}
function customCssParse(name,css){
    var text=String(css||'');
    if(!text.trim()) return {name:name,status:'PASS',length:0,message:'Empty custom CSS.'};
    // Options are stored with nl2br; render path clears these breaks. Mirror that here.
    var normalized=text.replace(/<br\s*\/?\s*>/gi,'\n');
    var st=document.createElement('style'); st.textContent=normalized; st.setAttribute('data-tsqa-temp','1'); document.head.appendChild(st);
    var status='PASS',message='Browser parsed stylesheet.';
    try { message='Browser parsed '+(st.sheet ? st.sheet.cssRules.length : 0)+' rule(s).'; }
    catch(e){ status='WARN'; message='cssRules inspection unavailable: '+e.message; }
    st.remove();
    return {name:name,status:status,length:normalized.length,message:message};
}
function row(status,name,details){ return {status:status,name:name,details:details}; }

function run(){
    var health=[], results=[], knownIds={}, idCounts={}, checks=0;

    // 1) Definition integrity
    Object.keys(areas).forEach(function(group){
        (areas[group]||[]).forEach(function(a){
            if(a.id) { knownIds[a.id]=true; idCounts[a.id]=(idCounts[a.id]||0)+1; }
        });
    });
    var duplicates=Object.keys(idCounts).filter(function(id){return idCounts[id]>1;});
    health.push(row(duplicates.length?'FAIL':'PASS','Unique Theme Style IDs',duplicates.length?('Duplicates: '+duplicates.join(', ')):'All Theme Style IDs are unique.'));

    // 2) Raw DB storage integrity
    health.push(row(storage.json_valid?'PASS':'FAIL','theme_style JSON storage',storage.json_valid?('Valid JSON with '+storage.item_count+' item(s).'):('Invalid JSON: '+storage.json_error)));
    var invalidStored=[], unknownStored=[], storedSeen={};
    (storage.items||[]).forEach(function(item,idx){
        if(!item || typeof item.id==='undefined' || typeof item.color==='undefined'){ invalidStored.push('#'+idx+' missing id/color'); return; }
        if(!knownIds[item.id]) unknownStored.push(String(item.id));
        if(!validColor(String(item.color))) invalidStored.push(String(item.id)+'='+String(item.color));
        if(storedSeen[item.id]) invalidStored.push('duplicate saved id '+String(item.id));
        storedSeen[item.id]=true;
    });
    health.push(row(invalidStored.length?'FAIL':'PASS','Saved Theme Style values',invalidStored.length?invalidStored.join(' | '):'All stored items have valid IDs structure and browser-valid colors.'));
    health.push(row(unknownStored.length?'WARN':'PASS','Unknown saved IDs',unknownStored.length?('Saved IDs no longer defined: '+unknownStored.join(', ')):'No stale/unknown saved IDs.'));

    // 3) Runtime wiring
    Object.keys(wiring).forEach(function(k){ health.push(row(wiring[k]?'PASS':'FAIL','Runtime wiring: '+k,wiring[k]?'Function loaded.':'Required Theme Style runtime function is missing.')); });

    // 4) Font engine
    var familyOk=fonts.indexOf(fontFamily)!==-1;
    var sizeNum=parseInt(fontSize,10), sizeOk=sizeNum>=12 && sizeNum<=18;
    var fontOutputOk=String(fontCss).indexOf('--theme-style-font:')!==-1 && String(fontCss).indexOf('--theme-style-font-size:'+sizeNum+'px')!==-1;
    health.push(row(familyOk?'PASS':'FAIL','Font family option',familyOk?('Valid: '+fontFamily):('Unknown font: '+fontFamily)));
    health.push(row(sizeOk?'PASS':'FAIL','Font size option',sizeOk?('Valid: '+sizeNum+'px'):('Out of range: '+fontSize)));
    health.push(row(fontOutputOk?'PASS':'FAIL','Font renderer output',fontOutputOk?'Font variables are present in server-rendered CSS.':'Expected font CSS variables were not generated.'));

    // 5) Scope CSS containers exist
    Object.keys(scopeGroups).forEach(function(scope){
        var css=String(scopeCss[scope]||'');
        var ok=css.indexOf('theme_style_generated')!==-1 || Object.keys(applied).length===0;
        health.push(row(ok?'PASS':'FAIL','Generated CSS scope: '+scope,ok?('Output length: '+css.length):'No Theme Style generated CSS output for this scope.'));
    });

    // 6) Every Theme Style area
    Object.keys(areas).forEach(function(group){
        (areas[group]||[]).forEach(function(a){
            var issues=[], notes=[], saved=applied[a.id]||'';
            var sv=selectorValid(a.target||'');
            if(!a.id) issues.push('Missing ID.');
            if(!a.target) issues.push('Missing target selector.');
            if(!a.css) issues.push('Missing CSS property.');
            if(!sv.ok) issues.push('Invalid selector syntax: '+sv.error);
            else notes.push('Selector syntax PASS'+(sv.count?' / live matches '+sv.count:' / no match required on QA page'));

            var testValue=(a.css==='background-image'?'none':(saved||'#123456'));
            if(a.css && !propertyAccepts(a.css,testValue)) issues.push('Browser rejected property/value '+a.css+':'+testValue);

            var adds=additionalRules(a.additional_selectors||'',saved||'#123456');
            adds.forEach(function(r){
                if(r.error){ issues.push(r.error+': '+r.raw); return; }
                var av=selectorValid(r.selector);
                if(!av.ok) issues.push('Invalid additional selector: '+r.selector+' / '+av.error);
                if(!propertyAccepts(r.property,r.value)) issues.push('Browser rejected additional property/value '+r.property+':'+r.value);
            });
            if(adds.length) notes.push('Additional rules checked: '+adds.length);

            var rendered=[];
            if(saved){
                if(!validColor(saved)) issues.push('Saved color is invalid: '+saved);
                var rr=renderStatusFor(a,group,saved);
                rendered=rr.rendered;
                if(!rr.ok) issues=issues.concat(rr.failures);
                else notes.push('Saved value generated in every expected scope.');
            } else {
                notes.push('No saved value; definition/browser syntax tested only.');
            }

            results.push({status:issues.length?'FAIL':'PASS',scope:group,id:a.id,property:a.css,saved:saved,selector:a.target,renderedIn:rendered,issues:issues,notes:notes});
        });
    });

    // 7) Custom CSS
    var custom=[customCssParse('Admin custom CSS',customCss.admin),customCssParse('Customer custom CSS',customCss.customers),customCssParse('Shared Admin + Customer CSS',customCss.both)];

    // Render tables and totals.
    var counts={PASS:0,WARN:0,FAIL:0};
    health.forEach(function(r){counts[r.status]=(counts[r.status]||0)+1;});
    results.forEach(function(r){counts[r.status]=(counts[r.status]||0)+1;});
    custom.forEach(function(r){counts[r.status]=(counts[r.status]||0)+1;});
    checks=health.length+results.length+custom.length;

    var hb=document.querySelector('#tsqa-health-table tbody'); hb.innerHTML='';
    health.forEach(function(r){
        var c=r.status==='PASS'?'success':(r.status==='WARN'?'warning':'danger');
        var tr=document.createElement('tr'); tr.innerHTML='<td><span class="label label-'+c+'">'+esc(r.status)+'</span></td><td>'+esc(r.name)+'</td><td>'+esc(r.details)+'</td>'; hb.appendChild(tr);
    });

    var tb=document.querySelector('#tsqa-table tbody'); tb.innerHTML='';
    results.forEach(function(r){
        var c=r.status==='PASS'?'success':'danger', details=[];
        if(r.issues.length) details.push('<strong>Issues:</strong> '+esc(r.issues.join(' | ')));
        if(r.notes.length) details.push(esc(r.notes.join(' | ')));
        var tr=document.createElement('tr');
        tr.innerHTML='<td><span class="label label-'+c+'">'+esc(r.status)+'</span></td><td>'+esc(r.scope)+'</td><td><code>'+esc(r.id)+'</code></td><td><code>'+esc(r.property)+'</code></td><td><code>'+esc(r.saved||'(not set)')+'</code></td><td>'+esc(r.renderedIn.length?r.renderedIn.join(', '):(r.saved?'NONE':'N/A'))+'</td><td style="max-width:600px;white-space:normal">'+details.join('<br>')+'</td>';
        tb.appendChild(tr);
    });

    var cb=document.querySelector('#tsqa-custom-table tbody'); cb.innerHTML='';
    custom.forEach(function(r){
        var c=r.status==='PASS'?'success':(r.status==='WARN'?'warning':'danger');
        var tr=document.createElement('tr'); tr.innerHTML='<td><span class="label label-'+c+'">'+esc(r.status)+'</span></td><td>'+esc(r.name)+'</td><td>'+r.length+'</td><td>'+esc(r.message)+'</td>'; cb.appendChild(tr);
    });

    document.getElementById('tsqa-pass').textContent=counts.PASS||0;
    document.getElementById('tsqa-warn').textContent=counts.WARN||0;
    document.getElementById('tsqa-fail').textContent=counts.FAIL||0;
    document.getElementById('tsqa-total').textContent=checks;

    var summary=document.getElementById('tsqa-summary');
    if((counts.FAIL||0)===0){
        summary.className='alert alert-success';
        summary.innerHTML='<strong>Full automated Theme Style QA passed.</strong> PASS: '+counts.PASS+' | WARN: '+counts.WARN+' | FAIL: 0 | Checks: '+checks+'.';
    }else{
        summary.className='alert alert-danger';
        summary.innerHTML='<strong>Theme Style QA found '+counts.FAIL+' failure(s).</strong> PASS: '+counts.PASS+' | WARN: '+counts.WARN+' | FAIL: '+counts.FAIL+' | Checks: '+checks+'. Review the red rows or export JSON.';
    }

    lastReport={version:'2.0',generatedAt:new Date().toISOString(),page:location.href,totals:{checks:checks,pass:counts.PASS||0,warn:counts.WARN||0,fail:counts.FAIL||0},storage:storage,font:{family:fontFamily,size:fontSize},scopeCssLengths:Object.keys(scopeCss).reduce(function(o,k){o[k]=String(scopeCss[k]||'').length;return o;},{}),health:health,results:results,customCss:custom};
    document.getElementById('tsqa-export').disabled=false;
}

document.getElementById('tsqa-run').addEventListener('click',run);
document.getElementById('tsqa-export').addEventListener('click',function(){
    if(!lastReport) return;
    var blob=new Blob([JSON.stringify(lastReport,null,2)],{type:'application/json'}), url=URL.createObjectURL(blob), a=document.createElement('a');
    a.href=url; a.download='theme-style-full-qa-v2-'+new Date().toISOString().replace(/[:.]/g,'-')+'.json'; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function(){URL.revokeObjectURL(url);},1000);
});
})();
</script>
</body>
</html>
