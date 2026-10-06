<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="<?= e($locale); ?>">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?= $title ?? ''; ?></title>
	<?= compile_theme_css(); ?>
    <!-- Markito Client Portal Full UI v3 -->
    <link rel="stylesheet"
        href="<?= base_url('assets/css/markito-client-portal-v3.css?v=3.3.0'); ?>">
    <script defer src="<?= base_url('assets/js/markito-client-portal-motion-v3.2.js?v=3.3.0'); ?>"></script>
    <!-- Markito Client Portal v4 Premium Experience -->
    <link rel="stylesheet" href="<?= base_url('assets/css/markito-client-portal-v4.css?v=4.3.6.9'); ?>">
    <script defer src="<?= base_url('assets/js/markito-client-portal-v4.js?v=4.3.6.6'); ?>"></script>
	<script
		src="<?= base_url('assets/plugins/jquery/jquery.min.js'); ?>">
	</script>
	<?php app_customers_head(); ?>
</head>

<body
	class="customers <?= strtolower($this->agent->browser()); ?><?= is_mobile() ? ' mobile' : ''; ?><?= isset($bodyclass) ? ' ' . $bodyclass : ''; ?>"
	<?= $isRTL == 'true' ? 'dir="rtl"' : ''; ?>>

	<?php hooks()->do_action('customers_after_body_start'); ?>