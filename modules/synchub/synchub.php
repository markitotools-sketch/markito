<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: SyncHub
Description: Multi-company and multi-branch synchronization system for Perfex CRM.
Version: 1.11.0
Requires at least: 2.3.*
*/

define('SYNCHUB_MODULE_NAME', 'synchub');

register_activation_hook(SYNCHUB_MODULE_NAME, 'synchub_activation_hook');

function synchub_activation_hook()
{
    require __DIR__ . '/install.php';
}

hooks()->add_action('admin_init', 'synchub_ensure_schema');

function synchub_ensure_schema()
{
    require __DIR__ . '/install.php';
}

function synchub_instance_config($key = null)
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config/instance.php';
        $config = file_exists($file) ? require $file : [];
    }
    if ($key === null) return $config;
    return array_key_exists($key, $config) ? $config[$key] : null;
}

function synchub_instance_code()
{
    return strtoupper(trim((string) synchub_instance_config('code')));
}

function synchub_instance_role()
{
    return strtolower(trim((string) synchub_instance_config('role')));
}


// SyncHub v9: capture the staff member who initiated the outbound request.
// The receiver uses this metadata to create local Perfex Project Activity rows
// without depending on a browser session in the stateless API controller.
function synchub_attach_activity_actor($payload)
{
    if (!is_array($payload)) $payload = [];

    if (empty($payload['sender_instance_code'])) {
        $payload['sender_instance_code'] = synchub_instance_code();
    }

    if (array_key_exists('activity_actor_source_staff_id', $payload)) {
        return $payload;
    }

    $staffId = 0;
    if (function_exists('is_staff_logged_in') && function_exists('get_staff_user_id') && is_staff_logged_in()) {
        $staffId = (int)get_staff_user_id();
    }

    $email = '';
    $fullname = '';
    if ($staffId > 0) {
        $CI = &get_instance();
        $staff = $CI->db->select('firstname,lastname,email')->where('staffid',$staffId)
            ->get(db_prefix().'staff')->row();
        if ($staff) {
            $email = strtolower(trim((string)$staff->email));
            $fullname = trim((string)$staff->firstname . ' ' . (string)$staff->lastname);
            if ($fullname === '') $fullname = (string)$staff->email;
        }
    }

    $payload['activity_actor_source_staff_id'] = $staffId;
    $payload['activity_actor_email'] = $email;
    $payload['activity_actor_fullname'] = $fullname;
    return $payload;
}

function synchub_get_entity_origin($entityType, $localId)
{
    $CI = &get_instance();
    return $CI->db->where('entity_type', strtolower((string)$entityType))
        ->where('local_id', (int)$localId)
        ->get(db_prefix() . 'synchub_entity_origin')->row();
}

function synchub_register_entity_origin($entityType, $localId, $sourceCompanyCode = null, $sourceBranchId = null, $syncUuid = null)
{
    $CI = &get_instance();
    $entityType = strtolower(trim((string)$entityType));
    $localId = (int)$localId;
    $sourceCompanyCode = strtoupper(trim((string)($sourceCompanyCode ?: synchub_instance_code())));
    if ($entityType === '' || $localId <= 0 || $sourceCompanyCode === '') return false;

    $existing = synchub_get_entity_origin($entityType, $localId);
    if ($existing) return $existing;

    $ok = $CI->db->insert(db_prefix() . 'synchub_entity_origin', [
        'entity_type' => $entityType,
        'local_id' => $localId,
        'source_company_code' => $sourceCompanyCode,
        'source_branch_id' => $sourceBranchId !== null ? (int)$sourceBranchId : null,
        'sync_uuid' => $syncUuid ?: synchub_generate_uuid(),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => null,
    ]);
    return $ok ? synchub_get_entity_origin($entityType, $localId) : false;
}

function synchub_init_menu()
{
    // SyncHub v9.3: the SyncHub administration UI is a master/control-plane feature.
    // Child instances keep all SyncHub runtime/API functionality but do not expose
    // the SyncHub sidebar menu to staff users.
    if (synchub_instance_role() !== 'master') {
        return;
    }

    $CI = &get_instance();
    $CI->app_menu->add_sidebar_menu_item('synchub', [
        'name' => 'SyncHub', 'href' => admin_url('synchub'), 'icon' => 'fa fa-exchange', 'position' => 6,
    ]);
    $CI->app_menu->add_sidebar_children_item('synchub', [
        'slug' => 'synchub-companies', 'name' => 'Companies', 'href' => admin_url('synchub/companies'),
    ]);
    $CI->app_menu->add_sidebar_children_item('synchub', [
        'slug' => 'synchub-branches', 'name' => 'Branches', 'href' => admin_url('synchub/branches'),
    ]);
}
hooks()->add_action('admin_init', 'synchub_init_menu');

function synchub_project_payload($projectId, $origin, $projectIdForRemote = null)
{
    $CI = &get_instance();
    $project = $CI->db->where('id', (int)$projectId)->get(db_prefix() . 'projects')->row();
    if (!$project || !$origin) return null;
    // Project members must travel by a stable cross-instance key, never by staff ID.
    $memberEmails = [];
    $memberRows = $CI->db
        ->select('s.email')
        ->from(db_prefix() . 'project_members pm')
        ->join(db_prefix() . 'staff s', 's.staffid = pm.staff_id', 'inner')
        ->where('pm.project_id', (int)$projectId)
        ->get()
        ->result();
    foreach ($memberRows as $memberRow) {
        $email = strtolower(trim((string)$memberRow->email));
        if ($email !== '') $memberEmails[$email] = $email;
    }

    $creatorEmail = '';
    if (!empty($project->addedfrom)) {
        $creator = $CI->db->select('email')->where('staffid', (int)$project->addedfrom)->get(db_prefix() . 'staff')->row();
        if ($creator) $creatorEmail = strtolower(trim((string)$creator->email));
    }

    return [
        'project_id' => $projectIdForRemote !== null ? (int)$projectIdForRemote : (int)$project->id,
        'name' => $project->name,
        'clientid' => (int)$project->clientid,
        'status' => (int)$project->status,
        'start_date' => $project->start_date,
        'deadline' => $project->deadline,
        'description' => $project->description,
        'project_member_emails' => array_values($memberEmails),
        'project_creator_email' => $creatorEmail,
        'source_company_code' => (string)$origin->source_company_code,
        'source_branch_id' => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'sync_uuid' => (string)$origin->sync_uuid,
        'sender_instance_code' => synchub_instance_code(),
    ];
}

function synchub_company_by_code($code)
{
    $CI = &get_instance();
    return $CI->db->where('code', strtoupper(trim((string)$code)))->where('active', 1)
        ->get(db_prefix() . 'synchub_companies')->row();
}

function synchub_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db->where('entity_type', 'project')->where('sync_uuid', (string)$syncUuid)
        ->get(db_prefix() . 'synchub_entity_map')->row();
}

function synchub_project_assignment($projectId)
{
    $CI = &get_instance();
    $table = db_prefix() . 'synchub_project_assignment';
    if (!$CI->db->table_exists($table)) return null;

    return $CI->db
        ->where('project_id', (int)$projectId)
        ->get($table)
        ->row();
}

