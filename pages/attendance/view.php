<?php
// pages/attendance/view.php
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
$selected_session = null;
$attendance_records = [];
$attendance_stats = ['total_members' => 0, 'present' => 0, 'absent' => 0, 'not_marked' => 0, 'percentage' => 0];

if ($session_id) {
    // Get session details with service information
    $session_sql = "SELECT ss.*, s.name as service_name, s.description,
                    u1.username as opened_by_user, u2.username as closed_by_user
                    FROM service_sessions ss
                    JOIN services s ON ss.service_id = s.id
                    LEFT JOIN users u1 ON ss.opened_by = u1.id
                    LEFT JOIN users u2 ON ss.closed_by = u2.id
                    WHERE ss.id = ?";
    $session_stmt = $pdo->prepare($session_sql);
    $session_stmt->execute([$session_id]);
    $selected_session = $session_stmt->fetch();
    
    if ($selected_session) {
        // Get attendance records with member details
        $attendance_sql = "SELECT m.id as member_id, m.name, m.phone, m.email, d.name as department_name,
                          a.status, u.username as marked_by, a.method
                          FROM members m
                          LEFT JOIN departments d ON m.department_id = d.id
                          LEFT JOIN attendance a ON m.id = a.member_id AND a.session_id = ?
                          LEFT JOIN users u ON a.marked_by = u.id
                          WHERE m.status = 'active'
                          ORDER BY m.name";
        $attendance_stmt = $pdo->prepare($attendance_sql);
        $attendance_stmt->execute([$session_id]);
        $attendance_records = $attendance_stmt->fetchAll();
        
        // Calculate statistics
        $attendance_stats['total_members'] = count($attendance_records);
        foreach ($attendance_records as $record) {
            if ($record['status'] === 'present') {
                $attendance_stats['present']++;
            } elseif ($record['status'] === 'absent') {
                $attendance_stats['absent']++;
            } else {
                $attendance_stats['not_marked']++;
            }
        }
        
        $attendance_stats['percentage'] = $attendance_stats['total_members'] > 0 
            ? round(($attendance_stats['present'] / $attendance_stats['total_members']) * 100, 1) 
            : 0;
    }
}

$page_title = "Attendance Report - Bridge Ministries International";
?>
<?php include '../../includes/header.php'; ?>

<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/attendance-modern.css?v=<?php echo time(); ?>" rel="stylesheet">

