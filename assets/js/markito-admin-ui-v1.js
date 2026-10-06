/* Markito Admin UI v1 — Phase 1 Global Shell */
(function () {
  'use strict';

  function boot() {
    if (!document.body) return;

    document.body.classList.add('mk-admin-shell');

    /* Keep external behavior untouched; this only adds presentation hooks. */
    var header = document.getElementById('header');
    var menu = document.getElementById('menu');
    var wrapper = document.getElementById('wrapper');

    if (header) header.classList.add('mk-admin-shell-header');
    if (menu) menu.classList.add('mk-admin-shell-sidebar');
    if (wrapper) wrapper.classList.add('mk-admin-shell-workspace');

    /* Add a small stagger only to currently visible top-level sidebar items. */
    var items = document.querySelectorAll('#side-menu > li');
    items.forEach(function (item, index) {
      item.style.setProperty('--mk-menu-index', String(index));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();


/* ============================================================
   Markito Admin UI v1 — Phase 2 Dashboard Runtime
   ============================================================ */
(function () {
  'use strict';

  function installLogoFallback() {
    var logo = document.getElementById('logo');
    if (!logo || logo.querySelector('.mk-admin-logo-fallback')) return;

    var img = logo.querySelector('img');
    var fallback = document.createElement('span');
    fallback.className = 'mk-admin-logo-fallback';
    fallback.innerHTML =
      '<span class="mk-admin-logo-mark">M</span>' +
      '<span class="mk-admin-logo-copy">' +
      '<strong>Markito</strong>' +
      '<small>ADMIN WORKSPACE</small>' +
      '</span>';

    logo.appendChild(fallback);

    function showFallback() {
      fallback.classList.add('is-visible');
      if (img) img.style.display = 'none';
    }

    if (!img) {
      showFallback();
      return;
    }

    img.addEventListener('error', showFallback, { once: true });

    if (img.complete && img.naturalWidth === 0) {
      showFallback();
    }
  }

  function tagDashboardWidgets() {
    if (!document.querySelector('.mk-admin-dashboard-page')) return;

    document.querySelectorAll('.mk-admin-dashboard-zone .widget, .mk-admin-dashboard-zone .panel_s')
      .forEach(function (widget, index) {
        widget.classList.add('mk-admin-dashboard-widget');
        widget.style.setProperty('--mk-widget-index', String(index));
      });
  }

  function bootPhase2() {
    installLogoFallback();
    tagDashboardWidgets();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPhase2, { once: true });
  } else {
    bootPhase2();
  }

  window.addEventListener('load', function () {
    installLogoFallback();
    tagDashboardWidgets();
  });
})();


/* ============================================================
   Markito Admin UI v1 — Phase 3 Widget Enhancement Runtime
   ============================================================ */
(function () {
  'use strict';

  function enhanceDashboard() {
    var page = document.querySelector('.mk-admin-dashboard-page');
    if (!page) return;

    /* Tag dashboard widgets in stable order without touching Perfex behavior. */
    document.querySelectorAll('.mk-admin-dashboard-zone .widget, .mk-admin-dashboard-zone .panel_s')
      .forEach(function (el, index) {
        el.classList.add('mk-admin-dashboard-widget');
        el.dataset.mkWidgetIndex = String(index);
      });

    /* Improve native empty states without changing the text/data. */
    document.querySelectorAll('.mk-admin-dashboard-page .text-muted')
      .forEach(function (el) {
        var txt = (el.textContent || '').trim().toLowerCase();
        if (
          txt.indexOf('no ') === 0 ||
          txt.indexOf('nothing') === 0 ||
          txt.indexOf('no records') !== -1 ||
          txt.indexOf('no data') !== -1
        ) {
          el.classList.add('mk-admin-empty-state');
        }
      });

    /* Mark compact top widgets for consistent executive-card treatment. */
    var top = document.querySelector('.mk-admin-dashboard-zone-top');
    if (top) {
      top.querySelectorAll('.panel_s, .widget, [class*="col-"] > div')
        .forEach(function (el) {
          if (el.querySelector('.progress') || el.querySelector('[class*="progress"]')) {
            el.classList.add('mk-admin-kpi-card');
          }
        });
    }
  }

  function bootPhase3() {
    enhanceDashboard();

    /* Perfex can redraw dashboard widgets after DOM ready. */
    var target = document.querySelector('.mk-admin-dashboard-page');
    if (target && window.MutationObserver) {
      var observer = new MutationObserver(function () {
        enhanceDashboard();
      });
      observer.observe(target, { childList:true, subtree:true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPhase3, { once:true });
  } else {
    bootPhase3();
  }
})();


/* ============================================================
   Markito Admin UI v1 — Phase 4 Core Pages Runtime
   ============================================================ */
(function () {
  'use strict';

  function enhanceCorePages() {
    var page = document.querySelector('.mk-admin-core-page');
    if (!page) return;

    /* Tag tables for CSS hooks only; no DataTables options are changed. */
    page.querySelectorAll('table.dataTable, table.table')
      .forEach(function (table) {
        table.classList.add('mk-admin-premium-table');
      });

    /* Mark the first meaningful actions block as a toolbar. */
    var toolbar = page.querySelector('._buttons');
    if (toolbar) toolbar.classList.add('mk-admin-page-toolbar');

    /* Add a class to summary/status blocks without altering their markup/data. */
    page.querySelectorAll(
      '.leads-overview, .task-summary, [class*="tw-grid-cols-6"], [class*="tw-auto-cols-max"]'
    ).forEach(function (el) {
      el.classList.add('mk-admin-summary-strip');
    });
  }

  function bootPhase4() {
    enhanceCorePages();

    var page = document.querySelector('.mk-admin-core-page');
    if (page && window.MutationObserver) {
      var observer = new MutationObserver(function () {
        enhanceCorePages();
      });

      observer.observe(page, { childList:true, subtree:true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPhase4, { once:true });
  } else {
    bootPhase4();
  }
})();


/* ============================================================
   Markito Admin UI v1 — Phase 8
   Native Advanced Motion + Low-Cost WebGL Runtime
   ============================================================ */
(function () {
  'use strict';

  var pointerRaf = false;

  function reduced() {
    return window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function lowPower() {
    var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (c && c.saveData) return true;
    if (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4) return true;
    if (navigator.deviceMemory && navigator.deviceMemory <= 4) return true;
    return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
  }

  function surfaces() {
    document.querySelectorAll(
      '.mk-admin-dashboard-zone-top .panel_s,' +
      '.mk-admin-dashboard-widget,' +
      '.mk-admin-core-page .panel_s,' +
      '.mk-admin-sales-page .panel_s,' +
      '.mk-admin-support-page .panel_s,' +
      '.mk-admin-team-page .panel_s'
    ).forEach(function (el) {
      if (el.classList.contains('mk-motion-surface')) return;
      el.classList.add('mk-motion-surface');

      el.addEventListener('pointermove', function (e) {
        if (pointerRaf) return;
        pointerRaf = true;
        requestAnimationFrame(function () {
          var r = el.getBoundingClientRect();
          el.style.setProperty('--mk-x', (e.clientX-r.left)+'px');
          el.style.setProperty('--mk-y', (e.clientY-r.top)+'px');
          pointerRaf = false;
        });
      }, {passive:true});
    });
  }

  function reveals() {
    if (reduced()) return;
    document.body.classList.add('mk-motion-enabled');

    var els = Array.prototype.slice.call(document.querySelectorAll(
      '.mk-admin-dashboard-widget,' +
      '.mk-admin-dashboard-zone-top .panel_s,' +
      '.mk-admin-core-page .panel_s,' +
      '.mk-admin-sales-page .panel_s,' +
      '.mk-admin-support-page .panel_s,' +
      '.mk-admin-team-page .panel_s'
    ));

    els.forEach(function (el,i) {
      if (el.dataset.mkRevealReady === '1') return;
      el.dataset.mkRevealReady = '1';
      el.classList.add('mk-reveal');
      el.style.setProperty('--mk-reveal-delay', Math.min((i%8)*28,168)+'ms');
    });

    if (!('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('mk-reveal-visible'); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('mk-reveal-visible');
        io.unobserve(entry.target);
      });
    }, {rootMargin:'60px 0px',threshold:.06});

    els.forEach(function (el) {
      if (!el.classList.contains('mk-reveal-visible')) io.observe(el);
    });
  }

  function webglHero() {
    if (reduced() || lowPower()) return;

    var hero = document.querySelector('.mk-admin-dashboard-hero');
    if (!hero || hero.querySelector('.mk-admin-webgl')) return;

    var canvas = document.createElement('canvas');
    canvas.className = 'mk-admin-webgl';
    canvas.setAttribute('aria-hidden','true');
    hero.prepend(canvas);

    var gl = canvas.getContext('webgl',{
      alpha:true,antialias:false,depth:false,stencil:false,
      powerPreference:'low-power',preserveDrawingBuffer:false
    });
    if (!gl) { canvas.remove(); return; }

    var vsSrc = 'attribute vec2 p;void main(){gl_Position=vec4(p,0.0,1.0);}';
    var fsSrc = [
      'precision mediump float;',
      'uniform vec2 r;uniform float t;',
      'void main(){',
      'vec2 uv=gl_FragCoord.xy/r.xy;',
      'vec2 q=uv-vec2(.78,.46);q.x*=r.x/r.y;',
      'float d=length(q);',
      'float ring=smoothstep(.50,.05,d)*.32;',
      'float wave=.5+.5*sin((uv.x*7.0+uv.y*4.0)+(t*.55));',
      'float glow=ring*(.42+.58*wave);',
      'vec3 a=vec3(1.0,.55,.10);vec3 b=vec3(1.0,.76,.28);',
      'vec3 col=mix(a,b,uv.y)*glow;',
      'float line=.012/(abs(sin((uv.x+uv.y*.72)*13.0+t*.18))+.18);',
      'col+=a*line*.035;',
      'gl_FragColor=vec4(col,glow*.58);}'
    ].join('');

    function shader(type,src) {
      var s=gl.createShader(type);
      gl.shaderSource(s,src); gl.compileShader(s);
      if (!gl.getShaderParameter(s,gl.COMPILE_STATUS)) { gl.deleteShader(s); return null; }
      return s;
    }

    var vs=shader(gl.VERTEX_SHADER,vsSrc), fs=shader(gl.FRAGMENT_SHADER,fsSrc);
    if (!vs || !fs) { canvas.remove(); return; }

    var prog=gl.createProgram();
    gl.attachShader(prog,vs); gl.attachShader(prog,fs); gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog,gl.LINK_STATUS)) { canvas.remove(); return; }
    gl.useProgram(prog);

    var buf=gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER,buf);
    gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,-1,1,1,-1,1,1]),gl.STATIC_DRAW);

    var p=gl.getAttribLocation(prog,'p');
    gl.enableVertexAttribArray(p); gl.vertexAttribPointer(p,2,gl.FLOAT,false,0,0);
    var ur=gl.getUniformLocation(prog,'r'), ut=gl.getUniformLocation(prog,'t');

    var visible=true, running=true, last=0, start=performance.now();

    function resize() {
      var rect=hero.getBoundingClientRect(), dpr=Math.min(window.devicePixelRatio||1,1.25);
      var w=Math.max(1,Math.floor(rect.width*dpr)), h=Math.max(1,Math.floor(rect.height*dpr));
      if (canvas.width!==w || canvas.height!==h) {
        canvas.width=w; canvas.height=h; gl.viewport(0,0,w,h);
      }
    }

    function frame(now) {
      if (!running) return;
      if (visible && !document.hidden && now-last>=42) {
        resize();
        gl.uniform2f(ur,canvas.width,canvas.height);
        gl.uniform1f(ut,(now-start)/1000);
        gl.drawArrays(gl.TRIANGLES,0,6);
        last=now;
      }
      requestAnimationFrame(frame);
    }

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        visible=!!(entries[0]&&entries[0].isIntersecting);
      },{threshold:.01}).observe(hero);
    }

    window.addEventListener('pagehide',function(){running=false;},{once:true});
    requestAnimationFrame(frame);
  }

  function gpuArm() {
    document.addEventListener('pointerover',function(e){
      var el=e.target.closest('.mk-motion-surface'); if(el) el.classList.add('mk-gpu-active');
    },{passive:true});
    document.addEventListener('pointerout',function(e){
      var el=e.target.closest('.mk-motion-surface'); if(!el) return;
      setTimeout(function(){el.classList.remove('mk-gpu-active');},220);
    },{passive:true});
  }

  function boot() {
    surfaces(); reveals(); webglHero(); gpuArm();

    var root=document.getElementById('wrapper');
    if (root && window.MutationObserver) {
      var queued=false;
      new MutationObserver(function(){
        if(queued) return; queued=true;
        requestAnimationFrame(function(){surfaces();reveals();queued=false;});
      }).observe(root,{childList:true,subtree:true});
    }
  }

  if (document.readyState==='loading') {
    document.addEventListener('DOMContentLoaded',boot,{once:true});
  } else { boot(); }
})();


