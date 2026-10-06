(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function reducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function setupHeader() {
    var header = document.getElementById('mk4-header');
    if (!header) return;

    function sync() {
      if (window.scrollY > 18) {
        header.classList.add('mk4-scrolled');
      } else {
        header.classList.remove('mk4-scrolled');
      }
    }

    sync();
    window.addEventListener('scroll', sync, { passive: true });
  }

  function setupCursorGlow() {
    qsa('.mk4-cursor-glow').forEach(function (el) {
      if (el.dataset.mk4Glow === '1') return;
      el.dataset.mk4Glow = '1';

      el.addEventListener('pointermove', function (event) {
        var rect = el.getBoundingClientRect();
        el.style.setProperty('--mk4-x', (event.clientX - rect.left) + 'px');
        el.style.setProperty('--mk4-y', (event.clientY - rect.top) + 'px');
      });
    });
  }

  function animateCounter(el) {
    if (!el || el.dataset.mk4Animated === '1') return;

    var target = parseInt(el.getAttribute('data-value') || '0', 10);
    if (!isFinite(target)) return;

    el.dataset.mk4Animated = '1';

    if (reducedMotion()) {
      el.textContent = target;
      return;
    }

    var started = null;
    var duration = 620;

    function frame(now) {
      if (!started) started = now;
      var p = Math.min((now - started) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased);

      if (p < 1) {
        requestAnimationFrame(frame);
      } else {
        el.textContent = target;
      }
    }

    requestAnimationFrame(frame);
  }

  function setupCounters() {
    var counters = qsa('.mk4-counter[data-value]');

    if (!('IntersectionObserver' in window)) {
      counters.forEach(animateCounter);
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: .35 });

    counters.forEach(function (counter) {
      io.observe(counter);
    });
  }

  function animateNativeProgressBars() {
    if (reducedMotion()) {
      qsa('[data-percent].progress-bar').forEach(function (bar) {
        bar.style.width = (bar.getAttribute('data-percent') || 0) + '%';
      });
      return;
    }

    qsa('[data-percent].progress-bar').forEach(function (bar) {
      if (bar.dataset.mk4Progress === '1') return;
      bar.dataset.mk4Progress = '1';
      var value = parseFloat(bar.getAttribute('data-percent') || '0');
      bar.style.width = '0%';

      requestAnimationFrame(function () {
        setTimeout(function () {
          bar.style.transition = 'width .8s cubic-bezier(.22,.61,.36,1)';
          bar.style.width = value + '%';
        }, 120);
      });
    });
  }

  function enhanceSingleTask() {
    var task = document.getElementById('task');
    if (!task) return;

    var assignees = task.querySelector('#assignees');
    if (assignees) {
      assignees.classList.add('mk4-assignee-strip');
    }

    qsa('.task_attachments_wrapper .task-attachment-col', task).forEach(function (item) {
      item.classList.add('mk4-attachment-card');
    });
  }

  function setupTaskRows() {
    qsa('.mk4-task-table tbody tr').forEach(function (row, index) {
      if (row.dataset.mk4Row === '1') return;
      row.dataset.mk4Row = '1';
      row.style.animationDelay = Math.min(index * 35, 280) + 'ms';
      row.classList.add('mk4-row-enter');
    });
  }

  function boot() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    setupHeader();
    setupCursorGlow();
    setupCounters();
    animateNativeProgressBars();
    enhanceSingleTask();
    setupTaskRows();

    if ('MutationObserver' in window) {
      var t = null;
      new MutationObserver(function () {
        clearTimeout(t);
        t = setTimeout(function () {
          setupCursorGlow();
          setupCounters();
          animateNativeProgressBars();
          enhanceSingleTask();
          setupTaskRows();
        }, 100);
      }).observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();

/* ============================================================
   Markito Client Portal v4 — Phase 1.1 Polish
   ============================================================ */
(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function syncMilestoneColumns() {
    qsa('.mk4-task-phases').forEach(function (phase) {
      var visible = qsa('.mk4-milestone-column', phase).filter(function (col) {
        return !col.classList.contains('hide') &&
          window.getComputedStyle(col).display !== 'none';
      });

      phase.setAttribute('data-mk4-columns', String(Math.max(1, visible.length)));
    });
  }

  function ensureRouteProgress() {
    var bar = document.getElementById('mk4-route-progress');
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'mk4-route-progress';
      document.body.appendChild(bar);
    }
    return bar;
  }

  function startRouteFeedback(link) {
    var bar = ensureRouteProgress();
    bar.classList.remove('is-finishing');
    bar.classList.add('is-active');

    var projectTab = link && link.closest && link.closest('.mk33-project-tabs');
    if (projectTab) {
      var stage = document.querySelector('.mk33-project-tab-stage');
      if (stage) {
        stage.classList.add('mk4-is-navigating');
      }
    }
  }

  function finishRouteFeedback() {
    var bar = ensureRouteProgress();
    bar.classList.remove('is-active');
    bar.classList.add('is-finishing');

    window.setTimeout(function () {
      bar.classList.remove('is-finishing');
    }, 350);

    var stage = document.querySelector('.mk33-project-tab-stage');
    if (stage) {
      stage.classList.remove('mk4-is-navigating');
    }
  }

  function setupRouteFeedback() {
    ensureRouteProgress();

    document.addEventListener('click', function (event) {
      if (event.defaultPrevented) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

      var link = event.target.closest && event.target.closest('a');
      if (!link) return;
      if (link.target === '_blank' || link.hasAttribute('download')) return;

      var href = link.getAttribute('href') || '';
      if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;

      try {
        var url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
      } catch (e) {
        return;
      }

      startRouteFeedback(link);
    }, true);

    window.addEventListener('pageshow', finishRouteFeedback);
    window.addEventListener('load', finishRouteFeedback);
  }

  function bootPolish() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    syncMilestoneColumns();
    setupRouteFeedback();

    window.addEventListener('resize', syncMilestoneColumns, { passive:true });

    if ('MutationObserver' in window) {
      var timer = null;
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(syncMilestoneColumns, 80);
      }).observe(document.body, { childList:true, subtree:true, attributes:true, attributeFilter:['class','style'] });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPolish);
  } else {
    bootPolish();
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 2 interactions
   ============================================================ */
(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function setupPhase2FileCards() {
    qsa('.mk42-file-card').forEach(function (card, index) {
      if (card.dataset.mk42Ready === '1') return;
      card.dataset.mk42Ready = '1';
      card.style.animationDelay = Math.min(index * 45, 320) + 'ms';
      card.classList.add('mk4-row-enter');
    });
  }

  function setupDiscussionCards() {
    qsa('.mk42-discussion-card').forEach(function (card, index) {
      if (card.dataset.mk42Ready === '1') return;
      card.dataset.mk42Ready = '1';
      card.style.animationDelay = Math.min(index * 45, 320) + 'ms';
      card.classList.add('mk4-row-enter');
    });
  }

  function setupSupportRows() {
    qsa('.mk42-ticket-table tbody tr').forEach(function (row, index) {
      if (row.dataset.mk42Ready === '1') return;
      row.dataset.mk42Ready = '1';
      row.style.animationDelay = Math.min(index * 30, 260) + 'ms';
      row.classList.add('mk4-row-enter');
    });
  }

  function bootPhase2() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    setupPhase2FileCards();
    setupDiscussionCards();
    setupSupportRows();

    if ('MutationObserver' in window) {
      var timer = null;
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          setupPhase2FileCards();
          setupDiscussionCards();
          setupSupportRows();
        }, 100);
      }).observe(document.body, { childList:true, subtree:true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPhase2);
  } else {
    bootPhase2();
  }
})();


