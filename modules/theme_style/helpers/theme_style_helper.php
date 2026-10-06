<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Theme Style Pro v4
 * Selector map audited against the current Perfex application views/resources.
 */
function get_styling_areas($type = 'admin')
{
    $areas = [
        'admin' => [
            [
                'name' => _l('theme_style_sidebar_bg_color'),
                'id' => 'admin-menu',
                'target' => 'html body.mk-admin-shell #menu.sidebar',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_setup_bg_color'),
                'id' => 'admin-setup-menu',
                'target' => '#setup-menu-wrapper',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_sidebar_links_color'),
                'id' => 'admin-menu-links',
                'target' => '#side-menu li > a, #setup-menu li > a',
                'css' => 'color',
                'additional_selectors' => '#side-menu li > a i|color+#setup-menu li > a i|color+#side-menu li > a svg|color+#setup-menu li > a svg|color',
            ],
            [
                'name' => _l('theme_style_sidebar_active_item_bg_color'),
                'id' => 'admin-menu-active-item',
                'target' => '#side-menu li.active > a, #side-menu li.mm-active > a, #setup-menu li.active > a, #setup-menu li.mm-active > a, #setup-menu li > a[aria-expanded="true"]',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_sidebar_active_item_color'),
                'id' => 'admin-menu-active-item-color',
                'target' => '#side-menu li.active > a, #side-menu li.mm-active > a, #setup-menu li.active > a, #setup-menu li.mm-active > a, #setup-menu li > a[aria-expanded="true"]',
                'css' => 'color',
                'additional_selectors' => '#side-menu li.active > a i|color+#side-menu li.mm-active > a i|color+#setup-menu li.active > a i|color+#setup-menu li.mm-active > a i|color',
            ],
            [
                'name' => _l('theme_style_top_header_bg_color'),
                'id' => 'top-header',
                'target' => '#header, #header.navbar, .admin-navbar',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_top_header_bg_links_color'),
                'id' => 'top-header-links',
                'target' => '#header a, #header i, #header svg, .admin-navbar a, .admin-navbar i, .admin-navbar svg',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_content_background_color'),
                'id' => 'content',
                'target' => '#wrapper, #wrapper > .content, #wrapper .content, body.admin #wrapper',
                'css' => 'background',
                'additional_selectors' => '',
            ],
        ],

        'customers' => [
            [
                'name' => _l('theme_style_navigation_bg_color'),
                'id' => 'customers-navigation',
                'target' => 'body.customers.customers > nav.navbar.navbar-default.header.mk43-header, body.customers.customers > .navbar.navbar-default.header.mk43-header, body.customers.customers > nav.navbar.navbar-default.header.mk4-header, body.customers.customers > .navbar.navbar-default.header.mk4-header, body.customers nav.navbar.navbar-default.header, body.customers .navbar.navbar-default.header, body.mk-client-theme nav.navbar, body.mk-client-theme .navbar',
                'css' => 'background',
                'additional_selectors' => 'body.customers nav.navbar.navbar-default.header|background-image+body.mk-client-theme nav.navbar|background-image',
            ],
            [
                'name' => _l('theme_style_navigation_link_color'),
                'id' => 'customers-navigation-links',
                'target' => 'body.customers .navbar.navbar-default .navbar-nav > li > a, body.customers .navbar.navbar-default .navbar-brand, body.customers .navbar.navbar-default i, body.customers .navbar.navbar-default svg, body.mk-client-theme .navbar a, body.mk-client-theme .navbar i, body.mk-client-theme .navbar svg',
                'css' => 'color',
                'additional_selectors' => 'body.customers .navbar-default .navbar-toggle .icon-bar|background-color',
            ],
            [
                'name' => _l('theme_style_customer_content_bg_color'),
                'id' => 'customers-content-background',
                'target' => 'body.customers #wrapper, body.customers #content, body.customers #content > .container, body.mk-client-theme #wrapper, body.mk-client-theme #content',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_customer_panel_bg_color'),
                'id' => 'customers-panel-background',
                'target' => 'body.customers .panel, body.customers .panel_s .panel-body, body.customers .card, body.customers .widget, body.customers .well, body.mk-client-theme .panel, body.mk-client-theme .panel_s .panel-body, body.mk-client-theme .card, body.mk-client-theme .widget',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_customer_text_color'),
                'id' => 'customers-text-color',
                'target' => 'body.customers #content, body.customers .panel-body, body.customers .table, body.customers label, body.customers p, body.customers h1, body.customers h2, body.customers h3, body.customers h4, body.customers h5, body.customers h6, body.mk-client-theme #content, body.mk-client-theme .panel-body',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_customer_border_color'),
                'id' => 'customers-border-color',
                'target' => 'body.customers .panel, body.customers .panel_s .panel-body, body.customers .table > thead > tr > th, body.customers .table > tbody > tr > td, body.customers .dropdown-menu, body.mk-client-theme .panel, body.mk-client-theme .table > thead > tr > th, body.mk-client-theme .table > tbody > tr > td',
                'css' => 'border-color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_customer_submenu_bg_color'),
                'id' => 'customers-submenu-background',
                'target' => 'body.customers .customer-top-submenu, body.customers ul.submenu.customer-top-submenu, body.mk-client-theme .customer-top-submenu',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_customer_submenu_links_color'),
                'id' => 'customers-submenu-links',
                'target' => 'body.customers .customer-top-submenu a, body.customers .customer-top-submenu svg, body.mk-client-theme .customer-top-submenu a, body.mk-client-theme .customer-top-submenu svg',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_footer_background'),
                'id' => 'customers-footer-background',
                'target' => 'body.customers.customers footer.footer.mk342-footer, body.customers.customers .footer.mk342-footer, body.customers.customers footer.footer.mk341-footer, body.customers.customers .footer.mk341-footer, body.customers footer.footer, body.customers footer, body.customers .footer, body.mk-client-theme footer, body.mk-client-theme .footer',
                'css' => 'background',
                'additional_selectors' => 'body.customers footer.footer|background-image+body.mk-client-theme footer|background-image',
            ],
            [
                'name' => _l('theme_style_footer_text_color'),
                'id' => 'customers-footer-text',
                'target' => 'body.customers footer.footer, body.customers footer.footer a, body.customers footer, body.customers footer a, body.customers .footer, body.customers .footer a, body.mk-client-theme footer, body.mk-client-theme footer a',
                'css' => 'color',
                'additional_selectors' => '',
            ],
        ],

        'general' => [
            [
                'name' => _l('theme_style_links'),
                'id' => 'links',
                'target' => 'a:not(.btn):not(.label):not(.badge):not(.close)',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_link_hover_color'),
                'id' => 'links-hover',
                'target' => 'a:not(.btn):not(.label):not(.badge):not(.close):hover, a:not(.btn):not(.label):not(.badge):not(.close):focus',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_body_text_color'),
                'id' => 'body-text',
                'target' => '.panel-body, .modal-body, .dropdown-menu, .table, label, p, .form-group, h1, h2, h3, h4, h5, h6',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_text_muted'),
                'id' => 'text-muted',
                'target' => '.text-muted, .help-block',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_panel_background_color'),
                'id' => 'panel-background',
                'target' => '.panel, .panel_s .panel-body, .modal-content, .dropdown-menu, .widget, .card, .well',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_border_color'),
                'id' => 'border-color',
                'target' => '.panel, .panel_s .panel-body, .modal-content, .dropdown-menu, .form-control, .table > thead > tr > th, .table > tbody > tr > td, .input-group-addon, .well',
                'css' => 'border-color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_input_background_color'),
                'id' => 'input-background',
                'target' => '.form-control, .bootstrap-select > .dropdown-toggle, .select2-container .select2-selection, .input-group-addon',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_input_text_color'),
                'id' => 'input-text',
                'target' => '.form-control, .bootstrap-select > .dropdown-toggle, .select2-container .select2-selection, .select2-selection__rendered',
                'css' => 'color',
                'additional_selectors' => '.form-control::placeholder|color',
            ],
            [
                'name' => _l('theme_style_input_border_color'),
                'id' => 'input-border',
                'target' => '.form-control, .bootstrap-select > .dropdown-toggle, .select2-container .select2-selection, .input-group-addon',
                'css' => 'border-color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_admin_login_background'),
                'id' => 'admin-login-background',
                'target' => 'body.login_admin',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            ['name'=>_l('theme_style_text_danger'),'id'=>'text-danger','target'=>'.text-danger','css'=>'color','additional_selectors'=>''],
            ['name'=>_l('theme_style_text_warning'),'id'=>'text-warning','target'=>'.text-warning','css'=>'color','additional_selectors'=>''],
            ['name'=>_l('theme_style_text_info'),'id'=>'text-info','target'=>'.text-info','css'=>'color','additional_selectors'=>''],
            ['name'=>_l('theme_style_text_success'),'id'=>'text-success','target'=>'.text-success','css'=>'color','additional_selectors'=>''],
        ],

        'buttons' => [
            ['name'=>_l('theme_style_button_default'),'id'=>'btn-default','target'=>'.btn-default','css'=>'background','additional_selectors'=>''],
            ['name'=>_l('theme_style_button_primary'),'id'=>'btn-primary','target'=>'.btn-primary','css'=>'background','additional_selectors'=>''],
            ['name'=>_l('theme_style_button_info'),'id'=>'btn-info','target'=>'.btn-info','css'=>'background','additional_selectors'=>''],
            ['name'=>_l('theme_style_button_success'),'id'=>'btn-success','target'=>'.btn-success','css'=>'background','additional_selectors'=>''],
            ['name'=>_l('theme_style_button_danger'),'id'=>'btn-danger','target'=>'.btn-danger','css'=>'background','additional_selectors'=>''],
        ],

        'tabs' => [
            [
                'name' => _l('theme_style_tabs_bg_color'),
                'id' => 'tabs-bg',
                'target' => '.nav-tabs, .nav-tabs-segmented',
                'css' => 'background',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_tabs_links_color'),
                'id' => 'tabs-links',
                'target' => '.nav-tabs > li > a, .nav-tabs-segmented > li > a',
                'css' => 'color',
                'additional_selectors' => '.nav-tabs > li > a i|color+.nav-tabs-segmented > li > a i|color',
            ],
            [
                'name' => _l('theme_style_tabs_active_links_color'),
                'id' => 'tabs-links-active-hover',
                'target' => '.nav-tabs > li.active > a, .nav-tabs > li.active > a:focus, .nav-tabs > li.active > a:hover, .nav-tabs > li > a:hover, .nav-tabs > li > a:focus, .nav-tabs-segmented > li.active > a, .nav-tabs-segmented > li > a:hover, .nav-tabs-segmented > li > a:focus',
                'css' => 'color',
                'additional_selectors' => '',
            ],
            [
                'name' => _l('theme_style_tabs_active_border_color'),
                'id' => 'tabs-active-border',
                'target' => '.nav-tabs > li.active > a, .nav-tabs > li.active > a:focus, .nav-tabs > li.active > a:hover, .nav-tabs-segmented > li.active > a',
                'css' => 'border-bottom-color',
                'additional_selectors' => '',
            ],
        ],

        'modals' => [
            ['name'=>_l('theme_style_modal_heading_bg'),'id'=>'modal-heading','target'=>'.modal-header','css'=>'background','additional_selectors'=>''],
            ['name'=>_l('theme_style_modal_heading_color'),'id'=>'modal-heading-color','target'=>'.modal-header .modal-title','css'=>'color','additional_selectors'=>''],
            ['name'=>_l('theme_style_modal_close_btn_color'),'id'=>'modal-close-button-color','target'=>'.modal-header .close, .modal-header button.close','css'=>'color','additional_selectors'=>''],
            ['name'=>_l('theme_style_modal_header_text_color'),'id'=>'modal-header-text-color','target'=>'.modal-header > *:not(.modal-title):not(.close)','css'=>'color','additional_selectors'=>''],
        ],

        'tables' => [
            ['name'=>_l('theme_style_table_headings_background'),'id'=>'table-headings-bg','target'=>'table.dataTable thead th, .table > thead > tr > th','css'=>'background','additional_selectors'=>''],
            ['name'=>_l('theme_style_table_headings_color'),'id'=>'table-headings','target'=>'table.dataTable thead th, .table > thead > tr > th','css'=>'color','additional_selectors'=>''],
            ['name'=>_l('theme_style_table_links_color'),'id'=>'table-links-color','target'=>'.dataTables_wrapper table tbody a:not(.btn), .table tbody a:not(.btn)','css'=>'color','additional_selectors'=>''],
            ['name'=>_l('theme_style_table_links_hover_focus_color'),'id'=>'table-links-hover-focus-color','target'=>'.dataTables_wrapper table tbody a:not(.btn):hover, .dataTables_wrapper table tbody a:not(.btn):focus, .table tbody a:not(.btn):hover, .table tbody a:not(.btn):focus','css'=>'color','additional_selectors'=>''],
        ],
    ];

    $tags = get_tags();
    $areas['tags'] = [];

    foreach ($tags as $tag) {
        $areas['tags'][] = [
            'name' => $tag['name'],
            'id' => 'tag-' . $tag['id'],
            'target' => '.tag-id-' . $tag['id'],
            'css' => 'color',
            'additional_selectors' => 'ul.tagit li.tagit-choice.tag-id-' . $tag['id'] . ' .tagit-label:not(a)|color',
        ];
    }

    $areas = hooks()->apply_filters('get_styling_areas', $areas);

    if (!is_array($type)) {
        return $areas[$type] ?? [];
    }

    $result = [];
    foreach ($type as $t) {
        $result[$t] = $areas[$t] ?? [];
    }

    return $result;
}

