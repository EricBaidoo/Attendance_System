<?php
// pages/attendance/attendance.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'member';
if (!in_array($user_role, ['admin', 'staff'])) {
    header('Location: ../../index.php');
    exit;
}

// Database connection
try {
    require '../../config/database.php';
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

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
    
    // Calculate real-time summary
    $attendance_summary['total'] = count($members);
    foreach ($members as $member) {
        if ($member['attendance_status'] === 'present') {
            $attendance_summary['present']++;
        }
    }
    $attendance_summary['absent'] = $attendance_summary['total'] - $attendance_summary['present'];
    $attendance_summary['percentage'] = $attendance_summary['total'] > 0 ? round(($attendance_summary['present'] / $attendance_summary['total']) * 100) : 0;
}

$page_title = "Mark Attendance - Bridge Ministries International";
include '../../includes/header.php';
?>

<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/attendance-modern.css?v=<?php echo time(); ?>" rel="stylesheet">

<!-- Professional Attendance Dashboard -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center">
                        <div class="feature-icon bg-gradient-primary text-white rounded-3 me-3">
                            <i class="bi bi-clipboard-check fs-3"></i>
                        </div>
                        <div>
                            <h1 class="mb-1 fw-bold text-dark">Mark Attendance</h1>
                            <?php if ($selected_session): ?>
                                <p class="text-muted mb-0">
                                    <strong><?php echo htmlspecialchars($selected_session['service_name'] ?? ''); ?></strong> • 
                                    <?php echo date('F j, Y'); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted mb-0">No active sessions available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <?php if ($selected_session): ?>
                        <div class="d-flex align-items-center justify-content-end">
                            <div class="live-indicator me-3">
                                <span class="badge bg-success fs-6 px-3 py-2">
                                    <i class="bi bi-broadcast"></i> LIVE SESSION
                                </span>
                            </div>
                            <div class="text-end">
                                <div class="fs-2 fw-bold text-primary"><?php echo $attendance_summary['present']; ?></div>
                                <small class="text-muted">of <?php echo $attendance_summary['total']; ?> present</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <?php if ($selected_session): ?>
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-gradient-success text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3">
                        <i class="bi bi-people-fill fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1" id="presentCount"><?php echo $attendance_summary['present']; ?></h3>
                    <p class="mb-0 opacity-90">Members Present</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-gradient-warning text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3">
                        <i class="bi bi-person-x fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1" id="absentCount"><?php echo $attendance_summary['absent']; ?></h3>
                    <p class="mb-0 opacity-90">Members Absent</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-gradient-info text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3">
                        <i class="bi bi-calculator fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1" id="totalCount"><?php echo $attendance_summary['total']; ?></h3>
                    <p class="mb-0 opacity-90">Total Members</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-gradient-primary text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3">
                        <i class="bi bi-percent fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1" id="percentageCount"><?php echo $attendance_summary['percentage']; ?>%</h3>
                    <p class="mb-0 opacity-90">Attendance Rate</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success ?? ''); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error ?? ''); ?>
        </div>
    <?php endif; ?>

    <!-- Active Sessions Selection -->
    <?php if (!empty($sessions)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0">
                <i class="bi bi-broadcast text-success me-2"></i>
                Active Sessions Today
            </h5>
        </div>
        <div class="card-body p-4">
            <?php if (count($sessions) > 1): ?>
                <div class="row g-3">
                    <?php foreach ($sessions as $session): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100 session-card <?php echo ($selected_session_id == $session['id']) ? 'border-primary' : 'border-secondary'; ?> border-2" 
                                 style="cursor: pointer;" onclick="selectSession(<?php echo $session['id']; ?>)">
                                <div class="card-body text-center p-4">
                                    <div class="feature-icon bg-gradient-primary text-white rounded-3 mx-auto mb-3">
                                        <i class="bi bi-calendar-event fs-3"></i>
                                    </div>
                                    <h6 class="fw-bold"><?php echo htmlspecialchars($session['service_name'] ?? ''); ?></h6>
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-clock"></i> Started <?php echo date('g:i A', strtotime($session['opened_at'])); ?>
                                    </p>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($session['location'] ?? ''); ?>
                                    </p>
                                    <?php if ($selected_session_id == $session['id']): ?>
                                        <div class="badge bg-primary mt-2">Currently Selected</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <div class="feature-icon bg-gradient-primary text-white rounded-3 mx-auto mb-3">
                        <i class="bi bi-calendar-event fs-3"></i>
                    </div>
                    <h6><?php echo htmlspecialchars($sessions[0]['service_name'] ?? ''); ?></h6>
                    <p class="text-muted">
                        <i class="bi bi-clock"></i> Started <?php echo date('g:i A', strtotime($sessions[0]['opened_at'])); ?> • 
                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($sessions[0]['location'] ?? ''); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($selected_session && !empty($members)): ?>
    <!-- Attendance Controls -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-check text-primary me-2"></i>
                        Mark Attendance: <?php echo htmlspecialchars($selected_session['service_name'] ?? ''); ?>
                    </h5>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <button class="btn btn-success btn-sm" onclick="markAllPresent()">
                            <i class="bi bi-check-all me-1"></i>Mark All Present
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="window.location.href='view.php?session_id=<?php echo $selected_session_id; ?>'">
                            <i class="bi bi-eye me-1"></i>View Reports
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <!-- Search and Filter Controls -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchMembers" placeholder="Search members by name...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="statusFilter" id="filterAll" checked>
                        <label class="btn btn-outline-secondary" for="filterAll" data-status="all">All Members</label>
                        
                        <input type="radio" class="btn-check" name="statusFilter" id="filterPresent">
                        <label class="btn btn-outline-success" for="filterPresent" data-status="present">Present</label>
                        
                        <input type="radio" class="btn-check" name="statusFilter" id="filterAbsent">
                        <label class="btn btn-outline-warning" for="filterAbsent" data-status="unmarked">Not Marked</label>
                    </div>
                </div>
            </div>

            <!-- Members Grid -->
            <div class="row g-3" id="membersGrid">
                <?php 
                $current_department = '';
                foreach ($members as $member): 
                    $initials = strtoupper(substr($member['name'] ?? '', 0, 2));
                    $status_class = $member['attendance_status'] ?? 'unmarked';
                    $department_name = $member['department_name'] ?? 'No Department';
                    
                    // Department header (you could add this if needed)
                ?>
                <div class="col-lg-4 col-md-6 member-card" 
                     data-member-id="<?php echo $member['id']; ?>"
                     data-member-name="<?php echo strtolower($member['name'] ?? ''); ?>"
                     data-status="<?php echo $status_class; ?>">
                    
                    <div class="card h-100 border-0 shadow-sm <?php echo $status_class === 'present' ? 'border-success' : ''; ?> attendance-member-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="member-avatar rounded-circle bg-gradient-primary text-white d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px; font-weight: bold;">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($member['name'] ?? ''); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($department_name); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <?php if ($status_class === 'present'): ?>
                                    <button type="button" class="btn btn-success attendance-btn" 
                                            onclick="markAttendance(<?php echo $member['id']; ?>, 'unmark', this)">
                                        <i class="bi bi-check-circle-fill me-2"></i>Present
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-primary attendance-btn" 
                                            onclick="markAttendance(<?php echo $member['id']; ?>, 'mark', this)">
                                        <i class="bi bi-circle me-2"></i>Mark Present
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Real-time Progress -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0">
                <i class="bi bi-bar-chart text-info me-2"></i>
                Real-time Attendance Progress
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                             id="progressBar" 
                             style="width: <?php echo $attendance_summary['percentage']; ?>%">
                            <span class="fw-bold"><?php echo $attendance_summary['percentage']; ?>%</span>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <?php echo $attendance_summary['present']; ?> of <?php echo $attendance_summary['total']; ?> members marked present
                    </small>
                </div>
                <div class="col-md-4 text-center">
                    <div class="fs-3 fw-bold text-success" id="livePercentage"><?php echo $attendance_summary['percentage']; ?>%</div>
                    <small class="text-muted">Completion Rate</small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$selected_session || empty($members)): ?>
    <!-- No Active Session State -->
    <div class="text-center py-5">
        <div class="feature-icon bg-gradient-secondary text-white rounded-3 mx-auto mb-4" style="width: 100px; height: 100px;">
            <i class="bi bi-calendar-x display-4"></i>
        </div>
        <h3 class="text-muted mb-3">No Active Sessions</h3>
        <p class="text-muted mb-4">There are currently no open attendance sessions for today.</p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="../services/sessions.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Create New Session
            </a>
            <a href="../reports/report.php" class="btn btn-outline-info">
                <i class="bi bi-graph-up me-2"></i>View Reports
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const selectedSessionId = <?php echo json_encode($selected_session_id); ?>;

