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
    $where_conditions = [];
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
        $where_conditions[] = "v.follow_up_needed = ?";
        $params[] = $follow_up_filter;
    }
    
    // Exclude visitors who have been converted to new converts
    $where_conditions[] = "(v.status IS NULL OR v.status != 'converted_to_convert')";
    
    // Build the query
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $visitors_sql = "
        SELECT v.*, s.name as service_name 
        FROM visitors v 
        LEFT JOIN services s ON v.service_id = s.id 
        $where_clause 
        ORDER BY DATE(v.created_at) DESC, v.created_at DESC
    ";
    
    $visitors_stmt = $pdo->prepare($visitors_sql);
    $visitors_stmt->execute($params);
    $visitors = $visitors_stmt->fetchAll();
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Page configuration
$page_title = "Visitor Management";
$page_header = true;
$page_icon = "bi bi-person-badge";
$page_heading = "Visitor Management";
$page_description = "Track active church visitors, follow-ups, and manage conversions";
if ($user_role == 'admin') {
    $page_actions = '<a href="new_converts.php" class="btn btn-info me-2"><i class="bi bi-people-fill"></i> View New Converts</a>' .
                   '<a href="add.php" class="btn btn-primary me-2"><i class="bi bi-person-plus"></i> Add Visitor</a>' .
                   '<a href="checkin.php" class="btn btn-success"><i class="bi bi-check-circle"></i> Visitor Check-In</a>';
} else {
    $page_actions = '<a href="new_converts.php" class="btn btn-info me-2"><i class="bi bi-people-fill"></i> View New Converts</a>' .
                   '<a href="checkin.php" class="btn btn-primary"><i class="bi bi-check-circle"></i> Visitor Check-In</a>';
}

