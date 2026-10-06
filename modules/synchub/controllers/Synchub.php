<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Synchub extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $data['title'] = 'SyncHub';

        $this->load->view('synchub/dashboard', $data);
    }

    public function companies()
    {
        /*
        |--------------------------------------------------------------------------
        | Add New Company
        |--------------------------------------------------------------------------
        */

        if ($this->input->post()) {

            $name     = trim($this->input->post('name'));
            $code     = trim($this->input->post('code'));
            $type     = $this->input->post('type');
            $base_url = trim($this->input->post('base_url'));

            if ($name == '' || $code == '') {

                set_alert(
                    'danger',
                    'Company Name and Company Code are required.'
                );

                redirect(admin_url('synchub/companies'));
            }

            /*
            |--------------------------------------------------------------------------
            | Check duplicate company code
            |--------------------------------------------------------------------------
            */

            $exists = $this->db
                ->where('code', $code)
                ->get(db_prefix() . 'synchub_companies')
                ->row();

            if ($exists) {

                set_alert(
                    'danger',
                    'Company Code already exists.'
                );

                redirect(admin_url('synchub/companies'));
            }

            /*
            |--------------------------------------------------------------------------
            | Insert Company
            |--------------------------------------------------------------------------
            */

            $insert = [
                'uuid'       => $this->generate_uuid(),
                'name'       => $name,
                'code'       => strtoupper($code),
                'type'       => $type == 'master' ? 'master' : 'child',
                'base_url'   => $base_url,
                'active'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $this->db->insert(
                db_prefix() . 'synchub_companies',
                $insert
            );

            set_alert(
                'success',
                'Company added successfully.'
            );

            redirect(admin_url('synchub/companies'));
        }

        /*
        |--------------------------------------------------------------------------
        | Get Companies
        |--------------------------------------------------------------------------
        */

        $data['title'] = 'Companies';

        $data['companies'] = $this->db
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'synchub_companies')
            ->result();

        $this->load->view(
            'synchub/companies',
            $data
        );
    }

    public function delete_company($id)
    {
        if (!$this->input->is_ajax_request() && strtolower((string) $this->input->method()) !== 'post') {
            show_404();
        }

        $id = (int) $id;
        $company = $this->db->where('id', $id)->get(db_prefix() . 'synchub_companies')->row();

        if (!$company) {
            set_alert('danger', 'Company not found.');
            redirect(admin_url('synchub/companies'));
        }

        $instanceCode = function_exists('synchub_instance_code')
            ? strtoupper(trim((string) synchub_instance_code()))
            : '';

        if (strtolower((string) $company->type) === 'master' || strtoupper((string) $company->code) === $instanceCode) {
            set_alert('danger', 'The master/current SyncHub company cannot be deleted.');
            redirect(admin_url('synchub/companies'));
        }

        $reasons = [];
        $companyCode = strtoupper(trim((string) $company->code));

        $checks = [
            [db_prefix() . 'synchub_branches', 'company_id', $id, 'branches'],
            [db_prefix() . 'synchub_connections', 'company_id', $id, 'connections'],
            [db_prefix() . 'synchub_entity_map', 'company_id', $id, 'entity mappings'],
            [db_prefix() . 'synchub_queue', 'company_id', $id, 'queue events'],
        ];

        foreach ($checks as $check) {
            [$table, $field, $value, $label] = $check;
            if ($this->db->table_exists($table)) {
                $count = (int) $this->db->where($field, $value)->count_all_results($table);
                if ($count > 0) {
                    $reasons[] = $label . ' (' . $count . ')';
                }
            }
        }

        $codeChecks = [
            [db_prefix() . 'synchub_entity_origin', 'source_company_code', 'entity origins'],
            [db_prefix() . 'synchub_project_assignment', 'assigned_company_code', 'project assignments'],
            [db_prefix() . 'synchub_project_assignment', 'created_from_instance_code', 'project ownership records'],
            [db_prefix() . 'synchub_staff_map', 'remote_company_code', 'staff mappings'],
        ];

        foreach ($codeChecks as $check) {
            [$table, $field, $label] = $check;
            if ($this->db->table_exists($table)) {
                $count = (int) $this->db->where($field, $companyCode)->count_all_results($table);
                if ($count > 0) {
                    $reasons[] = $label . ' (' . $count . ')';
                }
            }
        }

        if (!empty($reasons)) {
            set_alert('danger', 'Company cannot be deleted because it is in use: ' . implode(', ', $reasons) . '.');
            redirect(admin_url('synchub/companies'));
        }

        $this->db->where('id', $id)->delete(db_prefix() . 'synchub_companies');
        set_alert('success', 'Company deleted successfully.');
        redirect(admin_url('synchub/companies'));
    }

    public function delete_branch($id)
    {
        if (!$this->input->is_ajax_request() && strtolower((string) $this->input->method()) !== 'post') {
            show_404();
        }

        $id = (int) $id;
        $branch = $this->db->where('id', $id)->get(db_prefix() . 'synchub_branches')->row();

        if (!$branch) {
            set_alert('danger', 'Branch not found.');
            redirect(admin_url('synchub/branches'));
        }

        $reasons = [];
        $checks = [
            [db_prefix() . 'synchub_entity_map', 'branch_id', $id, 'entity mappings'],
            [db_prefix() . 'synchub_queue', 'branch_id', $id, 'queue events'],
            [db_prefix() . 'synchub_entity_origin', 'source_branch_id', $id, 'entity origins'],
        ];

        foreach ($checks as $check) {
            [$table, $field, $value, $label] = $check;
            if ($this->db->table_exists($table)) {
                $count = (int) $this->db->where($field, $value)->count_all_results($table);
                if ($count > 0) {
                    $reasons[] = $label . ' (' . $count . ')';
                }
            }
        }

        if (!empty($reasons)) {
            set_alert('danger', 'Branch cannot be deleted because it is in use: ' . implode(', ', $reasons) . '.');
            redirect(admin_url('synchub/branches'));
        }

        $this->db->where('id', $id)->delete(db_prefix() . 'synchub_branches');
        set_alert('success', 'Branch deleted successfully.');
        redirect(admin_url('synchub/branches'));
    }

    /*
    |--------------------------------------------------------------------------
    | UUID Generator
    |--------------------------------------------------------------------------
    */

    private function generate_uuid()
    {
        $data = random_bytes(16);

        $data[6] = chr(
            ord($data[6]) & 0x0f | 0x40
        );

        $data[8] = chr(
            ord($data[8]) & 0x3f | 0x80
        );

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }

