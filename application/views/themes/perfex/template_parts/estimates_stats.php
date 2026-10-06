<?php defined('BASEPATH') or exit('No direct script access allowed');

$where_total = ['clientid' => get_client_user_id()];
if (get_option('exclude_estimate_from_client_area_with_draft_status') == 1) {
    $where_total['status !='] = 1;
}

$total_estimates = total_rows(db_prefix() . 'estimates', $where_total);
$stats = [];

if (get_option('exclude_estimate_from_client_area_with_draft_status') == 0) {
    $stats[] = ['label' => _l('estimate_status_draft'), 'value' => total_rows(db_prefix() . 'estimates', ['status' => 1, 'clientid' => get_client_user_id()]), 'url' => site_url('clients/estimates/1'), 'class' => 'is-neutral', 'icon' => 'fa-regular fa-file'];
}

$stats[] = ['label' => _l('estimate_status_sent'), 'value' => total_rows(db_prefix() . 'estimates', ['status' => 2, 'clientid' => get_client_user_id()]), 'url' => site_url('clients/estimates/2'), 'class' => 'is-blue', 'icon' => 'fa-regular fa-paper-plane'];
$stats[] = ['label' => _l('estimate_status_expired'), 'value' => total_rows(db_prefix() . 'estimates', ['status' => 5, 'clientid' => get_client_user_id()]), 'url' => site_url('clients/estimates/5'), 'class' => 'is-orange', 'icon' => 'fa-regular fa-clock'];
$stats[] = ['label' => _l('estimate_status_declined'), 'value' => total_rows(db_prefix() . 'estimates', ['status' => 3, 'clientid' => get_client_user_id()]), 'url' => site_url('clients/estimates/3'), 'class' => 'is-red', 'icon' => 'fa-solid fa-xmark'];
$stats[] = ['label' => _l('estimate_status_accepted'), 'value' => total_rows(db_prefix() . 'estimates', ['status' => 4, 'clientid' => get_client_user_id()]), 'url' => site_url('clients/estimates/4'), 'class' => 'is-green', 'icon' => 'fa-solid fa-check'];
?>

<div class="mk43-finance-stats <?= count($stats) === 5 ? 'has-five' : ''; ?>">
    <?php foreach ($stats as $stat) { ?>
        <a href="<?= e($stat['url']); ?>" class="mk43-finance-stat <?= e($stat['class']); ?> mk4-cursor-glow">
            <span class="mk43-finance-stat-icon"><i class="<?= e($stat['icon']); ?>"></i></span>
            <span class="mk43-finance-stat-copy">
                <strong class="mk4-counter" data-value="<?= e($stat['value']); ?>">0</strong>
                <small><?= e($stat['label']); ?></small>
            </span>
            <span class="mk43-finance-stat-total">/ <?= e($total_estimates); ?></span>
            <i class="fa-solid fa-chevron-right mk43-finance-stat-arrow"></i>
        </a>
    <?php } ?>
</div>
