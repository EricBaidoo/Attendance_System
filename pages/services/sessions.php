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

<!-- Using Bootstrap classes only -->

<div class="sessions-container">
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-calendar-day"></i> Today's Sessions</h1>
                    <p><?php echo date('l, F j, Y'); ?> - Active Service Sessions</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="list.php" class="btn btn-light me-2">
                        <i class="bi bi-list"></i> All Services
                    </a>
                    <a href="../attendance/mark.php" class="btn btn-outline-light">
                        <i class="bi bi-check-square"></i> Mark Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="container">
            <!-- Alert Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stats-card total-services">
                        <h3 class="stats-number"><?php echo $stats['total_today']; ?></h3>
                        <p class="stats-label">Total Today</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card active-sessions">
                        <h3 class="stats-number"><?php echo $stats['open_count']; ?></h3>
                        <p class="stats-label">Open Sessions</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card total-attendance">
                        <h3 class="stats-number"><?php echo $stats['closed_count']; ?></h3>
                        <p class="stats-label">Completed</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card avg-attendance">
                        <h3 class="stats-number"><?php echo round($stats['avg_attendance']); ?></h3>
                        <p class="stats-label">Avg Attendance</p>
                    </div>
                </div>
            </div>

            <!-- Active Sessions Section -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-clock-history"></i> Active Sessions
            </h2>

            <?php if (empty($today_sessions)): ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-plus"></i>
                    <h4>No Sessions Today</h4>
                    <p>No service sessions have been started for today yet.</p>
                </div>
            <?php else: ?>
                    <?php foreach ($today_sessions as $session): ?>
                        <?php
                        $attendance_percentage = $session['total_members'] > 0 
                            ? round(($session['present_count'] / $session['total_members']) * 100) 
                            : 0;
                        
                        $status_class = $session['status'] === 'open' ? 'status-open' : 'status-closed';
                        $session_status_class = $session['status'] === 'open' ? 'open' : 'closed';
                        $status_icon = $session['status'] === 'open' ? 'door-open' : 'lock';
                        ?>
                        <div class="session-item-card status-<?php echo $session['status']; ?>">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="session-header">
                                        <div class="session-icon <?php echo $session_status_class; ?>">
                                            <i class="bi bi-<?php echo $status_icon; ?>"></i>
                                        </div>
                                        <div>
                                            <h4 class="session-title"><?php echo htmlspecialchars($session['service_name']); ?></h4>
                                            <div class="session-meta">
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($session['status']); ?>
                                                </span>
                                                <span class="session-time">Started at <?php echo date('g:i A', strtotime($session['opened_at'])); ?></span>
                                                <?php if ($session['closed_at']): ?>
                                                    <span class="session-time">Ended at <?php echo date('g:i A', strtotime($session['closed_at'])); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="session-opener">
                                        <i class="bi bi-person text-primary me-2"></i>
                                        Opened by <?php echo htmlspecialchars($session['opened_by_user']); ?>
                                        <?php if ($session['closed_by_user']): ?>
                                            • Closed by <?php echo htmlspecialchars($session['closed_by_user']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                    
                                    <div class="col-md-4">
                                        <?php if ($session['status'] === 'closed'): ?>
                                            <!-- Attendance Statistics -->
                                            <div class="attendance-stats">
                                                <div class="stats-row">
                                                    <div class="stat-group">
                                                        <div class="stat-number present"><?php echo $session['present_count']; ?></div>
                                                        <div class="stat-text">Present</div>
                                                    </div>
                                                    <div class="stat-group">
                                                        <div class="stat-number absent"><?php echo $session['absent_count']; ?></div>
                                                        <div class="stat-text">Absent</div>
                                                    </div>
                                                    <div class="stat-group">
                                                        <div class="stat-number rate"><?php echo $attendance_percentage; ?>%</div>
                                                        <div class="stat-text">Rate</div>
                                                    </div>
                                                </div>
                                                
                                                <a href="../attendance/view.php?session_id=<?php echo $session['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="bi bi-eye"></i> View Report
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <!-- Live Session Actions -->
                                            <div class="live-session">
                                                <div class="live-indicator">
                                                    <i class="bi bi-broadcast"></i> LIVE SESSION
                                                </div>
                                                <div class="attendance-count">
                                                    <?php echo $session['present_count']; ?> / <?php echo $session['total_members']; ?>
                                                </div>
                                                <div class="attendance-label">Present</div>
                                                
                                                <div class="action-buttons">
                                                    <a href="../attendance/mark.php?session_id=<?php echo $session['id']; ?>" 
                                                       class="btn btn-success btn-sm">
                                                        <i class="bi bi-check-square"></i> Mark
                                                    </a>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Close this session? All unmarked members will be marked absent.')">
                                                        <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                        <input type="hidden" name="close_session" value="1">
                                                        <button type="submit" class="btn btn-danger btn-sm">
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
                    <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Start New Session Section -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="bi bi-plus-circle"></i> Start New Session
            </h2>
            
            <?php if (empty($service_templates)): ?>
                <div class="empty-state">
                    <i class="bi bi-exclamation-triangle"></i>
                    <h4>No Service Templates</h4>
                    <p>Please create some services first before opening sessions.</p>
                    <a href="list.php" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Create Services
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($service_templates as $template): ?>
                    <div class="col-md-4 mb-3">
                        <div class="template-card">
                            <div class="template-icon">
                                <i class="bi bi-calendar-plus"></i>
                            </div>
                            <h5 class="template-title"><?php echo htmlspecialchars($template['name']); ?></h5>
                            <p class="template-description"><?php echo htmlspecialchars($template['description']); ?></p>
                            
                            <form method="post" class="start-session-form">
                                <input type="hidden" name="service_id" value="<?php echo $template['id']; ?>">
                                <input type="hidden" name="session_date" value="<?php echo $today; ?>">
                                <input type="hidden" name="open_session" value="1">
                                
                                <button type="submit" class="btn btn-primary btn-start-session">
                                    <i class="bi bi-play-fill"></i> Start Session
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Professional hover effects and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Stats cards hover effects
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06)';
        });
    });
    
    // Session items hover effects
    const sessionItems = document.querySelectorAll('.session-item');
    sessionItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
        });
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
        });
    });
    
    // Template cards - no hover effects (removed)
    
    // Button hover effects
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        const originalTransform = button.style.transform;
        button.addEventListener('mouseenter', function() {
            if (!this.disabled) {
                this.style.transform = 'translateY(-1px)';
            }
        });
        button.addEventListener('mouseleave', function() {
            this.style.transform = originalTransform || 'translateY(0)';
        });
    });
});