function get_applied_styling_area()
{
    $decoded = json_decode((string) get_option('theme_style'));
    return is_array($decoded) ? $decoded : [];
}

function theme_style_is_valid_color($value)
{
    return (bool) preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', trim((string) $value));
}

function get_custom_style_values($type, $selector)
{
    foreach (get_applied_styling_area() as $item) {
        if (isset($item->id, $item->color) && (string) $item->id === (string) $selector) {
            return (string) $item->color;
        }
    }

    return '';
}

function render_theme_styling_picker($id, $value, $target, $css, $additional = '')
{
    echo '<div class="input-group mbot15 colorpicker-component" data-target="' . html_escape($target) . '" data-css="' . html_escape($css) . '" data-additional="' . html_escape($additional) . '">';
    echo '<input type="text" value="' . html_escape($value) . '" data-id="' . html_escape($id) . '" class="form-control" />';
    echo '<span class="input-group-addon"><i></i></span>';
    echo '</div>';
}

function theme_style_scope_selector($selector, $scope = 'all')
{
    $selector = trim((string) $selector);

    if ($selector === '') {
        return '';
    }

    if (strpos($selector, 'html ') === 0) {
        return $selector;
    }

    if (strpos($selector, 'body.') === 0 || strpos($selector, 'body#') === 0 || $selector === 'body') {
        return 'html ' . $selector;
    }

    $prefixes = ['html body'];

    if ($scope === 'admin') {
        $prefixes = ['html body.admin', 'html body.mk-admin-shell'];
    } elseif ($scope === 'customers') {
        $prefixes = ['html body.customers', 'html body.mk-client-theme'];
    } elseif ($scope === 'auth') {
        $prefixes = ['html body.login_admin'];
    } elseif ($scope === 'external') {
        $prefixes = ['html body'];
    }

    $out = [];
    foreach ($prefixes as $prefix) {
        $out[] = $prefix . ' ' . $selector;
    }

    return implode(', ', array_unique($out));
}

