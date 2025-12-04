<?php
// pages/attendance/view.php - Comprehensive Service Report (Redesigned)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'member';

// Database connection
try {
    require '../../config/database.php';
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$session_id = $_GET['session_id'] ?? '';

// Initialize default values
$service_data = null;
$statistics = [
    'total_members' => 0,
    'present_members' => 0,
    'absent_members' => 0,
    'visitors' => 0,
    'new_converts' => 0,
    'total_attendance' => 0,
    'attendance_percentage' => 0
];
$attendee_list = [];
$present_members = [];
$absent_members = [];
$visitor_list = [];

if (!empty($session_id)) {
    try {
        // Get service session details
        $session_sql = "SELECT ss.*, s.name as service_name, s.id as service_id 
                       FROM service_sessions ss 
                       JOIN services s ON ss.service_id = s.id 
                       WHERE ss.id = ?";
        $session_stmt = $pdo->prepare($session_sql);
        $session_stmt->execute([$session_id]);
        $service_data = $session_stmt->fetch(PDO::FETCH_ASSOC);

        if ($service_data) {
            // Reset all arrays and counters
            $attendee_list = [];
            $present_members = [];
            $absent_members = [];
            $visitor_list = [];
            $statistics['present_members'] = 0;
            $statistics['absent_members'] = 0;
            $statistics['visitors'] = 0;

            // Get total active members
            $total_members_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active'";
            $total_members_stmt = $pdo->prepare($total_members_sql);
            $total_members_stmt->execute();
            $total_members_result = $total_members_stmt->fetch(PDO::FETCH_ASSOC);
            $statistics['total_members'] = $total_members_result['total'] ?? 0;

            // Get member attendance data with better debugging
            $member_attendance_sql = "SELECT m.id as member_id, m.name, m.phone, m.email, m.congregation_group, m.status as member_status,
                                            a.status as attendance_status, a.method, a.date as attendance_date
                                     FROM members m
                                     LEFT JOIN attendance a ON m.id = a.member_id AND a.session_id = ?
                                     WHERE m.status = 'active'
                                     ORDER BY m.name";
            $member_stmt = $pdo->prepare($member_attendance_sql);
            $member_stmt->execute([$session_id]);
            $member_data = $member_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($member_data as $member) {
                $member['attendee_type'] = 'MEMBER';
                $attendee_list[] = $member;
                
                // Check attendance status - use attendance_status from the attendance table
                $attendance_status = $member['attendance_status'];
                
                if ($attendance_status === 'present') {
                    $member['status'] = 'present';
                    $present_members[] = $member;
                    $statistics['present_members']++;
                } else {
                    // If attendance_status is NULL, 'absent', or any other value, count as absent
                    $member['status'] = 'absent';
                    $absent_members[] = $member;
                    $statistics['absent_members']++;
                }
            }

            // Get visitor data with better debugging
            $visitor_sql = "SELECT v.*, 'present' as status, 'visitor_checkin' as method
                           FROM visitors v 
                           WHERE v.service_id = ? AND v.date = ?
                           ORDER BY v.name";
            $visitor_stmt = $pdo->prepare($visitor_sql);
            $visitor_stmt->execute([$service_data['service_id'], $service_data['session_date']]);
            $visitor_data = $visitor_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Debug: Let's also check if there are ANY visitors for this service regardless of date
            $visitor_debug_sql = "SELECT COUNT(*) as total FROM visitors WHERE service_id = ?";
            $visitor_debug_stmt = $pdo->prepare($visitor_debug_sql);
            $visitor_debug_stmt->execute([$service_data['service_id']]);
            $visitor_debug_result = $visitor_debug_stmt->fetch(PDO::FETCH_ASSOC);
            // You can use this for debugging: $visitor_debug_result['total']

            foreach ($visitor_data as $visitor) {
                $visitor['attendee_type'] = 'VISITOR';
                $attendee_list[] = $visitor;
                $visitor_list[] = $visitor;
                $statistics['visitors']++;
            }

            // Get new converts count
            $converts_sql = "SELECT COUNT(*) as total FROM new_converts 
                           WHERE service_id = ? AND date = ?";
            $converts_stmt = $pdo->prepare($converts_sql);
            $converts_stmt->execute([$service_data['service_id'], $service_data['session_date']]);
            $converts_result = $converts_stmt->fetch(PDO::FETCH_ASSOC);
            $statistics['new_converts'] = $converts_result['total'] ?? 0;

            // Calculate totals and percentage
            $statistics['total_attendance'] = $statistics['present_members'] + $statistics['visitors'];
            $statistics['attendance_percentage'] = $statistics['total_members'] > 0 
                ? round(($statistics['present_members'] / $statistics['total_members']) * 100, 1) 
                : 0;

            // Debug the percentage calculation
            error_log("PERCENTAGE DEBUG: Present={$statistics['present_members']}, Total={$statistics['total_members']}, Percentage={$statistics['attendance_percentage']}");
        }
    } catch (Exception $e) {
        error_log("Error fetching session data: " . $e->getMessage());
    }
}

// Ensure percentage is calculated correctly as a fallback
if ($statistics['attendance_percentage'] == 0 && $statistics['total_members'] > 0 && $statistics['present_members'] > 0) {
    $statistics['attendance_percentage'] = round(($statistics['present_members'] / $statistics['total_members']) * 100, 1);
    error_log("FALLBACK PERCENTAGE CALCULATION: {$statistics['attendance_percentage']}%");
}

$page_title = "Comprehensive Service Report - Bridge Ministries International";
?>
<?php include '../../includes/header.php'; ?>

<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">

<style>
/* Comprehensive Report Styling */
.comprehensive-report-container {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #f1f3f4 100%);
    min-height: calc(100vh - 120px);
    padding: 2rem 0;
}

.report-header {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 1rem;
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease;
}

.report-header:hover {
    transform: translateY(-0.125rem);
}

.service-title {
    color: #000032;
    font-weight: 700;
    font-size: 2rem;
}

.service-name {
    color: #1a1a5e;
    font-weight: 600;
    font-size: 1.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 1rem;
    box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    color: white;
}

.stat-card:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
}

