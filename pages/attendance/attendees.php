<?php
// pages/attendance/attendees.php - View complete attendee list for a session
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Database connection
require '../../config/database.php';
require '../../includes/attendance_utils.php';

$session_id = $_GET['session_id'] ?? '';
$attendee_data = null;
$error = '';

if ($session_id) {
    $attendee_data = getSessionAttendeeList($pdo, $session_id);
    if (!$attendee_data) {
        $error = 'Session not found or no data available.';
    }
} else {
    $error = 'No session specified.';
}

$page_title = "Attendee List - Bridge Ministries International";
?>
<?php include '../../includes/header.php'; ?>

<link href="../../assets/css/attendance-view.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Session Attendee List</h1>
                <div>
                    <a href="view.php?session_id=<?php echo htmlspecialchars($session_id); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Attendance View
                    </a>
                    <?php if ($attendee_data): ?>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print List
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($attendee_data): ?>
                <!-- Session Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Session Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Service:</strong> <?php echo htmlspecialchars($attendee_data['session']['service_name']); ?></p>
                                <p><strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($attendee_data['session']['session_date'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge badge-<?php echo $attendee_data['session']['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($attendee_data['session']['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total Attendees:</strong> <?php echo $attendee_data['summary']['total_attendees']; ?></p>
                                <p><strong>Members:</strong> <?php echo $attendee_data['summary']['total_members']; ?> 
                                   (Present: <?php echo $attendee_data['summary']['members_present']; ?>, 
                                    Absent: <?php echo $attendee_data['summary']['members_absent']; ?>, 
                                    Late: <?php echo $attendee_data['summary']['members_late']; ?>)</p>
                                <p><strong>Visitors:</strong> <?php echo $attendee_data['summary']['total_visitors']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendee List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users"></i> Complete Attendee List
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Method</th>
                                        <th>Additional Info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $count = 1;
                                    foreach ($attendee_data['attendees'] as $attendee): 
                                    ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $attendee['attendee_type'] === 'MEMBER' ? 'primary' : 'info'; ?>">
                                                <?php echo htmlspecialchars($attendee['attendee_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($attendee['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($attendee['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($attendee['email'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $status = $attendee['status'];
                                            $badge_class = match($status) {
                                                'present' => 'success',
                                                'absent' => 'danger', 
                                                'late' => 'warning',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge badge-<?php echo $badge_class; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo ucfirst(str_replace('_', ' ', $attendee['method'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                if ($attendee['attendee_type'] === 'MEMBER') {
                                                    echo isset($attendee['congregation_group']) ? $attendee['congregation_group'] : 'N/A';
                                                } else {
                                                    $info = [];
                                                    if (isset($attendee['gender'])) $info[] = ucfirst($attendee['gender']);
                                                    if (isset($attendee['age_group'])) $info[] = ucfirst($attendee['age_group']);
                                                    echo implode(', ', $info) ?: 'N/A';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="card mt-4 d-print-none">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-download"></i> Export Options
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <button onclick="exportToCSV()" class="btn btn-success btn-block">
                                    <i class="fas fa-file-csv"></i> Export to CSV
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button onclick="window.print()" class="btn btn-primary btn-block">
                                    <i class="fas fa-print"></i> Print List
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button onclick="copyToClipboard()" class="btn btn-info btn-block">
                                    <i class="fas fa-copy"></i> Copy to Clipboard
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Export to CSV function
function exportToCSV() {
    const table = document.querySelector('table');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    const csvContent = rows.map(row => {
        const cols = Array.from(row.querySelectorAll('th, td'));
        return cols.map(col => {
            // Clean up the text content
            const text = col.textContent.trim().replace(/\s+/g, ' ');
            // Escape commas and quotes for CSV
            return `"${text.replace(/"/g, '""')}"`;
        }).join(',');
    }).join('\n');
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'attendee_list_<?php echo $session_id; ?>_<?php echo date('Y-m-d'); ?>.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Copy to clipboard function
function copyToClipboard() {
    const table = document.querySelector('table');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    const textContent = rows.map(row => {
        const cols = Array.from(row.querySelectorAll('th, td'));
        return cols.map(col => col.textContent.trim()).join('\t');
    }).join('\n');
    
    navigator.clipboard.writeText(textContent).then(() => {
        alert('Attendee list copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard. Please try manually selecting the text.');
    });
}

// Print styles
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        @media print {
            .d-print-none { display: none !important; }
            .table { font-size: 12px; }
            .card { border: none !important; box-shadow: none !important; }
            body { background: white !important; }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include '../../includes/footer.php'; ?>