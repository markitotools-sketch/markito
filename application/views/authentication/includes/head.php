<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
    <title>
        <?php echo e(get_option('companyname')); ?> - <?php echo _l('admin_auth_login_heading'); ?>
    </title>
    <?php echo app_compile_css('admin-auth'); ?>
    <style>
    body,
    html {
        font-size: 16px;
    }

    body>* {
        font-size: 14px;
    }

    body {
        font-family: "Inter", sans-serif;
        color: #475569;
        margin: 0;
        padding: 0;
    }

    .company-logo {
        padding: 25px 10px;
        display: block;
    }

    .company-logo img {
        margin: 0 auto;
        display: block;
    }

    @media screen and (max-height: 575px),
    screen and (min-width: 992px) and (max-width:1199px) {

        #rc-imageselect,
        .g-recaptcha {
            transform: scale(0.83);
            -webkit-transform: scale(0.83);
            transform-origin: 0 0;
            -webkit-transform-origin: 0 0;
        }
    }
    </style>
    <?php if (show_recaptcha()) { ?>
    <script src='https://www.google.com/recaptcha/api.js'></script>
    <?php } ?>
    <?php if (file_exists(FCPATH . 'assets/css/custom.css')) { ?>
    <link href="<?php echo base_url('assets/css/custom.css'); ?>" rel="stylesheet" id="custom-css">
    <?php } ?>
    <?php hooks()->do_action('app_admin_authentication_head'); ?>

    <?php
    // Markito Theme Controller v1: auth tokens
    $mk_ui_primary = get_option('mk_ui_primary_color') ?: '#f59b23';
    $mk_ui_dark    = get_option('mk_ui_dark_color') ?: '#15191f';
    $mk_ui_surface = get_option('mk_ui_surface_color') ?: '#f4f6f8';
    $mk_ui_font    = get_option('mk_ui_font_family') ?: 'Inter';

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $mk_ui_primary)) $mk_ui_primary = '#f59b23';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $mk_ui_dark)) $mk_ui_dark = '#15191f';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $mk_ui_surface)) $mk_ui_surface = '#f4f6f8';

    $mk_fonts = ['Inter','Poppins','Manrope','Cairo','Tajawal','Arial','system-ui'];
    if (!in_array($mk_ui_font, $mk_fonts, true)) $mk_ui_font = 'Inter';

    $mk_hex = ltrim($mk_ui_primary, '#');
    $mk_rgb = implode(',', [
        hexdec(substr($mk_hex, 0, 2)),
        hexdec(substr($mk_hex, 2, 2)),
        hexdec(substr($mk_hex, 4, 2)),
    ]);
    ?>
    <style id="mk-theme-vars-auth">
    :root{
      --mk-primary: <?= e($mk_ui_primary); ?>;
      --mk-primary-rgb: <?= e($mk_rgb); ?>;
      --mk-dark: <?= e($mk_ui_dark); ?>;
      --mk-surface: <?= e($mk_ui_surface); ?>;
      --mk-font: "<?= e($mk_ui_font); ?>", sans-serif;
    }
    </style>
    <link href="<?= base_url('assets/css/markito-theme-controller-v1.css?v=1.0.0'); ?>" rel="stylesheet" id="mk-theme-controller-auth">

<?php
// Markito Full UI Controller v2
$mk_ui_defaults_v2 = [
    'mk_ui_font_family' => 'Inter',
    'mk_ui_font_size' => '14',
    'mk_ui_primary_color' => '#f59b23',
    'mk_ui_dark_color' => '#15191f',
    'mk_ui_sidebar_text_color' => '#c8d0dc',
    'mk_ui_page_color' => '#f4f6f8',
    'mk_ui_card_color' => '#ffffff',
    'mk_ui_header_color' => '#ffffff',
    'mk_ui_text_color' => '#172033',
    'mk_ui_muted_color' => '#718096',
    'mk_ui_border_color' => '#e4e8ee',
    'mk_ui_table_head_color' => '#171c23',
    'mk_ui_table_head_text_color' => '#ffffff',
];

