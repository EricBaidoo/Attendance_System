<?php
require_once '../../../includes/security.php';
requireLogin('../../../login');

$page_title   = "People & Attendance Overview - Bridge Ministries International";
$page_heading = "People & Attendance";
$page_header  = false;

$user_role = getUserRole();
$user_name = $_SESSION['username'] ?? 'Guest';

require_once '../../../config/database.php';
require_once '../../../includes/attendance_utils.php';

try {
    $members_count  = $pdo->query("SELECT COUNT(*) FROM people p JOIN member_roles mr ON mr.person_id = p.id WHERE p.current_stage = 'member' AND mr.status = 'active'")->fetchColumn();
    $visitors_count = $pdo->query("SELECT COUNT(*) FROM people WHERE current_stage = 'visitor'")->fetchColumn();
    $male_count     = $pdo->query("SELECT COUNT(*) FROM people WHERE current_stage = 'member' AND LOWER(COALESCE(gender, '')) = 'male'")->fetchColumn();
    $female_count   = $pdo->query("SELECT COUNT(*) FROM people WHERE current_stage = 'member' AND LOWER(COALESCE(gender, '')) = 'female'")->fetchColumn();

    $today_attendance_data = getTotalDailyAttendance($pdo);
    $today_attendance      = $today_attendance_data['total_attendance'] ?? 0;

    $new_converts_stats  = $pdo->query("
        SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN date_converted >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent
        FROM new_convert_roles
    ")->fetch();
    $new_converts_count    = $new_converts_stats['total']  ?? 0;
    $active_converts_count = $new_converts_stats['active'] ?? 0;
    $recent_converts_count = $new_converts_stats['recent'] ?? 0;
} catch (Exception $e) {
    $members_count = $visitors_count = $male_count = $female_count = 0;
    $new_converts_count = $active_converts_count = $recent_converts_count = 0;
    $today_attendance = 0;
}

include '../../../includes/header.php';
?>
<link href="../../../assets/css/dashboard_enhanced.css?v=<?php echo @filemtime('../../../assets/css/dashboard_enhanced.css'); ?>" rel="stylesheet">

<div class="container-fluid px-0">

<!-- Dashboard Header -->
<div class="px-3 px-md-4 mb-4">
    <div class="card border-0 shadow-lg dashboard-header-card">
        <div class="card-body p-3 p-md-4">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <h1 class="text-primary mb-2 dashboard-title">
                        <i class="bi bi-people-fill"></i> People &amp; Attendance
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                        <span class="text-muted">Welcome back, <strong class="text-primary"><?php echo htmlspecialchars($user_name); ?></strong></span>
                        <span class="badge bg-primary text-white px-2 px-md-3 py-2"><?php echo date('l, F j, Y'); ?></span>
                        <span class="badge bg-success text-white px-2 px-md-3 py-2">
                            <i class="bi bi-circle-fill me-1 pulse"></i>
                            Today: <?php echo number_format($today_attendance); ?> Attendance
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="px-3 px-md-4 mb-4">
    <div class="row g-2 g-md-3 g-lg-4">

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-lg h-100 enhanced-members-card">
                <div class="card-body text-white position-relative">
                    <div class="card-pattern"></div>
                    <div class="d-flex align-items-center justify-content-between position-relative">
                        <div>
                            <h6 class="text-white-75 mb-2 fw-semibold">Total Members</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo number_format($members_count); ?></h2>
                            <small class="text-white-75"><i class="bi bi-people me-1"></i> Active church members</small>
                        </div>
                        <div class="card-icon"><i class="bi bi-people-fill text-white fs-1"></i></div>
                    </div>
                    <div class="mt-3">
                        <a href="../members/list" class="btn btn-light btn-sm w-100 fw-semibold">
                            <i class="bi bi-arrow-right me-1"></i> Manage Members
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-lg h-100 enhanced-visitors-card">
                <div class="card-body text-white position-relative">
                    <div class="card-pattern"></div>
                    <div class="d-flex align-items-center justify-content-between position-relative">
                        <div>
                            <h6 class="text-white-75 mb-2 fw-semibold">Total Visitors</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo number_format($visitors_count); ?></h2>
                            <small class="text-white-75"><i class="bi bi-person-check me-1"></i> Registered visitors</small>
                        </div>
                        <div class="card-icon"><i class="bi bi-person-check-fill text-white fs-1"></i></div>
                    </div>
                    <div class="mt-3">
                        <a href="../visitors/list" class="btn btn-light btn-sm w-100 fw-semibold">
                            <i class="bi bi-arrow-right me-1"></i> View Visitors
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-lg h-100 enhanced-converts-card">
                <div class="card-body text-white position-relative">
                    <div class="card-pattern"></div>
                    <div class="d-flex align-items-center justify-content-between position-relative">
                        <div>
                            <h6 class="text-white-75 mb-2 fw-semibold">Total Male</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo number_format($male_count); ?></h2>
                            <small class="text-white-75"><i class="bi bi-person me-1"></i> Male members</small>
                        </div>
                        <div class="card-icon"><i class="bi bi-person-fill text-white fs-1"></i></div>
                    </div>
                    <div class="mt-3">
                        <a href="../members/list?gender=male" class="btn btn-light btn-sm w-100 fw-semibold">
                            <i class="bi bi-arrow-right me-1"></i> View Male Members
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-lg h-100 enhanced-services-card">
                <div class="card-body text-white position-relative">
                    <div class="card-pattern"></div>
                    <div class="d-flex align-items-center justify-content-between position-relative">
                        <div>
                            <h6 class="text-white-75 mb-2 fw-semibold">Total Female</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo number_format($female_count); ?></h2>
                            <small class="text-white-75"><i class="bi bi-person me-1"></i> Female members</small>
                        </div>
                        <div class="card-icon"><i class="bi bi-person-fill text-white fs-1"></i></div>
                    </div>
                    <div class="mt-3">
                        <a href="../members/list?gender=female" class="btn btn-light btn-sm w-100 fw-semibold">
                            <i class="bi bi-arrow-right me-1"></i> View Female Members
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Quick Actions -->
<div class="px-3 px-md-4 mb-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="mb-3 mb-md-4">
                <h3 class="text-primary mb-1 fw-bold">
                    <i class="bi bi-lightning-fill me-2"></i> Quick Actions
                </h3>
                <p class="text-muted mb-0">Streamline your administrative tasks</p>
            </div>
            <?php if (in_array($user_role, ['admin', 'staff'])): ?>
            <div class="row mb-3 mb-md-4">
                <div class="col-12 col-lg-6 mb-3">
                    <a href="../members/add" class="btn btn-primary btn-lg w-100 py-3 py-md-4 text-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="text-start">
                                <i class="bi bi-person-plus-fill fs-3 d-block mb-2 text-white"></i>
                                <h5 class="mb-1 fw-bold text-white">Add New Member</h5>
                                <small class="opacity-75 text-white">Register church members quickly</small>
                            </div>
                            <i class="bi bi-arrow-right fs-2 opacity-50 text-white"></i>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-lg-6 mb-3">
                    <a href="../visitors/add" class="btn btn-success btn-lg w-100 py-3 py-md-4 text-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="text-start">
                                <i class="bi bi-person-badge-fill fs-3 d-block mb-2 text-white"></i>
                                <h5 class="mb-1 fw-bold text-white">Register Visitor</h5>
                                <small class="opacity-75 text-white">Track first-time attendees</small>
                            </div>
                            <i class="bi bi-arrow-right fs-2 opacity-50 text-white"></i>
                        </div>
                    </a>
                </div>
            </div>
            <div class="row g-2 g-md-3">
                <div class="col-6 col-md-6 col-lg-3">
                    <a href="../checkin/index" class="action-tile text-decoration-none">
                        <div class="action-tile-card action-tile-checkin p-4 rounded-3 h-100 d-flex align-items-center hover-lift">
                            <div class="action-icon me-3"><i class="bi bi-qr-code-scan text-white fs-4"></i></div>
                            <div>
                                <h6 class="mb-1 text-white fw-semibold">Check-in</h6>
                                <small class="text-white opacity-75">Quick member check-in</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-6 col-lg-3">
                    <a href="../services/list" class="action-tile text-decoration-none">
                        <div class="action-tile-card action-tile-services p-4 rounded-3 h-100 d-flex align-items-center hover-lift">
                            <div class="action-icon me-3"><i class="bi bi-gear-fill text-white fs-4"></i></div>
                            <div>
                                <h6 class="mb-1 text-white fw-semibold">Services</h6>
                                <small class="text-white opacity-75">Manage church services</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-6 col-lg-3">
                    <a href="attendance" class="action-tile text-decoration-none">
                        <div class="action-tile-card action-tile-attendance p-4 rounded-3 h-100 d-flex align-items-center hover-lift">
                            <div class="action-icon me-3"><i class="bi bi-clipboard-check-fill text-white fs-4"></i></div>
                            <div>
                                <h6 class="mb-1 text-white fw-semibold">Mark Attendance</h6>
                                <small class="text-white opacity-75">Record member attendance</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-6 col-lg-3">
                    <a href="../reports/report" class="action-tile text-decoration-none">
                        <div class="action-tile-card action-tile-reports p-4 rounded-3 h-100 d-flex align-items-center hover-lift">
                            <div class="action-icon me-3"><i class="bi bi-bar-chart-line text-white fs-4"></i></div>
                            <div>
                                <h6 class="mb-1 text-white fw-semibold">Reports</h6>
                                <small class="text-white opacity-75">Analytics &amp; insights</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-6 col-lg-3">
                    <a href="../logs" class="action-tile text-decoration-none">
                        <div class="action-tile-card action-tile-settings p-4 rounded-3 h-100 d-flex align-items-center hover-lift">
                            <div class="action-icon me-3"><i class="bi bi-journal-text text-white fs-4"></i></div>
                            <div>
                                <h6 class="mb-1 text-white fw-semibold">Logs</h6>
                                <small class="text-white opacity-75">Trace errors and activity</small>
                            </div>
                        </div>
                    </a>
                </div>
                <?php if ($user_role === 'admin'): ?>
                <div class="col-6 col-md-6 col-lg-3">
                    <a href="../admin/settings" class="action-tile text-decoration-none">
                        <div class="action-tile-card action-tile-settings p-4 rounded-3 h-100 d-flex align-items-center hover-lift">
                            <div class="action-icon me-3"><i class="bi bi-gear-fill text-white fs-4"></i></div>
                            <div>
                                <h6 class="mb-1 text-white fw-semibold">Settings</h6>
                                <small class="text-white opacity-75">Departments &amp; cell centers</small>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upcoming Birthdays -->
<div class="px-3 px-md-4 mb-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h5 class="text-primary mb-3">
                <i class="bi bi-gift-fill"></i> Upcoming Birthdays
            </h5>
            <div class="birthdays-list dashboard-birthdays-list">
                <?php
                try {
                    $birthdays = $pdo->query("
                        SELECT name, phone, dob
                        FROM member_roles
                        WHERE MONTH(dob) = MONTH(CURDATE())
                        AND DAY(dob) >= DAY(CURDATE())
                        AND status = 'active'
                        AND dob IS NOT NULL
                        ORDER BY DAY(dob) ASC
                        LIMIT 10
                    ")->fetchAll();

                    if ($birthdays):
                        foreach ($birthdays as $birthday): ?>
                        <div class="d-flex align-items-center mb-3 p-2 hover-bg-light rounded">
                            <div class="birthday-icon me-3">
                                <i class="bi bi-person-circle text-primary fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($birthday['name']); ?></h6>
                                <small class="text-muted">
                                    <?php echo date('F j', strtotime($birthday['dob'])); ?>
                                    <?php if ($birthday['phone']): ?>
                                        &bull; <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($birthday['phone']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php
                                $days_until = (strtotime(date('Y') . '-' . date('m-d', strtotime($birthday['dob']))) - strtotime(date('Y-m-d'))) / 86400;
                                if ($days_until == 0): ?>
                                    <span class="badge bg-success">Today!</span>
                                <?php elseif ($days_until <= 7): ?>
                                    <span class="badge bg-warning"><?php echo (int)$days_until; ?> days</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach;
                    else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x text-muted fs-1 mb-2 d-block"></i>
                        <p class="text-muted mb-0">No upcoming birthdays this month</p>
                    </div>
                    <?php endif;
                } catch (Exception $e) {
                    echo '<p class="text-muted mb-0">No birthday information available.</p>';
                } ?>
            </div>
        </div>
    </div>
</div>

</div><!-- /.container-fluid -->

<?php include '../../../includes/footer.php'; ?>

