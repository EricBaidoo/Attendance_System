<?php
// pages/reports/report_simple.php - Simplified System Reports
session_start();

// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Include database
require_once '../../config/database.php';

// Get date filters with defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Initialize variables with defaults
$overview_stats = [
    'total_members' => 0,
    'total_visitors' => 0,
    'new_converts' => 0,
    'total_sessions' => 0,
    'active_departments' => 0,
    'active_services' => 0
];

$attendance_stats = [
    'total_present' => 0,
    'total_absent' => 0,
    'sessions_with_attendance' => 0,
    'unique_attendees' => 0,
    'attendance_percentage' => 0
];

$demographics = [
    'total_members' => 0,
    'male_count' => 0,
    'female_count' => 0,
    'baptized_count' => 0,
    'adult_count' => 0,
    'youth_count' => 0
];

$error_message = '';

try {
    // Get basic member count
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'");
    $overview_stats['total_members'] = $stmt->fetchColumn();
    
    // Get visitors count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $overview_stats['total_visitors'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Get sessions count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_sessions WHERE session_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $overview_stats['total_sessions'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Get departments count
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM departments WHERE status = 'active'");
        $overview_stats['active_departments'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Table might not exist
    }
    
    // Get attendance statistics
    try {
        $attendance_sql = "SELECT 
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
            COUNT(DISTINCT a.session_id) as sessions_with_attendance,
            COUNT(DISTINCT a.member_id) as unique_attendees,
            ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as attendance_percentage
            FROM attendance a
            JOIN service_sessions ss ON a.session_id = ss.id
            WHERE ss.session_date BETWEEN ? AND ?";
        
        $stmt = $pdo->prepare($attendance_sql);
        $stmt->execute([$start_date, $end_date]);
        $result = $stmt->fetch();
        
        if ($result) {
            $attendance_stats = $result;
        }
    } catch (Exception $e) {
        // Attendance tables might not exist or have issues
    }
    
    // Get demographics
    try {
        $demographics_sql = "SELECT 
            COUNT(*) as total_members,
            COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_count,
            COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_count,
            COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized_count,
            COUNT(CASE WHEN congregation_group = 'Adult' THEN 1 END) as adult_count,
            COUNT(CASE WHEN congregation_group = 'Youth' THEN 1 END) as youth_count
            FROM members WHERE status = 'active'";
        
        $stmt = $pdo->query($demographics_sql);
        $result = $stmt->fetch();
        
        if ($result) {
            $demographics = $result;
        }
    } catch (Exception $e) {
        // Demographics query failed
    }

} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

$page_title = "System Reports - Bridge Ministries International";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stat-card {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #0056b3 100%);
            border: none;
            border-radius: 12px;
            color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <?php if ($error_message): ?>
            <div class="alert alert-danger border-0 shadow-sm">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h2 text-primary mb-2 fw-bold">
                            <i class="bi bi-graph-up"></i> System Reports & Analytics
                        </h1>
                        <p class="text-muted mb-0">
                            Comprehensive insights for <?php echo date('F j, Y', strtotime($start_date)); ?> 
                            to <?php echo date('F j, Y', strtotime($end_date)); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Apply Filters
                                </button>
                                <a href="test_db.php" class="btn btn-outline-info">
                                    <i class="bi bi-database"></i> Test DB
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card h-100" style="background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo number_format($overview_stats['total_members']); ?></div>
                                <div class="stat-label">Active Members</div>
                            </div>
                            <div class="text-white-50">
                                <i class="bi bi-people fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card h-100" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo number_format($attendance_stats['total_present']); ?></div>
                                <div class="stat-label">Total Present</div>
                            </div>
                            <div class="text-white-50">
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card h-100" style="background: linear-gradient(135deg, #fd7e14 0%, #e55a0d 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo number_format($overview_stats['total_visitors']); ?></div>
                                <div class="stat-label">New Visitors</div>
                            </div>
                            <div class="text-white-50">
                                <i class="bi bi-person-plus fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card h-100" style="background: linear-gradient(135deg, #6f42c1 0%, #59369c 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="stat-number"><?php echo $attendance_stats['attendance_percentage'] ?: '0'; ?>%</div>
                                <div class="stat-label">Attendance Rate</div>
                            </div>
                            <div class="text-white-50">
                                <i class="bi bi-graph-up fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demographics & Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-pie-chart text-success"></i> Member Demographics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-primary"><?php echo $demographics['male_count']; ?></div>
                                    <small class="text-muted">Male Members</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-success"><?php echo $demographics['female_count']; ?></div>
                                    <small class="text-muted">Female Members</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-info"><?php echo $demographics['baptized_count']; ?></div>
                                    <small class="text-muted">Baptized</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-warning"><?php echo $demographics['adult_count']; ?></div>
                                    <small class="text-muted">Adults</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-speedometer2 text-info"></i> Quick Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-primary"><?php echo $overview_stats['total_sessions']; ?></div>
                                    <small class="text-muted">Total Sessions</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-success"><?php echo $attendance_stats['unique_attendees']; ?></div>
                                    <small class="text-muted">Unique Attendees</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-warning"><?php echo $overview_stats['active_departments']; ?></div>
                                    <small class="text-muted">Active Departments</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 mb-1 text-danger"><?php echo $attendance_stats['total_absent']; ?></div>
                                    <small class="text-muted">Total Absent</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="fw-semibold mb-3">Attendance Performance</h6>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $attendance_stats['attendance_percentage']; ?>%">
                                    <?php echo $attendance_stats['attendance_percentage']; ?>%
                                </div>
                            </div>
                            <small class="text-muted">Overall attendance rate for selected period</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-4 text-center">
                        <h6 class="mb-3">Report Actions</h6>
                        <a href="test_db.php" class="btn btn-outline-primary me-2">
                            <i class="bi bi-database"></i> Test Database
                        </a>
                        <a href="report.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-arrow-clockwise"></i> Full Report
                        </a>
                        <button class="btn btn-outline-success" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>