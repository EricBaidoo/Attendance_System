<?php
// Enhanced Check-in System with Smart Visitor Detection
require_once '../../../includes/people_sync.php';

// Database connection
require '../../../config/database.php';

$message = '';
$error = '';
$member_data = null;
$visitor_data = null;
$show_visitor_form = false;

// Handle success messages from redirect
if (isset($_GET['success']) && isset($_GET['name'])) {
    $name = htmlspecialchars($_GET['name']);
    switch ($_GET['success']) {
        case 'checkin':
            $message = "Welcome, $name! Check-in successful.";
            break;
        case 'visitor':
            $message = "Welcome, $name, enjoy the service!";
            break;
        case 'returning':
            $message = "Welcome back, $name! Thanks for visiting us again.";
            break;
    }
}

// Test database connection
try {
    $test_count = $pdo->query("SELECT COUNT(*) FROM member_roles WHERE status = 'active'")->fetchColumn();
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
            $member_sql = "SELECT m.*, p.full_name AS name, p.phone, p.alt_phone AS phone2, p.email, d.name as department_name 
                          FROM member_roles m 
                          JOIN people p ON p.id = m.person_id
                          LEFT JOIN departments d ON m.department_id = d.id 
                          WHERE m.status = 'active'
                          AND (
                              p.full_name LIKE ? OR
                              p.phone LIKE ? OR 
                              REPLACE(REPLACE(REPLACE(REPLACE(p.phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ? OR
                              p.alt_phone LIKE ? OR 
                              REPLACE(REPLACE(REPLACE(REPLACE(p.alt_phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?
                          )
                          ORDER BY 
                              CASE 
                                  WHEN p.full_name = ? THEN 1
                                  WHEN p.full_name LIKE ? THEN 2
                                  ELSE 3
                              END
                          LIMIT 1";
            
            $search_clean = preg_replace('/[^0-9]/', '', $search_term);
            $search_like = "%$search_term%";
            $search_clean_like = "%$search_clean%";
            
            $member_stmt = $pdo->prepare($member_sql);
            $member_stmt->execute([
                $search_like,           // name LIKE
                $search_like,           // phone LIKE
                $search_clean_like,     // phone cleaned LIKE
                $search_like,           // phone2 LIKE
                $search_clean_like,     // phone2 cleaned LIKE
                $search_term,           // exact name match for ORDER BY
                $search_like            // partial name match for ORDER BY
            ]);
            $member_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member_data) {
                // Search visitors
                $visitor_sql = "SELECT vr.*, p.full_name AS name, p.phone, p.email FROM visitor_roles vr
                               JOIN people p ON p.id = vr.person_id
                               WHERE (
                                   p.phone LIKE ? OR 
                                   REPLACE(REPLACE(REPLACE(REPLACE(p.phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ? OR
                                   p.full_name LIKE ?
                               )
                               ORDER BY vr.created_at DESC
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
                $sql = "SELECT CONCAT(p.full_name, ' - ', COALESCE(p.phone, '')) as suggestion, p.full_name AS name, p.phone, p.alt_phone AS phone2
                    FROM member_roles m
                    JOIN people p ON p.id = m.person_id
                    WHERE (p.full_name LIKE ? OR p.phone LIKE ? OR p.alt_phone LIKE ?) AND m.status = 'active'
                    ORDER BY p.full_name 
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
            
            // Reload member data to display on the form
            if ($member_id) {
                try {
                    $member_sql = "SELECT m.*, p.full_name AS name, p.phone, p.alt_phone AS phone2, p.email, d.name as department_name 
                                  FROM member_roles m 
                                  JOIN people p ON p.id = m.person_id
                                  LEFT JOIN departments d ON m.department_id = d.id 
                                  WHERE m.id = ?";
                    $member_stmt = $pdo->prepare($member_sql);
                    $member_stmt->execute([$member_id]);
                    $member_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // Ignore reload errors
                }
            }
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
                    
                    // Reload member data
                    try {
                        $member_sql = "SELECT m.*, p.full_name AS name, p.phone, p.alt_phone AS phone2, p.email, d.name as department_name 
                                      FROM member_roles m 
                                      JOIN people p ON p.id = m.person_id
                                      LEFT JOIN departments d ON m.department_id = d.id 
                                      WHERE m.id = ?";
                        $member_stmt = $pdo->prepare($member_sql);
                        $member_stmt->execute([$member_id]);
                        $member_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        // Ignore reload errors
                    }
                } else {
                    // Check if already checked in for this specific session
                    $check_sql = "SELECT id FROM attendance WHERE member_id = ? AND session_id = ? AND DATE(date) = CURDATE()";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$member_id, $session_id]);
                    
                    if ($check_stmt->fetchColumn()) {
                        $error = "You have already checked in for this service today.";
                        
                        // Reload member data
                        try {
                            $member_sql = "SELECT m.*, p.full_name AS name, p.phone, p.alt_phone AS phone2, p.email, d.name as department_name 
                                          FROM member_roles m 
                                          JOIN people p ON p.id = m.person_id
                                          LEFT JOIN departments d ON m.department_id = d.id 
                                          WHERE m.id = ?";
                            $member_stmt = $pdo->prepare($member_sql);
                            $member_stmt->execute([$member_id]);
                            $member_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            // Ignore reload errors
                        }
                    } else {
                        // Record attendance
                        $attendance_sql = "INSERT INTO attendance (member_id, service_id, session_id, date, status, method) 
                                          VALUES (?, ?, ?, NOW(), 'present', 'auto')";
                        $pdo->prepare($attendance_sql)->execute([$member_id, $service_id, $session_id]);
                        
                        $member_name_sql = "SELECT p.full_name AS name, m.person_id FROM member_roles m JOIN people p ON p.id = m.person_id WHERE m.id = ?";
                        $member_name_stmt = $pdo->prepare($member_name_sql);
                        $member_name_stmt->execute([$member_id]);
                        $member_row = $member_name_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        $name = (string)($member_row['name'] ?? 'Member');

                        if (!empty($member_row['person_id'])) {
                            peopleEnsureStage($pdo, (int)$member_row['person_id'], 'member');
                        }
                        
                        $message = "Welcome, " . htmlspecialchars($name) . "! Check-in successful.";
                        
                        // Clear form data and redirect to prevent resubmission
                        $pdo->commit();
                        header('Location: checkin.php?success=checkin&name=' . urlencode($name));
                        exit;
                    }
                }
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error recording attendance: " . $e->getMessage();
                
                // Reload member data to display on the form
                if ($member_id) {
                    try {
                        $member_sql = "SELECT m.*, p.full_name AS name, p.phone, p.alt_phone AS phone2, p.email, d.name as department_name 
                                      FROM member_roles m 
                                      JOIN people p ON p.id = m.person_id
                                      LEFT JOIN departments d ON m.department_id = d.id 
                                      WHERE m.id = ?";
                        $member_stmt = $pdo->prepare($member_sql);
                        $member_stmt->execute([$member_id]);
                        $member_data = $member_stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $inner_e) {
                        // Ignore reload errors
                    }
                }
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
                
                // Check if already checked in for this specific service today
                $check_sql = "SELECT vr.id FROM visitor_roles vr JOIN people p ON p.id = vr.person_id WHERE (p.full_name = ? OR p.phone = ?) AND vr.service_id = ? AND DATE(vr.date) = CURDATE()";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([$name, $phone, $service_id]);
                
                if ($check_stmt->fetchColumn()) {
                    $error = "You have already checked in for this service today. Welcome back!";
                } else {
                    // Record visitor
                    $person_id = peopleFindOrCreate($pdo, $name, null, $phone, 'visitor');
                    $visitor_sql = "INSERT INTO visitor_roles (person_id, location, service_id, date, first_time, follow_up_needed, status, created_at) 
                                   VALUES (?, ?, ?, NOW(), 'yes', 'yes', 'pending', NOW())";
                    $pdo->prepare($visitor_sql)->execute([$person_id, $location, $service_id]);
                    $new_visitor_id = (int)$pdo->lastInsertId();
                    if ($person_id) {
                        peopleAddLifecycleEvent($pdo, (int)$person_id, 'visitor_checked_in', 'visitor_roles', $new_visitor_id, 'Visitor created from checkin kiosk');
                    }
                    
                    $message = "Welcome, " . htmlspecialchars($name) . ", enjoy the service!";
                    
                    // Clear form data and redirect
                    $pdo->commit();
                    header('Location: checkin.php?success=visitor&name=' . urlencode($name));
                    exit;
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
                $person_id = peopleFindOrCreate($pdo, $name, null, $phone, 'visitor');
                $visitor_sql = "INSERT INTO visitor_roles (person_id, service_id, date, first_time, follow_up_needed, status, created_at) 
                               VALUES (?, ?, NOW(), 'no', 'no', 'contacted', NOW())";
                $pdo->prepare($visitor_sql)->execute([$person_id, $service_id]);
                $new_visitor_id = (int)$pdo->lastInsertId();
                if ($person_id) {
                    peopleAddLifecycleEvent($pdo, (int)$person_id, 'visitor_checked_in', 'visitor_roles', $new_visitor_id, 'Returning visitor check-in kiosk record');
                }
                
                $message = "Welcome back, " . htmlspecialchars($name) . "! Thanks for visiting us again.";
                
                // Clear form data and redirect
                $pdo->commit();
                header('Location: checkin.php?success=returning&name=' . urlencode($name));
                exit;
                
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
    <title>Church Check-In — Bridge Ministries International</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../../assets/css/checkin.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>

<body class="checkin-kiosk-page">
    <div class="checkin-app-shell container-fluid">
        <div class="checkin-layout">
            <aside class="checkin-brand-panel">
                <div class="checkin-brand-icon">
                    <i class="bi bi-house-heart-fill"></i>
                </div>
                <h1>Church Check-In</h1>
                <p class="checkin-brand-name">Bridge Ministries International</p>
              

                <div class="checkin-brand-stats">
                    <div class="checkin-stat-chip">
                        <span>Open Services</span>
                        <strong><?php echo count($active_services); ?></strong>
                    </div>
                    <div class="checkin-stat-chip">
                        <span>Today</span>
                        <strong><?php echo date('M d'); ?></strong>
                    </div>
                </div>
            </aside>

            <main class="checkin-main-card">
                <header class="checkin-main-header">
                    <h2>Check In</h2>
                    <p>Search by name or phone number to continue.</p>
                </header>

                <section class="checkin-main-body">
                    <?php if (!empty($message)): ?>
                        <div class="checkin-alert checkin-alert-success" role="alert">
                            <i class="bi bi-check-circle-fill"></i>
                            <span><?php echo $message; ?></span>
                        </div>
                    <?php elseif (!empty($error)): ?>
                        <div class="checkin-alert checkin-alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($active_services)): ?>
                        <div class="checkin-alert checkin-alert-warning" role="alert">
                            <i class="bi bi-calendar-x-fill"></i>
                            <span>No open service session found for today. Please contact an administrator.</span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$show_visitor_form && !$member_data && !$visitor_data): ?>
                        <div class="checkin-search-card">
                            <p class="checkin-search-title">Who are you checking in?</p>
                            <form method="POST" autocomplete="off">
                                <div class="checkin-search-group">
                                    <input
                                        type="text"
                                        class="checkin-search-input"
                                        name="search_term"
                                        id="searchTerm"
                                        value="<?php echo htmlspecialchars($_POST['search_term'] ?? ''); ?>"
                                        placeholder="Enter your full name or phone number"
                                        autocomplete="off"
                                        required
                                    >
                                    <button class="checkin-search-btn" type="submit" name="find_person">
                                        <i class="bi bi-search"></i> Find Me
                                    </button>
                                    <div id="searchSuggestions" class="autocomplete-suggestions"></div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($member_data): ?>
                        <div class="checkin-result-card is-member">
                            <div class="checkin-result-head">
                                <div class="checkin-result-icon"><i class="bi bi-person-check-fill"></i></div>
                                <div>
                                    <p class="checkin-result-title"><?php echo htmlspecialchars($member_data['name'] ?? ''); ?></p>
                                    <p class="checkin-result-sub"><?php echo htmlspecialchars($member_data['department_name'] ?? 'Church Member'); ?></p>
                                </div>
                            </div>

                            <form method="POST" class="checkin-form-grid">
                                <input type="hidden" name="member_id" value="<?php echo (int)($member_data['id'] ?? 0); ?>">
                                <input type="hidden" name="person_type" value="member">

                                <label class="checkin-field-label" for="memberService">Today's Service</label>
                                <select class="checkin-service-select" id="memberService" name="service_id" required>
                                    <option value="">Select a service</option>
                                    <?php foreach ($active_services as $service): ?>
                                        <option value="<?php echo (int)$service['service_id']; ?>">
                                            <?php echo htmlspecialchars($service['service_name']); ?>
                                            <?php if (!empty($service['description'])): ?>
                                                - <?php echo htmlspecialchars($service['description']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div class="checkin-actions">
                                    <button type="submit" name="checkin" class="checkin-submit-btn">
                                        <i class="bi bi-check-circle-fill"></i> Check In Now
                                    </button>
                                    <a href="checkin.php" class="checkin-back-btn">Search Again</a>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($visitor_data): ?>
                        <div class="checkin-result-card is-returning">
                            <div class="checkin-result-head">
                                <div class="checkin-result-icon"><i class="bi bi-arrow-repeat"></i></div>
                                <div>
                                    <p class="checkin-result-title"><?php echo htmlspecialchars($visitor_data['name'] ?? ''); ?></p>
                                    <p class="checkin-result-sub">Returning Visitor</p>
                                </div>
                            </div>

                            <form method="POST" class="checkin-form-grid">
                                <input type="hidden" name="person_type" value="returning_visitor">
                                <input type="hidden" name="visitor_id" value="<?php echo (int)($visitor_data['id'] ?? 0); ?>">
                                <input type="hidden" name="visitor_name" value="<?php echo htmlspecialchars($visitor_data['name'] ?? ''); ?>">
                                <input type="hidden" name="visitor_phone" value="<?php echo htmlspecialchars($visitor_data['phone'] ?? ''); ?>">

                                <label class="checkin-field-label" for="returningService">Today's Service</label>
                                <select class="checkin-service-select" id="returningService" name="service_id" required>
                                    <option value="">Select a service</option>
                                    <?php foreach ($active_services as $service): ?>
                                        <option value="<?php echo (int)$service['service_id']; ?>">
                                            <?php echo htmlspecialchars($service['service_name']); ?>
                                            <?php if (!empty($service['description'])): ?>
                                                - <?php echo htmlspecialchars($service['description']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div class="checkin-actions">
                                    <button type="submit" name="checkin" class="checkin-submit-btn">
                                        <i class="bi bi-check-circle-fill"></i> Check In Now
                                    </button>
                                    <a href="checkin.php" class="checkin-back-btn">Search Again</a>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($show_visitor_form): ?>
                        <div class="checkin-result-card is-new-visitor">
                            <div class="checkin-result-head">
                                <div class="checkin-result-icon"><i class="bi bi-person-plus-fill"></i></div>
                                <div>
                                    <p class="checkin-result-title">First-Time Visitor</p>
                                    <p class="checkin-result-sub">Welcome to Bridge Ministries</p>
                                </div>
                            </div>

                            <form method="POST" class="checkin-form-grid">
                                <input type="hidden" name="person_type" value="visitor">

                                <label class="checkin-field-label" for="visitorName">Full Name *</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="visitorName"
                                    name="name"
                                    value="<?php echo htmlspecialchars($visitor_name ?? ''); ?>"
                                    placeholder="Enter your full name"
                                    required
                                >

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="checkin-field-label" for="visitorPhone">Phone Number *</label>
                                        <input type="tel" class="form-control" id="visitorPhone" name="phone" placeholder="(000) 000-0000" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="checkin-field-label" for="visitorLocation">Location</label>
                                        <input type="text" class="form-control" id="visitorLocation" name="location" placeholder="City or area">
                                    </div>
                                </div>

                                <label class="checkin-field-label" for="visitorService">Today's Service *</label>
                                <select class="checkin-service-select" id="visitorService" name="service_id" required>
                                    <option value="">Select a service</option>
                                    <?php foreach ($active_services as $service): ?>
                                        <option value="<?php echo (int)$service['service_id']; ?>">
                                            <?php echo htmlspecialchars($service['service_name']); ?>
                                            <?php if (!empty($service['description'])): ?>
                                                - <?php echo htmlspecialchars($service['description']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div class="checkin-actions">
                                    <button type="submit" name="checkin" class="checkin-submit-btn">
                                        <i class="bi bi-check-circle-fill"></i> Complete Check-In
                                    </button>
                                    <a href="checkin.php" class="checkin-back-btn">Search Again</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>

        <p class="checkin-page-footer">
            &copy; <?php echo date('Y'); ?> Bridge Ministries International
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchTerm');
            const suggestionsContainer = document.getElementById('searchSuggestions');
            let searchTimeout;

            if (searchInput && suggestionsContainer) {
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
                            .then(data => showSuggestions(data))
                            .catch(() => {
                                suggestionsContainer.style.display = 'none';
                            });
                    }, 300);
                });

                function showSuggestions(suggestions) {
                    if (!Array.isArray(suggestions) || suggestions.length === 0) {
                        suggestionsContainer.style.display = 'none';
                        return;
                    }

                    suggestionsContainer.innerHTML = '';
                    suggestions.forEach((suggestion) => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-suggestion';
                        div.innerHTML = `<strong>${suggestion.name}</strong>${suggestion.phone ? ' - ' + suggestion.phone : ''}`;

                        div.addEventListener('click', function() {
                            searchInput.value = suggestion.value;
                            suggestionsContainer.style.display = 'none';
                        });

                        suggestionsContainer.appendChild(div);
                    });

                    suggestionsContainer.style.display = 'block';
                }

                document.addEventListener('click', function(event) {
                    if (!searchInput.contains(event.target) && !suggestionsContainer.contains(event.target)) {
                        suggestionsContainer.style.display = 'none';
                    }
                });

                if (!searchInput.value) {
                    searchInput.focus();
                }
            }

            const phoneInput = document.getElementById('visitorPhone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    let value = this.value.replace(/\D/g, '');
                    if (value.length >= 6) {
                        value = value.replace(/(\d{3})(\d{3})(\d{0,4}).*/, '($1) $2-$3');
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