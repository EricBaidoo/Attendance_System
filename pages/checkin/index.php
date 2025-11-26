<?php
// pages/checkin/index.php - Unified check-in system for members and visitors
require '../../config/database.php';

$message = '';
$error = '';

// Get today's active services
try {
    $services_sql = "SELECT ss.*, s.name as service_name, s.description 
                    FROM service_sessions ss 
                    JOIN services s ON ss.service_id = s.id 
                    WHERE ss.status = 'open' AND ss.session_date = CURDATE() 
                    ORDER BY ss.opened_at DESC";
    $services_stmt = $pdo->query($services_sql);
    $active_services = $services_stmt->fetchAll();
} catch (Exception $e) {
    $active_services = [];
    $error = "Unable to load services. Please try again.";
}

// Handle check-in submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service_id = $_POST['service_id'] ?? '';
    $person_type = $_POST['person_type'] ?? 'visitor'; // member or visitor
    $is_first_time = $_POST['first_time'] ?? 'no';
    $how_heard = trim($_POST['how_heard'] ?? '');
    
    if (empty($name) || empty($service_id) || empty($person_type)) {
        $error = "Name, service selection, and person type are required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($person_type === 'member') {
                // Handle member check-in
                $member_check = null;
                if (!empty($phone) || !empty($email)) {
                    $check_sql = "SELECT * FROM members WHERE";
                    $check_params = [];
                    $conditions = [];
                    
                    if (!empty($phone)) {
                        $conditions[] = "phone = ?";
                        $check_params[] = $phone;
                    }
                    if (!empty($email)) {
                        $conditions[] = "email = ?";
                        $check_params[] = $email;
                    }
                    
                    $check_sql .= " " . implode(' OR ', $conditions);
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute($check_params);
                    $member_check = $check_stmt->fetch();
                }
                
                if (!$member_check) {
                    // Check by name if no contact info match
                    $name_check = $pdo->prepare("SELECT * FROM members WHERE name LIKE ?");
                    $name_check->execute(["%$name%"]);
                    $member_check = $name_check->fetch();
                }
                
                if ($member_check) {
                    // Record member attendance
                    $attendance_sql = "INSERT INTO attendance (member_id, service_id, date, status, method, session_id) 
                                      VALUES (?, ?, CURDATE(), 'present', 'manual', ?) 
                                      ON DUPLICATE KEY UPDATE status = 'present'";
                    $pdo->prepare($attendance_sql)->execute([$member_check['id'], $service_id, $service_id]);
                    $message = "Welcome back, " . htmlspecialchars($member_check['name']) . "! Your attendance has been recorded.";
                } else {
                    $error = "Member record not found. Please check your information or contact a staff member.";
                }
                
            } else {
                // Handle visitor check-in (same as before)
                $existing_visitor = null;
                if (!empty($phone) || !empty($email)) {
                    $check_sql = "SELECT * FROM visitors WHERE";
                    $check_params = [];
                    $conditions = [];
                    
                    if (!empty($phone)) {
                        $conditions[] = "phone = ?";
                        $check_params[] = $phone;
                    }
                    if (!empty($email)) {
                        $conditions[] = "email = ?";
                        $check_params[] = $email;
                    }
                    
                    $check_sql .= " " . implode(' OR ', $conditions);
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute($check_params);
                    $existing_visitor = $check_stmt->fetch();
                }
                
                if ($existing_visitor) {
                    // Update existing visitor
                    $visitor_id = $existing_visitor['id'];
                    $update_sql = "UPDATE visitors SET name = ?, phone = ?, email = ?, 
                                  first_time = 'no', how_heard = COALESCE(?, how_heard) 
                                  WHERE id = ?";
                    $pdo->prepare($update_sql)->execute([$name, $phone, $email, $how_heard, $visitor_id]);
                } else {
                    // Create new visitor
                    $insert_sql = "INSERT INTO visitors (name, phone, email, first_time, how_heard, 
                                  service_id, date, created_at) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), NOW())";
                    $pdo->prepare($insert_sql)->execute([$name, $phone, $email, $is_first_time, $how_heard, $service_id]);
                    $visitor_id = $pdo->lastInsertId();
                }
                
                // Record visitor attendance
                $attendance_sql = "INSERT INTO visitor_attendance (visitor_id, service_id, visit_date, created_at) 
                                  VALUES (?, ?, CURDATE(), NOW()) 
                                  ON DUPLICATE KEY UPDATE visit_number = visit_number + 1";
                $pdo->prepare($attendance_sql)->execute([$visitor_id, $service_id]);
                
                $message = "Welcome! Your attendance has been recorded successfully.";
            }
            
            $pdo->commit();
            // Clear form data on success
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Sorry, there was an error recording your attendance. Please try again or see a staff member.";
        }
    }
}

// Set page variables for header
$page_title = "Service Check-In - Bridge Ministries International";
$page_header = true;
$page_icon = "bi bi-check-circle";
$page_heading = "Service Check-In";
$page_description = "Check in for today's service - Members and Visitors Welcome";
$base_url = "/ATTENDANCE%20SYSTEM/";

// Include header
include '../../includes/header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (empty($active_services)): ?>
<div class="modern-card p-5 text-center">
    <i class="bi bi-calendar-x text-muted fs-1 mb-3"></i>
    <h3 class="text-muted">No Active Services</h3>
    <p class="text-muted mb-4">There are currently no open services for check-in today.</p>
    <p class="small text-muted">Please contact a staff member if you believe this is an error.</p>
    <a href="../../" class="btn btn-outline-primary">
        <i class="bi bi-house me-2"></i>Back to Home
    </a>