/* ============================================================
   Markito World-Class Header v4.2.2
   Adaptive overflow + sticky compression
   ============================================================ */
(function () {
  'use strict';

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function setupWorldHeader() {
    var header = document.getElementById('mk43-header');
    var shell = document.getElementById('mk43-shell');
    var wrap = document.getElementById('mk43-primary-wrap');
    var list = document.getElementById('mk43-primary-list');
    var more = document.getElementById('mk43-more');
    var moreMenu = document.getElementById('mk43-more-menu');

    if (!header || !shell || !wrap || !list || !more || !moreMenu) {
      return;
    }

    function syncScroll() {
      if (window.scrollY > 18) {
        header.classList.add('mk43-scrolled');
      } else {
        header.classList.remove('mk43-scrolled');
      }
    }

    function restoreOverflow() {
      var moved = toArray(moreMenu.children);
      moved.forEach(function (item) {
        list.insertBefore(item, more);
      });

      more.classList.remove('is-visible', 'active');
    }

    function availablePrimaryWidth() {
      return wrap.getBoundingClientRect().width;
    }

    function currentPrimaryWidth() {
      var width = 0;
      toArray(list.children).forEach(function (item) {
        if (item === more && !more.classList.contains('is-visible')) return;
        if (window.getComputedStyle(item).display === 'none') return;
        width += item.getBoundingClientRect().width;
      });
      return width + 10;
    }

    function updateMoreActive() {
      var hasActive = !!moreMenu.querySelector('li.active');
      more.classList.toggle('active', hasActive);
    }

    function fitPrimaryNav() {
      restoreOverflow();

      if (window.innerWidth <= 1023) {
        return;
      }

      var items = toArray(list.querySelectorAll(':scope > li[data-mk43-primary="1"]'));

      // Keep Home plus at least two primary destinations visible.
      var minimumVisible = Math.min(3, items.length);

      while (
        currentPrimaryWidth() > availablePrimaryWidth() &&
        items.length > minimumVisible
      ) {
        var item = items.pop();

        if (!more.classList.contains('is-visible')) {
          more.classList.add('is-visible');
        }

        moreMenu.insertBefore(item, moreMenu.firstChild);
      }

      // Showing More itself consumes space, so run a final safety loop.
      while (
        currentPrimaryWidth() > availablePrimaryWidth() &&
        items.length > minimumVisible
      ) {
        var extra = items.pop();
        moreMenu.insertBefore(extra, moreMenu.firstChild);
      }

      updateMoreActive();
    }

    var resizeTimer = null;

    syncScroll();
    fitPrimaryNav();

    window.addEventListener('scroll', syncScroll, { passive:true });

    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(fitPrimaryNav, 80);
    }, { passive:true });

    window.addEventListener('load', fitPrimaryNav);

    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(fitPrimaryNav);
    }

    // Re-fit if plugins add/remove navigation links.
    if ('MutationObserver' in window) {
      var mutationTimer = null;
      new MutationObserver(function (records) {
        var relevant = records.some(function (record) {
          return record.target !== moreMenu;
        });

        if (!relevant) return;

        clearTimeout(mutationTimer);
        mutationTimer = setTimeout(fitPrimaryNav, 90);
      }).observe(list, { childList:true, subtree:false });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupWorldHeader);
  } else {
    setupWorldHeader();
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 3 interactions
   ============================================================ */
(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function animateRows() {
    qsa('.mk43-finance-table tbody tr').forEach(function (row, index) {
      if (row.dataset.mk43Ready === '1') return;
      row.dataset.mk43Ready = '1';
      row.style.animationDelay = Math.min(index * 34, 280) + 'ms';
      row.classList.add('mk4-row-enter');
    });
  }

  function markDocumentPage() {
    if (document.querySelector('.invoice-html')) {
      document.body.classList.add('mk43-document-invoice');
    }
    if (document.querySelector('.estimate-html')) {
      document.body.classList.add('mk43-document-estimate');
    }
    if (document.querySelector('.proposal-html')) {
      document.body.classList.add('mk43-document-proposal');
    }
    if (document.querySelector('.contract-html')) {
      document.body.classList.add('mk43-document-contract');
    }
  }

  function bootPhase3() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    animateRows();
    markDocumentPage();

    if ('MutationObserver' in window) {
      var timer = null;
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(animateRows, 100);
      }).observe(document.body, { childList:true, subtree:true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPhase3);
  } else {
    bootPhase3();
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 3.2
   Universal responsive runtime helper
   ============================================================ */
(function () {
  'use strict';

  var COMPACT_MAX = 1023;
  var lastMode = null;
  var timer = null;

  function isCompact() {
    return window.innerWidth <= COMPACT_MAX;
  }

  function applyViewportState() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    var width = window.innerWidth;
    var mode =
      width < 360 ? 'xxs' :
      width < 480 ? 'xs' :
      width < 768 ? 'sm' :
      width < 1024 ? 'md' :
      width < 1280 ? 'lg' :
      width < 1600 ? 'xl' : 'xxl';

    document.documentElement.setAttribute('data-mk-viewport', mode);
    document.documentElement.style.setProperty('--mk-runtime-vw', width + 'px');

    var header = document.getElementById('mk43-header');
    if (header) {
      header.setAttribute('data-mk-header-mode', isCompact() ? 'compact' : 'desktop');
    }

    // If we cross between compact and desktop while the menu is open,
    // normalize Bootstrap's collapse state so no stale inline height/display survives.
    if (lastMode !== null && lastMode !== (isCompact() ? 'compact' : 'desktop')) {
      var collapse = document.getElementById('theme-navbar-collapse');
      if (collapse) {
        collapse.style.height = '';
        collapse.classList.remove('collapsing');

        if (isCompact()) {
          collapse.classList.remove('in');
          collapse.setAttribute('aria-expanded', 'false');
        } else {
          collapse.classList.remove('in');
          collapse.setAttribute('aria-expanded', 'true');
        }
      }

      var toggle = document.querySelector('.mk43-mobile-toggle');
      if (toggle) {
        toggle.classList.add('collapsed');
        toggle.setAttribute('aria-expanded', 'false');
      }
    }

    lastMode = isCompact() ? 'compact' : 'desktop';
  }

  function closeCompactMenuAfterNavigation(event) {
    if (!isCompact()) return;

    var link = event.target.closest && event.target.closest('#mk43-primary-list a');
    if (!link || link.getAttribute('href') === '#') return;

    var collapse = document.getElementById('theme-navbar-collapse');
    var toggle = document.querySelector('.mk43-mobile-toggle');

    if (collapse && collapse.classList.contains('in') && window.jQuery) {
      window.jQuery(collapse).collapse('hide');
    } else if (collapse) {
      collapse.classList.remove('in');
      collapse.style.height = '';
    }

    if (toggle) {
      toggle.classList.add('collapsed');
      toggle.setAttribute('aria-expanded', 'false');
    }
  }

  applyViewportState();

  window.addEventListener('resize', function () {
    clearTimeout(timer);
    timer = setTimeout(applyViewportState, 80);
  }, { passive:true });

  window.addEventListener('orientationchange', function () {
    setTimeout(applyViewportState, 120);
  }, { passive:true });

  document.addEventListener('click', closeCompactMenuAfterNavigation);

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(applyViewportState);
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 3.3
   Premium motion runtime
   ============================================================ */
(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function setupAmbient() {
    if (!document.body || !document.body.classList.contains('customers')) return;
    if (document.querySelector('.mk33-ambient-dot')) return;

    var one = document.createElement('span');
    var two = document.createElement('span');

    one.className = 'mk33-ambient-dot is-one';
    two.className = 'mk33-ambient-dot is-two';

    document.body.appendChild(one);
    document.body.appendChild(two);

    var progress = document.createElement('div');
    progress.className = 'mk33-scroll-progress';
    progress.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progress);

    function syncProgress() {
      var doc = document.documentElement;
      var max = Math.max(1, doc.scrollHeight - window.innerHeight);
      var pct = Math.min(100, Math.max(0, (window.scrollY / max) * 100));
      progress.style.width = pct + '%';
    }

    syncProgress();
    window.addEventListener('scroll', syncProgress, { passive:true });
    window.addEventListener('resize', syncProgress, { passive:true });
  }

  function setupReveal() {
    var targets = qsa([
      '.mk2-dashboard > *',
      '.mk33-projects-hero',
      '.mk33-status-grid > *',
      '.mk33-project-workspace > *',
      '.mk42-page-hero',
      '.mk42-feature-hero',
      '.mk42-file-card',
      '.mk42-discussion-card',
      '.mk42-ticket-row',
      '.mk43-finance-hero',
      '.mk43-finance-summary-card',
      '.mk43-table-card',
      '.mk43-contract-chart-card',
      '.mk43-account-hero',
      '.mk43-calendar-hero',
      '.mk43-calendar-card',
      '.panel_s'
    ].join(','));

    targets.forEach(function (el, index) {
      if (el.dataset.mk33Reveal === '1') return;
      el.dataset.mk33Reveal = '1';
      el.classList.add('mk33-reveal');
      el.style.transitionDelay = Math.min((index % 8) * 45, 260) + 'ms';
    });

    if (!('IntersectionObserver' in window)) {
      targets.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold:.08,
      rootMargin:'0px 0px -4% 0px'
    });

    targets.forEach(function (el) {
      if (!el.classList.contains('is-visible')) observer.observe(el);
    });
  }

  function setupMagneticCards() {
    var finePointer = window.matchMedia &&
      window.matchMedia('(hover:hover) and (pointer:fine)').matches;

    var targets = qsa([
      '.mk2-card',
      '.mk33-card',
      '.mk42-file-card',
      '.mk42-discussion-card',
      '.mk43-finance-stat',
      '.mk43-table-card',
      '.mk43-finance-summary-card',
      '.mk43-contract-chart-card',
      '.mk43-calendar-card'
    ].join(','));

    targets.forEach(function (card) {
      if (card.dataset.mk33Magnetic === '1') return;
      card.dataset.mk33Magnetic = '1';
      card.classList.add('mk33-magnetic-card');

      if (!finePointer) return;

      card.addEventListener('pointermove', function (event) {
        var rect = card.getBoundingClientRect();
        var x = event.clientX - rect.left;
        var y = event.clientY - rect.top;

        var px = (x / rect.width) * 100;
        var py = (y / rect.height) * 100;

        card.style.setProperty('--mk-pointer-x', px + '%');
        card.style.setProperty('--mk-pointer-y', py + '%');

        var rotateY = ((x / rect.width) - .5) * 1.3;
        var rotateX = (((y / rect.height) - .5) * -1.0);

        card.style.transform =
          'perspective(900px) rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' +
          rotateY.toFixed(2) + 'deg) translateY(-2px)';
      });

      card.addEventListener('pointerleave', function () {
        card.style.transform = '';
      });
    });
  }

  function bootBrandMotion() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    setupAmbient();
    setupReveal();
    setupMagneticCards();

    if ('MutationObserver' in window) {
      var timer = null;
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          setupReveal();
          setupMagneticCards();
        }, 120);
      }).observe(document.body, { childList:true, subtree:true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootBrandMotion);
  } else {
    bootBrandMotion();
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 3.4
   Signature workflow reveal + subtle perspective
   ============================================================ */
(function () {
  'use strict';

  function bootWorkflow() {
    var section = document.getElementById('mk34-workflow');
    if (!section) return;

    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold:.14 });

      observer.observe(section);
    } else {
      section.classList.add('is-visible');
    }

    var finePointer = window.matchMedia &&
      window.matchMedia('(hover:hover) and (pointer:fine)').matches;

    if (!finePointer) return;

    section.querySelectorAll('.mk34-step').forEach(function (card) {
      card.addEventListener('pointermove', function (event) {
        var rect = card.getBoundingClientRect();
        var x = event.clientX - rect.left;
        var y = event.clientY - rect.top;

        card.style.setProperty('--mk34-x', ((x / rect.width) * 100) + '%');
        card.style.setProperty('--mk34-y', ((y / rect.height) * 100) + '%');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootWorkflow);
  } else {
    bootWorkflow();
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 3.4.1 Visual Polish
   Footer reveal + back-to-top
   ============================================================ */
(function () {
  'use strict';

  function bootPolish() {
    var footer = document.querySelector('.mk341-footer');
    var backTop = document.getElementById('mk341-back-top');

    if (footer) {
      if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              footer.classList.add('is-visible');
              observer.disconnect();
            }
          });
        }, { threshold:.08 });

        observer.observe(footer);
      } else {
        footer.classList.add('is-visible');
      }
    }

    if (backTop) {
      backTop.addEventListener('click', function () {
        var reduce = window.matchMedia &&
          window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        window.scrollTo({
          top:0,
          behavior:reduce ? 'auto' : 'smooth'
        });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPolish);
  } else {
    bootPolish();
  }
})();


