<?php
// ── Path detection ──────────────────────────────────────────────────────────
$script_path  = $_SERVER['SCRIPT_NAME'];
$request_path = $_SERVER['REQUEST_URI'];
$clean_path   = parse_url($request_path, PHP_URL_PATH);

if (strpos($clean_path, '/pages/') !== false) {
    $levels_up = 2;
    if (strpos($clean_path, '/pages/people_attendance/') !== false) {
        $levels_up = 3;
    }
} elseif (strpos($clean_path, '/includes/') !== false) {
    $levels_up = 1;
} else {
    $levels_up = 0;
}

$relative_path = $levels_up === 0 ? './' : str_repeat('../', $levels_up);

// ── Session / user info ─────────────────────────────────────────────────────
$user_name     = $_SESSION['username'] ?? 'User';
$user_role     = function_exists('getUserRole') ? getUserRole() : 'guest';
$user_initials = strtoupper(substr(preg_replace('/\s+.*/', '', $user_name), 0, 1));

$can_people_module = function_exists('canAccessModule') ? canAccessModule('people_attendance', $user_role) : in_array($user_role, ['admin', 'staff']);
$can_finance_module = function_exists('canAccessModule') ? canAccessModule('finance', $user_role) : ($user_role === 'admin');
$can_comm_module = function_exists('canAccessModule') ? canAccessModule('communication', $user_role) : ($user_role === 'admin');
$can_admin_module = function_exists('canAccessModule') ? canAccessModule('administration', $user_role) : ($user_role === 'admin');
$system_notice = function_exists('pullSystemNotice') ? pullSystemNotice() : null;

// ── Topbar: module label + page title ───────────────────────────────────────
$_module_map = [
    '/members'       => 'People & Attendance',
    '/visitors'      => 'People & Attendance',
    '/attendance'    => 'People & Attendance',
    '/services'      => 'People & Attendance',
    '/reports'       => 'People & Attendance',
    '/checkin'       => 'People & Attendance',
    '/finance'       => 'Finance',
    '/communication' => 'Communication',
    '/pastoral'      => 'Pastoral Care',
    '/admin'         => 'Administration',
];
$topbar_module = getInstitutionName($pdo);
foreach ($_module_map as $_mp => $_ml) {
    if (strpos($clean_path, $_mp) !== false) { $topbar_module = $_ml; break; }
}
if (isset($page_heading)) {
    $topbar_title = $page_heading;
} elseif (isset($page_title)) {
    $inst_name = getInstitutionName($pdo);
    $topbar_title = preg_replace('/ ?[–\-|—] ?' . preg_quote($inst_name, '/') . '$/', '', $page_title);
} else {
    $topbar_title = 'Dashboard';
}

// ── Active link helpers ─────────────────────────────────────────────────────
$_req  = $clean_path;
$_self = basename($script_path);

$_isHub        = ($_self === 'index.php' && strpos($_req, '/pages/') === false);
$_isAttDash    = strpos($_req, 'attendance/dashboard') !== false;
$_isMembers    = strpos($_req, '/members')    !== false;
$_isVisitors   = strpos($_req, '/visitors')   !== false && strpos($_req, 'new_converts') === false;
$_isConverts   = strpos($_req, 'new_converts') !== false;
$_isServices   = strpos($_req, '/services')   !== false;
$_isSessions   = strpos($_req, '/sessions')   !== false;
$_isCheckin    = strpos($_req, '/checkin')    !== false;
$_isAttendance = strpos($_req, '/attendance') !== false && !$_isAttDash;
$_isReports    = strpos($_req, '/reports')    !== false;
$_isAdmin      = strpos($_req, '/admin')      !== false;
$_isFinance    = strpos($_req, '/finance')    !== false;
$_isFinanceDash = strpos($_req, '/finance/dashboard') !== false;
$_isFinanceIncome = strpos($_req, '/finance/income') !== false;
$_isFinanceExpenses = strpos($_req, '/finance/expenses') !== false;
$_isFinanceReports = strpos($_req, '/finance/reports') !== false;
$_isFinanceTithers = strpos($_req, '/finance/tithers') !== false;
$_isCommunication = strpos($_req, '/communication') !== false;
$_isCommDash = strpos($_req, '/communication/dashboard') !== false;
$_isCommCampaigns = strpos($_req, '/communication/campaigns') !== false;
$_isCommSms = strpos($_req, '/communication/sms') !== false;
$_isCommLogs = strpos($_req, '/communication/logs') !== false;
$_isUsers      = strpos($_req, '/admin/users') !== false;

function _navClass(bool $a): string { return $a ? ' class="sidebar-link active"' : ' class="sidebar-link"'; }

$_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === '1';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : htmlspecialchars(getInstitutionName($pdo)); ?></title>
    <link rel="icon" type="image/png" href="<?php echo $relative_path . getInstitutionLogo($pdo); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $relative_path; ?>assets/css/sidebar.css?v=<?php echo @filemtime($relative_path . 'assets/css/sidebar.css'); ?>" rel="stylesheet">
    <link href="<?php echo $relative_path; ?>assets/css/mobile-responsive.css" rel="stylesheet">
    <link href="<?php echo $relative_path; ?>assets/css/icons.css" rel="stylesheet">
