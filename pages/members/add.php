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
        $phone2 = !empty(trim($_POST['phone2'])) ? trim($_POST['phone2']) : null;
        $gender = !empty($_POST['gender']) ? strtolower($_POST['gender']) : null;
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $location = !empty(trim($_POST['location'])) ? trim($_POST['location']) : null;
        $occupation = !empty(trim($_POST['occupation'])) ? trim($_POST['occupation']) : null;
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $congregation_group = !empty($_POST['congregation_group']) ? $_POST['congregation_group'] : 'Adult';
        $baptized = !empty($_POST['baptized']) ? $_POST['baptized'] : 'no';
        $ministerial_status = !empty($_POST['ministerial_status']) ? $_POST['ministerial_status'] : null;
        
        // Only require name and phone
        if ($name && $phone) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO members 
                     (name, email, phone, phone2, gender, dob, location, occupation, 
                     department_id, congregation_group, baptized, ministerial_status, status, date_joined) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())"
                );
                
                if ($stmt->execute([
                    $name, $email, $phone, $phone2, $gender, $dob, $location, $occupation,
                    $department_id, $congregation_group, $baptized, $ministerial_status
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1e3c72">
    <title>Add New Member - Bridge Ministries International</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/members.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="../../assets/css/mobile-responsive.css" rel="stylesheet">
</head>
<body class="add-member-page">


    <div class="main-container">
        <!-- Error Messages -->
        <?php if (isset($error)): ?>
        <div class="container mb-3">
            <div class="alert alert-danger shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-3 fs-5"></i>
                    <div>
                        <h6 class="mb-1 fw-bold">Error Adding Member</h6>
                        <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="container">
            <div class="member-form-card">
                <!-- Header -->
                <div class="form-header">
                    <div class="header-icon">
                        <i class="bi bi-person-plus-fill fs-5"></i>
                    </div>
                    <h4 class="fw-bold mb-1" style="color: #ffffff;">Add New Member</h4>
                    <p class="mb-0" style="color: rgba(255, 255, 255, 0.95);">Bridge Ministries International</p>
                    <small style="color: rgba(255, 255, 255, 0.85);">Register a new church member</small>
                </div>

                <!-- Body -->
                <div class="form-body">
                    <form method="POST" id="memberForm">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <!-- Personal Information Section -->
                                <div class="mb-3">
                                    <div class="section-header">
                                        <div class="section-icon personal-info">
                                            <i class="bi bi-person-circle"></i>
                                        </div>
                                        <div>
                                            <h6 class="section-title text-primary mb-0">Personal Info</h6>
                                            <small class="text-secondary">Basic details</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="memberName" 
                                                       name="name" 
                                                       required>
                                                <label for="memberName">
                                                    <i class="bi bi-person me-1"></i>Full Name <span class="required">*</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-6">
                                            <label class="form-label">
                                                <i class="bi bi-gender-ambiguous me-1"></i>Gender
                                            </label>
                                            <select class="form-select" name="gender">
                                                <option value="">Select</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-6">
                                            <label class="form-label">
                                                <i class="bi bi-calendar me-1"></i>Birth Date
                                            </label>
                                            <input type="date" class="form-control" name="dob">
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information Section -->
                                <div class="mb-3">
                                    <div class="section-header">
                                        <div class="section-icon contact-info">
                                            <i class="bi bi-telephone"></i>
                                        </div>
                                        <div>
                                            <h6 class="section-title text-success mb-0">Contact Info</h6>
                                            <small class="text-secondary">Communication details</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="email" 
                                                       class="form-control" 
                                                       id="memberEmail" 
                                                       name="email">
                                                <label for="memberEmail">
                                                    <i class="bi bi-envelope me-1"></i>Email Address
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="tel" 
                                                       class="form-control" 
                                                       id="memberPhone" 
                                                       name="phone" 
                                                       required>
                                                <label for="memberPhone">
                                                    <i class="bi bi-telephone me-1"></i>Phone <span class="required">*</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="tel" 
                                                       class="form-control" 
                                                       id="memberPhone2" 
                                                       name="phone2">
                                                <label for="memberPhone2">
                                                    <i class="bi bi-telephone-plus me-1"></i>Phone 2 (Optional)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <!-- Location & Work -->
                                <div class="mb-3">
                                    <div class="section-header">
                                        <div class="section-icon church-info">
                                            <i class="bi bi-geo-alt"></i>
                                        </div>
                                        <div>
                                            <h6 class="section-title text-purple mb-0">Location & Work</h6>
                                            <small class="text-secondary">Address and occupation</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="memberLocation" 
                                                       name="location">
                                                <label for="memberLocation">
                                                    <i class="bi bi-geo-alt me-1"></i>Location/City
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="memberOccupation" 
                                                       name="occupation">
                                                <label for="memberOccupation">
                                                    <i class="bi bi-briefcase me-1"></i>Occupation
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Church Information Section -->
                                <div class="mb-3">
                                    <div class="section-header">
                                        <div class="section-icon church-info">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <div>
                                            <h6 class="section-title text-purple mb-0">Church Info</h6>
                                            <small class="text-secondary">Ministry and groups</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="bi bi-diagram-3 me-1"></i>Department
                                            </label>
                                            <select class="form-select" name="department_id">
                                                <option value="">No Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept['id']; ?>">
                                                        <?php echo htmlspecialchars($dept['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="bi bi-people me-1"></i>Congregation Group
                                            </label>
                                            <select class="form-select" name="congregation_group">
                                                <option value="Adult">Adult</option>
                                                <option value="Youth">Youth</option>
                                                <option value="Teen">Teen</option>
                                                <option value="Children">Children</option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="bi bi-award me-1"></i>Ministerial Status
                                            </label>
                                            <select class="form-select" name="ministerial_status">
                                                <option value="">Not Set</option>
                                                <option value="Levite">Levite</option>
                                                <option value="Shepherd">Shepherd</option>
                                                <option value="Minister">Minister</option>
                                                <option value="Junior Pastor">Junior Pastor</option>
                                                <option value="Senior Pastor">Senior Pastor</option>
                                                <option value="General Overseer">General Overseer</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="bi bi-droplet me-1"></i>Baptized Status
                                            </label>
                                            <div class="d-flex gap-2">
                                                <div class="form-check flex-fill">
                                                    <input class="form-check-input" type="radio" name="baptized" value="no" id="baptized_no" checked>
                                                    <label class="form-check-label" for="baptized_no">
                                                        <i class="bi bi-x-circle me-1"></i>Not Baptized
                                                    </label>
                                                </div>
                                                <div class="form-check flex-fill">
                                                    <input class="form-check-input" type="radio" name="baptized" value="yes" id="baptized_yes">
                                                    <label class="form-check-label" for="baptized_yes">
                                                        <i class="bi bi-check-circle me-1"></i>Baptized
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="row g-2 pt-3 border-top mt-2">
                            <div class="col-md-4">
                                <a href="list.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-left me-1"></i>Back
                                </a>
                            </div>
                            <div class="col-md-4">
                                <button type="reset" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check-circle-fill me-1"></i>Add Member
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation feedback
        document.getElementById('memberForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#28a745';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>