function selectSession(sessionId) {
    window.location.href = `attendance.php?session_id=${sessionId}`;
}

function markAttendance(memberId, action, button) {
    const formData = new FormData();
    formData.append('mark_attendance', '1');
    formData.append('member_id', memberId);
    formData.append('session_id', selectedSessionId);
    formData.append('action', action);
    
    // Show loading state
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
    button.disabled = true;
    
    fetch('attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const card = button.closest('.member-card');
            
            if (action === 'mark') {
                card.setAttribute('data-status', 'present');
                card.querySelector('.attendance-member-card').classList.add('border-success');
                button.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Present';
                button.className = 'btn btn-success attendance-btn';
                button.setAttribute('onclick', `markAttendance(${memberId}, 'unmark', this)`);
                updateSummaryCount('mark');
            } else {
                card.setAttribute('data-status', 'unmarked');
                card.querySelector('.attendance-member-card').classList.remove('border-success');
                button.innerHTML = '<i class="bi bi-circle me-2"></i>Mark Present';
                button.className = 'btn btn-outline-primary attendance-btn';
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
            button.innerHTML = originalHTML;
            button.disabled = false;
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalHTML;
        button.disabled = false;
        alert('Network error. Please try again.');
    });
}

function updateSummaryCount(action) {
    const presentCount = document.getElementById('presentCount');
    const absentCount = document.getElementById('absentCount');
    const percentageCount = document.getElementById('percentageCount');
    const livePercentage = document.getElementById('livePercentage');
    const progressBar = document.getElementById('progressBar');
    
    let present = parseInt(presentCount.textContent);
    let absent = parseInt(absentCount.textContent);
    const total = present + absent;
    
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
    
    // Update percentage
    const percentage = total > 0 ? Math.round((present / total) * 100) : 0;
    percentageCount.textContent = percentage + '%';
    livePercentage.textContent = percentage + '%';
    progressBar.style.width = percentage + '%';
    progressBar.innerHTML = `<span class="fw-bold">${percentage}%</span>`;
    
    // Add animation
    [presentCount, absentCount, percentageCount, livePercentage].forEach(element => {
        element.style.transform = 'scale(1.2)';
        element.style.transition = 'transform 0.2s';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 200);
    });
}