</head>
<body>

<!-- Mobile overlay -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- ═══════════════════════════ SIDEBAR ═══════════════════════════════════ -->
<nav id="sidebar"<?php if ($_collapsed) echo ' class="collapsed"'; ?>>

    <a class="sidebar-brand" href="<?php echo $relative_path; ?>index">
        <img class="sidebar-brand-logo"
             src="<?php echo $relative_path . getInstitutionLogo($pdo); ?>" alt="Institution Logo">
        <span class="sidebar-brand-text">
            <span class="sidebar-brand-name"><?php echo htmlspecialchars(getInstitutionName($pdo)); ?></span>

            <span class="sidebar-brand-sub">Church System</span>
        </span>
    </a>

    <div class="sidebar-nav-wrap">

        <!-- Hub -->
        <a href="<?php echo $relative_path; ?>index"
           title="Home Dashboard"<?php echo _navClass($_isHub); ?>>
            <i class="bi bi-grid-1x2-fill"></i>
            <span class="sidebar-link-label">Home Dashboard</span>
        </a>

        <?php if ($can_people_module): ?>
        <!-- ── People & Attendance ── -->
        <div class="sidebar-section">People &amp; Attendance</div>
        <div class="sidebar-section-divider"></div>

          <a href="<?php echo $relative_path; ?>pages/people_attendance/attendance/dashboard"
           title="Attendance Overview"<?php echo _navClass($_isAttDash || $_isAttendance); ?>>
            <i class="bi bi-speedometer2"></i>
            <span class="sidebar-link-label">Overview</span>
        </a>
          <a href="<?php echo $relative_path; ?>pages/people_attendance/members/list"
           title="Members"<?php echo _navClass($_isMembers); ?>>
            <i class="bi bi-people-fill"></i>
            <span class="sidebar-link-label">Members</span>
        </a>
          <a href="<?php echo $relative_path; ?>pages/people_attendance/visitors/list"
           title="Visitors"<?php echo _navClass($_isVisitors); ?>>
            <i class="bi bi-person-badge"></i>
            <span class="sidebar-link-label">Visitors</span>
        </a>
          <a href="<?php echo $relative_path; ?>pages/people_attendance/visitors/new_converts"
           title="New Converts"<?php echo _navClass($_isConverts); ?>>
            <i class="bi bi-person-plus-fill"></i>
            <span class="sidebar-link-label">New Converts</span>
        </a>
          <a href="<?php echo $relative_path; ?>pages/people_attendance/services/list"
           title="Services"<?php echo _navClass($_isServices); ?>>
            <i class="bi bi-calendar-event-fill"></i>
            <span class="sidebar-link-label">Services</span>
        </a>
          <a href="<?php echo $relative_path; ?>pages/people_attendance/services/sessions"
           title="Sessions &amp; Attendance"<?php echo _navClass($_isSessions); ?>>
            <i class="bi bi-clipboard-check-fill"></i>
            <span class="sidebar-link-label">Sessions</span>
        </a>
          <a href="<?php echo $relative_path; ?>pages/people_attendance/checkin/index"
           title="Check-in"<?php echo _navClass($_isCheckin); ?>>
            <i class="bi bi-qr-code-scan"></i>
            <span class="sidebar-link-label">Check-in</span>
        </a>
          <a href="<?php echo $relative_path; ?>pages/people_attendance/reports/report"
           title="Reports"<?php echo _navClass($_isReports); ?>>
            <i class="bi bi-graph-up-arrow"></i>
            <span class="sidebar-link-label">Reports</span>
        </a>
        <?php endif; ?>

        <?php if ($can_finance_module): ?>
        <!-- ── Finance ── -->
        <div class="sidebar-section">Finance</div>
        <div class="sidebar-section-divider"></div>
          <a href="<?php echo $relative_path; ?>pages/finance/dashboard"
              title="Finance Overview"<?php echo _navClass($_isFinanceDash || ($_isFinance && !$_isFinanceIncome && !$_isFinanceExpenses && !$_isFinanceReports && !$_isFinanceTithers)); ?>>
            <i class="bi bi-cash-coin"></i>
            <span class="sidebar-link-label">Overview</span>
        </a>
        <a href="<?php echo $relative_path; ?>pages/finance/income"
           title="Finance Income"<?php echo _navClass($_isFinanceIncome); ?>>
            <i class="bi bi-wallet2"></i>
            <span class="sidebar-link-label">Income</span>
        </a>
        <a href="<?php echo $relative_path; ?>pages/finance/expenses"
           title="Finance Expenses"<?php echo _navClass($_isFinanceExpenses); ?>>
            <i class="bi bi-receipt-cutoff"></i>
            <span class="sidebar-link-label">Expenses</span>
        </a>
        <a href="<?php echo $relative_path; ?>pages/finance/reports"
           title="Finance Reports"<?php echo _navClass($_isFinanceReports); ?>>
            <i class="bi bi-bar-chart-line-fill"></i>
            <span class="sidebar-link-label">Reports</span>
        </a>
        <a href="<?php echo $relative_path; ?>pages/finance/tithers"
           title="Tithers"<?php echo _navClass($_isFinanceTithers); ?>>
            <i class="bi bi-journal-bookmark-fill"></i>
            <span class="sidebar-link-label">Tithers</span>
        </a>
        <?php endif; ?>

        <?php if ($can_comm_module): ?>
        <!-- ── Communication ── -->
        <div class="sidebar-section">Communication</div>
        <div class="sidebar-section-divider"></div>
        <a href="<?php echo $relative_path; ?>pages/communication/dashboard"
                            title="Communication Overview"<?php echo _navClass($_isCommDash || ($_isCommunication && !$_isCommCampaigns && !$_isCommSms && !$_isCommLogs)); ?>>
            <i class="bi bi-chat-dots-fill"></i>
            <span class="sidebar-link-label">Overview</span>
        </a>
        <a href="<?php echo $relative_path; ?>pages/communication/campaigns"
           title="Campaigns"<?php echo _navClass($_isCommCampaigns); ?>>
            <i class="bi bi-broadcast-pin"></i>
            <span class="sidebar-link-label">Campaigns</span>
        </a>
        <a href="<?php echo $relative_path; ?>pages/communication/sms"
           title="SMS Center"<?php echo _navClass($_isCommSms); ?>>
            <i class="bi bi-chat-square-text-fill"></i>
            <span class="sidebar-link-label">SMS Center</span>
        </a>
        <a href="<?php echo $relative_path; ?>pages/communication/logs"
           title="Delivery Logs"<?php echo _navClass($_isCommLogs); ?>>
            <i class="bi bi-clock-history"></i>
            <span class="sidebar-link-label">Logs</span>
        </a>
        <?php endif; ?>

        <?php if ($can_admin_module): ?>
        <!-- ── Pastoral Care ── -->
        <div class="sidebar-section">Pastoral Care</div>
        <div class="sidebar-section-divider"></div>
        <a href="#" title="Pastoral Care" class="sidebar-link disabled-link">
            <i class="bi bi-heart-pulse-fill"></i>
            <span class="sidebar-link-label">Pastoral Care</span>
            <span class="sidebar-badge badge-soon">Soon</span>
        </a>

        <!-- ── Administration ── -->
        <div class="sidebar-section">Administration</div>
        <div class="sidebar-section-divider"></div>
          <a href="<?php echo $relative_path; ?>pages/admin/users"
              title="Users"<?php echo _navClass($_isUsers); ?>>
                <i class="bi bi-person-gear"></i>
                <span class="sidebar-link-label">Users</span>
          </a>
        <a href="<?php echo $relative_path; ?>pages/admin/settings"
           title="Settings"<?php echo _navClass($_isAdmin); ?>>
            <i class="bi bi-gear-fill"></i>
            <span class="sidebar-link-label">Settings</span>
        </a>
        <?php endif; ?>

    </div><!-- /.sidebar-nav-wrap -->

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
            <div class="sidebar-user-info">
                <span class="sidebar-user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="sidebar-user-role"><?php echo htmlspecialchars($user_role); ?></span>
            </div>
        </div>
        <a href="<?php echo $relative_path; ?>logout" class="sidebar-logout" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
            <span class="sidebar-logout-text">Logout</span>
        </a>
    </div>

