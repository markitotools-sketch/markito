<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk43ContractCount = is_array($contracts ?? null) ? count($contracts) : 0;
$mk43SignedCount = 0;

if (is_array($contracts ?? null)) {
    foreach ($contracts as $mk43Contract) {
        if (!empty($mk43Contract['signature']) || $mk43Contract['marked_as_signed'] == '1') {
            $mk43SignedCount++;
        }
    }
}
?>

<section class="mk43-finance-hero mk43-contracts-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">CONTRACT VAULT</div>
        <h1><?= _l('clients_contracts'); ?></h1>
        <p>Review contract periods, signatures and commercial agreements securely.</p>
    </div>
    <div class="mk43-dual-orbs">
        <div class="mk43-mini-orb">
            <span><i class="fa-solid fa-file-contract"></i></span>
            <strong class="mk4-counter" data-value="<?= e($mk43ContractCount); ?>">0</strong>
            <small>Total</small>
        </div>
        <div class="mk43-mini-orb is-green">
            <span><i class="fa-solid fa-signature"></i></span>
            <strong class="mk4-counter" data-value="<?= e($mk43SignedCount); ?>">0</strong>
            <small>Signed</small>
        </div>
    </div>
</section>

<section class="mk43-contract-chart-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-solid fa-chart-pie"></i></span>
        <div><strong><?= _l('contract_summary_by_type'); ?></strong><small>Contract distribution by type</small></div>
    </div>
    <div class="mk43-contract-chart">
        <canvas class="chart" height="260" id="contracts-by-type-chart"></canvas>
    </div>
</section>

<section class="mk43-table-card">
    <div class="mk43-section-head">
        <span class="mk43-section-icon"><i class="fa-solid fa-file-contract"></i></span>
        <div><strong><?= _l('clients_contracts'); ?></strong><small>Your agreement history</small></div>
    </div>
    <div class="mk43-table-shell">
        <?php get_template_part('contracts_table'); ?>
    </div>
</section>

<script>
var contracts_by_type = '<?= $contracts_by_type_chart; ?>';
</script>
