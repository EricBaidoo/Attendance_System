<?php
// pages/attendance/mark.php
session_start();
require __DIR__ . '/../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'member';
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get current user's member record if they are a member
$current_member = null;
if ($user_role == 'member') {
    $member_sql = "SELECT * FROM members WHERE email = ? OR name = ? LIMIT 1";
    $member_stmt = $pdo->prepare($member_sql);
    $member_stmt->execute([$_SESSION['username'], $_SESSION['username']]);
    $current_member = $member_stmt->fetch();
    
    if (!$current_member) {
        $error = "No member record found for your account. Please contact an administrator.";
    }
}

// Get only open sessions for today
$sessions_sql = "SELECT ss.*, s.name as service_name, s.description as service_description 
                FROM service_sessions ss 
                JOIN services s ON ss.service_id = s.id 
                WHERE ss.status = 'open' AND ss.session_date = CURDATE() 
                ORDER BY ss.opened_at DESC";
$sessions_stmt = $pdo->query($sessions_sql);
$sessions = $sessions_stmt->fetchAll();

// Handle attendance submission (simplified for open sessions only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $session_id = $_POST['session_id'] ?? '';
    $member_id = $_POST['member_id'] ?? '';
    $action = $_POST['action'] ?? 'mark';
    
    if ($session_id && $member_id) {
        try {
            // Verify session is open and for today
            $session_check_sql = "SELECT ss.*, s.id as service_id FROM service_sessions ss 
                                 JOIN services s ON ss.service_id = s.id 
                                 WHERE ss.id = ? AND ss.status = 'open' AND ss.session_date = CURDATE()";
            $session_check_stmt = $pdo->prepare($session_check_sql);
            $session_check_stmt->execute([$session_id]);
            $session_data = $session_check_stmt->fetch();
            
            if (!$session_data) {
                echo json_encode(['success' => false, 'message' => 'Session is not available for attendance marking']);
                exit;
            }
            
            if ($action === 'mark') {
                // Mark as present
                $insert_sql = "INSERT IGNORE INTO attendance (member_id, session_id, service_id, date, status, marked_by, method) VALUES (?, ?, ?, CURDATE(), 'present', ?, 'manual')";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([$member_id, $session_id, $session_data['service_id'], $_SESSION['user_id']]);
                echo json_encode(['success' => true, 'message' => 'Member marked as present']);
            } else {
                // Unmark (remove attendance record)
                $delete_sql = "DELETE FROM attendance WHERE member_id = ? AND session_id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute([$member_id, $session_id]);
                echo json_encode(['success' => true, 'message' => 'Attendance removed']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Auto-select the first open session or get selected session
$selected_session = null;
$selected_session_id = $_GET['session_id'] ?? '';

if ($selected_session_id) {
    // Validate selected session is open and for today
    foreach ($sessions as $session) {
        if ($session['id'] == $selected_session_id) {
            $selected_session = $session;
            break;
        }
    }
    if (!$selected_session) {
        $error = 'Selected session is not available for attendance marking.';
    }
} elseif (!empty($sessions)) {
    // Auto-select first open session
    $selected_session = $sessions[0];
    $selected_session_id = $selected_session['id'];
}

// Get members with real-time attendance for selected session
$members = [];
$attendance_summary = ['present' => 0, 'total' => 0];

if ($selected_session) {
    $members_sql = "SELECT m.*, d.name as department_name, 
                    CASE WHEN a.id IS NOT NULL THEN 'present' ELSE 'unmarked' END as attendance_status 
                    FROM members m 
                    LEFT JOIN departments d ON m.department_id = d.id 
                    LEFT JOIN attendance a ON m.id = a.member_id 
                        AND a.session_id = ? 
                        AND a.status = 'present'
                    WHERE m.status = 'active'
                    ORDER BY d.name, m.name";
    $members_stmt = $pdo->prepare($members_sql);
    $members_stmt->execute([$selected_session_id]);
    $members = $members_stmt->fetchAll();
    
    // Calculate real-time summary using DISTINCT count like sessions.php
    $attendance_summary['total'] = count($members);
    
    // Get accurate present count using DISTINCT like sessions.php
    $present_count_sql = "SELECT COUNT(DISTINCT member_id) as present_count 
                         FROM attendance 
                         WHERE session_id = ? AND status = 'present'";
    $present_count_stmt = $pdo->prepare($present_count_sql);
    $present_count_stmt->execute([$selected_session_id]);
    $present_result = $present_count_stmt->fetch();
    $attendance_summary['present'] = $present_result['present_count'] ?? 0;
    
    $attendance_summary['absent'] = $attendance_summary['total'] - $attendance_summary['present'];
    $attendance_summary['percentage'] = $attendance_summary['total'] > 0 ? round(($attendance_summary['present'] / $attendance_summary['total']) * 100) : 0;
}

$page_title = "Mark Attendance - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/attendance-modern.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="feature-icon bg-gradient-primary text-white rounded-3 me-3">
                            <i class="bi bi-check-square-fill fs-3"></i>
                        </div>
                        <div>
                            <h1 class="mb-1 fw-bold text-dark">Mark Attendance</h1>
                            <?php if ($selected_session): ?>
                                <p class="text-muted mb-0">
                                    <strong><?php echo htmlspecialchars($selected_session['service_name'] ?? ''); ?></strong> • 
                                    <?php echo date('F j, Y'); ?>
                                </p>
                                <div class="d-flex gap-3 text-muted small">
                                    <span><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($selected_session['location'] ?? ''); ?></span>
                                    <span><i class="bi bi-clock me-1"></i>Started <?php echo date('g:i A', strtotime($selected_session['opened_at'])); ?></span>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No active sessions available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($selected_session): ?>
                        <div class="d-flex align-items-center justify-content-end">
                            <div class="live-indicator me-3">
                                <span class="badge bg-success fs-6 px-3 py-2 live-indicator">
                                    <i class="bi bi-circle-fill me-1 live-indicator"></i>LIVE SESSION
                                </span>
                            </div>
                            <div class="text-center">
                                <div class="fs-3 fw-bold text-success"><?php echo $attendance_summary['present']; ?></div>
                                <small class="text-muted">of <?php echo $attendance_summary['total']; ?> Present</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="feature-icon bg-light text-muted rounded-3">
                            <i class="bi bi-people-fill fs-1"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Active Session Selection -->
        <?php if (!empty($sessions) && count($sessions) > 1): ?>
        <div class="service-selection">
            <h3><i class="bi bi-broadcast"></i> Active Sessions Today</h3>
            <div class="row">
                <?php foreach ($sessions as $session): ?>
                    <div class="col-md-6 mb-3">
                        <div class="service-card <?php echo ($selected_session_id == $session['id']) ? 'active' : ''; ?>" 
                             onclick="selectSession(<?php echo $session['id']; ?>)">
                            <h4><?php echo htmlspecialchars($session['service_name'] ?? ''); ?></h4>
                            <p class="service-date">
                                <i class="bi bi-clock"></i> Started <?php echo date('g:i A', strtotime($session['opened_at'])); ?>
                            </p>
                            <p><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($session['location'] ?? ''); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($selected_session): ?>
        <!-- Attendance Controls -->
        <div class="attendance-marking">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3><i class="bi bi-list-check"></i> Attendance for <?php echo htmlspecialchars($selected_session['service_name'] ?? ''); ?></h3>
                <div>
                    <button class="btn btn-success btn-sm" onclick="markAllPresent()">
                        <i class="bi bi-check-all"></i> Mark All Present
                    </button>
                    <a href="view.php?session_id=<?php echo $selected_session_id; ?>" class="btn btn-info btn-sm">
                        <i class="bi bi-eye"></i> View Reports
                    </a>
                </div>
            </div>

            <div class="attendance-controls">
                <div class="row">
                    <div class="col-md-6">
                        <div class="control-group">
                            <label>Search Members</label>
                            <input type="text" class="form-control search-attendance" id="searchMembers" 
                                   placeholder="Type member name...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="control-group">
                            <label>Filter by Status</label>
                            <div class="filter-buttons">
                                <button class="filter-btn active" data-status="all">All</button>
                                <button class="filter-btn" data-status="present">Present</button>
                                <button class="filter-btn" data-status="unmarked">Not Marked</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Members Grid -->
            <div class="members-grid" id="membersGrid">
                <?php foreach ($members as $member): ?>
                    <?php
                    $initials = strtoupper(substr($member['name'], 0, 2));
                    $status_class = $member['attendance_status'] ? $member['attendance_status'] : '';
                    ?>
                    <div class="member-attendance-card <?php echo $status_class; ?>" 
                         data-member-id="<?php echo $member['id']; ?>"
                         data-member-name="<?php echo strtolower($member['name']); ?>"
                         data-status="<?php echo $member['attendance_status'] ? $member['attendance_status'] : 'unmarked'; ?>">
                        
                        <div class="member-info">
                            <div class="member-avatar-lg"><?php echo $initials; ?></div>
                            <div class="member-details">
                                <h5><?php echo htmlspecialchars($member['name'] ?? ''); ?></h5>
                                <div class="member-department"><?php echo htmlspecialchars($member['department_name'] ?? 'No Department'); ?></div>
                            </div>
                        </div>

                        <div class="attendance-buttons">
                            <?php if ($member['attendance_status'] === 'present'): ?>
                                <button type="button" class="attendance-btn btn-present marked" 
                                        onclick="markAttendance(<?php echo $member['id']; ?>, 'unmark', this)">
                                    <i class="bi bi-check-circle-fill"></i> Present ✓
                                </button>
                            <?php else: ?>
                                <button type="button" class="attendance-btn btn-present" 
                                        onclick="markAttendance(<?php echo $member['id']; ?>, 'mark', this)">
                                    <i class="bi bi-circle"></i> Mark Present
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Real-time Attendance Summary -->
        <div class="attendance-summary">
            <h3><i class="bi bi-bar-chart"></i> Session Summary</h3>
            <div class="summary-stats">
                <div class="summary-card summary-present">
                    <div class="summary-number" id="presentCount"><?php echo $attendance_summary['present']; ?></div>
                    <div class="summary-label">Present</div>
                </div>
                <div class="summary-card summary-absent">
                    <div class="summary-number" id="absentCount"><?php echo $attendance_summary['absent']; ?></div>
                    <div class="summary-label">Absent</div>
                </div>
                <div class="summary-card summary-total">
                    <div class="summary-number" id="totalCount"><?php echo $attendance_summary['total']; ?></div>
                    <div class="summary-label">Total Members</div>
                </div>
                <div class="summary-card summary-percentage">
                    <div class="summary-number" id="percentageCount"><?php echo $attendance_summary['percentage']; ?>%</div>
                    <div class="summary-label">Attendance Rate</div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-track">
                    <div id="progressBar" class="progress-fill" data-width="<?php echo $attendance_summary['percentage']; ?>"></div>
                </div>
                <small class="text-muted mt-1 d-block">Real-time attendance progress</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
const selectedSessionId = <?php echo json_encode($selected_session_id); ?>;

function selectSession(sessionId) {
    window.location.href = `mark.php?session_id=${sessionId}`;
}

function markAttendance(memberId, action, button) {
    const formData = new FormData();
    formData.append('mark_attendance', '1');
    formData.append('member_id', memberId);
    formData.append('session_id', selectedSessionId);
    formData.append('action', action);
    
    // Show loading state
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
    button.disabled = true;
    
    fetch('mark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the card and button appearance
            const card = button.closest('.member-attendance-card');
            
            if (action === 'mark') {
                card.className = 'member-attendance-card present';
                card.setAttribute('data-status', 'present');
                button.innerHTML = '<i class="bi bi-check-circle-fill"></i> Present ✓';
                button.className = 'attendance-btn btn-present marked';
                button.setAttribute('onclick', `markAttendance(${memberId}, 'unmark', this)`);
                updateSummaryCount('mark');
            } else {
                card.className = 'member-attendance-card';
                card.setAttribute('data-status', 'unmarked');
                button.innerHTML = '<i class="bi bi-circle"></i> Mark Present';
                button.className = 'attendance-btn btn-present';
                button.setAttribute('onclick', `markAttendance(${memberId}, 'mark', this)`);
                updateSummaryCount('unmark');
            }
            
            // Success animation
            button.style.transform = 'scale(0.95)';
            setTimeout(() => {
                button.style.transform = 'scale(1)';
                button.disabled = false;
            }, 200);
        } else {
            button.innerHTML = originalText;
            button.disabled = false;
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalText;
        button.disabled = false;
        alert('Network error. Please try again.');
    });
}

function updateSummaryCount(action) {
    const presentCount = document.getElementById('presentCount');
    const absentCount = document.getElementById('absentCount');
    const totalCount = document.getElementById('totalCount');
    const percentageCount = document.getElementById('percentageCount');
    const progressBar = document.getElementById('progressBar');
    
    let present = parseInt(presentCount.textContent);
    let absent = parseInt(absentCount.textContent);
    const total = parseInt(totalCount.textContent);
    
    if (action === 'mark') {
        present++;
        absent--;
    } else if (action === 'unmark') {
        present--;
        absent++;
    }
    
    // Update display
    presentCount.textContent = present;
    absentCount.textContent = absent;
    
    // Update percentage and progress bar
    const percentage = total > 0 ? Math.round((present / total) * 100) : 0;
    percentageCount.textContent = percentage + '%';
    progressBar.style.width = percentage + '%';
    
    // Add animation to the updated numbers
    [presentCount, absentCount, percentageCount].forEach(element => {
        element.style.transform = 'scale(1.2)';
        element.style.color = '#007bff';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.color = '';
        }, 300);
    });
}

function markAllPresent() {
    const cards = document.querySelectorAll('.member-attendance-card[data-status="unmarked"], .member-attendance-card:not([data-status])');
    cards.forEach(card => {
        const memberId = card.getAttribute('data-member-id');
        const presentBtn = card.querySelector('.btn-present:not(.marked)');
        if (presentBtn) {
            markAttendance(memberId, 'mark', presentBtn);
        }
    });
}

// Search functionality
document.getElementById('searchMembers').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.member-attendance-card');
    
    cards.forEach(card => {
        const memberName = card.getAttribute('data-member-name');
        if (memberName.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

// Filter functionality
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const filterStatus = this.getAttribute('data-status');
        const cards = document.querySelectorAll('.member-attendance-card');
        
        cards.forEach(card => {
            const cardStatus = card.getAttribute('data-status');
            if (filterStatus === 'all' || cardStatus === filterStatus) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>