function synchub_register_project_assignment($projectId, $syncUuid, $assignedCompanyCode, $createdFromInstanceCode)
{
    $CI = &get_instance();
    $table = db_prefix() . 'synchub_project_assignment';
    if (!$CI->db->table_exists($table)) return false;

    $projectId = (int)$projectId;
    $syncUuid = trim((string)$syncUuid);
    $assignedCompanyCode = strtoupper(trim((string)$assignedCompanyCode));
    $createdFromInstanceCode = strtoupper(trim((string)$createdFromInstanceCode));
    if ($projectId <= 0 || $syncUuid === '' || $assignedCompanyCode === '' || $createdFromInstanceCode === '') return false;

    $existing = $CI->db->where('project_id', $projectId)->get($table)->row();
    $data = [
        'sync_uuid' => $syncUuid,
        'assigned_company_code' => $assignedCompanyCode,
        'created_from_instance_code' => $createdFromInstanceCode,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($existing) {
        return $CI->db->where('id', (int)$existing->id)->update($table, $data);
    }

    $data['project_id'] = $projectId;
    $data['created_at'] = date('Y-m-d H:i:s');
    return $CI->db->insert($table, $data);
}

function synchub_is_master_created_assigned_project($projectId)
{
    if (synchub_instance_role() !== 'master') return false;
    $assignment = synchub_project_assignment((int)$projectId);
    if (!$assignment) return false;

    return strtoupper(trim((string)$assignment->created_from_instance_code)) === synchub_instance_code()
        && strtoupper(trim((string)$assignment->assigned_company_code)) !== ''
        && strtoupper(trim((string)$assignment->assigned_company_code)) !== synchub_instance_code();
}

function synchub_remote_url($companyCode)
{
    $companyCode = strtoupper(trim((string)$companyCode));
    if (synchub_instance_role() === 'child') {
        return rtrim((string)synchub_instance_config('master_url'), '/');
    }
    $children = synchub_instance_config('child_urls');
    if (is_array($children) && !empty($children[$companyCode])) return rtrim((string)$children[$companyCode], '/');
    return '';
}

function synchub_enqueue_project_event($projectId, $action)
{
    $CI = &get_instance();
    $projectId = (int)$projectId;
    $action = strtolower((string)$action);
    $origin = synchub_get_entity_origin('project', $projectId);
    if (!$origin) return false;

    $sourceCode = strtoupper((string)$origin->source_company_code);
    $instanceCode = synchub_instance_code();

    // MARKITO-origin projects are local-only and never leave the master.
    if ($sourceCode === 'MARKITO') return false;

    $map = synchub_map_by_uuid((string)$origin->sync_uuid);
    $remoteProjectId = $map ? (int)$map->remote_id : null;

    if (synchub_instance_role() === 'master') {
        // Markito may create a project for a child before a remote mapping exists.
        if ($action !== 'create' && (!$map || $remoteProjectId <= 0)) return false;
        $destination = synchub_company_by_code($sourceCode);
    } else {
        // A child may only publish projects that originated in itself.
        if ($sourceCode !== $instanceCode) return false;
        $destination = synchub_company_by_code((string)synchub_instance_config('master_code'));
    }
    if (!$destination) return false;

    if ($action === 'delete') {
        $payload = [
            'project_id' => $remoteProjectId ?: $projectId,
            'source_company_code' => $sourceCode,
            'source_branch_id' => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
            'sync_uuid' => (string)$origin->sync_uuid,
            'sender_instance_code' => synchub_instance_code(),
        ];
    } else {
        $payload = synchub_project_payload($projectId, $origin, $remoteProjectId ?: null);
        if (!$payload) return false;
    }

    $ok = $CI->db->insert(db_prefix() . 'synchub_queue', [
        'event_id' => synchub_generate_uuid(),
        'entity_type' => 'project',
        'entity_id' => $projectId,
        'action' => $action,
        'company_id' => (int)$destination->id,
        'branch_id' => null,
        'payload' => json_encode($payload),
        'status' => 'pending',
        'attempts' => 0,
        'last_error' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => null,
    ]);
    if (!$ok) return false;
    return synchub_dispatch_project_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_project_queue_item($queueId)
{
    $CI = &get_instance();
    $queue = $CI->db->where('id', (int)$queueId)->where('entity_type', 'project')
        ->get(db_prefix() . 'synchub_queue')->row();
    if (!$queue) return false;

    $payload = json_decode((string)$queue->payload, true);
    if (!is_array($payload)) $payload = [];
    $payload['action'] = (string)$queue->action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody = json_encode($payload);

    $company = $CI->db->where('id', (int)$queue->company_id)->get(db_prefix() . 'synchub_companies')->row();
    if (!$company) return synchub_fail_queue_item((int)$queue->id, 'Destination company not found.');
    $urlBase = synchub_remote_url((string)$company->code);
    $apiKey = (string)synchub_instance_config('api_key');
    $apiSecret = (string)synchub_instance_config('api_secret');
    if ($urlBase === '' || $apiKey === '' || $apiSecret === '') {
        return synchub_fail_queue_item((int)$queue->id, 'Remote URL/API credentials are missing.');
    }

    $timestamp = (string)time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $apiSecret);
    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status' => 'processing', 'attempts' => (int)$queue->attempts + 1, 'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $ch = curl_init($urlBase . '/synchub/api/project');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $rawBody, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json', 'X-SyncHub-Key: ' . $apiKey,
            'X-SyncHub-Timestamp: ' . $timestamp, 'X-SyncHub-Signature: ' . $signature,
        ],
        CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError !== '') return synchub_fail_queue_item((int)$queue->id, 'cURL: ' . $curlError);

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id, 'HTTP ' . $httpCode . ': ' . (string)$response);
    }

    if ((string)$queue->action === 'create') {
        $remoteId = (int)($decoded['local_project_id'] ?? 0);
        $syncUuid = (string)($payload['sync_uuid'] ?? '');
        if ($remoteId <= 0 || $syncUuid === '') return synchub_fail_queue_item((int)$queue->id, 'Create response missing mapping data.');
        $map = synchub_map_by_uuid($syncUuid);
        $data = [
            'local_id' => (int)$queue->entity_id, 'remote_id' => $remoteId, 'company_id' => (int)$queue->company_id,
            'branch_id' => null, 'remote_company_id' => null, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($map) {
            $CI->db->where('id', (int)$map->id)->update(db_prefix() . 'synchub_entity_map', $data);
        } else {
            $data['entity_type'] = 'project'; $data['sync_uuid'] = $syncUuid; $data['created_at'] = date('Y-m-d H:i:s');
            $CI->db->insert(db_prefix() . 'synchub_entity_map', $data);
        }
    } else {
        $syncUuid = (string)($payload['sync_uuid'] ?? '');
        if ($syncUuid !== '') {
            $map = synchub_map_by_uuid($syncUuid);
            if ($map) $CI->db->where('id', (int)$map->id)->update(db_prefix() . 'synchub_entity_map', ['updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status' => 'success', 'last_error' => null, 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    return true;
}

function synchub_fail_queue_item($queueId, $error)
{
    $CI = &get_instance();
    $CI->db->where('id', (int)$queueId)->update(db_prefix() . 'synchub_queue', [
        'status' => 'failed', 'last_error' => substr((string)$error, 0, 65000), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    return false;
}

hooks()->add_filter('before_add_project', 'synchub_before_add_project_assignment');
function synchub_before_add_project_assignment($data)
{
    if (is_array($data) && array_key_exists('synchub_project_company', $data)) {
        unset($data['synchub_project_company']);
    }
    return $data;
}

hooks()->add_action('after_add_project', 'synchub_after_add_project_origin');
function synchub_after_add_project_origin($projectId)
{
    $projectId = (int)$projectId;
    if ($projectId <= 0) return;

    $CI = &get_instance();

    // SyncHub v9.9:
    // A child instance has no Project Company selector. A project created there
    // is source-owned by that child and must immediately publish CREATE upstream.
    if (synchub_instance_role() === 'child') {
        $origin = synchub_register_entity_origin(
            'project',
            $projectId,
            synchub_instance_code()
        );
        if (!$origin) return;

        synchub_enqueue_project_event($projectId, 'create');
        return;
    }

    // Master keeps the Project Company selector behavior:
    // MARKITO => local-only, child code => create in both.
    $selectedCompany = strtoupper(trim((string)$CI->input->post('synchub_project_company')));
    if ($selectedCompany === '') $selectedCompany = synchub_instance_code();

    if ($selectedCompany !== synchub_instance_code()) {
        $company = synchub_company_by_code($selectedCompany);
        if (!$company || strtolower((string)$company->type) !== 'child') {
            $selectedCompany = synchub_instance_code();
        }
    }

    $origin = synchub_register_entity_origin('project', $projectId, $selectedCompany);
    if (!$origin || $selectedCompany === synchub_instance_code()) return;

    synchub_register_project_assignment(
        $projectId,
        (string)$origin->sync_uuid,
        $selectedCompany,
        synchub_instance_code()
    );

    synchub_enqueue_project_event($projectId, 'create');
}

hooks()->add_action('after_update_project', 'synchub_project_updated');
function synchub_project_updated($projectId)
{
    synchub_enqueue_project_event((int)$projectId, 'update');
}

hooks()->add_action('before_project_deleted', 'synchub_project_before_delete');
function synchub_project_before_delete($projectId)
{
    $projectId = (int)$projectId;

    // Normal child-origin project deleted in Markito remains local-only.
    // Exception: project created in Markito and assigned to a child deletes from both.
    if (synchub_instance_role() === 'master') {
        if (synchub_is_master_created_assigned_project($projectId)) {
            synchub_enqueue_project_event($projectId, 'delete');
        }
        return;
    }

    synchub_enqueue_project_event($projectId, 'delete');
}

hooks()->add_action('after_project_deleted', 'synchub_project_after_delete_cleanup');
function synchub_project_after_delete_cleanup($projectId)
{
    $CI = &get_instance();
    $projectId = (int)$projectId;
    $origin = synchub_get_entity_origin('project', $projectId);

    if (!$origin) {
        return;
    }

    $sourceCode = strtoupper((string)$origin->source_company_code);

    // Normal child-origin project deleted in Markito remains local-only and keeps
    // its tombstone. Markito-created/child-assigned projects are the exception.
    $masterCreatedAssigned = synchub_is_master_created_assigned_project($projectId);
    if (synchub_instance_role() === 'master' && $sourceCode !== 'MARKITO' && !$masterCreatedAssigned) {
        return;
    }

    // Clean metadata for ordinary local deletes and the explicit assignment exception.
    $CI->db
        ->where('entity_type', 'project')
        ->where('sync_uuid', (string)$origin->sync_uuid)
        ->delete(db_prefix() . 'synchub_entity_map');

    $CI->db
        ->where('entity_type', 'project')
        ->where('local_id', $projectId)
        ->delete(db_prefix() . 'synchub_entity_origin');

    if ($CI->db->table_exists(db_prefix() . 'synchub_project_assignment')) {
        $CI->db->where('project_id', $projectId)->delete(db_prefix() . 'synchub_project_assignment');
    }
}



// SyncHub v9.2: preserve delete ownership for master-created child-target entities.
// source_company_code identifies the operational company, not necessarily the instance
// that originally created the task/milestone.  A local outbound CREATE queue event is
// the durable evidence available in the current schema that this instance created it.
function synchub_entity_was_created_on_this_instance($entityType, $localId, $syncUuid = '')
{
    $CI = &get_instance();
    $entityType = strtolower(trim((string)$entityType));
    $localId = (int)$localId;
    $syncUuid = trim((string)$syncUuid);

    if ($entityType === '' || $localId <= 0) {
        return false;
    }

    $CI->db->from(db_prefix() . 'synchub_queue');
    $CI->db->where('entity_type', $entityType);
    $CI->db->where('entity_id', $localId);
    $CI->db->where('action', 'create');
    $CI->db->order_by('id', 'ASC');
    $row = $CI->db->get()->row();

    if (!$row) {
        return false;
    }

    // Extra identity check when the historical payload contains a UUID.
    if ($syncUuid !== '') {
        $payload = json_decode((string)$row->payload, true);
        if (is_array($payload) && !empty($payload['sync_uuid'])
            && trim((string)$payload['sync_uuid']) !== $syncUuid) {
            return false;
        }
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| Task Sync - Phase 2 (Create / Update / Delete)
|--------------------------------------------------------------------------
|
| Tasks inherit the source company and visibility of their parent project.
| Core task data is synchronized here. Assignees, followers, checklist,
| comments, timers and attachments are handled in later phases.
|
*/

function synchub_task_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db
        ->where('entity_type', 'task')
        ->where('sync_uuid', (string)$syncUuid)
        ->get(db_prefix() . 'synchub_entity_map')
        ->row();
}

function synchub_task_assignees_payload($taskId)
{
    $CI = &get_instance();

    $rows = $CI->db
        ->select(db_prefix() . 'task_assigned.staffid, ' . db_prefix() . 'staff.email')
        ->from(db_prefix() . 'task_assigned')
        ->join(
            db_prefix() . 'staff',
            db_prefix() . 'staff.staffid = ' . db_prefix() . 'task_assigned.staffid',
            'left'
        )
        ->where(db_prefix() . 'task_assigned.taskid', (int)$taskId)
        ->get()
        ->result();

    $assignees = [];
    foreach ($rows as $row) {
        $assignees[] = [
            'source_staff_id' => (int)$row->staffid,
            'email'           => strtolower(trim((string)$row->email)),
        ];
    }

    return $assignees;
}

function synchub_apply_task_assignees($taskId, $projectId, $senderCompanyCode, $assignees)
{
    $CI = &get_instance();

    if (!is_array($assignees)) {
        return true;
    }

    $taskId = (int)$taskId;
    $projectId = (int)$projectId;
    $senderCompanyCode = strtoupper(trim((string)$senderCompanyCode));
    if ($taskId <= 0 || $projectId <= 0 || $senderCompanyCode === '') {
        return false;
    }

    $desired = [];
    foreach ($assignees as $assignee) {
        if (!is_array($assignee)) continue;
        $remoteStaffId = (int)($assignee['source_staff_id'] ?? 0);
        $email = strtolower(trim((string)($assignee['email'] ?? '')));
        $localStaffId = function_exists('synchub_resolve_local_staff_id')
            ? (int)synchub_resolve_local_staff_id($senderCompanyCode, $remoteStaffId, $email)
            : 0;
        if ($localStaffId <= 0) continue;

        $desired[$localStaffId] = true;
        if (total_rows(db_prefix() . 'project_members', ['project_id'=>$projectId,'staff_id'=>$localStaffId]) == 0) {
            $CI->db->insert(db_prefix() . 'project_members', ['project_id'=>$projectId,'staff_id'=>$localStaffId]);
        }
        if (total_rows(db_prefix() . 'task_assigned', ['taskid'=>$taskId,'staffid'=>$localStaffId]) == 0) {
            $CI->db->insert(db_prefix() . 'task_assigned', [
                'taskid'=>$taskId,
                'staffid'=>$localStaffId,
                'assigned_from'=>$localStaffId,
            ]);
        }
    }

    $existing = $CI->db->select('id,staffid')->where('taskid',$taskId)->get(db_prefix().'task_assigned')->result();
    foreach ($existing as $row) {
        if (!isset($desired[(int)$row->staffid])) {
            $CI->db->where('id',(int)$row->id)->delete(db_prefix().'task_assigned');
        }
    }
    return true;
}

function synchub_task_payload($taskId, $taskOrigin, $projectOrigin, $taskIdForRemote = null)
{
    $CI = &get_instance();

    $task = $CI->db
        ->where('id', (int)$taskId)
        ->get(db_prefix() . 'tasks')
        ->row();

    if (!$task || !$taskOrigin || !$projectOrigin || $task->rel_type !== 'project') {
        return null;
    }

    return [
        'task_id'                  => $taskIdForRemote !== null ? (int)$taskIdForRemote : (int)$task->id,
        'name'                     => (string)$task->name,
        'description'              => isset($task->description) ? (string)$task->description : '',
        'priority'                 => isset($task->priority) ? (int)$task->priority : 2,
        'status'                   => isset($task->status) ? (int)$task->status : 1,
        'startdate'                => isset($task->startdate) ? $task->startdate : date('Y-m-d'),
        'duedate'                  => isset($task->duedate) ? $task->duedate : null,
        'billable'                 => isset($task->billable) ? (int)$task->billable : 0,
        'visible_to_client'        => isset($task->visible_to_client) ? (int)$task->visible_to_client : 0,
        'hourly_rate'              => isset($task->hourly_rate) ? (float)$task->hourly_rate : 0,
        'milestone_sync_uuid'      => ((int)($task->milestone ?? 0) > 0 && ($mOrigin = synchub_get_entity_origin('milestone', (int)$task->milestone))) ? (string)$mOrigin->sync_uuid : '',
        'milestone_order'          => isset($task->milestone_order) ? (int)$task->milestone_order : 0,
        'parent_project_sync_uuid' => (string)$projectOrigin->sync_uuid,
        'source_company_code'      => (string)$taskOrigin->source_company_code,
        'source_branch_id'         => $taskOrigin->source_branch_id !== null ? (int)$taskOrigin->source_branch_id : null,
        'sync_uuid'                => (string)$taskOrigin->sync_uuid,
        'sender_instance_code'     => synchub_instance_code(),
        'assignees'                => synchub_task_assignees_payload($taskId),
    ];
}

function synchub_enqueue_task_event($taskId, $action = 'create')
{
    $CI = &get_instance();
    $taskId = (int)$taskId;
    $action = strtolower(trim((string)$action));

    if ($taskId <= 0 || !in_array($action, ['create', 'update', 'delete'], true)) {
        return false;
    }

    $taskOrigin = synchub_get_entity_origin('task', $taskId);
    $task = null;
    $projectOrigin = null;

    if ($action !== 'delete') {
        $task = $CI->db
            ->where('id', $taskId)
            ->get(db_prefix() . 'tasks')
            ->row();

        if (!$task || $task->rel_type !== 'project' || (int)$task->rel_id <= 0) {
            return false;
        }

        $projectOrigin = synchub_get_entity_origin('project', (int)$task->rel_id);
        if (!$projectOrigin) {
            return false;
        }

        // A task always inherits the source company of its parent project.
        if (!$taskOrigin) {
            $taskOrigin = synchub_register_entity_origin(
                'task',
                $taskId,
                (string)$projectOrigin->source_company_code,
                $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
            );
        }
    }

    if (!$taskOrigin) {
        return false;
    }

    $sourceCode = strtoupper(trim((string)$taskOrigin->source_company_code));

    // Markito-origin projects/tasks never leave Markito.
    if ($sourceCode === 'MARKITO') {
        return false;
    }

    $map = synchub_task_map_by_uuid((string)$taskOrigin->sync_uuid);
    $remoteTaskId = $map ? (int)$map->remote_id : 0;

    if (synchub_instance_role() === 'master') {
        // A genuinely child-created task remains source-owned: deleting it in Markito is local-only.
        // Exception: if Markito itself created this task for the child, delete must propagate.
        if ($action === 'delete') {
            if (!$map || $remoteTaskId <= 0
                || !synchub_entity_was_created_on_this_instance('task', $taskId, (string)$taskOrigin->sync_uuid)) {
                return false;
            }
        }

        $destination = synchub_company_by_code($sourceCode);
        if (!$destination) {
            return false;
        }

        if ($action === 'create') {
            // A task created in Markito for a child-assigned project has no task map yet.
            // Allow the first outbound create only when the parent project is already
            // mapped to that same child. This preserves normal child-origin routing.
            if (!$projectOrigin) {
                return false;
            }
            $projectMap = synchub_map_by_uuid((string)$projectOrigin->sync_uuid);
            if (!$projectMap
                || (int)$projectMap->remote_id <= 0
                || (int)$projectMap->company_id !== (int)$destination->id) {
                return false;
            }
        } elseif (!$map || $remoteTaskId <= 0) {
            return false;
        }
    } else {
        // A child only publishes tasks belonging to its own source projects.
        if ($sourceCode !== synchub_instance_code()) {
            return false;
        }
        if ($action !== 'create' && (!$map || $remoteTaskId <= 0)) {
            return false;
        }
        $destination = synchub_company_by_code((string)synchub_instance_config('master_code'));
    }

    if (!$destination) {
        return false;
    }

    if ($action === 'delete') {
        $payload = [
            'task_id'             => $remoteTaskId > 0 ? $remoteTaskId : $taskId,
            'source_company_code' => $sourceCode,
            'source_branch_id'    => $taskOrigin->source_branch_id !== null ? (int)$taskOrigin->source_branch_id : null,
            'sync_uuid'           => (string)$taskOrigin->sync_uuid,
        ];
    } else {
        $payload = synchub_task_payload(
            $taskId,
            $taskOrigin,
            $projectOrigin,
            $action === 'create' ? null : $remoteTaskId
        );
        if (!$payload) {
            return false;
        }
    }

    $ok = $CI->db->insert(db_prefix() . 'synchub_queue', [
        'event_id'    => synchub_generate_uuid(),
        'entity_type' => 'task',
        'entity_id'   => $taskId,
        'action'      => $action,
        'company_id'  => (int)$destination->id,
        'branch_id'   => $taskOrigin->source_branch_id !== null ? (int)$taskOrigin->source_branch_id : null,
        'payload'     => json_encode($payload),
        'status'      => 'pending',
        'attempts'    => 0,
        'last_error'  => null,
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => null,
    ]);

    if (!$ok) {
        return false;
    }

    return synchub_dispatch_task_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_task_queue_item($queueId)
{
    $CI = &get_instance();

    $queue = $CI->db
        ->where('id', (int)$queueId)
        ->where('entity_type', 'task')
        ->get(db_prefix() . 'synchub_queue')
        ->row();

    if (!$queue) {
        return false;
    }

    $payload = json_decode((string)$queue->payload, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $payload['action'] = (string)$queue->action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody = json_encode($payload);

    $company = $CI->db
        ->where('id', (int)$queue->company_id)
        ->get(db_prefix() . 'synchub_companies')
        ->row();

    if (!$company) {
        return synchub_fail_queue_item((int)$queue->id, 'Task destination company not found.');
    }

    $urlBase   = synchub_remote_url((string)$company->code);
    $apiKey    = (string)synchub_instance_config('api_key');
    $apiSecret = (string)synchub_instance_config('api_secret');

    if ($urlBase === '' || $apiKey === '' || $apiSecret === '') {
        return synchub_fail_queue_item((int)$queue->id, 'Task remote URL/API credentials are missing.');
    }

    $timestamp = (string)time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $apiSecret);

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'     => 'processing',
        'attempts'   => (int)$queue->attempts + 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $ch = curl_init($urlBase . '/synchub/api/task');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $rawBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-SyncHub-Key: ' . $apiKey,
            'X-SyncHub-Timestamp: ' . $timestamp,
            'X-SyncHub-Signature: ' . $signature,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return synchub_fail_queue_item((int)$queue->id, 'Task cURL: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id, 'Task HTTP ' . $httpCode . ': ' . (string)$response);
    }

    $syncUuid = trim((string)($payload['sync_uuid'] ?? ''));

    if ((string)$queue->action === 'create') {
        $remoteId = (int)($decoded['local_task_id'] ?? 0);
        if ($remoteId <= 0 || $syncUuid === '') {
            return synchub_fail_queue_item((int)$queue->id, 'Task create response missing mapping data.');
        }

        $map = synchub_task_map_by_uuid($syncUuid);
        $data = [
            'local_id'          => (int)$queue->entity_id,
            'remote_id'         => $remoteId,
            'company_id'        => (int)$queue->company_id,
            'branch_id'         => $queue->branch_id !== null ? (int)$queue->branch_id : null,
            'remote_company_id' => null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if ($map) {
            $CI->db->where('id', (int)$map->id)->update(db_prefix() . 'synchub_entity_map', $data);
        } else {
            $data['entity_type'] = 'task';
            $data['sync_uuid']   = $syncUuid;
            $data['created_at']  = date('Y-m-d H:i:s');
            $CI->db->insert(db_prefix() . 'synchub_entity_map', $data);
        }
    } elseif ($syncUuid !== '') {
        $map = synchub_task_map_by_uuid($syncUuid);
        if ($map) {
            $CI->db->where('id', (int)$map->id)->update(db_prefix() . 'synchub_entity_map', [
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'     => 'success',
        'last_error' => null,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return true;
}

hooks()->add_action('task_assignee_added', 'synchub_task_assignee_changed');
function synchub_task_assignee_changed($data)
{
    if (!empty($GLOBALS['synchub_receiving_task_assignees']) || !is_array($data)) return;
    $taskId = (int)($data['task_id'] ?? 0);
    if ($taskId > 0 && synchub_get_entity_origin('task', $taskId)) {
        synchub_enqueue_task_event($taskId, 'update');
    }
}

hooks()->add_action('after_add_task', 'synchub_after_add_task_create');
function synchub_after_add_task_create($taskId)
{
    if (!empty($GLOBALS['synchub_receiving_task'])) {
        return;
    }

    synchub_enqueue_task_event((int)$taskId, 'create');
}

hooks()->add_action('after_update_task', 'synchub_after_update_task');
function synchub_after_update_task($taskId)
{
    if (!empty($GLOBALS['synchub_receiving_task'])) {
        return;
    }

    synchub_enqueue_task_event((int)$taskId, 'update');
}

hooks()->add_action('task_status_changed', 'synchub_task_status_changed');
function synchub_task_status_changed($data)
{
    if (!empty($GLOBALS['synchub_receiving_task'])) {
        return;
    }

    $taskId = is_array($data) ? (int)($data['task_id'] ?? 0) : 0;
    if ($taskId > 0) {
        synchub_enqueue_task_event($taskId, 'update');
    }
}

// SyncHub v8: task milestone/order update surfaces.
// Perfex changes these fields through direct DB AJAX endpoints that do not fire after_update_task.
hooks()->add_action('admin_init', 'synchub_observe_task_update_surfaces');
function synchub_observe_task_update_surfaces()
{
    $CI = &get_instance();
    if (!empty($GLOBALS['synchub_receiving_task'])) return;
    $controller = strtolower((string)$CI->router->fetch_class());
    $method = strtolower((string)$CI->router->fetch_method());

    if ($controller === 'tasks' && $method === 'change_milestone') {
        $taskId = (int)$CI->uri->rsegment(4);
        if ($taskId <= 0) return;
        register_shutdown_function(function () use ($taskId) {
            if (synchub_get_entity_origin('task', $taskId)) synchub_enqueue_task_event($taskId, 'update');
        });
        return;
    }

    if ($controller === 'projects' && $method === 'update_task_milestone' && strtolower((string)$CI->input->method()) === 'post') {
        $ids = [];
        $moved = (int)$CI->input->post('task_id');
        if ($moved > 0) $ids[$moved] = true;
        $order = $CI->input->post('order');
        if (is_array($order)) foreach ($order as $row) if (is_array($row) && isset($row[0]) && (int)$row[0] > 0) $ids[(int)$row[0]] = true;
        $taskIds = array_keys($ids);
        register_shutdown_function(function () use ($taskIds) {
            foreach ($taskIds as $taskId) if (synchub_get_entity_origin('task', (int)$taskId)) synchub_enqueue_task_event((int)$taskId, 'update');
        });
    }
}

hooks()->add_action('task_deleted', 'synchub_task_deleted');
function synchub_task_deleted($taskId)
{
    $CI = &get_instance();
    $taskId = (int)$taskId;
    if ($taskId <= 0) {
        return;
    }

    $origin = synchub_get_entity_origin('task', $taskId);
    if (!$origin) {
        return;
    }

    $syncUuid = (string)$origin->sync_uuid;
    $sourceCode = strtoupper((string)$origin->source_company_code);

    // An inbound source-side delete is already authorized and applied locally.
    // Clean its metadata and do not send anything back.
    if (!empty($GLOBALS['synchub_receiving_task'])) {
        $CI->db->where('entity_type', 'task')->where('sync_uuid', $syncUuid)->delete(db_prefix() . 'synchub_entity_map');
        $CI->db->where('entity_type', 'task')->where('local_id', $taskId)->delete(db_prefix() . 'synchub_entity_origin');
        return;
    }

    // Genuine child-created tasks remain source-owned when deleted from Markito.
    // A task created in Markito for the child is the explicit exception and deletes on both.
    if (synchub_instance_role() === 'master' && $sourceCode !== 'MARKITO'
        && !synchub_entity_was_created_on_this_instance('task', $taskId, $syncUuid)) {
        return;
    }

    $sent = true;
    if ((synchub_instance_role() === 'child' && $sourceCode === synchub_instance_code())
        || (synchub_instance_role() === 'master' && $sourceCode !== 'MARKITO')) {
        $sent = synchub_enqueue_task_event($taskId, 'delete');
    }

    // If outbound delete failed, keep metadata so the queued event remains diagnosable/retryable.
    if (!$sent) {
        return;
    }

    $CI->db->where('entity_type', 'task')->where('sync_uuid', $syncUuid)->delete(db_prefix() . 'synchub_entity_map');
    $CI->db->where('entity_type', 'task')->where('local_id', $taskId)->delete(db_prefix() . 'synchub_entity_origin');
}

function synchub_generate_uuid()
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}


/*
|--------------------------------------------------------------------------
| Project Company Badge / Projects Table Company Column
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Milestone Sync - Full CRUD
|--------------------------------------------------------------------------
|
| This Perfex version does not expose milestone CRUD action hooks.
| SyncHub therefore observes the resolved Projects controller methods and
| runs after the native request finishes. No Perfex core file is modified.
|
*/

function synchub_milestone_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db
        ->where('entity_type', 'milestone')
        ->where('sync_uuid', (string)$syncUuid)
        ->get(db_prefix() . 'synchub_entity_map')
        ->row();
}

function synchub_milestone_payload($milestoneId, $milestoneOrigin, $projectOrigin)
{
    $CI = &get_instance();

    $milestone = $CI->db
        ->where('id', (int)$milestoneId)
        ->get(db_prefix() . 'milestones')
        ->row();

    if (!$milestone || !$milestoneOrigin || !$projectOrigin) {
        return null;
    }

    return [
        'milestone_id'                    => (int)$milestone->id,
        'name'                            => (string)$milestone->name,
        'description'                     => isset($milestone->description) ? (string)$milestone->description : '',
        'start_date'                      => isset($milestone->start_date) ? $milestone->start_date : null,
        'due_date'                        => isset($milestone->due_date) ? $milestone->due_date : null,
        'milestone_order'                 => isset($milestone->milestone_order) ? (int)$milestone->milestone_order : 0,
        'color'                           => isset($milestone->color) ? (string)$milestone->color : '',
        'description_visible_to_customer' => isset($milestone->description_visible_to_customer) ? (int)$milestone->description_visible_to_customer : 0,
        'hide_from_customer'              => isset($milestone->hide_from_customer) ? (int)$milestone->hide_from_customer : 0,
        'parent_project_sync_uuid'        => (string)$projectOrigin->sync_uuid,
        'source_company_code'             => (string)$milestoneOrigin->source_company_code,
        'source_branch_id'                => $milestoneOrigin->source_branch_id !== null ? (int)$milestoneOrigin->source_branch_id : null,
        'sync_uuid'                       => (string)$milestoneOrigin->sync_uuid,
    ];
}

function synchub_enqueue_milestone_event($milestoneId, $action = 'create', $originOverride = null)
{
    $CI = &get_instance();
    $milestoneId = (int)$milestoneId;
    $action = strtolower(trim((string)$action));

    if ($milestoneId <= 0 || !in_array($action, ['create', 'update', 'delete'], true)) {
        return false;
    }

    $milestone = null;
    $projectOrigin = null;
    $milestoneOrigin = $originOverride ?: synchub_get_entity_origin('milestone', $milestoneId);

    if ($action !== 'delete') {
        $milestone = $CI->db
            ->where('id', $milestoneId)
            ->get(db_prefix() . 'milestones')
            ->row();

        if (!$milestone || (int)$milestone->project_id <= 0) {
            return false;
        }

        $projectOrigin = synchub_get_entity_origin('project', (int)$milestone->project_id);
        if (!$projectOrigin) {
            return false;
        }

        // Milestones inherit the source company/branch of the parent project.
        if (!$milestoneOrigin) {
            $milestoneOrigin = synchub_register_entity_origin(
                'milestone',
                $milestoneId,
                (string)$projectOrigin->source_company_code,
                $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
            );
        }
    }

    if (!$milestoneOrigin) {
        return false;
    }

    $sourceCode = strtoupper(trim((string)$milestoneOrigin->source_company_code));
    if ($sourceCode === 'MARKITO') {
        return false;
    }

    $map = synchub_milestone_map_by_uuid((string)$milestoneOrigin->sync_uuid);
    $remoteMilestoneId = $map ? (int)$map->remote_id : 0;

    if (synchub_instance_role() === 'master') {
        // A genuinely child-created milestone remains source-owned: Markito delete is local-only.
        // Exception: if Markito created it for the child, delete must propagate to that child.
        if ($action === 'delete') {
            if (!$map || $remoteMilestoneId <= 0
                || !synchub_entity_was_created_on_this_instance('milestone', $milestoneId, (string)$milestoneOrigin->sync_uuid)) {
                return false;
            }
        }

        $destination = synchub_company_by_code($sourceCode);
        if (!$destination) {
            return false;
        }

        if ($action === 'create') {
            // A milestone created in Markito for a child-assigned project has no
            // milestone map yet. The mapped parent project is the authorization
            // boundary for this first outbound create.
            if (!$projectOrigin) {
                return false;
            }
            $projectMap = synchub_map_by_uuid((string)$projectOrigin->sync_uuid);
            if (!$projectMap
                || (int)$projectMap->remote_id <= 0
                || (int)$projectMap->company_id !== (int)$destination->id) {
                return false;
            }
        } elseif (!$map || $remoteMilestoneId <= 0) {
            return false;
        }
    } else {
        // A child publishes only milestones that belong to its own projects.
        if ($sourceCode !== synchub_instance_code()) {
            return false;
        }
        if ($action !== 'create' && (!$map || $remoteMilestoneId <= 0)) {
            return false;
        }
        $destination = synchub_company_by_code((string)synchub_instance_config('master_code'));
    }

    if (!$destination) {
        return false;
    }

    if ($action === 'delete') {
        $payload = [
            'milestone_id'        => $remoteMilestoneId > 0 ? $remoteMilestoneId : $milestoneId,
            'source_company_code' => $sourceCode,
            'source_branch_id'    => $milestoneOrigin->source_branch_id !== null ? (int)$milestoneOrigin->source_branch_id : null,
            'sync_uuid'           => (string)$milestoneOrigin->sync_uuid,
        ];
    } else {
        $payload = synchub_milestone_payload($milestoneId, $milestoneOrigin, $projectOrigin);
        if (!$payload) {
            return false;
        }
    }

    $ok = $CI->db->insert(db_prefix() . 'synchub_queue', [
        'event_id'    => synchub_generate_uuid(),
        'entity_type' => 'milestone',
        'entity_id'   => $milestoneId,
        'action'      => $action,
        'company_id'  => (int)$destination->id,
        'branch_id'   => $milestoneOrigin->source_branch_id !== null ? (int)$milestoneOrigin->source_branch_id : null,
        'payload'     => json_encode($payload),
        'status'      => 'pending',
        'attempts'    => 0,
        'last_error'  => null,
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => null,
    ]);

    if (!$ok) {
        return false;
    }

    return synchub_dispatch_milestone_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_milestone_queue_item($queueId)
{
    $CI = &get_instance();

    $queue = $CI->db
        ->where('id', (int)$queueId)
        ->where('entity_type', 'milestone')
        ->get(db_prefix() . 'synchub_queue')
        ->row();

    if (!$queue) {
        return false;
    }

    $payload = json_decode((string)$queue->payload, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $payload['action'] = (string)$queue->action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody = json_encode($payload);

    $company = $CI->db
        ->where('id', (int)$queue->company_id)
        ->get(db_prefix() . 'synchub_companies')
        ->row();

    if (!$company) {
        return synchub_fail_queue_item((int)$queue->id, 'Milestone destination company not found.');
    }

    $urlBase   = synchub_remote_url((string)$company->code);
    $apiKey    = (string)synchub_instance_config('api_key');
    $apiSecret = (string)synchub_instance_config('api_secret');

    if ($urlBase === '' || $apiKey === '' || $apiSecret === '') {
        return synchub_fail_queue_item((int)$queue->id, 'Milestone remote URL/API credentials are missing.');
    }

    $timestamp = (string)time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $apiSecret);

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'     => 'processing',
        'attempts'   => (int)$queue->attempts + 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $ch = curl_init($urlBase . '/synchub/api/milestone');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $rawBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-SyncHub-Key: ' . $apiKey,
            'X-SyncHub-Timestamp: ' . $timestamp,
            'X-SyncHub-Signature: ' . $signature,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return synchub_fail_queue_item((int)$queue->id, 'Milestone cURL: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id, 'Milestone HTTP ' . $httpCode . ': ' . (string)$response);
    }

    if ((string)$queue->action === 'create') {
        $remoteId = (int)($decoded['local_milestone_id'] ?? 0);
        $syncUuid = trim((string)($payload['sync_uuid'] ?? ''));
        if ($remoteId <= 0 || $syncUuid === '') {
            return synchub_fail_queue_item((int)$queue->id, 'Milestone create response missing mapping data.');
        }

        $map = synchub_milestone_map_by_uuid($syncUuid);
        $data = [
            'local_id'          => (int)$queue->entity_id,
            'remote_id'         => $remoteId,
            'company_id'        => (int)$queue->company_id,
            'branch_id'         => $queue->branch_id !== null ? (int)$queue->branch_id : null,
            'remote_company_id' => null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if ($map) {
            $CI->db->where('id', (int)$map->id)->update(db_prefix() . 'synchub_entity_map', $data);
        } else {
            $data['entity_type'] = 'milestone';
            $data['sync_uuid']   = $syncUuid;
            $data['created_at']  = date('Y-m-d H:i:s');
            $CI->db->insert(db_prefix() . 'synchub_entity_map', $data);
        }
    }

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'     => 'success',
        'last_error' => null,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return true;
}

hooks()->add_action('admin_init', 'synchub_observe_milestone_requests');
function synchub_observe_milestone_requests()
{
    $CI = &get_instance();

    if (!empty($GLOBALS['synchub_receiving_milestone'])) {
        return;
    }

    $controller = strtolower((string)$CI->router->fetch_class());
    $method     = strtolower((string)$CI->router->fetch_method());

    if ($controller !== 'projects') {
        return;
    }

    // SyncHub v8: milestone order is a separate AJAX update surface in Perfex.
    if ($method === 'update_milestones_order' && strtolower((string)$CI->input->method()) === 'post') {
        $order = $CI->input->post('order');
        $ids = [];
        if (is_array($order)) foreach ($order as $row) if (is_array($row) && isset($row[0]) && (int)$row[0] > 0) $ids[(int)$row[0]] = true;
        $milestoneIds = array_keys($ids);
        register_shutdown_function(function () use ($milestoneIds) {
            foreach ($milestoneIds as $milestoneId) if (synchub_get_entity_origin('milestone', (int)$milestoneId)) synchub_enqueue_milestone_event((int)$milestoneId, 'update');
        });
        return;
    }

    // COLOR UPDATE uses Projects::change_milestone_color().
    // Perfex updates the milestone color through a dedicated endpoint, so it
    // never reaches Projects::milestone() and must be observed separately.
    if ($method === 'change_milestone_color' && strtolower((string)$CI->input->method()) === 'post') {
        $milestoneId = (int)$CI->input->post('milestone_id');

        if ($milestoneId <= 0) {
            return;
        }

        $milestone = $CI->db
            ->where('id', $milestoneId)
            ->get(db_prefix() . 'milestones')
            ->row();

        if (!$milestone || (int)$milestone->project_id <= 0) {
            return;
        }

        $projectId = (int)$milestone->project_id;
        $projectOrigin = synchub_get_entity_origin('project', $projectId);
        if (!$projectOrigin) {
            return;
        }

        register_shutdown_function(function () use ($milestoneId, $projectId) {
            $CI = &get_instance();
            $milestone = $CI->db
                ->where('id', $milestoneId)
                ->where('project_id', $projectId)
                ->get(db_prefix() . 'milestones')
                ->row();

            if (!$milestone) {
                return;
            }

            $projectOrigin = synchub_get_entity_origin('project', $projectId);
            if (!$projectOrigin) {
                return;
            }

            if (!synchub_get_entity_origin('milestone', $milestoneId)) {
                synchub_register_entity_origin(
                    'milestone',
                    $milestoneId,
                    (string)$projectOrigin->source_company_code,
                    $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
                );
            }

            synchub_enqueue_milestone_event($milestoneId, 'update');
        });
        return;
    }

    // CREATE / UPDATE use Projects::milestone().
    if ($method === 'milestone' && strtolower((string)$CI->input->method()) === 'post') {
        $postedId  = (int)$CI->input->post('id');
        $projectId = (int)$CI->input->post('project_id');

        if ($projectId <= 0) {
            return;
        }

        $projectOrigin = synchub_get_entity_origin('project', $projectId);
        if (!$projectOrigin) {
            return;
        }

        if ($postedId > 0) {
            // UPDATE: wait until Perfex completes the native update, then publish it.
            register_shutdown_function(function () use ($postedId, $projectId) {
                $CI = &get_instance();
                $milestone = $CI->db->where('id', $postedId)->where('project_id', $projectId)->get(db_prefix() . 'milestones')->row();
                if (!$milestone) {
                    return;
                }

                $projectOrigin = synchub_get_entity_origin('project', $projectId);
                if (!$projectOrigin) {
                    return;
                }

                if (!synchub_get_entity_origin('milestone', $postedId)) {
                    synchub_register_entity_origin(
                        'milestone',
                        $postedId,
                        (string)$projectOrigin->source_company_code,
                        $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
                    );
                }

                synchub_enqueue_milestone_event($postedId, 'update');
            });
            return;
        }

        // CREATE: remember the current highest id and locate the inserted row after request completion.
        $before = $CI->db
            ->select_max('id', 'max_id')
            ->where('project_id', $projectId)
            ->get(db_prefix() . 'milestones')
            ->row();
        $beforeMaxId = $before ? (int)$before->max_id : 0;

        register_shutdown_function(function () use ($projectId, $beforeMaxId) {
            $CI = &get_instance();

            $milestone = $CI->db
                ->where('project_id', $projectId)
                ->where('id >', $beforeMaxId)
                ->order_by('id', 'DESC')
                ->get(db_prefix() . 'milestones')
                ->row();

            if (!$milestone) {
                return;
            }

            $projectOrigin = synchub_get_entity_origin('project', $projectId);
            if (!$projectOrigin) {
                return;
            }

            if (!synchub_get_entity_origin('milestone', (int)$milestone->id)) {
                synchub_register_entity_origin(
                    'milestone',
                    (int)$milestone->id,
                    (string)$projectOrigin->source_company_code,
                    $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
                );
            }

            synchub_enqueue_milestone_event((int)$milestone->id, 'create');
        });
        return;
    }

    // DELETE uses Projects::delete_milestone($project_id, $id).
    if ($method === 'delete_milestone') {
        $projectId  = (int)$CI->uri->rsegment(3);
        $milestoneId = (int)$CI->uri->rsegment(4);

        if ($projectId <= 0 || $milestoneId <= 0) {
            return;
        }

        $milestone = $CI->db->where('id', $milestoneId)->where('project_id', $projectId)->get(db_prefix() . 'milestones')->row();
        if (!$milestone) {
            return;
        }

        $origin = synchub_get_entity_origin('milestone', $milestoneId);
        if (!$origin) {
            $projectOrigin = synchub_get_entity_origin('project', $projectId);
            if ($projectOrigin) {
                $origin = synchub_register_entity_origin(
                    'milestone',
                    $milestoneId,
                    (string)$projectOrigin->source_company_code,
                    $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
                );
            }
        }

        if (!$origin) {
            return;
        }

        // Snapshot the origin because the milestone row will be gone at shutdown.
        $originSnapshot = (object)[
            'source_company_code' => (string)$origin->source_company_code,
            'source_branch_id'    => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
            'sync_uuid'           => (string)$origin->sync_uuid,
        ];

        register_shutdown_function(function () use ($milestoneId, $originSnapshot) {
            $CI = &get_instance();

            // If the native delete did not happen, do not publish anything.
            if ($CI->db->where('id', $milestoneId)->get(db_prefix() . 'milestones')->row()) {
                return;
            }

            $sourceCode = strtoupper(trim((string)$originSnapshot->source_company_code));

            // Genuine child-created milestones remain source-owned when deleted from Markito.
            // A milestone created in Markito for the child is the explicit exception.
            if (synchub_instance_role() === 'master' && $sourceCode !== 'MARKITO'
                && !synchub_entity_was_created_on_this_instance('milestone', $milestoneId, (string)$originSnapshot->sync_uuid)) {
                return;
            }

            $sent = true;
            if ((synchub_instance_role() === 'child' && $sourceCode === synchub_instance_code())
                || (synchub_instance_role() === 'master' && $sourceCode !== 'MARKITO')) {
                $sent = synchub_enqueue_milestone_event($milestoneId, 'delete', $originSnapshot);
            }

            if (!$sent) {
                return;
            }

            $CI->db->where('entity_type', 'milestone')->where('sync_uuid', (string)$originSnapshot->sync_uuid)->delete(db_prefix() . 'synchub_entity_map');
            $CI->db->where('entity_type', 'milestone')->where('local_id', $milestoneId)->delete(db_prefix() . 'synchub_entity_origin');
        });
    }
}


function synchub_project_source_company_name($projectId)
{
    $origin = synchub_get_entity_origin('project', (int)$projectId);
    if (!$origin) {
        return 'Unknown';
    }

    $sourceCode = strtoupper(trim((string)$origin->source_company_code));
    $company = synchub_company_by_code($sourceCode);

    if ($company && !empty($company->name)) {
        return (string)$company->name;
    }

    if ($sourceCode === synchub_instance_code()) {
        $instanceName = trim((string)synchub_instance_config('name'));
        if ($instanceName !== '') {
            return $instanceName;
        }
    }

    return $sourceCode !== '' ? $sourceCode : 'Unknown';
}

hooks()->add_filter('projects_table_columns', 'synchub_projects_table_company_column');
function synchub_projects_table_company_column($columns)
{
    $columns[] = 'Company';
    return $columns;
}

hooks()->add_filter('projects_table_sql_columns', 'synchub_projects_table_company_sql_column');
function synchub_projects_table_company_sql_column($columns)
{
    $projectsTable = db_prefix() . 'projects';
    $originTable   = db_prefix() . 'synchub_entity_origin';

    $columns[] = "(SELECT source_company_code FROM {$originTable} WHERE entity_type='project' AND local_id={$projectsTable}.id LIMIT 1) as synchub_source_company_code";

    return $columns;
}

hooks()->add_filter('projects_table_row_data', 'synchub_projects_table_company_row', 10, 2);
function synchub_projects_table_company_row($row, $aRow)
{
    $projectId = isset($aRow['id']) ? (int)$aRow['id'] : 0;
    $companyName = $projectId > 0 ? synchub_project_source_company_name($projectId) : 'Unknown';
    $sourceCode = isset($aRow['synchub_source_company_code'])
        ? strtoupper(trim((string)$aRow['synchub_source_company_code']))
        : '';

    $row[] = '<span class="label label-info" title="Source company: ' . html_escape($sourceCode) . '">' . html_escape($companyName) . '</span>';

    return $row;
}

hooks()->add_action('app_admin_footer', 'synchub_project_company_selector');
function synchub_project_company_selector()
{
    if (synchub_instance_role() !== 'master') return;

    $CI = &get_instance();
    if (strtolower((string)$CI->uri->segment(1)) !== 'admin'
        || strtolower((string)$CI->uri->segment(2)) !== 'projects'
        || strtolower((string)$CI->uri->segment(3)) !== 'project'
        || (int)$CI->uri->segment(4) > 0) {
        return;
    }

    $options = [];
    $instanceCode = synchub_instance_code();
    $instanceName = trim((string)synchub_instance_config('name'));
    $options[$instanceCode] = $instanceName !== '' ? $instanceName : $instanceCode;

    $companies = $CI->db
        ->where('active', 1)
        ->where('type', 'child')
        ->order_by('name', 'ASC')
        ->get(db_prefix() . 'synchub_companies')
        ->result();

    foreach ($companies as $company) {
        $code = strtoupper(trim((string)$company->code));
        if ($code === '' || $code === $instanceCode) continue;
        $options[$code] = !empty($company->name) ? (string)$company->name : $code;
    }

    $optionsJson = json_encode($options, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    ?>
    <script>
    (function($){
        $(function(){
            var $form = $('#project_form');
            if (!$form.length || $('#synchub-project-company-field').length) return;

            var options = <?= $optionsJson ?>;
            var $select = $('<select>', {
                name: 'synchub_project_company',
                id: 'synchub_project_company',
                class: 'selectpicker',
                'data-width': '100%'
            });

            $.each(options, function(code, name){
                $select.append($('<option>', {value: code, text: name}));
            });

            var $group = $('<div>', {
                id: 'synchub-project-company-field',
                class: 'form-group select-placeholder'
            });
            $group.append($('<label>', {
                for: 'synchub_project_company',
                class: 'control-label',
                text: 'Project Company'
            }));
            $group.append($select);

            var $nameGroup = $form.find('input[name="name"]').first().closest('.form-group');
            if ($nameGroup.length) $nameGroup.after($group);
            else $('#tab_project').prepend($group);

            if ($.fn.selectpicker) $select.selectpicker();
        });
    })(window.jQuery);
    </script>
    <?php
}

hooks()->add_action('app_admin_footer', 'synchub_project_source_badge');
function synchub_project_source_badge()
{
    $CI = &get_instance();

    if (strtolower((string)$CI->uri->segment(1)) !== 'admin'
        || strtolower((string)$CI->uri->segment(2)) !== 'projects'
        || strtolower((string)$CI->uri->segment(3)) !== 'view') {
        return;
    }

    $projectId = (int)$CI->uri->segment(4);
    if ($projectId <= 0) {
        return;
    }

    $origin = synchub_get_entity_origin('project', $projectId);
    if (!$origin) {
        return;
    }

    $companyName = synchub_project_source_company_name($projectId);
    $sourceCode = strtoupper(trim((string)$origin->source_company_code));

    $companyNameJs = json_encode($companyName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $sourceCodeJs = json_encode($sourceCode, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    echo '<script>\n';
    echo '$(function(){\n';
    echo '  if($("#synchub-project-source-badge").length){ return; }\n';
    echo '  var companyName=' . $companyNameJs . ';\n';
    echo '  var sourceCode=' . $sourceCodeJs . ';\n';
    echo '  var badge=$("<span>",{id:"synchub-project-source-badge",class:"label label-info tw-ml-2",text:"Company: "+companyName,title:"Source company: "+sourceCode});\n';
    echo '  var target=$("#project_view_name");\n';
    echo '  if(target.length){ target.after(badge); }\n';
    echo '});\n';
    echo '</script>\n';
}



/*
|--------------------------------------------------------------------------
| Project Notes Sync
|--------------------------------------------------------------------------
| Perfex project notes are private per staff member (project_id + staff_id).
| There are no native CRUD hooks for Projects::save_note(), so SyncHub
| observes the native POST request and publishes the saved row at shutdown.
*/

function synchub_project_note_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db
        ->where('entity_type', 'project_note')
        ->where('sync_uuid', (string)$syncUuid)
        ->get(db_prefix() . 'synchub_entity_map')
        ->row();
}

function synchub_project_note_payload($noteId, $noteOrigin, $projectOrigin)
{
    $CI = &get_instance();

    $note = $CI->db
        ->where('id', (int)$noteId)
        ->get(db_prefix() . 'project_notes')
        ->row();

    if (!$note || !$noteOrigin || !$projectOrigin) {
        return null;
    }

    $staff = $CI->db
        ->where('staffid', (int)$note->staff_id)
        ->get(db_prefix() . 'staff')
        ->row();

    if (!$staff || empty($staff->email)) {
        return null;
    }

    return [
        'note_id'                  => (int)$note->id,
        'content'                  => (string)$note->content,
        'project_sync_uuid'        => (string)$projectOrigin->sync_uuid,
        'source_staff_id'          => (int)$note->staff_id,
        'source_staff_email'       => strtolower(trim((string)$staff->email)),
        'source_company_code'      => (string)$noteOrigin->source_company_code,
        'source_branch_id'         => $noteOrigin->source_branch_id !== null ? (int)$noteOrigin->source_branch_id : null,
        'sync_uuid'                => (string)$noteOrigin->sync_uuid,
    ];
}

function synchub_enqueue_project_note_event($noteId, $action = 'create')
{
    $CI = &get_instance();
    $noteId = (int)$noteId;
    $action = strtolower(trim((string)$action));

    if ($noteId <= 0 || !in_array($action, ['create', 'update'], true)) {
        return false;
    }

    $note = $CI->db
        ->where('id', $noteId)
        ->get(db_prefix() . 'project_notes')
        ->row();

    if (!$note || (int)$note->project_id <= 0) {
        return false;
    }

    $projectOrigin = synchub_get_entity_origin('project', (int)$note->project_id);
    if (!$projectOrigin) {
        return false;
    }

    $noteOrigin = synchub_get_entity_origin('project_note', $noteId);
    if (!$noteOrigin) {
        $noteOrigin = synchub_register_entity_origin(
            'project_note',
            $noteId,
            (string)$projectOrigin->source_company_code,
            $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
        );
    }

    if (!$noteOrigin) {
        return false;
    }

    $sourceCode  = strtoupper(trim((string)$projectOrigin->source_company_code));
    $instanceCode = synchub_instance_code();

    // Notes under Markito-origin projects never leave Markito.
    if ($sourceCode === 'MARKITO') {
        return false;
    }

    if (synchub_instance_role() === 'master') {
        $destination = synchub_company_by_code($sourceCode);
    } else {
        // A child only publishes notes belonging to projects that belong to itself.
        if ($sourceCode !== $instanceCode) {
            return false;
        }
        $destination = synchub_company_by_code((string)synchub_instance_config('master_code'));
    }

    if (!$destination) {
        return false;
    }

    $payload = synchub_project_note_payload($noteId, $noteOrigin, $projectOrigin);
    if (!$payload) {
        return false;
    }

    $ok = $CI->db->insert(db_prefix() . 'synchub_queue', [
        'event_id'    => synchub_generate_uuid(),
        'entity_type' => 'project_note',
        'entity_id'   => $noteId,
        'action'      => $action,
        'company_id'  => (int)$destination->id,
        'branch_id'   => $noteOrigin->source_branch_id !== null ? (int)$noteOrigin->source_branch_id : null,
        'payload'     => json_encode($payload),
        'status'      => 'pending',
        'attempts'    => 0,
        'last_error'  => null,
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => null,
    ]);

    if (!$ok) {
        return false;
    }

    return synchub_dispatch_project_note_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_project_note_queue_item($queueId)
{
    $CI = &get_instance();

    $queue = $CI->db
        ->where('id', (int)$queueId)
        ->where('entity_type', 'project_note')
        ->get(db_prefix() . 'synchub_queue')
        ->row();

    if (!$queue) {
        return false;
    }

    $payload = json_decode((string)$queue->payload, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $payload['action'] = (string)$queue->action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody = json_encode($payload);

    $company = $CI->db
        ->where('id', (int)$queue->company_id)
        ->get(db_prefix() . 'synchub_companies')
        ->row();

    if (!$company) {
        return synchub_fail_queue_item((int)$queue->id, 'Project note destination company not found.');
    }

    $urlBase   = synchub_remote_url((string)$company->code);
    $apiKey    = (string)synchub_instance_config('api_key');
    $apiSecret = (string)synchub_instance_config('api_secret');

    if ($urlBase === '' || $apiKey === '' || $apiSecret === '') {
        return synchub_fail_queue_item((int)$queue->id, 'Project note remote URL/API credentials are missing.');
    }

    $timestamp = (string)time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $apiSecret);

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'     => 'processing',
        'attempts'   => (int)$queue->attempts + 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $ch = curl_init($urlBase . '/synchub/api/project_note');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $rawBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-SyncHub-Key: ' . $apiKey,
            'X-SyncHub-Timestamp: ' . $timestamp,
            'X-SyncHub-Signature: ' . $signature,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response  = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return synchub_fail_queue_item((int)$queue->id, 'Project note cURL: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id, 'Project note HTTP ' . $httpCode . ': ' . (string)$response);
    }

    $remoteId = (int)($decoded['local_note_id'] ?? 0);
    $syncUuid = (string)($payload['sync_uuid'] ?? '');

    if ($remoteId <= 0 || $syncUuid === '') {
        return synchub_fail_queue_item((int)$queue->id, 'Project note response missing mapping data.');
    }

    $map = synchub_project_note_map_by_uuid($syncUuid);
    $mapData = [
        'local_id'          => (int)$queue->entity_id,
        'remote_id'         => $remoteId,
        'company_id'        => (int)$queue->company_id,
        'branch_id'         => $queue->branch_id !== null ? (int)$queue->branch_id : null,
        'remote_company_id' => null,
        'updated_at'        => date('Y-m-d H:i:s'),
    ];

    if ($map) {
        $CI->db->where('id', (int)$map->id)->update(db_prefix() . 'synchub_entity_map', $mapData);
    } else {
        $mapData['entity_type'] = 'project_note';
        $mapData['sync_uuid']   = $syncUuid;
        $mapData['created_at']  = date('Y-m-d H:i:s');
        $CI->db->insert(db_prefix() . 'synchub_entity_map', $mapData);
    }

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'     => 'success',
        'last_error' => null,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return true;
}

hooks()->add_action('admin_init', 'synchub_observe_project_note_requests');
function synchub_observe_project_note_requests()
{
    $CI = &get_instance();

    if (!empty($GLOBALS['synchub_receiving_project_note'])) {
        return;
    }

    $controller = strtolower((string)$CI->router->fetch_class());
    $method     = strtolower((string)$CI->router->fetch_method());

    if ($controller !== 'projects' || $method !== 'save_note' || strtolower((string)$CI->input->method()) !== 'post') {
        return;
    }

    $projectId = (int)$CI->uri->rsegment(3);
    $staffId   = (int)get_staff_user_id();

    if ($projectId <= 0 || $staffId <= 0) {
        return;
    }

    $projectOrigin = synchub_get_entity_origin('project', $projectId);
    if (!$projectOrigin) {
        return;
    }

    $existing = $CI->db
        ->where('project_id', $projectId)
        ->where('staff_id', $staffId)
        ->get(db_prefix() . 'project_notes')
        ->row();

    $existingId = $existing ? (int)$existing->id : 0;

    register_shutdown_function(function () use ($projectId, $staffId, $existingId) {
        $CI = &get_instance();

        $note = $CI->db
            ->where('project_id', $projectId)
            ->where('staff_id', $staffId)
            ->get(db_prefix() . 'project_notes')
            ->row();

        if (!$note) {
            return;
        }

        $projectOrigin = synchub_get_entity_origin('project', $projectId);
        if (!$projectOrigin) {
            return;
        }

        $noteOrigin = synchub_get_entity_origin('project_note', (int)$note->id);
        if (!$noteOrigin) {
            $noteOrigin = synchub_register_entity_origin(
                'project_note',
                (int)$note->id,
                (string)$projectOrigin->source_company_code,
                $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
            );
        }

        if (!$noteOrigin) {
            return;
        }

        $map = synchub_project_note_map_by_uuid((string)$noteOrigin->sync_uuid);
        $action = ($existingId > 0 || $map) ? 'update' : 'create';
        synchub_enqueue_project_note_event((int)$note->id, $action);
    });
}

/*
|--------------------------------------------------------------------------
| Staff Mapping Helpers
|--------------------------------------------------------------------------
*/

function synchub_staff_by_email($email)
{
    $CI = &get_instance();
    $email = strtolower(trim((string)$email));
    if ($email === '') return null;

    return $CI->db
        ->where("LOWER(email) = " . $CI->db->escape($email), null, false)
        ->get(db_prefix() . 'staff')
        ->row();
}

function synchub_staff_map($remoteCompanyCode, $localStaffId = null, $remoteStaffId = null)
{
    $CI = &get_instance();
    $CI->db->where('remote_company_code', strtoupper(trim((string)$remoteCompanyCode)));
    if ($localStaffId !== null) $CI->db->where('local_staff_id', (int)$localStaffId);
    if ($remoteStaffId !== null) $CI->db->where('remote_staff_id', (int)$remoteStaffId);
    return $CI->db->get(db_prefix() . 'synchub_staff_map')->row();
}

function synchub_upsert_staff_map($remoteCompanyCode, $localStaffId, $remoteStaffId, $email)
{
    $CI = &get_instance();
    $remoteCompanyCode = strtoupper(trim((string)$remoteCompanyCode));
    $localStaffId = (int)$localStaffId;
    $remoteStaffId = (int)$remoteStaffId;
    $email = strtolower(trim((string)$email));
    if ($remoteCompanyCode === '' || $localStaffId <= 0 || $remoteStaffId <= 0 || $email === '') return false;

    $existing = synchub_staff_map($remoteCompanyCode, $localStaffId, null);
    $data = [
        'remote_company_code' => $remoteCompanyCode,
        'local_staff_id' => $localStaffId,
        'remote_staff_id' => $remoteStaffId,
        'staff_email' => $email,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($existing) {
        return $CI->db->where('id', (int)$existing->id)->update(db_prefix() . 'synchub_staff_map', $data);
    }

    $existingRemote = synchub_staff_map($remoteCompanyCode, null, $remoteStaffId);
    if ($existingRemote) {
        return $CI->db->where('id', (int)$existingRemote->id)->update(db_prefix() . 'synchub_staff_map', $data);
    }

    $data['created_at'] = date('Y-m-d H:i:s');
    return $CI->db->insert(db_prefix() . 'synchub_staff_map', $data);
}

function synchub_resolve_local_staff_id($remoteCompanyCode, $remoteStaffId, $email = '')
{
    $map = synchub_staff_map($remoteCompanyCode, null, (int)$remoteStaffId);
    if ($map) return (int)$map->local_staff_id;

    $staff = synchub_staff_by_email($email);
    if (!$staff) return 0;

    synchub_upsert_staff_map($remoteCompanyCode, (int)$staff->staffid, (int)$remoteStaffId, (string)$staff->email);
    return (int)$staff->staffid;
}

/*
|--------------------------------------------------------------------------
| Timesheet Sync - Full CRUD
|--------------------------------------------------------------------------
*/

function synchub_timesheet_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db->where('entity_type', 'timesheet')
        ->where('sync_uuid', (string)$syncUuid)
        ->get(db_prefix() . 'synchub_entity_map')->row();
}

function synchub_timesheet_payload($timerId, $origin)
{
    $CI = &get_instance();
    $timer = $CI->db->where('id', (int)$timerId)->get(db_prefix() . 'taskstimers')->row();
    if (!$timer || !$origin) return null;

    $task = $CI->db->where('id', (int)$timer->task_id)->get(db_prefix() . 'tasks')->row();
    if (!$task || $task->rel_type !== 'project') return null;

    $taskOrigin = synchub_get_entity_origin('task', (int)$task->id);
    $projectOrigin = synchub_get_entity_origin('project', (int)$task->rel_id);
    $staff = $CI->db->where('staffid', (int)$timer->staff_id)->get(db_prefix() . 'staff')->row();
    if (!$taskOrigin || !$projectOrigin || !$staff) return null;

    return [
        'timesheet_id'           => (int)$timer->id,
        'parent_task_sync_uuid'  => (string)$taskOrigin->sync_uuid,
        'project_sync_uuid'      => (string)$projectOrigin->sync_uuid,
        'source_staff_id'        => (int)$timer->staff_id,
        'staff_email'            => strtolower(trim((string)$staff->email)),
        'start_time'             => (int)$timer->start_time,
        'end_time'               => $timer->end_time !== null ? (int)$timer->end_time : null,
        'hourly_rate'            => isset($timer->hourly_rate) ? (float)$timer->hourly_rate : 0,
        'note'                   => isset($timer->note) ? (string)$timer->note : null,
        'source_company_code'    => (string)$origin->source_company_code,
        'source_branch_id'       => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'sync_uuid'              => (string)$origin->sync_uuid,
        'sender_instance_code'   => synchub_instance_code(),
    ];
}

function synchub_enqueue_timesheet_event($timerId, $action = 'create', $originOverride = null)
{
    $CI = &get_instance();
    $timerId = (int)$timerId;
    $action = strtolower(trim((string)$action));
    if ($timerId <= 0 || !in_array($action, ['create','update','delete'], true)) return false;

    $origin = $originOverride ?: synchub_get_entity_origin('timesheet', $timerId);
    $timer = null;
    $projectOrigin = null;

    if ($action !== 'delete') {
        $timer = $CI->db->where('id', $timerId)->get(db_prefix() . 'taskstimers')->row();
        if (!$timer) return false;
        $task = $CI->db->where('id', (int)$timer->task_id)->get(db_prefix() . 'tasks')->row();
        if (!$task || $task->rel_type !== 'project') return false;
        $projectOrigin = synchub_get_entity_origin('project', (int)$task->rel_id);
        if (!$projectOrigin) return false;

        if (!$origin) {
            $origin = synchub_register_entity_origin(
                'timesheet',
                $timerId,
                (string)$projectOrigin->source_company_code,
                $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
            );
        }
    }

    if (!$origin) return false;
    $sourceCode = strtoupper((string)$origin->source_company_code);
    $instanceCode = synchub_instance_code();
    if ($sourceCode === 'MARKITO') return false;

    $map = synchub_timesheet_map_by_uuid((string)$origin->sync_uuid);
    if ($action === 'create' && $map) return true;
    if (($action === 'update' || $action === 'delete') && !$map) return false;

    if (synchub_instance_role() === 'master') {
        $destination = synchub_company_by_code($sourceCode);
    } else {
        if ($sourceCode !== $instanceCode) return false;
        $destination = synchub_company_by_code((string)synchub_instance_config('master_code'));
    }
    if (!$destination) return false;

    if ($action === 'delete') {
        $payload = [
            'timesheet_id' => $map && (int)$map->remote_id > 0 ? (int)$map->remote_id : $timerId,
            'source_company_code' => $sourceCode,
            'source_branch_id' => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
            'sync_uuid' => (string)$origin->sync_uuid,
            'sender_instance_code' => $instanceCode,
        ];
    } else {
        $payload = synchub_timesheet_payload($timerId, $origin);
        if (!$payload) return false;
    }

    if (!$CI->db->insert(db_prefix() . 'synchub_queue', [
        'event_id' => synchub_generate_uuid(), 'entity_type' => 'timesheet', 'entity_id' => $timerId,
        'action' => $action, 'company_id' => (int)$destination->id, 'branch_id' => null,
        'payload' => json_encode($payload), 'status' => 'pending', 'attempts' => 0, 'last_error' => null,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null,
    ])) return false;

    return synchub_dispatch_timesheet_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_timesheet_queue_item($queueId)
{
    $CI = &get_instance();
    $queue = $CI->db->where('id', (int)$queueId)->where('entity_type', 'timesheet')
        ->get(db_prefix() . 'synchub_queue')->row();
    if (!$queue) return false;

    $payload = json_decode((string)$queue->payload, true);
    if (!is_array($payload)) $payload = [];
    $payload['action'] = (string)$queue->action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody = json_encode($payload);

    $company = $CI->db->where('id', (int)$queue->company_id)->get(db_prefix() . 'synchub_companies')->row();
    if (!$company) return synchub_fail_queue_item((int)$queue->id, 'Timesheet destination company not found.');
    $urlBase = synchub_remote_url((string)$company->code);
    $apiKey = (string)synchub_instance_config('api_key');
    $apiSecret = (string)synchub_instance_config('api_secret');
    if ($urlBase === '' || $apiKey === '' || $apiSecret === '') {
        return synchub_fail_queue_item((int)$queue->id, 'Timesheet remote URL/API credentials are missing.');
    }

    $timestamp = (string)time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $apiSecret);
    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'=>'processing','attempts'=>(int)$queue->attempts+1,'updated_at'=>date('Y-m-d H:i:s')
    ]);

    $ch = curl_init($urlBase . '/synchub/api/timesheet');
    curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$rawBody,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-SyncHub-Key: '.$apiKey,
            'X-SyncHub-Timestamp: '.$timestamp,'X-SyncHub-Signature: '.$signature],
        CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5]);
    $response = curl_exec($ch); $httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $curlError=curl_error($ch); curl_close($ch);
    if ($curlError !== '') return synchub_fail_queue_item((int)$queue->id, 'Timesheet cURL: '.$curlError);
    $decoded=json_decode((string)$response,true);
    if ($httpCode<200 || $httpCode>=300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id, 'Timesheet HTTP '.$httpCode.': '.(string)$response);
    }

    $syncUuid=(string)($payload['sync_uuid'] ?? '');
    if ((string)$queue->action === 'create') {
        $remoteId=(int)($decoded['local_timesheet_id'] ?? 0);
        if ($remoteId<=0 || $syncUuid==='') return synchub_fail_queue_item((int)$queue->id,'Timesheet create response missing mapping data.');
        $mapData=['local_id'=>(int)$queue->entity_id,'remote_id'=>$remoteId,'company_id'=>(int)$queue->company_id,
            'branch_id'=>null,'remote_company_id'=>null,'updated_at'=>date('Y-m-d H:i:s')];
        $map=synchub_timesheet_map_by_uuid($syncUuid);
        if ($map) $CI->db->where('id',(int)$map->id)->update(db_prefix().'synchub_entity_map',$mapData);
        else { $mapData['entity_type']='timesheet'; $mapData['sync_uuid']=$syncUuid; $mapData['created_at']=date('Y-m-d H:i:s');
            $CI->db->insert(db_prefix().'synchub_entity_map',$mapData); }
    } elseif ($syncUuid !== '') {
        $map=synchub_timesheet_map_by_uuid($syncUuid);
        if ($map) $CI->db->where('id',(int)$map->id)->update(db_prefix().'synchub_entity_map',[
            'updated_at'=>date('Y-m-d H:i:s')
        ]);
    }

    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
        'status'=>'success','last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')]);
    return true;
}

hooks()->add_action('task_timer_started', 'synchub_after_task_timer_started');
function synchub_after_task_timer_started($data)
{
    if (!empty($GLOBALS['synchub_receiving_timesheet'])) return;
    $timerId = is_array($data) ? (int)($data['timer_id'] ?? 0) : 0;
    if ($timerId > 0) synchub_enqueue_timesheet_event($timerId, 'create');
}

hooks()->add_action('task_timer_deleted', 'synchub_task_timer_deleted');
function synchub_task_timer_deleted($timesheet)
{
    if (!is_object($timesheet) || empty($timesheet->id)) return;
    $CI = &get_instance();
    $timerId = (int)$timesheet->id;
    $origin = synchub_get_entity_origin('timesheet', $timerId);
    if (!$origin) return;

    $syncUuid = (string)$origin->sync_uuid;
    $sourceCode = strtoupper((string)$origin->source_company_code);

    if (!empty($GLOBALS['synchub_receiving_timesheet'])) {
        $CI->db->where('entity_type','timesheet')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
        $CI->db->where('entity_type','timesheet')->where('local_id',$timerId)->delete(db_prefix().'synchub_entity_origin');
        return;
    }

    // SyncHub v9.4:
    // source_company_code is the operational target and is not sufficient by itself
    // to decide delete ownership.  A master-side outbound CREATE event proves that
    // Markito created this timesheet for the child, so its delete must propagate.
    // A genuinely child-created timesheet mirrored into Markito remains local-only.
    $sent = true;

    if (synchub_instance_role() === 'master') {
        if ($sourceCode === 'MARKITO') {
            // Pure Markito-local timesheets never leave Markito.
            $sent = true;
        } elseif (synchub_entity_was_created_on_this_instance('timesheet', $timerId, $syncUuid)) {
            $sent = synchub_enqueue_timesheet_event($timerId, 'delete', $origin);
        } else {
            // Genuine child-origin mirror deleted in Markito: local-only by policy.
            return;
        }
    } elseif (synchub_instance_role() === 'child' && $sourceCode === synchub_instance_code()) {
        $sent = synchub_enqueue_timesheet_event($timerId, 'delete', $origin);
    }

    if (!$sent) return;

    $CI->db->where('entity_type','timesheet')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
    $CI->db->where('entity_type','timesheet')->where('local_id',$timerId)->delete(db_prefix().'synchub_entity_origin');
}

hooks()->add_action('admin_init', 'synchub_observe_timesheet_crud');
function synchub_observe_timesheet_crud()
{
    $CI = &get_instance();
    if (!empty($GLOBALS['synchub_receiving_timesheet'])) return;
    if (strtolower((string)$CI->input->method()) !== 'post') return;

    $class = strtolower((string)$CI->router->fetch_class());
    $method = strtolower((string)$CI->router->fetch_method());

    $isManual = ($class === 'projects' && $method === 'timesheet')
        || ($class === 'tasks' && in_array($method, ['log_time','update_timesheet'], true));

    if ($isManual) {
        $timerId = (int)$CI->input->post('timer_id');
        if ($timerId > 0) {
            register_shutdown_function(function() use ($timerId) {
                $CI = &get_instance();
                if ($CI->db->where('id',$timerId)->get(db_prefix().'taskstimers')->row()) {
                    synchub_enqueue_timesheet_event($timerId, 'update');
                }
            });
            return;
        }

        $taskId=(int)$CI->input->post('timesheet_task_id');
        $staffId=(int)$CI->input->post('timesheet_staff_id');
        if ($staffId<=0) $staffId=(int)get_staff_user_id();
        if ($taskId<=0 || $staffId<=0) return;
        $row=$CI->db->select_max('id','max_id')->where('task_id',$taskId)->where('staff_id',$staffId)
            ->get(db_prefix().'taskstimers')->row();
        $beforeId=$row ? (int)$row->max_id : 0;

        register_shutdown_function(function() use ($taskId,$staffId,$beforeId) {
            $CI=&get_instance();
            $timer=$CI->db->where('task_id',$taskId)->where('staff_id',$staffId)->where('id >',$beforeId)
                ->order_by('id','DESC')->get(db_prefix().'taskstimers')->row();
            if ($timer) synchub_enqueue_timesheet_event((int)$timer->id,'create');
        });
        return;
    }

    // Start timer is handled by task_timer_started. Stopping a timer has no native
    // after-stop hook, so dispatch the final state after the request completes.
    if ($class === 'tasks' && $method === 'timer_tracking') {
        $timerId = (int)$CI->input->post('timer_id');
        if ($timerId > 0) {
            register_shutdown_function(function() use ($timerId) {
                $CI=&get_instance();
                if ($CI->db->where('id',$timerId)->get(db_prefix().'taskstimers')->row()) {
                    synchub_enqueue_timesheet_event($timerId,'update');
                }
            });
        }
    }
}



/* =============================================================
 * Step 16 - Project Files CREATE sync (binary + metadata)
 * Transport note: multipart project-file requests are sent through
 * the already CSRF-exempt /synchub/api/task URI and routed by the
 * synchub_entity_type field before normal task handling.
 * ============================================================= */

function synchub_project_file_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db->where('entity_type','project_file')->where('sync_uuid',(string)$syncUuid)
        ->get(db_prefix().'synchub_entity_map')->row();
}

function synchub_project_file_payload($fileId, $origin, $projectOrigin)
{
    $CI = &get_instance();
    $file = $CI->db->where('id',(int)$fileId)->get(db_prefix().'project_files')->row();
    if (!$file || !$origin || !$projectOrigin) return null;

    $staffEmail = '';
    if (!empty($file->staffid)) {
        $staff = $CI->db->select('email')->where('staffid',(int)$file->staffid)->get(db_prefix().'staff')->row();
        if ($staff) $staffEmail = (string)$staff->email;
    }

    return [
        'project_file_id'          => (int)$file->id,
        'parent_project_sync_uuid' => (string)$projectOrigin->sync_uuid,
        'source_company_code'      => strtoupper((string)$origin->source_company_code),
        'source_branch_id'         => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'sync_uuid'                => (string)$origin->sync_uuid,
        'sender_instance_code'     => synchub_instance_code(),
        'original_file_name'       => !empty($file->original_file_name) ? (string)$file->original_file_name : (string)$file->file_name,
        'filetype'                 => (string)$file->filetype,
        'subject'                  => isset($file->subject) ? (string)$file->subject : '',
        'visible_to_customer'      => !empty($file->visible_to_customer) ? 1 : 0,
        'staff_email'              => $staffEmail,
        'dateadded'                => !empty($file->dateadded) ? (string)$file->dateadded : date('Y-m-d H:i:s'),
    ];
}

function synchub_project_file_disk_path($fileId)
{
    $CI = &get_instance();
    $file = $CI->db->where('id',(int)$fileId)->get(db_prefix().'project_files')->row();
    if (!$file || !empty($file->external)) return '';
    $path = get_upload_path_by_type('project').(int)$file->project_id.'/'.(string)$file->file_name;
    return is_file($path) ? $path : '';
}

function synchub_enqueue_project_file_event($fileId, $action='create')
{
    $CI = &get_instance();
    $fileId=(int)$fileId;
    if ($fileId<=0 || strtolower((string)$action)!=='create') return false;

    $file=$CI->db->where('id',$fileId)->get(db_prefix().'project_files')->row();
    if (!$file || !empty($file->external)) return false;
    $projectOrigin=synchub_get_entity_origin('project',(int)$file->project_id);
    if (!$projectOrigin) return false;

    $origin=synchub_get_entity_origin('project_file',$fileId);
    if (!$origin) {
        $origin=synchub_register_entity_origin('project_file',$fileId,(string)$projectOrigin->source_company_code,
            $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null);
    }
    if (!$origin) return false;

    $sourceCode=strtoupper((string)$origin->source_company_code);
    if ($sourceCode==='MARKITO') return false;
    if (synchub_project_file_map_by_uuid((string)$origin->sync_uuid)) return true;

    if (synchub_instance_role()==='master') {
        $destination=synchub_company_by_code($sourceCode);
    } else {
        if ($sourceCode!==synchub_instance_code()) return false;
        $destination=synchub_company_by_code((string)synchub_instance_config('master_code'));
    }
    if (!$destination) return false;

    $payload=synchub_project_file_payload($fileId,$origin,$projectOrigin);
    if (!$payload || synchub_project_file_disk_path($fileId)==='') return false;

    if (!$CI->db->insert(db_prefix().'synchub_queue',[
        'event_id'=>synchub_generate_uuid(),'entity_type'=>'project_file','entity_id'=>$fileId,'action'=>'create',
        'company_id'=>(int)$destination->id,'branch_id'=>$origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'payload'=>json_encode($payload),'status'=>'pending','attempts'=>0,'last_error'=>null,
        'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
    ])) return false;

    return synchub_dispatch_project_file_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_project_file_queue_item($queueId)
{
    $CI=&get_instance();
    $queue=$CI->db->where('id',(int)$queueId)->where('entity_type','project_file')->get(db_prefix().'synchub_queue')->row();
    if (!$queue) return false;

    $payload=json_decode((string)$queue->payload,true);
    if (!is_array($payload)) $payload=[];
    $action=strtolower((string)($queue->action ?: ($payload['action'] ?? 'create')));
    if (!in_array($action,['create','delete'],true)) return synchub_fail_queue_item((int)$queue->id,'Unsupported project file action: '.$action);
    $payload['action']=$action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody=json_encode($payload);

    $company=$CI->db->where('id',(int)$queue->company_id)->get(db_prefix().'synchub_companies')->row();
    if (!$company) return synchub_fail_queue_item((int)$queue->id,'Project file destination company not found.');
    $urlBase=synchub_remote_url((string)$company->code);
    $apiKey=(string)synchub_instance_config('api_key');
    $apiSecret=(string)synchub_instance_config('api_secret');
    if ($urlBase==='' || $apiKey==='' || $apiSecret==='') return synchub_fail_queue_item((int)$queue->id,'Project file remote URL/API credentials are missing.');

    $timestamp=(string)time();
    $signature=hash_hmac('sha256',$timestamp.'.'.$rawBody,$apiSecret);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
        'status'=>'processing','attempts'=>(int)$queue->attempts+1,'updated_at'=>date('Y-m-d H:i:s')
    ]);

    $post=['synchub_entity_type'=>'project_file','metadata'=>$rawBody];
    if ($action==='create') {
        $diskPath=synchub_project_file_disk_path((int)$queue->entity_id);
        if ($diskPath==='') return synchub_fail_queue_item((int)$queue->id,'Project file physical file is missing.');
        $mime=(string)($payload['filetype'] ?? 'application/octet-stream');
        $uploadName=(string)($payload['original_file_name'] ?? basename($diskPath));
        $post['file']=new CURLFile($diskPath,$mime,$uploadName);
    }

    $ch=curl_init($urlBase.'/synchub/api/task');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['X-SyncHub-Key: '.$apiKey,'X-SyncHub-Timestamp: '.$timestamp,'X-SyncHub-Signature: '.$signature],
        CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>5]);
    $response=curl_exec($ch); $httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $curlError=curl_error($ch); curl_close($ch);
    if ($curlError!=='') return synchub_fail_queue_item((int)$queue->id,'Project file cURL: '.$curlError);
    $decoded=json_decode((string)$response,true);
    if ($httpCode<200 || $httpCode>=300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id,'Project file HTTP '.$httpCode.': '.(string)$response);
    }

    $syncUuid=(string)($payload['sync_uuid'] ?? '');
    if ($syncUuid==='') return synchub_fail_queue_item((int)$queue->id,'Project file response missing sync UUID.');

    if ($action==='delete') {
        // Remote delete succeeded; clean local sync metadata. The native Perfex
        // remove_file() call that triggered this hook will remove the local row/file.
        $CI->db->where('entity_type','project_file')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
        $CI->db->where('entity_type','project_file')->where('local_id',(int)$queue->entity_id)->delete(db_prefix().'synchub_entity_origin');
        $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
            'status'=>'success','last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')
        ]);
        return true;
    }

    $remoteId=(int)($decoded['local_file_id'] ?? 0);
    if ($remoteId<=0) return synchub_fail_queue_item((int)$queue->id,'Project file create response missing mapping data.');

    $mapData=['local_id'=>(int)$queue->entity_id,'remote_id'=>$remoteId,'company_id'=>(int)$queue->company_id,
        'branch_id'=>$queue->branch_id!==null ? (int)$queue->branch_id : null,'remote_company_id'=>null,
        'updated_at'=>date('Y-m-d H:i:s')];
    $map=synchub_project_file_map_by_uuid($syncUuid);
    if ($map) $CI->db->where('id',(int)$map->id)->update(db_prefix().'synchub_entity_map',$mapData);
    else {
        $mapData['entity_type']='project_file'; $mapData['sync_uuid']=$syncUuid; $mapData['created_at']=date('Y-m-d H:i:s');
        $CI->db->insert(db_prefix().'synchub_entity_map',$mapData);
    }
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
        'status'=>'success','last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')
    ]);
    return true;
}

