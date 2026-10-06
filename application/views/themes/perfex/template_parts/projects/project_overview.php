<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="mk33-overview-grid">
    <section class="mk33-overview-card">
        <div class="mk33-overview-card-head">
            <span class="mk33-overview-icon"><i class="fa-regular fa-folder-open"></i></span>
            <div>
                <strong><?= _l('project_overview'); ?></strong>
                <small>Core project information</small>
            </div>
        </div>

        <div class="mk33-detail-list">
            <div><span><?= _l('project'); ?></span><strong><?= _l('the_number_sign'); ?><?= e($project->id); ?></strong></div>

            <?php if ($project->settings->view_finance_overview == 1) { ?>
                <div>
                    <span><?= _l('project_billing_type'); ?></span>
                    <strong>
                        <?php
                        if ($project->billing_type == 1) {
                            $type_name = 'project_billing_type_fixed_cost';
                        } elseif ($project->billing_type == 2) {
                            $type_name = 'project_billing_type_project_hours';
                        } else {
                            $type_name = 'project_billing_type_project_task_hours';
                        }
                        echo e(_l($type_name));
                        ?>
                    </strong>
                </div>
            <?php } ?>

            <?php if (($project->billing_type == 1 || $project->billing_type == 2) && $project->settings->view_finance_overview == 1) { ?>
                <div>
                    <span><?= $project->billing_type == 1 ? _l('project_total_cost') : _l('project_rate_per_hour'); ?></span>
                    <strong>
                        <?= e(app_format_money(
                            $project->billing_type == 1 ? $project->project_cost : $project->project_rate_per_hour,
                            $currency
                        )); ?>
                    </strong>
                </div>
            <?php } ?>

            <div><span><?= _l('project_status'); ?></span><strong><?= e($project_status['name']); ?></strong></div>
            <div><span><?= _l('project_start_date'); ?></span><strong><?= e(_d($project->start_date)); ?></strong></div>

            <?php if ($project->deadline) { ?>
                <div><span><?= _l('project_deadline'); ?></span><strong><?= e(_d($project->deadline)); ?></strong></div>
            <?php } ?>

            <?php if ($project->date_finished) { ?>
                <div><span><?= _l('project_completed_date'); ?></span><strong class="text-success"><?= e(_dt($project->date_finished)); ?></strong></div>
            <?php } ?>

            <?php if ($project->billing_type == 1 && $project->settings->view_task_total_logged_time == 1) { ?>
                <div>
                    <span><?= _l('project_overview_total_logged_hours'); ?></span>
                    <strong><?= e(seconds_to_time_format($this->projects_model->total_logged_time($project->id))); ?></strong>
                </div>
            <?php } ?>

            <?php $custom_fields = get_custom_fields('projects', ['show_on_client_portal' => 1]); ?>
            <?php foreach ($custom_fields as $field) {
                $value = get_custom_field_value($project->id, $field['id'], 'projects');
                if ($value == '') {
                    continue;
                } ?>
                <div><span><?= e(ucfirst($field['name'])); ?></span><strong><?= $value; ?></strong></div>
            <?php } ?>
        </div>
    </section>

    <section class="mk33-overview-card">
        <div class="mk33-overview-card-head">
            <span class="mk33-overview-icon is-blue"><i class="fa-solid fa-chart-line"></i></span>
            <div>
                <strong>Progress & Delivery</strong>
                <small>Current project health</small>
            </div>
        </div>

        <div class="mk33-progress-stack">
            <div class="mk33-progress-stat">
                <div class="mk33-progress-stat-top">
                    <span><?= _l('project_progress_text'); ?></span>
                    <strong><?= e($progress); ?>%</strong>
                </div>
                <div class="progress">
                    <div class="progress-bar progress-bar-success no-percent-text not-dynamic"
                         role="progressbar"
                         aria-valuenow="<?= e($progress); ?>"
                         aria-valuemin="0" aria-valuemax="100"
                         style="width:0%"
                         data-percent="<?= e($progress); ?>"></div>
                </div>
            </div>

            <?php if ($project->settings->view_tasks == 1) { ?>
                <div class="mk33-progress-stat">
                    <div class="mk33-progress-stat-top">
                        <span><?= _l('project_open_tasks'); ?></span>
                        <strong dir="ltr"><?= e($tasks_not_completed); ?> / <?= e($total_tasks); ?></strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-success no-percent-text not-dynamic"
                             role="progressbar"
                             aria-valuenow="<?= e($tasks_not_completed_progress); ?>"
                             aria-valuemin="0" aria-valuemax="100"
                             style="width:0%"
                             data-percent="<?= e($tasks_not_completed_progress); ?>"></div>
                    </div>
                    <small><?= e($tasks_not_completed_progress); ?>%</small>
                </div>
            <?php } ?>

            <?php if ($project->deadline) { ?>
                <div class="mk33-progress-stat">
                    <div class="mk33-progress-stat-top">
                        <span><?= _l('project_days_left'); ?></span>
                        <strong dir="ltr"><?= e($project_days_left); ?> / <?= e($project_total_days); ?></strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar<?= $project_time_left_percent == 0 ? ' progress-bar-warning ' : ' progress-bar-success '; ?>no-percent-text not-dynamic"
                             role="progressbar"
                             aria-valuenow="<?= e($project_time_left_percent); ?>"
                             aria-valuemin="0" aria-valuemax="100"
                             style="width:0%"
                             data-percent="<?= e($project_time_left_percent); ?>"></div>
                    </div>
                    <small><?= e($project_time_left_percent); ?>%</small>
                </div>
            <?php } ?>
        </div>
    </section>
