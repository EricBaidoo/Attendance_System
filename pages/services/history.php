<?php
// pages/services/history.php - View service session history with attendee lists
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Database connection
require '../../config/database.php';
require '../../includes/attendance_utils.php';

$service_id = $_GET['service_id'] ?? '';
$service = null;
$sessions = [];
$error = '';

if ($service_id) {
    // Get service details
    $service_sql = "SELECT * FROM services WHERE id = ?";
    $service_stmt = $pdo->prepare($service_sql);
    $service_stmt->execute([$service_id]);
    $service = $service_stmt->fetch();
    
    if ($service) {
        // Get all sessions for this service (ordered by most recent first)
        $sessions_sql = "SELECT ss.*, 
                        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as member_present_count,
                        COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.member_id END) as member_absent_count,
                        (
                            SELECT COUNT(DISTINCT v.id) 
                            FROM visitors v 
                            WHERE v.service_id = ss.service_id 
                            AND v.date = ss.session_date
                        ) as visitor_count,
                        u1.username as opened_by_user,
                        u2.username as closed_by_user
                        FROM service_sessions ss
                        LEFT JOIN attendance a ON ss.id = a.session_id
                        LEFT JOIN users u1 ON ss.opened_by = u1.id
                        LEFT JOIN users u2 ON ss.closed_by = u2.id
                        WHERE ss.service_id = ?
                        GROUP BY ss.id
                        ORDER BY ss.session_date DESC, ss.opened_at DESC";
        $sessions_stmt = $pdo->prepare($sessions_sql);
        $sessions_stmt->execute([$service_id]);
        $sessions = $sessions_stmt->fetchAll();
    } else {
        $error = 'Service not found.';
    }
} else {
    $error = 'No service specified.';
}

$page_title = "Service History - " . ($service ? $service['name'] : 'Unknown Service');
?>
<?php include '../../includes/header.php'; ?>

<link href="../../assets/css/services.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Service History</h1>
                    <?php if ($service): ?>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($service['name']); ?></p>
                    <?php endif; ?>
                </div>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Services
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($service): ?>
                <!-- Service Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Service Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($service['description'] ?: 'No description available'); ?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Total Sessions:</strong> <?php echo count($sessions); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-<?php echo $service['template_status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($service['template_status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sessions History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> Session History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sessions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Sessions Found</h5>
                                <p class="text-muted">No sessions have been held for this service yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Duration</th>
                                            <th>Members</th>
                                            <th>Visitors</th>
                                            <th>Total Attendance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <?php
                                            $total_attendance = $session['member_present_count'] + $session['visitor_count'];
                                            $attendance_rate = $session['member_present_count'] > 0 && ($session['member_present_count'] + $session['member_absent_count']) > 0
                                                ? round(($session['member_present_count'] / ($session['member_present_count'] + $session['member_absent_count'])) * 100)
                                                : 0;
                                            
                                            // Calculate duration
                                            $duration = 'N/A';
                                            if ($session['opened_at'] && $session['closed_at']) {
                                                $start = new DateTime($session['opened_at']);
                                                $end = new DateTime($session['closed_at']);
                                                $diff = $start->diff($end);
                                                $duration = '';
                                                if ($diff->h > 0) $duration .= $diff->h . 'h ';
                                                $duration .= $diff->i . 'm';
                                            } elseif ($session['status'] === 'open') {
                                                $duration = 'Live Session';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($session['session_date'])); ?></strong><br>
                                                    <small class="text-muted">
                                                        Started: <?php echo $session['opened_at'] ? date('g:i A', strtotime($session['opened_at'])) : 'N/A'; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $session['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($session['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $session['status'] === 'open' ? 'text-success fw-bold' : ''; ?>">
                                                        <?php echo $duration; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-success fw-bold"><?php echo $session['member_present_count']; ?></span>
                                                        <span class="text-muted">/</span>
                                                        <span class="text-danger"><?php echo $session['member_absent_count']; ?></span>
                                                    </div>
                                                    <small class="text-muted"><?php echo $attendance_rate; ?>% rate</small>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-info"><?php echo $session['visitor_count']; ?></span>
                                                    <small class="text-muted">guests</small>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-primary fs-5"><?php echo $total_attendance; ?></span>
                                                    <small class="text-muted">total</small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="../attendance/view.php?session_id=<?php echo $session['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           title="View Detailed Report">
                                                            <i class="fas fa-chart-bar"></i>
                                                        </a>
                                                        <a href="../attendance/attendees.php?session_id=<?php echo $session['id']; ?>" 
                                                           class="btn btn-outline-success btn-sm" 
                                                           title="View Attendee List">
                                                            <i class="fas fa-users"></i>
                                                        </a>
                                                        <?php if ($session['status'] === 'open'): ?>
                                                            <a href="../attendance/mark.php?session_id=<?php echo $session['id']; ?>" 
                                                               class="btn btn-outline-warning btn-sm" 
                                                               title="Mark Attendance">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Summary Statistics -->
                            <div class="row mt-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h4><?php echo count($sessions); ?></h4>
                                            <small>Total Sessions</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h4><?php echo array_sum(array_column($sessions, 'member_present_count')); ?></h4>
                                            <small>Total Member Attendance</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h4><?php echo array_sum(array_column($sessions, 'visitor_count')); ?></h4>
                                            <small>Total Visitor Attendance</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-dark">
                                        <div class="card-body text-center">
                                            <?php 
                                            $avg_attendance = count($sessions) > 0 
                                                ? round((array_sum(array_column($sessions, 'member_present_count')) + array_sum(array_column($sessions, 'visitor_count'))) / count($sessions))
                                                : 0;
                                            ?>
                                            <h4><?php echo $avg_attendance; ?></h4>
                                            <small>Avg Attendance/Session</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>