hooks()->add_action('admin_init','synchub_observe_project_file_upload');
function synchub_observe_project_file_upload()
{
    $CI=&get_instance();
    if (!empty($GLOBALS['synchub_receiving_project_file'])) return;
    if (strtolower((string)$CI->router->fetch_class())!=='projects' || strtolower((string)$CI->router->fetch_method())!=='upload_file') return;
    if (strtolower((string)$CI->input->method())!=='post') return;
    $projectId=(int)$CI->uri->rsegment(3);
    if ($projectId<=0) return;
    $origin=synchub_get_entity_origin('project',$projectId);
    if (!$origin || strtoupper((string)$origin->source_company_code)==='MARKITO') return;

    $row=$CI->db->select_max('id','max_id')->where('project_id',$projectId)->get(db_prefix().'project_files')->row();
    $beforeId=$row ? (int)$row->max_id : 0;
    register_shutdown_function(function() use ($projectId,$beforeId) {
        $CI=&get_instance();
        $files=$CI->db->where('project_id',$projectId)->where('id >',$beforeId)->order_by('id','ASC')->get(db_prefix().'project_files')->result();
        foreach ($files as $file) {
            if (empty($file->external)) synchub_enqueue_project_file_event((int)$file->id,'create');
        }
    });
}


/* =============================================================
 * Step 16.1 - Project Files DELETE sync
 * Policy:
 *   CHILD/source delete  -> delete copy in MARKITO.
 *   MARKITO delete of a child-origin file -> local-only.
 * ============================================================= */