$mk_ui_font_v2 = get_option('mk_ui_font_family') ?: $mk_ui_defaults_v2['mk_ui_font_family'];
$mk_ui_font_size_v2 = (int)(get_option('mk_ui_font_size') ?: $mk_ui_defaults_v2['mk_ui_font_size']);
if ($mk_ui_font_size_v2 < 12 || $mk_ui_font_size_v2 > 18) $mk_ui_font_size_v2 = 14;

$mk_allowed_fonts_v2 = ['Inter','Poppins','Manrope','Cairo','Tajawal','Arial','system-ui'];
if (!in_array($mk_ui_font_v2,$mk_allowed_fonts_v2,true)) $mk_ui_font_v2 = 'Inter';

$mk_colors_v2 = [];
foreach ([
    'primary'=>'mk_ui_primary_color',
    'dark'=>'mk_ui_dark_color',
    'sidebar_text'=>'mk_ui_sidebar_text_color',
    'page'=>'mk_ui_page_color',
    'card'=>'mk_ui_card_color',
    'header'=>'mk_ui_header_color',
    'text'=>'mk_ui_text_color',
    'muted'=>'mk_ui_muted_color',
    'border'=>'mk_ui_border_color',
    'table_head'=>'mk_ui_table_head_color',
    'table_head_text'=>'mk_ui_table_head_text_color',
] as $mk_key_v2=>$mk_option_v2) {
    $mk_val_v2 = get_option($mk_option_v2) ?: $mk_ui_defaults_v2[$mk_option_v2];
    if (!preg_match('/^#[0-9a-fA-F]{6}$/',$mk_val_v2)) $mk_val_v2 = $mk_ui_defaults_v2[$mk_option_v2];
    $mk_colors_v2[$mk_key_v2] = $mk_val_v2;
}
$mk_hex_v2 = ltrim($mk_colors_v2['primary'],'#');
$mk_rgb_v2 = implode(',',[
    hexdec(substr($mk_hex_v2,0,2)),
    hexdec(substr($mk_hex_v2,2,2)),
    hexdec(substr($mk_hex_v2,4,2)),
]);
?>
<style id="mk-theme-vars-v2-auth">
:root{
  --mk-ui-primary: <?= e($mk_colors_v2['primary']); ?>;
  --mk-ui-primary-rgb: <?= e($mk_rgb_v2); ?>;
  --mk-ui-dark: <?= e($mk_colors_v2['dark']); ?>;
  --mk-ui-sidebar-text: <?= e($mk_colors_v2['sidebar_text']); ?>;
  --mk-ui-page: <?= e($mk_colors_v2['page']); ?>;
  --mk-ui-card: <?= e($mk_colors_v2['card']); ?>;
  --mk-ui-header: <?= e($mk_colors_v2['header']); ?>;
  --mk-ui-text: <?= e($mk_colors_v2['text']); ?>;
  --mk-ui-muted: <?= e($mk_colors_v2['muted']); ?>;
  --mk-ui-border: <?= e($mk_colors_v2['border']); ?>;
  --mk-ui-table-head: <?= e($mk_colors_v2['table_head']); ?>;
  --mk-ui-table-head-text: <?= e($mk_colors_v2['table_head_text']); ?>;
  --mk-ui-font: "<?= e($mk_ui_font_v2); ?>",sans-serif;
  --mk-ui-font-size: <?= (int)$mk_ui_font_size_v2; ?>px;
}
</style>
<link href="<?= base_url('assets/css/markito-theme-controller-v2.css?v=2.0.0'); ?>" rel="stylesheet" id="mk-theme-controller-v2">

<link href="<?= base_url('assets/css/premium-admin-login-v1.css?v=1.0.0'); ?>" rel="stylesheet" id="premium-admin-login-v1">
</head>