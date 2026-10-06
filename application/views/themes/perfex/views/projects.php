<?php defined('BASEPATH') or exit('No direct script access allowed');

$totalClientProjects = total_rows(db_prefix() . 'projects', ['clientid' => get_client_user_id()]);
$activeClientProjects = 0;
$finishedClientProjects = 0;

foreach ($project_statuses as $mkStatus) {
    $mkCount = total_rows(db_prefix() . 'projects', [
        'status'   => $mkStatus['id'],
        'clientid' => get_client_user_id(),
    ]);

    $mkStatusName = strtolower(trim($mkStatus['name']));
    if (strpos($mkStatusName, 'progress') !== false) {
        $activeClientProjects += $mkCount;
    }
    if (strpos($mkStatusName, 'finish') !== false || strpos($mkStatusName, 'complete') !== false) {
        $finishedClientProjects += $mkCount;
    }
}
?>

<section class="mk33-projects-hero mk33-spotlight">
    <div class="mk33-projects-hero-copy">
        <div class="mk33-eyebrow">PROJECT WORKSPACE</div>
        <h1><?= _l('clients_my_projects'); ?></h1>
        <p>Track your work, deadlines, files, discussions and progress from one clean workspace.</p>
        <div class="mk33-projects-hero-actions">
            <a class="mk33-hero-link" href="#mk33-project-list">
                <i class="fa-regular fa-folder-open"></i>
                Browse projects
            </a>
            <span class="mk33-hero-meta">
                <i class="fa-regular fa-clock"></i>
                Live project status
            </span>
        </div>
    </div>

    <div class="mk33-projects-kpis">
        <div class="mk33-kpi">
            <span class="mk33-kpi-icon"><i class="fa-solid fa-layer-group"></i></span>
            <span>
                <strong class="mk33-count" data-count="<?= e($totalClientProjects); ?>">0</strong>
                <small>Total Projects</small>
            </span>
        </div>
        <div class="mk33-kpi is-blue">
            <span class="mk33-kpi-icon"><i class="fa-solid fa-play"></i></span>
            <span>
                <strong class="mk33-count" data-count="<?= e($activeClientProjects); ?>">0</strong>
                <small>In Progress</small>
            </span>
        </div>
        <div class="mk33-kpi is-green">
            <span class="mk33-kpi-icon"><i class="fa-solid fa-check"></i></span>
            <span>
                <strong class="mk33-count" data-count="<?= e($finishedClientProjects); ?>">0</strong>
                <small>Finished</small>
            </span>
        </div>
    </div>
</section>


<section id="mk33-project-list" class="panel_s mk33-project-list-panel">
    <div class="panel-body">
        <div class="mk33-section-head mk33-table-title">
            <div>
                <span class="mk33-section-icon"><i class="fa-regular fa-folder-open"></i></span>
                <span>
                    <strong><?= _l('clients_my_projects'); ?></strong>
                    <small>Open a project to view tasks, milestones, files and activity</small>
                </span>
            </div>
        </div>

        <div class="mk33-table-shell">
            <table class="table dt-table table-projects" data-order-col="2" data-order-type="desc">
                <thead>
                    <tr>
                        <th class="th-project-name"><?= _l('project_name'); ?></th>
                        <th class="th-project-start-date"><?= _l('project_start_date'); ?></th>
                        <th class="th-project-deadline"><?= _l('project_deadline'); ?></th>
                        <th class="th-project-billing-type"><?= _l('project_billing_type'); ?></th>
                        <?php
                        $custom_fields = get_custom_fields('projects', ['show_on_client_portal' => 1]);
                        foreach ($custom_fields as $field) { ?>
                            <th><?= e($field['name']); ?></th>
                        <?php } ?>
                        <th><?= _l('project_status'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project) { ?>
                        <tr class="mk33-project-row">
                            <td>
                                <a class="mk33-project-name-link"
                                   href="<?= site_url('clients/project/' . $project['id']); ?>">
                                    <span class="mk33-project-row-icon"><i class="fa-regular fa-folder-open"></i></span>
                                    <span>
                                        <strong><?= e($project['name']); ?></strong>
                                        <small>#<?= e($project['id']); ?></small>
                                    </span>
                                    <i class="fa-solid fa-arrow-right mk33-row-arrow"></i>
                                </a>
                            </td>
                            <td class="mk363-project-start" data-order="<?= e($project['start_date']); ?>">
                                <span class="mk33-date-cell">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?= e(_d($project['start_date'])); ?>
                                </span>
                            </td>
                            <td class="mk363-project-deadline" data-order="<?= e($project['deadline']); ?>">
                                <span class="mk33-date-cell">
                                    <i class="fa-regular fa-flag"></i>
                                    <?= e(_d($project['deadline'])); ?>
                                </span>
                            </td>
                            <td class="mk363-project-billing">
                                <?php
                                if ($project['billing_type'] == 1) {
                                    $type_name = 'project_billing_type_fixed_cost';
                                } elseif ($project['billing_type'] == 2) {
                                    $type_name = 'project_billing_type_project_hours';
                                } else {
                                    $type_name = 'project_billing_type_project_task_hours';
                                }
                                echo _l($type_name);
                                ?>
                            </td>
                            <?php foreach ($custom_fields as $field) { ?>
                                <td class="mk363-project-custom"><?= get_custom_field_value($project['id'], $field['id'], 'projects'); ?></td>
                            <?php } ?>
                            <td class="mk363-project-status">
                                <?php
                                $status = get_project_status_by_id($project['status']);
                                echo '<span class="label project-status-' . $status['id'] . '" style="color:' . $status['color'] . ';border:1px solid ' . adjust_hex_brightness($status['color'], 0.4) . ';background:' . adjust_hex_brightness($status['color'], 0.04) . ';">' . e($status['name']) . '</span>';
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
