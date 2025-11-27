<?php
// More robust path detection for hosting environments
$script_path = $_SERVER['SCRIPT_NAME'];
$request_path = $_SERVER['REQUEST_URI'];

// Remove query string and get clean path
$clean_path = parse_url($request_path, PHP_URL_PATH);

// Count how many directories we are from the root
$path_parts = explode('/', trim($clean_path, '/'));
$script_parts = explode('/', trim($script_path, '/'));

// Find the project root by looking for common patterns
$project_root_found = false;
$levels_up = 0;

// Check if we're in a subdirectory (pages/something/)
if (strpos($clean_path, '/pages/') !== false) {
    $levels_up = 2; // pages/subfolder/ = 2 levels up
} else if (strpos($clean_path, '/includes/') !== false) {
    $levels_up = 1; // includes/ = 1 level up  
} else {
    $levels_up = 0; // root level
}

// Generate the relative path
if ($levels_up == 0) {
    $relative_path = './';
} else {
    $relative_path = str_repeat('../', $levels_up);
}

// Debug info (remove this after testing)
// echo "<!-- DEBUG: Script: $script_path | Request: $clean_path | Levels up: $levels_up | Relative: $relative_path -->";
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Bridge Ministries International'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo $relative_path; ?>assets/css/header.css" rel="stylesheet">

</head>
<body>
    <!-- Simple Bootstrap Navbar -->
    <nav class="navbar navbar-expand-xl navbar-dark fixed-top">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand" href="<?php echo $relative_path; ?>index.php">
                <img src="<?php echo $relative_path; ?>assets/css/image/bmi logo.png" alt="BMI Logo" class="navbar-logo">
                BMI ATTENDANCE
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" 
                           href="<?php echo $relative_path; ?>index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'members') !== false ? 'active' : ''; ?>" 
                           href="<?php echo $relative_path; ?>pages/members/list.php">
                            <i class="bi bi-people-fill"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'visitors') !== false && strpos($_SERVER['REQUEST_URI'], 'new_converts') === false ? 'active' : ''; ?>" 
                           href="<?php echo $relative_path; ?>pages/visitors/list.php">
                            <i class="bi bi-person-badge"></i> Visitors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'new_converts') !== false ? 'active' : ''; ?>" 
                           href="<?php echo $relative_path; ?>pages/visitors/new_converts.php">
                            <i class="bi bi-person-plus-fill"></i> Converts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'services') !== false ? 'active' : ''; ?>" 
                           href="<?php echo $relative_path; ?>pages/services/list.php">
                            <i class="bi bi-calendar-event"></i> Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'sessions') !== false || strpos($_SERVER['REQUEST_URI'], 'attendance') !== false ? 'active' : ''; ?>" 
                           href="<?php echo $relative_path; ?>pages/services/sessions.php">
                            <i class="bi bi-clipboard-check"></i> Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'active' : ''; ?>" 
                           href="<?php echo $relative_path; ?>pages/reports/index.php">
                            <i class="bi bi-graph-up"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $relative_path; ?>logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="container-fluid px-4">
            <?php if (isset($page_header) && $page_header): ?>
                <div class="card border-0 shadow-sm p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="text-gradient mb-2">
                                <?php echo isset($page_icon) ? '<i class="' . $page_icon . '"></i> ' : ''; ?>
                                <?php echo isset($page_heading) ? $page_heading : 'Page Title'; ?>
                            </h1>
                            <p class="text-muted mb-0">
                                <?php echo isset($page_description) ? $page_description : 'Page description'; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if (isset($page_actions)): ?>
                                <?php echo $page_actions; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>