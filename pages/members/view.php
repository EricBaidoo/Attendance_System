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

// Get member ID
$member_id = $_GET['id'] ?? null;
if (!$member_id) {
    header('Location: list.php');
    exit;
}

// Database connection
try {
    require '../../config/database.php';
    
    // Get member details
    $stmt = $pdo->prepare("SELECT m.*, d.name AS department_name 
                          FROM members m 
                          LEFT JOIN departments d ON m.department_id = d.id 
                          WHERE m.id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        header('Location: list.php?error=Member not found');
        exit;
    }
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "View Member - {$member['name']}";
$page_header = true;
$page_icon = "bi bi-person";
$page_heading = "Member Details";
$page_description = "View member information and details";
$page_actions = '<a href="edit.php?id=' . $member['id'] . '" class="btn btn-warning"><i class="bi bi-pencil"></i> Edit Member</a>
                <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../includes/header.php';
?>

<!-- Member Details Card -->
<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-person-circle text-primary me-2"></i>Personal Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Full Name</label>
                        <div class="h6"><?php echo htmlspecialchars($member['name']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Gender</label>
                        <div class="h6"><?php echo ucfirst($member['gender'] ?? 'Not specified'); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Date of Birth</label>
                        <div class="h6"><?php echo $member['dob'] ? date('F d, Y', strtotime($member['dob'])) : 'Not provided'; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Phone</label>
                        <div class="h6"><?php echo htmlspecialchars($member['phone'] ?? 'Not provided'); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Email</label>
                        <div class="h6"><?php echo htmlspecialchars($member['email'] ?? 'Not provided'); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold text-muted">Location</label>
                        <div class="h6"><?php echo htmlspecialchars($member['location'] ?? 'Not specified'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Status Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-shield-check text-success me-2"></i>Status Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Member Status</label>
                    <div>
                        <?php 
                        $status_class = $member['status'] == 'active' ? 'bg-success' : 'bg-danger';
                        echo '<span class="badge ' . $status_class . ' fs-6">' . ucfirst($member['status']) . '</span>';
                        ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Congregation Group</label>
                    <div>
                        <?php 
                        $group = $member['congregation_group'] ?? 'Adult';
                        $group_colors = [
                            'Adult' => 'bg-primary',
                            'Youth' => 'bg-success', 
                            'Teen' => 'bg-info',
                            'Children' => 'bg-warning'
                        ];
                        $color_class = $group_colors[$group] ?? 'bg-secondary';
                        echo '<span class="badge ' . $color_class . ' fs-6">' . htmlspecialchars($group) . '</span>';
                        ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Baptized</label>
                    <div>
                        <?php 
                        if ($member['baptized'] == 'yes') {
                            echo '<span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Yes</span>';
                        } else {
                            echo '<span class="badge bg-secondary fs-6">No</span>';
                        }
                        ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Department</label>
                    <div class="h6"><?php echo htmlspecialchars($member['department_name'] ?? 'Not assigned'); ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Date Joined</label>
                    <div class="h6"><?php echo $member['date_joined'] ? date('F d, Y', strtotime($member['date_joined'])) : 'Not recorded'; ?></div>
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
                    <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil me-2"></i>Edit Details
                    </a>
                    
                    <?php if ($member['status'] == 'active'): ?>
                    <button class="btn btn-outline-danger" onclick="changeStatus(<?php echo $member['id']; ?>, 'inactive')">
                        <i class="bi bi-pause-circle me-2"></i>Set Inactive
                    </button>
                    <?php else: ?>
                    <button class="btn btn-outline-success" onclick="changeStatus(<?php echo $member['id']; ?>, 'active')">
                        <i class="bi bi-play-circle me-2"></i>Set Active
                    </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-danger" onclick="deleteMember(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>')">
                        <i class="bi bi-trash me-2"></i>Delete Member
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changeStatus(memberId, newStatus) {
    if (confirm('Are you sure you want to change this member\'s status to ' + newStatus + '?')) {
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                member_id: memberId,
                status: newStatus
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
            alert('Error updating status: ' + error);
        });
    }
}

function deleteMember(memberId, memberName) {
    if (confirm('Are you sure you want to delete ' + memberName + '? This action cannot be undone.')) {
        // Implement delete functionality
        alert('Delete functionality will be implemented here.');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>