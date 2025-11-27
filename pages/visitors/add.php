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
                $stmt = $pdo->prepare(
                    "INSERT INTO visitors 
                     (name, email, phone, service_id, first_time, how_heard, invited_by, 
                      follow_up_needed, notes, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                
                if ($stmt->execute([
                    $name, $email, $phone, $service_id, $first_time, $how_heard, 
                    $invited_by, $follow_up_needed, $notes
                ])) {
                    $visitor_id = $pdo->lastInsertId();
                    header('Location: view.php?id=' . $visitor_id . '&success=Visitor added successfully');
                    exit;
                } else {
                    $error = "Failed to add visitor";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
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
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "Add New Visitor";
$page_header = true;
$page_icon = "bi bi-person-badge";
$page_heading = "Add New Visitor";
$page_description = "Register a church visitor to the system";
$page_actions = '<a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Visitors</a>
                <a href="checkin.php" class="btn btn-success"><i class="bi bi-check-circle"></i> Quick Check-In</a>';

include '../../includes/header.php';
?>

<!-- Using Bootstrap classes only -->

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill text-danger me-3 fs-4"></i>
        <div>
            <h6 class="alert-heading mb-1">Error Adding Visitor</h6>
            <p class="mb-0"><?php echo $error; ?></p>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Add Visitor Form -->
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-8">
        <div class="card border-0 shadow form-card">
            <div class="card-header form-gradient-header text-white p-4">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-25 rounded-circle p-3 me-3">
                        <i class="bi bi-person-badge fs-3"></i>
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold">New Visitor Registration</h4>
                        <p class="mb-0 opacity-75">Welcome and register a church visitor</p>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4">
                <form method="POST">
                    <!-- Personal Information Section -->
                    <div class="mb-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary text-white rounded-circle p-2 me-3">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <h5 class="mb-0 text-primary fw-bold">Visitor Information</h5>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-person text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="name" 
                                           placeholder="Enter visitor's full name..." required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Visit Type</label>
                                <select class="form-select" name="first_time">
                                    <option value="yes">First Time Visitor</option>
                                    <option value="no">Return Visitor</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-envelope text-muted"></i>
                                    </span>
                                    <input type="email" class="form-control border-start-0" name="email" 
                                           placeholder="visitor@email.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-telephone text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="phone" 
                                           placeholder="(123) 456-7890">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="section-divider">
                    
                    <!-- Visit Information Section -->
                    <div class="mb-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-success text-white rounded-circle p-2 me-3">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <h5 class="mb-0 text-success fw-bold">Visit Details</h5>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Service <span class="required">*</span></label>
                                <select class="form-select" name="service_id" required>
                                    <option value="">Select Service</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['id']; ?>">
                                            <?php echo htmlspecialchars($service['name']); ?>
                                            <?php if ($service['session_date']): ?>
                                                - <?php echo date('M d, Y', strtotime($service['session_date'])); ?>
                                                <?php if ($service['session_status']): ?>
                                                    (<?php echo ucfirst($service['session_status']); ?>)
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">How Did You Hear About Us?</label>
                                <select class="form-select" name="how_heard">
                                    <option value="">Select Option</option>
                                    <option value="friend">Friend/Family Invitation</option>
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
                                    <option value="self">Came by Themselves</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-12 d-none" id="invited_by_details">
                                <label class="form-label">Specific Details</label>
                                <input type="text" class="form-control" name="invited_by_details" id="invited_by_details_input"
                                       placeholder="Enter member name, social media platform, or other details...">
                                <small class="form-text text-muted">For members: Enter full name. For social media: Specify platform (Facebook, Instagram, etc.)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Follow-up Needed?</label>
                                <div class="d-flex gap-3 pt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="follow_up_needed" value="no" id="followup_no" checked>
                                        <label class="form-check-label" for="followup_no">No Follow-up</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="follow_up_needed" value="yes" id="followup_yes">
                                        <label class="form-check-label" for="followup_yes">Needs Follow-up</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Additional Notes</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Any additional information about the visitor..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <div>
                            <a href="list.php" class="btn btn-outline-secondary btn-custom">
                                <i class="bi bi-arrow-left me-2"></i>Cancel & Go Back
                            </a>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="reset" class="btn btn-outline-warning btn-custom">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset Form
                            </button>
                            <button type="submit" class="btn btn-success btn-custom">
                                <i class="bi bi-check-circle-fill me-2"></i>Add Visitor
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
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
                detailsInput.placeholder = 'Specify platform (Facebook, Instagram, TikTok, etc.)...';
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