/* ============================================================
   Phase 3.4.2 — slim footer back-to-top
   ============================================================ */
(function () {
  'use strict';

  function boot342() {
    var button = document.getElementById('mk342-back-top');
    if (!button) return;

    button.addEventListener('click', function () {
      var reduce = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      window.scrollTo({
        top:0,
        behavior:reduce ? 'auto' : 'smooth'
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot342);
  } else {
    boot342();
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 3.5
   All Pages Experience runtime
   ============================================================ */
(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function removeLegacyScrollControls() {
    ['toplink', 'botlink'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.parentNode) {
        el.parentNode.removeChild(el);
      }
    });
  }

  function addRouteIdentity() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    var path = (window.location.pathname || '/').toLowerCase();
    var route = 'generic';

    if (/\/clients\/projects/.test(path)) route = 'projects';
    else if (/\/clients\/invoices/.test(path)) route = 'invoices';
    else if (/\/clients\/contracts/.test(path)) route = 'contracts';
    else if (/\/clients\/estimates/.test(path)) route = 'estimates';
    else if (/\/clients\/proposals/.test(path)) route = 'proposals';
    else if (/\/clients\/tickets|\/clients\/open_ticket/.test(path)) route = 'support';
    else if (/\/clients\/files/.test(path)) route = 'files';
    else if (/\/clients\/calendar/.test(path)) route = 'calendar';
    else if (/\/clients\/profile|\/clients\/company/.test(path)) route = 'account';
    else if (/\/knowledge-base|\/clients\/knowledge_base/.test(path)) route = 'knowledge';
    else if (/\/clients\/?$/.test(path) || /markito-local\/?$/.test(path)) route = 'home';

    document.body.setAttribute('data-mk35-route', route);
    document.body.classList.add('mk35-page-ready');
  }

  function enhanceTopHeading() {
    var candidates = qsa('#content .section-heading');
    var heading = candidates.find(function (el) {
      return !el.closest('.panel_s') && !el.closest('.modal');
    });

    if (heading) {
      heading.classList.add('mk35-page-heading');
    }
  }

  function setupReveal() {
    var selectors = [
      '#content .panel_s',
      '#content .panel',
      '#content .well',
      '#content .list-group',
      '#content .table-responsive',
      '#content .dataTables_wrapper',
      '#content .kb-search-jumbotron',
      '#content .mk33-projects-hero',
      '#content .mk33-project-list-panel',
      '#content .mk33-project-workspace',
      '#content .mk42-page-hero',
      '#content .mk42-feature-hero',
      '#content .mk43-finance-hero',
      '#content .mk43-account-hero',
      '#content .mk43-calendar-hero',
      '#content .mk43-calendar-card',
      '#content .mk43-table-card',
      '#content .mk43-finance-summary-card',
      '#content .mk42-file-card',
      '#content .mk42-discussion-card'
    ].join(',');

    var targets = qsa(selectors);

    targets.forEach(function (el, index) {
      if (el.dataset.mk35Reveal === '1') return;
      el.dataset.mk35Reveal = '1';
      el.classList.add('mk35-reveal');
      el.style.transitionDelay = Math.min((index % 7) * 38, 220) + 'ms';
    });

    if (!('IntersectionObserver' in window)) {
      targets.forEach(function (el) {
        el.classList.add('mk35-visible');
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('mk35-visible');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold:.05,
      rootMargin:'0px 0px -3% 0px'
    });

    targets.forEach(function (el) {
      if (!el.classList.contains('mk35-visible')) {
        observer.observe(el);
      }
    });
  }

  function setupRows() {
    qsa('#content table tbody tr').forEach(function (row, index) {
      if (row.dataset.mk35Row === '1') return;
      row.dataset.mk35Row = '1';
      row.classList.add('mk35-row-enter');
      row.style.animationDelay = Math.min((index % 12) * 28, 280) + 'ms';
    });
  }

  function setupGlowCards() {
    var finePointer = window.matchMedia &&
      window.matchMedia('(hover:hover) and (pointer:fine)').matches;

    var cards = qsa([
      '#content .panel_s',
      '#content .mk33-kpi',
      '#content .mk42-file-card',
      '#content .mk42-discussion-card',
      '#content .mk43-finance-stat',
      '#content .list-group-item'
    ].join(','));

    cards.forEach(function (card) {
      if (card.dataset.mk35Glow === '1') return;
      card.dataset.mk35Glow = '1';
      card.classList.add('mk35-glow-card', 'mk35-power-card');

      if (!finePointer) return;

      card.addEventListener('pointermove', function (event) {
        var rect = card.getBoundingClientRect();
        card.style.setProperty('--mk35-x', (event.clientX - rect.left) + 'px');
        card.style.setProperty('--mk35-y', (event.clientY - rect.top) + 'px');
      });
    });
  }

  function setupButtonRipples() {
    document.addEventListener('click', function (event) {
      var target = event.target.closest &&
        event.target.closest('.btn, .mk33-hero-link, .mk43-inline-action, .mk42-primary-action');

      if (!target) return;
      if (window.matchMedia &&
          window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

      var rect = target.getBoundingClientRect();
      var ripple = document.createElement('span');
      ripple.className = 'mk35-ripple';
      ripple.style.left = (event.clientX - rect.left) + 'px';
      ripple.style.top = (event.clientY - rect.top) + 'px';

      target.appendChild(ripple);
      setTimeout(function () {
        if (ripple.parentNode) ripple.parentNode.removeChild(ripple);
      }, 650);
    });
  }

  function setupEmptyStates() {
    qsa([
      '.mk2-empty-icon',
      '.mk42-empty-icon',
      '.no-data',
      '.empty-state',
      '[class*="empty-state"]'
    ].join(',')).forEach(function (el) {
      el.classList.add('mk35-empty-pulse');
    });
  }

  function normalizePageScroll() {
    // Clear stale inline heights/overflow that can survive plugins or resize events.
    ['wrapper', 'content'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.style.removeProperty('height');
      el.style.removeProperty('max-height');
      el.style.removeProperty('overflow-y');
    });

    qsa('.fc-view, .tasks-phases .kan-ban-col .panel-body').forEach(function (el) {
      el.style.removeProperty('height');
      el.style.removeProperty('max-height');
      el.style.removeProperty('overflow-y');
    });
  }

  function bootAllPages() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    removeLegacyScrollControls();
    normalizePageScroll();
    addRouteIdentity();
    enhanceTopHeading();
    setupReveal();
    setupRows();
    setupGlowCards();
    setupEmptyStates();
    setupButtonRipples();

    if ('MutationObserver' in window) {
      var timer = null;
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
          removeLegacyScrollControls();
          normalizePageScroll();
          setupReveal();
          setupRows();
          setupGlowCards();
          setupEmptyStates();
        }, 100);
      }).observe(document.body, { childList:true, subtree:true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAllPages);
  } else {
    bootAllPages();
  }
})();


