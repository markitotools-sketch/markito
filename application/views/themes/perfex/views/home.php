<?php defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$clientId = (int) get_client_user_id();

$client = $CI->db->select('company,datecreated')
    ->where('userid', $clientId)
    ->get(db_prefix() . 'clients')
    ->row();

$projectStatuses = isset($project_statuses) ? $project_statuses : $CI->projects_model->get_project_statuses();

$projectCounts = [];
$totalProjects = 0;
foreach ($projectStatuses as $status) {
    $count = total_rows(db_prefix() . 'projects', [
        'status'   => $status['id'],
        'clientid' => $clientId,
    ]);
    $projectCounts[(int) $status['id']] = $count;
    $totalProjects += $count;
}

$whereTotalInvoices = 'clientid=' . $clientId . ' AND status !=5';
if (get_option('exclude_invoice_from_client_area_with_draft_status') == 1) {
    $whereTotalInvoices .= ' AND status != 6';
}

$totalInvoices  = total_rows(db_prefix() . 'invoices', $whereTotalInvoices);
$totalUnpaid    = total_rows(db_prefix() . 'invoices', ['status' => 1, 'clientid' => $clientId]);
$totalPaid      = total_rows(db_prefix() . 'invoices', ['status' => 2, 'clientid' => $clientId]);
$totalPartial   = total_rows(db_prefix() . 'invoices', ['status' => 3, 'clientid' => $clientId]);
$totalOverdue   = total_rows(db_prefix() . 'invoices', ['status' => 4, 'clientid' => $clientId]);

$totalPayments = $CI->db
    ->from(db_prefix() . 'invoicepaymentrecords ipr')
    ->join(db_prefix() . 'invoices i', 'i.id = ipr.invoiceid', 'inner')
    ->where('i.clientid', $clientId)
    ->count_all_results();

$closedStatus = 5;
$openTickets = $CI->db
    ->from(db_prefix() . 'tickets')
    ->where('userid', $clientId)
    ->where('status !=', $closedStatus)
    ->count_all_results();

$recentProjects = $CI->db
    ->select('id,name,status,start_date,deadline')
    ->where('clientid', $clientId)
    ->order_by('id', 'desc')
    ->limit(4)
    ->get(db_prefix() . 'projects')
    ->result_array();

$allClientProjectIds = $CI->db
    ->select('id')
    ->where('clientid', $clientId)
    ->get(db_prefix() . 'projects')
    ->result_array();

$allClientProjectIds = array_map(function ($row) {
    return (int) $row['id'];
}, $allClientProjectIds);

$recentFiles = [];
$recentActivity = [];
$upcoming = [];

if (!empty($allClientProjectIds)) {
    $recentFiles = $CI->db
        ->select('pf.id,pf.project_id,pf.file_name,pf.subject,pf.dateadded,p.name as project_name')
        ->from(db_prefix() . 'project_files pf')
        ->join(db_prefix() . 'projects p', 'p.id = pf.project_id', 'inner')
        ->where('p.clientid', $clientId)
        ->where('pf.visible_to_customer', 1)
        ->order_by('pf.id', 'desc')
        ->limit(4)
        ->get()
        ->result_array();

    $recentActivity = $CI->db
        ->select('pa.id,pa.project_id,pa.description_key,pa.additional_data,pa.dateadded,p.name as project_name')
        ->from(db_prefix() . 'project_activity pa')
        ->join(db_prefix() . 'projects p', 'p.id = pa.project_id', 'inner')
        ->where('p.clientid', $clientId)
        ->where('pa.visible_to_customer', 1)
        ->order_by('pa.id', 'desc')
        ->limit(4)
        ->get()
        ->result_array();

    $upcoming = $CI->db
        ->select('id,name,duedate,rel_id')
        ->from(db_prefix() . 'tasks')
        ->where('rel_type', 'project')
        ->where_in('rel_id', $allClientProjectIds)
        ->where('visible_to_client', 1)
        ->where('duedate IS NOT NULL', null, false)
        ->where('duedate >=', date('Y-m-d'))
        ->order_by('duedate', 'asc')
        ->limit(4)
        ->get()
        ->result_array();
}

function mk2_status_meta($status)
{
    $name = strtolower(trim($status['name'] ?? ''));

    if (strpos($name, 'progress') !== false) {
        return ['class' => 'mk2-progress', 'icon' => 'play'];
    }
    if (strpos($name, 'hold') !== false) {
        return ['class' => 'mk2-hold', 'icon' => 'pause'];
    }
    if (strpos($name, 'cancel') !== false) {
        return ['class' => 'mk2-cancelled', 'icon' => 'close'];
    }
    if (strpos($name, 'finish') !== false || strpos($name, 'complete') !== false) {
        return ['class' => 'mk2-finished', 'icon' => 'check'];
    }

    return ['class' => 'mk2-not-started', 'icon' => 'clock'];
}

