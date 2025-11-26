<?php
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

// Get visitor ID
$visitor_id = $_GET['id'] ?? null;
if (!$visitor_id) {
    header('Location: list.php');
    exit;
}

// Database connection
try {
    require '../../config/database.php';
    
    // Get visitor details
    $stmt = $pdo->prepare("SELECT v.*, s.name AS service_name 
                          FROM visitors v 
                          LEFT JOIN services s ON v.service_id = s.id 
                          WHERE v.id = ?");
    $stmt->execute([$visitor_id]);
    $visitor = $stmt->fetch();
    
    if (!$visitor) {
        header('Location: list.php?error=Visitor not found');
        exit;
    }
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "View Visitor - {$visitor['name']}";
$page_header = true;
$page_icon = "bi bi-person-badge";
$page_heading = "Visitor Details";
$page_description = "View visitor information and visit history";
$page_actions = '<a href="edit.php?id=' . $visitor['id'] . '" class="btn btn-warning"><i class="bi bi-pencil"></i> Edit Visitor</a>
                <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../includes/header.php';
?>

<!-- Visitor Details -->
<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-person-circle text-primary me-2"></i>Visitor Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Full Name</label>
                        <div class="h6"><?php echo htmlspecialchars($visitor['name']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Visit Type</label>
                        <div>
                            <?php if ($visitor['first_time'] == 'yes'): ?>
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-star-fill me-1"></i>First Time Visitor
                            </span>
                            <?php else: ?>
                            <span class="badge bg-info fs-6">
                                <i class="bi bi-arrow-repeat me-1"></i>Return Visitor
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Phone</label>
                        <div class="h6"><?php echo htmlspecialchars($visitor['phone'] ?? 'Not provided'); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Email</label>
                        <div class="h6"><?php echo htmlspecialchars($visitor['email'] ?? 'Not provided'); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Visit Date</label>
                        <div class="h6"><?php echo date('F d, Y', strtotime($visitor['date'])); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Service Attended</label>
                        <div class="h6"><?php echo htmlspecialchars($visitor['service_name'] ?? 'Unknown Service'); ?></div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold text-muted">How They Heard About Us</label>
                        <div class="h6"><?php echo htmlspecialchars($visitor['how_heard'] ?? 'Not specified'); ?></div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold text-muted">Referred/Invited By</label>
                        <div>
                            <?php if ($visitor['invited_by']): ?>
                            <?php 
                            $invited_by = $visitor['invited_by'];
                            if (strpos($invited_by, 'Member:') === 0) {
                                echo '<i class="bi bi-person-check text-success me-2"></i>';
                                echo '<span class="fw-medium">' . htmlspecialchars($invited_by) . '</span>';
                            } elseif (strpos($invited_by, 'Social Media:') === 0) {
                                echo '<i class="bi bi-share text-primary me-2"></i>';
                                echo '<span class="fw-medium">' . htmlspecialchars($invited_by) . '</span>';
                            } elseif ($invited_by === 'Website') {
                                echo '<i class="bi bi-globe text-info me-2"></i>';
                                echo '<span class="fw-medium">Found via Website</span>';
                            } elseif ($invited_by === 'Self-directed') {
                                echo '<i class="bi bi-person-walking text-secondary me-2"></i>';
                                echo '<span class="fw-medium">Came by themselves</span>';
                            } else {
                                echo '<i class="bi bi-info-circle text-warning me-2"></i>';
                                echo '<span class="fw-medium">' . htmlspecialchars($invited_by) . '</span>';
                            }
                            ?>
                            <?php else: ?>
                            <span class="text-muted">Not specified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($visitor['notes']): ?>
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-semibold text-muted">Notes</label>
                        <div class="p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($visitor['notes'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Status Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-clipboard-check text-success me-2"></i>Status Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Member Status</label>
                    <div>
                        <?php if (($visitor['became_member'] ?? 'no') == 'yes'): ?>
                        <span class="badge bg-primary fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i>Became Member
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary fs-6">Still Visitor</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Follow-up Status</label>
                    <div>
                        <?php if (($visitor['follow_up_needed'] ?? 'no') == 'yes'): ?>
                            <?php if (($visitor['follow_up_completed'] ?? 'no') == 'yes'): ?>
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle me-1"></i>Completed
                            </span>
                            <?php else: ?>
                            <span class="badge bg-warning fs-6">
                                <i class="bi bi-clock me-1"></i>Pending
                            </span>
                            <?php endif; ?>
                        <?php else: ?>
                        <span class="badge bg-light text-dark fs-6">Not Needed</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Date Added</label>
                    <div class="h6"><?php echo date('F d, Y', strtotime($visitor['created_at'] ?? $visitor['date'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-lightning text-warning me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="edit.php?id=<?php echo $visitor['id']; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil me-2"></i>Edit Details
                    </a>
                    
                    <?php if (($visitor['follow_up_needed'] ?? 'no') == 'yes' && ($visitor['follow_up_completed'] ?? 'no') == 'no'): ?>
                    <button class="btn btn-success" onclick="markFollowUpComplete(<?php echo $visitor['id']; ?>)">
                        <i class="bi bi-telephone me-2"></i>Mark Follow-up Complete
                    </button>
                    <?php endif; ?>
                    
                    <?php if (($visitor['became_member'] ?? 'no') != 'yes'): ?>
                    <button class="btn btn-primary" onclick="convertToMember(<?php echo $visitor['id']; ?>, '<?php echo htmlspecialchars($visitor['name']); ?>')">
                        <i class="bi bi-person-plus me-2"></i>Convert to Member
                    </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-danger" onclick="deleteVisitor(<?php echo $visitor['id']; ?>, '<?php echo htmlspecialchars($visitor['name']); ?>')">
                        <i class="bi bi-trash me-2"></i>Delete Visitor
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function markFollowUpComplete(visitorId) {
    if (confirm('Mark follow-up as completed for this visitor?')) {
        fetch('update_followup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                visitor_id: visitorId,
                action: 'complete'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error updating follow-up status: ' + error);
        });
    }
}

function convertToMember(visitorId, visitorName) {
    if (confirm('Convert ' + visitorName + ' to a church member? This will create a member record.')) {
        // Redirect to member creation with pre-filled data
        window.location.href = '../members/add.php?from_visitor=' + visitorId;
    }
}

function deleteVisitor(visitorId, visitorName) {
    if (confirm('Are you sure you want to delete ' + visitorName + '? This action cannot be undone.')) {
        // Implement delete functionality
        alert('Delete functionality will be implemented here.');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>