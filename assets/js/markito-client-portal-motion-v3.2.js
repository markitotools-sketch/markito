(function () {
  'use strict';

  function qsa(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function setupReveal() {
    var selectors = [
      '.panel_s',
      '.well',
      '.alert',
      '.dropzone',
      '.list-group',
      '.mk2-card',
      '.mk2-action',
      '.mk2-status'
    ].join(',');

    var items = qsa(selectors).filter(function (el) {
      return !el.classList.contains('mk3-reveal');
    });

    items.forEach(function (el, index) {
      el.classList.add('mk3-reveal');
      el.setAttribute('data-mk3-delay', String((index % 5) + 1));
    });

    if (!('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('mk3-in'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('mk3-in');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.08,
      rootMargin: '0px 0px -24px 0px'
    });

    items.forEach(function (el) {
      observer.observe(el);
    });
  }

  function setupPageTransition() {
    var veil = document.createElement('div');
    veil.id = 'mk3-page-transition';
    document.body.appendChild(veil);

    document.addEventListener('click', function (event) {
      var link = event.target.closest && event.target.closest('a');
      if (!link) return;
      if (event.defaultPrevented) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      if (link.target === '_blank') return;
      if (link.hasAttribute('download')) return;

      var href = link.getAttribute('href') || '';
      if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;

      try {
        var url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
      } catch (e) {
        return;
      }

      veil.classList.add('mk3-active');
    }, true);

    window.addEventListener('pageshow', function () {
      veil.classList.remove('mk3-active');
    });
  }

  function setupDynamicContentObserver() {
    if (!('MutationObserver' in window)) return;

    var timer = null;
    var observer = new MutationObserver(function () {
      clearTimeout(timer);
      timer = setTimeout(setupReveal, 80);
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  function boot() {
    if (!document.body || !document.body.classList.contains('customers')) return;
    setupReveal();
    setupPageTransition();
    setupDynamicContentObserver();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();

/* Markito Premium Projects v3.3 */
(function () {
  'use strict';

  function animateCounter(el) {
    if (!el || el.dataset.mk33Animated === '1') return;

    var target = parseInt(el.getAttribute('data-count') || '0', 10);
    if (!isFinite(target)) return;

    el.dataset.mk33Animated = '1';

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      el.textContent = target;
      return;
    }

    var start = 0;
    var duration = 650;
    var startTime = null;

    function step(time) {
      if (!startTime) startTime = time;
      var progress = Math.min((time - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(start + ((target - start) * eased));
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target;
      }
    }

    requestAnimationFrame(step);
  }

  function setupCounters() {
    var els = Array.prototype.slice.call(document.querySelectorAll('.mk33-count[data-count]'));

    if (!('IntersectionObserver' in window)) {
      els.forEach(animateCounter);
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

    els.forEach(function (el) { io.observe(el); });
  }

  function setupSpotlights() {
    Array.prototype.slice.call(document.querySelectorAll('.mk33-spotlight')).forEach(function (el) {
      if (el.dataset.mk33Spotlight === '1') return;
      el.dataset.mk33Spotlight = '1';

      el.addEventListener('pointermove', function (event) {
        var r = el.getBoundingClientRect();
        el.style.setProperty('--mk33-x', (event.clientX - r.left) + 'px');
        el.style.setProperty('--mk33-y', (event.clientY - r.top) + 'px');
      });
    });
  }

  function setupProjectTabs() {
    var stage = document.querySelector('.mk33-project-tab-stage');
    if (!stage) return;

    Array.prototype.slice.call(document.querySelectorAll('.mk33-project-tabs a')).forEach(function (link) {
      link.addEventListener('click', function () {
        stage.style.opacity = '.55';
        stage.style.transform = 'translateY(5px)';
      });
    });
  }

  function bootPremiumProjects() {
    setupCounters();
    setupSpotlights();
    setupProjectTabs();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPremiumProjects);
  } else {
    bootPremiumProjects();
  }
})();
