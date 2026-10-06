<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk42ProjectTicketCount = is_array($tickets ?? null) ? count($tickets) : 0;
?>

<section class="mk42-feature-hero mk42-support-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">PROJECT SUPPORT</div>
        <h2>Support Tickets</h2>
        <p>Keep support questions tied directly to this project and its delivery context.</p>
    </div>

    <div class="mk42-feature-stats">
        <div class="mk42-feature-stat">
            <span class="mk42-feature-stat-icon"><i class="fa-regular fa-life-ring"></i></span>
            <span><strong class="mk4-counter" data-value="<?= e($mk42ProjectTicketCount); ?>">0</strong><small>Tickets</small></span>
        </div>
    </div>
</section>

<div class="mk42-support-toolbar">
    <div>
        <strong>Need help with this project?</strong>
        <small>Open a ticket and it will stay connected to this project.</small>
    </div>
    <a href="<?= site_url('clients/open_ticket?project_id=' . $project->id); ?>" class="btn btn-primary">
        <i class="fa-regular fa-plus"></i>
        <?= _l('clients_ticket_open_subject'); ?>
    </a>
</div>

<div class="mk42-table-card">
    <?php get_template_part('tickets_table'); ?>
</div>