// Auto-refresh for live sessions (every 60 seconds for better performance)
let refreshInterval;
function startAutoRefresh() {
    const liveIndicators = document.querySelectorAll('.live-indicator');
    if (liveIndicators.length > 0) {
        refreshInterval = setTimeout(function() {
            window.location.reload();
        }, 60000); // 60 seconds
    }
}

// Start auto-refresh on page load
startAutoRefresh();

// Clear refresh interval when page is hidden to save resources
document.addEventListener('visibilitychange', function() {
    if (document.hidden && refreshInterval) {
        clearTimeout(refreshInterval);
    } else if (!document.hidden) {
        startAutoRefresh();
    }
});

// Enhanced form validation for location input
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const locationInput = this.querySelector('input[name="location"]');
        if (locationInput && locationInput.value.trim() === '') {
            e.preventDefault();
            locationInput.style.borderColor = '#ef4444';
            locationInput.focus();
            
            // Show error message
            let errorMsg = locationInput.parentNode.querySelector('.error-message');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.style.color = '#ef4444';
                errorMsg.style.fontSize = '0.75rem';
                errorMsg.style.marginTop = '0.25rem';
                errorMsg.textContent = 'Please enter a location for the session.';
                locationInput.parentNode.appendChild(errorMsg);
            }
            
            // Remove error styling on input
            locationInput.addEventListener('input', function() {
                this.style.borderColor = '#e5e7eb';
                const errorMsg = this.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
        }
    });
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Loading state for forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitButton = this.querySelector('button[type="submit"]');
        if (submitButton && !submitButton.disabled) {
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            submitButton.disabled = true;
            
            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }, 5000);
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>