/* ============================================================
   Markito Admin UI v1 — Phase 9 REAL VISUAL REBUILD
   Route-aware premium UI, actual working pages.
   ============================================================ */
(function () {
  'use strict';

  var routes = [
    {match:/\/admin\/clients(?:\/|$)/, key:'customers', icon:'fa-regular fa-building', title:'Customers', sub:'Accounts, contacts, activity and commercial relationships in one workspace.'},
    {match:/\/admin\/leads(?:\/|$)/, key:'leads', icon:'fa-solid fa-crosshairs', title:'Leads', sub:'Qualify opportunities, move faster and keep the pipeline under control.'},
    {match:/\/admin\/projects(?:\/|$)/, key:'projects', icon:'fa-regular fa-folder-open', title:'Projects', sub:'Delivery, progress, team activity and deadlines — clearly organized.'},
    {match:/\/admin\/tasks(?:\/|$)/, key:'tasks', icon:'fa-regular fa-circle-check', title:'Tasks', sub:'Priorities, ownership and execution without the operational noise.'},
    {match:/\/admin\/invoices(?:\/|$)/, key:'sales', icon:'fa-regular fa-file-lines', title:'Invoices', sub:'Billing, collection status and revenue operations.'},
    {match:/\/admin\/estimates(?:\/|$)/, key:'sales', icon:'fa-regular fa-file', title:'Estimates', sub:'Commercial estimates, follow-up and conversion workflow.'},
    {match:/\/admin\/proposals(?:\/|$)/, key:'sales', icon:'fa-regular fa-file-powerpoint', title:'Proposals', sub:'Create, track and convert client proposals with less friction.'},
    {match:/\/admin\/tickets(?:\/|$)/, key:'support', icon:'fa-regular fa-life-ring', title:'Support', sub:'Customer requests, ownership, response and resolution.'},
    {match:/\/admin\/staff(?:\/|$)/, key:'team', icon:'fa-solid fa-people-group', title:'Team', sub:'People, roles, permissions and operational access.'},
    {match:/\/admin\/settings(?:\/|$)/, key:'settings', icon:'fa-solid fa-sliders', title:'System Settings', sub:'Configure the workspace while keeping operations predictable.'}
  ];

  function routeInfo() {
    var p = window.location.pathname;
    for (var i=0;i<routes.length;i++) {
      if (routes[i].match.test(p)) return routes[i];
    }
    return null;
  }

  function addRouteIdentity(info) {
    if (!info || !document.body) return;
    document.body.classList.add('mk9-page','mk9-'+info.key);
  }

  function createHero(info) {
    if (!info) return;
    if (document.querySelector('.mk9-page-hero')) return;

    var wrapper = document.getElementById('wrapper');
    if (!wrapper || wrapper.classList.contains('mk-admin-dashboard-page')) return;

    var content = wrapper.querySelector(':scope > .content');
    if (!content) content = wrapper.querySelector('.content');
    if (!content) return;

    var hero = document.createElement('section');
    hero.className = 'mk9-page-hero';
    hero.innerHTML =
      '<div class="mk9-page-copy">' +
        '<div class="mk9-page-kicker"><i class="'+info.icon+'"></i><span>MARKITO OPERATIONS</span></div>' +
        '<h1 class="mk9-page-title">'+info.title+'</h1>' +
        '<p class="mk9-page-subtitle">'+info.sub+'</p>' +
      '</div>' +
      '<div class="mk9-page-context"><i class="fa-regular fa-calendar"></i><span>'+new Intl.DateTimeFormat(undefined,{weekday:"short",day:"2-digit",month:"short"}).format(new Date())+'</span></div>';

    content.insertBefore(hero, content.firstChild);
  }

  function enhancePage() {
    var info = routeInfo();
    if (!info) return;

    addRouteIdentity(info);
    createHero(info);

    var page = document.getElementById('wrapper');
    if (!page) return;

    var toolbar = page.querySelector('._buttons');
    if (toolbar) toolbar.classList.add('mk-admin-page-toolbar');

    page.querySelectorAll('.panel_s').forEach(function (panel) {
      panel.classList.add('mk-motion-surface');
    });

    page.querySelectorAll('.leads-overview,.task-summary,[class*="tw-grid-cols-6"]')
      .forEach(function (el) { el.classList.add('mk-admin-summary-strip'); });
  }

  function repairTopStats() {
    var zone = document.querySelector('.mk-admin-dashboard-zone-top');
    if (!zone) return;

    /* Remove Phase-3 grid side effects from nested rows at runtime too. */
    zone.querySelectorAll('.widget.relative > .row > [class*="quick-stats-"] .row')
      .forEach(function (row) {
        row.style.removeProperty('display');
        row.style.removeProperty('grid-template-columns');
        row.style.removeProperty('gap');
      });
  }

  function boot9() {
    enhancePage();
    repairTopStats();

    var root = document.getElementById('wrapper');
    if (root && window.MutationObserver) {
      var queued = false;
      new MutationObserver(function () {
        if (queued) return;
        queued = true;
        requestAnimationFrame(function () {
          enhancePage();
          repairTopStats();
          queued = false;
        });
      }).observe(root,{childList:true,subtree:true});
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded',boot9,{once:true});
  } else {
    boot9();
  }
})();


/* ============================================================
   Markito Admin UI v1 — Phase 10 Runtime
   Robust table classification + dashboard options polish.
   ============================================================ */
(function () {
  'use strict';

  function classifyTables() {
    document.querySelectorAll('table.dataTable, table.table').forEach(function (table) {
      var count = table.querySelectorAll('thead th').length;

      if (count > 6) {
        table.classList.add('mk10-wide-table');
      } else {
        table.classList.remove('mk10-wide-table');
      }

      var scrollHost = table.closest('.panel-table-full,.table-responsive,.dataTables_scrollBody');
      if (scrollHost) scrollHost.classList.add('mk10-table-scroll');
    });
  }

  function markOptions() {
    var options = document.getElementById('dashboard-options');
    if (options) options.classList.add('mk10-dashboard-options');
  }

  function boot10() {
    classifyTables();
    markOptions();

    var root = document.getElementById('wrapper');
    if (root && window.MutationObserver) {
      var queued = false;
      new MutationObserver(function () {
        if (queued) return;
        queued = true;
        requestAnimationFrame(function () {
          classifyTables();
          markOptions();
          queued = false;
        });
      }).observe(root,{childList:true,subtree:true});
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded',boot10,{once:true});
  } else {
    boot10();
  }
})();


/* ============================================================
   Phase 10.1 Runtime Stabilization
   ============================================================ */
(function () {
  'use strict';

  function stabilizeTables() {
    document.querySelectorAll('table.dataTable, table.table').forEach(function (table) {
      var columns = table.querySelectorAll('thead th').length;

      if (columns >= 7) {
        table.classList.add('mk10-wide-table');
      } else {
        table.classList.remove('mk10-wide-table');
      }

      var host = table.closest('.panel-table-full,.table-responsive,.dataTables_scrollBody');
      if (host) {
        host.style.removeProperty('height');
        host.style.removeProperty('max-height');
      }
    });
  }

  function stabilizeKpis() {
    var row = document.querySelector('.mk10-kpi-grid');
    if (!row) return;

    row.querySelectorAll(':scope > [class*="quick-stats-"]').forEach(function (item) {
      item.classList.add('mk10-kpi-item');
    });
  }

  function boot101() {
    stabilizeKpis();
    stabilizeTables();

    var root = document.getElementById('wrapper');
    if (root && window.MutationObserver) {
      var queued = false;

      new MutationObserver(function () {
        if (queued) return;
        queued = true;

        requestAnimationFrame(function () {
          stabilizeKpis();
          stabilizeTables();
          queued = false;
        });
      }).observe(root,{childList:true,subtree:true});
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded',boot101,{once:true});
  } else {
    boot101();
  }
})();


/* ============================================================
   Phase 10.2 Runtime Guard
   ============================================================ */
(function () {
  'use strict';

  function salesToolbar() {
    if (!document.body.classList.contains('mk9-sales')) return;

    document.querySelectorAll('._buttons').forEach(function (bar) {
      bar.classList.add('mk102-sales-toolbar');
    });
  }

  function sidebarGuard() {
    var menu = document.getElementById('menu');
    if (!menu) return;

    menu.classList.add('mk102-contained-sidebar');

    /* When a submenu opens near the bottom, keep it visible by scrolling
       inside the sidebar instead of letting it leak over the page. */
    menu.addEventListener('click', function () {
      window.setTimeout(function () {
        var active = menu.querySelector('li.mm-active > .nav-second-level, li.active > .nav-second-level');
        if (!active) return;

        var menuRect = menu.getBoundingClientRect();
        var activeRect = active.getBoundingClientRect();

        if (activeRect.bottom > menuRect.bottom - 12) {
          menu.scrollTop += activeRect.bottom - menuRect.bottom + 20;
        }
      }, 220);
    });
  }

  function boot102() {
    salesToolbar();
    sidebarGuard();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot102, {once:true});
  } else {
    boot102();
  }
})();


/* ============================================================
   Phase 10.3 Runtime Guard
   ============================================================ */
(function () {
  'use strict';

  function dashboardOptionsInit() {
    var dashboard = document.querySelector('.mk-admin-dashboard-page');
    if (!dashboard) return;

    var area = dashboard.querySelector('.screen-options-area');
    if (!area) return;

    /* Always start closed after a full page load.
       Native Perfex .screen-options-btn slideToggle remains untouched. */
    if (!area.dataset.mk103Initialised) {
      area.style.display = 'none';
      area.dataset.mk103Initialised = '1';
    }
  }

  function filterDropdownGuard() {
    document.querySelectorAll('app-filters .btn-group').forEach(function (group) {
      if (group.dataset.mk103Filters === '1') return;
      group.dataset.mk103Filters = '1';

      /* Bootstrap adds .open. We only fix stacking; no filter behavior changes. */
      group.addEventListener('shown.bs.dropdown', function () {
        group.style.zIndex = '5001';
      });

      group.addEventListener('hidden.bs.dropdown', function () {
        group.style.removeProperty('z-index');
      });
    });
  }

  function boot103() {
    dashboardOptionsInit();
    filterDropdownGuard();

    var root = document.getElementById('wrapper');
    if (root && window.MutationObserver) {
      var queued = false;

      new MutationObserver(function () {
        if (queued) return;
        queued = true;

        requestAnimationFrame(function () {
          filterDropdownGuard();
          queued = false;
        });
      }).observe(root, {childList:true, subtree:true});
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot103, {once:true});
  } else {
    boot103();
  }
})();


/* ============================================================
   Phase 10.4 — Deterministic Dashboard Options Toggle
   ============================================================ */
(function () {
  'use strict';

  function getArea() {
    return document.querySelector('.mk104-dashboard-options-area');
  }

  window.mkToggleDashboardOptions = function () {
    var area = getArea();
    if (!area) return;

    var isHidden = window.getComputedStyle(area).display === 'none';

    if (window.jQuery) {
      var $area = window.jQuery(area);
      $area.stop(true, true);

      if (isHidden) {
        $area.slideDown(180, function () {
          area.style.display = 'block';
        });
      } else {
        $area.slideUp(160, function () {
          area.style.display = 'none';
        });
      }
    } else {
      area.style.display = isHidden ? 'block' : 'none';
    }
  };

  function initialiseDashboardOptions() {
    var area = getArea();
    if (!area) return;

    /* Dashboard JS may inject #dashboard-options after DOM ready.
       We only control visibility, never its native checkbox behavior. */
    area.style.display = 'none';
  }

  function boot104() {
    initialiseDashboardOptions();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot104, {once:true});
  } else {
    boot104();
  }
})();

/* ============================================================
   Markito Admin UI — Setup Click Runtime Fix v2
   ============================================================ */
(function () {
  'use strict';

  var marker = 'mkSetupRuntimeV2';

  function setupDrawer() {
    return document.getElementById('setup-menu-wrapper');
  }

  function isRtl() {
    return document.documentElement.getAttribute('dir') === 'rtl' ||
      (typeof window.isRTL !== 'undefined' && String(window.isRTL) === 'true');
  }

  function openSetup() {
    var drawer = setupDrawer();
    if (!drawer) return false;

    drawer.classList.remove('fadeOutLeft', 'fadeOutRight', 'fadeInLeft', 'fadeInRight');
    drawer.classList.add('display-block');
    drawer.classList.add(isRtl() ? 'fadeInRight' : 'fadeInLeft');

    drawer.style.setProperty('display', 'block', 'important');
    drawer.style.setProperty('visibility', 'visible', 'important');
    drawer.style.setProperty('opacity', '1', 'important');
    drawer.style.setProperty('z-index', '1250', 'important');

    document.body.classList.add('mk-setup-open');

    try {
      if (typeof window.requestGet === 'function') {
        if (!(typeof window.is_mobile === 'function' && window.is_mobile())) {
          window.requestGet('misc/set_setup_menu_open');
        }
      }
    } catch (e) {}

    return true;
  }

  function closeSetup() {
    var drawer = setupDrawer();
    if (!drawer) return false;

    drawer.classList.remove('fadeInLeft', 'fadeInRight');
    drawer.classList.add(isRtl() ? 'fadeOutRight' : 'fadeOutLeft');
    document.body.classList.remove('mk-setup-open');

    try {
      if (typeof window.requestGet === 'function') {
        window.requestGet('misc/set_setup_menu_closed');
      }
    } catch (e) {}

    window.setTimeout(function () {
      if (
        drawer.classList.contains('fadeOutLeft') ||
        drawer.classList.contains('fadeOutRight')
      ) {
        drawer.classList.remove('display-block');
        drawer.style.setProperty('display', 'none', 'important');
      }
    }, 220);

    return true;
  }

  function clickHandler(event) {
    var openButton = event.target.closest('.open-customizer, #setup-menu-item > a');
    if (openButton) {
      event.preventDefault();
      openSetup();
      return;
    }

    var closeButton = event.target.closest('.close-customizer');
    if (closeButton) {
      event.preventDefault();
      closeSetup();
    }
  }

  function boot() {
    if (document.documentElement.dataset[marker]) return;
    document.documentElement.dataset[marker] = '1';

    document.addEventListener('click', clickHandler, true);

    var drawer = setupDrawer();
    if (drawer && drawer.classList.contains('display-block')) {
      openSetup();
    }

    window.mkOpenSetupDrawer = openSetup;
    window.mkCloseSetupDrawer = closeSetup;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