function theme_style_scope_selector_list($selectors, $scope = 'all')
{
    $out = [];

    foreach (array_filter(array_map('trim', explode(',', (string) $selectors))) as $selector) {
        $scoped = theme_style_scope_selector($selector, $scope);
        if ($scoped !== '') {
            $out[] = $scoped;
        }
    }

    return implode(', ', $out);
}

function theme_style_contrast_text($hex)
{
    $hex = ltrim((string) $hex, '#');

    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if (strlen($hex) < 6) {
        return '#ffffff';
    }

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return ((($r * 299) + ($g * 587) + ($b * 114)) / 1000) > 168 ? '#172033' : '#ffffff';
}

function theme_style_css_rule($selectors, $property, $value, $scope)
{
    $selectors = theme_style_scope_selector_list($selectors, $scope);
    if ($selectors === '') {
        return '';
    }

    return $selectors . '{' . $property . ':' . $value . ' !important;}' . PHP_EOL;
}

function theme_style_button_css($target, $color, $scope)
{
    $normal = theme_style_scope_selector_list($target, $scope);
    $hover = adjust_color_brightness($color, -35);
    $text = theme_style_contrast_text($color);
    $hoverText = theme_style_contrast_text($hover);
    $states = [];

    foreach (array_filter(array_map('trim', explode(',', $target))) as $selector) {
        foreach ([':hover', ':focus', '.focus', ':active', '.active'] as $state) {
            $states[] = theme_style_scope_selector($selector . $state, $scope);
        }

        $states[] = theme_style_scope_selector('.open > .dropdown-toggle' . $selector, $scope);
    }

    $css = $normal . '{background:' . $color . ' !important;background-color:' . $color . ' !important;border-color:' . $color . ' !important;color:' . $text . ' !important;box-shadow:none !important;}' . PHP_EOL;
    $css .= implode(', ', array_filter($states)) . '{background:' . $hover . ' !important;background-color:' . $hover . ' !important;border-color:' . $hover . ' !important;color:' . $hoverText . ' !important;}' . PHP_EOL;

    return $css;
}

