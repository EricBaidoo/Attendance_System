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
    
    // Build the query
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $converts_sql = "
        SELECT nc.*, d.name as department_name, v.name as visitor_name 
        FROM new_converts nc 
        LEFT JOIN departments d ON nc.department_id = d.id 
        LEFT JOIN visitors v ON nc.visitor_id = v.id 
        $where_clause 
        ORDER BY nc.date_converted DESC, nc.created_at DESC
    ";
    
    $converts_stmt = $pdo->prepare($converts_sql);
    $converts_stmt->execute($params);
    $converts = $converts_stmt->fetchAll();
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "New Converts Management";
$page_header = true;
$page_icon = "bi bi-people-fill";
$page_heading = "New Converts Management";
$page_description = "Track new converts and their journey to full membership";

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/visitors.css">

<!-- Success Message -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
        <div>
            <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total New Converts -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card stats-card border-0 h-100">
            <div class="card-body text-center">
                <div class="bg-primary bg-opacity-10 rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-people-fill text-primary fs-4"></i>
                </div>
                <h3 class="fw-bold text-primary mb-1"><?= number_format($stats['total'] ?? 0) ?></h3>
                <p class="text-muted mb-0 small">Total New Converts</p>
            </div>
        </div>
    </div>
    
    <!-- Active Converts -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card stats-card border-0 h-100">
            <div class="card-body text-center">
                <div class="bg-success bg-opacity-10 rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-person-check-fill text-success fs-4"></i>
                </div>
                <h3 class="fw-bold text-success mb-1"><?= number_format($stats['active'] ?? 0) ?></h3>
                <p class="text-muted mb-0 small">Active Converts</p>
            </div>
        </div>
    </div>
    
    <!-- Converted to Members -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card stats-card border-0 h-100">
            <div class="card-body text-center">
                <div class="bg-warning bg-opacity-10 rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-arrow-up-circle-fill text-warning fs-4"></i>
                </div>
                <h3 class="fw-bold text-warning mb-1"><?= number_format($stats['converted_to_members'] ?? 0) ?></h3>
                <p class="text-muted mb-0 small">Now Members</p>
            </div>
        </div>
    </div>
    
    <!-- Recent Converts (30 days) -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card stats-card border-0 h-100">
            <div class="card-body text-center">
                <div class="bg-info bg-opacity-10 rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-clock-fill text-info fs-4"></i>
                </div>
                <h3 class="fw-bold text-info mb-1"><?= number_format($stats['recent_converts'] ?? 0) ?></h3>
                <p class="text-muted mb-0 small">Last 30 Days</p>
            </div>
        </div>
    </div>
    
    <!-- Baptized -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card stats-card border-0 h-100">
            <div class="card-body text-center">
                <div class="bg-primary bg-opacity-10 rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-droplet-fill text-primary fs-4"></i>
                </div>
                <h3 class="fw-bold text-primary mb-1"><?= number_format($stats['baptized'] ?? 0) ?></h3>
                <p class="text-muted mb-0 small">Baptized</p>
            </div>
        </div>
    </div>
    
    <!-- Not Baptized -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card stats-card border-0 h-100">
            <div class="card-body text-center">
                <div class="bg-secondary bg-opacity-10 rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px;">
                    <i class="bi bi-hourglass text-secondary fs-4"></i>
                </div>
                <h3 class="fw-bold text-secondary mb-1"><?= number_format($stats['not_baptized'] ?? 0) ?></h3>
                <p class="text-muted mb-0 small">Not Baptized</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Search New Converts</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by name, email, or phone...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="converted_to_member" <?= $status_filter == 'converted_to_member' ? 'selected' : '' ?>>Converted to Member</option>
                    <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Baptized</label>
                <select class="form-select" name="baptized">
                    <option value="">All</option>
                    <option value="yes" <?= $baptized_filter == 'yes' ? 'selected' : '' ?>>Baptized</option>
                    <option value="no" <?= $baptized_filter == 'no' ? 'selected' : '' ?>>Not Baptized</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if ($search || $status_filter || $baptized_filter): ?>
                    <a href="new_converts.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- New Converts Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table text-primary me-2"></i>New Converts List</h5>
            <span class="badge bg-primary fs-6"><?= count($converts) ?> converts found</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($converts)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
            </div>
            <h5 class="text-muted">No New Converts Found</h5>
            <p class="text-muted mb-4">No new converts match your current search criteria.</p>
            <a href="../visitors/list.php" class="btn btn-primary">
                <i class="bi bi-eye me-2"></i>Check Visitors for Conversion
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="border-end">
                            <i class="bi bi-person me-1"></i>Name
                        </th>
                        <th class="border-end">
                            <i class="bi bi-envelope me-1"></i>Contact
                        </th>
                        <th class="border-end">
                            <i class="bi bi-building me-1"></i>Department
                        </th>
                        <th class="border-end">
                            <i class="bi bi-calendar me-1"></i>Conversion Date
                        </th>
                        <th class="border-end">
                            <i class="bi bi-droplet me-1"></i>Baptized
                        </th>
                        <th class="border-end">
                            <i class="bi bi-check-circle me-1"></i>Status
                        </th>
                        <th class="text-center">
                            <i class="bi bi-gear"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($converts as $convert): ?>
                    <tr class="align-middle">
                        <td class="border-end">
                            <div class="d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-person-check text-success"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($convert['name']) ?></div>
                                    <?php if ($convert['visitor_name']): ?>
                                    <small class="text-muted">From visitor: <?= htmlspecialchars($convert['visitor_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="border-end">
                            <div>
                                <?php if ($convert['phone']): ?>
                                <div class="mb-1">
                                    <i class="bi bi-telephone text-muted me-1"></i>
                                    <small><?= htmlspecialchars($convert['phone']) ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if ($convert['email']): ?>
                                <div>
                                    <i class="bi bi-envelope text-muted me-1"></i>
                                    <small><?= htmlspecialchars($convert['email']) ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if (!$convert['phone'] && !$convert['email']): ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="border-end">
                            <?php if ($convert['department_name']): ?>
                            <span class="badge bg-light text-dark">
                                <?= htmlspecialchars($convert['department_name']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">No Department</span>
                            <?php endif; ?>
                        </td>
                        <td class="border-end">
                            <div class="fw-medium"><?= date('M d, Y', strtotime($convert['date_converted'])) ?></div>
                            <small class="text-muted"><?= date('l', strtotime($convert['date_converted'])) ?></small>
                        </td>
                        <td class="border-end">
                            <?php if ($convert['baptized'] == 'yes'): ?>
                            <span class="badge bg-primary convert-badge">
                                <i class="bi bi-droplet-fill me-1"></i>Baptized
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary convert-badge">
                                <i class="bi bi-hourglass me-1"></i>Not Baptized
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="border-end">
                            <?php if ($convert['status'] == 'active'): ?>
                            <span class="badge bg-success convert-badge">
                                <i class="bi bi-check-circle me-1"></i>Active
                            </span>
                            <?php elseif ($convert['status'] == 'converted_to_member'): ?>
                            <span class="badge bg-warning convert-badge">
                                <i class="bi bi-arrow-up-circle me-1"></i>Now Member
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary convert-badge">
                                <i class="bi bi-x-circle me-1"></i>Inactive
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="view_convert.php?id=<?= $convert['id'] ?>">
                                        <i class="bi bi-eye me-2"></i>View Details
                                    </a></li>
                                    <li><a class="dropdown-item" href="edit_convert.php?id=<?= $convert['id'] ?>">
                                        <i class="bi bi-pencil me-2"></i>Edit
                                    </a></li>
                                    <?php if ($convert['status'] == 'active'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-warning" href="../visitors/convert.php?id=<?= $convert['visitor_id'] ?>">
                                        <i class="bi bi-arrow-up-circle me-2"></i>Convert to Member
                                    </a></li>
                                    <?php endif; ?>
                                    <?php if ($user_role == 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteConvert(<?= $convert['id'] ?>, '<?= addslashes($convert['name']) ?>')">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function deleteConvert(convertId, convertName) {
    if (confirm(`Are you sure you want to delete new convert "${convertName}"? This action cannot be undone.`)) {
        fetch('delete_convert.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                convert_id: convertId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}
</script>