function synchub_enqueue_project_file_delete_event($fileId)
{
    $CI = &get_instance();
    $fileId = (int)$fileId;
    if ($fileId <= 0 || !empty($GLOBALS['synchub_receiving_project_file'])) return false;

    $file = $CI->db->where('id',$fileId)->get(db_prefix().'project_files')->row();
    if (!$file) return false;

    $origin = synchub_get_entity_origin('project_file',$fileId);
    if (!$origin) return false;

    $sourceCode = strtoupper((string)$origin->source_company_code);
    if ($sourceCode === '' || $sourceCode === 'MARKITO') return false;

    // SyncHub v9.5:
    // source_company_code is the operational target and cannot by itself prove
    // which instance originally created the file. A local outbound CREATE queue
    // event is the durable ownership evidence available in the current schema.
    if (synchub_instance_role() === 'master') {
        // Markito-created file for a child-target project: propagate delete.
        // Genuine child-origin mirror deleted in Markito: local-only.
        if (!synchub_entity_was_created_on_this_instance('project_file', $fileId, (string)$origin->sync_uuid)) {
            return false;
        }
        $destination = synchub_company_by_code($sourceCode);
    } else {
        // A child may propagate only deletion of an entity that originated there.
        if ($sourceCode !== synchub_instance_code()) return false;
        $destination = synchub_company_by_code((string)synchub_instance_config('master_code'));
    }

    $projectOrigin = synchub_get_entity_origin('project',(int)$file->project_id);
    if (!$projectOrigin || !$destination) return false;

    $payload = synchub_project_file_payload($fileId,$origin,$projectOrigin);
    if (!$payload) return false;
    $payload['action'] = 'delete';

    if (!$CI->db->insert(db_prefix().'synchub_queue',[
        'event_id'=>synchub_generate_uuid(),
        'entity_type'=>'project_file',
        'entity_id'=>$fileId,
        'action'=>'delete',
        'company_id'=>(int)$destination->id,
        'branch_id'=>$origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'payload'=>json_encode($payload),
        'status'=>'pending',
        'attempts'=>0,
        'last_error'=>null,
        'created_at'=>date('Y-m-d H:i:s'),
        'updated_at'=>null,
    ])) return false;

    return synchub_dispatch_project_file_queue_item((int)$CI->db->insert_id());
}

