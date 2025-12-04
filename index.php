<?php
// Include security and session management
require_once 'includes/security.php';

// Require login and update session security
requireLogin();

// Set page variables for header
$page_title = "Bridge Ministries International - Dashboard";
$page_header = true;
$page_icon = "bi bi-house-heart";
$base_url = "/ATTENDANCE%20SYSTEM/";

// Determine user role and access level
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['username'] ?? 'Guest';
$is_logged_in = true;

// Set page content based on role
$page_header = false; // Disable page header to prevent duplication with unified card

// Include database connection
require_once 'config/database.php';

// Include attendance utilities
require_once 'includes/attendance_utils.php';

// Get statistics for the page
try {
    $members_count = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
    $visitors_count = $pdo->query("SELECT COUNT(*) FROM visitors WHERE (status IS NULL OR status != 'converted_to_convert')")->fetchColumn();
    $male_count = $pdo->query("SELECT COUNT(*) FROM members WHERE gender = 'Male' AND status = 'active'")->fetchColumn();
    $female_count = $pdo->query("SELECT COUNT(*) FROM members WHERE gender = 'Female' AND status = 'active'")->fetchColumn();
    
    // Get today's total attendance
    $today_attendance_data = getTotalDailyAttendance($pdo);
    $today_attendance = $today_attendance_data['total_attendance'] ?? 0;
    
    // Get new converts statistics
    $new_converts_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN date_converted >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent
        FROM new_converts
    ");
    $new_converts_stats = $new_converts_stmt->fetch();
    $new_converts_count = $new_converts_stats['total'] ?? 0;
    $active_converts_count = $new_converts_stats['active'] ?? 0;
    $recent_converts_count = $new_converts_stats['recent'] ?? 0;
} catch (Exception $e) {
    $members_count = 0;
    $visitors_count = 0;
    $male_count = 0;
    $female_count = 0;
    $new_converts_count = 0;
    $active_converts_count = 0;
    $recent_converts_count = 0;
}

