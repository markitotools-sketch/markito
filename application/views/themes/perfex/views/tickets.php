<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk42TicketTotal = is_array($tickets ?? null) ? count($tickets) : 0;
?>

<section class="mk42-page-hero mk42-support-page-hero mk4-cursor-glow">
    <div class="mk42-page-hero-copy">
        <div class="mk4-kicker">SUPPORT CENTER</div>
        <h1><?= _l('clients_tickets_heading'); ?></h1>
        <p>Track support requests, responses and project questions from one clean workspace.</p>
        <div class="mk42-page-hero-actions">
            <a href="<?= site_url('clients/open_ticket'); ?>" class="btn btn-primary">
                <i class="fa-regular fa-plus"></i>
                <?= _l('clients_ticket_open_subject'); ?>
            </a>
        </div>
    </div>
    <div class="mk42-page-hero-stat">
        <span><i class="fa-regular fa-life-ring"></i></span>
        <div><strong class="mk4-counter" data-value="<?= e($mk42TicketTotal); ?>">0</strong><small>Visible Tickets</small></div>
    </div>
</section>

<section class="mk42-ticket-status-shell">
    <div class="mk42-section-heading">
        <span class="mk42-section-heading-icon"><i class="fa-solid fa-chart-pie"></i></span>
        <div><strong><?= _l('tickets_summary'); ?></strong><small>Filter your support requests by status</small></div>
    </div>

    <div class="mk42-ticket-status-grid">
        <?php foreach (get_clients_area_tickets_summary($ticket_statuses) as $status) { ?>
            <a href="<?= e($status['url']); ?>"
               class="mk42-ticket-status-card mk4-cursor-glow <?= in_array($status['ticketstatusid'], $list_statuses) ? 'is-selected' : ''; ?>">
                <span class="mk42-ticket-status-dot" style="--mk42-status-color:<?= e($status['statuscolor']); ?>"></span>
                <span>
                    <strong><?= e($status['total_tickets']); ?></strong>
                    <small style="color:<?= e($status['statuscolor']); ?>"><?= e($status['translated_name']); ?></small>
                </span>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        <?php } ?>
    </div>
</section>

<section class="mk42-table-card">
    <div class="mk42-section-heading">
        <span class="mk42-section-heading-icon"><i class="fa-regular fa-message"></i></span>
        <div><strong><?= _l('clients_tickets_heading'); ?></strong><small>Your support conversation history</small></div>
    </div>
    <?php get_template_part('tickets_table'); ?>
</section>
