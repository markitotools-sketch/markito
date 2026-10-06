<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Api extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function ping()
    {
        return $this->json_response(['success'=>true,'service'=>'SyncHub','instance'=>function_exists('synchub_instance_code') ? synchub_instance_code() : 'SEEN']);
    }

    public function project()
    {
        if ($this->input->method() !== 'post') return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        $rawBody=(string)$this->input->raw_input_stream;
        if (!$this->verify_request($rawBody)) return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        $payload=json_decode($rawBody,true);
        if (!is_array($payload)) return $this->json_response(['success'=>false,'message'=>'Invalid JSON payload.'],400);

        $action=strtolower(trim((string)($payload['action'] ?? 'create')));
        $sourceId=(int)($payload['project_id'] ?? 0);
        $sourceCode=strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $syncUuid=trim((string)($payload['sync_uuid'] ?? ''));
        if ($sourceId<=0 || $sourceCode==='' || $syncUuid==='') return $this->json_response(['success'=>false,'message'=>'project_id, source_company_code and sync_uuid are required.'],422);

        if ($sourceCode!=='SEEN') return $this->json_response(['success'=>false,'message'=>'Seen only accepts changes for SEEN-origin projects.'],422);

        $map=$this->db->where('entity_type','project')->where('sync_uuid',$syncUuid)->get(db_prefix().'synchub_entity_map')->row();

        if ($action==='delete') {
            if (!$map) return $this->json_response(['success'=>true,'message'=>'Nothing to delete.','sync_uuid'=>$syncUuid]);
            $localId=(int)$map->local_id;
            $this->db->trans_begin();
            $this->db->where('id',$localId)->delete(db_prefix().'projects');
            $this->db->where('entity_type','project')->where('local_id',$localId)->delete(db_prefix().'synchub_entity_origin');
            $this->db->where('id',(int)$map->id)->delete(db_prefix().'synchub_entity_map');
            if ($this->db->trans_status()===false) { $this->db->trans_rollback(); return $this->json_response(['success'=>false,'message'=>'Delete failed.'],500); }
            $this->db->trans_commit();
            return $this->json_response(['success'=>true,'message'=>'Project deleted.','local_project_id'=>$localId,'sync_uuid'=>$syncUuid]);
        }

        $name=trim((string)($payload['name'] ?? ''));
        if ($name==='') return $this->json_response(['success'=>false,'message'=>'Project name is required.'],422);

        if ($action==='update') {
            if (!$map) return $this->json_response(['success'=>false,'message'=>'Project mapping not found.'],404);
            $localId=(int)$map->local_id;
            $data=['name'=>$name];
            if (isset($payload['status'])) $data['status']=(int)$payload['status'];
            if (!empty($payload['start_date'])) $data['start_date']=$payload['start_date'];
            if (array_key_exists('deadline',$payload)) $data['deadline']=!empty($payload['deadline']) ? $payload['deadline'] : null;
            if (array_key_exists('description',$payload)) $data['description']=(string)$payload['description'];
            $ok=$this->db->where('id',$localId)->update(db_prefix().'projects',$data);
            if (!$ok) return $this->json_response(['success'=>false,'message'=>'Project update failed.'],500);
            $this->db->where('id',(int)$map->id)->update(db_prefix().'synchub_entity_map',['updated_at'=>date('Y-m-d H:i:s')]);
            return $this->json_response(['success'=>true,'message'=>'Project updated.','local_project_id'=>$localId,'sync_uuid'=>$syncUuid]);
        }

        if ($action!=='create') return $this->json_response(['success'=>false,'message'=>'Unsupported project action.'],422);
        return $this->json_response(['success'=>false,'message'=>'Master must not create projects in Seen.'],422);
        if ($map) return $this->json_response(['success'=>true,'message'=>'Project already synchronized.','local_project_id'=>(int)$map->local_id,'sync_uuid'=>$syncUuid]);

        $clientId=$this->resolve_client_id((int)($payload['clientid'] ?? 0));
        if ($clientId<=0) return $this->json_response(['success'=>false,'message'=>'No customer exists in this CRM.'],422);

        $projectData=[
            'name'=>$name,'description'=>(string)($payload['description'] ?? ''),'status'=>(int)($payload['status'] ?? 2),
            'clientid'=>$clientId,'billing_type'=>2,'start_date'=>!empty($payload['start_date']) ? $payload['start_date'] : date('Y-m-d'),
            'deadline'=>!empty($payload['deadline']) ? $payload['deadline'] : null,'project_created'=>date('Y-m-d'),'date_finished'=>null,
            'progress'=>0,'progress_from_tasks'=>1,'project_cost'=>0,'project_rate_per_hour'=>0,'estimated_hours'=>null,'addedfrom'=>1,
            'contact_notification'=>1,'notify_contacts'=>serialize([]),
        ];
        $this->db->trans_begin();
        if (!$this->db->insert(db_prefix().'projects',$projectData)) { $this->db->trans_rollback(); return $this->json_response(['success'=>false,'message'=>'Unable to create project.'],500); }
        $localId=(int)$this->db->insert_id();
        $company=$this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) { $this->db->trans_rollback(); return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422); }
        $this->db->insert(db_prefix().'synchub_entity_map',[
            'entity_type'=>'project','local_id'=>$localId,'remote_id'=>$sourceId,'company_id'=>(int)$company->id,
            'branch_id'=>isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null,
            'remote_company_id'=>null,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        $this->db->insert(db_prefix().'synchub_entity_origin',[
            'entity_type'=>'project','local_id'=>$localId,'source_company_code'=>$sourceCode,
            'source_branch_id'=>isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null,
            'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        if ($this->db->trans_status()===false) { $this->db->trans_rollback(); return $this->json_response(['success'=>false,'message'=>'Create transaction failed.'],500); }
        $this->db->trans_commit();
        return $this->json_response(['success'=>true,'message'=>'Project synchronized.','source_project_id'=>$sourceId,'local_project_id'=>$localId,'sync_uuid'=>$syncUuid]);
    }



    public function task()
    {
        // Multipart transport aliases. These are checked before raw JSON
        // authentication because multipart bodies are authenticated against the
        // exact JSON metadata field instead of PHP's raw input stream.
        if ((string)$this->input->post('synchub_entity_type') === 'project_file') {
            return $this->project_file();
        }
        if ((string)$this->input->post('synchub_entity_type') === 'project_discussion_comment_attachment') {
            return $this->project_discussion_comment_attachment();
        }
        if ($this->input->method() !== 'post') {
            return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        }

        $rawBody = (string)$this->input->raw_input_stream;
        if (!$this->verify_request($rawBody)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid JSON payload.'],400);
        }

        if (strtolower(trim((string)($payload['synchub_entity_type'] ?? ''))) === 'project_discussion_comment') {
            return $this->receive_project_discussion_comment($payload);
        }

        if (strtolower(trim((string)($payload['synchub_entity_type'] ?? ''))) === 'task_comment') {
            return $this->receive_task_comment($payload);
        }

        if (strtolower(trim((string)($payload['synchub_entity_type'] ?? ''))) === 'project_discussion') {
            return $this->receive_project_discussion($payload);
        }

        $action       = strtolower(trim((string)($payload['action'] ?? 'create')));
        $sourceTaskId = (int)($payload['task_id'] ?? 0);
        $sourceCode   = strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $senderCode   = strtoupper(trim((string)($payload['sender_instance_code'] ?? $sourceCode)));
        $syncUuid     = trim((string)($payload['sync_uuid'] ?? ''));
        $projectUuid  = trim((string)($payload['parent_project_sync_uuid'] ?? ''));
        $name         = trim((string)($payload['name'] ?? ''));

        if (!in_array($action, ['create','update','delete'], true)) {
            return $this->json_response(['success'=>false,'message'=>'Unsupported task action.'],422);
        }

        if ($sourceTaskId <= 0 || $sourceCode === '' || $syncUuid === '') {
            return $this->json_response([
                'success'=>false,
                'message'=>'task_id, source_company_code and sync_uuid are required.'
            ],422);
        }

        $existingMap = $this->db
            ->where('entity_type','task')
            ->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_map')
            ->row();

        if ($action === 'delete') {
            // Idempotent delete: if mapping is already gone, the desired state is already true.
            if (!$existingMap) {
                return $this->json_response([
                    'success'=>true,
                    'message'=>'Task already absent.',
                    'sync_uuid'=>$syncUuid,
                ]);
            }

            $localTaskId = (int)$existingMap->local_id;
            $taskExists = $this->db->where('id',$localTaskId)->get(db_prefix().'tasks')->row();

            if ($taskExists) {
                $this->load->model('tasks_model');
                $GLOBALS['synchub_receiving_task'] = true;
                try {
                    $deleted = $this->tasks_model->delete_task($localTaskId, true);
                } finally {
                    unset($GLOBALS['synchub_receiving_task']);
                }

                if (!$deleted) {
                    return $this->json_response(['success'=>false,'message'=>'Unable to delete mapped task.'],500);
                }
            }

            $this->db->where('entity_type','task')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
            $this->db->where('entity_type','task')->where('local_id',$localTaskId)->delete(db_prefix().'synchub_entity_origin');

            return $this->json_response([
                'success'=>true,
                'message'=>'Task deleted.',
                'local_task_id'=>$localTaskId,
                'sync_uuid'=>$syncUuid,
            ]);
        }

        if ($projectUuid === '' || $name === '') {
            return $this->json_response([
                'success'=>false,
                'message'=>'name and parent_project_sync_uuid are required for task create/update.'
            ],422);
        }

        $projectOrigin = $this->db
            ->where('entity_type','project')
            ->where('sync_uuid',$projectUuid)
            ->get(db_prefix().'synchub_entity_origin')
            ->row();

        if (!$projectOrigin) {
            return $this->json_response([
                'success'=>false,
                'message'=>'Parent project mapping/origin was not found on this instance.',
                'parent_project_sync_uuid'=>$projectUuid,
            ],404);
        }

        $localProjectId = (int)$projectOrigin->local_id;
        $projectExists = $this->db->where('id',$localProjectId)->get(db_prefix().'projects')->row();
        if (!$projectExists) {
            return $this->json_response(['success'=>false,'message'=>'Mapped parent project does not exist locally.'],404);
        }

        if ($action === 'update') {
            if (!$existingMap) {
                return $this->json_response(['success'=>false,'message'=>'Task mapping was not found for update.'],404);
            }

            $localTaskId = (int)$existingMap->local_id;
            $localTask = $this->db->where('id',$localTaskId)->get(db_prefix().'tasks')->row();
            if (!$localTask) {
                return $this->json_response(['success'=>false,'message'=>'Mapped task does not exist locally.'],404);
            }

            // Keep the task attached to the mapped project; only synchronize core task fields.
            $updateData = [
                'name'              => $name,
                'description'       => (string)($payload['description'] ?? ''),
                'priority'          => (int)($payload['priority'] ?? 2),
                'status'            => (int)($payload['status'] ?? 1),
                'startdate'         => !empty($payload['startdate']) ? $payload['startdate'] : date('Y-m-d'),
                'duedate'           => !empty($payload['duedate']) ? $payload['duedate'] : null,
                'billable'          => !empty($payload['billable']) ? 1 : 0,
                'visible_to_client' => !empty($payload['visible_to_client']) ? 1 : 0,
                'hourly_rate'       => isset($payload['hourly_rate']) ? (float)$payload['hourly_rate'] : 0,
                'rel_type'          => 'project',
                'rel_id'            => $localProjectId,
            ];

            $GLOBALS['synchub_receiving_task'] = true;
            try {
                $updated = $this->db->where('id',$localTaskId)->update(db_prefix().'tasks',$updateData);
            } finally {
                unset($GLOBALS['synchub_receiving_task']);
            }

            if (!$updated) {
                return $this->json_response(['success'=>false,'message'=>'Unable to update mapped task.'],500);
            }

            $this->db->where('entity_type','task')->where('sync_uuid',$syncUuid)->update(db_prefix().'synchub_entity_map', [
                'remote_id'=>$sourceTaskId,
                'updated_at'=>date('Y-m-d H:i:s'),
            ]);
            $this->db->where('entity_type','task')->where('local_id',$localTaskId)->update(db_prefix().'synchub_entity_origin', [
                'updated_at'=>date('Y-m-d H:i:s'),
            ]);


            $assigneeResult = null;
            if (array_key_exists('assignees', $payload)) {
                $assigneeResult = $this->apply_task_assignees($localTaskId, $localProjectId, $senderCode, $payload['assignees']);
                if (empty($assigneeResult['success'])) {
                    return $this->json_response([
                        'success'=>false,
                        'message'=>'Task updated but assignees could not be synchronized.',
                        'assignee_result'=>$assigneeResult,
                    ],500);
                }
            }

            return $this->json_response([
                'success'=>true,
                'message'=>'Task updated.',
                'source_task_id'=>$sourceTaskId,
                'local_task_id'=>$localTaskId,
                'local_project_id'=>$localProjectId,
                'sync_uuid'=>$syncUuid,
                'assignee_result'=>$assigneeResult,
            ]);
        }

        // CREATE
        if ($existingMap) {
            return $this->json_response([
                'success'=>true,
                'message'=>'Task already synchronized.',
                'local_task_id'=>(int)$existingMap->local_id,
                'sync_uuid'=>$syncUuid,
            ]);
        }

        $this->load->model('tasks_model');

        $taskData = [
            'name'               => $name,
            'description'        => (string)($payload['description'] ?? ''),
            'priority'           => (int)($payload['priority'] ?? 2),
            'startdate'          => !empty($payload['startdate']) ? $payload['startdate'] : date('Y-m-d'),
            'duedate'            => !empty($payload['duedate']) ? $payload['duedate'] : '',
            'rel_type'           => 'project',
            'rel_id'             => $localProjectId,
            'billable'           => !empty($payload['billable']) ? 1 : 0,
            'visible_to_client'  => !empty($payload['visible_to_client']) ? 1 : 0,
            'hourly_rate'        => isset($payload['hourly_rate']) ? (float)$payload['hourly_rate'] : 0,
            'milestone'          => 0,
            'repeat_every'       => '',
            'withDefaultAssignee'=> false,
        ];

        $this->db->trans_begin();
        $GLOBALS['synchub_receiving_task'] = true;
        try {
            $localTaskId = (int)$this->tasks_model->add($taskData, false);
        } finally {
            unset($GLOBALS['synchub_receiving_task']);
        }

        if ($localTaskId <= 0) {
            $this->db->trans_rollback();
            return $this->json_response(['success'=>false,'message'=>'Unable to create task.'],500);
        }

        if (isset($payload['status'])) {
            $this->db->where('id',$localTaskId)->update(db_prefix().'tasks', ['status'=>(int)$payload['status']]);
        }


        $assigneeResult = null;
        if (array_key_exists('assignees', $payload)) {
            $assigneeResult = $this->apply_task_assignees($localTaskId, $localProjectId, $senderCode, $payload['assignees']);
            if (empty($assigneeResult['success'])) {
                $this->db->trans_rollback();
                return $this->json_response([
                    'success'=>false,
                    'message'=>'Task created but assignees could not be synchronized.',
                    'assignee_result'=>$assigneeResult,
                ],500);
            }
        }

        $company = $this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) {
            $this->db->trans_rollback();
            return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422);
        }

        $branchId = isset($payload['source_branch_id']) && $payload['source_branch_id'] !== null
            ? (int)$payload['source_branch_id']
            : null;

        $this->db->insert(db_prefix().'synchub_entity_origin', [
            'entity_type'=>'task',
            'local_id'=>$localTaskId,
            'source_company_code'=>$sourceCode,
            'source_branch_id'=>$branchId,
            'sync_uuid'=>$syncUuid,
            'created_at'=>date('Y-m-d H:i:s'),
            'updated_at'=>null,
        ]);

        $this->db->insert(db_prefix().'synchub_entity_map', [
            'entity_type'=>'task',
            'local_id'=>$localTaskId,
            'remote_id'=>$sourceTaskId,
            'company_id'=>(int)$company->id,
            'branch_id'=>$branchId,
            'remote_company_id'=>null,
            'sync_uuid'=>$syncUuid,
            'created_at'=>date('Y-m-d H:i:s'),
            'updated_at'=>null,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->json_response(['success'=>false,'message'=>'Task create transaction failed.'],500);
        }

        $this->db->trans_commit();

        return $this->json_response([
            'success'=>true,
            'message'=>'Task synchronized.',
            'source_task_id'=>$sourceTaskId,
            'local_task_id'=>$localTaskId,
            'local_project_id'=>$localProjectId,
            'sync_uuid'=>$syncUuid,
        ]);
    }


    public function milestone()
    {
        if ($this->input->method() !== 'post') {
            return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        }

        $rawBody = (string)$this->input->raw_input_stream;
        if (!$this->verify_request($rawBody)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid JSON payload.'],400);
        }

        $action            = strtolower(trim((string)($payload['action'] ?? 'create')));
        $sourceMilestoneId = (int)($payload['milestone_id'] ?? 0);
        $sourceCode        = strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $syncUuid          = trim((string)($payload['sync_uuid'] ?? ''));
        $projectUuid       = trim((string)($payload['parent_project_sync_uuid'] ?? ''));
        $name              = trim((string)($payload['name'] ?? ''));

        if (!in_array($action, ['create','update','delete'], true)) {
            return $this->json_response(['success'=>false,'message'=>'Unsupported milestone action.'],422);
        }

        if ($sourceMilestoneId <= 0 || $sourceCode === '' || $syncUuid === '') {
            return $this->json_response([
                'success'=>false,
                'message'=>'milestone_id, source_company_code and sync_uuid are required.'
            ],422);
        }

        $existingMap = $this->db
            ->where('entity_type','milestone')
            ->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_map')
            ->row();

        if ($action === 'delete') {
            // Idempotent delete.
            if (!$existingMap) {
                return $this->json_response([
                    'success'=>true,
                    'message'=>'Milestone already absent.',
                    'sync_uuid'=>$syncUuid,
                ]);
            }

            $localMilestoneId = (int)$existingMap->local_id;
            $this->db->trans_begin();

            $GLOBALS['synchub_receiving_milestone'] = true;
            try {
                $this->db->where('id',$localMilestoneId)->delete(db_prefix().'milestones');
                // Match Perfex native delete behavior for linked tasks.
                $this->db->where('milestone',$localMilestoneId)->update(db_prefix().'tasks', ['milestone'=>0]);
            } finally {
                unset($GLOBALS['synchub_receiving_milestone']);
            }

            $this->db->where('entity_type','milestone')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
            $this->db->where('entity_type','milestone')->where('local_id',$localMilestoneId)->delete(db_prefix().'synchub_entity_origin');

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return $this->json_response(['success'=>false,'message'=>'Milestone delete transaction failed.'],500);
            }
            $this->db->trans_commit();

            return $this->json_response([
                'success'=>true,
                'message'=>'Milestone deleted.',
                'local_milestone_id'=>$localMilestoneId,
                'sync_uuid'=>$syncUuid,
            ]);
        }

        if ($projectUuid === '' || $name === '') {
            return $this->json_response([
                'success'=>false,
                'message'=>'name and parent_project_sync_uuid are required for milestone create/update.'
            ],422);
        }

        $projectOrigin = $this->db
            ->where('entity_type','project')
            ->where('sync_uuid',$projectUuid)
            ->get(db_prefix().'synchub_entity_origin')
            ->row();

        if (!$projectOrigin) {
            return $this->json_response([
                'success'=>false,
                'message'=>'Parent project origin was not found on this instance.',
                'parent_project_sync_uuid'=>$projectUuid,
            ],404);
        }

        $localProjectId = (int)$projectOrigin->local_id;
        $projectExists = $this->db->where('id',$localProjectId)->get(db_prefix().'projects')->row();
        if (!$projectExists) {
            return $this->json_response(['success'=>false,'message'=>'Mapped parent project does not exist locally.'],404);
        }

        $milestoneData = [
            'name'                            => $name,
            // Payload already comes from the milestone DB row; do not nl2br() it again.
            'description'                     => (string)($payload['description'] ?? ''),
            'start_date'                      => !empty($payload['start_date']) ? $payload['start_date'] : date('Y-m-d'),
            'due_date'                        => !empty($payload['due_date']) ? $payload['due_date'] : date('Y-m-d'),
            'project_id'                      => $localProjectId,
            'milestone_order'                 => (int)($payload['milestone_order'] ?? 0),
            'color'                           => (string)($payload['color'] ?? ''),
            'description_visible_to_customer' => !empty($payload['description_visible_to_customer']) ? 1 : 0,
            'hide_from_customer'              => !empty($payload['hide_from_customer']) ? 1 : 0,
        ];

        if ($action === 'update') {
            if (!$existingMap) {
                return $this->json_response(['success'=>false,'message'=>'Milestone mapping was not found for update.'],404);
            }

            $localMilestoneId = (int)$existingMap->local_id;
            $localMilestone = $this->db->where('id',$localMilestoneId)->get(db_prefix().'milestones')->row();
            if (!$localMilestone) {
                return $this->json_response(['success'=>false,'message'=>'Mapped milestone does not exist locally.'],404);
            }

            $GLOBALS['synchub_receiving_milestone'] = true;
            try {
                $updated = $this->db->where('id',$localMilestoneId)->update(db_prefix().'milestones',$milestoneData);
            } finally {
                unset($GLOBALS['synchub_receiving_milestone']);
            }

            if (!$updated) {
                return $this->json_response(['success'=>false,'message'=>'Unable to update mapped milestone.'],500);
            }

            $this->db->where('entity_type','milestone')->where('sync_uuid',$syncUuid)->update(db_prefix().'synchub_entity_map', [
                'remote_id'=>$sourceMilestoneId,
                'updated_at'=>date('Y-m-d H:i:s'),
            ]);
            $this->db->where('entity_type','milestone')->where('local_id',$localMilestoneId)->update(db_prefix().'synchub_entity_origin', [
                'updated_at'=>date('Y-m-d H:i:s'),
            ]);

            return $this->json_response([
                'success'=>true,
                'message'=>'Milestone updated.',
                'source_milestone_id'=>$sourceMilestoneId,
                'local_milestone_id'=>$localMilestoneId,
                'local_project_id'=>$localProjectId,
                'sync_uuid'=>$syncUuid,
            ]);
        }

        // CREATE
        if ($existingMap) {
            return $this->json_response([
                'success'=>true,
                'message'=>'Milestone already synchronized.',
                'local_milestone_id'=>(int)$existingMap->local_id,
                'sync_uuid'=>$syncUuid,
            ]);
        }

        $company = $this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) {
            return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422);
        }

        $milestoneData['datecreated'] = date('Y-m-d');
        $this->db->trans_begin();

        $GLOBALS['synchub_receiving_milestone'] = true;
        try {
            $inserted = $this->db->insert(db_prefix().'milestones', $milestoneData);
            $localMilestoneId = $inserted ? (int)$this->db->insert_id() : 0;
        } finally {
            unset($GLOBALS['synchub_receiving_milestone']);
        }

        if ($localMilestoneId <= 0) {
            $dbError = $this->db->error();
            $this->db->trans_rollback();
            return $this->json_response([
                'success'=>false,
                'message'=>'Unable to create milestone row.',
                'db_error'=>isset($dbError['message']) ? $dbError['message'] : '',
            ],500);
        }

        $branchId = isset($payload['source_branch_id']) && $payload['source_branch_id'] !== null
            ? (int)$payload['source_branch_id']
            : null;

        $this->db->insert(db_prefix().'synchub_entity_origin', [
            'entity_type'=>'milestone',
            'local_id'=>$localMilestoneId,
            'source_company_code'=>$sourceCode,
            'source_branch_id'=>$branchId,
            'sync_uuid'=>$syncUuid,
            'created_at'=>date('Y-m-d H:i:s'),
            'updated_at'=>null,
        ]);

        $this->db->insert(db_prefix().'synchub_entity_map', [
            'entity_type'=>'milestone',
            'local_id'=>$localMilestoneId,
            'remote_id'=>$sourceMilestoneId,
            'company_id'=>(int)$company->id,
            'branch_id'=>$branchId,
            'remote_company_id'=>null,
            'sync_uuid'=>$syncUuid,
            'created_at'=>date('Y-m-d H:i:s'),
            'updated_at'=>null,
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->json_response(['success'=>false,'message'=>'Milestone create transaction failed.'],500);
        }

        $this->db->trans_commit();

        return $this->json_response([
            'success'=>true,
            'message'=>'Milestone synchronized.',
            'source_milestone_id'=>$sourceMilestoneId,
            'local_milestone_id'=>$localMilestoneId,
            'local_project_id'=>$localProjectId,
            'sync_uuid'=>$syncUuid,
        ]);
    }



    public function project_note()
    {
        if ($this->input->method() !== 'post') {
            return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        }

        $rawBody = (string)$this->input->raw_input_stream;
        if (!$this->verify_request($rawBody)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid JSON payload.'],400);
        }

        $action          = strtolower(trim((string)($payload['action'] ?? 'create')));
        $sourceCode      = strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $sourceBranchId  = array_key_exists('source_branch_id', $payload) && $payload['source_branch_id'] !== null
            ? (int)$payload['source_branch_id'] : null;
        $syncUuid        = trim((string)($payload['sync_uuid'] ?? ''));
        $projectSyncUuid = trim((string)($payload['project_sync_uuid'] ?? ''));
        $remoteStaffId   = (int)($payload['source_staff_id'] ?? 0);
        $staffEmail      = strtolower(trim((string)($payload['source_staff_email'] ?? '')));
        $content         = (string)($payload['content'] ?? '');

        if (!in_array($action, ['create','update','delete'], true)) {
            return $this->json_response(['success'=>false,'message'=>'Unsupported project note action.'],422);
        }

        if ($sourceCode === '' || $syncUuid === '' || $projectSyncUuid === '' || $remoteStaffId <= 0 || $staffEmail === '') {
            return $this->json_response([
                'success'=>false,
                'message'=>'source_company_code, sync_uuid, project_sync_uuid, source_staff_id and source_staff_email are required.'
            ],422);
        }

        $projectOrigin = $this->db
            ->where('entity_type', 'project')
            ->where('sync_uuid', $projectSyncUuid)
            ->get(db_prefix().'synchub_entity_origin')
            ->row();

        if (!$projectOrigin) {
            return $this->json_response(['success'=>false,'message'=>'Mapped project origin not found for project note.'],404);
        }

        $localProjectId = (int)$projectOrigin->local_id;
        $localStaffId = 0;

        if (function_exists('synchub_resolve_local_staff_id')) {
            $localStaffId = (int)synchub_resolve_local_staff_id($sourceCode, $remoteStaffId, $staffEmail);
        }

        if ($localStaffId <= 0) {
            $staff = $this->db
                ->where("LOWER(email) = " . $this->db->escape($staffEmail), null, false)
                ->get(db_prefix().'staff')
                ->row();
            if ($staff) {
                $localStaffId = (int)$staff->staffid;
            }
        }

        if ($localStaffId <= 0) {
            return $this->json_response(['success'=>false,'message'=>'Unable to resolve local staff member for project note.'],422);
        }

        $existingMap = $this->db
            ->where('entity_type', 'project_note')
            ->where('sync_uuid', $syncUuid)
            ->get(db_prefix().'synchub_entity_map')
            ->row();

        $note = null;
        if ($existingMap && (int)$existingMap->local_id > 0) {
            $note = $this->db
                ->where('id', (int)$existingMap->local_id)
                ->get(db_prefix().'project_notes')
                ->row();
        }

        if (!$note) {
            $note = $this->db
                ->where('project_id', $localProjectId)
                ->where('staff_id', $localStaffId)
                ->get(db_prefix().'project_notes')
                ->row();
        }

        $GLOBALS['synchub_receiving_project_note'] = true;

        if ($note) {
            $localNoteId = (int)$note->id;
            $ok = $this->db
                ->where('id', $localNoteId)
                ->update(db_prefix().'project_notes', ['content'=>$content]);
        } else {
            $ok = $this->db->insert(db_prefix().'project_notes', [
                'project_id' => $localProjectId,
                'staff_id'   => $localStaffId,
                'content'    => $content,
            ]);
            $localNoteId = $ok ? (int)$this->db->insert_id() : 0;
        }

        unset($GLOBALS['synchub_receiving_project_note']);

        if (!$ok || $localNoteId <= 0) {
            $dbError = $this->db->error();
            return $this->json_response([
                'success'=>false,
                'message'=>'Unable to save synchronized project note.',
                'db_error'=>isset($dbError['message']) ? $dbError['message'] : '',
            ],500);
        }

        $localOrigin = $this->db
            ->where('entity_type', 'project_note')
            ->where('local_id', $localNoteId)
            ->get(db_prefix().'synchub_entity_origin')
            ->row();

        if ($localOrigin && (string)$localOrigin->sync_uuid !== $syncUuid) {
            $this->db->where('entity_type','project_note')->where('sync_uuid',(string)$localOrigin->sync_uuid)
                ->delete(db_prefix().'synchub_entity_map');
            $this->db->where('id',(int)$localOrigin->id)->update(db_prefix().'synchub_entity_origin', [
                'source_company_code'=>$sourceCode,
                'source_branch_id'=>$sourceBranchId,
                'sync_uuid'=>$syncUuid,
                'updated_at'=>date('Y-m-d H:i:s'),
            ]);
        } elseif (!$localOrigin) {
            $this->db->insert(db_prefix().'synchub_entity_origin', [
                'entity_type'=>'project_note',
                'local_id'=>$localNoteId,
                'source_company_code'=>$sourceCode,
                'source_branch_id'=>$sourceBranchId,
                'sync_uuid'=>$syncUuid,
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>null,
            ]);
        }

        $sourceCompany = $this->db
            ->where('code', $sourceCode)
            ->get(db_prefix().'synchub_companies')
            ->row();
        $companyId = $sourceCompany ? (int)$sourceCompany->id : 0;

        if ($existingMap) {
            $this->db->where('id',(int)$existingMap->id)->update(db_prefix().'synchub_entity_map', [
                'local_id'=>$localNoteId,
                'remote_id'=>(int)($payload['note_id'] ?? 0),
                'company_id'=>$companyId,
                'branch_id'=>$sourceBranchId,
                'updated_at'=>date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->insert(db_prefix().'synchub_entity_map', [
                'entity_type'=>'project_note',
                'local_id'=>$localNoteId,
                'remote_id'=>(int)($payload['note_id'] ?? 0),
                'company_id'=>$companyId,
                'branch_id'=>$sourceBranchId,
                'remote_company_id'=>null,
                'sync_uuid'=>$syncUuid,
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>null,
            ]);
        }

        return $this->json_response([
            'success'=>true,
            'message'=>'Project note synchronized.',
            'local_note_id'=>$localNoteId,
            'local_project_id'=>$localProjectId,
            'local_staff_id'=>$localStaffId,
            'sync_uuid'=>$syncUuid,
        ]);
    }


    public function timesheet()
    {
        if ($this->input->method() !== 'post') {
            return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        }

        $rawBody=(string)$this->input->raw_input_stream;
        if (!$this->verify_request($rawBody)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        }
        $payload=json_decode($rawBody,true);
        if (!is_array($payload)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid JSON payload.'],400);
        }

        $action=strtolower(trim((string)($payload['action'] ?? 'create')));
        $sourceId=(int)($payload['timesheet_id'] ?? 0);
        $sourceCode=strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $syncUuid=trim((string)($payload['sync_uuid'] ?? ''));
        $taskUuid=trim((string)($payload['parent_task_sync_uuid'] ?? ''));
        $remoteStaffId=(int)($payload['source_staff_id'] ?? 0);
        $staffEmail=strtolower(trim((string)($payload['staff_email'] ?? '')));

        if (!in_array($action,['create','update','delete'],true)) {
            return $this->json_response(['success'=>false,'message'=>'Unsupported timesheet action.'],422);
        }
        if ($sourceId<=0 || $sourceCode==='' || $syncUuid==='') {
            return $this->json_response(['success'=>false,'message'=>'timesheet_id, source_company_code and sync_uuid are required.'],422);
        }

        $existingMap=$this->db->where('entity_type','timesheet')->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_map')->row();

        if ($action === 'delete') {
            if (!$existingMap) {
                return $this->json_response(['success'=>true,'message'=>'Timesheet already absent.','sync_uuid'=>$syncUuid]);
            }
            $localId=(int)$existingMap->local_id;
            $GLOBALS['synchub_receiving_timesheet']=true;
            try {
                $this->db->where('id',$localId)->delete(db_prefix().'taskstimers');
                $this->db->where('rel_id',$localId)->where('rel_type','timesheet')->delete(db_prefix().'taggables');
            } finally {
                unset($GLOBALS['synchub_receiving_timesheet']);
            }
            $this->db->where('entity_type','timesheet')->where('local_id',$localId)->delete(db_prefix().'synchub_entity_origin');
            $this->db->where('id',(int)$existingMap->id)->delete(db_prefix().'synchub_entity_map');
            return $this->json_response(['success'=>true,'message'=>'Timesheet deleted.','local_timesheet_id'=>$localId,'sync_uuid'=>$syncUuid]);
        }

        if ($taskUuid==='' || $staffEmail==='') {
            return $this->json_response(['success'=>false,'message'=>'parent_task_sync_uuid and staff_email are required for create/update.'],422);
        }

        $taskOrigin=$this->db->where('entity_type','task')->where('sync_uuid',$taskUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if (!$taskOrigin) {
            return $this->json_response(['success'=>false,'message'=>'Mapped task origin not found for timesheet.'],404);
        }
        $localTaskId=(int)$taskOrigin->local_id;
        $task=$this->db->where('id',$localTaskId)->get(db_prefix().'tasks')->row();
        if (!$task || $task->rel_type!=='project') {
            return $this->json_response(['success'=>false,'message'=>'Mapped task does not exist or is not a project task.'],404);
        }

        $localStaffId=0;
        if (function_exists('synchub_resolve_local_staff_id')) {
            $localStaffId=(int)synchub_resolve_local_staff_id($sourceCode,$remoteStaffId,$staffEmail);
        }
        if ($localStaffId<=0) {
            $staff=$this->db->where("LOWER(email) = ".$this->db->escape($staffEmail),null,false)
                ->get(db_prefix().'staff')->row();
            if ($staff) $localStaffId=(int)$staff->staffid;
        }
        if ($localStaffId<=0) {
            return $this->json_response(['success'=>false,'message'=>'Unable to resolve local staff member for timesheet.'],422);
        }

        $startTime=(int)($payload['start_time'] ?? 0);
        $endTime=array_key_exists('end_time',$payload) && $payload['end_time']!==null ? (int)$payload['end_time'] : null;
        if ($startTime<=0) return $this->json_response(['success'=>false,'message'=>'Invalid timesheet start_time.'],422);
        if ($endTime !== null && $endTime < $startTime) return $this->json_response(['success'=>false,'message'=>'Timesheet end_time is earlier than start_time.'],422);

        $data=[
            'start_time'=>$startTime,
            'end_time'=>$endTime,
            'staff_id'=>$localStaffId,
            'task_id'=>$localTaskId,
            'hourly_rate'=>isset($payload['hourly_rate']) ? (float)$payload['hourly_rate'] : 0,
            'note'=>array_key_exists('note',$payload) ? $payload['note'] : null,
        ];

        if ($action === 'update') {
            if (!$existingMap) return $this->json_response(['success'=>false,'message'=>'Timesheet mapping not found.'],404);
            $localId=(int)$existingMap->local_id;
            if (!$this->db->where('id',$localId)->get(db_prefix().'taskstimers')->row()) {
                return $this->json_response(['success'=>false,'message'=>'Mapped local timesheet no longer exists.'],404);
            }
            $GLOBALS['synchub_receiving_timesheet']=true;
            try {
                $ok=$this->db->where('id',$localId)->update(db_prefix().'taskstimers',$data);
            } finally {
                unset($GLOBALS['synchub_receiving_timesheet']);
            }
            if (!$ok) return $this->json_response(['success'=>false,'message'=>'Unable to update synchronized timesheet.'],500);
            $this->db->where('id',(int)$existingMap->id)->update(db_prefix().'synchub_entity_map',[
                'remote_id'=>$sourceId,'updated_at'=>date('Y-m-d H:i:s')
            ]);
            $this->db->where('entity_type','timesheet')->where('local_id',$localId)->update(db_prefix().'synchub_entity_origin',[
                'updated_at'=>date('Y-m-d H:i:s')
            ]);
            return $this->json_response(['success'=>true,'message'=>'Timesheet updated.',
                'local_timesheet_id'=>$localId,'local_task_id'=>$localTaskId,'local_staff_id'=>$localStaffId,'sync_uuid'=>$syncUuid]);
        }

        if ($existingMap) {
            return $this->json_response(['success'=>true,'message'=>'Timesheet already synchronized.',
                'local_timesheet_id'=>(int)$existingMap->local_id,'sync_uuid'=>$syncUuid]);
        }

        $GLOBALS['synchub_receiving_timesheet']=true;
        try {
            $ok=$this->db->insert(db_prefix().'taskstimers',$data);
            $localId=$ok ? (int)$this->db->insert_id() : 0;
        } finally {
            unset($GLOBALS['synchub_receiving_timesheet']);
        }

        if (!$ok || $localId<=0) {
            $dbError=$this->db->error();
            return $this->json_response(['success'=>false,'message'=>'Unable to create synchronized timesheet.',
                'db_error'=>isset($dbError['message'])?$dbError['message']:''],500);
        }

        $sourceBranchId=isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null;
        $this->db->insert(db_prefix().'synchub_entity_origin',[
            'entity_type'=>'timesheet','local_id'=>$localId,'source_company_code'=>$sourceCode,
            'source_branch_id'=>$sourceBranchId,'sync_uuid'=>$syncUuid,
            'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        $sourceCompany=$this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        $this->db->insert(db_prefix().'synchub_entity_map',[
            'entity_type'=>'timesheet','local_id'=>$localId,'remote_id'=>$sourceId,
            'company_id'=>$sourceCompany ? (int)$sourceCompany->id : 0,
            'branch_id'=>$sourceBranchId,'remote_company_id'=>null,'sync_uuid'=>$syncUuid,
            'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);

        return $this->json_response(['success'=>true,'message'=>'Timesheet synchronized.',
            'local_timesheet_id'=>$localId,'local_task_id'=>$localTaskId,'local_staff_id'=>$localStaffId,'sync_uuid'=>$syncUuid]);
    }


    public function staff_resolve()
    {
        if ($this->input->method() !== 'post') {
            return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        }

        $rawBody = (string)$this->input->raw_input_stream;
        if (!$this->verify_request($rawBody)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid JSON payload.'],400);
        }

        $sourceCode = strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $remoteStaffId = (int)($payload['remote_staff_id'] ?? 0);
        $email = strtolower(trim((string)($payload['email'] ?? '')));

        if ($sourceCode === '' || $remoteStaffId <= 0 || $email === '') {
            return $this->json_response(['success'=>false,'message'=>'source_company_code, remote_staff_id and email are required.'],422);
        }

        $staff = $this->db
            ->where("LOWER(email) = " . $this->db->escape($email), null, false)
            ->get(db_prefix().'staff')
            ->row();

        if (!$staff) {
            return $this->json_response([
                'success'=>false,
                'message'=>'No local staff member matches this email.',
                'email'=>$email,
            ],404);
        }

        if (function_exists('synchub_upsert_staff_map')) {
            synchub_upsert_staff_map($sourceCode, (int)$staff->staffid, $remoteStaffId, $email);
        }

        return $this->json_response([
            'success'=>true,
            'message'=>'Staff mapping resolved.',
            'local_staff_id'=>(int)$staff->staffid,
            'remote_staff_id'=>$remoteStaffId,
            'email'=>$email,
            'source_company_code'=>$sourceCode,
        ]);
    }

    private function apply_task_assignees($taskId, $projectId, $senderCompanyCode, $assignees)
    {
        $taskId = (int)$taskId;
        $projectId = (int)$projectId;
        $senderCompanyCode = strtoupper(trim((string)$senderCompanyCode));

        if (!is_array($assignees)) {
            return ['success'=>false,'message'=>'Invalid assignees payload.'];
        }
        if ($taskId <= 0 || $projectId <= 0 || $senderCompanyCode === '') {
            return ['success'=>false,'message'=>'Invalid task/project/sender values.'];
        }

        $desired = [];
        $resolved = [];
        $unresolved = [];

        // Resolve every remote staff member before changing current assignments.
        foreach ($assignees as $assignee) {
            if (!is_array($assignee)) {
                continue;
            }

            $remoteStaffId = (int)($assignee['source_staff_id'] ?? 0);
            $email = strtolower(trim((string)($assignee['email'] ?? '')));
            $localStaffId = 0;

            if ($remoteStaffId > 0 && $this->db->table_exists(db_prefix().'synchub_staff_map')) {
                $map = $this->db
                    ->where('remote_company_code', $senderCompanyCode)
                    ->where('remote_staff_id', $remoteStaffId)
                    ->get(db_prefix().'synchub_staff_map')
                    ->row();
                if ($map) {
                    $localStaffId = (int)$map->local_staff_id;
                }
            }

            if ($localStaffId <= 0 && $email !== '') {
                $staff = $this->db
                    ->where("LOWER(email) = " . $this->db->escape($email), null, false)
                    ->get(db_prefix().'staff')
                    ->row();
                if ($staff) {
                    $localStaffId = (int)$staff->staffid;

                    if ($remoteStaffId > 0 && $this->db->table_exists(db_prefix().'synchub_staff_map')) {
                        $existing = $this->db
                            ->where('remote_company_code', $senderCompanyCode)
                            ->where('remote_staff_id', $remoteStaffId)
                            ->get(db_prefix().'synchub_staff_map')
                            ->row();
                        $mapData = [
                            'remote_company_code'=>$senderCompanyCode,
                            'local_staff_id'=>$localStaffId,
                            'remote_staff_id'=>$remoteStaffId,
                            'staff_email'=>$email,
                            'updated_at'=>date('Y-m-d H:i:s'),
                        ];
                        if ($existing) {
                            $this->db->where('id',(int)$existing->id)->update(db_prefix().'synchub_staff_map',$mapData);
                        } else {
                            $mapData['created_at']=date('Y-m-d H:i:s');
                            $this->db->insert(db_prefix().'synchub_staff_map',$mapData);
                        }
                    }
                }
            }

            if ($localStaffId <= 0) {
                $unresolved[] = ['source_staff_id'=>$remoteStaffId,'email'=>$email];
                continue;
            }

            $desired[$localStaffId] = true;
            $resolved[] = ['source_staff_id'=>$remoteStaffId,'local_staff_id'=>$localStaffId,'email'=>$email];
        }

        if (!empty($unresolved)) {
            return [
                'success'=>false,
                'message'=>'One or more assignees could not be resolved locally.',
                'resolved'=>$resolved,
                'unresolved'=>$unresolved,
            ];
        }

        $this->db->trans_begin();
        $GLOBALS['synchub_receiving_task_assignees'] = true;
        try {
            foreach (array_keys($desired) as $localStaffId) {
                $projectMember = $this->db
                    ->where('project_id',$projectId)
                    ->where('staff_id',(int)$localStaffId)
                    ->get(db_prefix().'project_members')
                    ->row();
                if (!$projectMember) {
                    $this->db->insert(db_prefix().'project_members', [
                        'project_id'=>$projectId,
                        'staff_id'=>(int)$localStaffId,
                    ]);
                }

                $taskAssignee = $this->db
                    ->where('taskid',$taskId)
                    ->where('staffid',(int)$localStaffId)
                    ->get(db_prefix().'task_assigned')
                    ->row();
                if (!$taskAssignee) {
                    $ok = $this->db->insert(db_prefix().'task_assigned', [
                        'staffid'=>(int)$localStaffId,
                        'taskid'=>$taskId,
                        'assigned_from'=>(int)$localStaffId,
                        'is_assigned_from_contact'=>0,
                    ]);
                    if (!$ok) {
                        $dbError = $this->db->error();
                        throw new Exception(isset($dbError['message']) ? $dbError['message'] : 'Unable to insert task assignee.');
                    }
                }
            }

            $existingRows = $this->db
                ->select('id,staffid')
                ->where('taskid',$taskId)
                ->get(db_prefix().'task_assigned')
                ->result();
            foreach ($existingRows as $row) {
                if (!isset($desired[(int)$row->staffid])) {
                    $this->db->where('id',(int)$row->id)->delete(db_prefix().'task_assigned');
                }
            }
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            unset($GLOBALS['synchub_receiving_task_assignees']);
            return ['success'=>false,'message'=>$e->getMessage(),'resolved'=>$resolved];
        }
        unset($GLOBALS['synchub_receiving_task_assignees']);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $dbError = $this->db->error();
            return [
                'success'=>false,
                'message'=>isset($dbError['message']) ? $dbError['message'] : 'Assignee transaction failed.',
                'resolved'=>$resolved,
            ];
        }

        $this->db->trans_commit();
        return [
            'success'=>true,
            'task_id'=>$taskId,
            'project_id'=>$projectId,
            'assigned_staff_ids'=>array_map('intval', array_keys($desired)),
            'resolved'=>$resolved,
        ];
    }



    public function project_file()
    {
        if ($this->input->method() !== 'post') {
            return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        }
        $rawBody=(string)$this->input->post('metadata');
        if ($rawBody==='' || !$this->verify_request($rawBody)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        }
        $payload=json_decode($rawBody,true);
        if (!is_array($payload)) return $this->json_response(['success'=>false,'message'=>'Invalid project file metadata.'],400);
        $action=strtolower(trim((string)($payload['action'] ?? 'create')));
        if (!in_array($action,['create','delete'],true)) return $this->json_response(['success'=>false,'message'=>'Unsupported project file action.'],422);

        $sourceFileId=(int)($payload['project_file_id'] ?? 0);
        $sourceCode=strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $syncUuid=trim((string)($payload['sync_uuid'] ?? ''));
        $projectUuid=trim((string)($payload['parent_project_sync_uuid'] ?? ''));
        if ($sourceFileId<=0 || $sourceCode==='' || $syncUuid==='' || $projectUuid==='') {
            return $this->json_response(['success'=>false,'message'=>'project_file_id, source_company_code, sync_uuid and parent_project_sync_uuid are required.'],422);
        }

        $existing=$this->db->where('entity_type','project_file')->where('sync_uuid',$syncUuid)->get(db_prefix().'synchub_entity_map')->row();

        if ($action==='delete') {
            if (!$existing) return $this->json_response(['success'=>true,'message'=>'Nothing to delete.','sync_uuid'=>$syncUuid]);
            $localFileId=(int)$existing->local_id;
            $GLOBALS['synchub_receiving_project_file']=true;
            try {
                $localFile=$this->db->where('id',$localFileId)->get(db_prefix().'project_files')->row();
                if ($localFile) {
                    $this->load->model('projects_model');
                    if (!$this->projects_model->remove_file($localFileId)) {
                        return $this->json_response(['success'=>false,'message'=>'Unable to delete mapped project file.'],500);
                    }
                }
            } finally {
                unset($GLOBALS['synchub_receiving_project_file']);
            }
            $this->db->where('entity_type','project_file')->where('local_id',$localFileId)->delete(db_prefix().'synchub_entity_origin');
            $this->db->where('id',(int)$existing->id)->delete(db_prefix().'synchub_entity_map');
            return $this->json_response(['success'=>true,'message'=>'Project file deleted.','local_file_id'=>$localFileId,'sync_uuid'=>$syncUuid]);
        }

        if ($existing) return $this->json_response(['success'=>true,'message'=>'Project file already synchronized.','local_file_id'=>(int)$existing->local_id,'sync_uuid'=>$syncUuid]);

        $projectOrigin=$this->db->where('entity_type','project')->where('sync_uuid',$projectUuid)->get(db_prefix().'synchub_entity_origin')->row();
        if (!$projectOrigin) return $this->json_response(['success'=>false,'message'=>'Parent project origin was not found.'],404);
        $localProjectId=(int)$projectOrigin->local_id;
        if (!$this->db->where('id',$localProjectId)->get(db_prefix().'projects')->row()) return $this->json_response(['success'=>false,'message'=>'Mapped parent project does not exist.'],404);

        if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            return $this->json_response(['success'=>false,'message'=>'Physical project file was not received.'],422);
        }
        if ((int)($_FILES['file']['error'] ?? UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) {
            return $this->json_response(['success'=>false,'message'=>'Project file upload error: '.(int)$_FILES['file']['error']],422);
        }

        $original=trim((string)($payload['original_file_name'] ?? $_FILES['file']['name'] ?? 'file'));
        $original=basename(str_replace('\\','/',$original));
        if ($original==='') $original='file';
        $extension=pathinfo($original,PATHINFO_EXTENSION);
        if ($extension==='') $extension=pathinfo((string)($_FILES['file']['name'] ?? ''),PATHINFO_EXTENSION);
        $storedName=app_generate_hash().($extension!=='' ? '.'.$extension : '');
        if (function_exists('_upload_extension_allowed') && !_upload_extension_allowed($storedName)) {
            return $this->json_response(['success'=>false,'message'=>'Project file extension is not allowed.'],422);
        }

        $dir=get_upload_path_by_type('project').$localProjectId.'/';
        if (!is_dir($dir) && !@mkdir($dir,0755,true) && !is_dir($dir)) return $this->json_response(['success'=>false,'message'=>'Unable to create project upload directory.'],500);
        $fullPath=$dir.$storedName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'],$fullPath)) return $this->json_response(['success'=>false,'message'=>'Unable to store project file.'],500);

        $staffId=0;
        $staffEmail=trim((string)($payload['staff_email'] ?? ''));
        if ($staffEmail!=='') {
            $staff=$this->db->where('LOWER(email)',strtolower($staffEmail))->get(db_prefix().'staff')->row();
            if ($staff) $staffId=(int)$staff->staffid;
        }
        $company=$this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) { @unlink($fullPath); return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422); }

        $row=[
            'project_id'=>$localProjectId,'file_name'=>$storedName,'original_file_name'=>$original,
            'filetype'=>(string)($payload['filetype'] ?? $_FILES['file']['type'] ?? 'application/octet-stream'),
            'dateadded'=>!empty($payload['dateadded']) ? (string)$payload['dateadded'] : date('Y-m-d H:i:s'),
            'staffid'=>$staffId,'contact_id'=>0,
            'subject'=>trim((string)($payload['subject'] ?? ''))!=='' ? (string)$payload['subject'] : $original,
            'visible_to_customer'=>!empty($payload['visible_to_customer']) ? 1 : 0,
        ];

        $this->db->trans_begin();
        $GLOBALS['synchub_receiving_project_file']=true;
        try {
            $inserted=$this->db->insert(db_prefix().'project_files',$row);
            $localFileId=$inserted ? (int)$this->db->insert_id() : 0;
        } finally { unset($GLOBALS['synchub_receiving_project_file']); }
        if ($localFileId<=0) { $this->db->trans_rollback(); @unlink($fullPath); return $this->json_response(['success'=>false,'message'=>'Unable to insert project file row.'],500); }

        $branchId=isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null;
        $this->db->insert(db_prefix().'synchub_entity_origin',[
            'entity_type'=>'project_file','local_id'=>$localFileId,'source_company_code'=>$sourceCode,
            'source_branch_id'=>$branchId,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        $this->db->insert(db_prefix().'synchub_entity_map',[
            'entity_type'=>'project_file','local_id'=>$localFileId,'remote_id'=>$sourceFileId,'company_id'=>(int)$company->id,
            'branch_id'=>$branchId,'remote_company_id'=>null,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        if ($this->db->trans_status()===false) { $this->db->trans_rollback(); @unlink($fullPath); return $this->json_response(['success'=>false,'message'=>'Project file create transaction failed.'],500); }
        $this->db->trans_commit();

        if (function_exists('is_image') && is_image($fullPath) && function_exists('create_img_thumb')) create_img_thumb($dir,$storedName);
        return $this->json_response(['success'=>true,'message'=>'Project file synchronized.','source_file_id'=>$sourceFileId,'local_file_id'=>$localFileId,'local_project_id'=>$localProjectId,'sync_uuid'=>$syncUuid]);
    }


    /**
     * Step 19 - receive a discussion comment that contains a physical attachment.
     * Perfex stores the attachment filename directly on tblprojectdiscussioncomments
     * and the binary under uploads/discussions/{discussion_id}/.
     */
    private function project_discussion_comment_attachment()
    {
        if ($this->input->method() !== 'post') {
            return $this->json_response(['success'=>false,'message'=>'POST request required.'],405);
        }

        $rawBody=(string)$this->input->post('metadata');
        if ($rawBody==='' || !$this->verify_request($rawBody)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid SyncHub authentication.'],401);
        }
        $payload=json_decode($rawBody,true);
        if (!is_array($payload)) {
            return $this->json_response(['success'=>false,'message'=>'Invalid discussion comment attachment metadata.'],400);
        }
        if (strtolower(trim((string)($payload['action'] ?? 'create'))) !== 'create') {
            return $this->json_response(['success'=>false,'message'=>'Only create is supported for discussion comment attachment transport.'],422);
        }

        $sourceCommentId=(int)($payload['comment_id'] ?? 0);
        $sourceCode=strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $senderCode=strtoupper(trim((string)($payload['sender_instance_code'] ?? $sourceCode)));
        $syncUuid=trim((string)($payload['sync_uuid'] ?? ''));
        $discussionUuid=trim((string)($payload['parent_discussion_sync_uuid'] ?? ''));
        if ($sourceCommentId<=0 || $sourceCode==='' || $syncUuid==='' || $discussionUuid==='') {
            return $this->json_response(['success'=>false,'message'=>'comment_id, source_company_code, sync_uuid and parent_discussion_sync_uuid are required.'],422);
        }

        $existingOrigin=$this->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if ($existingOrigin) {
            $existingId=(int)$existingOrigin->local_id;
            if ($this->db->where('id',$existingId)->get(db_prefix().'projectdiscussioncomments')->row()) {
                return $this->json_response(['success'=>true,'message'=>'Discussion comment attachment already synchronized.','local_comment_id'=>$existingId,'sync_uuid'=>$syncUuid]);
            }
        }

        $discussionOrigin=$this->db->where('entity_type','project_discussion')->where('sync_uuid',$discussionUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if (!$discussionOrigin) return $this->json_response(['success'=>false,'message'=>'Parent discussion origin was not found.'],404);
        $localDiscussionId=(int)$discussionOrigin->local_id;
        if (!$this->db->where('id',$localDiscussionId)->get(db_prefix().'projectdiscussions')->row()) {
            return $this->json_response(['success'=>false,'message'=>'Mapped parent discussion does not exist.'],404);
        }

        if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            return $this->json_response(['success'=>false,'message'=>'Physical discussion comment attachment was not received.'],422);
        }
        if ((int)($_FILES['file']['error'] ?? UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) {
            return $this->json_response(['success'=>false,'message'=>'Discussion comment attachment upload error: '.(int)$_FILES['file']['error']],422);
        }

        $original=trim((string)($payload['attachment_name'] ?? $_FILES['file']['name'] ?? 'attachment'));
        $original=basename(str_replace('\\','/',$original));
        if ($original==='') $original='attachment';
        if (function_exists('_upload_extension_allowed') && !_upload_extension_allowed($original)) {
            return $this->json_response(['success'=>false,'message'=>'Discussion comment attachment extension is not allowed.'],422);
        }

        $dir=PROJECT_DISCUSSION_ATTACHMENT_FOLDER.$localDiscussionId.'/';
        if (!is_dir($dir) && !@mkdir($dir,0755,true) && !is_dir($dir)) {
            return $this->json_response(['success'=>false,'message'=>'Unable to create discussion attachment directory.'],500);
        }
        $storedName=function_exists('unique_filename') ? unique_filename($dir,$original) : $original;
        $fullPath=$dir.$storedName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'],$fullPath)) {
            return $this->json_response(['success'=>false,'message'=>'Unable to store discussion comment attachment.'],500);
        }

        $localStaffId=0;
        $remoteStaffId=(int)($payload['source_staff_id']??0);
        $staffEmail=trim((string)($payload['staff_email']??''));
        if (function_exists('synchub_resolve_local_staff_id')) {
            $localStaffId=(int)synchub_resolve_local_staff_id($senderCode,$remoteStaffId,$staffEmail);
        }
        if ($localStaffId<=0 && $staffEmail!=='') {
            $staff=$this->db->where('LOWER(email)',strtolower($staffEmail))->get(db_prefix().'staff')->row();
            if ($staff) $localStaffId=(int)$staff->staffid;
        }
        if ($localStaffId<=0) {
            @unlink($fullPath);
            return $this->json_response(['success'=>false,'message'=>'Unable to resolve local staff member for discussion comment attachment.'],422);
        }

        $localParent=null;
        $parentUuid=trim((string)($payload['parent_comment_sync_uuid']??''));
        if ($parentUuid!=='') {
            $parentOrigin=$this->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$parentUuid)
                ->get(db_prefix().'synchub_entity_origin')->row();
            if ($parentOrigin) $localParent=(int)$parentOrigin->local_id;
        }

        $company=$this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) {
            @unlink($fullPath);
            return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422);
        }

        $mime=trim((string)($payload['attachment_mime_type'] ?? $_FILES['file']['type'] ?? ''));
        if ($mime==='') $mime=function_exists('get_mime_by_extension') ? (string)get_mime_by_extension($storedName) : 'application/octet-stream';
        if ($mime==='') $mime='application/octet-stream';
        $branchId=isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null;

        $row=[
            'discussion_id'=>$localDiscussionId,
            'discussion_type'=>'regular',
            'parent'=>$localParent,
            'created'=>!empty($payload['created'])?(string)$payload['created']:date('Y-m-d H:i:s'),
            'modified'=>!empty($payload['modified'])?(string)$payload['modified']:null,
            'content'=>(string)($payload['content']??''),
            'staff_id'=>$localStaffId,
            'contact_id'=>0,
            'fullname'=>!empty($payload['fullname'])?(string)$payload['fullname']:get_staff_full_name($localStaffId),
            'file_name'=>$storedName,
            'file_mime_type'=>$mime,
        ];

        $this->db->trans_begin();
        $GLOBALS['synchub_receiving_project_discussion_comment']=true;
        try {
            $ok=$this->db->insert(db_prefix().'projectdiscussioncomments',$row);
            $localCommentId=$ok?(int)$this->db->insert_id():0;
            if ($localCommentId>0) {
                $this->db->where('id',$localDiscussionId)->update(db_prefix().'projectdiscussions',['last_activity'=>$row['created']]);
                $this->db->insert(db_prefix().'synchub_entity_origin',[
                    'entity_type'=>'project_discussion_comment','local_id'=>$localCommentId,'source_company_code'=>$sourceCode,
                    'source_branch_id'=>$branchId,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
                ]);
                $this->db->insert(db_prefix().'synchub_entity_map',[
                    'entity_type'=>'project_discussion_comment','local_id'=>$localCommentId,'remote_id'=>$sourceCommentId,
                    'company_id'=>(int)$company->id,'branch_id'=>$branchId,'remote_company_id'=>null,'sync_uuid'=>$syncUuid,
                    'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
                ]);
            }
        } finally {
            unset($GLOBALS['synchub_receiving_project_discussion_comment']);
        }

        if (empty($ok) || empty($localCommentId) || $this->db->trans_status()===false) {
            $this->db->trans_rollback();
            @unlink($fullPath);
            return $this->json_response(['success'=>false,'message'=>'Unable to create synchronized discussion comment attachment.'],500);
        }
        $this->db->trans_commit();
        return $this->json_response(['success'=>true,'message'=>'Discussion comment attachment synchronized.','source_comment_id'=>$sourceCommentId,'local_comment_id'=>$localCommentId,'sync_uuid'=>$syncUuid]);
    }


    /**
     * Step 20 - Receive plain text task comments.
     * Attachments are intentionally handled in a later step.
     */
    private function receive_task_comment(array $payload)
    {
        $action = strtolower(trim((string)($payload['action'] ?? 'create')));
        if (!in_array($action, ['create','update','delete'], true)) {
            return $this->json_response(['success'=>false,'message'=>'Unsupported task comment action.'],422);
        }

        $sourceCommentId = (int)($payload['comment_id'] ?? 0);
        $sourceCode = strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $senderCode = strtoupper(trim((string)($payload['sender_instance_code'] ?? $sourceCode)));
        $syncUuid = trim((string)($payload['sync_uuid'] ?? ''));
        $taskUuid = trim((string)($payload['parent_task_sync_uuid'] ?? ''));
        $content = (string)($payload['content'] ?? '');

        if ($sourceCommentId <= 0 || $sourceCode === '' || $syncUuid === '') {
            return $this->json_response(['success'=>false,'message'=>'comment_id, source_company_code and sync_uuid are required.'],422);
        }

        if ($action === 'delete') {
            // SyncHub API receiver: do not call Tasks_model::remove_comment().
            // remove_comment() evaluates session-based staff permissions before
            // the $force flag, which breaks in stateless API requests.
            $existingOrigin = $this->db
                ->where('entity_type', 'task_comment')
                ->where('sync_uuid', $syncUuid)
                ->get(db_prefix() . 'synchub_entity_origin')
                ->row();

            $existingMap = $this->db
                ->where('entity_type', 'task_comment')
                ->where('sync_uuid', $syncUuid)
                ->get(db_prefix() . 'synchub_entity_map')
                ->row();

            $localCommentId = 0;
            if ($existingOrigin) {
                $localCommentId = (int) $existingOrigin->local_id;
            } elseif ($existingMap && isset($existingMap->local_id)) {
                $localCommentId = (int) $existingMap->local_id;
            }

            $GLOBALS['synchub_receiving_task_comment'] = true;
            $this->db->trans_begin();

            try {
                if ($localCommentId > 0) {
                    $this->db
                        ->where('id', $localCommentId)
                        ->delete(db_prefix() . 'task_comments');
                }

                // Idempotent cleanup so retries also finish successfully.
                $this->db
                    ->where('entity_type', 'task_comment')
                    ->where('sync_uuid', $syncUuid)
                    ->delete(db_prefix() . 'synchub_entity_map');

                $this->db
                    ->where('entity_type', 'task_comment')
                    ->where('sync_uuid', $syncUuid)
                    ->delete(db_prefix() . 'synchub_entity_origin');

                if ($this->db->trans_status() === false) {
                    throw new \RuntimeException('Task comment delete transaction failed.');
                }

                $this->db->trans_commit();
            } catch (\Throwable $e) {
                $this->db->trans_rollback();

                return $this->json_response([
                    'success' => false,
                    'message' => 'Task comment delete exception.',
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'local_comment_id' => $localCommentId,
                    'sync_uuid' => $syncUuid,
                ], 500);
            } finally {
                unset($GLOBALS['synchub_receiving_task_comment']);
            }

            return $this->json_response([
                'success' => true,
                'message' => 'Task comment deleted.',
                'source_comment_id' => $sourceCommentId,
                'local_comment_id' => $localCommentId,
                'sync_uuid' => $syncUuid,
            ], 200);
        }

        if ($taskUuid === '') {
            return $this->json_response(['success'=>false,'message'=>'parent_task_sync_uuid is required for task comment create/update.'],422);
        }

        if ($action === 'update') {
            $existingOrigin = $this->db->where('entity_type','task_comment')->where('sync_uuid',$syncUuid)
                ->get(db_prefix().'synchub_entity_origin')->row();
            if (!$existingOrigin) {
                return $this->json_response(['success'=>false,'message'=>'Mapped task comment was not found for update.'],404);
            }

            $localCommentId = (int)$existingOrigin->local_id;
            $existingComment = $this->db->where('id',$localCommentId)->get(db_prefix().'task_comments')->row();
            if (!$existingComment) {
                return $this->json_response(['success'=>false,'message'=>'Local task comment does not exist.'],404);
            }

            $GLOBALS['synchub_receiving_task_comment'] = true;
            try {
                $ok = $this->db->where('id',$localCommentId)->update(db_prefix().'task_comments',[
                    'content'=>$content,
                ]);
            } finally {
                unset($GLOBALS['synchub_receiving_task_comment']);
            }

            if (!$ok) {
                return $this->json_response(['success'=>false,'message'=>'Unable to update synchronized task comment.'],500);
            }

            $this->db->where('entity_type','task_comment')->where('sync_uuid',$syncUuid)
                ->update(db_prefix().'synchub_entity_origin',['updated_at'=>date('Y-m-d H:i:s')]);
            $this->db->where('entity_type','task_comment')->where('sync_uuid',$syncUuid)
                ->update(db_prefix().'synchub_entity_map',[
                    'remote_id'=>$sourceCommentId,
                    'updated_at'=>date('Y-m-d H:i:s'),
                ]);

            return $this->json_response([
                'success'=>true,
                'message'=>'Task comment updated.',
                'source_comment_id'=>$sourceCommentId,
                'local_comment_id'=>$localCommentId,
                'sync_uuid'=>$syncUuid,
            ]);
        }

        $existingOrigin = $this->db->where('entity_type','task_comment')->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if ($existingOrigin) {
            $existingId = (int)$existingOrigin->local_id;
            if ($this->db->where('id',$existingId)->get(db_prefix().'task_comments')->row()) {
                return $this->json_response(['success'=>true,'message'=>'Task comment already synchronized.','local_comment_id'=>$existingId,'sync_uuid'=>$syncUuid]);
            }
        }

        $taskOrigin = $this->db->where('entity_type','task')->where('sync_uuid',$taskUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if (!$taskOrigin) {
            return $this->json_response(['success'=>false,'message'=>'Parent task origin was not found.'],404);
        }
        $localTaskId = (int)$taskOrigin->local_id;
        if (!$this->db->where('id',$localTaskId)->get(db_prefix().'tasks')->row()) {
            return $this->json_response(['success'=>false,'message'=>'Mapped parent task does not exist.'],404);
        }

        $remoteStaffId = (int)($payload['source_staff_id'] ?? 0);
        $staffEmail = strtolower(trim((string)($payload['staff_email'] ?? '')));
        $localStaffId = 0;
        if (function_exists('synchub_resolve_local_staff_id')) {
            $localStaffId = (int)synchub_resolve_local_staff_id($senderCode, $remoteStaffId, $staffEmail);
        }
        if ($localStaffId <= 0 && $staffEmail !== '') {
            $staff = $this->db->where("LOWER(email) = ".$this->db->escape($staffEmail), null, false)
                ->get(db_prefix().'staff')->row();
            if ($staff) $localStaffId = (int)$staff->staffid;
        }
        if ($localStaffId <= 0) {
            return $this->json_response(['success'=>false,'message'=>'Unable to resolve local staff member for task comment.'],422);
        }

        $company = $this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) {
            return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422);
        }

        $row = [
            'content'=>$content,
            'taskid'=>$localTaskId,
            'staffid'=>$localStaffId,
            'contact_id'=>0,
            'file_id'=>0,
            'dateadded'=>!empty($payload['dateadded']) ? (string)$payload['dateadded'] : date('Y-m-d H:i:s'),
        ];

        $this->db->trans_begin();
        $GLOBALS['synchub_receiving_task_comment'] = true;
        try {
            $ok = $this->db->insert(db_prefix().'task_comments',$row);
            if (!$ok) {
                $this->db->trans_rollback();
                return $this->json_response(['success'=>false,'message'=>'Unable to create synchronized task comment.'],500);
            }
            $localCommentId = (int)$this->db->insert_id();

            $this->db->insert(db_prefix().'synchub_entity_origin',[
                'entity_type'=>'task_comment','local_id'=>$localCommentId,'source_company_code'=>$sourceCode,
                'source_branch_id'=>isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null,
                'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
            ]);
            $this->db->insert(db_prefix().'synchub_entity_map',[
                'entity_type'=>'task_comment','local_id'=>$localCommentId,'remote_id'=>$sourceCommentId,
                'company_id'=>(int)$company->id,
                'branch_id'=>isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null,
                'remote_company_id'=>null,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
            ]);
        } finally {
            unset($GLOBALS['synchub_receiving_task_comment']);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->json_response(['success'=>false,'message'=>'Task comment create transaction failed.'],500);
        }
        $this->db->trans_commit();

        return $this->json_response(['success'=>true,'message'=>'Task comment synchronized.','source_comment_id'=>$sourceCommentId,'local_comment_id'=>$localCommentId,'sync_uuid'=>$syncUuid]);
    }

    private function receive_project_discussion_comment(array $payload)
    {
        $action = strtolower(trim((string)($payload['action'] ?? 'create')));
        if (!in_array($action, ['create','update','delete'], true)) return $this->json_response(['success'=>false,'message'=>'Unsupported discussion comment action.'],422);

        $sourceCommentId=(int)($payload['comment_id']??0);
        $sourceCode=strtoupper(trim((string)($payload['source_company_code']??'')));
        $senderCode=strtoupper(trim((string)($payload['sender_instance_code']??$sourceCode)));
        $syncUuid=trim((string)($payload['sync_uuid']??''));
        $discussionUuid=trim((string)($payload['parent_discussion_sync_uuid']??''));
        if ($sourceCommentId<=0 || $sourceCode==='' || $syncUuid==='' || $discussionUuid==='') {
            return $this->json_response(['success'=>false,'message'=>'comment_id, source_company_code, sync_uuid and parent_discussion_sync_uuid are required.'],422);
        }

        if ($action === 'update' || $action === 'delete') {
            $localOrigin=$this->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$syncUuid)
                ->get(db_prefix().'synchub_entity_origin')->row();
            if (!$localOrigin) return $this->json_response(['success'=>false,'message'=>'Synchronized discussion comment mapping was not found.'],404);
            $localCommentId=(int)$localOrigin->local_id;
            $localComment=$this->db->where('id',$localCommentId)->get(db_prefix().'projectdiscussioncomments')->row();
            if (!$localComment) return $this->json_response(['success'=>false,'message'=>'Mapped discussion comment does not exist.'],404);

            $GLOBALS['synchub_receiving_project_discussion_comment']=true;
            if ($action === 'update') {
                $update=[
                    'content'=>(string)($payload['content']??''),
                    'modified'=>!empty($payload['modified'])?(string)$payload['modified']:date('Y-m-d H:i:s'),
                ];
                $ok=$this->db->where('id',$localCommentId)->update(db_prefix().'projectdiscussioncomments',$update);
                if ($ok) {
                    $this->db->where('id',(int)$localComment->discussion_id)->update(db_prefix().'projectdiscussions',['last_activity'=>$update['modified']]);
                }
                unset($GLOBALS['synchub_receiving_project_discussion_comment']);
                if (!$ok) return $this->json_response(['success'=>false,'message'=>'Unable to update synchronized discussion comment.'],500);
                return $this->json_response(['success'=>true,'message'=>'Discussion comment updated.','local_comment_id'=>$localCommentId,'sync_uuid'=>$syncUuid]);
            }

            // Delete directly instead of Projects_model::delete_discussion_comment().
            // The native model also logs project activity and resolves the current staff
            // session. API-to-API requests do not have an admin session, which can cause
            // an HTTP 500 even though the synchronized comment itself is valid.
            // Step 19.1 also removes the physical attachment file when this comment owns one.
            $attachmentPath = '';
            if (!empty($localComment->file_name)) {
                $attachmentPath = PROJECT_DISCUSSION_ATTACHMENT_FOLDER
                    . (int)$localComment->discussion_id . '/'
                    . basename((string)$localComment->file_name);
            }

            $this->db->trans_begin();

            $this->db->where('id',$localCommentId)->delete(db_prefix().'projectdiscussioncomments');
            $deleted = $this->db->affected_rows() > 0;

            if ($deleted) {
                // Match Perfex behaviour for replies to a deleted parent comment.
                $this->db->where('parent',$localCommentId)->update(db_prefix().'projectdiscussioncomments',['parent'=>null]);

                // Keep the discussion ordering/activity timestamp coherent without invoking
                // session-dependent activity logging from the native model.
                $this->db->where('id',(int)$localComment->discussion_id)->update(
                    db_prefix().'projectdiscussions',
                    ['last_activity'=>date('Y-m-d H:i:s')]
                );

                $this->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
                $this->db->where('entity_type','project_discussion_comment')->where('local_id',$localCommentId)->delete(db_prefix().'synchub_entity_origin');
            }

            if (!$deleted || $this->db->trans_status()===false) {
                $this->db->trans_rollback();
                unset($GLOBALS['synchub_receiving_project_discussion_comment']);
                return $this->json_response(['success'=>false,'message'=>'Unable to delete synchronized discussion comment.'],500);
            }

            // If the row had a synchronized attachment, delete the actual file too.
            // A missing file is harmless; an existing file that cannot be removed is
            // treated as a failure so we do not silently leave orphaned uploads behind.
            if ($attachmentPath !== '' && is_file($attachmentPath) && !@unlink($attachmentPath)) {
                $this->db->trans_rollback();
                unset($GLOBALS['synchub_receiving_project_discussion_comment']);
                return $this->json_response(['success'=>false,'message'=>'Discussion comment was not deleted because its attachment file could not be removed.'],500);
            }

            $this->db->trans_commit();
            unset($GLOBALS['synchub_receiving_project_discussion_comment']);
            return $this->json_response(['success'=>true,'message'=>'Discussion comment and attachment deleted.','local_comment_id'=>$localCommentId,'sync_uuid'=>$syncUuid]);
        }

        $existingOrigin=$this->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if ($existingOrigin) {
            $existingId=(int)$existingOrigin->local_id;
            if ($this->db->where('id',$existingId)->get(db_prefix().'projectdiscussioncomments')->row()) {
                return $this->json_response(['success'=>true,'message'=>'Comment already synchronized.','local_comment_id'=>$existingId,'sync_uuid'=>$syncUuid]);
            }
        }

        $discussionOrigin=$this->db->where('entity_type','project_discussion')->where('sync_uuid',$discussionUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if (!$discussionOrigin) return $this->json_response(['success'=>false,'message'=>'Parent discussion origin was not found.'],404);
        $localDiscussionId=(int)$discussionOrigin->local_id;
        if (!$this->db->where('id',$localDiscussionId)->get(db_prefix().'projectdiscussions')->row()) {
            return $this->json_response(['success'=>false,'message'=>'Mapped parent discussion does not exist.'],404);
        }

        $localStaffId=0;
        $remoteStaffId=(int)($payload['source_staff_id']??0);
        $staffEmail=trim((string)($payload['staff_email']??''));
        if (function_exists('synchub_resolve_local_staff_id')) {
            $localStaffId=(int)synchub_resolve_local_staff_id($senderCode,$remoteStaffId,$staffEmail);
        }
        if ($localStaffId<=0 && $staffEmail!=='') {
            $staff=$this->db->where('LOWER(email)',strtolower($staffEmail))->get(db_prefix().'staff')->row();
            if ($staff) $localStaffId=(int)$staff->staffid;
        }
        if ($localStaffId<=0) return $this->json_response(['success'=>false,'message'=>'Unable to resolve local staff member for discussion comment.'],422);

        $localParent=null;
        $parentUuid=trim((string)($payload['parent_comment_sync_uuid']??''));
        if ($parentUuid!=='') {
            $parentOrigin=$this->db->where('entity_type','project_discussion_comment')->where('sync_uuid',$parentUuid)
                ->get(db_prefix().'synchub_entity_origin')->row();
            if ($parentOrigin) $localParent=(int)$parentOrigin->local_id;
        }

        $company=$this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422);

        $row=[
            'discussion_id'=>$localDiscussionId,
            'discussion_type'=>'regular',
            'parent'=>$localParent,
            'created'=>!empty($payload['created'])?(string)$payload['created']:date('Y-m-d H:i:s'),
            'modified'=>!empty($payload['modified'])?(string)$payload['modified']:null,
            'content'=>(string)($payload['content']??''),
            'staff_id'=>$localStaffId,
            'contact_id'=>0,
            'fullname'=>!empty($payload['fullname'])?(string)$payload['fullname']:get_staff_full_name($localStaffId),
            'file_name'=>null,
            'file_mime_type'=>null,
        ];

        $GLOBALS['synchub_receiving_project_discussion_comment']=true;
        $this->db->trans_begin();
        $ok=$this->db->insert(db_prefix().'projectdiscussioncomments',$row);
        $localCommentId=$ok?(int)$this->db->insert_id():0;
        if ($localCommentId>0) {
            $this->db->where('id',$localDiscussionId)->update(db_prefix().'projectdiscussions',['last_activity'=>$row['created']]);
            $this->db->insert(db_prefix().'synchub_entity_origin',[
                'entity_type'=>'project_discussion_comment','local_id'=>$localCommentId,'source_company_code'=>$sourceCode,
                'source_branch_id'=>isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null,
                'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
            ]);
            $this->db->insert(db_prefix().'synchub_entity_map',[
                'entity_type'=>'project_discussion_comment','local_id'=>$localCommentId,'remote_id'=>$sourceCommentId,
                'company_id'=>(int)$company->id,
                'branch_id'=>isset($payload['source_branch_id']) && $payload['source_branch_id']!==null ? (int)$payload['source_branch_id'] : null,
                'remote_company_id'=>null,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
            ]);
        }
        if (!$ok || $localCommentId<=0 || $this->db->trans_status()===false) {
            $this->db->trans_rollback();
            unset($GLOBALS['synchub_receiving_project_discussion_comment']);
            return $this->json_response(['success'=>false,'message'=>'Unable to create synchronized discussion comment.'],500);
        }
        $this->db->trans_commit();
        unset($GLOBALS['synchub_receiving_project_discussion_comment']);
        return $this->json_response(['success'=>true,'message'=>'Discussion comment synchronized.','source_comment_id'=>$sourceCommentId,'local_comment_id'=>$localCommentId,'sync_uuid'=>$syncUuid]);
    }

    private function receive_project_discussion(array $payload)
    {
        $action = strtolower(trim((string)($payload['action'] ?? 'create')));
        if (!in_array($action,['create','update','delete'],true)) {
            return $this->json_response(['success'=>false,'message'=>'Unsupported discussion action.'],422);
        }

        $sourceDiscussionId = (int)($payload['discussion_id'] ?? 0);
        $sourceCode = strtoupper(trim((string)($payload['source_company_code'] ?? '')));
        $senderCode = strtoupper(trim((string)($payload['sender_instance_code'] ?? $sourceCode)));
        $syncUuid = trim((string)($payload['sync_uuid'] ?? ''));
        $projectUuid = trim((string)($payload['parent_project_sync_uuid'] ?? ''));
        $subject = trim((string)($payload['subject'] ?? ''));

        if ($sourceDiscussionId <= 0 || $sourceCode === '' || $syncUuid === '') {
            return $this->json_response(['success'=>false,'message'=>'discussion_id, source_company_code and sync_uuid are required.'],422);
        }

        $origin = $this->db->where('entity_type','project_discussion')->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        $map = $this->db->where('entity_type','project_discussion')->where('sync_uuid',$syncUuid)
            ->get(db_prefix().'synchub_entity_map')->row();
        $localDiscussionId = $origin ? (int)$origin->local_id : ($map ? (int)$map->local_id : 0);

        if ($action === 'delete') {
            if ($localDiscussionId <= 0) {
                return $this->json_response(['success'=>true,'message'=>'Discussion already absent.','sync_uuid'=>$syncUuid]);
            }

            $GLOBALS['synchub_receiving_project_discussion'] = true;
            try {
                $this->load->model('projects_model');
                $deleted = $this->projects_model->delete_discussion($localDiscussionId, false);
                if (!$deleted && $this->db->where('id',$localDiscussionId)->get(db_prefix().'projectdiscussions')->row()) {
                    return $this->json_response(['success'=>false,'message'=>'Unable to delete synchronized discussion.'],500);
                }
            } finally {
                unset($GLOBALS['synchub_receiving_project_discussion']);
            }

            $this->db->where('entity_type','project_discussion')->where('sync_uuid',$syncUuid)->delete(db_prefix().'synchub_entity_map');
            $this->db->where('entity_type','project_discussion')->where('local_id',$localDiscussionId)->delete(db_prefix().'synchub_entity_origin');
            return $this->json_response(['success'=>true,'message'=>'Discussion deleted.','local_discussion_id'=>$localDiscussionId,'sync_uuid'=>$syncUuid]);
        }

        if ($projectUuid === '' || $subject === '') {
            return $this->json_response(['success'=>false,'message'=>'subject and parent_project_sync_uuid are required.'],422);
        }

        $projectOrigin = $this->db->where('entity_type','project')->where('sync_uuid',$projectUuid)
            ->get(db_prefix().'synchub_entity_origin')->row();
        if (!$projectOrigin) return $this->json_response(['success'=>false,'message'=>'Parent project origin was not found.'],404);
        $localProjectId = (int)$projectOrigin->local_id;
        if (!$this->db->where('id',$localProjectId)->get(db_prefix().'projects')->row()) {
            return $this->json_response(['success'=>false,'message'=>'Mapped parent project does not exist.'],404);
        }

        $localStaffId = 0;
        $remoteStaffId = (int)($payload['source_staff_id'] ?? 0);
        $staffEmail = trim((string)($payload['staff_email'] ?? ''));
        if (function_exists('synchub_resolve_local_staff_id')) {
            $localStaffId = (int)synchub_resolve_local_staff_id($senderCode, $remoteStaffId, $staffEmail);
        }
        if ($localStaffId <= 0 && $staffEmail !== '') {
            $staff = $this->db->where('LOWER(email)', strtolower($staffEmail))->get(db_prefix().'staff')->row();
            if ($staff) $localStaffId = (int)$staff->staffid;
        }

        $company = $this->db->where('code',$sourceCode)->get(db_prefix().'synchub_companies')->row();
        if (!$company) return $this->json_response(['success'=>false,'message'=>'Source company is not registered: '.$sourceCode],422);

        $row = [
            'project_id'=>$localProjectId,
            'subject'=>$subject,
            'description'=>(string)($payload['description'] ?? ''),
            'show_to_customer'=>!empty($payload['show_to_customer']) ? 1 : 0,
            'datecreated'=>!empty($payload['datecreated']) ? (string)$payload['datecreated'] : date('Y-m-d H:i:s'),
            'last_activity'=>!empty($payload['last_activity']) ? (string)$payload['last_activity'] : null,
            'staff_id'=>$localStaffId,
            'contact_id'=>0,
        ];

        if ($action === 'update') {
            if ($localDiscussionId <= 0 || !$this->db->where('id',$localDiscussionId)->get(db_prefix().'projectdiscussions')->row()) {
                return $this->json_response(['success'=>false,'message'=>'Mapped discussion was not found for update.'],404);
            }
            // Do not replace original creator/date during an update.
            unset($row['datecreated'],$row['staff_id'],$row['contact_id']);
            $GLOBALS['synchub_receiving_project_discussion'] = true;
            try {
                $this->db->where('id',$localDiscussionId)->update(db_prefix().'projectdiscussions',$row);
            } finally {
                unset($GLOBALS['synchub_receiving_project_discussion']);
            }
            if ($this->db->error()['code']) {
                return $this->json_response(['success'=>false,'message'=>'Unable to update synchronized discussion.'],500);
            }
            if ($map) $this->db->where('id',(int)$map->id)->update(db_prefix().'synchub_entity_map',['updated_at'=>date('Y-m-d H:i:s')]);
            return $this->json_response(['success'=>true,'message'=>'Discussion updated.','local_discussion_id'=>$localDiscussionId,'sync_uuid'=>$syncUuid]);
        }

        // CREATE
        if ($localDiscussionId > 0 && $this->db->where('id',$localDiscussionId)->get(db_prefix().'projectdiscussions')->row()) {
            return $this->json_response(['success'=>true,'message'=>'Discussion already synchronized.','local_discussion_id'=>$localDiscussionId,'sync_uuid'=>$syncUuid]);
        }

        $this->db->trans_begin();
        $GLOBALS['synchub_receiving_project_discussion'] = true;
        try {
            $inserted = $this->db->insert(db_prefix().'projectdiscussions', $row);
            $localDiscussionId = $inserted ? (int)$this->db->insert_id() : 0;
        } finally {
            unset($GLOBALS['synchub_receiving_project_discussion']);
        }
        if ($localDiscussionId <= 0) {
            $this->db->trans_rollback();
            return $this->json_response(['success'=>false,'message'=>'Unable to create synchronized discussion.'],500);
        }

        $branchId = isset($payload['source_branch_id']) && $payload['source_branch_id'] !== null ? (int)$payload['source_branch_id'] : null;
        $this->db->insert(db_prefix().'synchub_entity_origin',[
            'entity_type'=>'project_discussion','local_id'=>$localDiscussionId,'source_company_code'=>$sourceCode,
            'source_branch_id'=>$branchId,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        $this->db->insert(db_prefix().'synchub_entity_map',[
            'entity_type'=>'project_discussion','local_id'=>$localDiscussionId,'remote_id'=>$sourceDiscussionId,'company_id'=>(int)$company->id,
            'branch_id'=>$branchId,'remote_company_id'=>null,'sync_uuid'=>$syncUuid,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null,
        ]);
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->json_response(['success'=>false,'message'=>'Discussion create transaction failed.'],500);
        }
        $this->db->trans_commit();

        return $this->json_response([
            'success'=>true,'message'=>'Discussion synchronized.','source_discussion_id'=>$sourceDiscussionId,
            'local_discussion_id'=>$localDiscussionId,'local_project_id'=>$localProjectId,'sync_uuid'=>$syncUuid
        ]);
    }

    private function resolve_client_id($requestedId)
    {
        $requestedId=(int)$requestedId;
        if ($requestedId>0) { $c=$this->db->where('userid',$requestedId)->get(db_prefix().'clients')->row(); if ($c) return (int)$c->userid; }
        $c=$this->db->where('active',1)->order_by('userid','ASC')->get(db_prefix().'clients')->row();
        return $c ? (int)$c->userid : 0;
    }

    private function verify_request($rawBody)
    {
        $file=dirname(__DIR__).'/config/instance.php'; $config=file_exists($file) ? require $file : [];
        $expectedKey=(string)($config['api_key'] ?? ''); $secret=(string)($config['api_secret'] ?? '');
        $key=(string)($_SERVER['HTTP_X_SYNCHUB_KEY'] ?? ''); $timestamp=(string)($_SERVER['HTTP_X_SYNCHUB_TIMESTAMP'] ?? '');
        $signature=(string)($_SERVER['HTTP_X_SYNCHUB_SIGNATURE'] ?? '');
        if ($expectedKey==='' || $secret==='' || $key==='' || $timestamp==='' || $signature==='') return false;
        if (!hash_equals($expectedKey,$key)) return false;
        if (!ctype_digit($timestamp) || abs(time()-(int)$timestamp)>300) return false;
        return hash_equals(hash_hmac('sha256',$timestamp.'.'.$rawBody,$secret),$signature);
    }

    private function json_response($data,$statusCode=200)
    {
        $this->output->set_status_header($statusCode)->set_content_type('application/json')->set_output(json_encode($data));
        return;
    }
}
