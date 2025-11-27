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
        $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
        $phone = trim($_POST['phone']);
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $location = !empty(trim($_POST['location'])) ? trim($_POST['location']) : null;
        $occupation = !empty(trim($_POST['occupation'])) ? trim($_POST['occupation']) : null;
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $congregation_group = !empty($_POST['congregation_group']) ? $_POST['congregation_group'] : 'Adult';
        $baptized = !empty($_POST['baptized']) ? $_POST['baptized'] : 'no';
        
        // Only require name and phone
        if ($name && $phone) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO members 
                     (name, email, phone, gender, dob, location, occupation, 
                      department_id, congregation_group, baptized, status, date_joined) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())"
                );
                
                if ($stmt->execute([
                    $name, $email, $phone, $gender, $dob, $location, $occupation,
                    $department_id, $congregation_group, $baptized
                ])) {
                    $member_id = $pdo->lastInsertId();
                    header('Location: view.php?id=' . $member_id . '&success=Member added successfully');
                    exit;
                } else {
                    $error = "Failed to add member";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "Please provide both name and phone number";
        }
    }
    
    // Get departments
    $dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
    $departments = $dept_stmt->fetchAll();
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "Add New Member";
$page_header = true;
$page_icon = "bi bi-person-plus";
$page_heading = "Add New Member";
$page_description = "Register a new church member to the system";
$page_actions = '<a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Members</a>';

include '../../includes/header.php';
?>

<!-- Using Bootstrap classes only -->

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill text-danger me-3 fs-4"></i>
        <div>
            <h6 class="alert-heading mb-1">Error Adding Member</h6>
            <p class="mb-0"><?php echo $error; ?></p>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Add Member Form -->
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-8">
        <div class="card border-0 shadow form-card">
            <div class="card-header form-gradient-header text-white p-4">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-25 rounded-circle p-3 me-3">
                        <i class="bi bi-person-plus fs-3"></i>
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold">New Member Registration</h4>
                        <p class="mb-0 opacity-75">Complete the form below to add a new church member</p>
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
                            <h5 class="mb-0 text-primary fw-bold">Personal Information</h5>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-person text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="name" 
                                           placeholder="Enter full name..." required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                        </div>
                    </div>
                    
                    <hr class="section-divider">
                    
                    <!-- Contact Information Section -->
                    <div class="mb-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-success text-white rounded-circle p-2 me-3">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <h5 class="mb-0 text-success fw-bold">Contact Information</h5>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-envelope text-muted"></i>
                                    </span>
                                    <input type="email" class="form-control border-start-0" name="email" 
                                           placeholder="member@email.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number <span class="required">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-telephone text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="phone" 
                                           placeholder="(123) 456-7890" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Occupation</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-briefcase text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="occupation" 
                                           placeholder="Job title or profession">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location/City</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-geo-alt text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" name="location" 
                                           placeholder="City, State">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="section-divider">
                    
                    <!-- Church Information Section -->
                    <div class="mb-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-warning text-dark rounded-circle p-2 me-3">
                                <i class="bi bi-building"></i>
                            </div>
                            <h5 class="mb-0 text-warning fw-bold">Church Information</h5>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">No Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Congregation Group</label>
                                <select class="form-select" name="congregation_group">
                                    <option value="Adult">Adult</option>
                                    <option value="Youth">Youth</option>
                                    <option value="Teen">Teen</option>
                                    <option value="Children">Children</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Baptized Status</label>
                                <div class="d-flex gap-3 pt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="baptized" value="no" id="baptized_no" checked>
                                        <label class="form-check-label" for="baptized_no">Not Baptized</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="baptized" value="yes" id="baptized_yes">
                                        <label class="form-check-label" for="baptized_yes">Baptized</label>
                                    </div>
                                </div>
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
                                <i class="bi bi-check-circle-fill me-2"></i>Add Member
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>