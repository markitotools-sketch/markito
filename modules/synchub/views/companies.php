<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>

<div id="wrapper">

    <div class="content">

        <div class="row">

            <div class="col-md-12">

                <div class="panel_s">

                    <div class="panel-body">

                        <h4 class="no-margin">
                            SyncHub Companies
                        </h4>

                        <hr class="hr-panel-heading" />

                        <h4>Add Company</h4>

                        <?php echo form_open(admin_url('synchub/companies')); ?>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Company Name
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="name"
                                        placeholder="Example: Seen Marketing"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Company Code
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="code"
                                        placeholder="Example: SEEN"
                                        required
                                    >

                                </div>

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Company Type
                                    </label>

                                    <select
                                        name="type"
                                        class="form-control"
                                    >

                                        <option value="master">
                                            Master
                                        </option>

                                        <option value="child">
                                            Child
                                        </option>

                                    </select>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Base URL
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="base_url"
                                        placeholder="https://app.seenmarketing.ae"
                                    >

                                </div>

                            </div>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Add Company
                        </button>

                        <?php echo form_close(); ?>

                        <hr>

                        <h4>Companies</h4>

                        <?php if (empty($companies)) { ?>

                            <p>
                                No companies added yet.
                            </p>

                        <?php } else { ?>

                            <div class="table-responsive">

                                <table class="table table-striped">

                                    <thead>

                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Code</th>
                                            <th>Type</th>
                                            <th>Base URL</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($companies as $company) { ?>

                                            <tr>

                                                <td>
                                                    <?php echo $company->id; ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($company->name); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($company->code); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($company->type); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($company->base_url); ?>
                                                </td>

                                                <td>
                                                    <?php echo $company->active ? 'Active' : 'Inactive'; ?>
                                                </td>

                                                <td>
                                                    <?php if (strtolower((string) $company->type) !== 'master') { ?>
                                                        <?php echo form_open(admin_url('synchub/delete_company/' . (int) $company->id), ['style' => 'display:inline;', 'onsubmit' => "return confirm('Delete this SyncHub company? This is allowed only when it has no dependent SyncHub data.');"]); ?>
                                                        <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                                        <?php echo form_close(); ?>
                                                    <?php } else { ?>
                                                        <span class="text-muted">Protected</span>
                                                    <?php } ?>
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