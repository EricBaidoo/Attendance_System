<?php
// pages/services/sessions.php - Manage service sessions (today's services)
session_start();
require __DIR__ . '/../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$success = '';
$error = '';
$today = date('Y-m-d');

// Handle session management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . print_r($_POST, true));
    if (isset($_POST['open_session'])) {
        $service_id = $_POST['service_id'];
        $session_date = $_POST['session_date'] ?? $today;
        
        try {
            $pdo->beginTransaction();
            
            // Check if any session already exists for this service today
            $check_sql = "SELECT id, status FROM service_sessions WHERE service_id = ? AND session_date = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$service_id, $session_date]);
            $existing = $check_stmt->fetch();
            
            if ($existing && $existing['status'] === 'open') {
                $error = 'An open session for this service already exists today.';
            } else if ($existing && $existing['status'] === 'closed') {
                // Delete the old closed session and create a new one
                $delete_sql = "DELETE FROM service_sessions WHERE id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([$existing['id']]);
                
                // Create new session
                $sql = "INSERT INTO service_sessions (service_id, session_date, status, opened_at, opened_by) VALUES (?, ?, 'open', NOW(), ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$service_id, $session_date, $_SESSION['user_id']]);
                
                if ($result) {
                    $session_id = $pdo->lastInsertId();
                    $success = 'Service session opened successfully!';
                    error_log("New session created: ID = $session_id");
                } else {
                    throw new Exception('Failed to create session.');
                }
            } else {
                // Create new session
                $sql = "INSERT INTO service_sessions (service_id, session_date, status, opened_at, opened_by) VALUES (?, ?, 'open', NOW(), ?)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$service_id, $session_date, $_SESSION['user_id']]);
                
                if ($result) {
                    $session_id = $pdo->lastInsertId();
                    $success = 'Service session opened successfully!';
                    error_log("New session created: ID = $session_id");
                } else {
                    throw new Exception('Failed to create session.');
                }
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Error opening session: ' . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }
    
    if (isset($_POST['close_session'])) {
        $session_id = $_POST['session_id'];
        error_log("Attempting to close session ID: $session_id");
        
        try {
            $pdo->beginTransaction();
            
            // Get session info for the date and service_id
            $session_info_sql = "SELECT service_id, session_date FROM service_sessions WHERE id = ?";
            $session_info_stmt = $pdo->prepare($session_info_sql);
            $session_info_stmt->execute([$session_id]);
            $session_info = $session_info_stmt->fetch();
            
            if (!$session_info) {
                throw new Exception("Session not found");
            }
            
            // Get all active members who are not marked present for this session
            $unmarked_sql = "SELECT m.id FROM members m 
                           WHERE m.status = 'active' 
                           AND m.id NOT IN (
                               SELECT member_id FROM attendance 
                               WHERE session_id = ? AND status = 'present'
                           )";
            $unmarked_stmt = $pdo->prepare($unmarked_sql);
            $unmarked_stmt->execute([$session_id]);
            $unmarked_members = $unmarked_stmt->fetchAll();
            
            error_log("Found " . count($unmarked_members) . " unmarked members");
            
            // Mark unmarked members as absent with proper service_id and date
            $absent_sql = "INSERT INTO attendance (member_id, service_id, session_id, date, status, marked_by, method) VALUES (?, ?, ?, ?, 'absent', ?, 'auto')";
            $absent_stmt = $pdo->prepare($absent_sql);
            
            foreach ($unmarked_members as $member) {
                $absent_stmt->execute([
                    $member['id'], 
                    $session_info['service_id'], 
                    $session_id, 
                    $session_info['session_date'], 
                    $_SESSION['user_id']
                ]);
            }
            
            // Close the session
            $close_sql = "UPDATE service_sessions SET status = 'closed', closed_at = NOW(), closed_by = ? WHERE id = ?";
            $close_stmt = $pdo->prepare($close_sql);
            $result = $close_stmt->execute([$_SESSION['user_id'], $session_id]);
            
            if (!$result) {
                throw new Exception("Failed to update session status");
            }
            
            $pdo->commit();
            $success = 'Session closed successfully! ' . count($unmarked_members) . ' members were marked as absent (not marked as present).';
            error_log("Session $session_id closed successfully");
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Error closing session: ' . $e->getMessage();
            error_log("Error closing session: " . $e->getMessage());
        }
    }
}

// Get service templates
$templates_sql = "SELECT id, name, description FROM services WHERE template_status = 'active' ORDER BY name";
$templates_stmt = $pdo->query($templates_sql);
$service_templates = $templates_stmt->fetchAll();

// Get today's sessions with service details and attendance counts
$sessions_sql = "SELECT ss.*, s.name as service_name, s.description,
                 COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.member_id END) as present_count,
                 COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.member_id END) as absent_count,
                 (SELECT COUNT(*) FROM members WHERE status = 'active') as total_members,
                 u1.username as opened_by_user,
                 u2.username as closed_by_user
                 FROM service_sessions ss
                 JOIN services s ON ss.service_id = s.id
                 LEFT JOIN attendance a ON ss.id = a.session_id
                 LEFT JOIN users u1 ON ss.opened_by = u1.id
                 LEFT JOIN users u2 ON ss.closed_by = u2.id
                 WHERE ss.session_date = ?
                 GROUP BY ss.id
                 ORDER BY ss.opened_at DESC";
