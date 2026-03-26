<?php
require_once '../../../includes/security.php';

requireLogin('../../../login.php');
$user_role = getUserRole();

// Get visitor ID
$visitor_id = $_GET['id'] ?? null;
if (!$visitor_id) {
    header('Location: list.php');
    exit;
}

// Database connection
try {
    require '../../../config/database.php';
    
    // Get visitor details
    $stmt = $pdo->prepare("SELECT vr.*, p.full_name AS name, p.email, p.phone, s.name AS service_name 
                          FROM visitor_roles vr 
                          JOIN people p ON vr.person_id = p.id
                          LEFT JOIN services s ON vr.service_id = s.id 
                          WHERE vr.id = ?");
    $stmt->execute([$visitor_id]);
    $visitor = $stmt->fetch();
    
    if (!$visitor) {
        header('Location: list.php?error=Visitor not found');
        exit;
    }

    $linked_convert = null;
    $linked_member = null;

    $linked_convert_stmt = $pdo->prepare("SELECT * FROM new_convert_roles WHERE person_id = ? ORDER BY date_converted DESC, id DESC LIMIT 1");
    $linked_convert_stmt->execute([$visitor['person_id']]);
    $linked_convert = $linked_convert_stmt->fetch();

    $member_conditions = [];
    $member_params = [];

    if (!empty($linked_convert['phone'])) {
        $member_conditions[] = "p.phone = ?";
        $member_params[] = $linked_convert['phone'];
    }
    if (!empty($linked_convert['email'])) {
        $member_conditions[] = "p.email = ?";
        $member_params[] = $linked_convert['email'];
    }

    if (empty($member_conditions)) {
        if (!empty($visitor['phone'])) {
            $member_conditions[] = "p.phone = ?";
            $member_params[] = $visitor['phone'];
        }
        if (!empty($visitor['email'])) {
            $member_conditions[] = "p.email = ?";
            $member_params[] = $visitor['email'];
        }
    }

    if (!empty($member_conditions)) {
        $linked_member_sql = "SELECT mr.id, p.full_name AS name, mr.date_joined, mr.status FROM member_roles mr JOIN people p ON mr.person_id = p.id WHERE " . implode(' OR ', $member_conditions) . " ORDER BY mr.id DESC LIMIT 1";
        $linked_member_stmt = $pdo->prepare($linked_member_sql);
        $linked_member_stmt->execute($member_params);
        $linked_member = $linked_member_stmt->fetch();
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

include '../../../includes/header.php';
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
                        <?php if (($visitor['became_member'] ?? 'no') == 'yes' || !empty($linked_member)): ?>
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

                <div class="mb-0">
                    <label class="form-label fw-semibold text-muted">Journey Stage</label>
                    <div class="d-flex flex-column gap-2">
                        <span class="badge bg-primary fs-6 text-start"><i class="bi bi-1-circle me-1"></i>Visitor (#<?php echo (int)$visitor['id']; ?>)</span>
                        <?php if (!empty($linked_convert)): ?>
                            <span class="badge bg-success fs-6 text-start"><i class="bi bi-2-circle me-1"></i>New Convert (#<?php echo (int)$linked_convert['id']; ?>)</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark fs-6 text-start"><i class="bi bi-2-circle me-1"></i>New Convert (not yet)</span>
                        <?php endif; ?>
                        <?php if (!empty($linked_member)): ?>
                            <span class="badge bg-info fs-6 text-start"><i class="bi bi-3-circle me-1"></i>Member (#<?php echo (int)$linked_member['id']; ?>)</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark fs-6 text-start"><i class="bi bi-3-circle me-1"></i>Member (not yet)</span>
                        <?php endif; ?>
                    </div>
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
                    
                    <?php if (empty($linked_convert)): ?>
                    <button class="btn btn-primary" onclick="startConvertJourney(<?php echo $visitor['id']; ?>, '<?php echo htmlspecialchars($visitor['name']); ?>')">
                        <i class="bi bi-person-plus me-2"></i>Start Convert Journey
                    </button>
                    <?php endif; ?>

                    <?php if (!empty($linked_convert) && empty($linked_member)): ?>
                    <a href="convert.php?id=<?php echo $visitor['id']; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-up-circle me-2"></i>Continue Conversion
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($linked_member)): ?>
                    <a href="../members/view.php?id=<?php echo (int)$linked_member['id']; ?>" class="btn btn-outline-success">
                        <i class="bi bi-person-check me-2"></i>View Member Record
                    </a>
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

function startConvertJourney(visitorId, visitorName) {
    if (confirm('Start conversion journey for ' + visitorName + '? This will move the visitor to New Convert for doctrine training.')) {
        window.location.href = 'convert.php?id=' + visitorId;
    }
}

function deleteVisitor(visitorId, visitorName) {
    if (confirm('Are you sure you want to delete ' + visitorName + '? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ visitor_id: visitorId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'list.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error deleting visitor: ' + error);
        });
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>