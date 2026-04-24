<?php
require_once '../../../includes/security.php';

requireLogin('../../../login');
$user_role = getUserRole();

try {
    require '../../../config/database.php';

    $has_baptized_col = (bool)$pdo->query("SHOW COLUMNS FROM new_convert_roles LIKE 'baptized'")->fetch();
    $has_visitor_id_col = (bool)$pdo->query("SHOW COLUMNS FROM new_convert_roles LIKE 'visitor_id'")->fetch();
    
    // Get success message from URL if redirected from conversion
    $success_message = $_GET['message'] ?? '';
    
    // Get search and filter parameters
    $search          = trim($_GET['search'] ?? '');
    $status_filter   = $_GET['status'] ?? '';
    $baptized_filter = $_GET['baptized'] ?? '';
    $date_from_input = trim($_GET['date_from'] ?? '');
    $date_to_input   = trim($_GET['date_to'] ?? '');

    $date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_input) ? $date_from_input : '';
    $date_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to_input)   ? $date_to_input   : '';
    if ($date_from && $date_to && $date_from > $date_to) {
        [$date_from, $date_to] = [$date_to, $date_from];
    }
    $has_period_filter = (bool)($date_from || $date_to);
    if ($date_from && $date_to) {
        $period_label = date('M j, Y', strtotime($date_from)) . ' â€“ ' . date('M j, Y', strtotime($date_to));
    } elseif ($date_from) {
        $period_label = 'From ' . date('M j, Y', strtotime($date_from));
    } elseif ($date_to) {
        $period_label = 'Until ' . date('M j, Y', strtotime($date_to));
    } else {
        $period_label = 'All Dates';
    }

    // Build shared filter conditions
    $where_conditions = [];
    $params = [];

    if ($search) {
        $where_conditions[] = "(p.full_name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp;
    }
    if ($status_filter) {
        $where_conditions[] = "nc.status = ?";
        $params[] = $status_filter;
    }
    if ($baptized_filter && $has_baptized_col) {
        $where_conditions[] = "nc.baptized = ?";
        $params[] = $baptized_filter;
    }
    if ($date_from && $date_to) {
        $where_conditions[] = "DATE(COALESCE(nc.date_converted, nc.created_at)) BETWEEN ? AND ?";
        $params[] = $date_from; $params[] = $date_to;
    } elseif ($date_from) {
        $where_conditions[] = "DATE(COALESCE(nc.date_converted, nc.created_at)) >= ?";
        $params[] = $date_from;
    } elseif ($date_to) {
        $where_conditions[] = "DATE(COALESCE(nc.date_converted, nc.created_at)) <= ?";
        $params[] = $date_to;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // People-aware stats (join people for accurate stage counts)
    $stats_sql = "
        SELECT
            COUNT(*)                                                                       AS total,
            COUNT(CASE WHEN nc.status = 'active'               THEN 1 END)                AS active,
            COUNT(CASE WHEN nc.status = 'converted_to_member'  THEN 1 END)                AS converted_to_members,
            COUNT(CASE WHEN DATE(COALESCE(nc.date_converted, nc.created_at))
                            >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)                        THEN 1 END) AS recent_converts,
            " . ($has_baptized_col ? "COUNT(CASE WHEN nc.baptized = 'yes' THEN 1 END)" : "0") . " AS baptized,
            COUNT(CASE WHEN nc.person_id IS NOT NULL
                            AND EXISTS (SELECT 1 FROM visitor_roles vr WHERE vr.person_id = nc.person_id)
                                                               THEN 1 END)                AS from_visitor_journey
        FROM new_convert_roles nc
        JOIN people p ON p.id = nc.person_id
    ";
    if ($where_clause !== '') {
        $stats_sql .= ' ' . $where_clause;
        $stats_stmt = $pdo->prepare($stats_sql);
        $stats_stmt->execute($params);
        $stats = $stats_stmt->fetch();
    } else {
        $stats = $pdo->query($stats_sql)->fetch();
    }

    // Table query: join people (stage) + the earliest linked visitor record (journey origin)
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $visitor_id_select = $has_visitor_id_col ? 'COALESCE(nc.visitor_id, v_orig.id) AS visitor_id,' : 'v_orig.id AS visitor_id,';
    $baptized_select = $has_baptized_col ? 'nc.baptized AS baptized,' : "'no' AS baptized,";

    $converts_sql = "
        SELECT nc.id,
               nc.person_id,
               nc.status,
               nc.date_converted,
               nc.member_conversion_date,
               nc.created_at,
               nc.notes,
               p.full_name                                   AS name,
               p.email,
               p.phone,
               p.current_stage                               AS people_stage,
               p.id                                          AS people_id,
               " . $visitor_id_select . "
               " . $baptized_select . "
               v_orig.id                                     AS origin_visitor_id,
               v_orig.created_at                             AS origin_visit_date
        FROM new_convert_roles nc
        JOIN people p ON p.id = nc.person_id
        LEFT JOIN visitor_roles v_orig ON v_orig.person_id = nc.person_id
        $where_clause
        ORDER BY COALESCE(nc.date_converted, nc.created_at) DESC, nc.id DESC
        LIMIT $limit OFFSET $offset
    ";
    $converts_stmt = $pdo->prepare($converts_sql);
    $converts_stmt->execute($params);
    $converts = $converts_stmt->fetchAll();
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM new_convert_roles nc JOIN people p ON p.id = nc.person_id $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_converts = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_converts / $limit);
    
} catch (Exception $e) {
    error_log('Database error loading new converts page: ' . $e->getMessage());
    die('Database error. Please contact the administrator.');
}

