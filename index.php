<?php
require_once 'includes/security.php';
requireLogin();

$page_title   = "Bridge Ministries International - Church Management";
$page_heading = "Church Management Hub";
$page_header  = false;

$user_role = getUserRole();
$user_name = $_SESSION['username'] ?? 'Guest';

$can_people_module = canAccessModule('people_attendance', $user_role);
$can_finance_module = canAccessModule('finance', $user_role);
$can_comm_module = canAccessModule('communication', $user_role);
$can_admin_module = canAccessModule('administration', $user_role);

$primary_action_href = 'index.php';
$primary_action_text = 'Open Home Dashboard';
$primary_action_icon = 'bi-grid-1x2-fill';

if ($can_people_module) {
    $primary_action_href = 'pages/people_attendance/attendance/dashboard.php';
    $primary_action_text = 'Open Attendance Dashboard';
    $primary_action_icon = 'bi-speedometer2';
} elseif ($can_finance_module) {
    $primary_action_href = 'pages/finance/dashboard.php';
    $primary_action_text = 'Open Finance Dashboard';
    $primary_action_icon = 'bi-cash-coin';
} elseif ($can_comm_module) {
    $primary_action_href = 'pages/communication/dashboard.php';
    $primary_action_text = 'Open Communication Dashboard';
    $primary_action_icon = 'bi-chat-dots-fill';
}

require_once 'config/database.php';
require_once 'includes/attendance_utils.php';

// Quick cross-module stats
try {
    $members_count  = $pdo->query("SELECT COUNT(*) FROM member_roles WHERE status = 'active'")->fetchColumn();
    $converts_count = $pdo->query("SELECT COUNT(*) FROM new_convert_roles WHERE status = 'active'")->fetchColumn();
    $today_att_data = getTotalDailyAttendance($pdo);
    $today_att      = $today_att_data['total_attendance'] ?? 0;
    $visitors_count = $pdo->query("SELECT COUNT(*) FROM visitor_roles WHERE (status IS NULL OR status = 'pending')")->fetchColumn();
} catch (Exception $e) {
    $members_count = $converts_count = $today_att = $visitors_count = 0;
}

include 'includes/header.php';
?>
<link href="assets/css/hub-dashboard.css?v=<?php echo filemtime('assets/css/hub-dashboard.css'); ?>" rel="stylesheet">

<div class="container-fluid hub-dashboard">

    <section class="hub-hero">
        <div class="hub-hero-left">
            <p class="hub-eyebrow">CHURCH MANAGEMENT SYSTEM</p>
            <h1 class="hub-title">Welcome back, <?php echo htmlspecialchars($user_name); ?></h1>
            <p class="hub-subtitle">Manage people, attendance, services and upcoming ministry modules from one professional workspace.</p>
        </div>
        <div class="hub-hero-right">
            <a href="<?php echo $primary_action_href; ?>" class="hub-primary-action">
                <i class="bi <?php echo $primary_action_icon; ?>"></i>
                <span><?php echo htmlspecialchars($primary_action_text); ?></span>
            </a>
            <div class="hub-date"><?php echo date('l, F j, Y'); ?></div>
        </div>
    </section>

    <?php if ($can_people_module): ?>
    <section class="hub-kpis">
        <article class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <p class="kpi-label">Active Members</p>
                <h3 class="kpi-value"><?php echo number_format($members_count); ?></h3>
            </div>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-person-badge"></i></div>
            <div>
                <p class="kpi-label">Current Visitors</p>
                <h3 class="kpi-value"><?php echo number_format($visitors_count); ?></h3>
            </div>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-clipboard-check-fill"></i></div>
            <div>
                <p class="kpi-label">Attendance Today</p>
                <h3 class="kpi-value"><?php echo number_format($today_att); ?></h3>
            </div>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-person-plus-fill"></i></div>
            <div>
                <p class="kpi-label">Active Converts</p>
                <h3 class="kpi-value"><?php echo number_format($converts_count); ?></h3>
            </div>
        </article>
    </section>
    <?php endif; ?>

    <section class="hub-workspace row g-4">
        <div class="col-12">
            <div class="hub-panel">
                <div class="hub-panel-head">
                    <h2>System Modules</h2>
                    <span>Main operational areas</span>
                </div>
                <div class="row g-3 hub-modules-grid">
                    <?php if ($can_people_module): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="pages/people_attendance/attendance/dashboard.php" class="module-card mod-attendance">
                            <span class="module-card-badge badge-active">Active</span>
                            <div class="module-icon-wrap"><i class="bi bi-people-fill"></i></div>
                            <div class="module-card-title">People &amp; Attendance</div>
                            <p class="module-card-desc">Members, visitors, converts, services, sessions, check-in and reports.</p>
                            <div class="hub-bottom-link">Open Module <i class="bi bi-arrow-right"></i></div>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($can_finance_module): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="pages/finance/dashboard.php" class="module-card mod-finance">
                            <span class="module-card-badge badge-active">Active</span>
                            <div class="module-icon-wrap"><i class="bi bi-cash-coin"></i></div>
                            <div class="module-card-title">Finance</div>
                            <p class="module-card-desc">Tithes, offerings, expenses, receipts and financial analytics workspace.</p>
                            <div class="hub-bottom-link">Open Module <i class="bi bi-arrow-right"></i></div>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($can_comm_module): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="pages/communication/dashboard.php" class="module-card mod-comms">
                            <span class="module-card-badge badge-active">Active</span>
                            <div class="module-icon-wrap"><i class="bi bi-chat-dots-fill"></i></div>
                            <div class="module-card-title">Communication</div>
                            <p class="module-card-desc">Announcements, SMS/email campaigns, messaging and communication logs.</p>
                            <div class="hub-bottom-link">Open Module <i class="bi bi-arrow-right"></i></div>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($can_admin_module): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="module-card mod-pastoral disabled">
                            <span class="module-card-badge badge-planned">Planned</span>
                            <div class="module-icon-wrap"><i class="bi bi-heart-pulse-fill"></i></div>
                            <div class="module-card-title">Pastoral Care</div>
                            <p class="module-card-desc">Counseling cases, follow-up tasks, prayer requests and pastoral notes.</p>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="module-card mod-perms disabled">
                            <span class="module-card-badge badge-planned">Planned</span>
                            <div class="module-icon-wrap"><i class="bi bi-shield-lock-fill"></i></div>
                            <div class="module-card-title">Permissions &amp; Audit</div>
                            <p class="module-card-desc">Role permissions, approval flow and full audit trail visibility.</p>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="module-card mod-people disabled">
                            <span class="module-card-badge badge-planned">Planned</span>
                            <div class="module-icon-wrap"><i class="bi bi-person-lines-fill"></i></div>
                            <div class="module-card-title">People Directory</div>
                            <p class="module-card-desc">Unified person profile and complete journey timeline.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($can_admin_module): ?>
            <div class="hub-panel hub-admin-panel">
                <div class="hub-panel-head">
                    <h2>Administration</h2>
                    <span>System configuration</span>
                </div>
                <a href="pages/admin/users.php" class="admin-entry mb-3">
                    <span class="admin-entry-icon"><i class="bi bi-person-gear"></i></span>
                    <span>
                        <strong>User Management</strong>
                        <small>Create users, assign roles and reset access passwords</small>
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </a>
                <a href="pages/admin/settings.php" class="admin-entry">
                    <span class="admin-entry-icon"><i class="bi bi-gear-fill"></i></span>
                    <span>
                        <strong>System Settings</strong>
                        <small>Departments, cell centers and master setup</small>
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

</div><!-- /.container-fluid -->

<?php include 'includes/footer.php'; ?>