.stat-card.present { background: linear-gradient(135deg, #198754 0%, #20c997 100%); }
.stat-card.absent { background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%); }
.stat-card.members { background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%); }
.stat-card.visitors { background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%); }
.stat-card.converts { background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); }
.stat-card.total { background: linear-gradient(135deg, #6f42c1 0%, #8a2be2 100%); }

.stat-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.2);
    font-size: 1.5rem;
}

.modern-table {
    background: white;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.05);
}

.modern-table thead th {
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
    color: white;
    font-weight: 600;
    padding: 1rem;
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.875rem;
}

.modern-table tbody tr:hover {
    background: rgba(0, 0, 50, 0.05);
}

.modern-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.attendee-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    margin-right: 0.75rem;
    font-size: 0.875rem;
}

.modern-btn {
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.1);
    text-decoration: none;
    display: inline-block;
}

.modern-btn:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
    color: white;
}

.modern-btn.success {
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
}

.modern-btn.info {
    background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
}

.filter-buttons .btn {
    border-radius: 1rem;
    padding: 0.5rem 1rem;
    font-weight: 600;
    margin-right: 0.5rem;
    border: 2px solid #000032;
    color: #000032;
    background: transparent;
    transition: all 0.3s ease;
}

.filter-buttons .btn:hover,
.filter-buttons .btn.active {
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
    border-color: #000032;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Overview Tab Specific Styles */
.overview-card {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    transition: all 0.3s ease;
}

.overview-card:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
}

.overview-icon {
    width: 4rem;
    height: 4rem;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.1);
    font-size: 2rem;
}

.breakdown-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.modern-btn.warning {
    background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
}
</style>

