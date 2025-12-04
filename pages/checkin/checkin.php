<?php
// Enhanced Check-in System with Smart Visitor Detection
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require '../../config/database.php';

$message = '';
$error = '';
$member_data = null;
$visitor_data = null;
$show_visitor_form = false;

// Test database connection
try {
    $test_count = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
    $db_status = "Connected - $test_count active members";
} catch (Exception $e) {
    $db_status = "Connection Failed: " . $e->getMessage();
    die("Database error: " . $e->getMessage());
}

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
    $error = "Unable to load services: " . $e->getMessage();
}

// Handle member lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_person'])) {
    $search_term = trim($_POST['search_term'] ?? '');
    
    if (empty($search_term)) {
        $error = "Please enter a name or phone number to search.";
    } else {
        try {
            // Search members first
            $member_sql = "SELECT m.*, d.name as department_name 
                          FROM members m 
                          LEFT JOIN departments d ON m.department_id = d.id 
                          WHERE (
                              m.phone LIKE ? OR 
                              REPLACE(REPLACE(REPLACE(REPLACE(m.phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ? OR
                              m.phone2 LIKE ? OR 
                              REPLACE(REPLACE(REPLACE(REPLACE(m.phone2, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ? OR
                              m.name LIKE ?
                          )
                          AND m.status = 'active'
                          LIMIT 1";
            
            $search_clean = preg_replace('/[^0-9]/', '', $search_term);
            $search_like = "%$search_term%";
            $search_clean_like = "%$search_clean%";
            
            $member_stmt = $pdo->prepare($member_sql);
            $member_stmt->execute([
                $search_like, $search_clean_like,
                $search_like, $search_clean_like,
                $search_like
            ]);
            $member_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member_data) {
                // Search visitors
                $visitor_sql = "SELECT * FROM visitors 
                               WHERE (
                                   phone LIKE ? OR 
                                   REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ? OR
                                   name LIKE ?
                               )
                               ORDER BY created_at DESC
                               LIMIT 1";
                
                $visitor_stmt = $pdo->prepare($visitor_sql);
                $visitor_stmt->execute([$search_like, $search_clean_like, $search_like]);
                $visitor_data = $visitor_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$visitor_data) {
                    // New visitor
                    $show_visitor_form = true;
                    $visitor_name = preg_match('/^[\d\s\(\)\-\+]+$/', $search_term) ? '' : $search_term;
                }
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle autocomplete AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['autocomplete'])) {
    header('Content-Type: application/json');
    $term = $_GET['term'] ?? '';
    $suggestions = [];
    
    if (strlen($term) >= 2) {
        try {
            $sql = "SELECT CONCAT(name, ' - ', COALESCE(phone, '')) as suggestion, name, phone, phone2
                    FROM members 
                    WHERE (name LIKE ? OR phone LIKE ? OR phone2 LIKE ?) AND status = 'active'
                    ORDER BY name 
                    LIMIT 10";
            
            $term_like = "%$term%";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$term_like, $term_like, $term_like]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $suggestions[] = [
                    'label' => $row['suggestion'],
                    'value' => $row['name'],
                    'name' => $row['name'],
                    'phone' => $row['phone']
                ];
            }
        } catch (Exception $e) {
            // Ignore errors for autocomplete
        }
    }
    
    echo json_encode($suggestions);
    exit;
}

// Handle check-in submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin'])) {
    $person_type = $_POST['person_type'] ?? '';
    $service_id = $_POST['service_id'] ?? '';
    
    if ($person_type === 'member') {
        $member_id = $_POST['member_id'] ?? '';
        
        if (empty($member_id) || empty($service_id)) {
            $error = "Please select a service to complete check-in.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get the session_id for the selected service
                $session_sql = "SELECT id FROM service_sessions WHERE service_id = ? AND status = 'open' AND session_date = CURDATE()";
                $session_stmt = $pdo->prepare($session_sql);
                $session_stmt->execute([$service_id]);
                $session_id = $session_stmt->fetchColumn();
                
                if (!$session_id) {
                    $error = "Selected service session is no longer available.";
                } else {
                    // Check if already checked in
                    $check_sql = "SELECT id FROM attendance WHERE member_id = ? AND service_id = ? AND DATE(date) = CURDATE()";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$member_id, $service_id]);
                    
                    if ($check_stmt->fetchColumn()) {
                        $error = "You have already checked in for this service today.";
                    } else {
                        // Record attendance
                        $attendance_sql = "INSERT INTO attendance (member_id, service_id, session_id, date, status, method) 
                                          VALUES (?, ?, ?, NOW(), 'present', 'auto')";
                        $pdo->prepare($attendance_sql)->execute([$member_id, $service_id, $session_id]);
                        
                        $member_name_sql = "SELECT name FROM members WHERE id = ?";
                        $member_name_stmt = $pdo->prepare($member_name_sql);
                        $member_name_stmt->execute([$member_id]);
                        $name = $member_name_stmt->fetchColumn();
                        
                        $message = "Welcome, " . htmlspecialchars($name) . "! Check-in successful.";
                        
                        // Clear form data
                        $_POST = [];
                        $member_data = null;
                    }
                }
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error recording attendance: " . $e->getMessage();
            }
        }
    } elseif ($person_type === 'visitor') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $location = trim($_POST['location'] ?? '');
        
        if (empty($name) || empty($phone) || empty($service_id)) {
            $error = "Please fill in name, phone, location, and select a service.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Check if already checked in today
                $check_sql = "SELECT id FROM visitors WHERE (name = ? OR phone = ?) AND DATE(date) = CURDATE()";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$name, $phone]);
                
                if ($check_stmt->fetchColumn()) {
                    $error = "You have already checked in today. Welcome back!";
                } else {
                    // Record visitor
                    $visitor_sql = "INSERT INTO visitors (name, phone, location, service_id, date, first_time, follow_up_needed, status, created_at) 
                                   VALUES (?, ?, ?, ?, CURDATE(), 'yes', 'yes', 'pending', NOW())";
                    $pdo->prepare($visitor_sql)->execute([$name, $phone, $location, $service_id]);
                    
                    $message = "Welcome, " . htmlspecialchars($name) . ", enjoy the service!";
                    
                    // Clear form data
                    $_POST = [];
                    $show_visitor_form = false;
                }
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error recording visitor: " . $e->getMessage();
            }
        }
    } elseif ($person_type === 'returning_visitor') {
        $visitor_id = $_POST['visitor_id'] ?? '';
        $name = $_POST['visitor_name'] ?? '';
        $phone = $_POST['visitor_phone'] ?? '';
        
        if (empty($service_id)) {
            $error = "Please select a service to complete check-in.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Record returning visitor
                $visitor_sql = "INSERT INTO visitors (name, phone, service_id, date, first_time, follow_up_needed, status, created_at) 
                               VALUES (?, ?, ?, CURDATE(), 'no', 'no', 'contacted', NOW())";
                $pdo->prepare($visitor_sql)->execute([$name, $phone, $service_id]);
                
                $message = "Welcome back, " . htmlspecialchars($name) . "! Thanks for visiting us again.";
                
                // Clear form data
                $_POST = [];
                $visitor_data = null;
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error recording visitor return: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Check-In - Bridge Ministries International</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/mobile-responsive.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            overflow-x: hidden;
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

        .checkin-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            z-index: 2;
        }

        .checkin-card {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            margin: 1rem;
        }

        .checkin-header {
            background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
            color: white;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            position: relative;
        }

        .checkin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .checkin-header * {
            position: relative;
        }

        .checkin-logo {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .checkin-body {
            padding: 2.5rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .page-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .page-description {
            font-size: 0.875rem;
            opacity: 0.75;
            font-weight: 400;
        }

        .search-section {
            margin-bottom: 2.5rem;
        }

        .search-input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-input {
            border: 2px solid #e9ecef;
            border-radius: 16px;
            padding: 1.125rem 130px 1.125rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            background: #ffffff;
            width: 100%;
        }

        .search-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
            transform: translateY(-1px);
            outline: none;
        }

        .search-btn {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border-radius: 12px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.25);
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #0b5ed7 0%, #0a58ca 100%);
            transform: translateY(-50%) scale(1.02);
            box-shadow: 0 4px 16px rgba(13, 110, 253, 0.35);
        }

        .search-hint {
            text-align: center;
            padding: 0.75rem;
            background: rgba(13, 110, 253, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(13, 110, 253, 0.1);
        }

        .autocomplete-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 15px 15px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .autocomplete-suggestion {
            padding: 1rem;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .autocomplete-suggestion:hover {
            background: #f8f9fa;
        }

        .autocomplete-suggestion:last-child {
            border-bottom: none;
        }

        .result-section {
            margin-top: 2rem;
        }

        .member-found {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(40, 167, 69, 0.15);
        }

        .visitor-found {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(255, 193, 7, 0.15);
        }

        .visitor-form {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px solid #2196f3;
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(33, 150, 243, 0.15);
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating .form-control {
            border-radius: 14px;
            border: 2px solid #e9ecef;
            padding: 1rem;
            height: 58px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .form-floating .form-control:focus {
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

        .service-select {
            border-radius: 14px;
            border: 2px solid #e9ecef;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            background: #ffffff;
            transition: all 0.3s ease;
            width: 100%;
        }

        .service-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .checkin-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 14px;
            padding: 1.125rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(40, 167, 69, 0.25);
        }

        .checkin-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(40, 167, 69, 0.35);
        }

        .alert-custom {
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .visitor-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 16px rgba(33, 150, 243, 0.25);
        }

        fieldset {
            border: none;
            margin: 0;
            padding: 0;
        }

        fieldset legend {
            float: none;
            width: auto;
            padding: 0;
            margin-bottom: 0;
            font-size: inherit;
            line-height: inherit;
        }

        .section-title {
            display: flex;
            align-items: center;
            font-weight: 700;
            color: #2196f3;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(33, 150, 243, 0.1);
        }

        .visitor-info-form {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(33, 150, 243, 0.1);
        }

        .form-actions {
            background: rgba(33, 150, 243, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .service-selection {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(33, 150, 243, 0.1);
        }

        .member-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 16px rgba(40, 167, 69, 0.25);
        }

        .member-checkin-form {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(40, 167, 69, 0.1);
        }

        .service-selection-member {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(40, 167, 69, 0.1);
        }

        .visitor-checkin-form {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 193, 7, 0.1);
        }

        .service-selection-visitor {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(255, 193, 7, 0.1);
        }

        .returning-visitor-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 16px rgba(255, 193, 7, 0.25);
        }

        .btn-outline-secondary {
            border: 2px solid #64748b;
            color: #64748b;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: #64748b;
            color: white;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .checkin-container {
                padding: 0.5rem;
            }
            
            .checkin-card {
                margin: 0.5rem;
                max-width: 100%;
            }
            
            .checkin-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .checkin-body {
                padding: 2rem 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .checkin-header {
                padding: 1.75rem 1.25rem 1.25rem;
            }
            
            .checkin-body {
                padding: 1.75rem 1.25rem;
            }
            
            .search-input {
                font-size: 1rem;
                padding: 1rem 115px 1rem 1.25rem;
            }
            
            .search-btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
                right: 5px;
            }
            
            .member-found, .visitor-found, .visitor-form {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 1.375rem;
            }
            
            .page-subtitle {
                font-size: 0.9rem;
            }
            
            .form-floating .form-control {
                height: 54px;
            }
            
            .checkin-btn {
                padding: 1rem 1.5rem;
                font-size: 1rem;
            }
        }

        @media (max-width: 400px) {
            .checkin-card {
                margin: 0.25rem;
            }
            
            .search-input {
                padding: 0.875rem 105px 0.875rem 1rem;
                font-size: 0.95rem;
            }
            
            .search-btn {
                padding: 0.625rem 1rem;
                font-size: 0.85rem;
                right: 4px;
            }
        }

        /* Enhanced focus states */
        *:focus {
            outline: none;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>

<body>
    <div class="checkin-container">
        <div class="checkin-card">
            <!-- Header -->
            <div class="checkin-header">
                <div class="checkin-logo">
                    <i class="bi bi-house-heart-fill fs-2" style="color: white;"></i>
                </div>
                <h3 class="fw-bold mb-2">Church Check-In</h3>
                <p class="mb-0 opacity-90">Bridge Ministries International</p>
                <small class="opacity-75">Enter your name or phone number to check in</small>
            </div>

            <!-- Body -->
            <div class="checkin-body">
                
                <?php if (!empty($message)): ?>
                <div class="alert-custom success-message">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <?php echo $message; ?>
                        </div>
                    </div>
                </div>
                <?php elseif (!empty($error)): ?>
                <div class="alert-custom error-message">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div>
                            <?php echo $error; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Search Section - Only show if no visitor form is active -->
                <?php if (!$show_visitor_form && !$member_data && !$visitor_data): ?>
                <div class="search-section">
                    <form method="POST">
                        <div class="search-input-group">
                            <input type="text" 
                                   class="form-control search-input" 
                                   name="search_term" 
                                   id="searchTerm"
                                   value="<?php echo htmlspecialchars($_POST['search_term'] ?? ''); ?>"
                                   placeholder="Enter your name or phone number"
                                   autocomplete="off" 
                                   required>
                            <button class="search-btn" type="submit" name="find_person">
                                <i class="bi bi-search me-1"></i>Find Me
                            </button>
                            <div id="searchSuggestions" class="autocomplete-suggestions"></div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="result-section">
                    <!-- Member Found -->
                    <?php if ($member_data): ?>
                    <div class="member-found">
                        <div class="text-center mb-4">
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="member-icon me-3">
                                    <i class="bi bi-person-check-fill"></i>
                                </div>
                                <div class="text-start">
                                    <h5 class="mb-1 text-success fw-bold">Welcome back!</h5>
                                    <h6 class="mb-1 text-dark"><?php echo htmlspecialchars($member_data['name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-building me-1"></i>
                                        <?php echo htmlspecialchars($member_data['department_name'] ?? 'Member'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" class="member-checkin-form">
                            <input type="hidden" name="member_id" value="<?php echo $member_data['id']; ?>">
                            <input type="hidden" name="person_type" value="member">
                            
                            <fieldset class="mb-4">
                                <legend class="section-title h6 text-success mb-3">
                                    <i class="bi bi-calendar-event me-2"></i>Service Selection
                                </legend>
                                
                                <div class="service-selection-member">
                                    <label class="form-label fw-medium">
                                        <i class="bi bi-clock me-2"></i>Select today's service *
                                    </label>
                                    <select class="form-select service-select" name="service_id" required>
                                        <option value="">Choose a service to attend</option>
                                        <?php foreach ($active_services as $service): ?>
                                            <option value="<?php echo $service['service_id']; ?>">
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                                <?php if ($service['description']): ?>
                                                    - <?php echo htmlspecialchars($service['description']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </fieldset>
                            
                            <div class="form-actions text-center">
                                <button type="submit" name="checkin" class="checkin-btn">
                                    <i class="bi bi-check-circle me-2"></i>Check In Now
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Returning Visitor Found -->
                    <?php elseif ($visitor_data): ?>
                    <div class="visitor-found">
                        <div class="text-center mb-4">
                            <div class="d-flex align-items-center justify-content-center mb-3">
                                <div class="returning-visitor-icon me-3">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <div class="text-start">
                                    <h5 class="mb-1 text-dark fw-bold">Welcome back!</h5>
                                    <h6 class="mb-1 text-warning"><?php echo htmlspecialchars($visitor_data['name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-heart me-1"></i>
                                        Thanks for visiting us again
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" class="visitor-checkin-form">
                            <input type="hidden" name="person_type" value="returning_visitor">
                            <input type="hidden" name="visitor_id" value="<?php echo $visitor_data['id']; ?>">
                            <input type="hidden" name="visitor_name" value="<?php echo htmlspecialchars($visitor_data['name']); ?>">
                            <input type="hidden" name="visitor_phone" value="<?php echo htmlspecialchars($visitor_data['phone'] ?? ''); ?>">
                            
                            <fieldset class="mb-4">
                                <legend class="section-title h6 text-warning mb-3">
                                    <i class="bi bi-calendar-event me-2"></i>Service Selection
                                </legend>
                                
                                <div class="service-selection-visitor">
                                    <label class="form-label fw-medium">
                                        <i class="bi bi-clock me-2"></i>Select today's service *
                                    </label>
                                    <select class="form-select service-select" name="service_id" required>
                                        <option value="">Choose a service to attend</option>
                                        <?php foreach ($active_services as $service): ?>
                                            <option value="<?php echo $service['service_id']; ?>">
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                                <?php if ($service['description']): ?>
                                                    - <?php echo htmlspecialchars($service['description']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </fieldset>
                            
                            <div class="form-actions text-center">
                                <button type="submit" name="checkin" class="checkin-btn">
                                    <i class="bi bi-check-circle me-2"></i>Check In Now
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- New Visitor Form -->
                    <?php elseif ($show_visitor_form): ?>
                    <div class="visitor-form">
                        <div class="text-center mb-4">
                            <div class="visitor-icon">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <h5 class="text-primary fw-bold mb-2">First Time Visitor</h5>
                            <p class="text-muted mb-0">Welcome! Please provide your information</p>
                        </div>
                        
                        <form method="POST" class="visitor-info-form">
                            <input type="hidden" name="person_type" value="visitor">
                            
                            <fieldset class="mb-4">
                                <legend class="section-title h6 text-primary mb-3">
                                    <i class="bi bi-person me-2"></i>Personal Information
                                </legend>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="visitorName" 
                                                   name="name" 
                                                   placeholder="Your full name"
                                                   value="<?php echo isset($visitor_name) ? htmlspecialchars($visitor_name) : ''; ?>" 
                                                   required>
                                            <label for="visitorName">
                                                <i class="bi bi-person me-2"></i>Full Name *
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="visitorPhone" 
                                                   name="phone" 
                                                   placeholder="Your phone number"
                                                   required>
                                            <label for="visitorPhone">
                                                <i class="bi bi-telephone me-2"></i>Phone Number *
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="visitorLocation" 
                                                   name="location" 
                                                   placeholder="City or area">
                                            <label for="visitorLocation">
                                                <i class="bi bi-geo-alt me-2"></i>Location
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>
                            
                            <fieldset class="mb-4">
                                <legend class="section-title h6 text-primary mb-3">
                                    <i class="bi bi-calendar-event me-2"></i>Service Selection
                                </legend>
                                
                                <div class="service-selection">
                                    <label class="form-label fw-medium">
                                        <i class="bi bi-clock me-2"></i>Select today's service *
                                    </label>
                                    <select class="form-select service-select" name="service_id" required>
                                        <option value="">Choose a service to attend</option>
                                        <?php foreach ($active_services as $service): ?>
                                            <option value="<?php echo $service['service_id']; ?>">
                                                <i class="bi bi-calendar-check"></i>
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                                <?php if ($service['description']): ?>
                                                    - <?php echo htmlspecialchars($service['description']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </fieldset>
                            
                            <div class="form-actions text-center">
                                <button type="submit" name="checkin" class="checkin-btn">
                                    <i class="bi bi-check-circle me-2"></i>Complete Check-In
                                </button>
                                <p class="text-muted mt-3 mb-0">
                                    <small>
                                        <i class="bi bi-shield-check me-1"></i>
                                        Your information is secure and will be used for church communication only
                                    </small>
                                </p>
                            </div>
                        </form>
                    </div>
                            
                            <div class="form-floating">
                                <input type="text" 
                                       class="form-control" 
                                       id="visitorName" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($visitor_name ?? ''); ?>" 
                                       placeholder="Full Name" 
                                       required>
                                <label for="visitorName">Full Name</label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="tel" 
                                       class="form-control" 
                                       id="visitorPhone" 
                                       name="phone" 
                                       placeholder="Phone Number" 
                                       required>
                                <label for="visitorPhone">Phone Number</label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="text" 
                                       class="form-control" 
                                       id="visitorLocation" 
                                       name="location" 
                                       placeholder="City or Area" 
                                       required>
                                <label for="visitorLocation">Location (City/Area)</label>
                            </div>
                            
                            <label class="form-label fw-medium">Select today's service:</label>
                            <select class="form-select service-select" name="service_id" required>
                                <option value="">Choose a service</option>
                                <?php foreach ($active_services as $service): ?>
                                    <option value="<?php echo $service['service_id']; ?>">
                                        <?php echo htmlspecialchars($service['service_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div class="d-grid gap-2 mb-2">
                                <button type="submit" name="checkin" class="checkin-btn">
                                    <i class="bi bi-heart me-2"></i>Check In
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <button type="button" onclick="window.location.href='checkin.php'" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i>Search Again
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced autocomplete functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchTerm');
            const suggestionsContainer = document.getElementById('searchSuggestions');
            
            let searchTimeout;
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim();
                    
                    clearTimeout(searchTimeout);
                    
                    if (query.length < 2) {
                        suggestionsContainer.style.display = 'none';
                        return;
                    }
                    
                    searchTimeout = setTimeout(() => {
                        fetch(`?autocomplete=1&term=${encodeURIComponent(query)}`)
                            .then(response => response.json())
                            .then(data => {
                                showSuggestions(data);
                            })
                            .catch(error => {
                                console.error('Autocomplete error:', error);
                            });
                    }, 300);
                });
                
                function showSuggestions(suggestions) {
                    if (suggestions.length === 0) {
                        suggestionsContainer.style.display = 'none';
                        return;
                    }
                    
                    suggestionsContainer.innerHTML = '';
                    suggestions.forEach((suggestion) => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-suggestion';
                        div.innerHTML = `<strong>${suggestion.name}</strong> ${suggestion.phone ? '- ' + suggestion.phone : ''}`;
                        
                        div.addEventListener('click', function() {
                            searchInput.value = suggestion.value;
                            suggestionsContainer.style.display = 'none';
                        });
                        
                        suggestionsContainer.appendChild(div);
                    });
                    
                    suggestionsContainer.style.display = 'block';
                }
                
                // Hide suggestions when clicking outside
                document.addEventListener('click', function(e) {
                    if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                        suggestionsContainer.style.display = 'none';
                    }
                });
            }

            // Auto-focus search input
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }

            // Phone number formatting
            const phoneInput = document.getElementById('visitorPhone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length >= 6) {
                        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                    } else if (value.length >= 3) {
                        value = value.replace(/(\d{3})(\d+)/, '($1) $2');
                    }
                    this.value = value;
                });
            }
        });
    </script>
</body>
</html>