<!-- Professional Attendance Report -->
<div class="container-fluid py-4">
    <?php if ($selected_session): ?>
    <!-- Report Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="feature-icon bg-gradient-info text-white rounded-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-graph-up fs-3"></i>
                        </div>
                        <div>
                            <h1 class="mb-1 fw-bold text-dark">Session Attendance Report</h1>
                            <h4 class="mb-2 text-primary"><?php echo htmlspecialchars($selected_session['service_name'] ?? ''); ?></h4>
                            <div class="d-flex gap-3 text-muted">
                                <span><i class="bi bi-calendar me-1"></i><?php echo date('l, F j, Y', strtotime($selected_session['session_date'])); ?></span>
                                <span><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($selected_session['location'] ?? ''); ?></span>
                                <span><i class="bi bi-clock me-1"></i>
                                    <?php echo date('g:i A', strtotime($selected_session['opened_at'])); ?>
                                    <?php if ($selected_session['closed_at']): ?>
                                        - <?php echo date('g:i A', strtotime($selected_session['closed_at'])); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex gap-2">
                        <?php if ($selected_session['status'] === 'open'): ?>
                            <a href="mark.php?session_id=<?php echo $session_id; ?>" class="btn btn-success">
                                <i class="bi bi-pencil me-1"></i> Edit Attendance
                            </a>
                        <?php endif; ?>
                        <a href="../services/sessions.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 bg-gradient-success text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-circle-fill fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $attendance_stats['present']; ?></h3>
                    <p class="mb-0 opacity-90">Members Present</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 bg-gradient-warning text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-dash-circle-fill fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $attendance_stats['not_marked']; ?></h3>
                    <p class="mb-0 opacity-90">Not Marked</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 bg-gradient-info text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-people-fill fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $attendance_stats['total_members']; ?></h3>
                    <p class="mb-0 opacity-90">Total Members</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100 bg-gradient-primary text-white">
                <div class="card-body text-center p-4">
                    <div class="feature-icon bg-light bg-opacity-20 text-white rounded-circle mx-auto mb-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-percent fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $attendance_stats['percentage']; ?>%</h3>
                    <p class="mb-0 opacity-90">Attendance Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Attendance Record -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-table text-primary me-2"></i>
                    Detailed Attendance Record
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="exportToCSV()">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="printReport()">
                        <i class="bi bi-printer me-1"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="attendanceTable">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Member Name</th>
                            <th class="fw-bold">Department</th>
                            <th class="fw-bold">Phone</th>
                            <th class="fw-bold">Email</th>
                            <th class="fw-bold">Status</th>
                            <th class="fw-bold">Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="member-avatar rounded-circle bg-gradient-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-weight: bold; font-size: 14px;">
                                        <?php echo strtoupper(substr($record['name'] ?? '', 0, 2)); ?>
                                    </div>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($record['name'] ?? ''); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($record['department_name'] ?? 'No Department'); ?></span>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($record['phone'] ?? '-'); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($record['email'] ?? '-'); ?></td>
                            <td>
                                <?php if ($record['status'] === 'present'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i> Present
                                    </span>
                                <?php elseif ($record['status'] === 'absent'): ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle me-1"></i> Absent
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-dash-circle me-1"></i> Not Marked
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['marked_by']): ?>
                                    <span class="text-dark fw-medium"><?php echo htmlspecialchars($record['marked_by'] ?? ''); ?></span>
                                    <br><small class="text-muted">via <?php echo htmlspecialchars($record['method'] ?? 'manual'); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- No Session Selected State -->
    <div class="text-center py-5">
        <div class="feature-icon bg-gradient-secondary text-white rounded-3 mx-auto mb-4" style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-calendar-x display-4"></i>
        </div>
        <h3 class="text-muted mb-3">No Session Selected</h3>
        <p class="text-muted mb-4">Please select a session to view the attendance report.</p>
        <a href="../services/sessions.php" class="btn btn-primary">
            <i class="bi bi-calendar me-2"></i> View Sessions
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
/* Gradient backgrounds */
.bg-gradient-success {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8, #007bff);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff, #6610f2);
}

.bg-gradient-secondary {
    background: linear-gradient(135deg, #6c757d, #495057);
}

/* Feature icon styling */
.feature-icon {
    transition: transform 0.3s ease;
}

.card:hover .feature-icon {
    transform: scale(1.1);
}

/* Member avatar styling */
.member-avatar {
    background: linear-gradient(135deg, #007bff, #6610f2) !important;
}

/* Table enhancements */
.table > :not(caption) > * > * {
    padding: 1rem 0.75rem;
}

.table-hover tbody tr:hover td {
    background-color: rgba(0, 123, 255, 0.05);
}

/* Card hover effects */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}

/* Print styles */
@media print {
    .btn, .card-header {
        display: none !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>

<script>
function exportToCSV() {
    const table = document.getElementById('attendanceTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Add session info as header
    csv.push(`"Session Attendance Report"`);
    csv.push(`"Service: <?php echo addslashes($selected_session['service_name'] ?? ''); ?>"`);
    csv.push(`"Date: <?php echo date('F j, Y', strtotime($selected_session['session_date'] ?? '')); ?>"`);
    csv.push(`"Location: <?php echo addslashes($selected_session['location'] ?? ''); ?>"`);
    csv.push('');
    
    // Add table headers
    const headerRow = rows[0];
    const headerCells = headerRow.querySelectorAll('th');
    let headerArray = [];
    headerCells.forEach(cell => {
        headerArray.push(`"${cell.textContent.trim()}"`);
    });
    csv.push(headerArray.join(','));
    
    // Add table data
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.querySelectorAll('td');
        let rowArray = [];
        
        cells.forEach(cell => {
            // Clean cell content
            let content = cell.textContent.trim();
            content = content.replace(/\n\s+/g, ' '); // Replace line breaks with space
            rowArray.push(`"${content}"`);
        });
        csv.push(rowArray.join(','));
    }
    
    // Download CSV
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = `attendance_report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

function printReport() {
    window.print();
}

// Initialize any tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Add any initialization code here
    console.log('Attendance report loaded successfully');
});
</script>