hooks()->add_action('before_remove_project_file','synchub_observe_project_file_delete');
function synchub_observe_project_file_delete($fileId)
{
    if (!empty($GLOBALS['synchub_receiving_project_file'])) return;
    synchub_enqueue_project_file_delete_event((int)$fileId);
}


/*
|--------------------------------------------------------------------------
| Project Discussions Sync - Step 17 (Create)
|--------------------------------------------------------------------------
|
| Child-created discussions inherit the parent project's source company and
| are synchronized to Markito. Discussion comments are handled separately.
|
*/

function synchub_project_discussion_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db->where('entity_type', 'project_discussion')
        ->where('sync_uuid', (string)$syncUuid)
        ->get(db_prefix() . 'synchub_entity_map')->row();
}

function synchub_project_discussion_payload($discussionId, $origin, $projectOrigin)
{
    $CI = &get_instance();
    $discussion = $CI->db->where('id', (int)$discussionId)
        ->get(db_prefix() . 'projectdiscussions')->row();
    if (!$discussion || !$origin || !$projectOrigin) return null;

    $staffEmail = '';
    if ((int)$discussion->staff_id > 0) {
        $staff = $CI->db->select('email')->where('staffid', (int)$discussion->staff_id)
            ->get(db_prefix() . 'staff')->row();
        if ($staff) $staffEmail = (string)$staff->email;
    }

    return [
        'synchub_entity_type'       => 'project_discussion',
        'discussion_id'             => (int)$discussion->id,
        'subject'                   => (string)$discussion->subject,
        'description'               => (string)$discussion->description,
        'show_to_customer'          => (int)$discussion->show_to_customer,
        'datecreated'               => (string)$discussion->datecreated,
        'last_activity'             => $discussion->last_activity,
        'source_staff_id'           => (int)$discussion->staff_id,
        'staff_email'               => $staffEmail,
        'parent_project_sync_uuid'  => (string)$projectOrigin->sync_uuid,
        'source_company_code'       => (string)$origin->source_company_code,
        'source_branch_id'          => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'sync_uuid'                 => (string)$origin->sync_uuid,
        'sender_instance_code'      => synchub_instance_code(),
    ];
}