<div class="comprehensive-report-container">
<div class="container-fluid">
    <?php if ($service_data): ?>
    
    <!-- Service Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm report-header">
                <div class="card-body p-4 text-center">
                    <h1 class="service-title mb-2">
                        <i class="bi bi-graph-up me-3"></i>Comprehensive Service Report
                    </h1>
                    <h2 class="service-name mb-2">
                        <?php echo htmlspecialchars($service_data['service_name']); ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar me-2"></i>
                        <?php echo date('l, F j, Y', strtotime($service_data['session_date'])); ?> at 
                        <?php echo date('g:i A', strtotime($service_data['opened_at'] ?? $service_data['session_date'])); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 stat-card present">
                <div class="card-body text-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Present</h6>
                            <h2 class="text-white mb-0"><?php echo ($statistics['present_members'] + $statistics['visitors']); ?></h2>
                            <small class="text-white-75"><?php echo $statistics['present_members']; ?> Members + <?php echo $statistics['visitors']; ?> Visitors</small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 stat-card absent">
                <div class="card-body text-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-1">Total Absent</h6>
                            <h2 class="text-white mb-0"><?php echo $statistics['absent_members']; ?></h2>
                            <small class="text-white-75">Members Only</small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 stat-card visitors">
                <div class="card-body text-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-1">Visitors</h6>
                            <h2 class="text-white mb-0"><?php echo $statistics['visitors']; ?></h2>
                            <small class="text-white-75">Guests Present</small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm h-100 stat-card members">
                <div class="card-body text-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-1">Attendance Rate</h6>
                            <h2 class="text-white mb-0"><?php echo $statistics['attendance_percentage']; ?>%</h2>
                            <small class="text-white-75">Member Attendance</small>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Sections -->
    <div class="row g-4">
        <!-- Present Attendees Section -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-people-fill me-2 text-success"></i>Present Attendees (<?php 
                            $present_attendees = array_filter($attendee_list, function($attendee) {
                                return ($attendee['status'] ?? 'present') === 'present';
                            });
                            echo count($present_attendees); 
                            ?>)
                        </h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" onclick="filterPresentAttendees('all')">All</button>
                            <button type="button" class="btn btn-outline-primary" onclick="filterPresentAttendees('MEMBER')">Members</button>
                            <button type="button" class="btn btn-outline-primary" onclick="filterPresentAttendees('VISITOR')">Visitors</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($present_attendees)): ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover mb-0" id="presentAttendeesTable">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($present_attendees as $attendee): 
                                    $colors = ['#667eea', '#764ba2', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7', '#fa709a', '#fee140'];
                                    $avatar_color = $colors[crc32($attendee['name'] ?? '') % count($colors)];
                                    $initials = strtoupper(substr($attendee['name'] ?? '', 0, 1) . (strpos($attendee['name'] ?? '', ' ') ? substr($attendee['name'], strpos($attendee['name'], ' ') + 1, 1) : ''));
                                ?>
                                <tr data-type="<?php echo $attendee['attendee_type']; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="attendee-avatar me-2" style="background-color: <?php echo $avatar_color; ?>">
                                                <?php echo $initials; ?>
                                            </div>
                                            <strong><?php echo htmlspecialchars($attendee['name'] ?? 'N/A'); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $attendee['attendee_type'] === 'MEMBER' ? 'bg-primary' : 'bg-info'; ?> badge-sm">
                                            <?php echo $attendee['attendee_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($attendee['phone'] ?? 'N/A'); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-person-check text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No attendees present</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Member Status & Actions Section -->
        <div class="col-lg-6">
            <div class="row g-3 h-100">
                <!-- Absent Members -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="fw-bold mb-0">
                                <i class="bi bi-person-x me-2 text-warning"></i>Absent Members (<?php echo count($absent_members); ?>)
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($absent_members)): ?>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact</th>
                                            <th>Department</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($absent_members as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="attendee-avatar me-2" style="background-color: #fd7e14; width: 1.8rem; height: 1.8rem; font-size: 0.75rem;">
                                                        <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?>
                                                    <br>
                                                    <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($member['congregation_group'] ?? 'N/A'); ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">All members present! 🎉</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="fw-bold mb-0">
                                <i class="bi bi-lightning me-2 text-primary"></i>Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="dropdown d-grid">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" 
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-download me-1"></i>Export
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="exportFullReport()">
                                                <i class="bi bi-file-spreadsheet me-1"></i>Full Report
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="exportAbsentees()">
                                                <i class="bi bi-person-x me-1"></i>Absent List
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-info btn-sm w-100" onclick="printReport()">
                                        <i class="bi bi-printer me-1"></i>Print
                                    </button>
                                </div>
                                <div class="col-6">
                                    <a href="mark.php?session_id=<?php echo $session_id; ?>" class="btn btn-success btn-sm w-100">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-warning btn-sm w-100" onclick="sendNotifications()">
                                        <i class="bi bi-bell me-1"></i>Notify
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Insights -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="fw-bold mb-0">
                                <i class="bi bi-lightbulb me-2 text-warning"></i>Insights
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if ($statistics['attendance_percentage'] >= 80): ?>
                            <div class="alert alert-success border-0 py-2 mb-2">
                                <small><i class="bi bi-check-circle me-1"></i><strong>Excellent Attendance</strong> - <?php echo $statistics['attendance_percentage']; ?>% member attendance</small>
                            </div>
                            <?php elseif ($statistics['attendance_percentage'] >= 60): ?>
                            <div class="alert alert-warning border-0 py-2 mb-2">
                                <small><i class="bi bi-exclamation-triangle me-1"></i><strong>Good Attendance</strong> - <?php echo $statistics['attendance_percentage']; ?>% member attendance</small>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-danger border-0 py-2 mb-2">
                                <small><i class="bi bi-exclamation-circle me-1"></i><strong>Low Attendance</strong> - Consider follow-up</small>
                            </div>
                            <?php endif; ?>

                            <?php if ($statistics['visitors'] > 0): ?>
                            <div class="alert alert-info border-0 py-2 mb-2">
                                <small><i class="bi bi-people me-1"></i><?php echo $statistics['visitors']; ?> visitor<?php echo $statistics['visitors'] > 1 ? 's' : ''; ?> attended today</small>
                            </div>
                            <?php endif; ?>

                            <?php if ($statistics['new_converts'] > 0): ?>
                            <div class="alert alert-success border-0 py-2 mb-0">
                                <small><i class="bi bi-heart me-1"></i><?php echo $statistics['new_converts']; ?> new convert<?php echo $statistics['new_converts'] > 1 ? 's' : ''; ?> recorded!</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- No Session Selected -->
    <div class="row">
        <div class="col-12">
            <div class="empty-state">
                <i class="bi bi-exclamation-circle"></i>
                <h3>No Service Session Selected</h3>
                <p>Please select a service session to view the comprehensive report.</p>
                <a href="../services/sessions.php" class="modern-btn">
                    <i class="bi bi-arrow-left me-2"></i>Back to Sessions
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<script>
// Filter attendees function
function filterAttendees(type) {
    const table = document.getElementById('attendeeTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const buttons = document.querySelectorAll('.filter-buttons .btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter rows
    rows.forEach(row => {
        const rowType = row.getAttribute('data-type');
        if (type === 'all' || rowType === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Filter present attendees function
function filterPresentAttendees(type) {
    const table = document.getElementById('presentAttendeesTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const buttons = document.querySelectorAll('.btn-group .btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter rows
    rows.forEach(row => {
        const rowType = row.getAttribute('data-type');
        if (type === 'all' || rowType === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Export report function
function exportReport() {
    exportFullReport();
}

function exportFullReport() {
    const sessionName = '<?php echo addslashes($service_data['service_name'] ?? ''); ?>';
    const sessionDate = '<?php echo addslashes($service_data['session_date'] ?? ''); ?>';
    
    let csvContent = `Service Report - ${sessionName}\n`;
    csvContent += `Date: ${sessionDate}\n\n`;
    csvContent += `Statistics:\n`;
    csvContent += `Total Members: <?php echo $statistics['total_members']; ?>\n`;
    csvContent += `Present Members: <?php echo $statistics['present_members']; ?>\n`;
    csvContent += `Absent Members: <?php echo $statistics['absent_members']; ?>\n`;
    csvContent += `Visitors: <?php echo $statistics['visitors']; ?>\n`;
    csvContent += `New Converts: <?php echo $statistics['new_converts']; ?>\n`;
    csvContent += `Total Attendance: <?php echo $statistics['total_attendance']; ?>\n`;
    csvContent += `Attendance Percentage: <?php echo $statistics['attendance_percentage']; ?>%\n\n`;
    
    // Add attendee list
    csvContent += `Attendee List:\n`;
    csvContent += `Type,Name,Phone,Email,Status\n`;
    
    <?php foreach ($attendee_list as $attendee): ?>
    csvContent += `<?php echo addslashes($attendee['attendee_type']); ?>,"<?php echo addslashes($attendee['name'] ?? ''); ?>","<?php echo addslashes($attendee['phone'] ?? ''); ?>","<?php echo addslashes($attendee['email'] ?? ''); ?>","<?php echo addslashes($attendee['status'] ?? 'present'); ?>"\n`;
    <?php endforeach; ?>
    
    downloadCSV(csvContent, `service_report_${sessionDate}.csv`);
}

function exportSummary() {
    const sessionName = '<?php echo addslashes($service_data['service_name'] ?? ''); ?>';
    const sessionDate = '<?php echo addslashes($service_data['session_date'] ?? ''); ?>';
    
    let csvContent = `Service Summary Report - ${sessionName}\n`;
    csvContent += `Date: ${sessionDate}\n\n`;
    csvContent += `Summary Statistics:\n`;
    csvContent += `Total Members,<?php echo $statistics['total_members']; ?>\n`;
    csvContent += `Present Members,<?php echo $statistics['present_members']; ?>\n`;
    csvContent += `Absent Members,<?php echo $statistics['absent_members']; ?>\n`;
    csvContent += `Attendance Rate,<?php echo $statistics['attendance_percentage']; ?>%\n`;
    csvContent += `Visitors,<?php echo $statistics['visitors']; ?>\n`;
    csvContent += `New Converts,<?php echo $statistics['new_converts']; ?>\n`;
    csvContent += `Total Attendance,<?php echo $statistics['total_attendance']; ?>\n`;
    
    downloadCSV(csvContent, `summary_${sessionDate}.csv`);
}

function exportAbsentees() {
    const sessionName = '<?php echo addslashes($service_data['service_name'] ?? ''); ?>';
    const sessionDate = '<?php echo addslashes($service_data['session_date'] ?? ''); ?>';
    
    let csvContent = `Absent Members Report - ${sessionName}\n`;
    csvContent += `Date: ${sessionDate}\n\n`;
    csvContent += `Name,Phone,Email,Department\n`;
    
    <?php foreach ($absent_members as $member): ?>
    csvContent += `"<?php echo addslashes($member['name'] ?? ''); ?>","<?php echo addslashes($member['phone'] ?? ''); ?>","<?php echo addslashes($member['email'] ?? ''); ?>","<?php echo addslashes($member['congregation_group'] ?? ''); ?>"\n`;
    <?php endforeach; ?>
    
    downloadCSV(csvContent, `absent_members_${sessionDate}.csv`);
}

function sendNotifications() {
    // This would typically integrate with your notification system
    alert('Notification feature would be implemented here. This could send SMS/Email to absent members.');
}

function printReport() {
    window.print();
}

function downloadCSV(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize page components
document.addEventListener('DOMContentLoaded', function() {
    // Add any initialization code here if needed
});
</script>

<?php include '../../includes/footer.php'; ?>