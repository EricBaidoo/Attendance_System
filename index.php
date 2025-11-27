<?php
// Start session
session_start();

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

// Get statistics for the page
try {
    $members_count = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $visitors_count = $pdo->query("SELECT COUNT(*) FROM visitors WHERE (status IS NULL OR status != 'converted_to_convert')")->fetchColumn();
    $departments_count = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $services_count = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    
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
    $departments_count = 0;
    $services_count = 0;
    $new_converts_count = 0;
    $active_converts_count = 0;
    $recent_converts_count = 0;
}

// Set footer stats
$show_footer_stats = true;
$footer_stats = [
    'members' => $members_count,
    'visitors_month' => $visitors_count,
    'services' => $services_count,
    'departments' => $departments_count
];

// Include header
include 'includes/header.php';
?>
<link href="assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">

<!-- Dashboard content starts here -->

<!-- Dashboard Header -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="text-primary mb-2">
                    <i class="bi bi-house-door-fill"></i> Administrative Dashboard
                </h1>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="text-muted">Welcome back, <strong class="text-dark"><?php echo htmlspecialchars($user_name); ?></strong></span>
                    <span class="badge bg-light text-dark"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards - Visible to all logged-in users -->
<div class="row g-3 g-lg-4 mb-4">
    <!-- Members Card -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
        <div class="card border-0 shadow-sm h-100 members-card">
            <div class="card-body text-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-white-50 mb-2">Total Members</h6>
                        <h2 class="text-white mb-2"><?php echo number_format($members_count); ?></h2>
                        <small class="text-white-50">
                            <i class="bi bi-arrow-up"></i> Active members
                        </small>
                    </div>
                    <div class="rounded p-3">
                        <i class="bi bi-people-fill text-white fs-2"></i>
                    </div>
                </div>
                <?php if (in_array($user_role, ['admin', 'staff'])): ?>
                <div class="mt-3">
                    <a href="pages/members/list.php" class="btn btn-light btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> Manage Members
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Visitors Card -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
        <div class="card border-0 shadow-sm h-100 visitors-card">
            <div class="card-body text-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-white-50 mb-2">Active Visitors</h6>
                        <h2 class="text-white mb-2"><?php echo number_format($visitors_count); ?></h2>
                        <small class="text-white-50">
                            <i class="bi bi-calendar"></i> Not converted
                        </small>
                    </div>
                    <div class="rounded p-3">
                        <i class="bi bi-person-check-fill text-white fs-2"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/visitors/list.php" class="btn btn-light btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> View Visitors
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Converts Card -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
        <div class="card border-0 shadow-sm h-100 converts-card">
            <div class="card-body text-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-white-50 mb-2">New Converts</h6>
                        <h2 class="text-white mb-2"><?php echo number_format($active_converts_count); ?></h2>
                        <small class="text-white-50">
                            <i class="bi bi-person-plus"></i> Active converts
                        </small>
                    </div>
                    <div class="rounded p-3">
                        <i class="bi bi-heart-fill text-white fs-2"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/visitors/new_converts.php" class="btn btn-light btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> View Converts
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Departments Card -->
    <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
        <div class="card border-0 shadow-sm h-100 departments-card">
            <div class="card-body text-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-white-50 mb-2">Departments</h6>
                        <h2 class="text-white mb-2"><?php echo number_format($departments_count); ?></h2>
                        <small class="text-white-50">
                            <i class="bi bi-diagram-3"></i> Active
                        </small>
                    </div>
                    <div class="rounded p-3">
                        <i class="bi bi-building-fill text-white fs-2"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/members/list.php?view=departments" class="btn btn-light btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> Manage Departments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Professional Quick Actions -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary mb-1 fw-bold">
                    <i class="bi bi-lightning-fill me-2"></i> Quick Actions
                </h3>
                <p class="text-muted mb-0">Streamline your administrative tasks</p>
            </div>
            <span class="badge bg-primary px-3 py-2"><?php echo ucfirst($user_role); ?> Dashboard</span>
        </div>
        <!-- Primary Actions Row -->
        <div class="row mb-4">
            <?php if (in_array($user_role, ['admin', 'staff'])): ?>
            <div class="col-lg-6 mb-3">
                <div class="primary-action-btn">
                    <a href="pages/members/add.php" class="btn btn-primary btn-lg w-100 py-4 position-relative overflow-hidden">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="text-start">
                                <i class="bi bi-person-plus-fill fs-3 d-block mb-2"></i>
                                <h5 class="mb-1 fw-bold">Add New Member</h5>
                                <small class="opacity-75">Register church members quickly</small>
                            </div>
                            <i class="bi bi-arrow-right fs-2 opacity-50"></i>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="secondary-action-btn">
                    <a href="pages/visitors/add.php" class="btn btn-success btn-lg w-100 py-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="text-start">
                                <i class="bi bi-person-badge-fill fs-3 d-block mb-2"></i>
                                <h5 class="mb-1 fw-bold">Register Visitor</h5>
                                <small class="opacity-75">Track first-time attendees</small>
                            </div>
                            <i class="bi bi-arrow-right fs-2 opacity-50"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        <!-- Secondary Actions Grid -->
        <div class="row g-3">
            <div class="col-lg-4 col-md-6">
                <a href="pages/services/list.php" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 bg-light border h-100 d-flex align-items-center hover-lift">
                        <div class="action-icon me-3">
                            <i class="bi bi-gear-fill text-secondary fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-dark fw-semibold">Services</h6>
                            <small class="text-muted">Manage church services</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="pages/attendance/mark.php" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 bg-warning bg-opacity-15 border border-warning border-opacity-50 h-100 d-flex align-items-center hover-lift">
                        <div class="action-icon me-3 bg-warning bg-opacity-20">
                            <i class="bi bi-clipboard-check-fill text-warning fs-3"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 text-dark fw-bold">Mark Attendance</h5>
                            <small class="text-dark opacity-75 fw-medium">Record member attendance</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($user_role == 'admin'): ?>
            <div class="col-lg-4 col-md-12">
                <a href="pages/reports/" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 bg-info bg-opacity-15 border border-info border-opacity-50 h-100 d-flex align-items-center hover-lift">
                        <div class="action-icon me-3 bg-info bg-opacity-20">
                            <i class="bi bi-bar-chart-fill text-info fs-3"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 text-dark fw-bold">Analytics & Reports</h5>
                            <small class="text-dark opacity-75 fw-medium">View detailed insights</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-4 col-md-12">
                <a href="pages/reports/report.php" class="action-tile text-decoration-none">
                    <div class="p-4 rounded-3 bg-info bg-opacity-15 border border-info border-opacity-50 h-100 d-flex align-items-center hover-lift">
                        <div class="action-icon me-3 bg-info bg-opacity-20">
                            <i class="bi bi-bar-chart-line text-info fs-3"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 text-dark fw-bold">Basic Reports</h5>
                            <small class="text-dark opacity-75 fw-medium">View summary reports</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Dashboard Insights Row -->
