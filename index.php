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

<link rel="stylesheet" href="assets/css/dashboard.css">

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
<?php 
// Determine grid layout based on user role and screen size
$card_col_class = ($user_role == 'admin') ? 'col-lg-3 col-md-6 col-12 mb-3' : 'col-lg-4 col-md-6 col-12 mb-3';
?>
<div class="row g-3">
    <!-- Members Card - All users can see this -->
    <div class="<?php echo $card_col_class; ?>">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-2">Total Members</h6>
                        <h2 class="text-primary mb-2"><?php echo number_format($members_count); ?></h2>
                        <small class="text-success">
                            <i class="bi bi-arrow-up"></i> Active members
                        </small>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded p-3">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                </div>
                <?php if (in_array($user_role, ['admin', 'staff'])): ?>
                <div class="mt-3">
                    <a href="pages/members/" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> Manage Members
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Visitors Card - Staff and Admin only -->
    <?php if (in_array($user_role, ['admin', 'staff'])): ?>
    <div class="<?php echo $card_col_class; ?>">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-2">Active Visitors</h6>
                        <h2 class="text-success mb-2"><?php echo number_format($visitors_count); ?></h2>
                        <small class="text-info">
                            <i class="bi bi-calendar"></i> Not converted
                        </small>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded p-3">
                        <i class="bi bi-person-badge text-success fs-4"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/visitors/" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> View Visitors
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Converts Card - Staff and Admin only -->
    <div class="<?php echo $card_col_class; ?>">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-2">New Converts</h6>
                        <h2 class="text-warning mb-2"><?php echo number_format($active_converts_count); ?></h2>
                        <small class="text-warning">
                            <i class="bi bi-person-plus"></i> Active converts
                        </small>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded p-3">
                        <i class="bi bi-person-plus-fill text-warning fs-4"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/visitors/new_converts.php" class="btn btn-warning btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> View Converts
                    </a>
                </div>
            </div>
        </div>
    </div>
    </div>
    <?php endif; ?>

    <!-- Departments Card - Admin only -->
    <?php if ($user_role == 'admin'): ?>
    <div class="<?php echo $card_col_class; ?>">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-2">Departments</h6>
                        <h2 class="text-info mb-2"><?php echo number_format($departments_count); ?></h2>
                        <small class="text-info">
                            <i class="bi bi-diagram-3"></i> Active
                        </small>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded p-3">
                        <i class="bi bi-diagram-3 text-info fs-4"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="pages/members/?view=departments" class="btn btn-info btn-sm w-100">
                        <i class="bi bi-arrow-right"></i> Manage Departments
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Role-Based Quick Actions -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h3 class="text-primary mb-3">
            <i class="bi bi-lightning-fill"></i> Quick Actions
            <small class="text-muted fs-6">(Available for <?php echo ucfirst($user_role); ?>)</small>
        </h3>
        <div class="row g-3">
            <?php if (in_array($user_role, ['admin', 'staff'])): ?>
            <div class="col-md-4">
                <a href="pages/members/add.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-person-plus-fill d-block fs-3 mb-2"></i>
                    Add New Member
                </a>
            </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="pages/visitors/add.php" class="btn btn-outline-success w-100 py-2">
            <div class="col-md-4">
                <a href="pages/visitors/add.php" class="btn btn-outline-success w-100 py-3">
                    <i class="bi bi-person-badge-fill d-block fs-3 mb-2"></i>
                    Register Visitor
                </a>
            </div>
            <div class="col-md-4">
                <a href="pages/services/list.php" class="btn btn-outline-secondary w-100 py-3">
                    <i class="bi bi-gear-fill d-block fs-3 mb-2"></i>
                    Services
                </a>
            </div>
            <div class="col-md-4">
                <a href="pages/services/sessions.php" class="btn btn-outline-dark w-100 py-3">
                    <i class="bi bi-calendar-day-fill d-block fs-3 mb-2"></i>
                    Sessions
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Attendance management - Staff and Admin only -->
            <div class="col-md-4">
                <a href="pages/attendance/mark.php" class="btn btn-outline-warning w-100 py-3">
                    <i class="bi bi-clipboard-check-fill d-block fs-3 mb-2"></i>
                    Manage Attendance
                </a>
            </div>
            
            <?php if ($user_role == 'admin'): ?>
            <div class="col-md-4">
                <a href="pages/reports/" class="btn btn-outline-info w-100 py-3">
                    <i class="bi bi-bar-chart-fill d-block fs-3 mb-2"></i>
                    View Reports
                </a>
            </div>
            <?php elseif ($user_role == 'staff'): ?>
            <div class="col-md-4">
                <a href="pages/reports/" class="btn btn-outline-info w-100 py-3">
                    <i class="bi bi-bar-chart-line d-block fs-3 mb-2"></i>
                    Basic Reports
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activity Section -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="text-primary mb-3">
                    <i class="bi bi-clock-history"></i> Recent Members
                </h4>
            <?php
            try {
                $recent_members = $pdo->query("
                    SELECT m.name, m.date_joined, d.name as department 
                    FROM members m 
                    LEFT JOIN departments d ON m.department_id = d.id
                    WHERE m.status = 'active'
                    ORDER BY m.date_joined DESC 
                    LIMIT 5
                ")->fetchAll();
                
                if ($recent_members): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_members as $member): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($member['name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($member['department'] ?: 'No department'); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M j', strtotime($member['date_joined'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="text-primary mb-3">
                    <i class="bi bi-person-badge"></i> Recent Visitors
                </h4>
            <?php
            try {
                $recent_visitors = $pdo->query("
                    SELECT name, created_at, phone 
                    FROM visitors 
                    WHERE (status IS NULL OR status != 'converted_to_convert')
                    ORDER BY created_at DESC 
                    LIMIT 5
                ")->fetchAll();
                
                if ($recent_visitors): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_visitors as $visitor): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($visitor['name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($visitor['phone'] ?: 'No phone'); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M j', strtotime($visitor['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
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