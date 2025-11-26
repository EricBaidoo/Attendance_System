<?php
// pages/attendance/view.php
session_start();
require __DIR__ . '/../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/css/attendance.css" rel="stylesheet">
    <link href="../../assets/css/attendance-view.css" rel="stylesheet">

</head>
<body>
<?php include '../../includes/header.php'; ?>

<div class="report-container">
    <div class="container">
        <?php if ($selected_session): ?>
        <div class="report-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="bi bi-graph-up"></i> Session Attendance Report</h1>
                        <h3><?php echo htmlspecialchars($selected_session['service_name']); ?></h3>
                        <p><i class="bi bi-calendar"></i> <?php echo date('l, F j, Y', strtotime($selected_session['session_date'])); ?></p>
                        <p><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($selected_session['location']); ?></p>
                        <p><i class="bi bi-clock"></i> 
                            Session: <?php echo date('g:i A', strtotime($selected_session['opened_at'])); ?>
                            <?php if ($selected_session['closed_at']): ?>
                                - <?php echo date('g:i A', strtotime($selected_session['closed_at'])); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($selected_session['status'] === 'open'): ?>
                            <a href="mark.php?session_id=<?php echo $session_id; ?>" class="btn btn-light">
                                <i class="bi bi-pencil"></i> Edit Attendance
                            </a>
                        <?php endif; ?>
                        <a href="../services/sessions.php" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left"></i> Back to Sessions
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-overview">
            <h3><i class="bi bi-bar-chart"></i> Attendance Overview</h3>
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="percentage-circle"></div>
                    <h5>Overall Attendance</h5>
                </div>
                <div class="col-md-9">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="summary-card summary-present text-center">
                                <div class="summary-number"><?php echo $attendance_stats['present']; ?></div>
                                <div class="summary-label">Present</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-card text-center" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.2)); color: #dc3545;">
                                <div class="summary-number"><?php echo $attendance_stats['absent']; ?></div>
                                <div class="summary-label">Absent</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(155, 89, 182, 0.1)); padding: 1rem; border-radius: 15px;">
                                    <div style="font-size: 2rem; font-weight: 700; color: #2c3e50;"><?php echo $attendance_stats['total_members']; ?></div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 1px;">Total Members</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="attendance-table">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3><i class="bi bi-table"></i> Detailed Attendance Record</h3>
                <div>
                    <button class="btn btn-success btn-sm" onclick="exportToCSV()">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="printReport()">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped" id="attendanceTable">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['name']); ?></td>
                            <td>
                                <span class="department-badge"><?php echo htmlspecialchars($record['department_name']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($record['phone']); ?></td>
                            <td><?php echo htmlspecialchars($record['email']); ?></td>
                            <td>
                                <?php if ($record['status'] === 'present'): ?>
                                    <span class="status-badge status-active">
                                        <i class="bi bi-check-circle"></i> Present
                                    </span>
                                <?php elseif ($record['status'] === 'absent'): ?>
                                    <span class="status-badge" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                        <i class="bi bi-x-circle"></i> Absent
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge" style="background-color: rgba(108, 117, 125, 0.1); color: #6c757d;">
                                        <i class="bi bi-dash-circle"></i> Not Marked
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($record['marked_by'] ?? '-'); ?>
                                <?php if ($record['marked_by']): ?>
                                    <br><small class="text-muted">By: <?php echo htmlspecialchars($record['marked_by']); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php else: ?>
        <div class="text-center" style="padding: 4rem;">
            <i class="bi bi-calendar-x" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
            <h3 style="color: #6c757d; margin-top: 1rem;">No Session Selected</h3>
            <p style="color: #95a5a6;">Please select a session to view attendance report.</p>
            <a href="../services/sessions.php" class="btn btn-primary">
                <i class="bi bi-calendar"></i> View Sessions
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function exportToCSV() {
    const table = document.getElementById('attendanceTable');
    const rows = table.querySelectorAll('tr');
    const csvContent = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = Array.from(cells).map(cell => {
            return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
        });
        csvContent.push(rowData.join(','));
    });
    
    const csvString = csvContent.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `attendance_report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function printReport() {
    window.print();
}

// Print styles
const printStyles = `
    @media print {
        .navbar, .btn, footer { display: none !important; }
        .report-container { background: white !important; }
        .report-header, .stats-overview, .attendance-table { 
            box-shadow: none !important; 
            border: 1px solid #ddd !important;
            margin-bottom: 1rem !important;
        }
        .page-break { page-break-before: always; }
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);
</script>
</body>
</html>