/* ============================================================
   Markito Client Portal v4 — Phase 3.6
   Runtime layout stabilizer
   ============================================================ */
(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function isAllowedVerticalScroller(el) {
    if (!el || !el.matches) return true;

    return el.matches([
      '.modal',
      '.modal *',
      '.dropdown-menu',
      '.dropdown-menu *',
      '.select2-results',
      '.select2-results *',
      '.project_file_area',
      '.project_file_discusssions_area',
      'textarea',
      'select',
      '.bootstrap-select .dropdown-menu',
      '.bootstrap-select .dropdown-menu *',
      '.mCustomScrollbar',
      '.dataTables_scrollBody'
    ].join(','));
  }

  function normalizeRootScroll() {
    var html = document.documentElement;
    var body = document.body;

    html.style.setProperty('overflow-x', 'hidden', 'important');
    html.style.setProperty('overflow-y', 'auto', 'important');
    html.style.setProperty('height', 'auto', 'important');
    html.style.setProperty('max-height', 'none', 'important');
    html.style.setProperty('scrollbar-gutter', 'auto', 'important');

    if (body && body.classList.contains('customers') && !body.classList.contains('modal-open')) {
      body.style.setProperty('overflow-x', 'hidden', 'important');
      body.style.setProperty('overflow-y', 'visible', 'important');
      body.style.setProperty('height', 'auto', 'important');
      body.style.setProperty('max-height', 'none', 'important');
    }

    ['wrapper', 'content'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;

      el.classList.add('mk36-no-nested-y');
      el.style.removeProperty('height');
      el.style.removeProperty('max-height');
      el.style.removeProperty('overflow-y');
    });
  }

  function removeLargeNestedVerticalScrollers() {
    var viewport = Math.max(window.innerHeight || 0, 600);

    qsa('body.customers *').forEach(function (el) {
      if (isAllowedVerticalScroller(el)) return;

      var style;
      try {
        style = window.getComputedStyle(el);
      } catch (e) {
        return;
      }

      var overflowY = style.overflowY;
      if (overflowY !== 'auto' && overflowY !== 'scroll') return;

      var rect = el.getBoundingClientRect();
      var isLarge = rect.height >= viewport * 0.52;
      var actuallyScrollable = el.scrollHeight > el.clientHeight + 5;

      if (isLarge && actuallyScrollable) {
        el.classList.add('mk36-no-nested-y');
      }
    });
  }

  function stabilizeProjectTabs() {
    var shell = document.querySelector('.mk33-project-tabs-shell');
    if (!shell) return;

    shell.style.setProperty('position', 'relative', 'important');
    shell.style.setProperty('top', 'auto', 'important');

    var stage = document.querySelector('.mk33-project-tab-stage');
    if (stage) {
      stage.style.setProperty('clear', 'both', 'important');
      stage.style.setProperty('overflow-y', 'visible', 'important');
    }
  }

  function staggerProjectContent() {
    var stage = document.querySelector('.mk33-project-tab-stage');
    if (!stage) return;

    qsa([
      '.mk33-overview-card',
      '.mk33-finance-card',
      '.mk33-description-card',
      '.mk4-task-stat',
      '.mk4-milestone-column',
      '.mk42-upload-shell',
      '.mk42-file-card',
      '.mk42-discussion-card',
      '.mk42-gantt-shell',
      '.mk42-ticket-status-shell',
      '.mk42-table-card'
    ].join(','), stage).forEach(function (el, index) {
      if (el.dataset.mk36Stagger === '1') return;

      el.dataset.mk36Stagger = '1';
      el.classList.add('mk36-stagger-item');
      el.style.setProperty('--mk36-delay', Math.min(index * 42, 280) + 'ms');
    });
  }

  function normalizeKanbanHeight() {
    qsa('.tasks-phases .kan-ban-col, .tasks-phases .kan-ban-col .panel-body').forEach(function (el) {
      el.style.setProperty('height', 'auto', 'important');
      el.style.setProperty('max-height', 'none', 'important');
      el.style.setProperty('overflow-y', 'visible', 'important');
    });
  }

  function runStabilizer() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    normalizeRootScroll();
    stabilizeProjectTabs();
    normalizeKanbanHeight();
    removeLargeNestedVerticalScrollers();
    staggerProjectContent();
  }

  var resizeTimer = null;
  var mutationTimer = null;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runStabilizer);
  } else {
    runStabilizer();
  }

  window.addEventListener('load', function () {
    runStabilizer();
    setTimeout(runStabilizer, 250);
  });

  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(runStabilizer, 120);
  }, { passive:true });

  if ('MutationObserver' in window) {
    new MutationObserver(function () {
      clearTimeout(mutationTimer);
      mutationTimer = setTimeout(runStabilizer, 140);
    }).observe(document.documentElement, {
      childList:true,
      subtree:true
    });
  }
})();