function mk2_icon($name)
{
    $icons = [
        'folder' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.75A1.75 1.75 0 0 1 4.75 5h5l2 2h7.5A1.75 1.75 0 0 1 21 8.75v8.5A1.75 1.75 0 0 1 19.25 19H4.75A1.75 1.75 0 0 1 3 17.25z"/></svg>',
        'file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.75h7.25L18 8.5v11.75H6z"/><path d="M13 3.75V9h5"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>',
        'support' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.5 3.5M18.4 5.6l-3.5 3.5M5.6 18.4l3.5-3.5M18.4 18.4l-3.5-3.5"/></svg>',
        'layers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/></svg>',
        'invoice' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-2-1.5L14 21l-2-1.5L10 21l-2-1.5L6 21z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c.8-4.2 3.2-6 7.5-6s6.7 1.8 7.5 6"/></svg>',
        'activity' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l2.2-5 4.1 10 2.2-5H21"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'play' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 7 8 5-8 5z"/></svg>',
        'pause' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7v10M15 7v10"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 8 8 8M16 8l-8 8"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 12 3 3 7-7"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>',
        'building' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V5h10v16M15 9h4v12M8 8h4M8 12h4M8 16h4"/></svg>',
        'payment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg>',
        'alert' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4 3.5 19h17z"/><path d="M12 9v4M12 16h.01"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function mk2_activity_label($row)
{
    $key = $row['description_key'] ?? '';
    if ($key !== '') {
        $translated = _l($key);
        if ($translated !== $key) {
            return $translated;
        }
    }

    return _l('project_activity');
}
?>

<style>
body.mk2-client-home {
    background: #f6f9fd !important;
}

body.mk2-client-home > .navbar.header {
    min-height: 68px !important;
    margin: 0 !important;
    border: 0 !important;
    border-bottom: 1px solid #e7edf6 !important;
    background: rgba(255,255,255,.98) !important;
    box-shadow: 0 1px 0 rgba(14,38,80,.02) !important;
}

body.mk2-client-home > .navbar.header .container,
body.mk2-client-home #content > .container {
    width: calc(100% - 48px) !important;
    max-width: 1600px !important;
}

body.mk2-client-home > .navbar.header .navbar-header {
    min-height: 68px;
    display: flex;
    align-items: center;
}

body.mk2-client-home > .navbar.header .navbar-brand.logo {
    position: relative;
    width: 220px;
    height: 54px;
    padding: 7px 0 !important;
    margin-left: 0 !important;
    font-size: 0 !important;
    overflow: visible;
}

body.mk2-client-home > .navbar.header .navbar-brand.logo img {
    display: none !important;
}

body.mk2-client-home > .navbar.header .navbar-brand.logo:before {
    content: "M";
    display: inline-grid;
    place-items: center;
    width: 44px;
    height: 44px;
    margin-right: 10px;
    border-radius: 13px;
    vertical-align: middle;
    color: #fff;
    font: 800 24px/1 Arial, sans-serif;
    letter-spacing: -.08em;
    background: linear-gradient(135deg,#17b9ff 0%,#1466e8 56%,#082d73 100%);
    box-shadow: 0 8px 20px rgba(21,93,252,.18);
}

body.mk2-client-home > .navbar.header .navbar-brand.logo:after {
    content: "Markito\A Digital Agency";
    white-space: pre;
    display: inline-block;
    vertical-align: middle;
    color: #0d214f;
    font: 800 17px/17px Arial, sans-serif;
    letter-spacing: -.02em;
}

body.mk2-client-home > .navbar.header .navbar-collapse {
    min-height: 68px;
}

body.mk2-client-home > .navbar.header .navbar-nav {
    display: flex;
    align-items: center;
    min-height: 68px;
    margin: 0 !important;
}

body.mk2-client-home > .navbar.header .navbar-nav > li > a {
    padding: 24px 14px !important;
    color: #0d1f46 !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    line-height: 20px !important;
    background: transparent !important;
}

body.mk2-client-home > .navbar.header .navbar-nav > li > a:hover {
    color: #9a5600 !important;
}

body.mk2-client-home > .navbar.header .navbar-nav > li.active > a {
    color: #9a5600 !important;
}

body.mk2-client-home > .navbar.header .client-profile-image-small {
    width: 38px !important;
    height: 38px !important;
    border: 2px solid #edf3fb;
}

body.mk2-client-home #wrapper,
body.mk2-client-home #content {
    background: #f6f9fd !important;
}

body.mk2-client-home #content {
    padding-top: 0 !important;
}

body.mk2-client-home .customer-top-submenu {
    display: none !important;
}

body.mk2-client-home #content > .container {
    padding-top: 18px;
    padding-bottom: 34px;
}

body.mk2-client-home #content > .container > .row {
    margin-left: 0;
    margin-right: 0;
}

.mk2-dashboard {
    --blue:#f59b23;
    --blue2:#dd7c0a;
    --navy:#081c49;
    --text:#4f6485;
    --muted:#8494ab;
    --border:#e5edf7;
    --shadow:0 9px 28px rgba(15,39,82,.06);
    color:var(--navy);
    font-family: Inter, "Segoe UI", Arial, sans-serif;
}

.mk2-dashboard * { box-sizing:border-box; }
.mk2-dashboard a { text-decoration:none !important; }
.mk2-dashboard svg {
    width:20px;
    height:20px;
    fill:none;
    stroke:currentColor;
    stroke-width:1.8;
    stroke-linecap:round;
    stroke-linejoin:round;
}

