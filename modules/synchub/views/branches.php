<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>

<div id="wrapper">

    <div class="content">

        <div class="row">

            <div class="col-md-12">

                <div class="panel_s">

                    <div class="panel-body">

                        <h4 class="no-margin">
                            SyncHub Branches
                        </h4>

                        <hr class="hr-panel-heading" />

                        <h4>Add Branch</h4>

                        <?php echo form_open(admin_url('synchub/branches')); ?>

                        <div class="row">

                            <div class="col-md-4">

                                <div class="form-group">

                                    <label>
                                        Company
                                    </label>

                                    <select
                                        class="form-control"
                                        name="company_id"
                                        required
                                    >

                                        <option value="">
                                            Select Company
                                        </option>

                                        <?php foreach ($companies as $company) { ?>

                                            <option
                                                value="<?php echo $company->id; ?>"
                                            >
                                                <?php echo html_escape($company->name); ?>
                                            </option>

                                        <?php } ?>

                                    </select>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div class="form-group">

                                    <label>
                                        Branch Name
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="name"
                                        placeholder="Example: Cairo"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div class="form-group">

                                    <label>
                                        Branch Code
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="code"
                                        placeholder="Example: CAIRO"
                                        required
                                    >

                                </div>

                            </div>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Add Branch
                        </button>

                        <?php echo form_close(); ?>

                        <hr>

                        <h4>Branches</h4>

                        <?php if (empty($branches)) { ?>

                            <p>
                                No branches added yet.
                            </p>

                        <?php } else { ?>

                            <div class="table-responsive">

                                <table class="table table-striped">

                                    <thead>

                                        <tr>
                                            <th>ID</th>
                                            <th>Company</th>
                                            <th>Branch</th>
                                            <th>Code</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($branches as $branch) { ?>

                                            <tr>

                                                <td>
                                                    <?php echo $branch->id; ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($branch->company_name); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($branch->name); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($branch->code); ?>
                                                </td>

                                                <td>
                                                    <?php echo $branch->active ? 'Active' : 'Inactive'; ?>
                                                </td>

                                                <td>
                                                    <?php echo form_open(admin_url('synchub/delete_branch/' . (int) $branch->id), ['style' => 'display:inline;', 'onsubmit' => "return confirm('Delete this SyncHub branch? This is allowed only when it has no dependent SyncHub data.');"]); ?>
                                                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                                    <?php echo form_close(); ?>
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