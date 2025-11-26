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
    
    // Handle form submission
    if ($_POST) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $service_id = $_POST['service_id'];
        $first_time = $_POST['first_time'];
        $how_heard = trim($_POST['how_heard']);
        $invited_by_type = $_POST['invited_by_type'];
        $invited_by_details = trim($_POST['invited_by_details']);
        $follow_up_needed = $_POST['follow_up_needed'];
        $follow_up_completed = $_POST['follow_up_completed'] ?? 'no';
        $became_member = $_POST['became_member'] ?? 'no';
        $notes = trim($_POST['notes']);
        
        // Format the invited_by field
        $invited_by = '';
        if ($invited_by_type) {
            switch ($invited_by_type) {
                case 'member':
                    $invited_by = 'Member: ' . $invited_by_details;
                    break;
                case 'social_media':
                    $invited_by = 'Social Media: ' . ($invited_by_details ?: 'Unspecified');
                    break;
                case 'website':
                    $invited_by = 'Website';
                    break;
                case 'self':
                    $invited_by = 'Self-directed';
                    break;
                case 'other':
                    $invited_by = 'Other: ' . ($invited_by_details ?: 'Unspecified');
                    break;
            }
        }
        
        $update_stmt = $pdo->prepare(
            "UPDATE visitors SET 
             name = ?, email = ?, phone = ?, service_id = ?, first_time = ?, 
             how_heard = ?, invited_by = ?, follow_up_needed = ?, 
             follow_up_completed = ?, became_member = ?, notes = ?
             WHERE id = ?"
        );
        
        if ($update_stmt->execute([
            $name, $email, $phone, $service_id, $first_time,
            $how_heard, $invited_by, $follow_up_needed,
            $follow_up_completed, $became_member, $notes, $visitor_id
        ])) {
            header('Location: view.php?id=' . $visitor_id . '&success=Visitor updated successfully');
            exit;
        } else {
            $error = "Failed to update visitor";
        }
    }
    
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
    
    // Parse invited_by for form population
    $invited_by_type = '';
    $invited_by_details = '';
    if ($visitor['invited_by']) {
        if (strpos($visitor['invited_by'], 'Member:') === 0) {
            $invited_by_type = 'member';
            $invited_by_details = str_replace('Member: ', '', $visitor['invited_by']);
        } elseif (strpos($visitor['invited_by'], 'Social Media:') === 0) {
            $invited_by_type = 'social_media';
            $invited_by_details = str_replace('Social Media: ', '', $visitor['invited_by']);
        } elseif ($visitor['invited_by'] === 'Website') {
            $invited_by_type = 'website';
        } elseif ($visitor['invited_by'] === 'Self-directed') {
            $invited_by_type = 'self';
        } else {
            $invited_by_type = 'other';
            $invited_by_details = str_replace('Other: ', '', $visitor['invited_by']);
        }
    }
    
    // Get services
    $services_stmt = $pdo->query("
        SELECT s.*, ss.session_date, ss.status as session_status 
        FROM services s 
        LEFT JOIN service_sessions ss ON s.id = ss.service_id 
        ORDER BY COALESCE(ss.session_date, s.created_at) DESC, s.name
    ");
    $services = $services_stmt->fetchAll();
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "Edit Visitor - {$visitor['name']}";
$page_header = true;
$page_icon = "bi bi-pencil";
$page_heading = "Edit Visitor";
$page_description = "Update visitor information and details";
$page_actions = '<a href="view.php?id=' . $visitor['id'] . '" class="btn btn-secondary"><i class="bi bi-eye"></i> View Visitor</a>
                <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/visitors.css">

<style>
.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.15);
    background-color: #fff;
}

