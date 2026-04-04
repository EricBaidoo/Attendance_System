<?php
require_once '../../../includes/security.php';

requireLogin('../../../login');
$user_role = getUserRole();

try {
	require '../../../config/database.php';
} catch (Exception $e) {
	error_log('Database connection failed in visitors list: ' . $e->getMessage());
	die('Database connection failed. Please contact the administrator.');
}

$search = trim($_GET['search'] ?? '');
$first_time_filter = $_GET['first_time'] ?? '';
$followup_filter = $_GET['followup'] ?? '';
$service_filter = $_GET['service_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$allowed_first_time = ['', 'yes', 'no'];
$allowed_followup = ['', 'pending', 'completed', 'not_needed'];
if (!in_array($first_time_filter, $allowed_first_time, true)) {
	$first_time_filter = '';
}
if (!in_array($followup_filter, $allowed_followup, true)) {
	$followup_filter = '';
}

$hasColumn = function ($table, $column) use ($pdo) {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
	$stmt->execute([$table, $column]);
	return (bool)$stmt->fetchColumn();
};

$has_name = $hasColumn('visitor_roles', 'name');
$has_email = $hasColumn('visitor_roles', 'email');
$has_phone = $hasColumn('visitor_roles', 'phone');
$has_date = $hasColumn('visitor_roles', 'date');
$has_created_at = $hasColumn('visitor_roles', 'created_at');
$has_follow_up_needed = $hasColumn('visitor_roles', 'follow_up_needed');
$has_follow_up_completed = $hasColumn('visitor_roles', 'follow_up_completed');
$has_became_member = $hasColumn('visitor_roles', 'became_member');

$display_name_expr = $has_name ? "COALESCE(NULLIF(vr.name, ''), p.full_name)" : "p.full_name";
$display_email_expr = $has_email ? "COALESCE(NULLIF(vr.email, ''), p.email)" : "p.email";
$display_phone_expr = $has_phone ? "COALESCE(NULLIF(vr.phone, ''), p.phone)" : "p.phone";

if ($has_date && $has_created_at) {
	$visit_date_expr = 'COALESCE(vr.date, vr.created_at)';
} elseif ($has_date) {
	$visit_date_expr = 'vr.date';
} elseif ($has_created_at) {
	$visit_date_expr = 'vr.created_at';
} else {
	$visit_date_expr = 'NOW()';
}

$where = [];
$params = [];

if ($search !== '') {
	$search_param = '%' . $search . '%';
	$where[] = "({$display_name_expr} LIKE ? OR {$display_email_expr} LIKE ? OR {$display_phone_expr} LIKE ? OR COALESCE(vr.how_heard, '') LIKE ? OR COALESCE(vr.invited_by, '') LIKE ? )";
	$params[] = $search_param;
	$params[] = $search_param;
	$params[] = $search_param;
	$params[] = $search_param;
	$params[] = $search_param;
}

if ($first_time_filter !== '') {
	$where[] = 'vr.first_time = ?';
	$params[] = $first_time_filter;
}

if ($service_filter !== '') {
	$where[] = 'vr.service_id = ?';
	$params[] = $service_filter;
}

if ($date_from !== '') {
	$where[] = "DATE({$visit_date_expr}) >= ?";
	$params[] = $date_from;
}

if ($date_to !== '') {
	$where[] = "DATE({$visit_date_expr}) <= ?";
	$params[] = $date_to;
}

if ($followup_filter !== '') {
	if ($has_follow_up_needed && $has_follow_up_completed) {
		if ($followup_filter === 'pending') {
			$where[] = "COALESCE(vr.follow_up_needed, 'no') = 'yes' AND COALESCE(vr.follow_up_completed, 'no') = 'no'";
		} elseif ($followup_filter === 'completed') {
			$where[] = "COALESCE(vr.follow_up_needed, 'no') = 'yes' AND COALESCE(vr.follow_up_completed, 'no') = 'yes'";
		} elseif ($followup_filter === 'not_needed') {
			$where[] = "COALESCE(vr.follow_up_needed, 'no') = 'no'";
		}
	}
}

$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

try {
	$services_stmt = $pdo->query('SELECT id, name FROM services ORDER BY name');
	$services = $services_stmt->fetchAll();
} catch (Exception $e) {
	$services = [];
}

$stats = [
	'total' => 0,
	'first_time' => 0,
	'returning' => 0,
	'pending_followup' => 0,
	'converted' => 0,
];

try {
	$stats_sql = "SELECT
			COUNT(*) AS total,
			SUM(CASE WHEN COALESCE(vr.first_time, 'no') = 'yes' THEN 1 ELSE 0 END) AS first_time,
			SUM(CASE WHEN COALESCE(vr.first_time, 'no') = 'no' THEN 1 ELSE 0 END) AS returning";

	if ($has_follow_up_needed && $has_follow_up_completed) {
		$stats_sql .= ", SUM(CASE WHEN COALESCE(vr.follow_up_needed, 'no') = 'yes' AND COALESCE(vr.follow_up_completed, 'no') = 'no' THEN 1 ELSE 0 END) AS pending_followup";
	} else {
		$stats_sql .= ', 0 AS pending_followup';
	}

	if ($has_became_member) {
		$stats_sql .= ", SUM(CASE WHEN COALESCE(vr.became_member, 'no') = 'yes' THEN 1 ELSE 0 END) AS converted";
	} else {
		$stats_sql .= ', 0 AS converted';
	}

	$stats_sql .= " FROM visitor_roles vr LEFT JOIN people p ON vr.person_id = p.id {$where_sql}";

	$stats_stmt = $pdo->prepare($stats_sql);
	$stats_stmt->execute($params);
	$stats_result = $stats_stmt->fetch();
	if ($stats_result) {
		$stats = array_merge($stats, $stats_result);
	}
} catch (Exception $e) {
	error_log('Visitors list stats query failed: ' . $e->getMessage());
}

try {
	$list_sql = "SELECT
			vr.id,
			vr.person_id,
			{$display_name_expr} AS visitor_name,
			{$display_email_expr} AS visitor_email,
			{$display_phone_expr} AS visitor_phone,
			vr.first_time,
			vr.how_heard,
			vr.invited_by,
			" . ($has_follow_up_needed ? "vr.follow_up_needed" : "'no' AS follow_up_needed") . ",
			" . ($has_follow_up_completed ? "vr.follow_up_completed" : "'no' AS follow_up_completed") . ",
			" . ($has_became_member ? "vr.became_member" : "'no' AS became_member") . ",
			{$visit_date_expr} AS visit_date,
			s.name AS service_name
		FROM visitor_roles vr
		LEFT JOIN people p ON vr.person_id = p.id
		LEFT JOIN services s ON vr.service_id = s.id
		{$where_sql}
		ORDER BY {$visit_date_expr} DESC, vr.id DESC
		LIMIT {$limit} OFFSET {$offset}";

	$list_stmt = $pdo->prepare($list_sql);
	$list_stmt->execute($params);
	$visitors = $list_stmt->fetchAll();

	$count_sql = "SELECT COUNT(*) FROM visitor_roles vr LEFT JOIN people p ON vr.person_id = p.id {$where_sql}";
	$count_stmt = $pdo->prepare($count_sql);
	$count_stmt->execute($params);
	$total_records = (int)$count_stmt->fetchColumn();
	$total_pages = max(1, (int)ceil($total_records / $limit));
} catch (Exception $e) {
	error_log('Visitors list query failed: ' . $e->getMessage());
	$visitors = [];
	$total_records = 0;
	$total_pages = 1;
}

$query_params = $_GET;
unset($query_params['page']);
$query_base = http_build_query($query_params);
$page_title = 'Visitors Directory - Bridge Ministries International';

include '../../../includes/header.php';
?>
<link href="../../../assets/css/visitors.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid py-4 px-3 px-md-4 visitors-directory-page">
	<?php if (!empty($_GET['success'])): ?>
		<div class="alert alert-success border-0 shadow-sm mb-4">
			<i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
		</div>
	<?php endif; ?>
	<?php if (!empty($_GET['error'])): ?>
		<div class="alert alert-danger border-0 shadow-sm mb-4">
			<i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
		</div>
	<?php endif; ?>

	<div class="card visitors-hero-card border-0 mb-4">
		<div class="card-body p-4">
			<div class="row align-items-center g-3">
				<div class="col-12 col-lg-8">
					<h2 class="card-title mb-1 fw-bold text-success"><i class="bi bi-person-badge-fill me-2"></i>Visitors Directory</h2>
					<p class="text-muted mb-0">Track first-time visitors, follow-up progress, and conversion journey.</p>
				</div>
				<div class="col-12 col-lg-4">
					<div class="d-flex justify-content-end gap-2">
						<a href="add" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Add Visitor</a>
						<a href="new_converts" class="btn btn-outline-primary"><i class="bi bi-arrow-right-circle me-1"></i>New Converts</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mb-4 visitors-kpi-row">
		<div class="col-12 col-sm-6 col-xl-3">
			<div class="card journey-kpi h-100">
				<div class="card-body">
					<small class="text-muted d-block mb-1">Total Visitors</small>
					<h3 class="mb-0 fw-bold"><?php echo (int)$stats['total']; ?></h3>
				</div>
			</div>
		</div>
		<div class="col-12 col-sm-6 col-xl-3">
			<div class="card journey-kpi h-100">
				<div class="card-body">
					<small class="text-muted d-block mb-1">First Time</small>
					<h3 class="mb-0 fw-bold"><?php echo (int)$stats['first_time']; ?></h3>
				</div>
			</div>
		</div>
		<div class="col-12 col-sm-6 col-xl-3">
			<div class="card journey-kpi h-100">
				<div class="card-body">
					<small class="text-muted d-block mb-1">Follow-up Pending</small>
					<h3 class="mb-0 fw-bold"><?php echo (int)$stats['pending_followup']; ?></h3>
				</div>
			</div>
		</div>
		<div class="col-12 col-sm-6 col-xl-3">
			<div class="card journey-kpi h-100">
				<div class="card-body">
					<small class="text-muted d-block mb-1">Converted to Member</small>
					<h3 class="mb-0 fw-bold"><?php echo (int)$stats['converted']; ?></h3>
				</div>
			</div>
		</div>
	</div>

	<div class="card visitors-filter-card border-0 mb-4">
		<div class="card-body p-4">
			<form method="GET" class="visitors-filter-form">
				<div class="row g-3">
					<div class="col-12 col-md-6 col-xl-3">
						<label class="form-label mb-1">Search</label>
						<div class="input-group">
							<span class="input-group-text"><i class="bi bi-search"></i></span>
							<input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, phone, email...">
						</div>
					</div>
					<div class="col-6 col-md-3 col-xl-2">
						<label class="form-label mb-1">Visit Type</label>
						<select name="first_time" class="form-select">
							<option value="" <?php echo $first_time_filter === '' ? 'selected' : ''; ?>>All</option>
							<option value="yes" <?php echo $first_time_filter === 'yes' ? 'selected' : ''; ?>>First Time</option>
							<option value="no" <?php echo $first_time_filter === 'no' ? 'selected' : ''; ?>>Returning</option>
						</select>
					</div>
					<div class="col-6 col-md-3 col-xl-2">
						<label class="form-label mb-1">Follow-up</label>
						<select name="followup" class="form-select">
							<option value="" <?php echo $followup_filter === '' ? 'selected' : ''; ?>>All</option>
							<option value="pending" <?php echo $followup_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
							<option value="completed" <?php echo $followup_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
							<option value="not_needed" <?php echo $followup_filter === 'not_needed' ? 'selected' : ''; ?>>Not Needed</option>
						</select>
					</div>
					<div class="col-12 col-md-6 col-xl-2">
						<label class="form-label mb-1">Service</label>
						<select name="service_id" class="form-select">
							<option value="">All Services</option>
							<?php foreach ($services as $service): ?>
								<option value="<?php echo (int)$service['id']; ?>" <?php echo (string)$service_filter === (string)$service['id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($service['name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-6 col-md-3 col-xl-1">
						<label class="form-label mb-1">From</label>
						<input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
					</div>
					<div class="col-6 col-md-3 col-xl-1">
						<label class="form-label mb-1">To</label>
						<input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
					</div>
					<div class="col-12 visitors-filter-actions">
						<button type="submit" class="btn btn-success"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
						<a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Reset</a>
					</div>
				</div>
			</form>
		</div>
	</div>

	<div class="card border-0">
		<div class="card-body p-3 p-md-4">
			<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 visitors-table-toolbar">
				<span class="badge bg-light text-dark visitors-toolbar-badge"><?php echo (int)$total_records; ?> records found</span>
				<span class="badge bg-white text-muted visitors-period-badge">Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?></span>
			</div>

			<div class="table-responsive visitors-table-wrap">
				<table class="table table-hover visitors-table align-middle">
					<thead>
						<tr>
							<th>Visitor</th>
							<th>Contact</th>
							<th>Visit</th>
							<th>Service</th>
							<th>Follow-up</th>
							<th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($visitors)): ?>
							<tr>
								<td colspan="6" class="text-center py-5 text-muted">
									<i class="bi bi-inbox fs-3 d-block mb-2"></i>
									No visitors found for the selected filters.
								</td>
							</tr>
						<?php else: ?>
							<?php foreach ($visitors as $visitor): ?>
								<?php
								$initial = strtoupper(substr((string)($visitor['visitor_name'] ?? '?'), 0, 1));
								$is_first_time = ($visitor['first_time'] ?? 'no') === 'yes';
								$follow_up_needed = ($visitor['follow_up_needed'] ?? 'no') === 'yes';
								$follow_up_completed = ($visitor['follow_up_completed'] ?? 'no') === 'yes';
								?>
								<tr>
									<td>
										<div class="d-flex align-items-center gap-2">
											<div class="avatar-circle"><?php echo htmlspecialchars($initial); ?></div>
											<div>
												<div class="fw-semibold"><?php echo htmlspecialchars($visitor['visitor_name'] ?? 'Unknown'); ?></div>
												<?php if ($is_first_time): ?>
													<span class="badge bg-success-subtle text-success-emphasis border">First Time</span>
												<?php else: ?>
													<span class="badge bg-info-subtle text-info-emphasis border">Returning</span>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td>
										<div class="small fw-semibold"><?php echo htmlspecialchars($visitor['visitor_phone'] ?: '-'); ?></div>
										<div class="text-muted small"><?php echo htmlspecialchars($visitor['visitor_email'] ?: '-'); ?></div>
									</td>
									<td>
										<div class="small fw-semibold"><?php echo date('M d, Y', strtotime((string)$visitor['visit_date'])); ?></div>
										<div class="text-muted small"><?php echo htmlspecialchars($visitor['how_heard'] ?: 'Not specified'); ?></div>
									</td>
									<td><?php echo htmlspecialchars($visitor['service_name'] ?: 'N/A'); ?></td>
									<td>
										<?php if ($follow_up_needed && !$follow_up_completed): ?>
											<span class="badge bg-warning text-dark">Pending</span>
										<?php elseif ($follow_up_needed && $follow_up_completed): ?>
											<span class="badge bg-success">Completed</span>
										<?php else: ?>
											<span class="badge bg-light text-dark border">Not Needed</span>
										<?php endif; ?>
									</td>
									<td class="text-end">
										<div class="btn-group btn-group-sm" role="group">
											<a href="view?id=<?php echo (int)$visitor['id']; ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
											<a href="edit?id=<?php echo (int)$visitor['id']; ?>" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
											<a href="convert?id=<?php echo (int)$visitor['id']; ?>" class="btn btn-outline-success" title="Convert"><i class="bi bi-arrow-up-circle"></i></a>
											<button type="button" class="btn btn-outline-danger" title="Delete" onclick="deleteVisitor(<?php echo (int)$visitor['id']; ?>, '<?php echo htmlspecialchars(addslashes((string)($visitor['visitor_name'] ?? 'Visitor'))); ?>')"><i class="bi bi-trash"></i></button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ($total_pages > 1): ?>
				<nav class="mt-3" aria-label="Visitors pagination">
					<ul class="pagination justify-content-center mb-0">
						<?php
						$prev_page = max(1, $page - 1);
						$next_page = min($total_pages, $page + 1);
						$prefix = $query_base === '' ? '' : $query_base . '&';
						?>
						<li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
							<a class="page-link" href="?<?php echo $prefix; ?>page=<?php echo $prev_page; ?>">Previous</a>
						</li>
						<?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
							<li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
								<a class="page-link" href="?<?php echo $prefix; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
							</li>
						<?php endfor; ?>
						<li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
							<a class="page-link" href="?<?php echo $prefix; ?>page=<?php echo $next_page; ?>">Next</a>
						</li>
					</ul>
				</nav>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
function deleteVisitor(visitorId, visitorName) {
	if (!confirm('Delete ' + visitorName + '? This action cannot be undone.')) {
		return;
	}

	fetch('delete', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify({ visitor_id: visitorId })
	})
	.then(function(response) {
		return response.json();
	})
	.then(function(data) {
		if (data.success) {
			window.location.href = 'list?success=' + encodeURIComponent(data.message || 'Visitor deleted successfully');
		} else {
			alert(data.message || 'Failed to delete visitor.');
		}
	})
	.catch(function() {
		alert('Could not complete delete request. Please try again.');
	});
}
</script>

<?php include '../../../includes/footer.php'; ?>
