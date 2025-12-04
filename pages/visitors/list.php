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
    
    // Get visitor statistics (excluding converted visitors)
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN first_time = 'yes' THEN 1 END) as first_time,
            COUNT(CASE WHEN first_time = 'no' THEN 1 END) as return_visitors,
            COUNT(CASE WHEN follow_up_needed = 'yes' AND follow_up_completed = 'no' THEN 1 END) as pending_followups,
            COUNT(CASE WHEN became_member = 'yes' THEN 1 END) as became_members,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as recent_visitors
        FROM visitors 
        WHERE (status IS NULL OR status != 'converted_to_convert')
    ");
    $stats = $stats_stmt->fetch();
    
    // Get search and filter parameters
    $search = $_GET['search'] ?? '';
    $first_time_filter = $_GET['first_time'] ?? '';
    $follow_up_filter = $_GET['follow_up'] ?? '';
    
    // Build where conditions
    $where_conditions = ['(v.status IS NULL OR v.status != \'converted_to_convert\')'];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(v.name LIKE ? OR v.email LIKE ? OR v.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($first_time_filter) {
        $where_conditions[] = "v.first_time = ?";
        $params[] = $first_time_filter;
    }
    
    if ($follow_up_filter) {
        if ($follow_up_filter === 'pending') {
            $where_conditions[] = "v.follow_up_needed = 'yes' AND v.follow_up_completed = 'no'";
        } elseif ($follow_up_filter === 'completed') {
            $where_conditions[] = "v.follow_up_completed = 'yes'";
        }
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get visitors with pagination
    $page = $_GET['page'] ?? 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $visitors_sql = "SELECT v.* FROM visitors v 
                     $where_clause 
                     ORDER BY v.created_at DESC 
                     LIMIT $limit OFFSET $offset";
    
    $visitors_stmt = $pdo->prepare($visitors_sql);
    $visitors_stmt->execute($params);
    $visitors = $visitors_stmt->fetchAll();
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM visitors v $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_visitors = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_visitors / $limit);
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = "Visitors Directory - Bridge Ministries International";
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

/* Visitor page specific icons */
.bi-person-badge::before { content: "\f46c"; }
.bi-person-plus::before { content: "\f472"; }
.bi-person-check::before { content: "\f470"; }
.bi-search::before { content: "\f52a"; }
.bi-funnel::before { content: "\f445"; }
.bi-eye::before { content: "\f434"; }
.bi-pencil::before { content: "\f4ca"; }
.bi-three-dots-vertical::before { content: "\f5aa"; }
.bi-envelope::before { content: "\f42f"; }
.bi-telephone::before { content: "\f57c"; }
.bi-calendar::before { content: "\f40e"; }
.bi-phone::before { content: "\f4d5"; }
.bi-heart::before { content: "\f497"; }
.bi-trash::before { content: "\f5a2"; }
.bi-arrow-left::before { content: "\f3fb"; }
</style>

<!-- Professional Visitors Dashboard -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-primary mb-2 fw-bold">
                        <i class="bi bi-person-badge"></i> Visitors Directory
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="text-muted">Manage church visitors and follow-up activities</span>
                        <span class="badge bg-light text-dark"><?php echo $stats['total']; ?> Total Visitors</span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <a href="add.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-person-plus"></i> Add Visitor
                    </a>
                    <a href="checkin.php" class="btn btn-primary">
                        <i class="bi bi-check-square"></i> Check-In
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-4">
            <div class="card border-0 shadow-sm h-100 members-card">
                <div class="card-body text-white p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-white-50 mb-2 fw-semibold">Total Visitors</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['total']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-people"></i> All time
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-person-badge text-white fs-2"></i>
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
                            <h6 class="text-white-50 mb-2 fw-semibold">First Time</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['first_time']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-star"></i> New visitors
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-star-fill text-white fs-2"></i>
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
                            <h6 class="text-white-50 mb-2 fw-semibold">Follow-ups Pending</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['pending_followups']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-clock"></i> Needs attention
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-telephone-fill text-white fs-2"></i>
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
                            <h6 class="text-white-50 mb-2 fw-semibold">Became Members</h6>
                            <h2 class="text-white mb-2 fw-bold"><?php echo $stats['became_members']; ?></h2>
                            <small class="text-white-50">
                                <i class="bi bi-check-circle"></i> Converted
                            </small>
                        </div>
                        <div class="rounded p-3">
                            <i class="bi bi-person-check-fill text-white fs-2"></i>
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
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Search Visitors</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, or phone" value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Visitor Type</label>
                    <select name="first_time" class="form-select">
                        <option value="">All Visitors</option>
                        <option value="yes" <?php echo $first_time_filter === 'yes' ? 'selected' : ''; ?>>First Time</option>
                        <option value="no" <?php echo $first_time_filter === 'no' ? 'selected' : ''; ?>>Return Visitors</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Follow-up Status</label>
                    <select name="follow_up" class="form-select">
                        <option value="">All</option>
                        <option value="pending" <?php echo $follow_up_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $follow_up_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Search & Filter
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Visitors Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-primary fw-bold mb-0">
                    <i class="bi bi-list-ul"></i> Visitors List
                </h5>
                <span class="text-muted">Showing <?php echo count($visitors); ?> of <?php echo $total_visitors; ?> visitors</span>
            </div>

            <?php if (empty($visitors)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-person-x text-muted empty-state-icon"></i>
                    <h4 class="text-muted mt-3 mb-2">No Visitors Found</h4>
                    <p class="text-muted mb-4">Try adjusting your search criteria or add new visitors.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add First Visitor
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">
                                    <i class="bi bi-person me-1"></i>Visitor
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-envelope me-1"></i>Contact
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-calendar me-1"></i>Visit Date
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-star me-1"></i>Type
                                </th>
                                <th class="fw-semibold">
                                    <i class="bi bi-telephone me-1"></i>Follow-up
                                </th>
                                <th class="fw-semibold text-center">
                                    <i class="bi bi-gear me-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visitors as $visitor): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3">
                                                <i class="bi bi-person-badge"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($visitor['name'] ?? ''); ?></h6>
                                                <small class="text-muted">
                                                    ID: #<?php echo $visitor['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small class="d-block">
                                                <i class="bi bi-envelope me-1 text-primary"></i>
                                                <?php echo htmlspecialchars($visitor['email'] ?? 'Not provided'); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?php echo htmlspecialchars($visitor['phone'] ?? 'Not provided'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="d-block fw-semibold">
                                            <?php echo date('M j, Y', strtotime($visitor['created_at'])); ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo date('g:i A', strtotime($visitor['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($visitor['first_time'] === 'yes'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-star-fill me-1"></i>First Time
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">
                                                <i class="bi bi-arrow-repeat me-1"></i>Return Visitor
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($visitor['follow_up_needed'] === 'yes'): ?>
                                            <?php if ($visitor['follow_up_completed'] === 'yes'): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>Completed
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-clock me-1"></i>Pending
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-dash-circle me-1"></i>Not Needed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $visitor['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $visitor['id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="update_followup.php?id=<?php echo $visitor['id']; ?>">
                                                        <i class="bi bi-telephone me-2"></i>Update Follow-up
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="convert.php?id=<?php echo $visitor['id']; ?>">
                                                        <i class="bi bi-arrow-up-circle me-2"></i>Convert to Member/Convert
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $visitor['id']; ?>)">
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
                <nav aria-label="Visitors pagination" class="mt-4">
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
function confirmDelete(visitorId) {
    if (confirm('Are you sure you want to delete this visitor? This action cannot be undone.')) {
        window.location.href = 'delete.php?id=' + visitorId;
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
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.table-hover tbody tr:hover {
    background-color: rgba(25, 135, 84, 0.05);
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php include '../../includes/footer.php'; ?>