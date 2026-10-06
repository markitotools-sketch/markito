<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?= form_hidden('project_id', $project->id); ?>

<section class="mk33-project-hero mk33-spotlight">
    <div class="mk33-project-hero-main">
        <div class="mk33-project-icon"><i class="fa-regular fa-folder-open"></i></div>
        <div class="mk33-project-title-wrap">
            <div class="mk33-eyebrow">PROJECT #<?= e($project->id); ?></div>
            <div class="mk33-project-title-line">
                <h1><?= e($project->name); ?></h1>
                <?= '<span class="label project-status-' . $project_status['id'] . '" style="color:' . $project_status['color'] . ';border:1px solid ' . adjust_hex_brightness($project_status['color'], 0.4) . ';background:' . adjust_hex_brightness($project_status['color'], 0.04) . ';">' . e($project_status['name']) . '</span>'; ?>
            </div>
            <div class="mk33-project-meta">
                <span><i class="fa-regular fa-calendar"></i> <?= e(_d($project->start_date)); ?></span>
                <?php if ($project->deadline) { ?>
                    <span><i class="fa-regular fa-flag"></i> <?= e(_d($project->deadline)); ?></span>
                <?php } ?>
                <?php if ($project->settings->view_team_members == 1 && count($members) > 0) { ?>
                    <span class="mk33-team-inline">
                        <i class="fa-regular fa-user"></i>
                        <?= e(count($members)); ?> <?= count($members) == 1 ? 'member' : 'members'; ?>
                    </span>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="mk33-project-hero-side">
        <div class="mk33-progress-ring"
             style="--mk33-progress:<?= e(isset($progress) ? (int) $progress : 0); ?>;">
            <div>
                <strong class="mk33-count" data-count="<?= e(isset($progress) ? (int) $progress : 0); ?>">0</strong>
                <small>%</small>
            </div>
        </div>

        <?php if ($project->settings->view_team_members == 1 && count($members) > 0) { ?>
            <div class="mk33-team-stack">
                <?php foreach ($members as $member) { ?>
                    <span data-title="<?= e(get_staff_full_name($member['staff_id'])); ?>" data-toggle="tooltip">
                        <?= staff_profile_image(
                            $member['staff_id'],
                            ['tw-inline-block tw-h-8 tw-w-8 tw-rounded-full tw-ring-2 tw-ring-white', '']
                        ); ?>
                    </span>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($project->settings->view_tasks == 1 && $project->settings->create_tasks == 1) { ?>
            <a href="<?= site_url('clients/project/' . $project->id . '?group=new_task'); ?>"
               class="btn btn-primary new-task mk33-new-task-btn">
                <i class="fa-regular fa-plus tw-mr-1"></i>
                <?= _l('new_task'); ?>
            </a>
        <?php } ?>
    </div>
</section>

<section class="panel_s mk33-project-workspace">
    <div class="panel-body">
        <?php get_template_part('projects/project_tabs'); ?>
        <div class="mk33-project-tab-stage">
            <?php get_template_part('projects/' . $group); ?>
        </div>
    </div>
</section>