function synchub_project_discussion_destination($origin)
{
    if (!$origin) return null;
    $sourceCode = strtoupper(trim((string)$origin->source_company_code));
    $instanceCode = synchub_instance_code();

    if (synchub_instance_role() === 'child') {
        if ($sourceCode !== $instanceCode) return null;
        return synchub_company_by_code((string)synchub_instance_config('master_code'));
    }

    if (synchub_instance_role() === 'master' && $sourceCode !== '' && $sourceCode !== 'MARKITO') {
        return synchub_company_by_code($sourceCode);
    }

    return null;
}

function synchub_enqueue_project_discussion_event($discussionId, $action = 'create', $originOverride = null, $payloadOverride = null)
{
    $CI = &get_instance();
    $discussionId = (int)$discussionId;
    $action = strtolower(trim((string)$action));
    if ($discussionId <= 0 || !in_array($action, ['create','update','delete'], true) || !empty($GLOBALS['synchub_receiving_project_discussion'])) return false;

    $origin = $originOverride ?: synchub_get_entity_origin('project_discussion', $discussionId);
    $discussion = $CI->db->where('id', $discussionId)->get(db_prefix() . 'projectdiscussions')->row();

    if ($action === 'create') {
        if (!$discussion || (int)$discussion->project_id <= 0) return false;
        $projectOrigin = synchub_get_entity_origin('project', (int)$discussion->project_id);
        if (!$projectOrigin) return false;
        if (!$origin) {
            $origin = synchub_register_entity_origin(
                'project_discussion',
                $discussionId,
                (string)$projectOrigin->source_company_code,
                $projectOrigin->source_branch_id !== null ? (int)$projectOrigin->source_branch_id : null
            );
        }
    }

    if (!$origin) return false;
    $sourceCode = strtoupper(trim((string)$origin->source_company_code));
    if ($sourceCode === '' || $sourceCode === 'MARKITO') return false;

    // SyncHub v9.6:
    // source_company_code represents the operational target for Markito-created
    // child-assigned data as well as the true source for child-created data.
    // CREATE on master is therefore valid when the parent project targets a child.
    // DELETE on master propagates only when this exact discussion was originally
    // created on this instance (proved by its outbound CREATE queue history).
    if ($action === 'delete' && synchub_instance_role() === 'master') {
        if (!synchub_entity_was_created_on_this_instance(
            'project_discussion',
            $discussionId,
            (string)$origin->sync_uuid
        )) {
            return true; // genuine child-origin mirror: local-only by policy
        }
    }

    $destination = synchub_project_discussion_destination($origin);
    if (!$destination) return false;

    if ($action === 'create' && synchub_project_discussion_map_by_uuid((string)$origin->sync_uuid)) return true;

    if (is_array($payloadOverride)) {
        $payload = $payloadOverride;
    } else {
        if (!$discussion || (int)$discussion->project_id <= 0) return false;
        $projectOrigin = synchub_get_entity_origin('project', (int)$discussion->project_id);
        if (!$projectOrigin) return false;
        $payload = synchub_project_discussion_payload($discussionId, $origin, $projectOrigin);
    }
    if (!$payload) return false;
    $payload['action'] = $action;

    $ok = $CI->db->insert(db_prefix() . 'synchub_queue', [
        'event_id'    => synchub_generate_uuid(),
        'entity_type' => 'project_discussion',
        'entity_id'   => $discussionId,
        'action'      => $action,
        'company_id'  => (int)$destination->id,
        'branch_id'   => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'payload'     => json_encode($payload),
        'status'      => 'pending',
        'attempts'    => 0,
        'last_error'  => null,
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => null,
    ]);
    if (!$ok) return false;
    return synchub_dispatch_project_discussion_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_project_discussion_queue_item($queueId)
{
    $CI = &get_instance();
    $queue = $CI->db->where('id', (int)$queueId)->where('entity_type', 'project_discussion')
        ->get(db_prefix() . 'synchub_queue')->row();
    if (!$queue) return false;

    $payload = json_decode((string)$queue->payload, true);
    if (!is_array($payload)) $payload = [];
    $payload['action'] = (string)$queue->action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody = json_encode($payload);

    $company = $CI->db->where('id', (int)$queue->company_id)->get(db_prefix() . 'synchub_companies')->row();
    if (!$company) return synchub_fail_queue_item((int)$queue->id, 'Discussion destination company not found.');

    $urlBase = synchub_remote_url((string)$company->code);
    $apiKey = (string)synchub_instance_config('api_key');
    $apiSecret = (string)synchub_instance_config('api_secret');
    if ($urlBase === '' || $apiKey === '' || $apiSecret === '') {
        return synchub_fail_queue_item((int)$queue->id, 'Discussion remote URL/API credentials are missing.');
    }

    $timestamp = (string)time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $apiSecret);
    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'=>'processing', 'attempts'=>(int)$queue->attempts + 1, 'updated_at'=>date('Y-m-d H:i:s')
    ]);

    $ch = curl_init($urlBase . '/synchub/api/task');
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json',
            'X-SyncHub-Key: ' . $apiKey,
            'X-SyncHub-Timestamp: ' . $timestamp,
            'X-SyncHub-Signature: ' . $signature,
        ],
        CURLOPT_POSTFIELDS=>$rawBody,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '') return synchub_fail_queue_item((int)$queue->id, 'Discussion cURL: ' . $curlError);
    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id, 'Discussion HTTP ' . $httpCode . ': ' . (string)$response);
    }

    $syncUuid = (string)($payload['sync_uuid'] ?? '');
    if ((string)$queue->action === 'create') {
        $remoteId = (int)($decoded['local_discussion_id'] ?? 0);
        if ($remoteId <= 0 || $syncUuid === '') return synchub_fail_queue_item((int)$queue->id, 'Discussion response missing mapping data.');

        $origin = synchub_get_entity_origin('project_discussion', (int)$queue->entity_id);
        $mapData = [
            'local_id'=>(int)$queue->entity_id,
            'remote_id'=>$remoteId,
            'company_id'=>(int)$queue->company_id,
            'branch_id'=>$origin && $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
            'remote_company_id'=>null,
            'updated_at'=>date('Y-m-d H:i:s'),
        ];
        $existing = synchub_project_discussion_map_by_uuid($syncUuid);
        if ($existing) {
            $CI->db->where('id', (int)$existing->id)->update(db_prefix() . 'synchub_entity_map', $mapData);
        } else {
            $mapData['entity_type'] = 'project_discussion';
            $mapData['sync_uuid'] = $syncUuid;
            $mapData['created_at'] = date('Y-m-d H:i:s');
            $CI->db->insert(db_prefix() . 'synchub_entity_map', $mapData);
        }
    } elseif ((string)$queue->action === 'delete' && $syncUuid !== '') {
        $CI->db->where('entity_type','project_discussion')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
        $CI->db->where('entity_type','project_discussion')->where('local_id',(int)$queue->entity_id)->delete(db_prefix().'synchub_entity_origin');
    } elseif ($syncUuid !== '') {
        $map = synchub_project_discussion_map_by_uuid($syncUuid);
        if ($map) $CI->db->where('id',(int)$map->id)->update(db_prefix().'synchub_entity_map',['updated_at'=>date('Y-m-d H:i:s')]);
    }

    $CI->db->where('id', (int)$queue->id)->update(db_prefix() . 'synchub_queue', [
        'status'=>'success', 'last_error'=>null, 'updated_at'=>date('Y-m-d H:i:s')
    ]);
    return true;
}

hooks()->add_action('admin_init', 'synchub_observe_project_discussion_create');
function synchub_observe_project_discussion_create()
{
    $CI = &get_instance();
    if (!empty($GLOBALS['synchub_receiving_project_discussion'])) return;
    if (strtolower((string)$CI->router->fetch_class()) !== 'projects' || strtolower((string)$CI->router->fetch_method()) !== 'discussion') return;
    if (strtolower((string)$CI->input->method()) !== 'post' || $CI->input->post('id')) return;

    $projectId = (int)$CI->input->post('project_id');
    if ($projectId <= 0) return;
    $row = $CI->db->select_max('id', 'max_id')->where('project_id', $projectId)
        ->get(db_prefix() . 'projectdiscussions')->row();
    $beforeId = $row ? (int)$row->max_id : 0;

    register_shutdown_function(function() use ($projectId, $beforeId) {
        $CI = &get_instance();
        $rows = $CI->db->where('project_id', $projectId)->where('id >', $beforeId)
            ->order_by('id', 'ASC')->get(db_prefix() . 'projectdiscussions')->result();
        foreach ($rows as $discussion) {
            synchub_enqueue_project_discussion_event((int)$discussion->id, 'create');
        }
    });
}


/*
|--------------------------------------------------------------------------
| Project Discussions Sync - Step 17.1 (Update + Delete)
|--------------------------------------------------------------------------
| Update is bidirectional for child-origin discussions.
| Delete from source child propagates to Markito.
| Delete from Markito is local-only and never deletes source child data.
*/

hooks()->add_action('admin_init', 'synchub_observe_project_discussion_update_delete');
function synchub_observe_project_discussion_update_delete()
{
    $CI = &get_instance();
    if (!empty($GLOBALS['synchub_receiving_project_discussion'])) return;
    if (strtolower((string)$CI->router->fetch_class()) !== 'projects') return;

    $method = strtolower((string)$CI->router->fetch_method());

    if ($method === 'discussion' && strtolower((string)$CI->input->method()) === 'post') {
        $discussionId = (int)$CI->input->post('id');
        if ($discussionId <= 0) return; // create is handled by Step 17

        register_shutdown_function(function() use ($discussionId) {
            $CI = &get_instance();
            if ($CI->db->where('id',$discussionId)->get(db_prefix().'projectdiscussions')->row()) {
                synchub_enqueue_project_discussion_event($discussionId,'update');
            }
        });
        return;
    }

    if ($method === 'delete_discussion') {
        $discussionId = (int)$CI->uri->segment(4);
        if ($discussionId <= 0) return;

        $discussion = $CI->db->where('id',$discussionId)->get(db_prefix().'projectdiscussions')->row();
        $origin = synchub_get_entity_origin('project_discussion',$discussionId);
        if (!$discussion || !$origin) return;

        $sourceCode = strtoupper(trim((string)$origin->source_company_code));

        // SyncHub v9.6:
        // Markito may delete a child-target discussion remotely only when Markito
        // originally created that discussion. A genuine Seen-origin mirror remains
        // local-only when removed from Markito.
        if (synchub_instance_role() === 'master') {
            if (!synchub_entity_was_created_on_this_instance(
                'project_discussion',
                $discussionId,
                (string)$origin->sync_uuid
            )) {
                $syncUuid = (string)$origin->sync_uuid;
                register_shutdown_function(function() use ($discussionId,$syncUuid) {
                    $CI = &get_instance();
                    if (!$CI->db->where('id',$discussionId)->get(db_prefix().'projectdiscussions')->row()) {
                        $CI->db->where('entity_type','project_discussion')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
                        $CI->db->where('entity_type','project_discussion')->where('local_id',$discussionId)->delete(db_prefix().'synchub_entity_origin');
                    }
                });
                return;
            }
            // Markito-created child-target discussion continues to the shared
            // snapshot + shutdown enqueue path below.
        } elseif (synchub_instance_role() === 'child') {
            // Only the source child may propagate deletion upstream.
            if ($sourceCode !== synchub_instance_code()) return;
        } else {
            return;
        }

        $projectOrigin = synchub_get_entity_origin('project',(int)$discussion->project_id);
        if (!$projectOrigin) return;
        $payload = synchub_project_discussion_payload($discussionId,$origin,$projectOrigin);
        if (!$payload) return;
        $payload['action'] = 'delete';

        register_shutdown_function(function() use ($discussionId,$origin,$payload) {
            $CI = &get_instance();
            if (!$CI->db->where('id',$discussionId)->get(db_prefix().'projectdiscussions')->row()) {
                synchub_enqueue_project_discussion_event($discussionId,'delete',$origin,$payload);
            }
        });
    }
}


/*
|--------------------------------------------------------------------------
| Project Discussion Comments Sync - Step 18 (Create)
|--------------------------------------------------------------------------
| Text comments on regular project discussions created in a child company
| are synchronized to Markito. Attachments are intentionally handled later.
*/

function synchub_project_discussion_comment_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db->where('entity_type','project_discussion_comment')
        ->where('sync_uuid',(string)$syncUuid)
        ->get(db_prefix().'synchub_entity_map')->row();
}