// Include header
include 'includes/header.php';
?>
<link href="assets/css/dashboard_enhanced.css?v=<?php echo filemtime('assets/css/dashboard_enhanced.css'); ?>" rel="stylesheet">
<style>
/* Bright vibrant colors matching member page */
.enhanced-members-card { 
    background: linear-gradient(135deg, #1a365d 0%, #2d3748 100%) !important; 
    color: white !important; 
    border-radius: 16px; 
    box-shadow: 0 12px 35px rgba(26, 54, 93, 0.2); 
}
.enhanced-visitors-card { 
    background: linear-gradient(135deg, #198754 0%, #20c997 100%) !important; 
    color: white !important; 
    border-radius: 16px; 
    box-shadow: 0 12px 35px rgba(25, 135, 84, 0.2); 
}
.enhanced-converts-card { 
    background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%) !important; 
    color: white !important; 
    border-radius: 16px; 
    box-shadow: 0 12px 35px rgba(253, 126, 20, 0.2); 
}
.enhanced-services-card { 
    background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%) !important; 
    color: white !important; 
    border-radius: 16px; 
    box-shadow: 0 12px 35px rgba(23, 162, 184, 0.2); 
}
.enhanced-members-card .card-body, .enhanced-visitors-card .card-body, 
.enhanced-converts-card .card-body, .enhanced-services-card .card-body { 
    color: white !important; 
}
/* Mobile responsive improvements */
@media (max-width: 768px) {
    .dashboard-title {
        font-size: 1.5rem !important;
    }
    .fs-3 {
        font-size: 1.2rem !important;
    }
    .fs-1 {
        font-size: 1.5rem !important;
    }
    .card-body {
        padding: 1rem !important;
    }
    .btn-lg {
        padding: 0.75rem 1rem !important;
    }
}
@media (max-width: 576px) {
    .px-3 {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
    h5 {
        font-size: 1rem !important;
    }
    small {
        font-size: 0.75rem !important;
    }
}
</style>

<!-- Enhanced Dashboard Content -->
<div class="container-fluid px-0">

<!-- Dashboard Header -->
<div class="px-3 px-md-4 mb-4">
    <div class="card border-0 shadow-lg dashboard-header-card">
        <div class="card-body p-3 p-md-4">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <h1 class="text-primary mb-2 dashboard-title">
                        <i class="bi bi-house-door-fill"></i> Administrative Dashboard
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

<!-- Enhanced Statistics Cards -->
<div class="px-3 px-md-4 mb-4">
    <div class="row g-2 g-md-3 g-lg-4">
        <!-- Members Card -->
        <div class="col-6 col-md-3 mb-3 mb-md-0">
        <div class="card border-0 shadow-lg h-100 enhanced-members-card">
            <div class="card-body text-white position-relative">
                <div class="card-pattern"></div>
                <div class="d-flex align-items-center justify-content-between position-relative">
                    <div>
                        <h6 class="text-white-75 mb-2 fw-semibold">Total Members</h6>
                        <h2 class="text-white mb-2 fw-bold"><?php echo number_format($members_count); ?></h2>
                        <small class="text-white-75">
                            <i class="bi bi-people me-1"></i> Active church members
                        </small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-people-fill text-white fs-1"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/members/list.php" class="btn btn-light btn-sm w-100 fw-semibold">
                        <i class="bi bi-arrow-right me-1"></i> Manage Members
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Visitors Card -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
        <div class="card border-0 shadow-lg h-100 enhanced-visitors-card">
            <div class="card-body text-white position-relative">
                <div class="card-pattern"></div>
                <div class="d-flex align-items-center justify-content-between position-relative">
                    <div>
                        <h6 class="text-white-75 mb-2 fw-semibold">Total Visitors</h6>
                        <h2 class="text-white mb-2 fw-bold"><?php echo number_format($visitors_count); ?></h2>
                        <small class="text-white-75">
                            <i class="bi bi-person-check me-1"></i> Registered visitors
                        </small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-person-check-fill text-white fs-1"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/visitors/list.php" class="btn btn-light btn-sm w-100 fw-semibold">
                        <i class="bi bi-arrow-right me-1"></i> View Visitors
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Male Members Card -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
        <div class="card border-0 shadow-lg h-100 enhanced-converts-card">
            <div class="card-body text-white position-relative">
                <div class="card-pattern"></div>
                <div class="d-flex align-items-center justify-content-between position-relative">
                    <div>
                        <h6 class="text-white-75 mb-2 fw-semibold">Total Male</h6>
                        <h2 class="text-white mb-2 fw-bold"><?php echo number_format($male_count); ?></h2>
                        <small class="text-white-75">
                            <i class="bi bi-person me-1"></i> Male members
                        </small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-person-fill text-white fs-1"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/members/list.php?gender=male" class="btn btn-light btn-sm w-100 fw-semibold">
                        <i class="bi bi-arrow-right me-1"></i> View Male Members
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Female Members Card -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
        <div class="card border-0 shadow-lg h-100 enhanced-services-card">
            <div class="card-body text-white position-relative">
                <div class="card-pattern"></div>
                <div class="d-flex align-items-center justify-content-between position-relative">
                    <div>
                        <h6 class="text-white-75 mb-2 fw-semibold">Total Female</h6>
                        <h2 class="text-white mb-2 fw-bold"><?php echo number_format($female_count); ?></h2>
                        <small class="text-white-75">
                            <i class="bi bi-person me-1"></i> Female members
                        </small>
                    </div>
                    <div class="card-icon">
                        <i class="bi bi-person-fill text-white fs-1"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/members/list.php?gender=female" class="btn btn-light btn-sm w-100 fw-semibold">
                        <i class="bi bi-arrow-right me-1"></i> View Female Members
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Quick Actions -->
<div class="px-3 px-md-4 mb-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="mb-3 mb-md-4">
                <h3 class="text-primary mb-1 fw-bold">
                    <i class="bi bi-lightning-fill me-2"></i> Quick Actions
                </h3>
                <p class="text-muted mb-0">Streamline your administrative tasks</p>
            </div>
            <!-- Primary Actions Row -->
            <div class="row mb-3 mb-md-4">
                <?php if (in_array($user_role, ['admin', 'staff'])): ?>
                <div class="col-12 col-lg-6 mb-3">
                    <div class="primary-action-btn">
                        <a href="pages/members/add.php" class="btn btn-primary btn-lg w-100 py-3 py-md-4 position-relative overflow-hidden text-white">
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
                </div>
                <div class="col-12 col-lg-6 mb-3">
                    <div class="secondary-action-btn">
                        <a href="pages/visitors/add.php" class="btn btn-success btn-lg w-100 py-3 py-md-4 text-white">
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
            </div>
            <!-- Secondary Actions Grid -->
            <div class="row g-2 g-md-3">
                <div class="col-6 col-lg-3">
                <a href="pages/checkin/index.php" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 h-100 d-flex align-items-center hover-lift" style="background: linear-gradient(135deg, #9333ea 0%, #a855f7 100%); color: white; box-shadow: 0 8px 25px rgba(147, 51, 234, 0.25);">
                        <div class="action-icon me-3">
                            <i class="bi bi-qr-code-scan text-white fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-white fw-semibold">Check-in</h6>
                            <small class="text-white opacity-75">Quick member check-in</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="pages/services/list.php" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 h-100 d-flex align-items-center hover-lift" style="background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); color: white; box-shadow: 0 8px 25px rgba(8, 145, 178, 0.25);">
                        <div class="action-icon me-3">
                            <i class="bi bi-gear-fill text-white fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-white fw-semibold">Services</h6>
                            <small class="text-white opacity-75">Manage church services</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="pages/attendance/attendance.php" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 h-100 d-flex align-items-center hover-lift" style="background: linear-gradient(135deg, #e11d48 0%, #f43f5e 100%); color: white; box-shadow: 0 8px 25px rgba(225, 29, 72, 0.25);">
                        <div class="action-icon me-3">
                            <i class="bi bi-clipboard-check-fill text-white fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-white fw-semibold">Mark Attendance</h6>
                            <small class="text-white opacity-75">Record member attendance</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="pages/reports/report.php" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 h-100 d-flex align-items-center hover-lift" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; box-shadow: 0 8px 25px rgba(5, 150, 105, 0.25);">
                        <div class="action-icon me-3">
                            <i class="bi bi-bar-chart-line text-white fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-white fw-semibold">Reports</h6>
                            <small class="text-white opacity-75">Analytics & insights</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upcoming Birthdays Section -->
<div class="row g-4" id="birthdays">
    <div class="col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h5 class="text-primary mb-3">
                    <i class="bi bi-gift-fill"></i> Upcoming Birthdays
                </h5>
                <div class="birthdays-list" style="max-height: 300px; overflow-y: auto;">
                    <?php
                    try {
                        // Use correct column name 'dob' and proper date handling
                        $birthdays = $pdo->query("
                            SELECT name, phone, dob 
                            FROM members 
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
                                            • <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($birthday['phone']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <?php 
                                    $days_until = (strtotime(date('Y') . '-' . date('m-d', strtotime($birthday['dob']))) - strtotime(date('Y-m-d'))) / (60*60*24);
                                    if ($days_until == 0): ?>
                                        <span class="badge bg-success">Today!</span>
                                    <?php elseif ($days_until <= 7): ?>
                                        <span class="badge bg-warning"><?php echo $days_until; ?> days</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach;
                        else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-1 mb-2"></i>
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
</div>

</div> <!-- Close container-fluid -->

<?php include 'includes/footer.php'; ?>