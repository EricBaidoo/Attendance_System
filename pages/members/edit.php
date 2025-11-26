on<?php
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
        $gender = $_POST['gender'];
        $dob = $_POST['dob'] ?: null;
        $location = trim($_POST['location']);
        $department_id = $_POST['department_id'] ?: null;
        $congregation_group = $_POST['congregation_group'];
        $baptized = $_POST['baptized'];
        $status = $_POST['status'];
        
        $update_stmt = $pdo->prepare(
            "UPDATE members SET 
             name = ?, email = ?, phone = ?, gender = ?, dob = ?, 
             location = ?, department_id = ?, congregation_group = ?, 
             baptized = ?, status = ?
             WHERE id = ?"
        );
        
        if ($update_stmt->execute([
            $name, $email, $phone, $gender, $dob, 
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

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Edit Member Form -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0"><i class="bi bi-pencil text-warning me-2"></i>Edit Member Information</h5>
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
                               value="<?php echo htmlspecialchars($member['name']); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($member['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($member['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">Date of Birth</label>
                            <input type="date" class="form-control" name="dob" 
                                   value="<?php echo $member['dob']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Location</label>
                        <input type="text" class="form-control" name="location" 
                               value="<?php echo htmlspecialchars($member['location']); ?>" 
                               placeholder="City, State or Address">
                    </div>
                </div>
                
                <!-- Contact & Church Information -->
                <div class="col-lg-6">
                    <h6 class="text-muted mb-3"><i class="bi bi-telephone me-2"></i>Contact & Church Information</h6>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($member['email']); ?>" 
                               placeholder="member@email.com">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Phone</label>
                        <input type="text" class="form-control" name="phone" 
                               value="<?php echo htmlspecialchars($member['phone']); ?>" 
                               placeholder="(123) 456-7890">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">No Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                        <?php echo ($member['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">Congregation Group</label>
                            <select class="form-select" name="congregation_group">
                                <option value="Adult" <?php echo ($member['congregation_group'] == 'Adult') ? 'selected' : ''; ?>>Adult</option>
                                <option value="Youth" <?php echo ($member['congregation_group'] == 'Youth') ? 'selected' : ''; ?>>Youth</option>
                                <option value="Teen" <?php echo ($member['congregation_group'] == 'Teen') ? 'selected' : ''; ?>>Teen</option>
                                <option value="Children" <?php echo ($member['congregation_group'] == 'Children') ? 'selected' : ''; ?>>Children</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium">Baptized</label>
                            <select class="form-select" name="baptized">
                                <option value="no" <?php echo ($member['baptized'] == 'no') ? 'selected' : ''; ?>>No</option>
                                <option value="yes" <?php echo ($member['baptized'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo ($member['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($member['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-between">
                <div>
                    <a href="view.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
                <div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Update Member
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>