$sessions_stmt = $pdo->prepare($sessions_sql);
$sessions_stmt->execute([$today]);
$today_sessions = $sessions_stmt->fetchAll();

// Get sessions statistics
$stats_sql = "SELECT 
              COUNT(*) as total_today,
              COUNT(CASE WHEN status = 'open' THEN 1 END) as open_count,
              COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_count,
              COALESCE(AVG(attendance_count), 0) as avg_attendance
              FROM service_sessions ss
              LEFT JOIN (
                  SELECT session_id, COUNT(*) as attendance_count
                  FROM attendance WHERE status = 'present'
                  GROUP BY session_id
              ) a ON ss.id = a.session_id
              WHERE ss.session_date = ?";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([$today]);
$stats = $stats_stmt->fetch();

$page_title = "Today's Sessions - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">

<style>
/* Force Bootstrap Icons to load properly */
@import url('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css');

.bi {
    font-family: "bootstrap-icons" !important;
    font-style: normal !important;
    font-variant: normal !important;
    text-rendering: auto;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.bi::before {
    font-family: "bootstrap-icons" !important;
    font-weight: normal !important;
    font-style: normal !important;
}

/* Service template card enhancements */
.template-icon {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}

.template-icon i {
    display: inline-block;
    font-size: 1.5rem !important;
    line-height: 1;
}

/* Fallback content for icons that don't load */
.template-icon::after {
    content: "⛪";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    display: none;
}

/* Show fallback if icon doesn't load */
.template-icon i:empty::before {
    content: "⛪";
    font-family: system-ui, -apple-system, sans-serif;
}

.card:hover .template-icon {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 50, 0.3);
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

/* Service icon styling */
.session-icon i {
    font-size: 1.2rem;
}

/* Enhanced button styling */
.start-session-form .btn-primary {
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
    border: none;
    transition: all 0.3s ease;
}

.start-session-form .btn-primary:hover {
    background: linear-gradient(135deg, #1a1a5e 0%, #000032 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 50, 0.3);
}

/* Force icon visibility */
.bi-sun::before { content: "\F5A3"; }
.bi-heart::before { content: "\F2DC"; }
.bi-book::before { content: "\F1CD"; }
.bi-people::before { content: "\F4E6"; }
.bi-emoji-smile::before { content: "\F25D"; }
.bi-megaphone::before { content: "\F3B6"; }
.bi-lightning::before { content: "\F387"; }
.bi-cup::before { content: "\F22C"; }
.bi-droplet::before { content: "\F254"; }
.bi-stars::before { content: "\F5CB"; }
.bi-calendar-plus::before { content: "\F1E8"; }
.bi-play-fill::before { content: "\F4DF"; }
</style>

<!-- Professional Sessions Dashboard -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-primary mb-2 fw-bold">
                        <i class="bi bi-calendar-day"></i> Today's Service Sessions
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="text-muted">Manage active service sessions for <strong class="text-dark"><?php echo date('l, F j, Y'); ?></strong></span>
                        <span class="badge bg-light text-dark"><?php echo count($today_sessions); ?> Sessions Today</span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <a href="list.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-list"></i> All Services
                    </a>
                    <a href="../attendance/mark.php" class="btn btn-primary">
                        <i class="bi bi-check-square"></i> Mark Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 members-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Total Sessions</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['total_today']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-calendar"></i> Today
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-calendar-week text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 visitors-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Open Sessions</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['open_count']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-broadcast"></i> Live
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-door-open-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 converts-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Completed</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['closed_count']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-check-circle"></i> Finished
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-lock-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 departments-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Avg Attendance</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo round($stats['avg_attendance']); ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-people"></i> Members
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-graph-up text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Sessions Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h3 class="text-primary mb-4 fw-bold">
                <i class="bi bi-clock-history"></i> Active Sessions
            </h3>

            <?php if (empty($today_sessions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-plus text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
                    <h4 class="text-muted mt-3 mb-2">No Sessions Today</h4>
                    <p class="text-muted mb-4">No service sessions have been started for today yet.</p>
                    <a href="#start-session" class="btn btn-primary" onclick="document.getElementById('start-session').scrollIntoView();">
                        <i class="bi bi-plus"></i> Start First Session
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($today_sessions as $session): ?>
                        <?php
                        $attendance_percentage = $session['total_members'] > 0 
                            ? round(($session['present_count'] / $session['total_members']) * 100) 
                            : 0;
                        
                        $status_color = $session['status'] === 'open' ? 'success' : 'secondary';
                        $status_icon = $session['status'] === 'open' ? 'door-open-fill' : 'lock-fill';
                        ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body p-4">
                                    <div class="row align-items-center">
                                        <div class="col-lg-8">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="session-icon me-3">
                                                    <i class="bi bi-<?php echo $status_icon; ?> text-<?php echo $status_color; ?> fs-4"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-primary mb-1 fw-bold"><?php echo htmlspecialchars($session['service_name']); ?></h4>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <span class="badge bg-<?php echo $status_color; ?>">
                                                            <?php echo ucfirst($session['status']); ?>
                                                        </span>
                                                        <small class="text-muted">
                                                            <i class="bi bi-clock"></i> Started at <?php echo date('g:i A', strtotime($session['opened_at'])); ?>
                                                            <?php if ($session['closed_at']): ?>
                                                                • Ended at <?php echo date('g:i A', strtotime($session['closed_at'])); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="session-meta">
                                                <small class="text-muted">
                                                    <i class="bi bi-person text-primary me-1"></i>
                                                    Opened by <strong><?php echo htmlspecialchars($session['opened_by_user']); ?></strong>
                                                    <?php if ($session['closed_by_user']): ?>
                                                        • Closed by <strong><?php echo htmlspecialchars($session['closed_by_user']); ?></strong>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                            
                                        <div class="col-lg-4">
                                            <?php if ($session['status'] === 'closed'): ?>
                                                <!-- Attendance Statistics -->
                                                <div class="text-center">
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-4">
                                                            <div class="p-3 bg-success bg-opacity-10 rounded-3">
                                                                <div class="fw-bold text-success fs-4"><?php echo $session['present_count']; ?></div>
                                                                <small class="text-muted">Present</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="p-3 bg-danger bg-opacity-10 rounded-3">
                                                                <div class="fw-bold text-danger fs-4"><?php echo $session['absent_count']; ?></div>
                                                                <small class="text-muted">Absent</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="p-3 bg-primary bg-opacity-10 rounded-3">
                                                                <div class="fw-bold text-primary fs-4"><?php echo $attendance_percentage; ?>%</div>
                                                                <small class="text-muted">Rate</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <a href="../attendance/view.php?session_id=<?php echo $session['id']; ?>" 
                                                       class="btn btn-primary btn-sm w-100">
                                                        <i class="bi bi-eye"></i> View Detailed Report
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <!-- Live Session Actions -->
                                                <div class="text-center">
                                                    <div class="alert alert-success border-0 mb-3">
                                                        <i class="bi bi-broadcast"></i> <strong>LIVE SESSION</strong>
                                                    </div>
                                                    <div class="attendance-summary p-3 bg-light rounded-3 mb-3">
                                                        <div class="fw-bold fs-3 text-primary"><?php echo $session['present_count']; ?> / <?php echo $session['total_members']; ?></div>
                                                        <small class="text-muted">Members Present</small>
                                                    </div>
                                                    
                                                    <div class="d-flex gap-2">
                                                        <a href="../attendance/mark.php?session_id=<?php echo $session['id']; ?>" 
                                                           class="btn btn-success btn-sm flex-fill">
                                                            <i class="bi bi-check-square"></i> Mark
                                                        </a>
                                                        <form method="post" class="flex-fill" onsubmit="return confirm('Close this session? All unmarked members will be marked absent.')">
                                                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                            <input type="hidden" name="close_session" value="1">
                                                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                                                <i class="bi bi-lock"></i> Close
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Start New Session Section -->
    <div class="card border-0 shadow-sm" id="start-session">
        <div class="card-body p-4">
            <h3 class="text-primary mb-4 fw-bold">
                <i class="bi bi-plus-circle"></i> Start New Session
            </h3>
            
            <?php if (empty($service_templates)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem; opacity: 0.7;"></i>
                    <h4 class="text-muted mt-3 mb-2">No Service Templates</h4>
                    <p class="text-muted mb-4">Please create some services first before opening sessions.</p>
                    <a href="list.php" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Create Services
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($service_templates as $template): ?>
                    <?php
                    // Determine appropriate icon based on service name/type
                    $service_icon = 'calendar-plus';
                    $service_name_lower = strtolower($template['name']);
                    
                    if (strpos($service_name_lower, 'sunday') !== false || strpos($service_name_lower, 'morning') !== false || strpos($service_name_lower, 'evening') !== false) {
                        $service_icon = 'sun';
                    } elseif (strpos($service_name_lower, 'prayer') !== false) {
                        $service_icon = 'heart';
                    } elseif (strpos($service_name_lower, 'bible study') !== false || strpos($service_name_lower, 'study') !== false) {
                        $service_icon = 'book';
                    } elseif (strpos($service_name_lower, 'youth') !== false) {
                        $service_icon = 'people';
                    } elseif (strpos($service_name_lower, 'children') !== false) {
                        $service_icon = 'emoji-smile';
                    } elseif (strpos($service_name_lower, 'conference') !== false || strpos($service_name_lower, 'seminar') !== false) {
                        $service_icon = 'megaphone';
                    } elseif (strpos($service_name_lower, 'revival') !== false) {
                        $service_icon = 'lightning';
                    } elseif (strpos($service_name_lower, 'communion') !== false) {
                        $service_icon = 'cup';
                    } elseif (strpos($service_name_lower, 'baptism') !== false) {
                        $service_icon = 'droplet';
                    } elseif (strpos($service_name_lower, 'celebration') !== false || strpos($service_name_lower, 'special') !== false) {
                        $service_icon = 'stars';
                    }
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="transition: transform 0.3s ease;">
                            <div class="card-body p-4 text-center">
                                <div class="template-icon mx-auto mb-3" style="width: 4rem; height: 4rem; background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                                    <i class="bi bi-<?php echo $service_icon; ?>"></i>
                                </div>
                                <h5 class="text-primary fw-bold mb-3"><?php echo htmlspecialchars($template['name']); ?></h5>
                                <p class="text-muted mb-4"><?php echo htmlspecialchars($template['description']); ?></p>
                                
                                <form method="post" class="start-session-form">
                                    <input type="hidden" name="service_id" value="<?php echo $template['id']; ?>">
                                    <input type="hidden" name="session_date" value="<?php echo $today; ?>">
                                    <input type="hidden" name="open_session" value="1">
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-play-fill"></i> Start Session
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>