<?php defined('BASEPATH') or exit('No direct script access allowed');

$where_total = 'clientid=' . get_client_user_id() . ' AND status !=5';
if (get_option('exclude_invoice_from_client_area_with_draft_status') == 1) {
    $where_total .= ' AND status != 6';
}

$total_invoices            = total_rows(db_prefix() . 'invoices', $where_total);
$total_open                = total_rows(db_prefix() . 'invoices', ['status' => 1, 'clientid' => get_client_user_id()]);
$total_paid                = total_rows(db_prefix() . 'invoices', ['status' => 2, 'clientid' => get_client_user_id()]);
$total_not_paid_completely = total_rows(db_prefix() . 'invoices', ['status' => 3, 'clientid' => get_client_user_id()]);
$total_overdue             = total_rows(db_prefix() . 'invoices', ['status' => 4, 'clientid' => get_client_user_id()]);

$stats = [
    ['label' => _l('invoice_status_unpaid'), 'value' => $total_open, 'url' => site_url('clients/invoices/1'), 'class' => 'is-red', 'icon' => 'fa-regular fa-clock'],
    ['label' => _l('invoice_status_paid'), 'value' => $total_paid, 'url' => site_url('clients/invoices/2'), 'class' => 'is-green', 'icon' => 'fa-solid fa-check'],
    ['label' => _l('invoice_status_overdue'), 'value' => $total_overdue, 'url' => site_url('clients/invoices/4'), 'class' => 'is-orange', 'icon' => 'fa-solid fa-triangle-exclamation'],
    ['label' => _l('invoice_status_not_paid_completely'), 'value' => $total_not_paid_completely, 'url' => site_url('clients/invoices/3'), 'class' => 'is-purple', 'icon' => 'fa-solid fa-circle-half-stroke'],
];
?>

<div class="mk43-finance-stats">
    <?php foreach ($stats as $stat) { ?>
        <a href="<?= e($stat['url']); ?>" class="mk43-finance-stat <?= e($stat['class']); ?> mk4-cursor-glow">
            <span class="mk43-finance-stat-icon"><i class="<?= e($stat['icon']); ?>"></i></span>
            <span class="mk43-finance-stat-copy">
                <strong class="mk4-counter" data-value="<?= e($stat['value']); ?>">0</strong>
                <small><?= e($stat['label']); ?></small>
            </span>
            <span class="mk43-finance-stat-total">/ <?= e($total_invoices); ?></span>
            <i class="fa-solid fa-chevron-right mk43-finance-stat-arrow"></i>
        </a>
    <?php } ?>
</div>
