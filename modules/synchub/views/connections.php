<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>

<div id="wrapper">

    <div class="content">

        <div class="row">

            <div class="col-md-12">

                <div class="panel_s">

                    <div class="panel-body">

                        <h4 class="no-margin">
                            SyncHub Connections
                        </h4>

                        <hr class="hr-panel-heading" />

                        <h4>Add Connection</h4>

                        <?php echo form_open(admin_url('synchub/connections')); ?>

                        <div class="row">

                            <div class="col-md-6">

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

                                            <option value="<?php echo $company->id; ?>">
                                                <?php echo html_escape($company->name); ?>
                                            </option>

                                        <?php } ?>

                                    </select>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Remote URL
                                    </label>

                                    <input
                                        type="url"
                                        class="form-control"
                                        name="remote_url"
                                        placeholder="https://app.seenmarketing.ae"
                                        required
                                    >

                                </div>

                            </div>

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Add Connection
                        </button>

                        <?php echo form_close(); ?>

                        <hr>

                        <h4>Connections</h4>

                        <?php if (empty($connections)) { ?>

                            <p>
                                No connections added yet.
                            </p>

                        <?php } else { ?>

                            <div class="table-responsive">

                                <table class="table table-striped">

                                    <thead>

                                        <tr>
                                            <th>ID</th>
                                            <th>Company</th>
                                            <th>Remote URL</th>
                                            <th>Status</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($connections as $connection) { ?>

                                            <tr>

                                                <td>
                                                    <?php echo $connection->id; ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($connection->company_name); ?>
                                                </td>

                                                <td>
                                                    <?php echo html_escape($connection->remote_url); ?>
                                                </td>

                                                <td>
                                                    <?php echo $connection->active ? 'Active' : 'Inactive'; ?>
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