function theme_style_render($types, $scope = 'all')
{
    $applied = get_applied_styling_area();

    if (empty($applied)) {
        return;
    }

    $types = is_array($types) ? $types : [$types];
    $areas = get_styling_areas($types);

    $saved = [];
    foreach ($applied as $item) {
        if (isset($item->id, $item->color) && theme_style_is_valid_color($item->color)) {
            $saved[(string) $item->id] = trim((string) $item->color);
        }
    }

    echo '<style id="theme_style_generated" data-scope="' . html_escape($scope) . '">' . PHP_EOL;

    foreach ($areas as $groupName => $group) {
        foreach ($group as $area) {
            if (!isset($saved[$area['id']])) {
                continue;
            }

            $color = $saved[$area['id']];

            if ($groupName === 'buttons') {
                echo theme_style_button_css($area['target'], $color, $scope);
                continue;
            }

            echo theme_style_css_rule($area['target'], $area['css'], $color, $scope);

            if (!empty($area['additional_selectors'])) {
                foreach (explode('+', $area['additional_selectors']) as $item) {
                    $parts = explode('|', $item, 2);

                    if (count($parts) === 2) {
                        // background-image needs "none" rather than the chosen color.
                        $value = $parts[1] === 'background-image' ? 'none' : $color;
                        echo theme_style_css_rule($parts[0], $parts[1], $value, $scope);
                    }
                }
            }
        }
    }

    echo '</style>' . PHP_EOL;
}

