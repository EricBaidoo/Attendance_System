<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';

requireLogin('../../../login');
$user_role = getUserRole();

// Get visitor ID
$visitor_id = $_GET['id'] ?? null;
if (!$visitor_id) {
    header('Location: list');
    exit;
}

// Database connection
try {
    require '../../../config/database.php';
    
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
            "UPDATE visitor_roles SET 
             name = ?, email = ?, phone = ?, service_id = ?, first_time = ?, 
             how_heard = ?, invited_by = ?, follow_up_needed = ?, 
             follow_up_completed = ?, became_member = ?, notes = ?
             WHERE id = ?"
        );

        try {
            $pdo->beginTransaction();

            if ($update_stmt->execute([
                $name, $email, $phone, $service_id, $first_time,
                $how_heard, $invited_by, $follow_up_needed,
                $follow_up_completed, $became_member, $notes, $visitor_id
            ])) {
                $expected_stage = strtolower((string)$became_member) === 'yes' ? 'member' : 'visitor';
                peopleSyncRecord($pdo, 'visitors', (int)$visitor_id, $name, $email, $phone, $expected_stage);
                $pdo->commit();
                header('Location: view?id=' . $visitor_id . '&success=Visitor updated successfully');
                exit;
            }

            $pdo->rollBack();
            $error = "Failed to update visitor";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Database error updating visitor: ' . $e->getMessage());
            $error = "Database operation failed. Please try again later.";
        }
    }
    
    // Get visitor details
    $stmt = $pdo->prepare("SELECT v.*, s.name AS service_name 
                          FROM visitor_roles v 
                          LEFT JOIN services s ON v.service_id = s.id 
                          WHERE v.id = ?");
    $stmt->execute([$visitor_id]);
    $visitor = $stmt->fetch();
    
    if (!$visitor) {
        header('Location: list?error=Visitor not found');
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
    error_log('Database error loading visitor edit page: ' . $e->getMessage());
    die('Database error. Please contact the administrator.');
}

// Page configuration
$page_title = "Edit Visitor - {$visitor['name']}";
$page_header = true;
$page_icon = "bi bi-pencil";
$page_heading = "Edit Visitor";
$page_description = "Update visitor information and details";
$page_actions = '<a href="view?id=' . $visitor['id'] . '" class="btn btn-secondary"><i class="bi bi-eye"></i> View Visitor</a>
                <a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../../includes/header.php';
?>
<link href="../../../assets/css/visitors.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid visitor-form-page py-4 px-3 px-md-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm visitor-form-shell">
        <div class="card-body p-4 p-lg-5">
            <div class="visitor-form-hero mb-4">
                <div class="visitor-form-avatar"><i class="bi bi-pencil-square"></i></div>
                <div>
                    <h3 class="mb-1 fw-bold">Update Visitor Profile</h3>
                    <p class="mb-0 text-muted">Edit contact details, visit history, and follow-up outcomes.</p>
                </div>
            </div>

            <form method="POST" id="visitorEditForm" novalidate>
                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="visitor-form-card h-100">
                            <h5 class="visitor-form-title"><i class="bi bi-person-vcard me-2"></i>Visitor Information</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($visitor['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($visitor['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($visitor['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Visit Type</label>
                                    <select class="form-select" name="first_time">
                                        <option value="yes" <?php echo ($visitor['first_time'] === 'yes') ? 'selected' : ''; ?>>First Time Visitor</option>
                                        <option value="no" <?php echo ($visitor['first_time'] === 'no') ? 'selected' : ''; ?>>Returning Visitor</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="visitor-form-card h-100">
                            <h5 class="visitor-form-title"><i class="bi bi-calendar-event me-2"></i>Visit & Follow-up</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Service</label>
                                    <select class="form-select" name="service_id">
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service['id']; ?>" <?php echo ($visitor['service_id'] == $service['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($service['name']); ?>
                                                <?php if (!empty($service['session_date'])): ?>
                                                    - <?php echo date('M d, Y', strtotime($service['session_date'])); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">How They Heard</label>
                                    <select class="form-select" name="how_heard">
                                        <option value="">Select Option</option>
                                        <option value="friend" <?php echo ($visitor['how_heard'] === 'friend') ? 'selected' : ''; ?>>Friend/Family</option>
                                        <option value="social_media" <?php echo ($visitor['how_heard'] === 'social_media') ? 'selected' : ''; ?>>Social Media</option>
                                        <option value="website" <?php echo ($visitor['how_heard'] === 'website') ? 'selected' : ''; ?>>Website</option>
                                        <option value="other" <?php echo ($visitor['how_heard'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Invited/Referred By</label>
                                    <select class="form-select" name="invited_by_type" id="invited_by_type" onchange="toggleInvitedByInput()">
                                        <option value="">Select Option</option>
                                        <option value="member" <?php echo ($invited_by_type === 'member') ? 'selected' : ''; ?>>Church Member</option>
                                        <option value="social_media" <?php echo ($invited_by_type === 'social_media') ? 'selected' : ''; ?>>Social Media</option>
                                        <option value="website" <?php echo ($invited_by_type === 'website') ? 'selected' : ''; ?>>Website</option>
                                        <option value="self" <?php echo ($invited_by_type === 'self') ? 'selected' : ''; ?>>Self-directed</option>
                                        <option value="other" <?php echo ($invited_by_type === 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-12 d-none visitor-conditional-field" id="invited_by_details">
                                    <label class="form-label">Referral Details</label>
                                    <input type="text" class="form-control" name="invited_by_details" id="invited_by_details_input" value="<?php echo htmlspecialchars($invited_by_details ?? ''); ?>">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Follow-up Needed</label>
                                    <select class="form-select" name="follow_up_needed">
                                        <option value="no" <?php echo (($visitor['follow_up_needed'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                        <option value="yes" <?php echo (($visitor['follow_up_needed'] ?? 'no') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Follow-up Completed</label>
                                    <select class="form-select" name="follow_up_completed">
                                        <option value="no" <?php echo (($visitor['follow_up_completed'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                        <option value="yes" <?php echo (($visitor['follow_up_completed'] ?? 'no') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Became Member</label>
                                    <select class="form-select" name="became_member">
                                        <option value="no" <?php echo (($visitor['became_member'] ?? 'no') === 'no') ? 'selected' : ''; ?>>No</option>
                                        <option value="yes" <?php echo (($visitor['became_member'] ?? 'no') === 'yes') ? 'selected' : ''; ?>>Yes</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="4"><?php echo htmlspecialchars($visitor['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="visitor-form-actions d-flex flex-wrap justify-content-between gap-2 mt-4 pt-3 border-top">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="view?id=<?php echo $visitor['id']; ?>" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                        <a href="list" class="btn btn-light border"><i class="bi bi-list me-1"></i>Visitor List</a>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Update Visitor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleInvitedByInput() {
    const selectElement = document.getElementById('invited_by_type');
    const detailsDiv = document.getElementById('invited_by_details');
    const detailsInput = document.getElementById('invited_by_details_input');
    
    if (selectElement.value && selectElement.value !== 'website' && selectElement.value !== 'self') {
        detailsDiv.classList.remove('d-none');
        
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
        detailsDiv.classList.add('d-none');
        detailsInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    toggleInvitedByInput();
    document.getElementById('visitorEditForm').addEventListener('submit', function (event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        this.classList.add('was-validated');
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
