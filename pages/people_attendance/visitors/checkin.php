<?php
// Public visitor check-in (no login required)
require_once '../../../includes/people_sync.php';

$message = '';
$error = '';

// Display success messages from redirect
if (isset($_GET['success'])) {
    $visitor_name = $_GET['name'] ?? 'Guest';
    if ($_GET['success'] === 'returning') {
        $message = "ðŸ‘‹ Welcome back, " . htmlspecialchars($visitor_name) . "! Thank you for visiting us again today.";
    } else if ($_GET['success'] === 'new') {
        $message = "ðŸŽ‰ Welcome to our church, " . htmlspecialchars($visitor_name) . "! We're so excited you're here. Someone from our team will follow up with you soon.";
    }
}

// Database connection
try {
    require '../../../config/database.php';
    
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

            $identity_conditions = [];
            $identity_params = [];
            if (!empty($phone)) {
                $identity_conditions[] = "phone = ?";
                $identity_params[] = $phone;
            }
            if (!empty($email)) {
                $identity_conditions[] = "email = ?";
                $identity_params[] = $email;
            }

            $today_check_sql = "SELECT id FROM visitor_roles WHERE service_id = ? AND DATE(date) = CURDATE()";
            $today_check_params = [$service_id];

            if (!empty($identity_conditions)) {
                $today_check_sql .= " AND (" . implode(' OR ', $identity_conditions) . ")";
                $today_check_params = array_merge($today_check_params, $identity_params);
            } else {
                $today_check_sql .= " AND name = ?";
                $today_check_params[] = $name;
            }

            $today_check_stmt = $pdo->prepare($today_check_sql);
            $today_check_stmt->execute($today_check_params);

            if ($today_check_stmt->fetchColumn()) {
                $error = "You have already checked in for this service today. Welcome back!";
                $pdo->rollBack();
            } else {
                $existing_visitor = null;
                if (!empty($identity_conditions)) {
                    $existing_sql = "SELECT id, first_time, how_heard, invited_by
                                     FROM visitor_roles
                                     WHERE " . implode(' OR ', $identity_conditions) . "
                                     ORDER BY created_at DESC
                                     LIMIT 1";
                    $existing_stmt = $pdo->prepare($existing_sql);
                    $existing_stmt->execute($identity_params);
                    $existing_visitor = $existing_stmt->fetch();
                }

                $visit_first_time = $existing_visitor ? 'no' : (($is_first_time === 'no') ? 'no' : 'yes');
                $visit_how_heard = !empty($how_heard)
                    ? $how_heard
                    : (!empty($existing_visitor['how_heard']) ? $existing_visitor['how_heard'] : null);
                $visit_invited_by = !empty($invited_by)
                    ? $invited_by
                    : (!empty($existing_visitor['invited_by']) ? $existing_visitor['invited_by'] : null);
                $follow_up_needed = $visit_first_time === 'yes' ? 'yes' : 'no';
                $status = $visit_first_time === 'yes' ? 'pending' : 'contacted';

                $insert_sql = "INSERT INTO visitor_roles
                    (name, phone, email, service_id, date, first_time, how_heard, invited_by, follow_up_needed, status, created_at)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, NOW())";

                $vis_insert_stmt = $pdo->prepare($insert_sql);
                $vis_insert_stmt->execute([
                    $name,
                    $phone,
                    $email,
                    $service_id,
                    $visit_first_time,
                    $visit_how_heard,
                    $visit_invited_by,
                    $follow_up_needed,
                    $status
                ]);
                $new_visitor_id = (int)$pdo->lastInsertId();

                peopleSyncRecord(
                    $pdo,
                    'visitors',
                    $new_visitor_id,
                    $name,
                    $email,
                    $phone,
                    'visitor',
                    'visitor_checked_in',
                    'Visitor created from public check-in form'
                );

                $pdo->commit();

                $message_type = ($existing_visitor || $visit_first_time === 'no') ? 'returning' : 'new';
                header('Location: checkin?success=' . $message_type . '&name=' . urlencode($name));
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
    <link href="../../../assets/css/visitors.css" rel="stylesheet">
</head>
<body class="checkin-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="card checkin-card">
                    <div class="checkin-header">
                        <div class="welcome-icon welcome-icon-large">
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
                                    <i class="bi bi-clock clock-icon-large"></i>
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
                                    
                                    <div id="invited_by_details_section" class="mt-3 d-none">
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