function synchub_project_discussion_comment_payload($commentId, $origin, $discussionOrigin)
{
    $CI = &get_instance();
    $comment = $CI->db->where('id',(int)$commentId)->get(db_prefix().'projectdiscussioncomments')->row();
    if (!$comment || !$origin || !$discussionOrigin) return null;
    if ((string)$comment->discussion_type !== 'regular') return null;

    $email = '';
    if ((int)$comment->staff_id > 0) {
        $staff = $CI->db->select('email')->where('staffid',(int)$comment->staff_id)->get(db_prefix().'staff')->row();
        if ($staff) $email = (string)$staff->email;
    }

    $parentUuid = null;
    if (!empty($comment->parent)) {
        $parentOrigin = synchub_get_entity_origin('project_discussion_comment',(int)$comment->parent);
        if ($parentOrigin) $parentUuid = (string)$parentOrigin->sync_uuid;
    }

    return [
        'synchub_entity_type'            => 'project_discussion_comment',
        'comment_id'                     => (int)$comment->id,
        'content'                        => (string)$comment->content,
        'created'                        => (string)$comment->created,
        'modified'                       => $comment->modified,
        'source_staff_id'                => (int)$comment->staff_id,
        'staff_email'                    => $email,
        'fullname'                       => (string)$comment->fullname,
        'source_company_code'            => (string)$origin->source_company_code,
        'source_branch_id'               => $origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'sync_uuid'                      => (string)$origin->sync_uuid,
        'parent_discussion_sync_uuid'    => (string)$discussionOrigin->sync_uuid,
        'parent_comment_sync_uuid'       => $parentUuid,
        // Step 19: an attachment is part of the Perfex discussion-comment row itself.
        // Keep the binary out of the queue JSON; only metadata is queued and the
        // physical file is streamed as multipart at dispatch time.
        'attachment_name'                => !empty($comment->file_name) ? (string)$comment->file_name : null,
        'attachment_mime_type'           => !empty($comment->file_mime_type) ? (string)$comment->file_mime_type : null,
        'sender_instance_code'           => synchub_instance_code(),
        'action'                         => 'create',
    ];
}

function synchub_project_discussion_comment_attachment_path($commentId)
{
    $CI = &get_instance();
    $comment = $CI->db->where('id',(int)$commentId)->get(db_prefix().'projectdiscussioncomments')->row();
    if (!$comment || empty($comment->file_name)) return '';
    $path = PROJECT_DISCUSSION_ATTACHMENT_FOLDER.(int)$comment->discussion_id.'/'.(string)$comment->file_name;
    return is_file($path) ? $path : '';
}

function synchub_enqueue_project_discussion_comment_create($commentId)
{
    $CI = &get_instance();
    $commentId = (int)$commentId;
    if ($commentId <= 0 || !empty($GLOBALS['synchub_receiving_project_discussion_comment'])) return false;

    $comment = $CI->db->where('id',$commentId)->get(db_prefix().'projectdiscussioncomments')->row();
    if (!$comment || (string)$comment->discussion_type !== 'regular') return false;

    $discussionOrigin = synchub_get_entity_origin('project_discussion',(int)$comment->discussion_id);
    if (!$discussionOrigin) return false;

    $sourceCode = strtoupper(trim((string)$discussionOrigin->source_company_code));
    if ($sourceCode === '' || $sourceCode === 'MARKITO') return false;

    // SyncHub v9.7:
    // A regular discussion comment follows the routing of its parent discussion.
    // On a child, locally-created comments go upstream to master.
    // On master, comments created inside a child-target discussion go to that child.
    if (synchub_instance_role() === 'child') {
        if ($sourceCode !== synchub_instance_code()) return false;
        $destination = synchub_company_by_code((string)synchub_instance_config('master_code'));
    } elseif (synchub_instance_role() === 'master') {
        $destination = synchub_company_by_code($sourceCode);
    } else {
        return false;
    }

    if (!$destination) return false;

    $origin = synchub_get_entity_origin('project_discussion_comment',$commentId);
    if (!$origin) {
        $origin = synchub_register_entity_origin(
            'project_discussion_comment',
            $commentId,
            (string)$discussionOrigin->source_company_code,
            $discussionOrigin->source_branch_id !== null ? (int)$discussionOrigin->source_branch_id : null
        );
    }
    if (!$origin || synchub_project_discussion_comment_map_by_uuid((string)$origin->sync_uuid)) return true;

    $payload = synchub_project_discussion_comment_payload($commentId,$origin,$discussionOrigin);
    if (!$payload) return false;

    $ok = $CI->db->insert(db_prefix().'synchub_queue',[
        'event_id'=>synchub_generate_uuid(),
        'entity_type'=>'project_discussion_comment',
        'entity_id'=>$commentId,
        'action'=>'create',
        'company_id'=>(int)$destination->id,
        'branch_id'=>$origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'payload'=>json_encode($payload),
        'status'=>'pending','attempts'=>0,'last_error'=>null,
        'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
    ]);
    if (!$ok) return false;
    return synchub_dispatch_project_discussion_comment_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_project_discussion_comment_queue_item($queueId)
{
    $CI = &get_instance();
    $queue = $CI->db->where('id',(int)$queueId)->where('entity_type','project_discussion_comment')
        ->get(db_prefix().'synchub_queue')->row();
    if (!$queue) return false;

    $payload = json_decode((string)$queue->payload,true);
    if (!is_array($payload)) $payload=[];
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody = json_encode($payload);
    $company = $CI->db->where('id',(int)$queue->company_id)->get(db_prefix().'synchub_companies')->row();
    if (!$company) return synchub_fail_queue_item((int)$queue->id,'Comment destination company not found.');

    $urlBase=synchub_remote_url((string)$company->code);
    $apiKey=(string)synchub_instance_config('api_key');
    $apiSecret=(string)synchub_instance_config('api_secret');
    if ($urlBase==='' || $apiKey==='' || $apiSecret==='') return synchub_fail_queue_item((int)$queue->id,'Comment remote URL/API credentials are missing.');

    $timestamp=(string)time();
    $signature=hash_hmac('sha256',$timestamp.'.'.$rawBody,$apiSecret);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
        'status'=>'processing','attempts'=>(int)$queue->attempts+1,'updated_at'=>date('Y-m-d H:i:s')
    ]);

    $ch=curl_init($urlBase.'/synchub/api/task');
    $headers=['X-SyncHub-Key: '.$apiKey,'X-SyncHub-Timestamp: '.$timestamp,'X-SyncHub-Signature: '.$signature];
    $curlOptions=[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>5];

    $attachmentName=trim((string)($payload['attachment_name'] ?? ''));
    if ($attachmentName!=='') {
        $diskPath=synchub_project_discussion_comment_attachment_path((int)$queue->entity_id);
        if ($diskPath==='') {
            curl_close($ch);
            return synchub_fail_queue_item((int)$queue->id,'Discussion comment attachment physical file is missing.');
        }
        $mime=trim((string)($payload['attachment_mime_type'] ?? ''));
        if ($mime==='') $mime=function_exists('get_mime_by_extension') ? (string)get_mime_by_extension($attachmentName) : 'application/octet-stream';
        if ($mime==='') $mime='application/octet-stream';
        $curlOptions[CURLOPT_POSTFIELDS]=[
            'synchub_entity_type'=>'project_discussion_comment_attachment',
            'metadata'=>$rawBody,
            'file'=>new CURLFile($diskPath,$mime,$attachmentName),
        ];
    } else {
        $headers[]='Content-Type: application/json';
        $curlOptions[CURLOPT_POSTFIELDS]=$rawBody;
    }
    $curlOptions[CURLOPT_HTTPHEADER]=$headers;
    curl_setopt_array($ch,$curlOptions);
    $response=curl_exec($ch); $curlError=curl_error($ch); $httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($curlError!=='') return synchub_fail_queue_item((int)$queue->id,'Comment cURL: '.$curlError);
    $decoded=json_decode((string)$response,true);
    if ($httpCode<200 || $httpCode>=300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id,'Comment HTTP '.$httpCode.': '.(string)$response);
    }

    $syncUuid=(string)($payload['sync_uuid']??'');
    $remoteId=(int)($decoded['local_comment_id']??0);
    if ($remoteId<=0 || $syncUuid==='') return synchub_fail_queue_item((int)$queue->id,'Comment response missing mapping data.');

    $origin=synchub_get_entity_origin('project_discussion_comment',(int)$queue->entity_id);
    $mapData=[
        'entity_type'=>'project_discussion_comment','local_id'=>(int)$queue->entity_id,'remote_id'=>$remoteId,
        'company_id'=>(int)$queue->company_id,'branch_id'=>$origin && $origin->source_branch_id!==null ? (int)$origin->source_branch_id : null,
        'remote_company_id'=>null,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')
    ];
    $existing=synchub_project_discussion_comment_map_by_uuid($syncUuid);
    if ($existing) {
        unset($mapData['created_at']);
        $CI->db->where('id',(int)$existing->id)->update(db_prefix().'synchub_entity_map',$mapData);
    } else {
        $CI->db->insert(db_prefix().'synchub_entity_map',$mapData);
    }
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',['status'=>'success','last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')]);
    return true;
}

hooks()->add_action('after_add_discussion_comment','synchub_after_add_project_discussion_comment');
function synchub_after_add_project_discussion_comment($commentId)
{
    if (!empty($GLOBALS['synchub_receiving_project_discussion_comment'])) return;
    synchub_enqueue_project_discussion_comment_create((int)$commentId);
}


/*
|--------------------------------------------------------------------------
| Project Discussion Comments Sync - Step 18.1 (Update + Delete)
|--------------------------------------------------------------------------
| Update is bidirectional for child-origin comments.
| Delete from source child propagates to Markito.
| Delete from Markito is local-only and never deletes source child data.
*/

function synchub_project_discussion_comment_destination($origin)
{
    if (!$origin) return null;
    $sourceCode = strtoupper(trim((string)$origin->source_company_code));
    if ($sourceCode === '' || $sourceCode === 'MARKITO') return null;
    if (synchub_instance_role() === 'child') {
        if ($sourceCode !== synchub_instance_code()) return null;
        return synchub_company_by_code((string)synchub_instance_config('master_code'));
    }
    if (synchub_instance_role() === 'master') {
        return synchub_company_by_code($sourceCode);
    }
    return null;
}

function synchub_enqueue_project_discussion_comment_event($commentId, $action, $originOverride=null, $payloadOverride=null)
{
    $CI=&get_instance();
    $commentId=(int)$commentId;
    $action=strtolower((string)$action);
    if ($commentId<=0 || !in_array($action,['update','delete'],true) || !empty($GLOBALS['synchub_receiving_project_discussion_comment'])) return false;

    $origin=$originOverride ?: synchub_get_entity_origin('project_discussion_comment',$commentId);
    if (!$origin) return false;
    $sourceCode=strtoupper(trim((string)$origin->source_company_code));
    if ($sourceCode==='' || $sourceCode==='MARKITO') return false;

    // SyncHub v9.7:
    // A master-side delete propagates only when this exact comment was
    // originally created on master. Genuine child-origin mirrors remain
    // local-only when removed from Markito.
    if ($action==='delete' && synchub_instance_role()==='master') {
        if (!synchub_entity_was_created_on_this_instance(
            'project_discussion_comment',
            $commentId,
            (string)$origin->sync_uuid
        )) {
            return true;
        }
    }

    $destination=synchub_project_discussion_comment_destination($origin);
    if (!$destination) return false;

    if (is_array($payloadOverride)) {
        $payload=$payloadOverride;
    } else {
        $comment=$CI->db->where('id',$commentId)->get(db_prefix().'projectdiscussioncomments')->row();
        if (!$comment || (string)$comment->discussion_type!=='regular') return false;
        $discussionOrigin=synchub_get_entity_origin('project_discussion',(int)$comment->discussion_id);
        if (!$discussionOrigin) return false;
        $payload=synchub_project_discussion_comment_payload($commentId,$origin,$discussionOrigin);
    }
    if (!$payload) return false;
    $payload['action']=$action;

    $ok=$CI->db->insert(db_prefix().'synchub_queue',[
        'event_id'=>synchub_generate_uuid(),
        'entity_type'=>'project_discussion_comment',
        'entity_id'=>$commentId,
        'action'=>$action,
        'company_id'=>(int)$destination->id,
        'branch_id'=>$origin->source_branch_id!==null?(int)$origin->source_branch_id:null,
        'payload'=>json_encode($payload),
        'status'=>'pending','attempts'=>0,'last_error'=>null,
        'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
    ]);
    if (!$ok) return false;
    return synchub_dispatch_project_discussion_comment_mutation_queue_item((int)$CI->db->insert_id());
}

function synchub_dispatch_project_discussion_comment_mutation_queue_item($queueId)
{
    $CI=&get_instance();
    $queue=$CI->db->where('id',(int)$queueId)->where('entity_type','project_discussion_comment')->get(db_prefix().'synchub_queue')->row();
    if (!$queue) return false;
    $payload=json_decode((string)$queue->payload,true); if(!is_array($payload)) $payload=[];
    $payload['action']=(string)$queue->action;
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody=json_encode($payload);
    $company=$CI->db->where('id',(int)$queue->company_id)->get(db_prefix().'synchub_companies')->row();
    if(!$company) return synchub_fail_queue_item((int)$queue->id,'Comment destination company not found.');
    $urlBase=synchub_remote_url((string)$company->code);
    $apiKey=(string)synchub_instance_config('api_key'); $apiSecret=(string)synchub_instance_config('api_secret');
    if($urlBase===''||$apiKey===''||$apiSecret==='') return synchub_fail_queue_item((int)$queue->id,'Comment remote URL/API credentials are missing.');
    $timestamp=(string)time(); $signature=hash_hmac('sha256',$timestamp.'.'.$rawBody,$apiSecret);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',['status'=>'processing','attempts'=>(int)$queue->attempts+1,'updated_at'=>date('Y-m-d H:i:s')]);
    $ch=curl_init($urlBase.'/synchub/api/task');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-SyncHub-Key: '.$apiKey,'X-SyncHub-Timestamp: '.$timestamp,'X-SyncHub-Signature: '.$signature],CURLOPT_POSTFIELDS=>$rawBody]);
    $response=curl_exec($ch); $curlError=curl_error($ch); $httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($curlError!=='') return synchub_fail_queue_item((int)$queue->id,'Comment cURL: '.$curlError);
    $decoded=json_decode((string)$response,true);
    if($httpCode<200||$httpCode>=300||!is_array($decoded)||empty($decoded['success'])) return synchub_fail_queue_item((int)$queue->id,'Comment HTTP '.$httpCode.': '.(string)$response);

    $syncUuid=(string)($payload['sync_uuid']??'');
    if((string)$queue->action==='delete' && $syncUuid!=='') {
        $CI->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
        $CI->db->where('entity_type','project_discussion_comment')->where('local_id',(int)$queue->entity_id)->delete(db_prefix().'synchub_entity_origin');
    } elseif($syncUuid!=='') {
        $map=synchub_project_discussion_comment_map_by_uuid($syncUuid);
        if($map) $CI->db->where('id',(int)$map->id)->update(db_prefix().'synchub_entity_map',['updated_at'=>date('Y-m-d H:i:s')]);
    }
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',['status'=>'success','last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')]);
    return true;
}

hooks()->add_action('admin_init','synchub_observe_project_discussion_comment_update_delete');
function synchub_observe_project_discussion_comment_update_delete()
{
    $CI=&get_instance();
    if(!empty($GLOBALS['synchub_receiving_project_discussion_comment'])) return;
    if(strtolower((string)$CI->router->fetch_class())!=='projects') return;
    $method=strtolower((string)$CI->router->fetch_method());

    if($method==='update_discussion_comment' && strtolower((string)$CI->input->method())==='post') {
        $commentId=(int)$CI->input->post('id');
        if($commentId<=0) return;
        register_shutdown_function(function() use($commentId){
            $CI=&get_instance();
            if($CI->db->where('id',$commentId)->get(db_prefix().'projectdiscussioncomments')->row()) {
                synchub_enqueue_project_discussion_comment_event($commentId,'update');
            }
        });
        return;
    }

    if($method==='delete_discussion_comment') {
        $commentId=(int)$CI->uri->segment(4);
        if($commentId<=0) return;
        $comment=$CI->db->where('id',$commentId)->get(db_prefix().'projectdiscussioncomments')->row();
        $origin=synchub_get_entity_origin('project_discussion_comment',$commentId);
        if(!$comment||!$origin||(string)$comment->discussion_type!=='regular') return;
        $sourceCode=strtoupper(trim((string)$origin->source_company_code));

        if(synchub_instance_role()==='master') {
            if (!synchub_entity_was_created_on_this_instance(
                'project_discussion_comment',
                $commentId,
                (string)$origin->sync_uuid
            )) {
                $syncUuid=(string)$origin->sync_uuid;
                register_shutdown_function(function() use($commentId,$syncUuid){
                    $CI=&get_instance();
                    if(!$CI->db->where('id',$commentId)->get(db_prefix().'projectdiscussioncomments')->row()) {
                        $CI->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
                        $CI->db->where('entity_type','project_discussion_comment')->where('local_id',$commentId)->delete(db_prefix().'synchub_entity_origin');
                    }
                });
                return;
            }
            // Markito-created child-target comment continues to shared delete path.
        } elseif(synchub_instance_role()==='child') {
            if($sourceCode!==synchub_instance_code()) return;
        } else {
            return;
        }

        $discussionOrigin=synchub_get_entity_origin('project_discussion',(int)$comment->discussion_id);
        if(!$discussionOrigin) return;
        $payload=synchub_project_discussion_comment_payload($commentId,$origin,$discussionOrigin);
        if(!$payload) return;
        $payload['action']='delete';
        register_shutdown_function(function() use($commentId,$origin,$payload){
            $CI=&get_instance();
            if(!$CI->db->where('id',$commentId)->get(db_prefix().'projectdiscussioncomments')->row()) {
                synchub_enqueue_project_discussion_comment_event($commentId,'delete',$origin,$payload);
            }
        });
    }
}


/* =============================================================
 * Step 18.2 - Reliable Discussion Comment UPDATE observer
 *
 * Perfex updates discussion comments through an AJAX request to
 * projects/update_discussion_comment. Some installations do not expose the
 * native request early enough for the previous shutdown observer to catch it
 * reliably. This module-only fallback watches the successful native AJAX
 * response in the browser, then calls SyncHub locally to enqueue the already
 * saved comment state. No Perfex core file is modified.
 * ============================================================= */

