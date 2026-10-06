<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" class="mk-admin-dashboard-page">
    <div class="screen-options-btn mk104-native-options-trigger" aria-hidden="true"></div>
    <div class="content">
        <div class="row">
            <?php $this->load->view('admin/includes/alerts'); ?>

            <?php hooks()->do_action('before_start_render_dashboard_content'); ?>

            <div class="clearfix"></div>


        <section class="mk-admin-dashboard-hero" aria-label="Dashboard overview">
            <div class="mk-admin-dashboard-hero-copy">
                <div class="mk-admin-dashboard-kicker">
                    <span class="mk-admin-dashboard-kicker-dot"></span>
                    MARKITO COMMAND CENTER
                </div>
                <h1>
                    <?= _l('dashboard'); ?>
                    <span class="mk-admin-dashboard-wave">/</span>
                    <?= e(get_staff_full_name(get_staff_user_id())); ?>
                </h1>
                <p>Everything that needs your attention, organized in one place.</p>
            </div>

            <div class="mk-admin-dashboard-hero-meta">
                <div class="mk-admin-dashboard-date">
                    <i class="fa-regular fa-calendar"></i>
                    <span><?= e(date('D, d M Y')); ?></span>
                </div>
                <button type="button" class="mk-admin-dashboard-options-trigger"
                    onclick="window.mkToggleDashboardOptions && window.mkToggleDashboardOptions();">
                    <i class="fa-solid fa-sliders"></i>
                    <span><?= _l('dashboard_options'); ?></span>
                </button>
            </div>
        </section>

            <div class="screen-options-area mk104-dashboard-options-area" style="display:none;"></div>


            <div class="col-md-12 mtop20 mk-admin-dashboard-zone mk-admin-dashboard-zone-top" data-container="top-12">
                <?php render_dashboard_widgets('top-12'); ?>
            </div>

            <?php hooks()->do_action('after_dashboard_top_container'); ?>

            <div class="col-md-6 mk-admin-dashboard-zone" data-container="middle-left-6">
                <?php render_dashboard_widgets('middle-left-6'); ?>
            </div>
            <div class="col-md-6 mk-admin-dashboard-zone" data-container="middle-right-6">
                <?php render_dashboard_widgets('middle-right-6'); ?>
            </div>

            <?php hooks()->do_action('after_dashboard_half_container'); ?>

            <div class="col-md-8 mk-admin-dashboard-zone" data-container="left-8">
                <?php render_dashboard_widgets('left-8'); ?>
            </div>
            <div class="col-md-4 mk-admin-dashboard-zone" data-container="right-4">
                <?php render_dashboard_widgets('right-4'); ?>
            </div>

            <div class="clearfix"></div>

            <div class="col-md-4 mk-admin-dashboard-zone" data-container="bottom-left-4">
                <?php render_dashboard_widgets('bottom-left-4'); ?>
            </div>
            <div class="col-md-4 mk-admin-dashboard-zone" data-container="bottom-middle-4">
                <?php render_dashboard_widgets('bottom-middle-4'); ?>
            </div>
            <div class="col-md-4 mk-admin-dashboard-zone" data-container="bottom-right-4">
                <?php render_dashboard_widgets('bottom-right-4'); ?>
            </div>

            <?php hooks()->do_action('after_dashboard'); ?>
        </div>
    </div>
</div>
<script>
    app.calendarIDs = '<?= json_encode($google_ids_calendars); ?>';
</script>
<?php init_tail(); ?>
<?php $this->load->view('admin/utilities/calendar_template'); ?>
<?php $this->load->view('admin/dashboard/dashboard_js'); ?>
</body>

</html>