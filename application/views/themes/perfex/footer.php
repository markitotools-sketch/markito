<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<footer class="footer mk342-footer">
    <div class="container">
        <div class="mk342-footer-shell">
            <div class="mk342-footer-brand">
                <span class="mk342-footer-mark">M</span>
                <div>
                    <strong>Markito Client Portal</strong>
                    <small>Projects · Files · Billing · Support</small>
                </div>
            </div>

            <div class="mk342-footer-center">
                <span class="copyright-footer">
                    © <?= date('Y'); ?> <?= e(_l('clients_copyright', get_option('companyname'))); ?>
                </span>

                <?php if (is_gdpr() && get_option('gdpr_show_terms_and_conditions_in_footer') == '1') { ?>
                    <a href="<?= terms_url(); ?>" class="terms-and-conditions-footer">
                        <?= _l('terms_and_conditions'); ?>
                    </a>
                <?php } ?>

                <?php if (is_gdpr() && is_client_logged_in() && get_option('show_gdpr_link_in_footer') == '1') { ?>
                    <a href="<?= site_url('clients/gdpr'); ?>" class="gdpr-footer">
                        <?= _l('gdpr_short'); ?>
                    </a>
                <?php } ?>
            </div>

            <button type="button" class="mk342-back-top" id="mk342-back-top" aria-label="Back to top">
                <span>Back to top</span>
                <i class="fa-solid fa-arrow-up"></i>
            </button>
        </div>
    </div>
</footer>

<!-- THEME STYLE CUSTOMER NAVIGATION RUNTIME BRIDGE v1 -->
<style id="theme-style-customer-nav-runtime-bridge-v1">
body.customers > .navbar.header {
    background: var(--theme-style-stable-nav-bg, inherit) !important;
    background-image: none !important;
}
</style>
<script id="theme-style-customer-nav-runtime-bridge-v1-js">
(function () {
    'use strict';
    function opaqueRgb(value) {
        if (!value) return null;
        var m = value.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/i);
        if (!m) return value;
        return 'rgb(' + Math.round(parseFloat(m[1])) + ',' + Math.round(parseFloat(m[2])) + ',' + Math.round(parseFloat(m[3])) + ')';
    }
    function themeNavigationColor() {
        var style = document.getElementById('theme_style_generated');
        if (!style || !style.sheet || !style.sheet.cssRules) return null;
        try {
            for (var i = style.sheet.cssRules.length - 1; i >= 0; i--) {
                var rule = style.sheet.cssRules[i];
                if (!rule.selectorText || !rule.style) continue;
                var s = rule.selectorText;
                var isNavigationRule =
                    s.indexOf('body.customers.customers > nav.navbar.navbar-default.header') !== -1 ||
                    s.indexOf('body.customers.customers > .navbar.navbar-default.header') !== -1 ||
                    s.indexOf('body.customers nav.navbar.navbar-default.header') !== -1 ||
                    s.indexOf('body.customers .navbar.navbar-default.header') !== -1;
                if (!isNavigationRule) continue;
                var bg = rule.style.getPropertyValue('background') || rule.style.getPropertyValue('background-color');
                if (bg) return opaqueRgb(bg.trim());
            }
        } catch (e) {}
        return null;
    }
    function applyThemeNavigationColor() {
        var header = document.querySelector('body.customers > .navbar.header');
        if (!header) return;
        var color = themeNavigationColor();
        if (!color) return;
        document.documentElement.style.setProperty('--theme-style-stable-nav-bg', color);
        header.style.setProperty('background', color, 'important');
        header.style.setProperty('background-image', 'none', 'important');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyThemeNavigationColor);
    } else {
        applyThemeNavigationColor();
    }
    window.addEventListener('load', applyThemeNavigationColor);
    setTimeout(applyThemeNavigationColor, 250);
})();
</script>