public function branches()
{
    /*
    |--------------------------------------------------------------------------
    | Add New Branch
    |--------------------------------------------------------------------------
    */

    if ($this->input->post()) {

        $company_id = (int) $this->input->post('company_id');
        $name       = trim($this->input->post('name'));
        $code       = trim($this->input->post('code'));

        if (!$company_id || $name == '' || $code == '') {

            set_alert(
                'danger',
                'Company, Branch Name and Branch Code are required.'
            );

            redirect(admin_url('synchub/branches'));
        }

        $company = $this->db
            ->where('id', $company_id)
            ->where('active', 1)
            ->get(db_prefix() . 'synchub_companies')
            ->row();

        if (!$company) {

            set_alert(
                'danger',
                'Invalid company selected.'
            );

            redirect(admin_url('synchub/branches'));
        }

        $exists = $this->db
            ->where('company_id', $company_id)
            ->where('code', strtoupper($code))
            ->get(db_prefix() . 'synchub_branches')
            ->row();

        if ($exists) {

            set_alert(
                'danger',
                'Branch Code already exists for this company.'
            );

            redirect(admin_url('synchub/branches'));
        }

        $insert = [
            'uuid'       => $this->generate_uuid(),
            'company_id' => $company_id,
            'name'       => $name,
            'code'       => strtoupper($code),
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(
            db_prefix() . 'synchub_branches',
            $insert
        );

        set_alert(
            'success',
            'Branch added successfully.'
        );

        redirect(admin_url('synchub/branches'));
    }

    /*
    |--------------------------------------------------------------------------
    | Load Branches
    |--------------------------------------------------------------------------
    */

    $data['title'] = 'Branches';

    $data['branches'] = $this->db
        ->select(
            db_prefix() . 'synchub_branches.*,
            ' . db_prefix() . 'synchub_companies.name as company_name'
        )
        ->from(db_prefix() . 'synchub_branches')
        ->join(
            db_prefix() . 'synchub_companies',
            db_prefix() . 'synchub_companies.id = ' .
            db_prefix() . 'synchub_branches.company_id',
            'left'
        )
        ->order_by(
            db_prefix() . 'synchub_branches.id',
            'DESC'
        )
        ->get()
        ->result();

    $data['companies'] = $this->db
        ->where('active', 1)
        ->order_by('name', 'ASC')
        ->get(db_prefix() . 'synchub_companies')
        ->result();

    $this->load->view(
        'synchub/branches',
        $data
    );
}
public function connections()
{
    /*
    |--------------------------------------------------------------------------
    | Add New Connection
    |--------------------------------------------------------------------------
    */

    if ($this->input->post()) {

        $company_id = (int) $this->input->post('company_id');
        $remote_url = trim($this->input->post('remote_url'));

        if (!$company_id || $remote_url == '') {

            set_alert(
                'danger',
                'Company and Remote URL are required.'
            );

            redirect(admin_url('synchub/connections'));
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Company
        |--------------------------------------------------------------------------
        */

        $company = $this->db
            ->where('id', $company_id)
            ->where('active', 1)
            ->get(db_prefix() . 'synchub_companies')
            ->row();

        if (!$company) {

            set_alert(
                'danger',
                'Invalid company selected.'
            );

            redirect(admin_url('synchub/connections'));
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Connection
        |--------------------------------------------------------------------------
        */

        $exists = $this->db
            ->where('company_id', $company_id)
            ->get(db_prefix() . 'synchub_connections')
            ->row();

        if ($exists) {

            set_alert(
                'danger',
                'A connection already exists for this company.'
            );

            redirect(admin_url('synchub/connections'));
        }

        /*
        |--------------------------------------------------------------------------
        | Generate API Credentials
        |--------------------------------------------------------------------------
        */

        $api_key    = bin2hex(random_bytes(16));
        $api_secret = bin2hex(random_bytes(32));

        /*
        |--------------------------------------------------------------------------
        | Insert Connection
        |--------------------------------------------------------------------------
        */

        $insert = [
            'company_id' => $company_id,
            'remote_url' => rtrim($remote_url, '/'),
            'api_key'    => $api_key,
            'api_secret' => $api_secret,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(
            db_prefix() . 'synchub_connections',
            $insert
        );

        set_alert(
            'success',
            'Connection added successfully.'
        );

        redirect(admin_url('synchub/connections'));
    }

    /*
    |--------------------------------------------------------------------------
    | Load Connections
    |--------------------------------------------------------------------------
    */

    $data['title'] = 'Connections';

    $data['connections'] = $this->db
        ->select(
            db_prefix() . 'synchub_connections.*,
            ' . db_prefix() . 'synchub_companies.name as company_name'
        )
        ->from(db_prefix() . 'synchub_connections')
        ->join(
            db_prefix() . 'synchub_companies',
            db_prefix() . 'synchub_companies.id = ' .
            db_prefix() . 'synchub_connections.company_id',
            'left'
        )
        ->order_by(
            db_prefix() . 'synchub_connections.id',
            'DESC'
        )
        ->get()
        ->result();

    $data['companies'] = $this->db
        ->where('active', 1)
        ->order_by('name', 'ASC')
        ->get(db_prefix() . 'synchub_companies')
        ->result();

    $this->load->view(
        'synchub/connections',
        $data
    );
}

public function projects()
{
    $data['title'] = 'Project Sync';

    /*
    |--------------------------------------------------------------------------
    | Load Perfex Projects
    |--------------------------------------------------------------------------
    */

    $data['projects'] = $this->db
        ->select('id, name, clientid, start_date, deadline, status')
        ->order_by('id', 'DESC')
        ->get(db_prefix() . 'projects')
        ->result();

    /*
    |--------------------------------------------------------------------------
    | Load Child Companies
    |--------------------------------------------------------------------------
    */

    $data['companies'] = $this->db
        ->where('type', 'child')
        ->where('active', 1)
        ->order_by('name', 'ASC')
        ->get(db_prefix() . 'synchub_companies')
        ->result();

    /*
    |--------------------------------------------------------------------------
    | Load Branches
    |--------------------------------------------------------------------------
    */

    $data['branches'] = $this->db
        ->where('active', 1)
        ->order_by('name', 'ASC')
        ->get(db_prefix() . 'synchub_branches')
        ->result();

    $this->load->view(
        'synchub/projects',
        $data
    );
}

public function queue_project()
{
    if (!$this->input->post()) {
        show_404();
    }

    $project_id = (int) $this->input->post('project_id');
    $company_id = (int) $this->input->post('company_id');
    $branch_id  = (int) $this->input->post('branch_id');

    if (!$project_id || !$company_id || !$branch_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required data.'
        ]);
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Check Project
    |--------------------------------------------------------------------------
    */

    $project = $this->db
        ->where('id', $project_id)
        ->get(db_prefix() . 'projects')
        ->row();

    if (!$project) {
        echo json_encode([
            'success' => false,
            'message' => 'Project not found.'
        ]);
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Check Company
    |--------------------------------------------------------------------------
    */

    $company = $this->db
        ->where('id', $company_id)
        ->where('type', 'child')
        ->where('active', 1)
        ->get(db_prefix() . 'synchub_companies')
        ->row();

    if (!$company) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid target company.'
        ]);
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Check Branch
    |--------------------------------------------------------------------------
    */

    $branch = $this->db
        ->where('id', $branch_id)
        ->where('company_id', $company_id)
        ->where('active', 1)
        ->get(db_prefix() . 'synchub_branches')
        ->row();

    if (!$branch) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid target branch.'
        ]);
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Build Payload
    |--------------------------------------------------------------------------
    */

    $payload = [
        'project_id' => $project->id,
        'name'       => $project->name,
        'clientid'   => $project->clientid,
        'start_date' => $project->start_date,
        'deadline'   => $project->deadline,
        'status'     => $project->status,
    ];

    /*
    |--------------------------------------------------------------------------
    | Add To Queue
    |--------------------------------------------------------------------------
    */

    $insert = [
        'event_id'    => $this->generate_uuid(),
        'entity_type' => 'project',
        'entity_id'   => $project_id,
        'action'      => 'create',
        'company_id'  => $company_id,
        'branch_id'   => $branch_id,
        'payload'     => json_encode($payload),
        'status'      => 'pending',
        'attempts'    => 0,
        'created_at'  => date('Y-m-d H:i:s'),
    ];

    $this->db->insert(
        db_prefix() . 'synchub_queue',
        $insert
    );

    echo json_encode([
        'success' => true,
        'message' => 'Project added to sync queue.'
    ]);
}