.mk2-hero {
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:42px;
    min-height:148px;
    padding:26px 30px;
    margin-bottom:16px;
    border:1px solid #dceafb;
    border-radius:18px;
    background:
        radial-gradient(circle at 54% 62%, rgba(65,132,255,.11) 0, rgba(65,132,255,.11) 13%, transparent 14%),
        radial-gradient(circle at 80% 20%, rgba(65,132,255,.09) 0, rgba(65,132,255,.09) 9%, transparent 10%),
        linear-gradient(110deg,#f7fbff 0%,#eef6ff 58%,#f6fcff 100%);
    box-shadow:var(--shadow);
}
.mk2-hero:after {
    content:"";
    position:absolute;
    width:230px;
    height:230px;
    right:-92px;
    top:-78px;
    border-radius:50%;
    background:rgba(35,207,233,.08);
}
.mk2-kicker {
    margin-bottom:4px;
    color:#5d84c4;
    font-size:11px;
    font-weight:800;
    letter-spacing:.18em;
    text-transform:uppercase;
}
.mk2-hero h1 {
    margin:0;
    color:#081c49;
    font-size:38px;
    font-weight:800;
    line-height:1.08;
    letter-spacing:-.035em;
}
.mk2-hero p {
    max-width:680px;
    margin:8px 0 0;
    color:#536b8e;
    font-size:14px;
    line-height:1.55;
}
.mk2-actions {
    position:relative;
    z-index:2;
    display:grid;
    grid-template-columns:repeat(4, minmax(142px,1fr));
    gap:10px;
    min-width:700px;
}
.mk2-action {
    display:flex;
    align-items:center;
    gap:10px;
    min-height:58px;
    padding:10px 13px;
    border:1px solid #e0e8f4;
    border-radius:13px;
    background:rgba(255,255,255,.95);
    box-shadow:0 7px 20px rgba(17,43,88,.05);
    transition:.18s ease;
}
.mk2-action:hover {
    transform:translateY(-2px);
    border-color:#bed5fb;
    box-shadow:0 10px 24px rgba(21,93,252,.10);
}
.mk2-action-ico {
    display:grid;
    place-items:center;
    width:38px;
    height:38px;
    flex:0 0 38px;
    border-radius:10px;
    color:var(--blue);
    background:#eaf3ff;
}
.mk2-action:nth-child(2) .mk2-action-ico { color:#7a31dd; background:#f3ecff; }
.mk2-action:nth-child(3) .mk2-action-ico { color:#16a34a; background:#ecfaef; }
.mk2-action:nth-child(4) .mk2-action-ico { color:#e11d48; background:#fff0f3; }
.mk2-action strong {
    display:block;
    color:#0a204f;
    font-size:12px;
    line-height:1.25;
}
.mk2-action small {
    display:block;
    margin-top:2px;
    color:#8b9aaf;
    font-size:10px;
    line-height:1.25;
}

.mk2-card {
    border:1px solid var(--border);
    border-radius:16px;
    background:#fff;
    box-shadow:var(--shadow);
}
.mk2-section {
    padding:15px;
    margin-bottom:16px;
}
.mk2-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin-bottom:12px;
}
.mk2-title {
    display:flex;
    align-items:center;
    gap:9px;
    min-width:0;
}
.mk2-title-ico {
    display:grid;
    place-items:center;
    width:32px;
    height:32px;
    flex:0 0 32px;
    border-radius:9px;
    color:var(--blue);
    background:#edf4ff;
}
.mk2-title-ico svg { width:17px; height:17px; }
.mk2-title h3 {
    margin:0;
    color:#0b2252;
    font-size:15px;
    font-weight:800;
    line-height:1.2;
}
.mk2-title p {
    margin:2px 0 0;
    color:#8a99ae;
    font-size:10px;
    line-height:1.3;
}
.mk2-link {
    display:inline-flex;
    align-items:center;
    gap:5px;
    color:var(--blue) !important;
    font-size:11px;
    font-weight:800;
    white-space:nowrap;
}
.mk2-link svg { width:15px; height:15px; }

.mk2-status-grid {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:10px;
}
.mk2-status {
    position:relative;
    min-height:100px;
    padding:12px 13px;
    border:1px solid #e7edf5;
    border-radius:12px;
    background:#fbfcfe;
}
.mk2-status-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.mk2-status-ico {
    display:grid;
    place-items:center;
    width:36px;
    height:36px;
    border-radius:50%;
    color:#5a6b83;
    background:#edf1f6;
}
.mk2-status-ico svg { width:18px; height:18px; }
.mk2-status-arrow {
    color:#aab6c7;
}
.mk2-status-arrow svg { width:15px; height:15px; }
.mk2-status-count {
    margin-top:6px;
    color:#061a45;
    font-size:23px;
    font-weight:800;
    line-height:1;
}
.mk2-status-name {
    margin-top:5px;
    color:#435875;
    font-size:11px;
    font-weight:700;
}
.mk2-progress {
    border-color:#d7e7fd;
    background:linear-gradient(135deg,#fcfdff,#f0f6ff);
}
.mk2-progress .mk2-status-ico { color:#1f6deb; background:#deecff; }
.mk2-hold {
    border-color:#f5e5b9;
    background:linear-gradient(135deg,#fffefa,#fff7e9);
}
.mk2-hold .mk2-status-ico { color:#e89a06; background:#ffefc6; }
.mk2-cancelled {
    border-color:#f7dbe1;
    background:linear-gradient(135deg,#fffdfd,#fff1f3);
}
.mk2-cancelled .mk2-status-ico { color:#ef3558; background:#ffe0e6; }
.mk2-finished {
    border-color:#d5efdf;
    background:linear-gradient(135deg,#fcfffd,#effcf4);
}
.mk2-finished .mk2-status-ico { color:#17a45a; background:#d9f6e4; }

.mk2-main {
    display:grid;
    grid-template-columns:minmax(0,1.7fr) minmax(265px,.78fr) minmax(300px,.9fr);
    gap:16px;
    margin-bottom:16px;
}
.mk2-panel { padding:15px; }

.mk2-metrics {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:8px;
    margin-bottom:10px;
}
.mk2-metric {
    min-height:74px;
    padding:10px;
    border:1px solid #edf1f6;
    border-radius:11px;
    background:#fcfdff;
}
.mk2-metric-row {
    display:flex;
    align-items:center;
    gap:8px;
}
.mk2-mini-ico {
    display:grid;
    place-items:center;
    width:32px;
    height:32px;
    flex:0 0 32px;
    border-radius:9px;
    color:var(--blue);
    background:#edf4ff;
}
.mk2-mini-ico svg { width:17px; height:17px; }
.mk2-paid .mk2-mini-ico { color:#16a34a; background:#eaf9ef; }
.mk2-unpaid .mk2-mini-ico { color:#e11d48; background:#fff0f2; }
.mk2-overdue .mk2-mini-ico { color:#e8790a; background:#fff4e7; }
.mk2-metric strong {
    display:block;
    color:#0b2252;
    font-size:17px;
    line-height:1;
}
.mk2-metric span span {
    display:block;
    margin-top:3px;
    color:#6f8098;
    font-size:10px;
}

.mk2-chart-wrap {
    position:relative;
    height:185px;
    overflow:hidden;
}
.mk2-chart-wrap canvas {
    position:relative;
    z-index:1;
    width:100% !important;
    height:185px !important;
}
.mk2-no-chart {
    height:185px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
    border-top:1px solid #eef2f7;
    background:
        repeating-linear-gradient(to bottom,transparent 0,transparent 36px,#f1f4f8 37px);
}
.mk2-no-chart svg {
    width:26px;
    height:26px;
    color:#9aa8bc;
    margin-bottom:7px;
}
.mk2-no-chart strong {
    color:#45566e;
    font-size:11px;
}
.mk2-no-chart span {
    margin-top:3px;
    color:#91a0b3;
    font-size:10px;
}

.mk2-snapshot {
    margin:0;
    padding:0;
    list-style:none;
}
.mk2-snapshot li {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    min-height:38px;
    border-bottom:1px solid #eef2f6;
    color:#586a84;
    font-size:11px;
}
.mk2-snapshot li:last-child { border-bottom:0; }
.mk2-snapshot-label {
    display:flex;
    align-items:center;
    gap:7px;
}
.mk2-snapshot-label svg {
    width:16px;
    height:16px;
    color:#173b78;
}
.mk2-snapshot strong {
    max-width:130px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:#0b1f4d;
    font-weight:800;
}

.mk2-events-empty {
    min-height:220px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
}
.mk2-events-empty-ico {
    display:grid;
    place-items:center;
    width:78px;
    height:78px;
    margin-bottom:12px;
    border-radius:50%;
    color:#91a4c1;
    background:#f1f5fa;
}
.mk2-events-empty-ico svg { width:31px; height:31px; }
.mk2-events-empty strong {
    color:#0b2252;
    font-size:13px;
}
.mk2-events-empty p {
    max-width:225px;
    margin:5px 0 0;
    color:#8796aa;
    font-size:10px;
    line-height:1.45;
}
.mk2-event-list {
    display:flex;
    flex-direction:column;
    gap:8px;
}
.mk2-event {
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:10px;
    border:1px solid #edf1f6;
    border-radius:11px;
}
.mk2-event-date {
    min-width:46px;
    padding:6px 4px;
    text-align:center;
    border-radius:9px;
    color:var(--blue);
    background:#edf4ff;
}
.mk2-event-date strong {
    display:block;
    font-size:15px;
    line-height:1;
}
.mk2-event-date span {
    font-size:8px;
    text-transform:uppercase;
}
.mk2-event h4 {
    margin:1px 0 3px;
    color:#102654;
    font-size:11px;
    font-weight:800;
}
.mk2-event p {
    margin:0;
    color:#7e8da2;
    font-size:9px;
}

.mk2-bottom {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:16px;
}
.mk2-list {
    display:flex;
    flex-direction:column;
    gap:7px;
}
.mk2-list-item {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    min-height:54px;
    padding:9px 10px;
    border:1px solid #edf1f6;
    border-radius:11px;
    background:#fbfcfe;
}
.mk2-list-item:hover {
    border-color:#dbe7f9;
    background:#f8fbff;
}
.mk2-list-main {
    display:flex;
    align-items:center;
    gap:9px;
    min-width:0;
}
.mk2-list-ico {
    display:grid;
    place-items:center;
    width:34px;
    height:34px;
    flex:0 0 34px;
    border-radius:10px;
    color:var(--blue);
    background:#edf4ff;
}
.mk2-list-ico svg { width:17px; height:17px; }
.mk2-list-text {
    min-width:0;
}
.mk2-list-text strong {
    display:block;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:#102454;
    font-size:11px;
    font-weight:800;
}
.mk2-list-text span {
    display:block;
    margin-top:2px;
    color:#8796ab;
    font-size:9px;
}
.mk2-pill {
    display:inline-flex;
    padding:2px 6px;
    border-radius:999px;
    color:#1766d9;
    background:#e9f2ff;
    font-size:8px;
    font-weight:800;
}
.mk2-empty {
    min-height:130px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    text-align:center;
}
.mk2-empty-ico {
    display:grid;
    place-items:center;
    width:44px;
    height:44px;
    margin-bottom:7px;
    border-radius:50%;
    color:#93a2b7;
    background:#f2f5f9;
}
.mk2-empty-ico svg { width:19px; height:19px; }
.mk2-empty strong {
    color:#485970;
    font-size:11px;
}
.mk2-empty span {
    margin-top:3px;
    color:#8c9aae;
    font-size:9px;
}

@media (max-width: 1350px) {
    .mk2-actions {
        grid-template-columns:repeat(2,minmax(150px,1fr));
        min-width:360px;
    }
}

@media (max-width: 1100px) {
    .mk2-main {
        grid-template-columns:1fr 1fr;
    }
    .mk2-main > :first-child {
        grid-column:1 / -1;
    }
    .mk2-status-grid {
        grid-template-columns:repeat(3,1fr);
    }
}

@media (max-width: 900px) {
    body.mk2-client-home > .navbar.header .navbar-nav {
        display:block;
        min-height:0;
    }
    body.mk2-client-home > .navbar.header .navbar-nav > li > a {
        padding:10px 12px !important;
    }
    .mk2-hero {
        flex-direction:column;
        align-items:stretch;
    }
    .mk2-actions {
        min-width:0;
    }
    .mk2-bottom {
        grid-template-columns:1fr;
    }
}

@media (max-width: 767px) {
    body.mk2-client-home > .navbar.header .container,
    body.mk2-client-home #content > .container {
        width:100% !important;
    }
    body.mk2-client-home > .navbar.header .navbar-brand.logo {
        width:190px;
    }
    .mk2-hero {
        padding:20px 17px;
        border-radius:14px;
    }
    .mk2-hero h1 {
        font-size:29px;
    }
    .mk2-actions {
        grid-template-columns:1fr;
    }
    .mk2-status-grid,
    .mk2-main {
        grid-template-columns:1fr;
    }
    .mk2-main > :first-child {
        grid-column:auto;
    }
    .mk2-metrics {
        grid-template-columns:repeat(2,1fr);
    }
}

@media (max-width: 480px) {
    .mk2-status-grid,
    .mk2-metrics {
        grid-template-columns:1fr;
    }
}
</style>

<style>
/* Phase 3.4 dashboard brand overrides — loaded after legacy dashboard CSS */
body.mk2-client-home {
    background:
        radial-gradient(circle at 8% 0%,rgba(245,155,35,.075),transparent 22%),
        #f6f7f9 !important;
}

body.mk2-client-home .mk2-hero {
    border-color:#eadbc8 !important;
    background:
        radial-gradient(circle at 72% 18%,rgba(245,155,35,.16),transparent 22%),
        radial-gradient(circle at 96% 90%,rgba(245,155,35,.09),transparent 18%),
        linear-gradient(115deg,#fff 0%,#fff8ee 58%,#fffdf9 100%) !important;
}

body.mk2-client-home .mk2-eyebrow {
    color:#a35b00 !important;
}

body.mk2-client-home .mk2-hero h1,
body.mk2-client-home .mk2-title h3,
body.mk2-client-home .mk2-list-text strong,
body.mk2-client-home .mk2-snapshot strong {
    color:#111318 !important;
}

body.mk2-client-home .mk2-hero p,
body.mk2-client-home .mk2-title p,
body.mk2-client-home .mk2-list-text span,
body.mk2-client-home .mk2-snapshot-label {
    color:#747b85 !important;
}

body.mk2-client-home .mk2-action:hover,
body.mk2-client-home .mk2-status:hover,
body.mk2-client-home .mk2-list-item:hover {
    border-color:#efc78d !important;
    background:#fffaf3 !important;
}

body.mk2-client-home .mk2-link {
    color:#a45c00 !important;
}

body.mk2-client-home .mk2-link:hover {
    color:#7c4400 !important;
}

body.mk2-client-home .mk2-action-ico,
body.mk2-client-home .mk2-title-ico,
body.mk2-client-home .mk2-list-ico {
    color:#a65d00 !important;
    background:#fff3df !important;
}

body.mk2-client-home .mk2-panel,
body.mk2-client-home .mk2-card {
    border-color:#e8e9ec !important;
    box-shadow:0 10px 30px rgba(15,18,23,.045) !important;
}

body.mk2-client-home .mk2-pill {
    color:#8d4e00 !important;
    background:#fff3df !important;
}

body.mk2-client-home .mk2-progress {
    border-color:#efc78d !important;
    background:#fff8ef !important;
}

body.mk2-client-home .mk2-progress .mk2-status-ico {
    color:#a65d00 !important;
    background:#ffe9c5 !important;
}
</style>


<div class="mk2-dashboard">
    <section class="mk2-hero">
        <div>
            <div class="mk2-kicker">Welcome Back</div>
            <h1 id="mk2-greeting">Good Afternoon, <?= e(ucfirst($contact->firstname)); ?> 👋</h1>
            <p>Here’s what’s happening with your projects, invoices and account today.</p>
        </div>

        <div class="mk2-actions">
            <?php if (has_contact_permission('projects')) { ?>
                <a class="mk2-action" href="<?= site_url('clients/projects'); ?>">
                    <span class="mk2-action-ico"><?= mk2_icon('folder'); ?></span>
                    <span><strong>View Projects</strong><small>Track your work</small></span>
                </a>
            <?php } ?>

            <a class="mk2-action" href="<?= site_url('clients/files'); ?>">
                <span class="mk2-action-ico"><?= mk2_icon('file'); ?></span>
                <span><strong>Files</strong><small>Access shared files</small></span>
            </a>

            <a class="mk2-action" href="<?= site_url('clients/calendar'); ?>">
                <span class="mk2-action-ico"><?= mk2_icon('calendar'); ?></span>
                <span><strong>Calendar</strong><small>View upcoming events</small></span>
            </a>

            <?php if (has_contact_permission('support')) { ?>
                <a class="mk2-action" href="<?= site_url('clients/open_ticket'); ?>">
                    <span class="mk2-action-ico"><?= mk2_icon('support'); ?></span>
                    <span><strong>Open Ticket</strong><small>Get support</small></span>
                </a>
            <?php } ?>
        </div>
    </section>

    <?php if (has_contact_permission('projects')) { ?>
        <section class="mk2-card mk2-section">
            <div class="mk2-head">
                <div class="mk2-title">
                    <span class="mk2-title-ico"><?= mk2_icon('layers'); ?></span>
                    <div>
                        <h3>Projects Summary</h3>
                        <p>A quick overview of all your projects and their current status.</p>
                    </div>
                </div>
                <a class="mk2-link" href="<?= site_url('clients/projects'); ?>">
                    View All Projects <?= mk2_icon('arrow'); ?>
                </a>
            </div>

            <div class="mk2-status-grid">
                <?php foreach ($projectStatuses as $status) {
                    $meta = mk2_status_meta($status);
                    $count = $projectCounts[(int) $status['id']] ?? 0;
                ?>
                    <a href="<?= site_url('clients/projects/' . (int) $status['id']); ?>"
                       class="mk2-status <?= e($meta['class']); ?>">
                        <div class="mk2-status-top">
                            <span class="mk2-status-ico"><?= mk2_icon($meta['icon']); ?></span>
                            <span class="mk2-status-arrow"><?= mk2_icon('arrow'); ?></span>
                        </div>
                        <div class="mk2-status-count"><?= e($count); ?></div>
                        <div class="mk2-status-name"><?= e($status['name']); ?></div>
                    </a>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

    <div class="mk2-main">
        <?php if (has_contact_permission('invoices')) { ?>
            <section class="mk2-card mk2-panel">
                <div class="mk2-head">
                    <div class="mk2-title">
                        <span class="mk2-title-ico"><?= mk2_icon('invoice'); ?></span>
                        <div>
                            <h3>Invoices &amp; Payments</h3>
                            <p>Your invoice overview and payment status.</p>
                        </div>
                    </div>
                    <a class="mk2-link" href="<?= site_url('clients/invoices'); ?>">
                        View Invoices <?= mk2_icon('arrow'); ?>
                    </a>
                </div>

                <div class="mk2-metrics">
                    <div class="mk2-metric">
                        <div class="mk2-metric-row">
                            <span class="mk2-mini-ico"><?= mk2_icon('invoice'); ?></span>
                            <span><strong><?= e($totalInvoices); ?></strong><span>Total Invoices</span></span>
                        </div>
                    </div>

                    <div class="mk2-metric mk2-paid">
                        <div class="mk2-metric-row">
                            <span class="mk2-mini-ico"><?= mk2_icon('check'); ?></span>
                            <span><strong><?= e($totalPaid); ?></strong><span>Paid</span></span>
                        </div>
                    </div>

                    <div class="mk2-metric mk2-unpaid">
                        <div class="mk2-metric-row">
                            <span class="mk2-mini-ico"><?= mk2_icon('clock'); ?></span>
                            <span><strong><?= e($totalUnpaid); ?></strong><span>Unpaid</span></span>
                        </div>
                    </div>

                    <div class="mk2-metric mk2-overdue">
                        <div class="mk2-metric-row">
                            <span class="mk2-mini-ico"><?= mk2_icon('alert'); ?></span>
                            <span><strong><?= e($totalOverdue); ?></strong><span>Overdue</span></span>
                        </div>
                    </div>
                </div>

                <?php if ($totalInvoices > 0) { ?>
                    <div class="mk2-chart-wrap">
                        <canvas id="client-home-chart" height="185" class="animated fadeIn"></canvas>
                    </div>
                <?php } else { ?>
                    <div class="mk2-no-chart">
                        <?= mk2_icon('activity'); ?>
                        <strong>No invoice data available yet.</strong>
                        <span>Your invoice activity will appear here once available.</span>
                    </div>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="mk2-card mk2-panel">
            <div class="mk2-head">
                <div class="mk2-title">
                    <span class="mk2-title-ico"><?= mk2_icon('user'); ?></span>
                    <div>
                        <h3>Account Snapshot</h3>
                        <p>Your account at a glance.</p>
                    </div>
                </div>
                <a class="mk2-link" href="<?= site_url('clients/profile'); ?>">
                    View Account <?= mk2_icon('arrow'); ?>
                </a>
            </div>

            <ul class="mk2-snapshot">
                <li><span class="mk2-snapshot-label"><?= mk2_icon('calendar'); ?> Customer Since</span><strong><?= $client && $client->datecreated ? e(_d(date('Y-m-d', strtotime($client->datecreated)))) : '—'; ?></strong></li>
                <li><span class="mk2-snapshot-label"><?= mk2_icon('folder'); ?> Total Projects</span><strong><?= e($totalProjects); ?></strong></li>
                <li><span class="mk2-snapshot-label"><?= mk2_icon('invoice'); ?> Total Invoices</span><strong><?= e($totalInvoices); ?></strong></li>
                <li><span class="mk2-snapshot-label"><?= mk2_icon('payment'); ?> Total Payments</span><strong><?= e($totalPayments); ?></strong></li>
                <li><span class="mk2-snapshot-label"><?= mk2_icon('support'); ?> Support Tickets</span><strong><?= e($openTickets); ?></strong></li>
                <li><span class="mk2-snapshot-label"><?= mk2_icon('file'); ?> Company</span><strong><?= e($client && $client->company ? $client->company : '—'); ?></strong></li>
            </ul>
        </section>

        <section class="mk2-card mk2-panel">
            <div class="mk2-head">
                <div class="mk2-title">
                    <span class="mk2-title-ico"><?= mk2_icon('calendar'); ?></span>
                    <div>
                        <h3>Upcoming Events</h3>
                        <p>Your upcoming project dates.</p>
                    </div>
                </div>
                <a class="mk2-link" href="<?= site_url('clients/calendar'); ?>">
                    View Calendar <?= mk2_icon('arrow'); ?>
                </a>
            </div>

            <?php if (empty($upcoming)) { ?>
                <div class="mk2-events-empty">
                    <span class="mk2-events-empty-ico"><?= mk2_icon('calendar'); ?></span>
                    <strong>No upcoming events</strong>
                    <p>You’re all caught up! New events and important dates will appear here.</p>
                </div>
            <?php } else { ?>
                <div class="mk2-event-list">
                    <?php foreach ($upcoming as $event) { ?>
                        <a class="mk2-event" href="<?= site_url('clients/project/' . (int) $event['rel_id']); ?>">
                            <span class="mk2-event-date">
                                <strong><?= e(date('d', strtotime($event['duedate']))); ?></strong>
                                <span><?= e(date('M', strtotime($event['duedate']))); ?></span>
                            </span>
                            <span>
                                <h4><?= e($event['name']); ?></h4>
                                <p>Due <?= e(_d($event['duedate'])); ?></p>
                            </span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </div>

    <div class="mk2-bottom">
        <section class="mk2-card mk2-panel">
            <div class="mk2-head">
                <div class="mk2-title">
                    <span class="mk2-title-ico"><?= mk2_icon('folder'); ?></span>
                    <div>
                        <h3>Recent Projects</h3>
                        <p>Your latest project activity.</p>
                    </div>
                </div>
                <?php if (has_contact_permission('projects')) { ?>
                    <a class="mk2-link" href="<?= site_url('clients/projects'); ?>">View All <?= mk2_icon('arrow'); ?></a>
                <?php } ?>
            </div>

            <?php if (empty($recentProjects)) { ?>
                <div class="mk2-empty">
                    <span class="mk2-empty-ico"><?= mk2_icon('folder'); ?></span>
                    <strong>No projects yet</strong>
                    <span>Your recent projects will appear here.</span>
                </div>
            <?php } else { ?>
                <div class="mk2-list">
                    <?php foreach ($recentProjects as $project) {
                        $statusData = get_project_status_by_id($project['status']);
                    ?>
                        <a class="mk2-list-item" href="<?= site_url('clients/project/' . (int) $project['id']); ?>">
                            <span class="mk2-list-main">
                                <span class="mk2-list-ico"><?= mk2_icon('folder'); ?></span>
                                <span class="mk2-list-text">
                                    <strong><?= e($project['name']); ?></strong>
                                    <span><?= !empty($statusData['name']) ? '<span class="mk2-pill">' . e($statusData['name']) . '</span>' : ''; ?></span>
                                </span>
                            </span>
                            <span class="mk2-status-arrow"><?= mk2_icon('arrow'); ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <section class="mk2-card mk2-panel">
            <div class="mk2-head">
                <div class="mk2-title">
                    <span class="mk2-title-ico"><?= mk2_icon('file'); ?></span>
                    <div>
                        <h3>Recent Files</h3>
                        <p>Your latest shared files.</p>
                    </div>
                </div>
                <a class="mk2-link" href="<?= site_url('clients/files'); ?>">View All <?= mk2_icon('arrow'); ?></a>
            </div>

            <?php if (empty($recentFiles)) { ?>
                <div class="mk2-empty">
                    <span class="mk2-empty-ico"><?= mk2_icon('file'); ?></span>
                    <strong>No files yet</strong>
                    <span>Shared files from your projects will appear here.</span>
                </div>
            <?php } else { ?>
                <div class="mk2-list">
                    <?php foreach ($recentFiles as $file) {
                        $fileTitle = !empty($file['subject']) ? $file['subject'] : $file['file_name'];
                    ?>
                        <a class="mk2-list-item" href="<?= site_url('clients/project/' . (int) $file['project_id'] . '?group=project_files'); ?>">
                            <span class="mk2-list-main">
                                <span class="mk2-list-ico"><?= mk2_icon('file'); ?></span>
                                <span class="mk2-list-text">
                                    <strong><?= e($fileTitle); ?></strong>
                                    <span><?= e($file['project_name']); ?> · <?= e(_dt($file['dateadded'])); ?></span>
                                </span>
                            </span>
                            <span class="mk2-status-arrow"><?= mk2_icon('arrow'); ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <section class="mk2-card mk2-panel">
            <div class="mk2-head">
                <div class="mk2-title">
                    <span class="mk2-title-ico"><?= mk2_icon('activity'); ?></span>
                    <div>
                        <h3>Recent Activity</h3>
                        <p>Your latest account activity.</p>
                    </div>
                </div>
            </div>

            <?php if (empty($recentActivity)) { ?>
                <div class="mk2-empty">
                    <span class="mk2-empty-ico"><?= mk2_icon('activity'); ?></span>
                    <strong>No recent activity</strong>
                    <span>Activity from your projects will appear here.</span>
                </div>
            <?php } else { ?>
                <div class="mk2-list">
                    <?php foreach ($recentActivity as $activity) { ?>
                        <a class="mk2-list-item" href="<?= site_url('clients/project/' . (int) $activity['project_id']); ?>">
                            <span class="mk2-list-main">
                                <span class="mk2-list-ico"><?= mk2_icon('activity'); ?></span>
                                <span class="mk2-list-text">
                                    <strong><?= e(mk2_activity_label($activity)); ?></strong>
                                    <span><?= e($activity['project_name']); ?> · <?= e(_dt($activity['dateadded'])); ?></span>
                                </span>
                            </span>
                            <span class="mk2-status-arrow"><?= mk2_icon('arrow'); ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </div>
    <?php hooks()->do_action('client_area_after_project_overview'); ?>
    <?php hooks()->do_action('client_area_dashboard_end'); ?>
</div>

<script>
(function () {
    document.body.classList.add('mk2-client-home');

    var hour = new Date().getHours();
    var greeting = hour < 12
        ? "<?= e(_l('good_morning')); ?>"
        : (hour < 18 ? "<?= e(_l('good_afternoon')); ?>" : "<?= e(_l('good_evening')); ?>");

    var name = "<?= e(ucfirst($contact->firstname)); ?>";
    var el = document.getElementById('mk2-greeting');
    if (el) {
        el.textContent = greeting + ', ' + name + ' 👋';
    }
})();
</script>


<style>
/* Phase 3.4.1 — remove remaining legacy blue from dashboard interactions */
body.mk2-client-home .mk2-eyebrow,
body.mk2-client-home .mk2-link,
body.mk2-client-home .mk2-dashboard a:hover,
body.mk2-client-home > .navbar.header .mk43-primary-list > li > a:hover,
body.mk2-client-home > .navbar.header .mk43-primary-list > li.active > a {
    color:#9a5600 !important;
}

body.mk2-client-home > .navbar.header .mk43-primary-list > li > a:hover i,
body.mk2-client-home > .navbar.header .mk43-primary-list > li.active > a i {
    color:#f59b23 !important;
}

body.mk2-client-home > .navbar.header .mk43-primary-list > li.active > a {
    border-color:#efc78d !important;
    background:#fff8ee !important;
    box-shadow:inset 0 0 0 1px #efc78d,0 6px 18px rgba(245,155,35,.10) !important;
}

body.mk2-client-home .mk2-action:hover strong,
body.mk2-client-home .mk2-status:hover strong,
body.mk2-client-home .mk2-list-item:hover strong {
    color:#8d4e00 !important;
}

body.mk2-client-home .mk2-action:hover svg,
body.mk2-client-home .mk2-link:hover svg {
    color:#dd7c0a !important;
    stroke:#dd7c0a !important;
}
</style>