</nav><!-- /#sidebar -->

<!-- ═══════════════════════════ MAIN CONTENT ══════════════════════════════ -->
<div id="mainContent" class="main-content<?php if ($_collapsed) echo ' sidebar-collapsed'; ?>">

    <!-- Top bar -->
    <header class="topbar">
        <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-breadcrumb">
            <span class="topbar-module"><?php echo htmlspecialchars($topbar_module); ?></span>
            <span class="topbar-page-title"><?php echo htmlspecialchars($topbar_title); ?></span>
        </div>
        <div class="topbar-right">
            <span class="topbar-date"><?php echo date('D, M j, Y'); ?></span>
            <div class="topbar-user">
                <div class="topbar-user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                <span class="user-label"><?php echo htmlspecialchars($user_name); ?></span>
            </div>
        </div>
    </header>

    <!-- Page content -->
    <main class="page-content">
        <?php if (is_array($system_notice) && !empty($system_notice['message'])): ?>
        <?php $notice_type = ($system_notice['type'] ?? 'info') === 'warning' ? 'warning' : 'info'; ?>
        <div class="alert alert-<?php echo $notice_type; ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-shield-exclamation me-2"></i>
            <?php echo htmlspecialchars((string)$system_notice['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($page_header) && $page_header): ?>
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="text-gradient mb-2">
                        <?php echo isset($page_icon) ? '<i class="' . $page_icon . '"></i> ' : ''; ?>
                        <?php echo isset($page_heading) ? htmlspecialchars($page_heading) : 'Page Title'; ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <?php echo isset($page_description) ? htmlspecialchars($page_description) : ''; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if (isset($page_actions)) echo $page_actions; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>