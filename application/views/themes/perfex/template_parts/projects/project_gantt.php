<?php defined('BASEPATH') or exit('No direct script access allowed');

$mk42GanttCount = is_array($gantt_data ?? null) ? count($gantt_data) : 0;
?>

<section class="mk42-feature-hero mk42-gantt-hero mk4-cursor-glow">
    <div>
        <div class="mk4-kicker">DELIVERY TIMELINE</div>
        <h2>Project Gantt</h2>
        <p>See tasks, milestones and project timing on a single visual timeline.</p>
    </div>

    <div class="mk42-feature-stats">
        <div class="mk42-feature-stat">
            <span class="mk42-feature-stat-icon"><i class="fa-solid fa-chart-gantt"></i></span>
            <span><strong class="mk4-counter" data-value="<?= e($mk42GanttCount); ?>">0</strong><small>Timeline Items</small></span>
        </div>
    </div>
</section>

<section class="mk42-gantt-shell">
    <div class="mk42-gantt-toolbar">
        <div>
            <strong>Timeline</strong>
            <small>Change the scale to inspect your project at a different level.</small>
        </div>

        <?php if (count($gantt_data) > 0) { ?>
            <div class="mk42-gantt-view">
                <label for="mk42-gantt-view">View</label>
                <select class="selectpicker" name="gantt_view" id="mk42-gantt-view">
                    <option value="Day"><?= _l('gantt_view_day'); ?></option>
                    <option value="Week"><?= _l('gantt_view_week'); ?></option>
                    <option value="Month" selected><?= _l('gantt_view_month'); ?></option>
                    <option value="Year"><?= _l('gantt_view_year'); ?></option>
                </select>
            </div>
        <?php } ?>
    </div>

    <?php if (count($gantt_data) > 0) { ?>
        <div class="mk42-gantt-canvas">
            <svg id="gantt"></svg>
        </div>
    <?php } else { ?>
        <div class="mk42-empty-premium">
            <span><i class="fa-solid fa-chart-gantt"></i></span>
            <strong><?= _l('no_tasks_found'); ?></strong>
            <p>Tasks with dates will appear on the timeline automatically.</p>
        </div>
    <?php } ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var gantt_data = <?= json_encode($gantt_data); ?>;

    if (gantt_data.length > 0) {
        var gantt = new Gantt("#gantt", gantt_data, {
            view_modes: ['Day', 'Week', 'Month', 'Year'],
            view_mode: 'Month',
            date_format: 'YYYY-MM-DD',
            popup_trigger: 'click mouseover',
            on_click: function(data) {
                if (typeof(data.task_id) != 'undefined') {
                    let params = [];
                    params['group'] = 'project_tasks';
                    params['taskid'] = data.task_id;
                    window.location.href = buildUrl(site_url + 'clients/project/' + project_id, params);
                }
            }
        });

        $("#gantt g.handle-group").hide();

        $('body').on('mouseleave', '.grid-row', function() {
            gantt.hide_popup();
        });

        $('select[name$="gantt_view"]').change(function(el) {
            let view = $(el.target).val();
            gantt.change_view_mode(view);
        });
    }
});
</script>