function theme_style_get_fonts()
{
    return [
        'System UI' => ['stack'=>'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif','google'=>''],
        'Inter' => ['stack'=>'"Inter",Arial,sans-serif','google'=>'Inter:wght@300;400;500;600;700;800'],
        'Poppins' => ['stack'=>'"Poppins",Arial,sans-serif','google'=>'Poppins:wght@300;400;500;600;700;800'],
        'Manrope' => ['stack'=>'"Manrope",Arial,sans-serif','google'=>'Manrope:wght@300;400;500;600;700;800'],
        'Roboto' => ['stack'=>'"Roboto",Arial,sans-serif','google'=>'Roboto:wght@300;400;500;700;900'],
        'Open Sans' => ['stack'=>'"Open Sans",Arial,sans-serif','google'=>'Open+Sans:wght@300;400;500;600;700;800'],
        'Montserrat' => ['stack'=>'"Montserrat",Arial,sans-serif','google'=>'Montserrat:wght@300;400;500;600;700;800'],
        'DM Sans' => ['stack'=>'"DM Sans",Arial,sans-serif','google'=>'DM+Sans:wght@300;400;500;600;700'],
        'Plus Jakarta Sans' => ['stack'=>'"Plus Jakarta Sans",Arial,sans-serif','google'=>'Plus+Jakarta+Sans:wght@300;400;500;600;700;800'],
        'Outfit' => ['stack'=>'"Outfit",Arial,sans-serif','google'=>'Outfit:wght@300;400;500;600;700;800'],
        'Space Grotesk' => ['stack'=>'"Space Grotesk",Arial,sans-serif','google'=>'Space+Grotesk:wght@300;400;500;600;700'],
        'Sora' => ['stack'=>'"Sora",Arial,sans-serif','google'=>'Sora:wght@300;400;500;600;700;800'],
        'Rubik' => ['stack'=>'"Rubik",Arial,sans-serif','google'=>'Rubik:wght@300;400;500;600;700;800'],
        'Cairo' => ['stack'=>'"Cairo",Tahoma,Arial,sans-serif','google'=>'Cairo:wght@300;400;500;600;700;800'],
        'Tajawal' => ['stack'=>'"Tajawal",Tahoma,Arial,sans-serif','google'=>'Tajawal:wght@300;400;500;700;800'],
        'Almarai' => ['stack'=>'"Almarai",Tahoma,Arial,sans-serif','google'=>'Almarai:wght@300;400;700;800'],
        'Alexandria' => ['stack'=>'"Alexandria",Tahoma,Arial,sans-serif','google'=>'Alexandria:wght@300;400;500;600;700;800'],
        'Readex Pro' => ['stack'=>'"Readex Pro",Tahoma,Arial,sans-serif','google'=>'Readex+Pro:wght@300;400;500;600;700'],
        'Noto Sans Arabic' => ['stack'=>'"Noto Sans Arabic",Tahoma,Arial,sans-serif','google'=>'Noto+Sans+Arabic:wght@300;400;500;600;700;800'],
        'Noto Kufi Arabic' => ['stack'=>'"Noto Kufi Arabic",Tahoma,Arial,sans-serif','google'=>'Noto+Kufi+Arabic:wght@300;400;500;600;700;800'],
        'Arial' => ['stack'=>'Arial,sans-serif','google'=>''],
        'Tahoma' => ['stack'=>'Tahoma,Arial,sans-serif','google'=>''],
        'Georgia' => ['stack'=>'Georgia,serif','google'=>''],
    ];
}