<div class="row g-4 mb-4">
    <!-- Attendance Trends -->
    <div class="col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="text-primary mb-0">
                        <i class="bi bi-graph-up"></i> Attendance Trends
                    </h4>
                    <span class="badge bg-light text-dark">Last 7 Weeks</span>
                </div>
                <div class="attendance-chart">
                    <div class="row text-center">
                        <?php
                        try {
                            // Get attendance data for last 7 weeks
                            $attendance_query = $pdo->query("
                                SELECT 
                                    WEEK(created_at) as week_num,
                                    DATE_FORMAT(created_at, '%b %d') as week_label,
                                    COUNT(*) as attendance_count
                                FROM attendance 
                                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 WEEK)
                                GROUP BY WEEK(created_at)
                                ORDER BY created_at DESC
                                LIMIT 7
                            ");
                            
                            $attendance_data = $attendance_query->fetchAll();
                            
                            // Get total members for percentage calculation
                            $total_members = $pdo->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'")->fetch()['count'];
                            
                            if ($attendance_data && $total_members > 0):
                                $attendance_data = array_reverse($attendance_data); // Show oldest to newest
                                foreach ($attendance_data as $week): 
                                    $percentage = round(($week['attendance_count'] / $total_members) * 100);
                                    $percentage = min($percentage, 100); // Cap at 100%
                                ?>
                                <div class="col">
                                    <div class="attendance-bar mb-2">
                                        <div class="bar" style="height: <?php echo $percentage; ?>%; background: linear-gradient(to top, #000032, #1a1a5e);"></div>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($week['week_label']); ?></small>
                                    <div class="fw-semibold text-primary"><?php echo $percentage; ?>%</div>
                                </div>
                                <?php endforeach;
                            else: ?>
                                <div class="col-12">
                                    <p class="text-muted mb-0">No attendance data available for the last 7 weeks.</p>
                                </div>
                            <?php endif;
                        } catch (Exception $e) {
                            echo '<div class="col-12"><p class="text-muted mb-0">Attendance data unavailable.</p></div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Events & Birthdays Row -->
<div class="row g-4 mb-4">
    <!-- Upcoming Events -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="text-primary mb-4">
                    <i class="bi bi-calendar-event"></i> This Week
                </h4>
                <div class="upcoming-events">
                    <?php
                    try {
                        // Get upcoming services from database
                        $upcoming_services = $pdo->query("
                            SELECT 
                                name,
                                date_time,
                                location,
                                description
                            FROM services 
                            WHERE date_time >= NOW() 
                            ORDER BY date_time ASC 
                            LIMIT 3
                        ")->fetchAll();
                        
                        if ($upcoming_services):
                            foreach ($upcoming_services as $service): ?>
                            <div class="event-item d-flex align-items-center mb-3 p-3 bg-light rounded-3">
                                <div class="event-date text-center me-3">
                                    <div class="fw-bold text-primary"><?php echo date('d', strtotime($service['date_time'])); ?></div>
                                    <small class="text-muted"><?php echo date('M', strtotime($service['date_time'])); ?></small>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($service['name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('g:i A', strtotime($service['date_time'])); ?>
                                        <?php if ($service['location']): ?>
                                            - <?php echo htmlspecialchars($service['location']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach;
                        else: ?>
                            <div class="event-item d-flex align-items-center mb-3 p-3 bg-light rounded-3">
                                <div class="event-date text-center me-3">
                                    <div class="fw-bold text-muted"><i class="bi bi-calendar-x"></i></div>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-semibold text-muted">No upcoming services</h6>
                                    <small class="text-muted">Schedule services to see them here</small>
                                </div>
                            </div>
                        <?php endif;
                        
                        // Check for pending tasks or notifications
                        $pending_converts = $pdo->query("
                            SELECT COUNT(*) as count 
                            FROM visitors 
                            WHERE status IS NULL OR status != 'converted_to_convert'
                        ")->fetch()['count'];
                        
                        if ($pending_converts > 0): ?>
                        <div class="alert alert-warning border-0 mb-0">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <small><strong>Action Needed:</strong> <?php echo $pending_converts; ?> visitor(s) need follow-up</small>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info border-0 mb-0">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle me-2"></i>
                                <small><strong>All caught up!</strong> No pending visitor follow-ups</small>
                            </div>
                        </div>
                        <?php endif;
                    } catch (Exception $e) {
                        echo '<p class="text-muted mb-0">Unable to load upcoming events.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Birthdays -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="text-primary mb-3">
                    <i class="bi bi-gift"></i> Upcoming Birthdays
                </h4>
                <div class="birthday-list">
                    <?php
                    try {
                        $birthdays = $pdo->query("
                            SELECT name, phone, date_of_birth 
                            FROM members 
                            WHERE MONTH(date_of_birth) = MONTH(CURDATE()) 
                            AND DAY(date_of_birth) >= DAY(CURDATE())
                            AND status = 'active'
                            ORDER BY DAY(date_of_birth) ASC 
                            LIMIT 5
                        ")->fetchAll();
                        
                        if ($birthdays): 
                            foreach ($birthdays as $birthday): ?>
                            <div class="d-flex align-items-center mb-3 p-2 hover-bg-light rounded">
                                <div class="birthday-icon me-3">
                                    <i class="bi bi-person-circle text-primary fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-semibold"><?php echo htmlspecialchars($birthday['name']); ?></h6>
                                    <small class="text-muted"><?php echo date('F j', strtotime($birthday['date_of_birth'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach;
                        else: ?>
                            <p class="text-muted mb-0">No upcoming birthdays this month.</p>
                        <?php endif;
                    } catch (Exception $e) {
                        echo '<p class="text-muted mb-0">No birthday information available.</p>';
                    } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Section -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="text-primary mb-0">
                        <i class="bi bi-clock-history"></i> Recent Members
                    </h4>
                    <a href="pages/members/list.php" class="btn btn-outline-primary btn-sm">View All</a>
                </div>
            <?php
            try {
                $recent_members = $pdo->query("
                    SELECT m.name, m.location, m.date_joined, d.name as department 
                    FROM members m 
                    LEFT JOIN departments d ON m.department_id = d.id
                    WHERE m.status = 'active'
                    ORDER BY m.date_joined DESC 
                    LIMIT 5
                ")->fetchAll();
                
                if ($recent_members): ?>
                    <div class="table-responsive">
                        <table class="table table-sm recent-activity-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Date Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_members as $member): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($member['location'] ?: 'No location'); ?></td>
                                    <td class="text-muted"><?php echo date('M j, Y', strtotime($member['date_joined'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent members found.</p>
                <?php endif;
            } catch (Exception $e) {
                echo '<p class="text-muted">No recent members found.</p>';
                // Uncomment for debugging: echo '<p class="text-danger">Debug: ' . $e->getMessage() . '</p>';
            }
            ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="text-primary mb-0">
                        <i class="bi bi-person-badge"></i> Recent Visitors
                    </h4>
                    <a href="pages/visitors/" class="btn btn-outline-success btn-sm">View All</a>
                </div>
            <?php
            try {
                $recent_visitors = $pdo->query("
                    SELECT v.name, v.address as location, v.created_at as date_joined, s.name as service_name 
                    FROM visitors v 
                    LEFT JOIN services s ON v.service_id = s.id
                    WHERE (v.status IS NULL OR v.status != 'converted_to_convert')
                    ORDER BY v.created_at DESC 
                    LIMIT 5
                ")->fetchAll();
                
                if ($recent_visitors): ?>
                    <div class="table-responsive">
                        <table class="table table-sm recent-activity-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Visit Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_visitors as $visitor): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($visitor['name']); ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($visitor['location'] ?: 'No address'); ?></td>
                                    <td class="text-muted"><?php echo date('M j, Y', strtotime($visitor['date_joined'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent visitors found.</p>
                <?php endif;
            } catch (Exception $e) {
                echo '<p class="text-muted">No recent visitors found.</p>';
                // Uncomment for debugging: echo '<p class="text-danger">Debug: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>