<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="mk33-status-grid">
    <?php foreach ($project_statuses as $status) {
        $name = strtolower(trim($status['name']));
        $icon = 'fa-regular fa-clock';
        $class = 'is-neutral';

        if (strpos($name, 'progress') !== false) {
            $icon = 'fa-solid fa-play';
            $class = 'is-progress';
        } elseif (strpos($name, 'hold') !== false) {
            $icon = 'fa-solid fa-pause';
            $class = 'is-hold';
        } elseif (strpos($name, 'cancel') !== false) {
            $icon = 'fa-solid fa-xmark';
            $class = 'is-cancelled';
        } elseif (strpos($name, 'finish') !== false || strpos($name, 'complete') !== false) {
            $icon = 'fa-solid fa-check';
            $class = 'is-finished';
        }

        $count = total_rows(db_prefix() . 'projects', [
            'status'   => $status['id'],
            'clientid' => get_client_user_id(),
        ]);
    ?>
        <a href="<?= site_url('clients/projects/' . $status['id']); ?>"
           class="mk33-status-card mk33-spotlight <?= e($class); ?> <?= isset($list_statuses) && in_array($status['id'], $list_statuses) ? 'is-selected' : ''; ?>">
            <span class="mk33-status-icon"><i class="<?= e($icon); ?>"></i></span>
            <span class="mk33-status-content">
                <strong class="mk33-count" data-count="<?= e($count); ?>">0</strong>
                <small><?= e($status['name']); ?></small>
            </span>
            <i class="fa-solid fa-chevron-right mk33-status-chevron"></i>
        </a>
    <?php } ?>
</div>