hooks()->add_action('admin_init', 'synchub_step182_comment_update_bridge');
function synchub_step182_comment_update_bridge()
{
    $CI = &get_instance();
    $commentId = isset($_GET['synchub_comment_updated']) ? (int) $_GET['synchub_comment_updated'] : 0;
    if ($commentId <= 0) {
        return;
    }

    if (!is_staff_logged_in()) {
        $CI->output->set_status_header(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $comment = $CI->db->where('id', $commentId)->get(db_prefix() . 'projectdiscussioncomments')->row();
    if (!$comment || (string) $comment->discussion_type !== 'regular') {
        $CI->output->set_status_header(404);
        echo json_encode(['success' => false, 'message' => 'Discussion comment not found']);
        exit;
    }

    $origin = synchub_get_entity_origin('project_discussion_comment', $commentId);
    if (!$origin) {
        $CI->output->set_status_header(422);
        echo json_encode(['success' => false, 'message' => 'Discussion comment origin not found']);
        exit;
    }

    $ok = synchub_enqueue_project_discussion_comment_event($commentId, 'update');
    $CI->output->set_content_type('application/json');
    echo json_encode(['success' => (bool) $ok, 'comment_id' => $commentId]);
    exit;
}

hooks()->add_action('app_admin_footer', 'synchub_step182_comment_update_js');
function synchub_step182_comment_update_js()
{
    $CI = &get_instance();
    if (strtolower((string) $CI->router->fetch_class()) !== 'projects') {
        return;
    }
    ?>
    <script>
    (function($){
        if (!$ || window.__synchubCommentUpdateBridge) return;
        window.__synchubCommentUpdateBridge = true;

        $(document).ajaxSuccess(function(event, xhr, settings){
            var url = String((settings && settings.url) || '');
            if (url.indexOf('projects/update_discussion_comment') === -1) return;

            var id = 0;
            var data = settings ? settings.data : null;

            if (typeof data === 'string') {
                try {
                    var params = new URLSearchParams(data);
                    id = parseInt(params.get('id') || '0', 10) || 0;
                } catch (e) {
                    var m = data.match(/(?:^|&)id=([^&]+)/);
                    if (m) id = parseInt(decodeURIComponent(m[1]), 10) || 0;
                }
            } else if (data && typeof data === 'object') {
                id = parseInt(data.id || '0', 10) || 0;
            }

            if (!id) return;

            $.get(admin_url + 'synchub', {synchub_comment_updated: id});
        });
    })(window.jQuery);
    </script>
    <?php
}

/* =============================================================
 * Step 20 - Task Comments CREATE sync
 * Plain text comments only. Task-comment attachments are handled later.
 * ============================================================= */

function synchub_task_comment_map_by_uuid($syncUuid)
{
    $CI = &get_instance();
    return $CI->db->where('entity_type','task_comment')
        ->where('sync_uuid',(string)$syncUuid)
        ->get(db_prefix().'synchub_entity_map')->row();
}

function synchub_task_comment_payload($commentId, $origin, $taskOrigin)
{
    $CI = &get_instance();
    $comment = $CI->db->where('id',(int)$commentId)->get(db_prefix().'task_comments')->row();
    if (!$comment || !$origin || !$taskOrigin) return null;

    // Step 20 deliberately syncs text-only comments. Perfex attachments are
    // stored through the files table and will be transported in a later step.
    $email = '';
    if ((int)$comment->staffid > 0) {
        $staff = $CI->db->select('email')->where('staffid',(int)$comment->staffid)->get(db_prefix().'staff')->row();
        if ($staff) $email = (string)$staff->email;
    }

    return [
        'synchub_entity_type'=>'task_comment',
        'comment_id'=>(int)$comment->id,
        'content'=>(string)$comment->content,
        'dateadded'=>(string)$comment->dateadded,
        'source_staff_id'=>(int)$comment->staffid,
        'staff_email'=>$email,
        'source_company_code'=>(string)$origin->source_company_code,
        'source_branch_id'=>$origin->source_branch_id !== null ? (int)$origin->source_branch_id : null,
        'sync_uuid'=>(string)$origin->sync_uuid,
        'parent_task_sync_uuid'=>(string)$taskOrigin->sync_uuid,
        'sender_instance_code'=>synchub_instance_code(),
        'action'=>'create',
    ];
}

function synchub_dispatch_task_comment_queue_item($queueId)
{
    $CI = &get_instance();
    $queue = $CI->db->where('id',(int)$queueId)->where('entity_type','task_comment')
        ->get(db_prefix().'synchub_queue')->row();
    if (!$queue) return false;

    $payload = json_decode((string)$queue->payload,true);
    if (!is_array($payload)) $payload=[];
    // SyncHub v9: preserve outbound activity actor.
    $payload = synchub_attach_activity_actor($payload);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue', ['payload'=>json_encode($payload)]);
    $rawBody=json_encode($payload);
    $company=$CI->db->where('id',(int)$queue->company_id)->get(db_prefix().'synchub_companies')->row();
    if (!$company) return synchub_fail_queue_item((int)$queue->id,'Task comment destination company not found.');

    $urlBase=synchub_remote_url((string)$company->code);
    $apiKey=(string)synchub_instance_config('api_key');
    $apiSecret=(string)synchub_instance_config('api_secret');
    if ($urlBase==='' || $apiKey==='' || $apiSecret==='') return synchub_fail_queue_item((int)$queue->id,'Task comment remote URL/API credentials are missing.');

    $timestamp=(string)time();
    $signature=hash_hmac('sha256',$timestamp.'.'.$rawBody,$apiSecret);
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
        'status'=>'processing','attempts'=>(int)$queue->attempts+1,'updated_at'=>date('Y-m-d H:i:s')
    ]);

    $ch=curl_init($urlBase.'/synchub/api/task');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$rawBody,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>120,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json',
            'X-SyncHub-Key: '.$apiKey,
            'X-SyncHub-Timestamp: '.$timestamp,
            'X-SyncHub-Signature: '.$signature,
        ],
    ]);
    $response=curl_exec($ch);
    $curlError=curl_error($ch);
    $httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError!=='') return synchub_fail_queue_item((int)$queue->id,'Task comment cURL: '.$curlError);
    $decoded=json_decode((string)$response,true);
    if ($httpCode<200 || $httpCode>=300 || !is_array($decoded) || empty($decoded['success'])) {
        return synchub_fail_queue_item((int)$queue->id,'Task comment HTTP '.$httpCode.': '.(string)$response);
    }

    $syncUuid=(string)($payload['sync_uuid']??'');
    $action=strtolower(trim((string)($payload['action']??$queue->action)));
    if ($action === 'delete') {
        if ($syncUuid==='') return synchub_fail_queue_item((int)$queue->id,'Task comment delete response missing sync_uuid.');
        $CI->db->where('entity_type','task_comment')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
        $CI->db->where('entity_type','task_comment')->where('local_id',(int)$queue->entity_id)->delete(db_prefix().'synchub_entity_origin');
        $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
            'status'=>'success','last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')
        ]);
        return true;
    }
    $remoteId=(int)($decoded['local_comment_id']??0);
    if ($remoteId<=0 || $syncUuid==='') return synchub_fail_queue_item((int)$queue->id,'Task comment response missing mapping data.');

    $origin=synchub_get_entity_origin('task_comment',(int)$queue->entity_id);
    $mapData=[
        'entity_type'=>'task_comment','local_id'=>(int)$queue->entity_id,'remote_id'=>$remoteId,
        'company_id'=>(int)$queue->company_id,
        'branch_id'=>$origin && $origin->source_branch_id!==null ? (int)$origin->source_branch_id : null,
        'remote_company_id'=>null,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')
    ];
    $existing=synchub_task_comment_map_by_uuid($syncUuid);
    if ($existing) {
        unset($mapData['created_at']);
        $CI->db->where('id',(int)$existing->id)->update(db_prefix().'synchub_entity_map',$mapData);
    } else {
        $CI->db->insert(db_prefix().'synchub_entity_map',$mapData);
    }
    $CI->db->where('id',(int)$queue->id)->update(db_prefix().'synchub_queue',[
        'status'=>'success','last_error'=>null,'updated_at'=>date('Y-m-d H:i:s')
    ]);
    return true;
}

function synchub_enqueue_task_comment_create($commentId)
{
    $CI=&get_instance();
    $commentId=(int)$commentId;
    if ($commentId<=0 || !empty($GLOBALS['synchub_receiving_task_comment'])) return false;

    $comment=$CI->db->where('id',$commentId)->get(db_prefix().'task_comments')->row();
    if (!$comment || (int)$comment->staffid<=0) return false;

    $taskOrigin=synchub_get_entity_origin('task',(int)$comment->taskid);
    if (!$taskOrigin) return false;
    $sourceCode=strtoupper(trim((string)$taskOrigin->source_company_code));
    if ($sourceCode==='' || $sourceCode==='MARKITO') return false;

    // SyncHub v9.8:
    // Task comments follow the routing of their parent task. A child-origin
    // comment goes upstream to master; a comment created in Markito on a
    // child-target task goes to that child.
    if (synchub_instance_role()==='child') {
        if ($sourceCode!==synchub_instance_code()) return false;
        $destination=synchub_company_by_code((string)synchub_instance_config('master_code'));
    } elseif (synchub_instance_role()==='master') {
        $destination=synchub_company_by_code($sourceCode);
    } else {
        return false;
    }

    if (!$destination) return false;

    $origin=synchub_get_entity_origin('task_comment',$commentId);
    if (!$origin) {
        $origin=synchub_register_entity_origin(
            'task_comment',
            $commentId,
            (string)$taskOrigin->source_company_code,
            $taskOrigin->source_branch_id!==null ? (int)$taskOrigin->source_branch_id : null
        );
    }
    if (!$origin || synchub_task_comment_map_by_uuid((string)$origin->sync_uuid)) return true;

    $payload=synchub_task_comment_payload($commentId,$origin,$taskOrigin);
    if (!$payload) return false;

    $ok=$CI->db->insert(db_prefix().'synchub_queue',[
        'event_id'=>synchub_generate_uuid(),
        'entity_type'=>'task_comment',
        'entity_id'=>$commentId,
        'action'=>'create',
        'company_id'=>(int)$destination->id,
        'branch_id'=>$origin->source_branch_id!==null ? (int)$origin->source_branch_id : null,
        'payload'=>json_encode($payload),
        'status'=>'pending','attempts'=>0,'last_error'=>null,
        'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
    ]);
    if (!$ok) return false;
    return synchub_dispatch_task_comment_queue_item((int)$CI->db->insert_id());
}

hooks()->add_action('task_comment_added','synchub_step20_task_comment_added');
function synchub_step20_task_comment_added($data)
{
    if (!empty($GLOBALS['synchub_receiving_task_comment'])) return;
    $commentId=0;
    if (is_array($data)) $commentId=(int)($data['comment_id']??0);
    elseif (is_object($data) && isset($data->comment_id)) $commentId=(int)$data->comment_id;
    if ($commentId>0) synchub_enqueue_task_comment_create($commentId);
}

/* =============================================================
 * Step 20.2 - Native Task Comment UPDATE hook
 * Perfex Tasks_model::edit_comment() fires task_comment_updated after
 * the database update succeeds, so no browser/AJAX bridge is needed.
 * ============================================================= */

function synchub_task_comment_destination($origin)
{
    if (!$origin) return null;
    $sourceCode = strtoupper(trim((string)$origin->source_company_code));
    if ($sourceCode === '' || $sourceCode === 'MARKITO') return null;

    if (synchub_instance_role() === 'child') {
        if ($sourceCode !== synchub_instance_code()) return null;
        return synchub_company_by_code((string)synchub_instance_config('master_code'));
    }

    if (synchub_instance_role() === 'master') {
        return synchub_company_by_code($sourceCode);
    }

    return null;
}

function synchub_enqueue_task_comment_update($commentId)
{
    $CI = &get_instance();
    $commentId = (int)$commentId;
    if ($commentId <= 0 || !empty($GLOBALS['synchub_receiving_task_comment'])) return false;

    $comment = $CI->db->where('id',$commentId)->get(db_prefix().'task_comments')->row();
    if (!$comment) return false;

    $origin = synchub_get_entity_origin('task_comment',$commentId);
    if (!$origin) return false;

    $taskOrigin = synchub_get_entity_origin('task',(int)$comment->taskid);
    if (!$taskOrigin) return false;

    $destination = synchub_task_comment_destination($origin);
    if (!$destination) return false;

    $payload = synchub_task_comment_payload($commentId,$origin,$taskOrigin);
    if (!$payload) return false;
    $payload['action'] = 'update';

    $ok = $CI->db->insert(db_prefix().'synchub_queue',[
        'event_id'=>synchub_generate_uuid(),
        'entity_type'=>'task_comment',
        'entity_id'=>$commentId,
        'action'=>'update',
        'company_id'=>(int)$destination->id,
        'branch_id'=>$origin->source_branch_id!==null ? (int)$origin->source_branch_id : null,
        'payload'=>json_encode($payload),
        'status'=>'pending','attempts'=>0,'last_error'=>null,
        'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
    ]);
    if (!$ok) return false;

    return synchub_dispatch_task_comment_queue_item((int)$CI->db->insert_id());
}

/**
 * Dispatch one Task Comment update at most once in the current request.
 *
 * The native Perfex task_comment_updated hook remains the primary path.
 * Tasks_model.php also calls this helper immediately after the native hook as
 * a guarded fallback because this installation does not consistently execute
 * module callbacks for that AJAX action. The global guard prevents duplicate
 * queue rows if both paths run.
 */
function synchub_task_comment_sync_after_native_update($commentId)
{
    $commentId = (int) $commentId;
    if ($commentId <= 0 || !empty($GLOBALS['synchub_receiving_task_comment'])) {
        return false;
    }

    if (!empty($GLOBALS['synchub_task_comment_update_sent'][$commentId])) {
        return true;
    }

    $ok = synchub_enqueue_task_comment_update($commentId);
    if ($ok) {
        if (!isset($GLOBALS['synchub_task_comment_update_sent'])) {
            $GLOBALS['synchub_task_comment_update_sent'] = [];
        }
        $GLOBALS['synchub_task_comment_update_sent'][$commentId] = true;
    }

    return (bool) $ok;
}

hooks()->add_action('task_comment_updated', 'synchub_step202_task_comment_updated');
function synchub_step202_task_comment_updated($data)
{
    if (!empty($GLOBALS['synchub_receiving_task_comment'])) {
        return;
    }

    $commentId = 0;
    if (is_array($data)) {
        $commentId = (int) ($data['comment_id'] ?? 0);
    } elseif (is_object($data) && isset($data->comment_id)) {
        $commentId = (int) $data->comment_id;
    }

    if ($commentId > 0) {
        synchub_task_comment_sync_after_native_update($commentId);
    }
}




/* Step 20.8 - Task Comment DELETE synchronization */
function synchub_enqueue_task_comment_delete($commentId, $taskId, $origin)
{
    $CI = &get_instance();
    $commentId=(int)$commentId; $taskId=(int)$taskId;
    if ($commentId<=0 || !$origin || !empty($GLOBALS['synchub_receiving_task_comment'])) return false;

    $sourceCode=strtoupper(trim((string)$origin->source_company_code));
    if ($sourceCode==='' || $sourceCode==='MARKITO') return false;

    if (synchub_instance_role()==='child') {
        if ($sourceCode!==synchub_instance_code()) return false;
        $destination=synchub_company_by_code((string)synchub_instance_config('master_code'));
    } elseif (synchub_instance_role()==='master') {
        if (!synchub_entity_was_created_on_this_instance(
            'task_comment',
            $commentId,
            (string)$origin->sync_uuid
        )) {
            return false;
        }
        $destination=synchub_company_by_code($sourceCode);
    } else {
        return false;
    }

    if (!$destination) return false;

    $taskOrigin=$taskId>0 ? synchub_get_entity_origin('task',$taskId) : null;
    $payload=[
        'synchub_entity_type'=>'task_comment','comment_id'=>$commentId,
        'source_company_code'=>(string)$origin->source_company_code,
        'source_branch_id'=>$origin->source_branch_id!==null ? (int)$origin->source_branch_id : null,
        'sync_uuid'=>(string)$origin->sync_uuid,
        'parent_task_sync_uuid'=>$taskOrigin ? (string)$taskOrigin->sync_uuid : '',
        'sender_instance_code'=>synchub_instance_code(),'action'=>'delete',
    ];
    $ok=$CI->db->insert(db_prefix().'synchub_queue',[
        'event_id'=>synchub_generate_uuid(),'entity_type'=>'task_comment','entity_id'=>$commentId,'action'=>'delete',
        'company_id'=>(int)$destination->id,'branch_id'=>$origin->source_branch_id!==null ? (int)$origin->source_branch_id : null,
        'payload'=>json_encode($payload),'status'=>'pending','attempts'=>0,'last_error'=>null,
        'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
    ]);
    if (!$ok) return false;
    return synchub_dispatch_task_comment_queue_item((int)$CI->db->insert_id());
}

hooks()->add_action('task_comment_deleted','synchub_step208_task_comment_deleted');
function synchub_step208_task_comment_deleted($data)
{
    if (!empty($GLOBALS['synchub_receiving_task_comment'])) return;
    $commentId=0; $taskId=0;
    if (is_array($data)) { $commentId=(int)($data['comment_id']??0); $taskId=(int)($data['task_id']??0); }
    elseif (is_object($data)) { $commentId=(int)($data->comment_id??0); $taskId=(int)($data->task_id??0); }
    if ($commentId<=0) return;

    $CI=&get_instance();
    $origin=synchub_get_entity_origin('task_comment',$commentId);
    if (!$origin) return;

    $syncUuid=(string)$origin->sync_uuid;
    $sourceCode=strtoupper(trim((string)$origin->source_company_code));

    // SyncHub v9.8:
    // On master, propagate delete only for comments originally created here.
    // Genuine child-origin mirrors remain local-only when deleted in Markito.
    if (synchub_instance_role()==='master') {
        if (synchub_entity_was_created_on_this_instance(
            'task_comment',
            $commentId,
            $syncUuid
        )) {
            synchub_enqueue_task_comment_delete($commentId,$taskId,$origin);
            return;
        }

        $CI->db->where('entity_type','task_comment')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
        $CI->db->where('entity_type','task_comment')->where('local_id',$commentId)->delete(db_prefix().'synchub_entity_origin');
        return;
    }

    if (synchub_instance_role()==='child' && $sourceCode===synchub_instance_code()) {
        synchub_enqueue_task_comment_delete($commentId,$taskId,$origin);
    }
}
