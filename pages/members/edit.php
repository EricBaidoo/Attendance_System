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
    
    // Handle form submission
    if ($_POST) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $phone2 = !empty(trim($_POST['phone2'])) ? trim($_POST['phone2']) : null;
        $gender = $_POST['gender'];
        $dob = $_POST['dob'] ?: null;
        $location = trim($_POST['location']);
        $department_id = $_POST['department_id'] ?: null;
        $congregation_group = $_POST['congregation_group'];
        $baptized = $_POST['baptized'];
        $status = $_POST['status'];
        
        $update_stmt = $pdo->prepare(
            "UPDATE members SET 
             name = ?, email = ?, phone = ?, phone2 = ?, gender = ?, dob = ?, 
             location = ?, department_id = ?, congregation_group = ?, 
             baptized = ?, status = ?
             WHERE id = ?"
        );
        
        if ($update_stmt->execute([
            $name, $email, $phone, $phone2, $gender, $dob, 
            $location, $department_id, $congregation_group, 
            $baptized, $status, $member_id
        ])) {
            header('Location: view.php?id=' . $member_id . '&success=Member updated successfully');
            exit;
        } else {
            $error = "Failed to update member";
        }
    }
    
    // Get member details
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    
    if (!$member) {
        header('Location: list.php?error=Member not found');
        exit;
    }
    
    // Get departments
    $dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
    $departments = $dept_stmt->fetchAll();
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "Edit Member - {$member['name']}";
$page_header = true;
$page_icon = "bi bi-pencil";
$page_heading = "Edit Member";
$page_description = "Update member information and details";
$page_actions = '<a href="view.php?id=' . $member['id'] . '" class="btn btn-secondary"><i class="bi bi-eye"></i> View Member</a>
                <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/members.css?v=<?php echo time(); ?>" rel="stylesheet">

<style>
.edit-form-container {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    min-height: calc(100vh - 200px);
    padding: 2rem 0;
}

.form-card {
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    border-radius: 20px;
    overflow: hidden;
}

.form-header {
    background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.member-avatar-edit {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    font-weight: bold;
    margin: 0 auto 1rem;
    border: 4px solid rgba(255,255,255,0.3);
}

.form-section {
    padding: 2rem;
    border-bottom: 1px solid #e5e7eb;
}

.form-section:last-child {
    border-bottom: none;
}

.section-title {
    color: #1f2937;
    font-weight: 700;
    font-size: 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.section-title i {
    margin-right: 0.75rem;
    color: #3b82f6;
    font-size: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control, .form-select {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.2s ease;
    background-color: #ffffff;
}

.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background-color: #ffffff;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    border: none;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.btn-outline-secondary {
    border: 2px solid #6b7280;
    color: #6b7280;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-outline-secondary:hover {
    background: #6b7280;
    border-color: #6b7280;
    transform: translateY(-2px);
}

.required {
    color: #ef4444;
}

.alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
}

.alert-success {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}
</style>

<div class="edit-form-container">
    <div class="container">
        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card form-card">
                    <!-- Form Header -->
                    <div class="form-header">
                        <div class="member-avatar-edit">
                            <?php echo strtoupper(substr($member['name'], 0, 2)); ?>
                        </div>
                        <h2 class="mb-2">Edit Member Information</h2>
                        <p class="mb-0 opacity-90">Update <?php echo htmlspecialchars($member['name']); ?>'s details</p>
                    </div>

                    <!-- Form Body -->
                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="bi bi-person-circle"></i>
                                Personal Information
                            </h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Full Name <span class="required">*</span></label>
                                        <input type="text" name="name" class="form-control" 
                                               value="<?php echo htmlspecialchars($member['name']); ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($member['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($member['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" name="dob" class="form-control" 
                                               value="<?php echo $member['dob']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Location</label>
                                        <input type="text" name="location" class="form-control" 
                                               value="<?php echo htmlspecialchars($member['location'] ?? ''); ?>"
                                               placeholder="City, State or Address">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="bi bi-telephone"></i>
                                Contact Information
                            </h3>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>"
                                               placeholder="member@example.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Primary Phone</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>"
                                               placeholder="+1 (555) 123-4567">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Alternative Phone</label>
                                        <input type="tel" name="phone2" class="form-control" 
                                               value="<?php echo htmlspecialchars($member['phone2'] ?? ''); ?>"
                                               placeholder="+1 (555) 987-6543">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Church Information Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="bi bi-building"></i>
                                Church Information
                            </h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Department</label>
                                        <select name="department_id" class="form-select">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" 
                                                    <?php echo ($member['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Congregation Group</label>
                                        <select name="congregation_group" class="form-select">
                                            <option value="Adult" <?php echo ($member['congregation_group'] == 'Adult') ? 'selected' : ''; ?>>Adult</option>
                                            <option value="Youth" <?php echo ($member['congregation_group'] == 'Youth') ? 'selected' : ''; ?>>Youth</option>
                                            <option value="Teen" <?php echo ($member['congregation_group'] == 'Teen') ? 'selected' : ''; ?>>Teen</option>
                                            <option value="Children" <?php echo ($member['congregation_group'] == 'Children') ? 'selected' : ''; ?>>Children</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Baptism Status</label>
                                        <select name="baptized" class="form-select">
                                            <option value="no" <?php echo ($member['baptized'] == 'no') ? 'selected' : ''; ?>>Not Baptized</option>
                                            <option value="yes" <?php echo ($member['baptized'] == 'yes') ? 'selected' : ''; ?>>Baptized</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Member Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?php echo ($member['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($member['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="form-section">
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Update Member
                                </button>
                                <a href="view.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-eye me-2"></i>View Member
                                </a>
                                <a href="list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to List
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../includes/footer.php'; ?>