</div>
<?php else: ?>
<!-- Unified Check-in Form ---->
<div class="modern-card p-4">
    <form method="POST" class="row g-3" id="checkinForm">
        <!-- Person Type Selection -->
        <div class="col-12">
            <label class="form-label fw-semibold">
                <i class="bi bi-person-check me-1"></i>I am a... *
            </label>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-check-card">
                        <input class="form-check-input" type="radio" name="person_type" id="member" value="member" 
                               <?php echo (($_POST['person_type'] ?? '') == 'member') ? 'checked' : ''; ?>>
                        <label class="form-check-label w-100 p-3 border rounded" for="member">
                            <i class="bi bi-people-fill text-primary fs-4 d-block mb-2"></i>
                            <strong>Church Member</strong><br>
                            <small class="text-muted">I'm already a member of this church</small>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-check-card">
                        <input class="form-check-input" type="radio" name="person_type" id="visitor" value="visitor" 
                               <?php echo (($_POST['person_type'] ?? 'visitor') == 'visitor') ? 'checked' : ''; ?>>
                        <label class="form-check-label w-100 p-3 border rounded" for="visitor">
                            <i class="bi bi-person-badge-fill text-success fs-4 d-block mb-2"></i>
                            <strong>Visitor</strong><br>
                            <small class="text-muted">I'm visiting or new to this church</small>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                <i class="bi bi-person-fill me-1"></i>Full Name *
            </label>
            <input type="text" class="form-control" name="name" required 
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                   placeholder="Enter your full name">
        </div>
        
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                <i class="bi bi-calendar-event me-1"></i>Service *
            </label>
            <select class="form-select" name="service_id" required>
                <option value="">Select today's service</option>
                <?php foreach ($active_services as $service): ?>
                    <option value="<?php echo $service['id']; ?>"
                            <?php echo (($_POST['service_id'] ?? '') == $service['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($service['service_name']); ?>
                        <?php if ($service['description']): ?>
                            - <?php echo htmlspecialchars($service['description']); ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                <i class="bi bi-telephone-fill me-1"></i>Phone Number
            </label>
            <input type="tel" class="form-control" name="phone" 
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                   placeholder="(555) 123-4567">
            <small class="form-text text-muted">Helps us identify you faster</small>
        </div>
        
        <div class="col-md-6">
            <label class="form-label fw-semibold">
                <i class="bi bi-envelope-fill me-1"></i>Email Address
            </label>
            <input type="email" class="form-control" name="email" 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   placeholder="your.email@example.com">
        </div>
        
        <!-- Visitor-specific fields -->
        <div id="visitorFields" style="display: none;">
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    <i class="bi bi-question-circle me-1"></i>Is this your first time visiting?
                </label>
                <select class="form-select" name="first_time">
                    <option value="yes" <?php echo (($_POST['first_time'] ?? 'yes') == 'yes') ? 'selected' : ''; ?>>Yes, first time</option>
                    <option value="no" <?php echo (($_POST['first_time'] ?? '') == 'no') ? 'selected' : ''; ?>>No, I've been here before</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    <i class="bi bi-chat-dots me-1"></i>How did you hear about us?
                </label>
                <input type="text" class="form-control" name="how_heard" 
                       value="<?php echo htmlspecialchars($_POST['how_heard'] ?? ''); ?>"
                       placeholder="Friend, website, social media, etc.">
            </div>
        </div>
        
        <div class="col-12">
            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                <button type="submit" name="checkin" class="btn btn-primary-modern btn-lg px-5">
                    <i class="bi bi-check-circle-fill me-2"></i>Check In
                </button>
                <a href="../../" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="bi bi-house me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </form>
</div>

<script>
// Show/hide visitor-specific fields based on selection
document.addEventListener('DOMContentLoaded', function() {
    const memberRadio = document.getElementById('member');
    const visitorRadio = document.getElementById('visitor');
    const visitorFields = document.getElementById('visitorFields');
    
    function toggleFields() {
        if (visitorRadio.checked) {
            visitorFields.style.display = 'block';
            visitorFields.querySelectorAll('.col-md-6').forEach(field => {
                field.classList.add('col-md-6');
            });
        } else {
            visitorFields.style.display = 'none';
        }
    }
    
    memberRadio.addEventListener('change', toggleFields);
    visitorRadio.addEventListener('change', toggleFields);
    
    // Initial state
    toggleFields();
});
</script>

<!-- Welcome Message -->
<div class="modern-card p-4 mt-4">
    <div class="text-center">
        <h4 class="text-gradient mb-3">
            <i class="bi bi-heart-fill text-danger me-2"></i>Welcome to Bridge Ministries International!
        </h4>
        <p class="text-muted">We're excited to have you worship with us today. After checking in, please feel free to:</p>
        <div class="row g-3 mt-3">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded">
                    <i class="bi bi-people-fill text-primary fs-4 mb-2 d-block"></i>
                    <strong>Meet Our Team</strong><br>
                    <small class="text-muted">Our friendly staff and volunteers are here to help</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded">
                    <i class="bi bi-cup-hot text-warning fs-4 mb-2 d-block"></i>
                    <strong>Enjoy Refreshments</strong><br>
                    <small class="text-muted">Coffee and light snacks available before service</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded">
                    <i class="bi bi-chat-heart text-success fs-4 mb-2 d-block"></i>
                    <strong>Ask Questions</strong><br>
                    <small class="text-muted">We'd love to answer any questions you might have</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.form-check-card .form-check-input:checked + .form-check-label {
    background-color: rgba(13, 110, 253, 0.1);
    border-color: #0d6efd !important;
}

.form-check-card .form-check-label {
    cursor: pointer;
    transition: all 0.3s ease;
}

.form-check-card .form-check-label:hover {
    background-color: rgba(0, 0, 0, 0.05);
}
</style>

<?php include '../../includes/footer.php'; ?>