include '../../includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Visitors -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-circle bg-primary bg-opacity-10 rounded-circle p-3 mx-auto mb-3">
                    <i class="bi bi-people-fill text-primary fs-4"></i>
                </div>
                <h3 class="fw-bold text-primary mb-1"><?php echo number_format($stats['total']); ?></h3>
                <p class="text-muted mb-0 small">Total Visitors</p>
            </div>
        </div>
    </div>
    
    <!-- First Time Visitors -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-circle bg-success bg-opacity-10 rounded-circle p-3 mx-auto mb-3">
                    <i class="bi bi-star-fill text-success fs-4"></i>
                </div>
                <h3 class="fw-bold text-success mb-1"><?php echo number_format($stats['first_time']); ?></h3>
                <p class="text-muted mb-0 small">First Time</p>
            </div>
        </div>
    </div>
    
    <!-- Return Visitors -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-circle bg-info bg-opacity-10 rounded-circle p-3 mx-auto mb-3">
                    <i class="bi bi-arrow-repeat text-info fs-4"></i>
                </div>
                <h3 class="fw-bold text-info mb-1"><?php echo number_format($stats['return_visitors']); ?></h3>
                <p class="text-muted mb-0 small">Return Visitors</p>
            </div>
        </div>
    </div>
    
    <!-- Recent Visitors -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-circle bg-warning bg-opacity-10 rounded-circle p-3 mx-auto mb-3">
                    <i class="bi bi-clock text-warning fs-4"></i>
                </div>
                <h3 class="fw-bold text-warning mb-1"><?php echo number_format($stats['recent_visitors'] ?? 0); ?></h3>
                <p class="text-muted mb-0 small">Last 7 Days</p>
            </div>
        </div>
    </div>
    
    <!-- Pending Follow-ups -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-circle bg-danger bg-opacity-10 rounded-circle p-3 mx-auto mb-3">
                    <i class="bi bi-exclamation-circle text-danger fs-4"></i>
                </div>
                <h3 class="fw-bold text-danger mb-1"><?php echo number_format($stats['pending_followups'] ?? 0); ?></h3>
                <p class="text-muted mb-0 small">Follow-ups Due</p>
            </div>
        </div>
    </div>
    
    <!-- Became Members -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-circle bg-secondary bg-opacity-10 rounded-circle p-3 mx-auto mb-3">
                    <i class="bi bi-check-circle text-secondary fs-4"></i>
                </div>
                <h3 class="fw-bold text-secondary mb-1"><?php echo number_format($stats['became_members'] ?? 0); ?></h3>
                <p class="text-muted mb-0 small">Became Members</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Search Visitors</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, email, or phone...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Visit Type</label>
                <select class="form-select" name="first_time">
                    <option value="">All Visitors</option>
                    <option value="yes" <?php echo $first_time_filter == 'yes' ? 'selected' : ''; ?>>First Time Only</option>
                    <option value="no" <?php echo $first_time_filter == 'no' ? 'selected' : ''; ?>>Return Visitors</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Follow-up Status</label>
                <select class="form-select" name="follow_up">
                    <option value="">All Status</option>
                    <option value="yes" <?php echo $follow_up_filter == 'yes' ? 'selected' : ''; ?>>Needs Follow-up</option>
                    <option value="no" <?php echo $follow_up_filter == 'no' ? 'selected' : ''; ?>>No Follow-up</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if ($search || $first_time_filter || $follow_up_filter): ?>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Visitors Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-table text-primary me-2"></i>Visitors List</h5>
            <span class="badge bg-primary fs-6"><?php echo count($visitors); ?> visitors found</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($visitors)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-person-x text-muted empty-state-icon"></i>
            </div>
            <h5 class="text-muted">No Visitors Found</h5>
            <p class="text-muted mb-4">No visitors match your current search criteria.</p>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-2"></i>Add First Visitor
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
                            <i class="bi bi-person-plus me-1"></i>Referred By
                        </th>
                        <th class="border-end">
                            <i class="bi bi-calendar me-1"></i>Visit Date
                        </th>
                        <th class="border-end">
                            <i class="bi bi-building me-1"></i>Service
                        </th>
                        <th class="border-end">
                            <i class="bi bi-star me-1"></i>Type
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
                    <?php foreach ($visitors as $visitor): ?>
                    <tr class="align-middle">
                        <td class="border-end">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-person text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($visitor['name']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="border-end">
                            <div>
                                <?php if ($visitor['phone']): ?>
                                <div class="mb-1">
                                    <i class="bi bi-telephone text-muted me-1"></i>
                                    <small><?php echo htmlspecialchars($visitor['phone']); ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if ($visitor['email']): ?>
                                <div>
                                    <i class="bi bi-envelope text-muted me-1"></i>
                                    <small><?php echo htmlspecialchars($visitor['email']); ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if (!$visitor['phone'] && !$visitor['email']): ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="border-end">
                            <div>
                                <?php if ($visitor['invited_by']): ?>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $invited_by = $visitor['invited_by'];
                                    if (strpos($invited_by, 'Member:') === 0) {
                                        echo '<i class="bi bi-person-check text-success me-2"></i>';
                                        echo '<span class="fw-medium">' . htmlspecialchars(str_replace('Member: ', '', $invited_by)) . '</span>';
                                        echo '<br><small class="text-success">Church Member</small>';
                                    } elseif (strpos($invited_by, 'Social Media:') === 0) {
                                        echo '<i class="bi bi-share text-primary me-2"></i>';
                                        echo '<span class="fw-medium">' . htmlspecialchars(str_replace('Social Media: ', '', $invited_by)) . '</span>';
                                        echo '<br><small class="text-primary">Social Media</small>';
                                    } elseif ($invited_by === 'Website') {
                                        echo '<i class="bi bi-globe text-info me-2"></i>';
                                        echo '<span class="fw-medium">Website</span>';
                                        echo '<br><small class="text-info">Online Discovery</small>';
                                    } elseif ($invited_by === 'Self-directed') {
                                        echo '<i class="bi bi-person-walking text-secondary me-2"></i>';
                                        echo '<span class="fw-medium">Self-directed</span>';
                                        echo '<br><small class="text-secondary">Came Alone</small>';
                                    } else {
                                        echo '<i class="bi bi-info-circle text-warning me-2"></i>';
                                        echo '<span class="fw-medium">' . htmlspecialchars($invited_by) . '</span>';
                                        echo '<br><small class="text-warning">Other</small>';
                                    }
                                    ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">Not specified</span>
                                <?php endif; ?>
                            </div>
                        </td>
                            </div>
                        </td>
                        <td class="border-end">
                            <div class="fw-medium"><?php echo date('M d, Y', strtotime($visitor['date'])); ?></div>
                            <small class="text-muted"><?php echo date('l', strtotime($visitor['date'])); ?></small>
                        </td>
                        <td class="border-end">
                            <span class="badge bg-light text-dark">
                                <?php echo htmlspecialchars($visitor['service_name'] ?? 'Unknown Service'); ?>
                            </span>
                        </td>
                        <td class="border-end">
                            <?php if ($visitor['first_time'] == 'yes'): ?>
                            <span class="badge bg-success">
                                <i class="bi bi-star-fill me-1"></i>First Time
                            </span>
                            <?php else: ?>
                            <span class="badge bg-info">
                                <i class="bi bi-arrow-repeat me-1"></i>Return
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="border-end">
                            <div>
                                <?php if (($visitor['became_member'] ?? 'no') == 'yes'): ?>
                                <span class="badge bg-primary mb-1">
                                    <i class="bi bi-check-circle me-1"></i>Member
                                </span>
                                <?php endif; ?>
                                <?php if (($visitor['follow_up_needed'] ?? 'no') == 'yes' && ($visitor['follow_up_completed'] ?? 'no') == 'no'): ?>
                                <span class="badge bg-warning">
                                    <i class="bi bi-clock me-1"></i>Follow-up
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="view.php?id=<?php echo $visitor['id']; ?>">
                                        <i class="bi bi-eye me-2"></i>View Details
                                    </a></li>
                                    <li><a class="dropdown-item" href="edit.php?id=<?php echo $visitor['id']; ?>">
                                        <i class="bi bi-pencil me-2"></i>Edit
                                    </a></li>
                                    <?php if (($visitor['follow_up_needed'] ?? 'no') == 'yes' && ($visitor['follow_up_completed'] ?? 'no') == 'no'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-success" href="#" 
                                           onclick="handleVisitorAction('follow-up', <?php echo $visitor['id']; ?>, '<?php echo addslashes($visitor['name']); ?>')">
                                        <i class="bi bi-telephone me-2"></i>Complete Follow-up
                                    </a></li>
                                    <?php endif; ?>
                                    <?php if (($visitor['became_member'] ?? 'no') != 'yes'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-primary" href="convert.php?id=<?php echo $visitor['id']; ?>">
                                        <i class="bi bi-person-plus me-2"></i>Convert to New Convert
                                    </a></li>
                                    <?php endif; ?>
                                    <?php if ($user_role == 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" 
                                           onclick="handleVisitorAction('delete', <?php echo $visitor['id']; ?>, '<?php echo addslashes($visitor['name']); ?>')">
                                        <i class="bi bi-trash me-2"></i>Delete Visitor
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
// Action button handlers
function handleVisitorAction(action, visitorId, visitorName) {
    let confirmMessage = '';
    let endpoint = '';
    
    switch(action) {
        case 'delete':
            confirmMessage = `Are you sure you want to delete visitor "${visitorName}"? This action cannot be undone.`;
            endpoint = 'delete.php';
            break;
        case 'follow-up':
            confirmMessage = `Mark follow-up as completed for "${visitorName}"?`;
            endpoint = 'update_followup.php';
            break;
        case 'convert':
            confirmMessage = `Convert visitor "${visitorName}" to a church member? This will create a new member record.`;
            endpoint = 'convert_to_member.php';
            break;
        default:
            return;
    }
    
    if (confirm(confirmMessage)) {
        const data = {
            visitor_id: visitorId,
            action: action === 'follow-up' ? 'complete' : action
        };
        
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload(); // Refresh the page to see changes
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