<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>

<div id="wrapper">

    <div class="content">

        <div class="row">

            <div class="col-md-12">

                <div class="panel_s">

                    <div class="panel-body">

                        <h4 class="no-margin">
                            Project Sync
                        </h4>

                        <hr class="hr-panel-heading" />

                        <p>
                            Select the target company and branch for each project.
                        </p>

                        <?php if (empty($projects)) { ?>

                            <p>
                                No projects found.
                            </p>

                        <?php } else { ?>

                            <div class="table-responsive">

                                <table class="table table-striped">

                                    <thead>

                                        <tr>
                                            <th>ID</th>
                                            <th>Project</th>
                                            <th>Start Date</th>
                                            <th>Deadline</th>
                                            <th>Target Company</th>
                                            <th>Target Branch</th>
                                            <th>Action</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($projects as $project) { ?>

                                            <tr>

                                                <td>
                                                    <?php echo $project->id; ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($project->name); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($project->start_date); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($project->deadline); ?>
                                                </td>

                                                <td>

                                                    <select
                                                        class="form-control synchub-company"
                                                        data-project-id="<?php echo $project->id; ?>"
                                                    >

                                                        <option value="">
                                                            Select Company
                                                        </option>

                                                        <?php foreach ($companies as $company) { ?>

                                                            <option value="<?php echo $company->id; ?>">
                                                                <?php echo html_escape($company->name); ?>
                                                            </option>

                                                        <?php } ?>

                                                    </select>

                                                </td>

                                                <td>

                                                    <select
                                                        class="form-control synchub-branch"
                                                        data-project-id="<?php echo $project->id; ?>"
                                                    >

                                                        <option value="">
                                                            Select Branch
                                                        </option>

                                                        <?php foreach ($branches as $branch) { ?>

                                                            <option
                                                                value="<?php echo $branch->id; ?>"
                                                                data-company-id="<?php echo $branch->company_id; ?>"
                                                            >
                                                                <?php echo html_escape($branch->name); ?>
                                                            </option>

                                                        <?php } ?>

                                                    </select>

                                                </td>

                                                <td>

                                                    <button
                                                        type="button"
                                                        class="btn btn-primary synchub-sync-project"
                                                        data-project-id="<?php echo $project->id; ?>"
                                                    >
                                                        Sync
                                                    </button>

                                                </td>

                                            </tr>

                                        <?php } ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php } ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php init_tail(); ?>

<script>

$(function() {

    /*
    |--------------------------------------------------------------------------
    | Filter branches by selected company
    |--------------------------------------------------------------------------
    */

    $('.synchub-company').on('change', function() {

        var projectId = $(this).data('project-id');
        var companyId = $(this).val();

        var branchSelect = $(
            '.synchub-branch[data-project-id="' + projectId + '"]'
        );

        branchSelect.val('');

        branchSelect.find('option').each(function() {

            var optionCompanyId = $(this).data('company-id');

            if (!optionCompanyId) {
                $(this).show();
                return;
            }

            if (String(optionCompanyId) === String(companyId)) {
                $(this).show();
            } else {
                $(this).hide();
            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | Add Project To Sync Queue
    |--------------------------------------------------------------------------
    */

    $('.synchub-sync-project').on('click', function() {

        var button = $(this);

        var projectId = button.data('project-id');

        var companyId = $(
            '.synchub-company[data-project-id="' + projectId + '"]'
        ).val();

        var branchId = $(
            '.synchub-branch[data-project-id="' + projectId + '"]'
        ).val();

        if (!companyId) {

            alert('Please select a target company.');

            return;
        }

        if (!branchId) {

            alert('Please select a target branch.');

            return;
        }

        button.prop('disabled', true);
        button.text('Adding...');

        $.ajax({

            url: '<?php echo admin_url('synchub/queue_project'); ?>',

            type: 'POST',

            dataType: 'json',

            data: {
                project_id: projectId,
                company_id: companyId,
                branch_id: branchId
            },

            success: function(response) {

                if (response.success) {

                    alert(response.message);

                    button.text('Queued');

                } else {

                    alert(response.message);

                    button.prop('disabled', false);
                    button.text('Sync');

                }

            },

            error: function() {

                alert(
                    'An error occurred while adding the project to the sync queue.'
                );

                button.prop('disabled', false);
                button.text('Sync');

            }

        });

    });

});

</script>