</div>

<?php if ($project->settings->view_finance_overview == 1) { ?>
    <section class="mk33-finance-card">
        <div class="mk33-overview-card-head">
            <span class="mk33-overview-icon is-green"><i class="fa-solid fa-coins"></i></span>
            <div>
                <strong>Finance Overview</strong>
                <small>Billing and project financial activity</small>
            </div>
        </div>

        <div class="mk33-finance-grid">
            <?php if ($project->billing_type == 3 || $project->billing_type == 2) { ?>
                <?php $data = $this->projects_model->total_logged_time_by_billing_type($project->id); ?>
                <div class="mk33-finance-item"><small><?= _l('project_overview_logged_hours'); ?></small><strong><?= e($data['logged_time']); ?></strong><span><?= e(app_format_money($data['total_money'], $currency)); ?></span></div>

                <?php $data = $this->projects_model->data_billable_time($project->id); ?>
                <div class="mk33-finance-item is-blue"><small><?= _l('project_overview_billable_hours'); ?></small><strong><?= e($data['logged_time']); ?></strong><span><?= e(app_format_money($data['total_money'], $currency)); ?></span></div>

                <?php $data = $this->projects_model->data_billed_time($project->id); ?>
                <div class="mk33-finance-item is-green"><small><?= _l('project_overview_billed_hours'); ?></small><strong><?= e($data['logged_time']); ?></strong><span><?= e(app_format_money($data['total_money'], $currency)); ?></span></div>

                <?php $data = $this->projects_model->data_unbilled_time($project->id); ?>
                <div class="mk33-finance-item is-red"><small><?= _l('project_overview_unbilled_hours'); ?></small><strong><?= e($data['logged_time']); ?></strong><span><?= e(app_format_money($data['total_money'], $currency)); ?></span></div>
            <?php } ?>

            <?php if ($project->settings->available_features['project_expenses'] == 1) { ?>
                <div class="mk33-finance-item"><small><?= _l('project_overview_expenses'); ?></small><strong><?= e(app_format_money(sum_from_table(db_prefix() . 'expenses', ['where' => ['project_id' => $project->id], 'field' => 'amount']), $currency)); ?></strong></div>
                <div class="mk33-finance-item is-blue"><small><?= _l('project_overview_expenses_billable'); ?></small><strong><?= e(app_format_money(sum_from_table(db_prefix() . 'expenses', ['where' => ['project_id' => $project->id, 'billable' => 1], 'field' => 'amount']), $currency)); ?></strong></div>
                <div class="mk33-finance-item is-green"><small><?= _l('project_overview_expenses_billed'); ?></small><strong><?= e(app_format_money(sum_from_table(db_prefix() . 'expenses', ['where' => ['project_id' => $project->id, 'invoiceid !=' => 'NULL', 'billable' => 1], 'field' => 'amount']), $currency)); ?></strong></div>
                <div class="mk33-finance-item is-red"><small><?= _l('project_overview_expenses_unbilled'); ?></small><strong><?= e(app_format_money(sum_from_table(db_prefix() . 'expenses', ['where' => ['project_id' => $project->id, 'invoiceid IS NULL', 'billable' => 1], 'field' => 'amount']), $currency)); ?></strong></div>
            <?php } ?>
        </div>
    </section>
<?php } ?>

<section class="mk33-description-card">
    <div class="mk33-overview-card-head">
        <span class="mk33-overview-icon"><i class="fa-regular fa-file-lines"></i></span>
        <div>
            <strong><?= _l('project_description'); ?></strong>
            <small>Project scope and notes</small>
        </div>
    </div>

    <div class="tc-content project-description">
        <?php if (empty($project->description)) { ?>
            <div class="mk33-empty-state">
                <i class="fa-regular fa-file-lines"></i>
                <strong><?= _l('no_description_project'); ?></strong>
            </div>
        <?php } ?>
        <?= check_for_links($project->description); ?>
    </div>
</section>
