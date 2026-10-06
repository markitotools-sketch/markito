<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="mk42-ticket-table-wrap">
<table class="table dt-table table-tickets mk42-ticket-table"
  data-order-col="<?= get_option('services') == 1 ? 7 : 6; ?>"
  data-order-type="desc">
  <thead>
    <tr>
      <th width="10%" class="th-ticket-number"><?= _l('clients_tickets_dt_number'); ?></th>
      <th class="th-ticket-subject"><?= _l('clients_tickets_dt_subject'); ?></th>
      <?php if ($show_submitter_on_table) { ?>
        <th class="th-ticket-submitter"><?= _l('ticket_dt_submitter'); ?></th>
      <?php } ?>
      <th class="th-ticket-department"><?= _l('clients_tickets_dt_department'); ?></th>
      <th class="th-ticket-project"><?= _l('project'); ?></th>
      <?php if (get_option('services') == 1) { ?>
        <th class="th-ticket-service"><?= _l('clients_tickets_dt_service'); ?></th>
      <?php } ?>
      <th class="th-ticket-priority"><?= _l('priority'); ?></th>
      <th class="th-ticket-status"><?= _l('clients_tickets_dt_status'); ?></th>
      <th class="th-ticket-last-reply"><?= _l('clients_tickets_dt_last_reply'); ?></th>
      <?php
      $custom_fields = get_custom_fields('tickets', ['show_on_client_portal' => 1]);
      foreach ($custom_fields as $field) { ?>
        <th><?= e($field['name']); ?></th>
      <?php } ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($tickets as $ticket) { ?>
      <tr class="mk42-ticket-row<?= $ticket['clientread'] == 0 ? ' is-unread' : ''; ?>">
        <td data-order="<?= e($ticket['ticketid']); ?>">
          <a class="mk42-ticket-number" href="<?= site_url('clients/ticket/' . $ticket['ticketid']); ?>">
            #<?= e($ticket['ticketid']); ?>
          </a>
        </td>
        <td>
          <a class="mk42-ticket-subject" href="<?= site_url('clients/ticket/' . $ticket['ticketid']); ?>">
            <span class="mk42-ticket-row-icon"><i class="fa-regular fa-message"></i></span>
            <span>
              <strong><?= e($ticket['subject']); ?></strong>
              <small><?= e($ticket['department_name']); ?></small>
            </span>
          </a>
        </td>
        <?php if ($show_submitter_on_table) { ?>
          <td><?= e($ticket['user_firstname'] . ' ' . $ticket['user_lastname']); ?></td>
        <?php } ?>
        <td><?= e($ticket['department_name']); ?></td>
        <td>
          <?php
          if ($ticket['project_id'] != 0) {
              echo '<a href="' . site_url('clients/project/' . $ticket['project_id']) . '">' . e(get_project_name_by_id($ticket['project_id'])) . '</a>';
          }
          ?>
        </td>
        <?php if (get_option('services') == 1) { ?>
          <td><?= e($ticket['service_name']); ?></td>
        <?php } ?>
        <td><span class="mk42-priority-pill"><?= e(ticket_priority_translate($ticket['priority'])); ?></span></td>
        <td>
          <span class="label inline-block" style="background:<?= e($ticket['statuscolor']); ?>">
            <?= e(ticket_status_translate($ticket['ticketstatusid'])); ?>
          </span>
        </td>
        <td data-order="<?= e($ticket['lastreply']); ?>">
          <?= $ticket['lastreply'] == null ? _l('client_no_reply') : e(_dt($ticket['lastreply'])); ?>
        </td>
        <?php foreach ($custom_fields as $field) { ?>
          <td><?= get_custom_field_value($ticket['ticketid'], $field['id'], 'tickets'); ?></td>
        <?php } ?>
      </tr>
    <?php } ?>
  </tbody>
</table>
</div>
