<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk43InvoiceCount = is_array($invoices ?? null) ? count($invoices) : 0;
?>

<section class="mk43-finance-hero mk43-invoices-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">BILLING CENTER</div>
        <h1><?= _l('clients_my_invoices'); ?></h1>
        <p>Review invoices, payment status and account history from one financial workspace.</p>
        <?php if (has_contact_permission('invoices')) { ?>
            <a href="<?= site_url('clients/statement'); ?>" class="mk43-inline-action">
                <i class="fa-regular fa-file-lines"></i>
                <?= _l('view_account_statement'); ?>
            </a>
        <?php } ?>
    </div>
    <div class="mk43-finance-hero-orb">
        <span><i class="fa-solid fa-file-invoice-dollar"></i></span>
        <strong class="mk4-counter" data-value="<?= e($mk43InvoiceCount); ?>">0</strong>
        <small>Visible invoices</small>
    </div>
</section>

<section class="mk43-finance-summary-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-solid fa-chart-pie"></i></span>
        <div><strong>Invoice overview</strong><small>Filter your invoices by payment status</small></div>
    </div>
    <?php get_template_part('invoices_stats'); ?>
</section>

<section class="mk43-table-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-solid fa-receipt"></i></span>
        <div><strong><?= _l('clients_my_invoices'); ?></strong><small>Your invoice history</small></div>
    </div>

    <div class="mk43-table-shell">
        <table class="table dt-table table-invoices mk43-finance-table" data-order-col="1" data-order-type="desc">
            <thead>
                <tr>
                    <th><?= _l('clients_invoice_dt_number'); ?></th>
                    <th><?= _l('clients_invoice_dt_date'); ?></th>
                    <th><?= _l('clients_invoice_dt_duedate'); ?></th>
                    <th><?= _l('clients_invoice_dt_amount'); ?></th>
                    <th><?= _l('clients_invoice_dt_status'); ?></th>
                    <?php $custom_fields = get_custom_fields('invoice', ['show_on_client_portal' => 1]); ?>
                    <?php foreach ($custom_fields as $field) { ?>
                        <th><?= e($field['name']); ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice) { ?>
                    <tr class="mk43-finance-row">
                        <td data-order="<?= e($invoice['number']); ?>">
                            <a href="<?= site_url('invoice/' . $invoice['id'] . '/' . $invoice['hash']); ?>" class="mk43-document-link">
                                <span class="mk43-document-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                                <span><strong><?= e(format_invoice_number($invoice['id'])); ?></strong><small>Invoice</small></span>
                            </a>
                        </td>
                        <td data-order="<?= e($invoice['date']); ?>"><?= e(_d($invoice['date'])); ?></td>
                        <td data-order="<?= e($invoice['duedate']); ?>"><?= e(_d($invoice['duedate'])); ?></td>
                        <td data-order="<?= e($invoice['total']); ?>"><strong><?= e(app_format_money($invoice['total'], $invoice['currency_name'])); ?></strong></td>
                        <td><?= format_invoice_status($invoice['status'], 'inline-block', true); ?></td>
                        <?php foreach ($custom_fields as $field) { ?>
                            <td><?= get_custom_field_value($invoice['id'], $field['id'], 'invoice'); ?></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
