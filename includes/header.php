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
    <!-- Custom Bootstrap overrides only -->
    <style>
        body { 
            padding-top: 0 !important;
            margin: 0;
        }
        .navbar {
            margin-bottom: 0;
        }
        .content-wrapper {
            padding-top: 1rem;
        }
        .navbar-brand { font-weight: 600; }
        @media (max-width: 991px) {
            .navbar-collapse {
                background: #2c3e50 !important;
                border-radius: 0.5rem;
                margin-top: 0.5rem;
                padding: 1rem !important;
                box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,0.3) !important;
            }
            .navbar-nav .nav-link {
                color: #fff !important;
                padding: 0.75rem 1rem !important;
                display: flex !important;
                align-items: center !important;
                gap: 0.75rem !important;
                margin-bottom: 0.25rem !important;
            }
            .navbar-nav .nav-link:hover {
                background: rgba(255,255,255,0.2) !important;
            }
        }
    </style>
</head>
</head>
<body>
    <!-- Professional Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="row w-100 align-items-center">
                <!-- Brand Section -->
                <div class="col-lg-4 col-md-6 col-4">
                    <a class="navbar-brand" href="<?php echo $relative_path; ?>index.php">
                        <img src="<?php echo $relative_path; ?>assets/css/image/bmi logo.png" alt="BMI Logo" style="height: 2.5rem; width: auto; margin-right: 0.625rem; border-radius: 0.375rem;">
                        <span>BMI ATTENDANCE</span>
                    </a>
                </div>

                <!-- Mobile Toggle -->
                <div class="col-4 d-lg-none text-center">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <i class="bi bi-list"></i>
                    </button>
                </div>

                <!-- Spacer for mobile balance -->
                <div class="col-4 d-lg-none"></div>

                <!-- Navigation Section -->
                <div class="col-lg-8 col-md-6">
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" 
                                   href="<?php echo $relative_path; ?>index.php" title="Dashboard">
                                    <i class="bi bi-speedometer2"></i>
                                    <span>Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'members') !== false ? 'active' : ''; ?>" 
                                   href="<?php echo $relative_path; ?>pages/members/list.php" title="Members Management">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Members</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'visitors') !== false && strpos($_SERVER['REQUEST_URI'], 'new_converts') === false ? 'active' : ''; ?>" 
                                   href="<?php echo $relative_path; ?>pages/visitors/list.php" title="Visitors Management">
                                    <i class="bi bi-person-badge"></i>
                                    <span>Visitors</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'new_converts') !== false ? 'active' : ''; ?>" 
                                   href="<?php echo $relative_path; ?>pages/visitors/new_converts.php" title="New Converts">
                                    <i class="bi bi-person-plus-fill"></i>
                                    <span>Converts</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'active' : ''; ?>" 
                                   href="<?php echo $relative_path; ?>pages/reports/index.php" title="Reports">
                                    <i class="bi bi-graph-up"></i>
                                    <span>Reports</span>
                                </a>
                            </li>
                            <li class="nav-item ms-2">
                                <a class="nav-link logout-btn" 
                                   href="<?php echo $relative_path; ?>logout.php" title="Logout">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
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