$page_title = "New Converts - " . getInstitutionName($pdo);
include '../../../includes/header.php';
?>
<link href="../../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/visitors.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/new-converts.css?v=<?php echo time(); ?>" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">

<!-- Professional New Converts Dashboard -->
<div class="container-fluid py-4 new-converts-page">
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
                        <span class="badge bg-light text-dark"><?php echo $stats['active']; ?> Active in Discipleship</span>
                        <span class="badge bg-light text-dark"><?php echo $stats['from_visitor_journey']; ?> From Visitor Journey</span>
                    </div>
                </div>
                <div class="col-lg-4 text-end">
                    <a href="../visitors/list" class="btn btn-outline-primary me-2">
                        <i class="bi bi-person-badge"></i> Visitors
                    </a>
                    <a href="../members/add" class="btn btn-primary">
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
    <div class="row g-3 g-lg-4 mb-4 new-converts-kpi-row">
            <div class="col-xxl-2 col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                <div class="card border-0 shadow-sm h-100 members-card">
                    <div class="card-body text-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2 fw-semibold">Total Converts</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['total']; ?></h2>
                                <small class="text-white-50"><i class="bi bi-heart"></i> All time records</small>
                            </div>
                            <div class="rounded p-3"><i class="bi bi-heart-fill text-white fs-2"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                <div class="card border-0 shadow-sm h-100 visitors-card">
                    <div class="card-body text-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2 fw-semibold">Active Converts</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['active']; ?></h2>
                                <small class="text-white-50"><i class="bi bi-check-circle"></i> In discipleship</small>
                            </div>
                            <div class="rounded p-3"><i class="bi bi-person-check-fill text-white fs-2"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                <div class="card border-0 shadow-sm h-100 converts-card">
                    <div class="card-body text-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2 fw-semibold">Promoted to Member</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['converted_to_members']; ?></h2>
                                <small class="text-white-50"><i class="bi bi-person-up"></i> Journey completed</small>
                            </div>
                            <div class="rounded p-3"><i class="bi bi-person-up text-white fs-2"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                <div class="card border-0 shadow-sm h-100 converts-card">
                    <div class="card-body text-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2 fw-semibold">Baptized</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['baptized']; ?></h2>
                                <small class="text-white-50"><i class="bi bi-droplet"></i> Water baptized</small>
                            </div>
                            <div class="rounded p-3"><i class="bi bi-droplet-fill text-white fs-2"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                <div class="card border-0 shadow-sm h-100 departments-card">
                    <div class="card-body text-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2 fw-semibold">From Visitor Journey</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['from_visitor_journey']; ?></h2>
                                <small class="text-white-50"><i class="bi bi-signpost-split"></i> Traceable origin</small>
                            </div>
                            <div class="rounded p-3"><i class="bi bi-signpost-split text-white fs-2"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-2 col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 mb-3">
                <div class="card border-0 shadow-sm h-100 departments-card">
                    <div class="card-body text-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-white-50 mb-2 fw-semibold">Recent (30d)</h6>
                                <h2 class="text-white mb-2 fw-bold"><?php echo $stats['recent_converts']; ?></h2>
                                <small class="text-white-50"><i class="bi bi-calendar"></i> New this month</small>
                            </div>
                            <div class="rounded p-3"><i class="bi bi-calendar-plus text-white fs-2"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Filters and Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="text-primary fw-bold mb-1"><i class="bi bi-funnel"></i> Search & Filters</h5>
                        <small class="text-muted">Filter by name, contact, baptism status, and conversion period.</small>
                    </div>
                    <span class="badge bg-light text-dark">
                        <i class="bi bi-calendar-range me-1"></i><?php echo htmlspecialchars($period_label); ?>
                    </span>
                </div>
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
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search & Filter
                        </button>
                        <a href="new_converts" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
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
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($has_period_filter): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">
                                <i class="bi bi-funnel-fill me-1"></i>Period Applied
                            </span>
                        <?php endif; ?>
                        <span class="badge bg-light text-dark">
                            <i class="bi bi-people me-1"></i><?php echo $total_converts; ?> Result<?php echo $total_converts == 1 ? '' : 's'; ?>
                        </span>
                    </div>
            </div>

            <?php if (empty($converts)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-heart text-muted empty-state-icon"></i>
                    <h4 class="text-muted mt-3 mb-2">No Converts Found</h4>
                    <p class="text-muted mb-4">No new converts match your search criteria.</p>
                    <a href="../visitors/list" class="btn btn-primary">
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
                                        <i class="bi bi-signpost-split me-1"></i>Visitor Origin
                                </th>
                                <th class="fw-semibold">
                                        <i class="bi bi-droplet me-1"></i>Baptism
                                    </th>
                                    <th class="fw-semibold">
                                        <i class="bi bi-signpost me-1"></i>Stage
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
                                        <?php $converted_at = $convert['date_converted'] ?: $convert['created_at']; ?>
                                        <span class="d-block fw-semibold">
                                            <?php echo $converted_at ? date('M j, Y', strtotime($converted_at)) : 'â€”'; ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo $converted_at ? date('g:i A', strtotime($converted_at)) : 'â€”'; ?>
                                        </small>
                                    </td>
                                    <td>
                                            <?php if (!empty($convert['origin_visitor_id'])): ?>
                                                <span class="d-block fw-semibold text-primary" style="font-size:.85rem;">
                                                    <i class="bi bi-person-badge me-1"></i>Visitor #<?php echo (int)$convert['origin_visitor_id']; ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?php echo $convert['origin_visit_date'] ? date('M j, Y', strtotime($convert['origin_visit_date'])) : 'â€”'; ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted"><i class="bi bi-dash-circle me-1"></i>Direct add</small>
                                            <?php endif; ?>
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
                                            <?php $pstage = $convert['people_stage'] ?? $convert['status'] ?? null;
                                            if ($pstage === 'member' || $convert['status'] === 'converted_to_member'): ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-person-check me-1"></i>Member
                                            </span>
                                            <?php elseif ($pstage === 'new_convert' || $convert['status'] === 'active'): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>Active Convert
                                                </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-pause-circle me-1"></i>Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!empty($convert['visitor_id'])): ?>
                                            <a href="convert?id=<?php echo (int)$convert['visitor_id']; ?>" class="btn btn-outline-primary" title="Open Convert Workflow">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="convert?id=<?php echo (int)$convert['visitor_id']; ?>" class="btn btn-outline-secondary" title="Manage Convert">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-outline-primary" title="No linked visitor record" disabled>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" title="No linked visitor record" disabled>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($convert['status'] !== 'converted_to_member' && !empty($convert['visitor_id'])): ?>
                                                    <li><a class="dropdown-item" href="convert?id=<?php echo (int)$convert['visitor_id']; ?>">
                                                        <i class="bi bi-person-check me-2"></i>Convert to Member
                                                    </a></li>
                                                    <?php endif; ?>
                                                    <?php if (!empty($convert['visitor_id'])): ?>
                                                    <li><a class="dropdown-item" href="convert?id=<?php echo (int)$convert['visitor_id']; ?>">
                                                        <i class="bi bi-droplet me-2"></i>Update Baptism Status
                                                    </a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><span class="dropdown-item text-muted"><i class="bi bi-info-circle me-2"></i>Delete not available here</span></li>
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
    alert('Delete action for new converts is not available on this page.');
}

function updateBaptism(convertId) {
    alert('Use the Convert Workflow page to update baptism status.');
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

<?php include '../../../includes/footer.php'; ?>
