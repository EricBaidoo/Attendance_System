<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';

requireLogin('../../../login');
$user_role = getUserRole();

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
        $notes = trim($_POST['notes']);
        
        // Format the invited_by field based on type
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
        
        if ($name && $service_id) {
            try {
                $pdo->beginTransaction();

                $visitor_person_id = peopleFindOrCreateCompat($pdo, $name, $email, $phone, 'visitor');
                if (!$visitor_person_id) {
                    throw new RuntimeException('Could not resolve person profile for this visitor.');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO visitor_roles 
                     (person_id, name, email, phone, service_id, first_time, how_heard, invited_by, 
                      follow_up_needed, notes, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                
                if ($stmt->execute([
                    $visitor_person_id, $name, $email, $phone, $service_id, $first_time, $how_heard, 
                    $invited_by, $follow_up_needed, $notes
                ])) {
                    $visitor_id = (int)$pdo->lastInsertId();
                    peopleSyncRecord($pdo, 'visitor_roles', $visitor_id, $name, $email, $phone, 'visitor', 'visitor_checked_in', 'Visitor created from admin add form');
                    $pdo->commit();
                    header('Location: view?id=' . $visitor_id . '&success=Visitor added successfully');
                    exit;
                } else {
                    $pdo->rollBack();
                    $error = "Failed to add visitor";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Database error adding visitor: ' . $e->getMessage());
                $error = "Database operation failed. Please try again later.";
            }
        } else {
            $error = "Please provide at least the visitor's name and service";
        }
    }
    
    // Get services for dropdown - include both active sessions and upcoming services
    $services_stmt = $pdo->query("
        SELECT s.*, ss.session_date, ss.status as session_status 
        FROM services s 
        LEFT JOIN service_sessions ss ON s.id = ss.service_id AND ss.session_date >= CURDATE()
        WHERE s.status IN ('scheduled', 'open') 
        ORDER BY COALESCE(ss.session_date, s.created_at) DESC, s.name
    ");
    $services = $services_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Database error loading visitor add page: ' . $e->getMessage());
    die('Database error. Please contact the administrator.');
}
?>

<?php
$page_title = 'Add Visitor - ' . getInstitutionName($pdo);
$page_header = true;
$page_icon = 'bi bi-person-plus';
$page_heading = 'Add New Visitor';
$page_description = 'Register a first-time or returning visitor';
$page_actions = '<a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../../includes/header.php';
?>
<link href="../../../assets/css/visitors.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid visitor-form-page py-4 px-3 px-md-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm visitor-form-shell">
        <div class="card-body p-4 p-lg-5">
            <div class="visitor-form-hero mb-4">
                <div class="visitor-form-avatar"><i class="bi bi-person-plus"></i></div>
                <div>
                    <h3 class="mb-1 fw-bold">Create Visitor Profile</h3>
                    <p class="mb-0 text-muted">Capture details to support care, follow-up, and conversion tracking.</p>
                </div>
            </div>

            <form method="POST" id="visitorForm" novalidate>
                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="visitor-form-card h-100">
                            <h5 class="visitor-form-title"><i class="bi bi-person-vcard me-2"></i>Visitor Information</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Visit Type</label>
                                    <select class="form-select" name="first_time">
                                        <option value="yes">First Time Visitor</option>
                                        <option value="no">Returning Visitor</option>
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
                                    <label class="form-label">Service <span class="required">*</span></label>
                                    <select class="form-select" name="service_id" required>
                                        <option value="">Select Service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service['id']; ?>">
                                                <?php echo htmlspecialchars($service['name']); ?>
                                                <?php if (!empty($service['session_date'])): ?>
                                                    - <?php echo date('M d, Y', strtotime($service['session_date'])); ?>
                                                    <?php if (!empty($service['session_status'])): ?>
                                                        (<?php echo ucfirst($service['session_status']); ?>)
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">How They Heard</label>
                                    <select class="form-select" name="how_heard">
                                        <option value="">Select Option</option>
                                        <option value="friend">Friend/Family</option>
                                        <option value="social_media">Social Media</option>
                                        <option value="website">Website</option>
                                        <option value="flyer">Flyer/Advertisement</option>
                                        <option value="radio">Radio</option>
                                        <option value="tv">Television</option>
                                        <option value="community">Community Event</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Invited/Referred By</label>
                                    <select class="form-select" name="invited_by_type" id="invited_by_type" onchange="toggleInvitedByInput()">
                                        <option value="">Select Option</option>
                                        <option value="member">Church Member</option>
                                        <option value="social_media">Social Media</option>
                                        <option value="website">Website</option>
                                        <option value="self">Self-directed</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-12 d-none visitor-conditional-field" id="invited_by_details">
                                    <label class="form-label">Referral Details</label>
                                    <input type="text" class="form-control" name="invited_by_details" id="invited_by_details_input" placeholder="Enter details...">
                                    <small class="text-muted d-block mt-1">Use member name or social platform when relevant.</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Follow-up Needed?</label>
                                    <div class="visitor-radio-group">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="follow_up_needed" value="no" id="followup_no" checked>
                                            <label class="form-check-label" for="followup_no">No</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="follow_up_needed" value="yes" id="followup_yes">
                                            <label class="form-check-label" for="followup_yes">Yes</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="visitor-form-actions d-flex flex-wrap justify-content-between gap-2 mt-4 pt-3 border-top">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
                        <button type="reset" class="btn btn-outline-warning"><i class="bi bi-arrow-clockwise me-1"></i>Reset</button>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle-fill me-1"></i>Add Visitor</button>
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
        switch (selectElement.value) {
            case 'member':
                detailsInput.placeholder = 'Enter the member full name...';
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

document.getElementById('visitorForm').addEventListener('submit', function (event) {
    if (!this.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

<?php include '../../../includes/footer.php'; ?>