function theme_style_render_font()
{
    $fonts = theme_style_get_fonts();
    $family = get_option('theme_style_font_family') ?: 'System UI';

    if (!isset($fonts[$family])) {
        $family = 'System UI';
    }

    $size = (int) get_option('theme_style_font_size');
    if ($size < 12 || $size > 18) {
        $size = 14;
    }

    $font = $fonts[$family];

    if ($font['google'] !== '') {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . PHP_EOL;
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . PHP_EOL;
        echo '<link href="https://fonts.googleapis.com/css2?family=' . $font['google'] . '&display=swap" rel="stylesheet">' . PHP_EOL;
    }

    echo '<style id="theme_style_font">' . PHP_EOL;
    echo ':root{--theme-style-font:' . $font['stack'] . ';--theme-style-font-size:' . $size . 'px;}' . PHP_EOL;
    echo 'body,body .content,body #wrapper,body .panel,body .panel-body,body .modal,body .modal-content,body .dropdown-menu,body .popover,body .navbar,body .sidebar,body #setup-menu-wrapper,body h1,body h2,body h3,body h4,body h5,body h6,body p,body a,body label,body small,body strong,body b,body em,body li,body td,body th,body input,body textarea,body select,body button,body option,body .btn,body .form-control,body .input-group,body .input-group-addon,body .bootstrap-select,body .select2-container,body .select2-selection,body .select2-results,body .dataTables_wrapper,body .table,body .nav,body .nav-tabs,body .nav-pills,body .breadcrumb,body .label,body .badge,body .alert,body .well,body .list-group,body .list-group-item{font-family:var(--theme-style-font) !important;}' . PHP_EOL;
    echo 'body{font-size:var(--theme-style-font-size) !important;}' . PHP_EOL;

    echo '.fa,.fas,.far,.fal,.fat,.fad,.fab,.fa-solid,.fa-regular,.fa-light,.fa-thin,.fa-duotone,.fa-brands,.fa:before,.fas:before,.far:before,.fal:before,.fat:before,.fad:before,.fab:before,.fa-solid:before,.fa-regular:before,.fa-light:before,.fa-thin:before,.fa-duotone:before,.fa-brands:before{font-family:"Font Awesome 6 Free","Font Awesome 5 Free","FontAwesome" !important;}' . PHP_EOL;
    echo '.fab,.fa-brands,.fab:before,.fa-brands:before{font-family:"Font Awesome 6 Brands","Font Awesome 5 Brands","FontAwesome" !important;}' . PHP_EOL;
    echo '.glyphicon,.glyphicon:before{font-family:"Glyphicons Halflings" !important;}' . PHP_EOL;
    echo '.material-icons,.material-icons-outlined,.material-symbols-outlined{font-family:"Material Icons","Material Symbols Outlined" !important;}' . PHP_EOL;
    echo 'code,kbd,pre,samp,.CodeMirror,.CodeMirror *,.ace_editor,.ace_editor *{font-family:monospace !important;}' . PHP_EOL;
    echo '</style>' . PHP_EOL;
}

function theme_style_all_area_ids()
{
    $ids = [];

    foreach (['admin','customers','general','buttons','tabs','modals','tables','tags'] as $group) {
        foreach (get_styling_areas($group) as $area) {
            $ids[] = $area['id'];
        }
    }

    return array_values(array_unique($ids));
}

function theme_style_audit_manifest()
{
    $manifest = [];

    foreach (['admin','customers','general','buttons','tabs','modals','tables','tags'] as $group) {
        foreach (get_styling_areas($group) as $area) {
            $manifest[] = [
                'group' => $group,
                'id' => $area['id'],
                'property' => $area['css'],
                'target' => $area['target'],
            ];
        }
    }

    return $manifest;
}

function is_admin_sidebar_background_light()
{
    return false;
}

function determine_header_logo_url_based_on_background_color($url)
{
    return $url;
}
