<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk43ProposalCount = is_array($proposals ?? null) ? count($proposals) : 0;
?>

<section class="mk43-finance-hero mk43-proposals-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">PROPOSALS</div>
        <h1><?= _l('proposals'); ?></h1>
        <p>Review commercial proposals, decisions and related documents in one place.</p>
    </div>
    <div class="mk43-finance-hero-orb is-purple">
        <span><i class="fa-regular fa-paper-plane"></i></span>
        <strong class="mk4-counter" data-value="<?= e($mk43ProposalCount); ?>">0</strong>
        <small>Visible proposals</small>
    </div>
</section>

<section class="mk43-table-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-regular fa-paper-plane"></i></span>
        <div><strong><?= _l('proposals'); ?></strong><small>Your proposal history</small></div>
    </div>
    <div class="mk43-table-shell">
        <?php get_template_part('proposals_table'); ?>
    </div>
</section>
