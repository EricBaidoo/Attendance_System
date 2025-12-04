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

try {
    require '../../config/database.php';
    
    // Get success message from URL if redirected from conversion
    $success_message = $_GET['message'] ?? '';
    
    // Get new converts statistics
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'converted_to_member' THEN 1 END) as converted_to_members,
            COUNT(CASE WHEN date_converted >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_converts,
            COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized,
            COUNT(CASE WHEN baptized = 'no' THEN 1 END) as not_baptized
        FROM new_converts
    ");
    $stats = $stats_stmt->fetch();
    
    // Get search and filter parameters
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $baptized_filter = $_GET['baptized'] ?? '';
    
    // Build where conditions
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(nc.name LIKE ? OR nc.email LIKE ? OR nc.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($status_filter) {
        $where_conditions[] = "nc.status = ?";
        $params[] = $status_filter;
    }
    
    if ($baptized_filter) {
        $where_conditions[] = "nc.baptized = ?";
        $params[] = $baptized_filter;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Get converts with pagination
    $page = $_GET['page'] ?? 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $converts_sql = "SELECT nc.* FROM new_converts nc 
                     $where_clause 
                     ORDER BY nc.date_converted DESC 
                     LIMIT $limit OFFSET $offset";
    
    $converts_stmt = $pdo->prepare($converts_sql);
    $converts_stmt->execute($params);
    $converts = $converts_stmt->fetchAll();
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM new_converts nc $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_converts = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_converts / $limit);
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = "New Converts - Bridge Ministries International";
include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/visitors.css?v=<?php echo time(); ?>" rel="stylesheet">

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

/* New converts page specific icons */
.bi-heart-fill::before { content: "\f499"; }
.bi-heart::before { content: "\f497"; }
.bi-person-plus-fill::before { content: "\f473"; }
.bi-water::before { content: "\f5d5"; }
.bi-calendar-heart::before { content: "\f413"; }
.bi-search::before { content: "\f52a"; }
.bi-eye::before { content: "\f434"; }
.bi-pencil::before { content: "\f4ca"; }
.bi-three-dots-vertical::before { content: "\f5aa"; }
.bi-envelope::before { content: "\f42f"; }
.bi-telephone::before { content: "\f57c"; }
.bi-droplet::before { content: "\f426"; }
.bi-check-circle::before { content: "\f41a"; }
.bi-people::before { content: "\f479"; }
.bi-x-circle::before { content: "\f5e8"; }
</style>

<!-- Professional New Converts Dashboard -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-primary mb-2 fw-bold">
                        <i class="bi bi-heart-fill"></i> New Converts
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="text-muted">Manage new converts and their spiritual journey</span>
                        <span class="badge bg-light text-dark"><?php echo $stats['total']; ?> Total Converts</span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <a href="../visitors/list.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-person-badge"></i> Visitors
                    </a>
                    <a href="../members/add.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add Member
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <span><?php echo htmlspecialchars($success_message ?? ''); ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 members-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Total Converts</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['total']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-heart"></i> All time
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-heart-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 visitors-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Active Converts</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['active']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-check-circle"></i> Currently active
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-person-check-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 converts-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Baptized</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['baptized']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-droplet"></i> Water baptized
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-droplet-fill text-white fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 departments-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Recent (30d)</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['recent_converts']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-calendar"></i> This month
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-calendar-plus text-white fs-2"></i>
                        </div>
                    </div>
                </div>
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
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Search Converts</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, or phone" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="converted_to_member" <?php echo $status_filter === 'converted_to_member' ? 'selected' : ''; ?>>Converted to Member</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Baptism</label>
                    <select name="baptized" class="form-select">
                        <option value="">All</option>
                        <option value="yes" <?php echo $baptized_filter === 'yes' ? 'selected' : ''; ?>>Baptized</option>
                        <option value="no" <?php echo $baptized_filter === 'no' ? 'selected' : ''; ?>>Not Baptized</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Converts Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-primary fw-bold mb-0">
                    <i class="bi bi-list-ul"></i> New Converts List
                </h5>
                <span class="text-muted">Showing <?php echo count($converts); ?> of <?php echo $total_converts; ?> converts</span>
            </div>

            <?php if (empty($converts)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-heart text-muted empty-state-icon"></i>
                    <h4 class="text-muted mt-3 mb-2">No Converts Found</h4>
                    <p class="text-muted mb-4">No new converts match your search criteria.</p>
                    <a href="../visitors/list.php" class="btn btn-primary">
                        <i class="bi bi-person-badge"></i> View Visitors
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">
                                    <i class="bi bi-person me-1"></i>Convert
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-envelope me-1"></i>Contact
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-calendar me-1"></i>Converted Date
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-droplet me-1"></i>Baptism
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-activity me-1"></i>Status
                                </th>
                                <th class="fw-semibold text-center">
                                    <i class="bi bi-gear me-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($converts as $convert): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <i class="bi bi-heart-fill"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($convert['name'] ?? ''); ?></h6>
                                                <small class="text-muted">
                                                    ID: #<?php echo $convert['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small class="d-block">
                                                <i class="bi bi-envelope me-1 text-primary"></i>
                                                <?php echo htmlspecialchars($convert['email'] ?? 'Not provided'); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?php echo htmlspecialchars($convert['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="d-block fw-semibold">
                                            <?php echo date('M j, Y', strtotime($convert['date_converted'])); ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo date('g:i A', strtotime($convert['date_converted'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($convert['baptized'] === 'yes'): ?>
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
                                        <?php if ($convert['status'] === 'active'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Active
                                            </span>
                                        <?php elseif ($convert['status'] === 'converted_to_member'): ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-person-check me-1"></i>Member
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-pause-circle me-1"></i>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $convert['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $convert['id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($convert['status'] !== 'converted_to_member'): ?>
                                                    <li><a class="dropdown-item" href="convert_to_member.php?id=<?php echo $convert['id']; ?>">
                                                        <i class="bi bi-person-check me-2"></i>Convert to Member
                                                    </a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item" href="#" onclick="updateBaptism(<?php echo $convert['id']; ?>)">
                                                        <i class="bi bi-droplet me-2"></i>Update Baptism Status
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $convert['id']; ?>)">
                                                        <i class="bi bi-trash me-2"></i>Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Converts pagination" class="mt-4">
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
function confirmDelete(convertId) {
    if (confirm('Are you sure you want to delete this convert record? This action cannot be undone.')) {
        window.location.href = 'delete.php?id=' + convertId;
    }
}

function updateBaptism(convertId) {
    if (confirm('Update baptism status for this convert?')) {
        window.location.href = 'update_baptism.php?id=' + convertId;
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
    background: linear-gradient(135deg, #fd7e14 0%, #f39c12 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.table-hover tbody tr:hover {
    background-color: rgba(253, 126, 20, 0.05);
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php include '../../includes/footer.php'; ?>