public function process_queue()
{
    $queue = $this->db
        ->where('status', 'pending')
        ->order_by('id', 'ASC')
        ->get(db_prefix() . 'synchub_queue')
        ->row();

    if (!$queue) {
        echo 'No pending queue items.';
        return;
    }

    $connection = $this->db
        ->where('company_id', $queue->company_id)
        ->where('active', 1)
        ->get(db_prefix() . 'synchub_connections')
        ->row();

    if (!$connection) {

        $this->db
            ->where('id', $queue->id)
            ->update(db_prefix() . 'synchub_queue', [
                'status' => 'failed',
                'last_error' => 'No active connection found.',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        echo 'No active connection found.';
        return;
    }

    $payloadData = json_decode($queue->payload, true);

if (!is_array($payloadData)) {
    $payloadData = [];
}

$payloadData['action'] = $queue->action;

$payload = json_encode($payloadData);

    $url = rtrim($connection->remote_url, '/')
        . '/synchub/api/project';

    $this->db
        ->where('id', $queue->id)
        ->update(db_prefix() . 'synchub_queue', [
            'status' => 'processing',
            'attempts' => $queue->attempts + 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {

        $this->db
            ->where('id', $queue->id)
            ->update(db_prefix() . 'synchub_queue', [
                'status' => 'failed',
                'last_error' => $curlError,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        echo 'cURL Error: ' . html_escape($curlError);
        return;
    }

    $decoded = json_decode($response, true);

    if (
        $httpCode >= 200 &&
        $httpCode < 300 &&
        is_array($decoded) &&
        !empty($decoded['success'])
    ) {

        $this->db
            ->where('id', $queue->id)
            ->update(db_prefix() . 'synchub_queue', [
                'status' => 'success',
                'last_error' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
echo '<pre>';
echo 'HTTP Code: ' . $httpCode;
echo "\n\n";
echo 'Seen Response:';
echo "\n";
echo html_escape($response);
echo '</pre>';
        return;
    }

    $this->db
        ->where('id', $queue->id)
        ->update(db_prefix() . 'synchub_queue', [
            'status' => 'failed',
            'last_error' => $response,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

echo '<pre>';

echo 'HTTP Code: ';
echo $httpCode;

echo "\n\n";

echo 'Response:';
echo "\n";

echo html_escape($response);

echo '</pre>';}

    public function sync_staff_mappings()
    {
        if (!function_exists('synchub_instance_role') || !function_exists('synchub_instance_code')) {
            show_error('SyncHub instance helpers are unavailable.', 500);
            return;
        }

        $instanceCode = strtoupper((string)synchub_instance_code());
        $role = strtolower((string)synchub_instance_role());
        $destinationCode = '';

        if ($role === 'child') {
            $destinationCode = strtoupper((string)synchub_instance_config('master_code'));
        } else {
            $destinationCode = strtoupper(trim((string)$this->input->get('company_code')));
            if ($destinationCode === '' || $destinationCode === $instanceCode) {
                $this->output->set_content_type('application/json')->set_output(json_encode([
                    'success'=>false,
                    'message'=>'On the master, pass ?company_code=CHILD_CODE.',
                ]));
                return;
            }
        }

        $remoteUrl = function_exists('synchub_remote_url') ? synchub_remote_url($destinationCode) : '';
        $apiKey = (string)synchub_instance_config('api_key');
        $apiSecret = (string)synchub_instance_config('api_secret');

        if ($remoteUrl === '' || $apiKey === '' || $apiSecret === '') {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'success'=>false,
                'message'=>'Remote URL/API credentials are missing.',
                'destination'=>$destinationCode,
            ]));
            return;
        }

        $staffRows = $this->db
            ->select('staffid,email,firstname,lastname,active')
            ->where('active', 1)
            ->where('email !=', '')
            ->order_by('staffid', 'ASC')
            ->get(db_prefix().'staff')
            ->result();

        $mapped = 0;
        $unmatched = 0;
        $failed = 0;
        $details = [];

        foreach ($staffRows as $staff) {
            $payload = [
                'source_company_code'=>$instanceCode,
                'remote_staff_id'=>(int)$staff->staffid,
                'email'=>strtolower(trim((string)$staff->email)),
            ];
            $rawBody = json_encode($payload);
            $timestamp = (string)time();
            $signature = hash_hmac('sha256', $timestamp . '.' . $rawBody, $apiSecret);

            $ch = curl_init(rtrim($remoteUrl, '/') . '/synchub/api/staff_resolve');
            curl_setopt_array($ch, [
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>$rawBody,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HTTPHEADER=>[
                    'Content-Type: application/json',
                    'X-SyncHub-Key: ' . $apiKey,
                    'X-SyncHub-Timestamp: ' . $timestamp,
                    'X-SyncHub-Signature: ' . $signature,
                ],
                CURLOPT_TIMEOUT=>15,
                CURLOPT_CONNECTTIMEOUT=>5,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                $failed++;
                $details[] = ['email'=>$staff->email,'status'=>'failed','message'=>$curlError];
                continue;
            }

            $decoded = json_decode((string)$response, true);
            if ($httpCode === 404 && is_array($decoded)) {
                $unmatched++;
                $details[] = ['email'=>$staff->email,'status'=>'unmatched'];
                continue;
            }

            if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['success'])) {
                $failed++;
                $details[] = ['email'=>$staff->email,'status'=>'failed','http_code'=>$httpCode,'response'=>$decoded ?: (string)$response];
                continue;
            }

            $remoteStaffId = (int)($decoded['local_staff_id'] ?? 0);
            if ($remoteStaffId <= 0) {
                $failed++;
                $details[] = ['email'=>$staff->email,'status'=>'failed','message'=>'Remote staff ID missing.'];
                continue;
            }

            if (function_exists('synchub_upsert_staff_map')) {
                synchub_upsert_staff_map($destinationCode, (int)$staff->staffid, $remoteStaffId, (string)$staff->email);
            }
            $mapped++;
            $details[] = ['email'=>$staff->email,'status'=>'mapped','local_staff_id'=>(int)$staff->staffid,'remote_staff_id'=>$remoteStaffId];
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success'=>true,
                'source'=>$instanceCode,
                'destination'=>$destinationCode,
                'mapped'=>$mapped,
                'unmatched'=>$unmatched,
                'failed'=>$failed,
                'details'=>$details,
            ]));
    }


    public function sync_task_assignees()
    {
        if (!function_exists('synchub_enqueue_task_event')) {
            show_error('SyncHub task helpers are unavailable.', 500);
            return;
        }

        $rows = $this->db
            ->select('o.local_id')
            ->from(db_prefix().'synchub_entity_origin o')
            ->join(db_prefix().'tasks t', 't.id = o.local_id', 'inner')
            ->where('o.entity_type', 'task')
            ->where('t.rel_type', 'project')
            ->order_by('o.local_id', 'ASC')
            ->get()
            ->result();

        $processed=0; $dispatched=0; $skipped=0;
        foreach ($rows as $row) {
            $processed++;
            if (synchub_enqueue_task_event((int)$row->local_id, 'update')) $dispatched++;
            else $skipped++;
        }

        $this->output->set_content_type('application/json')->set_output(json_encode([
            'success'=>true,
            'processed'=>$processed,
            'dispatched'=>$dispatched,
            'skipped'=>$skipped,
        ]));
    }

}