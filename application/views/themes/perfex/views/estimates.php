<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk43EstimateCount = is_array($estimates ?? null) ? count($estimates) : 0;
?>

<section class="mk43-finance-hero mk43-estimates-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">ESTIMATE CENTER</div>
        <h1><?= _l('clients_my_estimates'); ?></h1>
        <p>Review estimates, approval status and commercial details in one clean workspace.</p>
    </div>
    <div class="mk43-finance-hero-orb is-cyan">
        <span><i class="fa-regular fa-file-lines"></i></span>
        <strong class="mk4-counter" data-value="<?= e($mk43EstimateCount); ?>">0</strong>
        <small>Visible estimates</small>
    </div>
</section>

<section class="mk43-finance-summary-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-solid fa-chart-simple"></i></span>
        <div><strong>Estimate overview</strong><small>Track your estimate status</small></div>
    </div>
    <?php get_template_part('estimates_stats'); ?>
</section>

<section class="mk43-table-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-regular fa-file-lines"></i></span>
        <div><strong><?= _l('clients_my_estimates'); ?></strong><small>Your estimate history</small></div>
    </div>
    <div class="mk43-table-shell">
        <?php get_template_part('estimates_table'); ?>
    </div>
</section>