/* Phase 3.6.1 body-scroll owner removed by Phase 3.6.3. */



/* ============================================================
   Phase 3.6.3 — Natural document scroll stabilizer
   ============================================================ */
(function () {
  'use strict';

  function restoreNaturalDocumentScroll() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    var html = document.documentElement;
    var body = document.body;

    html.style.setProperty('height', 'auto', 'important');
    html.style.setProperty('min-height', '100%', 'important');
    html.style.setProperty('max-height', 'none', 'important');
    html.style.setProperty('overflow-x', 'hidden', 'important');
    html.style.setProperty('overflow-y', 'auto', 'important');

    if (!body.classList.contains('modal-open')) {
      body.style.setProperty('height', 'auto', 'important');
      body.style.setProperty('min-height', '100vh', 'important');
      body.style.setProperty('max-height', 'none', 'important');
      body.style.setProperty('overflow-x', 'hidden', 'important');
      body.style.setProperty('overflow-y', 'visible', 'important');
    }

    [
      '#wrapper',
      '#content',
      '#content > .container',
      '#content > .container-fluid',
      '.mk33-project-list-panel',
      '.mk33-project-list-panel > .panel-body',
      '.mk33-project-list-panel .mk33-table-shell',
      '.mk33-project-list-panel .dataTables_wrapper',
      '.mk33-project-list-panel .dataTables_scroll',
      '.mk33-project-list-panel .dataTables_scrollHead',
      '.mk33-project-list-panel .dataTables_scrollBody'
    ].forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (el) {
        el.style.setProperty('height', 'auto', 'important');
        el.style.setProperty('min-height', '0', 'important');
        el.style.setProperty('max-height', 'none', 'important');
        el.style.setProperty('overflow', 'visible', 'important');
        el.style.setProperty('overflow-y', 'visible', 'important');
      });
    });
  }

  function boot363() {
    restoreNaturalDocumentScroll();
    setTimeout(restoreNaturalDocumentScroll, 100);
    setTimeout(restoreNaturalDocumentScroll, 350);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot363);
  } else {
    boot363();
  }

  window.addEventListener('load', restoreNaturalDocumentScroll);

  window.addEventListener('resize', function () {
    clearTimeout(window.__mk363Resize);
    window.__mk363Resize = setTimeout(restoreNaturalDocumentScroll, 120);
  }, { passive:true });
})();


