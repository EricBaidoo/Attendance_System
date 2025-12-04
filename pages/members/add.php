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
                     (name, email, phone, phone2, gender, dob, location, occupation, 
                      department_id, congregation_group, baptized, status, date_joined) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())"
                );
                
                if ($stmt->execute([
                    $name, $email, $phone, $phone2, $gender, $dob, $location, $occupation,
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Member - Bridge Ministries International</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/mobile-responsive.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            max-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            overflow-x: hidden;
            overflow-y: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(120, 119, 198, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: 1;
        }

        .main-container {
            min-height: 100vh;
            padding: 0.75rem 0;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .member-form-card {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
            margin: 0 auto;
        }

        .form-header {
            background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
            color: white;
            padding: 1.25rem 1.5rem;
            text-align: center;
            position: relative;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .form-header * {
            position: relative;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .form-body {
            padding: 1.25rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            color: white;
            font-size: 0.9rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: #1a202c;
        }

        .form-floating {
            margin-bottom: 0.75rem;
        }

        .form-floating .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem;
            min-height: 45px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #1a202c;
        }

        .form-floating .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            transform: translateY(-1px);
            outline: none;
        }

        .form-floating label {
            padding: 0.75rem;
            font-weight: 600;
            color: #1a202c;
            font-size: 0.85rem;
        }

        .form-label {
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-check {
            background: rgba(13, 110, 253, 0.05);
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid rgba(13, 110, 253, 0.1);
            margin-bottom: 0.5rem;
        }

        .form-check-label {
            color: #1a202c;
            font-weight: 600;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 10px;
            padding: 0.6rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(40, 167, 69, 0.25);
            color: #ffffff;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(40, 167, 69, 0.35);
        }

        .btn-outline-secondary {
            border: 2px solid #6c757d;
            border-radius: 10px;
            padding: 0.6rem 1.25rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            color: #1a202c;
        }

        .btn-outline-warning {
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 0.6rem 1.25rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            color: #1a202c;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        .required {
            color: #e74c3c;
            font-weight: bold;
        }

        .personal-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        .contact-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
        }

        .church-info {
            background: linear-gradient(135deg, #e8e2ff 0%, #d4c5ff 100%);
        }

        .text-secondary {
            color: #4a5568 !important;
            font-weight: 500;
        }

        .text-purple {
            color: #7c3aed !important;
        }

        .form-floating .form-control::placeholder, .form-select option {
            color: #718096;
            font-weight: 400;
        }

        .form-floating .form-control:focus::placeholder {
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 0.5rem;
            }
            
            .form-header {
                padding: 1rem 1.25rem;
            }
            
            .form-body {
                padding: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                text-align: center;
                gap: 0.25rem;
            }
            
            .section-icon {
                margin-right: 0;
            }
        }

        @media (max-width: 576px) {
            .form-header {
                padding: 1rem;
            }
            
            .form-body {
                padding: 0.75rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>

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