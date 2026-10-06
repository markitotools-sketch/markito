<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-mb-6">
                            <div>
                                <h4 class="tw-m-0 tw-font-bold"><?= _l('theme_style'); ?></h4>
                                <p class="text-muted tw-mt-2 tw-mb-0"><?= _l('theme_style_description'); ?></p>
                            </div>
                            <a href="<?= admin_url('theme_style/reset'); ?>"
                               class="btn btn-default _delete"
                               data-toggle="tooltip"
                               title="<?= _l('theme_style_reset_info'); ?>">
                                <?= _l('theme_style_reset'); ?>
                            </a>
                        </div>

                        <ul class="nav nav-tabs" id="theme_styling_areas" role="tablist">
                            <li class="active"><a href="#tab_admin" data-toggle="tab"><?= _l('theme_style_admin'); ?></a></li>
                            <li><a href="#tab_customers" data-toggle="tab"><?= _l('theme_style_customers'); ?></a></li>
                            <li><a href="#tab_general" data-toggle="tab"><?= _l('theme_style_general'); ?></a></li>
                            <li><a href="#tab_buttons" data-toggle="tab"><?= _l('theme_style_buttons'); ?></a></li>
                            <li><a href="#tab_tabs" data-toggle="tab"><?= _l('theme_style_tabs'); ?></a></li>
                            <li><a href="#tab_modals" data-toggle="tab"><?= _l('theme_style_modals'); ?></a></li>
                            <li><a href="#tab_tables" data-toggle="tab"><?= _l('theme_style_tables'); ?></a></li>
                            <li><a href="#tab_fonts" data-toggle="tab"><?= _l('theme_style_fonts'); ?></a></li>
                            <li><a href="#tab_test_lab" data-toggle="tab">Test Lab</a></li>
                            <?php if (count(get_styling_areas('tags')) > 0) { ?>
                            <li><a href="#tab_tags" data-toggle="tab"><?= _l('theme_style_tags'); ?></a></li>
                            <?php } ?>
                            <li><a href="#tab_custom_css" data-toggle="tab"><?= _l('theme_style_custom_css'); ?></a></li>
                        </ul>

                        <div class="tab-content tw-mt-6">
                            <?php
                            $tabs = [
                                'admin' => 'tab_admin',
                                'customers' => 'tab_customers',
                                'general' => 'tab_general',
                                'buttons' => 'tab_buttons',
                                'tabs' => 'tab_tabs',
                                'modals' => 'tab_modals',
                                'tables' => 'tab_tables',
                            ];
                            foreach ($tabs as $group => $tabId) { ?>
                            <div role="tabpanel" class="tab-pane <?= $group === 'admin' ? 'active' : ''; ?>" id="<?= $tabId; ?>">
                                <div class="row">
                                    <?php foreach (get_styling_areas($group) as $area) { ?>
                                    <div class="col-md-6">
                                        <label class="bold mbot10 inline-block"><?= $area['name']; ?></label>
                                        <?php render_theme_styling_picker(
                                            $area['id'],
                                            get_custom_style_values($group, $area['id']),
                                            $area['target'],
                                            $area['css'],
                                            $area['additional_selectors']
                                        ); ?>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>

                            <div role="tabpanel" class="tab-pane" id="tab_fonts">
                                <?php
                                $fonts = theme_style_get_fonts();
                                $fontFamily = get_option('theme_style_font_family') ?: 'System UI';
                                $fontSize = (int) (get_option('theme_style_font_size') ?: 14);
                                ?>
                                <div class="row">
                                    <div class="col-md-7">
                                        <div class="form-group">
                                            <label class="bold" for="theme_style_font_family"><?= _l('theme_style_font_family'); ?></label>
                                            <select id="theme_style_font_family" class="selectpicker" data-width="100%" data-live-search="true">
                                                <?php foreach ($fonts as $name => $meta) { ?>
                                                <option value="<?= html_escape($name); ?>" <?= $fontFamily === $name ? 'selected' : ''; ?>>
                                                    <?= html_escape($name); ?>
                                                </option>
                                                <?php } ?>
                                            </select>
                                            <p class="text-muted mtop5"><?= _l('theme_style_font_scope_info'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label class="bold" for="theme_style_font_size"><?= _l('theme_style_font_size'); ?></label>
                                            <select id="theme_style_font_size" class="selectpicker" data-width="100%">
                                                <?php for ($i = 12; $i <= 18; $i++) { ?>
                                                <option value="<?= $i; ?>" <?= $fontSize === $i ? 'selected' : ''; ?>><?= $i; ?> px</option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="panel_s mtop15">
                                    <div class="panel-body" id="theme_font_preview">
                                        <h4 class="bold"><?= _l('theme_style_font_preview'); ?></h4>
                                        <p><?= _l('theme_style_font_preview_en'); ?></p>
                                        <p><?= _l('theme_style_font_preview_ar'); ?></p>
                                        <button type="button" class="btn btn-primary"><?= _l('theme_style_button_primary'); ?></button>
                                    </div>
                                </div>
                            </div>

                            <?php if (count(get_styling_areas('tags')) > 0) { ?>
                            <div role="tabpanel" class="tab-pane" id="tab_tags">
                                <div class="row">
                                    <?php foreach (get_styling_areas('tags') as $area) { ?>
                                    <div class="col-md-6">
                                        <label class="bold mbot10 inline-block"><?= html_escape($area['name']); ?></label>
                                        <?php render_theme_styling_picker(
                                            $area['id'],
                                            get_custom_style_values('tags', $area['id']),
                                            $area['target'],
                                            $area['css'],
                                            $area['additional_selectors']
                                        ); ?>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>


                            <div role="tabpanel" class="tab-pane" id="tab_test_lab">
                                <div class="alert alert-info">
                                    This lab contains real Perfex UI classes so you can verify Buttons, Tabs, General, Tables and Modal styling before leaving Theme Style.
                                </div>

                                <div class="panel_s">
                                    <div class="panel-body">
                                        <h4 class="bold">Buttons</h4>
                                        <p>
                                            <button type="button" class="btn btn-default">Default</button>
                                            <button type="button" class="btn btn-primary">Primary</button>
                                            <button type="button" class="btn btn-info">Info</button>
                                            <button type="button" class="btn btn-success">Success</button>
                                            <button type="button" class="btn btn-danger">Danger</button>
                                        </p>

                                        <hr />

                                        <h4 class="bold">General + Inputs</h4>
                                        <p class="text-muted">Muted text example</p>
                                        <p>
                                            <span class="text-success">Success text</span> ·
                                            <span class="text-info">Info text</span> ·
                                            <span class="text-warning">Warning text</span> ·
                                            <span class="text-danger">Danger text</span>
                                        </p>
                                        <div class="form-group">
                                            <label>Example input</label>
                                            <input class="form-control" placeholder="Theme Style input preview" />
                                        </div>

                                        <hr />

                                        <h4 class="bold">Tabs</h4>
                                        <ul class="nav nav-tabs">
                                            <li class="active"><a href="javascript:void(0)">Active Tab</a></li>
                                            <li><a href="javascript:void(0)">Normal Tab</a></li>
                                        </ul>

                                        <hr />

                                        <h4 class="bold">Table</h4>
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Heading One</th>
                                                        <th>Heading Two</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><a href="javascript:void(0)">Example table link</a></td>
                                                        <td>Example data</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <hr />

                                        <h4 class="bold">Modal</h4>
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#theme-style-test-modal">
                                            Open Test Modal
                                        </button>

                                        <hr />

                                        <h4 class="bold">Runtime Diagnostics</h4>
                                        <button type="button" class="btn btn-default" id="run-theme-style-diagnostics">Run DOM Diagnostics</button>
                                        <button type="button" class="btn btn-info" id="run-theme-style-server-audit">Run Server Audit</button>
                                        <div id="theme-style-diagnostics-result" class="mtop15"></div>
                                    </div>
                                </div>

                                <div class="modal fade" id="theme-style-test-modal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                <h4 class="modal-title">Theme Style Modal Test</h4>
                                            </div>
                                            <div class="modal-body">
                                                Modal body preview.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="tab_custom_css">
                                <div class="form-group">
                                    <label class="bold"><?= _l('theme_style_customers_and_admin'); ?></label>
                                    <textarea id="theme_style_custom_clients_and_admin_area" rows="12" class="form-control"><?= clear_textarea_breaks(get_option('theme_style_custom_clients_and_admin_area')); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="bold"><?= _l('theme_style_admin'); ?></label>
                                    <textarea id="theme_style_custom_admin_area" rows="12" class="form-control"><?= clear_textarea_breaks(get_option('theme_style_custom_admin_area')); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="bold"><?= _l('theme_style_customers'); ?></label>
                                    <textarea id="theme_style_custom_clients_area" rows="12" class="form-control"><?= clear_textarea_breaks(get_option('theme_style_custom_clients_area')); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <hr />
                        <button type="button" class="btn btn-primary" id="save-theme-style">
                            <?= _l('theme_style_save'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
(function($){
    'use strict';

    var previewStyle = document.createElement('style');
    previewStyle.id = 'theme-style-live-preview';
    document.body.appendChild(previewStyle);

    function validColor(color){
        return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test($.trim(color || ''));
    }

    function boostOne(selector){
        selector = $.trim(selector);

        if (!selector) {
            return '';
        }

        if (selector.indexOf('body.') === 0 || selector.indexOf('body#') === 0 || selector === 'body') {
            return 'html ' + selector;
        }

        if (selector.indexOf('html ') === 0) {
            return selector;
        }

        return [
            'html body ' + selector,
            'html body.mk-admin-shell ' + selector,
            'html body.login_admin ' + selector,
            'html body.customers ' + selector
        ].join(', ');
    }

    function boostList(selectors){
        return String(selectors || '').split(',').map(boostOne).filter(Boolean).join(', ');
    }

    function darken(hex, amount){
        hex = String(hex || '').replace('#','');

        if (hex.length === 3) {
            hex = hex.split('').map(function(c){ return c + c; }).join('');
        }

        var num = parseInt(hex.substring(0,6), 16);
        var r = Math.max(0, ((num >> 16) & 255) - amount);
        var g = Math.max(0, ((num >> 8) & 255) - amount);
        var b = Math.max(0, (num & 255) - amount);

        return '#' + [r,g,b].map(function(v){ return v.toString(16).padStart(2,'0'); }).join('');
    }

    function contrast(hex){
        hex = String(hex || '').replace('#','');

        if (hex.length === 3) {
            hex = hex.split('').map(function(c){ return c + c; }).join('');
        }

        if (hex.length < 6) {
            return '#ffffff';
        }

        var r = parseInt(hex.substring(0,2),16);
        var g = parseInt(hex.substring(2,4),16);
        var b = parseInt(hex.substring(4,6),16);
        var luma = ((r * 299) + (g * 587) + (b * 114)) / 1000;

        return luma > 168 ? '#172033' : '#ffffff';
    }

    function isButtonTarget(target){
        return /(^|,\s*)\.btn-(default|primary|info|success|danger)(,|$)/.test(target);
    }

    function buttonCss(target, color){
        var normal = boostList(target);
        var hoverColor = darken(color, 35);
        var normalText = contrast(color);
        var hoverText = contrast(hoverColor);
        var hoverSelectors = [];

        String(target).split(',').forEach(function(raw){
            var selector = $.trim(raw);

            if (!selector) {
                return;
            }

            [':hover', ':focus', ':active', '.active'].forEach(function(state){
                hoverSelectors.push(boostOne(selector + state));
            });
        });

        return normal + '{background:' + color + ' !important;background-color:' + color + ' !important;border-color:' + color + ' !important;color:' + normalText + ' !important;box-shadow:none !important;}' +
            hoverSelectors.join(', ') + '{background:' + hoverColor + ' !important;background-color:' + hoverColor + ' !important;border-color:' + hoverColor + ' !important;color:' + hoverText + ' !important;}';
    }

    function collectColors(){
        var data = [];

        $('.colorpicker-component input[data-id]').each(function(){
            var color = $.trim($(this).val());

            if (validColor(color)) {
                data.push({
                    id: $(this).attr('data-id'),
                    color: color
                });
            }
        });

        return data;
    }

    function renderPreview(){
        var css = '';

        $('.colorpicker-component').each(function(){
            var $wrap = $(this);
            var color = $.trim($wrap.find('input[data-id]').val());

            if (!validColor(color)) {
                return;
            }

            var target = String($wrap.attr('data-target') || '');
            var property = String($wrap.attr('data-css') || '');

            if (isButtonTarget(target)) {
                css += buttonCss(target, color);
                return;
            }

            css += boostList(target) + '{' + property + ':' + color + ' !important;}';

            var additional = String($wrap.attr('data-additional') || '');

            if (additional) {
                additional.split('+').forEach(function(item){
                    var parts = item.split('|');

                    if (parts.length === 2) {
                        css += boostList(parts[0]) + '{' + parts[1] + ':' + color + ' !important;}';
                    }
                });
            }
        });

        previewStyle.textContent = css;
    }

    function updateFontPreview(){
        var family = $('#theme_style_font_family').val() || 'System UI';
        var size = parseInt($('#theme_style_font_size').val() || '14', 10);

        $('#theme_font_preview').css({
            fontFamily: '"' + family + '", Arial, sans-serif',
            fontSize: size + 'px'
        });
    }

    $('.colorpicker-component').colorpicker().on('changeColor', renderPreview);
    $('.colorpicker-component input[data-id]').on('input change', renderPreview);
    $('#theme_style_font_family,#theme_style_font_size').on('changed.bs.select change', updateFontPreview);

    updateFontPreview();
    renderPreview();

    $('#save-theme-style').on('click', function(){
        var $button = $(this);
        $button.prop('disabled', true);

        $.post(admin_url + 'theme_style/save', {
            data: JSON.stringify(collectColors()),
            admin_area: $('#theme_style_custom_admin_area').val(),
            clients_area: $('#theme_style_custom_clients_area').val(),
            clients_and_admin: $('#theme_style_custom_clients_and_admin_area').val(),
            font_family: $('#theme_style_font_family').val(),
            font_size: $('#theme_style_font_size').val()
        }).done(function(){
            alert_float('success', '<?= html_escape(_l('settings_updated')); ?>');
            setTimeout(function(){ window.location.reload(); }, 350);
        }).fail(function(){
            alert_float('danger', 'Unable to save Theme Style');
            $button.prop('disabled', false);
        });
    });

    $('#run-theme-style-diagnostics').on('click', function(){
        var checks = [
            ['Sidebar', '#menu.sidebar'],
            ['Header', '#header'],
            ['Content', '#wrapper .content'],
            ['Default button', '.btn-default'],
            ['Primary button', '.btn-primary'],
            ['Info button', '.btn-info'],
            ['Success button', '.btn-success'],
            ['Danger button', '.btn-danger'],
            ['Tabs', '.nav-tabs'],
            ['Panel', '.panel_s .panel-body'],
            ['Input', '.form-control'],
            ['Table heading', '.table > thead > tr > th'],
            ['Modal header test fixture', '#theme-style-test-modal .modal-header']
        ];

        var html = '<table class="table"><thead><tr><th>Component</th><th>DOM Test</th></tr></thead><tbody>';
        var pass = 0;

        checks.forEach(function(check){
            var exists = $(check[1]).length > 0;

            if (exists) {
                pass++;
            }

            html += '<tr><td>' + check[0] + '</td><td>' +
                (exists ? '<span class="text-success">PASS</span>' : '<span class="text-danger">NOT FOUND</span>') +
                '</td></tr>';
        });

        html += '</tbody></table>';
        html = '<div class="alert ' + (pass === checks.length ? 'alert-success' : 'alert-warning') + '">' +
            'DOM diagnostics: ' + pass + '/' + checks.length + ' components detected on this page.' +
            '</div>' + html +
            '<p class="text-muted">Customer Portal selectors must still be verified on the Client Portal because those elements do not exist in Admin.</p>';

        $('#theme-style-diagnostics-result').html(html);
    });

    $('#run-theme-style-server-audit').on('click', function(){
        $.getJSON(admin_url + 'theme_style/audit').done(function(resp){
            if (!resp || !resp.success) {
                alert_float('danger', 'Theme Style server audit failed');
                return;
            }

            var missing = 0;
            (resp.rows || []).forEach(function(row){
                if (!row.has_target) {
                    missing++;
                }
            });

            var type = missing === 0 ? 'success' : 'warning';
            var text = 'Server audit: ' + resp.controls + ' controls mapped, ' + missing + ' missing targets. Font: ' +
                (resp.font_family || 'System UI') + ' / ' + (resp.font_size || '14') + 'px';

            $('#theme-style-diagnostics-result').prepend(
                '<div class="alert alert-' + type + '">' + text + '</div>'
            );
        }).fail(function(){
            alert_float('danger', 'Unable to run Theme Style server audit');
        });
    });

})(jQuery);
</script>
</body>
</html>