/* ============================================================
   Phase 3.6.4 — nested scroll cleanup + discussion modal guard
   ============================================================ */
(function () {
  'use strict';

  var cleanupSelectors = [
    '.mk33-project-workspace',
    '.mk33-project-workspace > .panel-body',
    '.mk33-project-tab-stage',
    '.mk42-discussion-body',
    '.mk42-discussion-grid',
    '.mk42-upload-shell',
    '.mk42-ticket-status-shell',
    '.mk42-table-card',
    '.mk43-calendar-shell',
    '.mk43-table-card',
    '.mk43-finance-summary-card',
    '.mk43-contract-chart-card',
    '.mk4-task-phases',
    '.kan-ban-content-wrapper'
  ];

  function flattenNestedWorkspaceScroll() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    cleanupSelectors.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (el) {
        el.style.setProperty('height', 'auto', 'important');
        el.style.setProperty('min-height', '0', 'important');
        el.style.setProperty('max-height', 'none', 'important');
        el.style.setProperty('overflow-y', 'visible', 'important');
      });
    });

    document.querySelectorAll(
      '.mk33-project-workspace .mCustomScrollBox, ' +
      '.mk33-project-workspace .mCSB_container, ' +
      '.mk33-project-workspace .scrollable, ' +
      '.mk33-project-workspace .scroll-content'
    ).forEach(function (el) {
      el.style.setProperty('height', 'auto', 'important');
      el.style.setProperty('max-height', 'none', 'important');
      el.style.setProperty('overflow-y', 'visible', 'important');
    });
  }

  function prepareDiscussionModal() {
    var modal = document.getElementById('discussion');
    if (!modal) return;

    var dialog = modal.querySelector('.modal-dialog');
    var content = modal.querySelector('.modal-content');
    var body = modal.querySelector('.modal-body');
    var footer = modal.querySelector('.modal-footer');

    if (dialog) dialog.classList.add('mk364-discussion-dialog');
    if (content) content.classList.add('mk364-discussion-modal');
    if (body) body.classList.add('mk364-discussion-modal-body');
    if (footer) footer.classList.add('mk364-discussion-modal-footer');
  }

  function boot364() {
    flattenNestedWorkspaceScroll();
    prepareDiscussionModal();

    setTimeout(function () {
      flattenNestedWorkspaceScroll();
      prepareDiscussionModal();
    }, 120);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot364);
  } else {
    boot364();
  }

  document.addEventListener('shown.bs.modal', function (event) {
    if (event.target && event.target.id === 'discussion') {
      prepareDiscussionModal();
      var body = event.target.querySelector('.mk364-discussion-modal-body');
      if (body) body.scrollTop = 0;
    }
  });

  if ('MutationObserver' in window) {
    var timer = null;
    new MutationObserver(function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        flattenNestedWorkspaceScroll();
        prepareDiscussionModal();
      }, 100);
    }).observe(document.body, { childList:true, subtree:true });
  }
})();


