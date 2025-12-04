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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Visitor - Bridge Ministries International</title>
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
            padding: 1rem 0;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .visitor-form-card {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 24px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
            margin: 0 auto;
        }

        .form-header {
            background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
            color: white;
            padding: 1.5rem 1.5rem;
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
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .form-body {
            padding: 1.5rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .section-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            color: white;
            font-size: 1rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-floating .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.875rem;
            min-height: 50px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .form-floating .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
            transform: translateY(-1px);
            outline: none;
        }

        .form-floating label {
            padding: 1rem;
            font-weight: 600;
            color: #6c757d;
            font-size: 0.95rem;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .form-check {
            background: rgba(13, 110, 253, 0.05);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(13, 110, 253, 0.1);
            margin-bottom: 0.5rem;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(13, 110, 253, 0.25);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(13, 110, 253, 0.35);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 14px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(40, 167, 69, 0.25);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(40, 167, 69, 0.35);
        }

        .btn-outline-secondary {
            border: 2px solid #6c757d;
            border-radius: 14px;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-warning {
            border: 2px solid #ffc107;
            border-radius: 14px;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .alert {
            border-radius: 16px;
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        .required {
            color: #dc3545;
            font-weight: bold;
        }

        .personal-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        .visit-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .form-header {
                padding: 2rem 1.5rem;
            }
            
            .form-body {
                padding: 2rem 1.5rem;
            }
            
            .section-header {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .section-icon {
                margin-right: 0;
            }
        }

        @media (max-width: 576px) {
            .form-header {
                padding: 1.75rem 1.25rem;
            }
            
            .form-body {
                padding: 1.75rem 1.25rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        .conditional-field {
            background: rgba(255, 193, 7, 0.05);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(255, 193, 7, 0.2);
            margin-top: 1rem;
        }
    </style>
</head>
<body>

    <div class="main-container">
        <!-- Error Messages -->
        <?php if (isset($error)): ?>
        <div class="container mb-4">
            <div class="alert alert-danger shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                    <div>
                        <h6 class="mb-1 fw-bold">Error Adding Visitor</h6>
                        <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="container">
            <div class="visitor-form-card">
                <!-- Header -->
                <div class="form-header">
                    <div class="header-icon">
                        <i class="bi bi-person-badge-fill fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-2">Add New Visitor</h3>
                    <p class="mb-0 opacity-90">Bridge Ministries International</p>
                    <small class="opacity-75">Register a church visitor to the system</small>
                </div>

                <!-- Body -->
                <div class="form-body">
                    <form method="POST" id="visitorForm">
                        <!-- Personal Information Section -->
                        <div class="mb-3">
                            <div class="section-header">
                                <div class="section-icon personal-info">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div>
                                    <h6 class="section-title text-primary mb-0">Visitor Information</h6>
                                    <small class="text-muted">Basic contact details</small>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" 
                                               class="form-control" 
                                               id="visitorName" 
                                               name="name" 
                                               placeholder="Full Name"
                                               required>
                                        <label for="visitorName">
                                            <i class="bi bi-person me-2"></i>Full Name <span class="required">*</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="bi bi-star me-2"></i>Visit Type
                                    </label>
                                    <select class="form-select" name="first_time">
                                        <option value="yes">First Time Visitor</option>
                                        <option value="no">Return Visitor</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" 
                                               class="form-control" 
                                               id="visitorEmail" 
                                               name="email" 
                                               placeholder="Email Address">
                                        <label for="visitorEmail">
                                            <i class="bi bi-envelope me-2"></i>Email Address
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" 
                                               class="form-control" 
                                               id="visitorPhone" 
                                               name="phone" 
                                               placeholder="Phone Number">
                                        <label for="visitorPhone">
                                            <i class="bi bi-telephone me-2"></i>Phone Number
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Visit Details Section -->
                        <div class="mb-3">
                            <div class="section-header">
                                <div class="section-icon visit-info">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                                <div>
                                    <h6 class="section-title text-success mb-0">Visit Details</h6>
                                    <small class="text-muted">Service and outreach info</small>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-check me-2"></i>Service <span class="required">*</span>
                                    </label>
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
                                    <label class="form-label">
                                        <i class="bi bi-megaphone me-2"></i>How Did You Hear About Us?
                                    </label>
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
                                    <label class="form-label">
                                        <i class="bi bi-people me-2"></i>Invited/Referred By
                                    </label>
                                    <select class="form-select" name="invited_by_type" id="invited_by_type" onchange="toggleInvitedByInput()">
                                        <option value="">Select Option</option>
                                        <option value="member">Church Member</option>
                                        <option value="social_media">Social Media</option>
                                        <option value="website">Website</option>
                                        <option value="self">Came by Themselves</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="bi bi-telephone-forward me-2"></i>Follow-up Needed?
                                    </label>
                                    <div class="d-flex gap-3 pt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="follow_up_needed" value="no" id="followup_no" checked>
                                            <label class="form-check-label" for="followup_no">
                                                <i class="bi bi-x-circle me-1"></i>No Follow-up
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="follow_up_needed" value="yes" id="followup_yes">
                                            <label class="form-check-label" for="followup_yes">
                                                <i class="bi bi-check-circle me-1"></i>Needs Follow-up
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12 d-none conditional-field" id="invited_by_details">
                                    <div class="form-floating">
                                        <input type="text" 
                                               class="form-control" 
                                               name="invited_by_details" 
                                               id="invited_by_details_input"
                                               placeholder="Specific Details">
                                        <label for="invited_by_details_input">
                                            <i class="bi bi-info-circle me-2"></i>Specific Details
                                        </label>
                                    </div>
                                    <small class="form-text text-muted mt-2">
                                        <i class="bi bi-lightbulb me-1"></i>
                                        For members: Enter full name. For social media: Specify platform (Facebook, Instagram, etc.)
                                    </small>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea class="form-control" 
                                                  id="notes" 
                                                  name="notes" 
                                                  style="height: 70px"
                                                  placeholder="Additional Notes"></textarea>
                                        <label for="notes">
                                            <i class="bi bi-journal-text me-2"></i>Notes
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="row g-2 pt-3 border-top">
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
                                    <i class="bi bi-check-circle-fill me-1"></i>Add Visitor
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
                        detailsInput.placeholder = 'Specify platform (Facebook, Instagram, TikTok, etc.)...';
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
        
        // Form validation feedback
        document.getElementById('visitorForm').addEventListener('submit', function(e) {
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