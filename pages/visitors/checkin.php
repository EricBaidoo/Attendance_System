<?php
// Public visitor check-in (no login required)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$error = '';

// Database connection
try {
    require '../../config/database.php';
    
    // Get today's active services
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

// Handle visitor check-in submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_visitor'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service_id = $_POST['service_id'] ?? '';
    $is_first_time = $_POST['first_time'] ?? 'yes';
    $how_heard = trim($_POST['how_heard'] ?? '');
    $invited_by_type = $_POST['invited_by_type'] ?? '';
    $invited_by_details = trim($_POST['invited_by_details'] ?? '');
    
    if (empty($name) || empty($service_id)) {
        $error = "Name and service selection are required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Format invited_by
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
            
            // Check if visitor exists (by phone or email)
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
                              first_time = 'no', how_heard = COALESCE(?, how_heard),
                              invited_by = COALESCE(?, invited_by)
                              WHERE id = ?";
                $pdo->prepare($update_sql)->execute([$name, $phone, $email, $how_heard, $invited_by, $visitor_id]);
            } else {
                // Create new visitor
                $insert_sql = "INSERT INTO visitors (name, phone, email, first_time, how_heard, invited_by,
                              service_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($insert_sql)->execute([$name, $phone, $email, $is_first_time, $how_heard, $invited_by, $service_id]);
                $visitor_id = $pdo->lastInsertId();
            }
            
            // Record visitor attendance
            $attendance_sql = "INSERT INTO visitor_attendance (visitor_id, service_id, visit_date, created_at) 
                              VALUES (?, ?, CURDATE(), NOW()) 
                              ON DUPLICATE KEY UPDATE visit_number = visit_number + 1";
            $pdo->prepare($attendance_sql)->execute([$visitor_id, $service_id]);
            
            $pdo->commit();
            $message = "Welcome! Your attendance has been recorded successfully.";
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Sorry, there was an error recording your attendance. Please try again or see a staff member.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Check-In - Church Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/visitors.css" rel="stylesheet">
</head>
<body class="checkin-page">
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .checkin-card {
            background: rgba(255, 255, 255, 0.98);
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
        }
        .checkin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 20px 20px 0 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .checkin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        .checkin-header h1, .checkin-header p {
            position: relative;
            z-index: 1;
        }
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 15px 18px;
            transition: all 0.3s ease;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
            background: white;
            transform: translateY(-1px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 15px 35px;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:active {
            transform: translateY(-1px);
        }
        .alert {
            border: none;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .alert-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        .alert-danger {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }
        .alert-warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
        }
        .service-card {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .service-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }
        .service-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        .form-label {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .card-body {
            padding: 40px !important;
        }
        .welcome-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        .first-time-section {
            background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%);
            border: 2px solid #9ae6b4;
            border-radius: 12px;
            padding: 20px;
        }
        @media (max-width: 768px) {
            .card-body {
                padding: 25px !important;
            }
            .checkin-header {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body class="checkin-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="card checkin-card">
                    <div class="checkin-header">
                        <div class="welcome-icon">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                        <h1 class="mb-2">Welcome!</h1>
                        <p class="mb-0 fs-5">We're so glad you're here with us today</p>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <strong>Success!</strong> <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($active_services)): ?>
                            <div class="alert alert-warning text-center" role="alert">
                                <div class="mb-3">
                                    <i class="bi bi-clock" style="font-size: 3rem;"></i>
                                </div>
                                <h4><strong>No Active Services</strong></h4>
                                <p class="mb-0">Currently there are no open services for check-in. Please contact a staff member for assistance.</p>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="mb-4">
                                    <label for="name" class="form-label">
                                        <i class="bi bi-person-fill me-2"></i>Full Name *
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           placeholder="Enter your full name"
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="phone" class="form-label">
                                            <i class="bi bi-telephone-fill me-2"></i>Phone Number
                                        </label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               placeholder="(555) 123-4567"
                                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">
                                            <i class="bi bi-envelope-fill me-2"></i>Email Address
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="your@email.com"
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-event me-2"></i>Select Today's Service *
                                    </label>
                                    <?php foreach ($active_services as $service): ?>
                                        <div class="service-card p-3 mb-2" onclick="selectService(<?= $service['id'] ?>)">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="service_id" 
                                                       id="service_<?= $service['id'] ?>" value="<?= $service['id'] ?>" required>
                                                <label class="form-check-label w-100" for="service_<?= $service['id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong class="fs-6"><?= htmlspecialchars($service['service_name']) ?></strong>
                                                            <?php if ($service['description']): ?>
                                                                <br><small class="opacity-75"><?= htmlspecialchars($service['description']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <i class="bi bi-arrow-right-circle"></i>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="first-time-section mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-star-fill me-2"></i>Is this your first time visiting?
                                    </label>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="first_time" 
                                                       id="first_yes" value="yes" checked>
                                                <label class="form-check-label" for="first_yes">
                                                    <strong>Yes, first time!</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="first_time" 
                                                       id="first_no" value="no">
                                                <label class="form-check-label" for="first_no">
                                                    I've been here before
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="how_heard" class="form-label">
                                        <i class="bi bi-chat-dots-fill me-2"></i>How did you hear about us?
                                    </label>
                                    <input type="text" class="form-control" id="how_heard" name="how_heard" 
                                           placeholder="Friend, Website, Social Media, Flyer, etc."
                                           value="<?= htmlspecialchars($_POST['how_heard'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-people-fill me-2"></i>Were you invited by someone?
                                    </label>
                                    <select class="form-select" name="invited_by_type" id="invited_by_type">
                                        <option value="">Select option (optional)</option>
                                        <option value="member">A church member invited me</option>
                                        <option value="social_media">Found through social media</option>
                                        <option value="website">Found through website</option>
                                        <option value="self">Found on my own</option>
                                        <option value="other">Other</option>
                                    </select>
                                    
                                    <div id="invited_by_details_section" class="mt-3" style="display: none;">
                                        <input type="text" class="form-control" name="invited_by_details" 
                                               id="invited_by_details" placeholder="Please specify...">
                                    </div>
                                </div>
                                
                                <div class="d-grid mt-5">
                                    <button type="submit" name="checkin_visitor" class="btn btn-primary btn-lg">
                                        <i class="bi bi-check-circle-fill me-2"></i>
                                        Check Me In
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Service selection handler
        function selectService(serviceId) {
            // Remove selected class from all service cards
            document.querySelectorAll('.service-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Check the radio button and add selected class
            const radio = document.getElementById('service_' + serviceId);
            radio.checked = true;
            radio.closest('.service-card').classList.add('selected');
        }
        
        // Initialize service card clicks
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.service-card').forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Remove selected class from all cards
                    document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
                    // Add selected class to clicked card
                    this.classList.add('selected');
                });
            });
        });
        
        // Show/hide invited by details based on selection
        document.getElementById('invited_by_type').addEventListener('change', function() {
            const detailsSection = document.getElementById('invited_by_details_section');
            const detailsInput = document.getElementById('invited_by_details');
            
            if (this.value === 'member' || this.value === 'social_media' || this.value === 'other') {
                detailsSection.style.display = 'block';
                
                // Update placeholder based on selection
                if (this.value === 'member') {
                    detailsInput.placeholder = "Enter the member's name who invited you...";
                } else if (this.value === 'social_media') {
                    detailsInput.placeholder = "Which platform? (Facebook, Instagram, etc.)";
                } else {
                    detailsInput.placeholder = "Please specify...";
                }
            } else {
                detailsSection.style.display = 'none';
                detailsInput.value = '';
            }
        });
        
        // Form enhancement
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading state to submit button
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function() {
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
                    submitBtn.disabled = true;
                });
            }
        });
    </script>
</body>
</html>