/* ============================================================
   Phase 3.6.5 — remove useless/unwanted nested vertical scrollbars
   ============================================================ */
(function () {
  'use strict';

  var allowedSelectors = [
    '.modal',
    '.modal-body',
    '.dropdown-menu',
    '.bootstrap-select .dropdown-menu',
    '.select2-results',
    '.project_file_area',
    '.project_file_discusssions_area',
    'textarea',
    'select',
    '.mce-container',
    '.mce-container *',
    '.tox',
    '.tox *'
  ];

  function isAllowed(el) {
    if (!el || !el.matches) return true;
    return allowedSelectors.some(function (selector) {
      try {
        return el.matches(selector) || !!el.closest(selector);
      } catch (e) {
        return false;
      }
    });
  }

  function killUselessVerticalScrollbars() {
    if (!document.body || !document.body.classList.contains('customers')) return;

    var viewport = Math.max(window.innerHeight || 0, 600);

    document.querySelectorAll('body.customers *').forEach(function (el) {
      if (isAllowed(el)) return;

      var style;
      try {
        style = window.getComputedStyle(el);
      } catch (e) {
        return;
      }

      if (style.overflowY !== 'auto' && style.overflowY !== 'scroll') {
        return;
      }

      var rect = el.getBoundingClientRect();
      if (rect.height <= 0) return;

      var hasRealVerticalOverflow = el.scrollHeight > el.clientHeight + 4;
      var isLargePageLikeViewport = rect.height >= viewport * 0.42;

      /* Case A: scrollbar track exists but there is nothing useful to scroll. */
      if (!hasRealVerticalOverflow) {
        el.classList.add('mk365-no-y-scroll');
        return;
      }

      /* Case B: a large page-like container has captured vertical page scrolling.
         Flatten it and let the browser page scroll naturally. */
      if (isLargePageLikeViewport) {
        el.classList.add('mk365-no-y-scroll');
      }
    });
  }

  function prepareDiscussionModal365() {
    var modal = document.getElementById('discussion');
    if (!modal) return;

    modal.classList.add('mk365-discussion-modal-wrap');

    var dialog = modal.querySelector('.modal-dialog');
    var content = modal.querySelector('.modal-content');
    var form = modal.querySelector('#discussion_form');
    var body = modal.querySelector('.modal-body');
    var footer = modal.querySelector('.modal-footer');

    if (dialog) dialog.classList.add('mk365-discussion-dialog');
    if (content) content.classList.add('mk365-discussion-modal');
    if (form) form.classList.add('mk365-discussion-form');
    if (body) body.classList.add('mk365-discussion-modal-body');
    if (footer) footer.classList.add('mk365-discussion-modal-footer');
  }

  function boot365() {
    prepareDiscussionModal365();
    killUselessVerticalScrollbars();

    setTimeout(killUselessVerticalScrollbars, 120);
    setTimeout(killUselessVerticalScrollbars, 420);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot365);
  } else {
    boot365();
  }

  window.addEventListener('load', boot365);

  window.addEventListener('resize', function () {
    clearTimeout(window.__mk365Resize);
    window.__mk365Resize = setTimeout(function () {
      prepareDiscussionModal365();
      killUselessVerticalScrollbars();
    }, 120);
  }, { passive:true });

  if ('MutationObserver' in window) {
    var timer = null;
    new MutationObserver(function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        prepareDiscussionModal365();
        killUselessVerticalScrollbars();
      }, 120);
    }).observe(document.body, { childList:true, subtree:true });
  }
})();


