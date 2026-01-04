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
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Filter parameters
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? 'active';
$baptism_filter = $_GET['baptism'] ?? '';
$group_filter = $_GET['group'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$ministerial_filter = $_GET['ministerial'] ?? '';

// Build query filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($gender_filter) {
    $where_conditions[] = "(LOWER(m.gender) = LOWER(?) OR m.gender = ?)";
    $gender_param = ucfirst(strtolower($gender_filter));
    $params[] = $gender_filter; // Original case
    $params[] = $gender_param; // Capitalized case
}

if ($search) {
    $where_conditions[] = "(m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.phone2 LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($department_filter) {
    $where_conditions[] = "m.department_id = ?";
    $params[] = $department_filter;
}

if ($baptism_filter) {
    $where_conditions[] = "m.baptized = ?";
    $params[] = $baptism_filter;
}

if ($group_filter) {
    $where_conditions[] = "m.congregation_group = ?";
    $params[] = $group_filter;
}

if ($ministerial_filter) {
    if ($ministerial_filter === 'none') {
        $where_conditions[] = "(m.ministerial_status IS NULL OR m.ministerial_status = '')";
    } else {
        $where_conditions[] = "m.ministerial_status = ?";
        $params[] = $ministerial_filter;
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get member statistics
try {
    // Check if congregation_group and ministerial_status columns exist
    $table_check = $pdo->query("SHOW COLUMNS FROM members LIKE 'congregation_group'");
    $has_congregation_group = $table_check->rowCount() > 0;
    $ms_check = $pdo->query("SHOW COLUMNS FROM members LIKE 'ministerial_status'");
    $has_ministerial_status = $ms_check->rowCount() > 0;
    
    if ($has_congregation_group) {
        $stats_sql = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
            COUNT(CASE WHEN gender = 'female' THEN 1 END) as female,
            COUNT(CASE WHEN baptized = 1 OR baptized = 'yes' THEN 1 END) as baptized,
            COUNT(CASE WHEN congregation_group = 'Adult' THEN 1 END) as adults,
            COUNT(CASE WHEN congregation_group = 'Youth' THEN 1 END) as youths,
            COUNT(CASE WHEN congregation_group = 'Teen' THEN 1 END) as teens,
            COUNT(CASE WHEN congregation_group = 'Children' THEN 1 END) as children";
    } else {
        $stats_sql = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
            COUNT(CASE WHEN gender = 'female' THEN 1 END) as female,
            COUNT(CASE WHEN baptized = 1 OR baptized = 'yes' THEN 1 END) as baptized,
            0 as adults, 0 as youths, 0 as teens, 0 as children";
    }

    if ($has_ministerial_status) {
        $stats_sql .= ",
            COUNT(CASE WHEN ministerial_status = 'Levite' THEN 1 END) as ms_levite,
            COUNT(CASE WHEN ministerial_status = 'Shepherd' THEN 1 END) as ms_shepherd,
            COUNT(CASE WHEN ministerial_status = 'Minister' THEN 1 END) as ms_minister,
            COUNT(CASE WHEN ministerial_status = 'Junior Pastor' THEN 1 END) as ms_jp,
            COUNT(CASE WHEN ministerial_status = 'Senior Pastor' THEN 1 END) as ms_sp,
            COUNT(CASE WHEN ministerial_status = 'General Overseer' THEN 1 END) as ms_go,
            COUNT(CASE WHEN ministerial_status IS NULL OR ministerial_status = '' THEN 1 END) as ms_none";
    } else {
        $stats_sql .= ", 0 as ms_levite, 0 as ms_shepherd, 0 as ms_minister, 0 as ms_jp, 0 as ms_sp, 0 as ms_go, 0 as ms_none";
    }

    $stats_sql .= " FROM members m $where_clause";
    
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute($params);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = [
        'total' => 0, 'male' => 0, 'female' => 0, 'baptized' => 0,
        'adults' => 0, 'youths' => 0, 'teens' => 0, 'children' => 0,
        'ms_levite' => 0, 'ms_shepherd' => 0, 'ms_minister' => 0, 'ms_jp' => 0, 'ms_sp' => 0, 'ms_go' => 0, 'ms_none' => 0
    ];
}

// Get departments for filter dropdown
try {
    $departments_stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $departments_stmt->fetchAll();
} catch (Exception $e) {
    $departments = [];
}

// Get members with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$members_sql = "SELECT m.*, d.name as department_name 
                FROM members m 
                LEFT JOIN departments d ON m.department_id = d.id 
                $where_clause 
                ORDER BY m.name 
                LIMIT $limit OFFSET $offset";

$members_stmt = $pdo->prepare($members_sql);
$members_stmt->execute($params);
$members = $members_stmt->fetchAll();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM members m $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_members = $count_stmt->fetch()['total'];
$total_pages = ceil($total_members / $limit);

$page_title = "Members Directory - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/members.css?v=<?php echo time(); ?>" rel="stylesheet">

<!-- Bootstrap Icons Fix -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
<style>
@import url('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css');

.bi {
    font-family: "bootstrap-icons" !important;
    font-style: normal;
    font-weight: normal;
    font-variant: normal;
    text-transform: none;
    line-height: 1;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.bi::before {
    font-family: "bootstrap-icons" !important;
    font-weight: normal !important;
    font-style: normal !important;
}

/* Member page specific icons */
.bi-people-fill::before { content: "\f47a"; }
.bi-person-plus::before { content: "\f472"; }
.bi-download::before { content: "\f426"; }
.bi-printer::before { content: "\f486"; }
.bi-search::before { content: "\f52a"; }
.bi-funnel::before { content: "\f445"; }
.bi-person::before { content: "\f465"; }
.bi-person-dress::before { content: "\f473"; }
.bi-envelope::before { content: "\f42f"; }
.bi-telephone::before { content: "\f57c"; }
.bi-eye::before { content: "\f434"; }
.bi-pencil::before { content: "\f4ca"; }
.bi-three-dots-vertical::before { content: "\f5aa"; }
.bi-check-circle::before { content: "\f41a"; }
.bi-x-circle::before { content: "\f5e8"; }
.bi-trash::before { content: "\f5a2"; }
.bi-file-excel::before { content: "\f438"; }
.bi-file-pdf::before { content: "\f43c"; }

/* Enhanced card styling to match index page */
.card-icon {
    opacity: 0.7;
    transition: all 0.3s ease;
}

.text-white-75 {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* Enhanced card hover effects to match dashboard */
.members-card:hover,
.visitors-card:hover,
.converts-card:hover,
.departments-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
}

.members-card:hover .card-icon,
.visitors-card:hover .card-icon,
.converts-card:hover .card-icon,
.departments-card:hover .card-icon {
    opacity: 1;
    transform: scale(1.1);
}

/* Mobile responsiveness improvements */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem !important;
    }
    
    .fs-1 {
        font-size: 1.5rem !important;
    }
    
    .fs-2 {
        font-size: 1.25rem !important;
    }
    
    h6 {
        font-size: 0.9rem !important;
    }
    
    h2 {
        font-size: 1.5rem !important;
    }
    
    small {
        font-size: 0.75rem !important;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 0.75rem !important;
    }
    
    h2 {
        font-size: 1.25rem !important;
    }
    
    .fs-1 {
        font-size: 1.25rem !important;
    }
    
    h6 {
        font-size: 0.8rem !important;
    }
}
</style>

<!-- Professional Members Dashboard -->
<div class="container-fluid px-0">
    <!-- Dashboard Header -->
    <div class="px-3 px-md-4 py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between">
                    <div class="mb-3 mb-md-0">
                        <h1 class="text-primary mb-2 fw-bold">
                            <i class="bi bi-people-fill"></i> Members Directory
                        </h1>
                        <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                            <span class="text-muted">Manage church members and their information</span>
                            <span class="badge bg-light text-dark"><?php echo $stats['total']; ?> Total Members</span>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="add.php" class="btn btn-outline-primary">
                            <i class="bi bi-person-plus"></i> Add Member
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#"><i class="bi bi-file-excel me-2"></i>Excel</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-file-pdf me-2"></i>PDF</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="px-3 px-md-4 mb-4">
        <!-- Overview: Total & Baptized -->
        <div class="row g-2 g-md-3 g-lg-4">
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-lg h-100 members-card">
                    <div class="card-body text-white position-relative">
                        <div class="card-pattern"></div>
                        <div class="d-flex align-items-center justify-content-between position-relative">
                            <div>
                                <h6 class="text-white-75 mb-2 fw-semibold">Total Members</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['total']; ?></h2>
                                <small class="text-white-75">
                                    <i class="bi bi-people me-1"></i> Active congregation
                                </small>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-people-fill text-white fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-lg h-100 visitors-card">
                    <div class="card-body text-white position-relative">
                        <div class="card-pattern"></div>
                        <div class="d-flex align-items-center justify-content-between position-relative">
                            <div>
                                <h6 class="text-white-75 mb-2 fw-semibold">Baptized</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['baptized']; ?></h2>
                                <small class="text-white-75">
                                    <i class="bi bi-droplet me-1"></i> <?php echo $stats['total'] > 0 ? round(($stats['baptized']/$stats['total'])*100) : 0; ?>% of members
                                </small>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-droplet-fill text-white fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demography Cards -->
        <div class="mt-4">
            <div class="d-flex align-items-center mb-2 ps-1">
                <h6 class="text-muted fw-semibold mb-0">
                    <i class="bi bi-people me-1"></i> Demography
                </h6>
            </div>
            <div class="row g-2 g-md-3 g-lg-4">
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 converts-card">
                        <div class="card-body text-white position-relative">
                            <div class="card-pattern"></div>
                            <div class="d-flex align-items-center justify-content-between position-relative">
                                <div>
                                    <h6 class="text-white-75 mb-2 fw-semibold small">Male</h6>
                                    <h3 class="text-white mb-1 fw-bold"><?php echo $stats['male']; ?></h3>
                                </div>
                                <div class="card-icon text-white">
                                    <i class="bi bi-person-fill fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 departments-card">
                        <div class="card-body text-white position-relative">
                            <div class="card-pattern"></div>
                            <div class="d-flex align-items-center justify-content-between position-relative">
                                <div>
                                    <h6 class="text-white-75 mb-2 fw-semibold small">Female</h6>
                                    <h3 class="text-white mb-1 fw-bold"><?php echo $stats['female']; ?></h3>
                                </div>
                                <div class="card-icon text-white">
                                    <i class="bi bi-person-dress fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);">
                        <div class="card-body text-white position-relative">
                            <div class="d-flex align-items-center justify-content-between position-relative">
                                <div>
                                    <h6 class="text-white-75 mb-2 fw-semibold small">Adults</h6>
                                    <h3 class="text-white mb-1 fw-bold"><?php echo $stats['adults']; ?></h3>
                                </div>
                                <div class="card-icon text-white">
                                    <i class="bi bi-person-badge fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <div class="card-body text-white position-relative">
                            <div class="d-flex align-items-center justify-content-between position-relative">
                                <div>
                                    <h6 class="text-white-75 mb-2 fw-semibold small">Youths</h6>
                                    <h3 class="text-white mb-1 fw-bold"><?php echo $stats['youths']; ?></h3>
                                </div>
                                <div class="card-icon text-white">
                                    <i class="bi bi-person-arms-up fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                        <div class="card-body text-white position-relative">
                            <div class="d-flex align-items-center justify-content-between position-relative">
                                <div>
                                    <h6 class="text-white-75 mb-2 fw-semibold small">Teens</h6>
                                    <h3 class="text-white mb-1 fw-bold"><?php echo $stats['teens']; ?></h3>
                                </div>
                                <div class="card-icon text-white">
                                    <i class="bi bi-emoji-smile fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <div class="card-body text-white position-relative">
                            <div class="d-flex align-items-center justify-content-between position-relative">
                                <div>
                                    <h6 class="text-white-75 mb-2 fw-semibold small">Children</h6>
                                    <h3 class="text-white mb-1 fw-bold"><?php echo $stats['children']; ?></h3>
                                </div>
                                <div class="card-icon text-white">
                                    <i class="bi bi-heart-fill fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ministerial Status Cards -->
        <div class="mt-4">
            <div class="d-flex align-items-center mb-2 ps-1">
                <h6 class="text-muted fw-semibold mb-0">
                    <i class="bi bi-award me-1"></i> Ministerial Status
                </h6>
            </div>
            <div class="row g-2 g-md-3 g-lg-4">
                <?php
                $ms_cards = [
                    ['label' => 'General Overseer', 'value' => $stats['ms_go'], 'class' => 'ms-card-go', 'icon' => 'bi-award'],
                    ['label' => 'Senior Pastor', 'value' => $stats['ms_sp'], 'class' => 'ms-card-sp', 'icon' => 'bi-stars'],
                    ['label' => 'Junior Pastor', 'value' => $stats['ms_jp'], 'class' => 'ms-card-jp', 'icon' => 'bi-lightning'],
                    ['label' => 'Minister', 'value' => $stats['ms_minister'], 'class' => 'ms-card-min', 'icon' => 'bi-briefcase'],
                    ['label' => 'Shepherd', 'value' => $stats['ms_shepherd'], 'class' => 'ms-card-shep', 'icon' => 'bi-heart'],
                    ['label' => 'Levite', 'value' => $stats['ms_levite'], 'class' => 'ms-card-levite', 'icon' => 'bi-music-note-beamed'],
                    ['label' => 'Not Set', 'value' => $stats['ms_none'], 'class' => 'ms-card-none', 'icon' => 'bi-dash-circle']
                ];
                foreach ($ms_cards as $card): ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 ms-card <?php echo $card['class']; ?>" >
                        <div class="card-body text-white position-relative">
                            <div class="d-flex align-items-center justify-content-between position-relative">
                                <div>
                                    <h6 class="text-white-75 mb-2 fw-semibold small"><?php echo $card['label']; ?></h6>
                                    <h3 class="text-white mb-1 fw-bold"><?php echo $card['value']; ?></h3>
                                </div>
                                <div class="card-icon text-white">
                                    <i class="bi <?php echo $card['icon']; ?> fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="text-primary fw-bold mb-3">
                <i class="bi bi-funnel"></i> Search & Filters
            </h5>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search Members</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, or phone" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All</option>
                        <option value="male" <?php echo $gender_filter === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo $gender_filter === 'female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-semibold">Congregation</label>
                    <select name="group" class="form-select">
                        <option value="">All</option>
                        <option value="Adult" <?php echo $group_filter === 'Adult' ? 'selected' : ''; ?>>Adults</option>
                        <option value="Youth" <?php echo $group_filter === 'Youth' ? 'selected' : ''; ?>>Youths</option>
                        <option value="Teen" <?php echo $group_filter === 'Teen' ? 'selected' : ''; ?>>Teens</option>
                        <option value="Children" <?php echo $group_filter === 'Children' ? 'selected' : ''; ?>>Children</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label fw-semibold">Ministerial Status</label>
                    <select name="ministerial" class="form-select">
                        <option value="">All</option>
                        <option value="General Overseer" <?php echo $ministerial_filter === 'General Overseer' ? 'selected' : ''; ?>>General Overseer</option>
                        <option value="Senior Pastor" <?php echo $ministerial_filter === 'Senior Pastor' ? 'selected' : ''; ?>>Senior Pastor</option>
                        <option value="Junior Pastor" <?php echo $ministerial_filter === 'Junior Pastor' ? 'selected' : ''; ?>>Junior Pastor</option>
                        <option value="Minister" <?php echo $ministerial_filter === 'Minister' ? 'selected' : ''; ?>>Minister</option>
                        <option value="Shepherd" <?php echo $ministerial_filter === 'Shepherd' ? 'selected' : ''; ?>>Shepherd</option>
                        <option value="Levite" <?php echo $ministerial_filter === 'Levite' ? 'selected' : ''; ?>>Levite</option>
                        <option value="none" <?php echo $ministerial_filter === 'none' ? 'selected' : ''; ?>>Not Set</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="?status=active" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-primary fw-bold mb-0">
                    <i class="bi bi-list-ul"></i> Members List
                </h5>
                <span class="text-muted">Showing <?php echo count($members); ?> of <?php echo $total_members; ?> members</span>
            </div>

            <?php if (empty($members)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-person-x text-muted" style="font-size: 4rem; opacity: 0.5;"></i>
                    <h4 class="text-muted mt-3 mb-2">No Members Found</h4>
                    <p class="text-muted mb-4">Try adjusting your search criteria or add new members.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add First Member
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">
                                    <i class="bi bi-person me-1"></i>Name
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-envelope me-1"></i>Contact
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-building me-1"></i>Department
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-droplet me-1"></i>Baptism
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-activity me-1"></i>Status
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-award me-1"></i>Ministerial Status
                                </th>
                                <th class="fw-semibold text-center">
                                    <i class="bi bi-gear me-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($member['name'] ?? ''); ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-<?php echo ($member['gender'] ?? '') === 'male' ? 'person' : 'person-dress'; ?> me-1"></i>
                                                    <?php echo ucfirst($member['gender'] ?? ''); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small class="d-block">
                                                <i class="bi bi-envelope me-1 text-primary"></i>
                                                <?php echo htmlspecialchars($member['email'] ?? ''); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?php echo htmlspecialchars($member['phone'] ?? ''); ?>
                                                <?php if ($member['phone2'] ?? ''): ?>
                                                    <br><i class="bi bi-telephone-plus me-1"></i><?php echo htmlspecialchars($member['phone2']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($member['department_name'] ?? 'No Department'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($member['baptized'] === 'yes'): ?>
                                            <span class="badge bg-primary">
                                                <i class="bi bi-droplet-fill me-1"></i>Baptized
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-droplet me-1"></i>Not Baptized
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($member['status'] === 'active'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-pause-circle me-1"></i>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $ms = $member['ministerial_status'] ?? '';
                                        $msLabel = $ms ?: 'Not Set';
                                        $msClass = 'bg-secondary';
                                        if ($ms === 'General Overseer') {
                                            $msClass = 'bg-dark text-white';
                                        } elseif ($ms === 'Senior Pastor') {
                                            $msClass = 'bg-primary';
                                        } elseif ($ms === 'Junior Pastor') {
                                            $msClass = 'bg-info text-dark';
                                        } elseif ($ms === 'Minister') {
                                            $msClass = 'bg-success';
                                        } elseif ($ms === 'Shepherd') {
                                            $msClass = 'bg-warning text-dark';
                                        } elseif ($ms === 'Levite') {
                                            $msClass = 'bg-light text-dark';
                                        }
                                        ?>
                                        <span class="badge <?php echo $msClass; ?>">
                                            <i class="bi bi-award me-1"></i><?php echo htmlspecialchars($msLabel); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- View Button -->
                                            <a href="view.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-info btn-xs" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <!-- Edit Button -->
                                            <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-outline-primary btn-xs" title="Edit Member">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <!-- Disable/Enable Button -->
                                            <a href="update_status.php?id=<?php echo $member['id']; ?>&action=toggle&return=list" 
                                               class="btn btn-outline-<?php echo $member['status'] === 'active' ? 'warning' : 'success'; ?> btn-xs" 
                                               title="<?php echo $member['status'] === 'active' ? 'Disable Member' : 'Enable Member'; ?>"
                                               onclick="return confirm('Are you sure you want to <?php echo $member['status'] === 'active' ? 'disable' : 'enable'; ?> this member?')">
                                                <i class="bi <?php echo $member['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                            </a>
                                            <!-- Delete Button - More Prominent -->
                                            <a href="javascript:void(0)" class="btn btn-danger btn-xs delete-btn" title="Permanently Delete Member" onclick="deleteMember(<?php echo $member['id']; ?>, '<?php echo addslashes($member['name']); ?>')">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Members pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteMember(memberId, memberName) {
    if (confirm('Are you sure you want to delete "' + memberName + '"? This action cannot be undone and will permanently remove all member data including attendance records.')) {
        // Create a hidden form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete.php';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = memberId;
        
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #000032 0%, #1a1a5e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 50, 0.05);
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Action buttons styling */
.table th:last-child,
.table td:last-child {
    width: 200px;
    min-width: 200px;
}

.btn-xs {
    padding: 0.2rem 0.4rem;
    font-size: 0.75rem;
    line-height: 1;
}

/* Ministerial status cards */
.ms-card {
    color: #fff;
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.ms-card-go { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }
.ms-card-sp { background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); }
.ms-card-jp { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
.ms-card-min { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); }
.ms-card-shep { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.ms-card-levite { background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%); color: #111827; }
.ms-card-none { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); }

/* Delete button styling - make it more obvious */
.delete-btn {
    font-weight: 600;
    padding: 0.4rem 0.8rem !important;
    font-size: 0.85rem !important;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    white-space: nowrap;
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
}

.delete-btn:hover {
    background-color: #dc3545 !important;
    color: white !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.5);
}

.delete-btn i {
    margin-right: 0;
}

.btn-group-sm .btn {
    border-radius: 0;
}

.btn-group-sm .btn:first-child {
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}

.btn-group-sm .btn:last-child {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}

.btn-group .btn:hover {
    z-index: 2;
}
</style>

<?php include '../../includes/footer.php'; ?>