.section-divider {
    height: 3px;
    background: linear-gradient(90deg, #6f42c1, #20c997, #fd7e14);
    border: none;
    border-radius: 3px;
    margin: 2rem 0;
}
</style>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Edit Visitor Form -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0"><i class="bi bi-pencil text-warning me-2"></i>Edit Visitor Information</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <!-- Personal Information -->
                <div class="col-lg-6">
                    <h6 class="text-muted mb-3"><i class="bi bi-person me-2"></i>Personal Information</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Full Name *</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo htmlspecialchars($visitor['name']); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($visitor['email']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">Phone</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($visitor['phone']); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Visit Information -->
                <div class="col-lg-6">
                    <h6 class="text-muted mb-3"><i class="bi bi-calendar-event me-2"></i>Visit Information</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Service</label>
                        <select class="form-select" name="service_id">
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" 
                                        <?php echo ($visitor['service_id'] == $service['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?>
                                    <?php if ($service['date']): ?>
                                        - <?php echo date('M d, Y', strtotime($service['date'])); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">Visit Type</label>
                            <select class="form-select" name="first_time">
                                <option value="yes" <?php echo ($visitor['first_time'] == 'yes') ? 'selected' : ''; ?>>First Time</option>
                                <option value="no" <?php echo ($visitor['first_time'] == 'no') ? 'selected' : ''; ?>>Return Visitor</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">How Heard</label>
                            <select class="form-select" name="how_heard">
                                <option value="">Select Option</option>
                                <option value="friend" <?php echo ($visitor['how_heard'] == 'friend') ? 'selected' : ''; ?>>Friend/Family</option>
                                <option value="social_media" <?php echo ($visitor['how_heard'] == 'social_media') ? 'selected' : ''; ?>>Social Media</option>
                                <option value="website" <?php echo ($visitor['how_heard'] == 'website') ? 'selected' : ''; ?>>Website</option>
                                <option value="other" <?php echo ($visitor['how_heard'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="section-divider">
            
            <!-- Referral Information -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium">Referred/Invited By</label>
                    <select class="form-select" name="invited_by_type" id="invited_by_type" onchange="toggleInvitedByInput()">
                        <option value="">Select Option</option>
                        <option value="member" <?php echo ($invited_by_type == 'member') ? 'selected' : ''; ?>>Church Member</option>
                        <option value="social_media" <?php echo ($invited_by_type == 'social_media') ? 'selected' : ''; ?>>Social Media</option>
                        <option value="website" <?php echo ($invited_by_type == 'website') ? 'selected' : ''; ?>>Website</option>
                        <option value="self" <?php echo ($invited_by_type == 'self') ? 'selected' : ''; ?>>Came by Themselves</option>
                        <option value="other" <?php echo ($invited_by_type == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3" id="invited_by_details" <?php echo ($invited_by_type && !in_array($invited_by_type, ['website', 'self'])) ? '' : 'style="display: none;"'; ?>>
                    <label class="form-label fw-medium">Details</label>
                    <input type="text" class="form-control" name="invited_by_details" id="invited_by_details_input"
                           value="<?php echo htmlspecialchars($invited_by_details); ?>">
                </div>
            </div>
            
            <!-- Status Information -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Follow-up Needed</label>
                    <select class="form-select" name="follow_up_needed">
                        <option value="no" <?php echo (($visitor['follow_up_needed'] ?? 'no') == 'no') ? 'selected' : ''; ?>>No</option>
                        <option value="yes" <?php echo (($visitor['follow_up_needed'] ?? 'no') == 'yes') ? 'selected' : ''; ?>>Yes</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Follow-up Completed</label>
                    <select class="form-select" name="follow_up_completed">
                        <option value="no" <?php echo (($visitor['follow_up_completed'] ?? 'no') == 'no') ? 'selected' : ''; ?>>No</option>
                        <option value="yes" <?php echo (($visitor['follow_up_completed'] ?? 'no') == 'yes') ? 'selected' : ''; ?>>Yes</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Became Member</label>
                    <select class="form-select" name="became_member">
                        <option value="no" <?php echo (($visitor['became_member'] ?? 'no') == 'no') ? 'selected' : ''; ?>>No</option>
                        <option value="yes" <?php echo (($visitor['became_member'] ?? 'no') == 'yes') ? 'selected' : ''; ?>>Yes</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-medium">Notes</label>
                <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($visitor['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <div>
                    <a href="view.php?id=<?php echo $visitor['id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
                <div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Update Visitor
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleInvitedByInput() {
    const selectElement = document.getElementById('invited_by_type');
    const detailsDiv = document.getElementById('invited_by_details');
    const detailsInput = document.getElementById('invited_by_details_input');
    
    if (selectElement.value && selectElement.value !== 'website' && selectElement.value !== 'self') {
        detailsDiv.style.display = 'block';
        
        // Update placeholder based on selection
        switch(selectElement.value) {
            case 'member':
                detailsInput.placeholder = 'Enter the member\'s full name...';
                break;
            case 'social_media':
                detailsInput.placeholder = 'Specify platform (Facebook, Instagram, etc.)...';
                break;
            case 'other':
                detailsInput.placeholder = 'Please specify...';
                break;
            default:
                detailsInput.placeholder = 'Enter details...';
        }
    } else {
        detailsDiv.style.display = 'none';
        detailsInput.value = '';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>