/* ============================================================
   Phase 3.6.6 — move Create Discussion modal to BODY root.
   This avoids Bootstrap fixed-modal freezes caused by transformed
   / overflow-hidden project ancestors.
   ============================================================ */
(function () {
  'use strict';

  function moveDiscussionModalToBody() {
    var modal = document.getElementById('discussion');
    if (!modal || !document.body) return;

    if (modal.parentNode !== document.body) {
      document.body.appendChild(modal);
    }

    modal.classList.add('mk365-discussion-modal-wrap');

    var dialog = modal.querySelector('.modal-dialog');
    var content = modal.querySelector('.modal-content');
    var form = modal.querySelector('#discussion_form');
    var body = modal.querySelector('.modal-body');
    var footer = modal.querySelector('.modal-footer');

    if (dialog) dialog.classList.add('mk365-discussion-dialog');
    if (content) content.classList.add('mk365-discussion-modal');
    if (form) form.classList.add('mk365-discussion-form');
    if (body) body.classList.add('mk365-discussion-modal-body');
    if (footer) footer.classList.add('mk365-discussion-modal-footer');
  }

  function normalizeModalState() {
    var modal = document.getElementById('discussion');
    if (!modal) return;

    /* If Bootstrap left stale backdrops from prior broken modal state, keep one. */
    var backdrops = Array.prototype.slice.call(document.querySelectorAll('body > .modal-backdrop'));
    if (backdrops.length > 1) {
      backdrops.slice(0, -1).forEach(function (el) {
        if (el.parentNode) el.parentNode.removeChild(el);
      });
    }
  }

  function boot366() {
    moveDiscussionModalToBody();
    normalizeModalState();

    /* Bootstrap 3 uses jQuery events. Use them when available. */
    if (window.jQuery) {
      var $ = window.jQuery;
      var $modal = $('#discussion');

      $modal.off('.mk366');

      $modal.on('show.bs.modal.mk366', function () {
        moveDiscussionModalToBody();
        normalizeModalState();
      });

      $modal.on('shown.bs.modal.mk366', function () {
        moveDiscussionModalToBody();
        var modalBody = this.querySelector('.mk365-discussion-modal-body');
        if (modalBody) modalBody.scrollTop = 0;
      });

      $modal.on('hidden.bs.modal.mk366', function () {
        normalizeModalState();

        /* Clean only stale Bootstrap backdrop/body state. */
        if (!document.querySelector('.modal.in')) {
          document.body.classList.remove('modal-open');
          document.querySelectorAll('body > .modal-backdrop').forEach(function (el) {
            if (el.parentNode) el.parentNode.removeChild(el);
          });
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot366);
  } else {
    boot366();
  }

  window.addEventListener('load', function () {
    moveDiscussionModalToBody();
    normalizeModalState();
  });
})();
