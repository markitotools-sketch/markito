<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Theme_style extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_admin()) {
            access_denied('Theme Style');
        }

        $this->load->helper('theme_style/theme_style');
    }

    public function index()
    {
        $data['title'] = _l('theme_style');
        $this->load->view('theme_style', $data);
    }

    public function reset()
    {
        update_option('theme_style', '[]');
        update_option('theme_style_font_family', 'System UI');
        update_option('theme_style_font_size', '14');

        set_alert('success', _l('settings_updated'));
        redirect(admin_url('theme_style'));
    }

    public function save()
    {
        hooks()->do_action('before_save_theme_style');

        $raw = (string) $this->input->post('data');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $allowedIds = array_flip(theme_style_all_area_ids());
        $clean = [];

        foreach ($decoded as $item) {
            if (!isset($item['id'], $item['color'])) {
                continue;
            }

            $id = (string) $item['id'];
            $color = trim((string) $item['color']);

            if (!isset($allowedIds[$id]) || !theme_style_is_valid_color($color)) {
                continue;
            }

            $clean[] = [
                'id' => $id,
                'color' => $color,
            ];
        }

        update_option('theme_style', json_encode($clean));

        $fonts = theme_style_get_fonts();
        $font = (string) $this->input->post('font_family');
        if (!isset($fonts[$font])) {
            $font = 'System UI';
        }

        $fontSize = (int) $this->input->post('font_size');
        if ($fontSize < 12 || $fontSize > 18) {
            $fontSize = 14;
        }

        update_option('theme_style_font_family', $font);
        update_option('theme_style_font_size', (string) $fontSize);

        foreach (['admin_area','clients_area','clients_and_admin'] as $area) {
            $value = trim((string) $this->input->post($area));
            update_option('theme_style_custom_' . $area, nl2br($value));
        }

        echo json_encode(['success' => true, 'colors_saved' => count($clean), 'font_family' => $font, 'font_size' => $fontSize]);
    }

    public function audit()
    {
        header('Content-Type: application/json; charset=utf-8');

        $manifest = theme_style_audit_manifest();
        $saved = get_applied_styling_area();
        $savedIndex = [];

        foreach ($saved as $item) {
            if (isset($item->id, $item->color)) {
                $savedIndex[(string) $item->id] = (string) $item->color;
            }
        }

        $rows = [];

        foreach ($manifest as $item) {
            $rows[] = [
                'group' => $item['group'],
                'id' => $item['id'],
                'property' => $item['property'],
                'has_target' => trim($item['target']) !== '',
                'saved_color' => $savedIndex[$item['id']] ?? null,
            ];
        }

        echo json_encode([
            'success' => true,
            'module_version' => '4.0.0',
            'controls' => count($rows),
            'rows' => $rows,
            'font_family' => get_option('theme_style_font_family'),
            'font_size' => get_option('theme_style_font_size'),
        ]);
    }


    /**
     * Theme Style Full QA v2.0
     * Admin-only and read-only. No options are modified.
     */
    public function qa()
    {
        $types = ['admin', 'tables', 'customers', 'general', 'tabs', 'modals', 'buttons', 'tags'];

        $data['title'] = 'Theme Style Full QA';
        $data['qa_types'] = $types;
        $data['qa_areas'] = [];
        $data['qa_applied'] = [];

        foreach ($types as $type) {
            $areas = get_styling_areas($type);
            $data['qa_areas'][$type] = is_array($areas) ? $areas : [];
        }

        $applied = get_applied_styling_area();
        if (is_array($applied)) {
            foreach ($applied as $item) {
                if (is_object($item) && isset($item->id)) {
                    $data['qa_applied'][(string) $item->id] = isset($item->color) ? (string) $item->color : '';
                }
            }
        }

        // Capture the exact CSS produced by the same render paths used by each hook.
        $scopeGroups = [
            'all'       => $types,
            'admin'     => ['general','tabs','buttons','admin','modals','tags','tables'],
            'customers' => ['general','tabs','buttons','customers','modals','tables'],
            'auth'      => ['general','buttons'],
            'external'  => ['general','buttons','tables'],
        ];

        $data['qa_scope_groups'] = $scopeGroups;
        $data['qa_scope_css'] = [];
        foreach ($scopeGroups as $scope => $groups) {
            ob_start();
            theme_style_render($groups, $scope);
            $data['qa_scope_css'][$scope] = ob_get_clean();
        }

        ob_start();
        theme_style_render_font();
        $data['qa_font_css'] = ob_get_clean();

        $data['qa_font_family'] = (string) get_option('theme_style_font_family');
        $data['qa_font_size'] = (string) get_option('theme_style_font_size');
        $data['qa_fonts'] = theme_style_get_fonts();

        $data['qa_custom_admin'] = (string) get_option('theme_style_custom_admin_area');
        $data['qa_custom_clients'] = (string) get_option('theme_style_custom_clients_area');
        $data['qa_custom_both'] = (string) get_option('theme_style_custom_clients_and_admin_area');

        // Storage diagnostics preserve malformed/unknown data that the normal helper may filter out.
        $rawTheme = (string) get_option('theme_style');
        $decodedTheme = json_decode($rawTheme, true);
        $data['qa_storage'] = [
            'raw_length' => strlen($rawTheme),
            'json_valid' => json_last_error() === JSON_ERROR_NONE,
            'json_error' => json_last_error_msg(),
            'item_count' => is_array($decodedTheme) ? count($decodedTheme) : 0,
            'items'      => is_array($decodedTheme) ? $decodedTheme : [],
        ];

        // Verify the module runtime functions loaded by theme_style.php are actually available.
        $data['qa_wiring'] = [
            'admin_head'      => function_exists('theme_style_admin_head'),
            'auth_head'       => function_exists('theme_style_auth_head'),
            'customers_head'  => function_exists('theme_style_clients_area_head'),
            'external_head'   => function_exists('theme_style_external_head'),
            'custom_css'      => function_exists('theme_style_custom_css'),
            'font_renderer'   => function_exists('theme_style_render_font'),
            'style_renderer'  => function_exists('theme_style_render'),
        ];

        $this->load->view('qa', $data);
    }

}