function markAllPresent() {
    const unmarkedCards = document.querySelectorAll('.member-card[data-status="unmarked"]');
    if (unmarkedCards.length === 0) {
        alert('All members are already marked present!');
        return;
    }
    
    if (!confirm(`Mark all ${unmarkedCards.length} remaining members as present?`)) {
        return;
    }
    
    unmarkedCards.forEach(card => {
        const memberId = card.getAttribute('data-member-id');
        const presentBtn = card.querySelector('.btn-outline-primary');
        if (presentBtn) {
            setTimeout(() => {
                markAttendance(memberId, 'mark', presentBtn);
            }, Math.random() * 1000); // Stagger the requests
        }
    });
}

// Search functionality
document.getElementById('searchMembers').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.member-card');
    
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
document.querySelectorAll('label[data-status]').forEach(label => {
    label.addEventListener('click', function() {
        const filterStatus = this.getAttribute('data-status');
        const cards = document.querySelectorAll('.member-card');
        
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

// Real-time updates every 30 seconds
setInterval(function() {
    if (selectedSessionId) {
        // You could implement auto-refresh of attendance data here
        console.log('Auto-refresh attendance data...');
    }
}, 30000);
</script>

<style>
/* Additional styling for attendance page */
.feature-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.session-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.session-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important;
}

.member-avatar {
    font-size: 18px;
}

.attendance-member-card {
    transition: all 0.3s ease;
}

.attendance-member-card:hover {
    transform: translateY(-2px);
}

.attendance-btn {
    transition: all 0.2s ease;
}

.attendance-btn:hover {
    transform: scale(1.05);
}

.progress-bar {
    transition: width 0.5s ease-in-out;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.live-indicator .badge {
    animation: pulse 2s infinite;
}
</style>

<?php include '../../includes/footer.php'; ?>