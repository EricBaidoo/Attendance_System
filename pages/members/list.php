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

// Build query filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)";
    $search_param = "%$search%";
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

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get statistics based on congregation groups
try {
    // First check if congregation_group column exists
    $column_check = $pdo->query("SHOW COLUMNS FROM members LIKE 'congregation_group'");
    
    if ($column_check->rowCount() > 0) {
        // Column exists, use congregation_group
        $stats_sql = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
            COUNT(CASE WHEN gender = 'female' THEN 1 END) as female,
            COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized,
            COUNT(CASE WHEN congregation_group = 'Adult' OR congregation_group IS NULL THEN 1 END) as adults,
            COUNT(CASE WHEN (congregation_group = 'Adult' OR congregation_group IS NULL) AND gender = 'male' THEN 1 END) as adult_male,
            COUNT(CASE WHEN (congregation_group = 'Adult' OR congregation_group IS NULL) AND gender = 'female' THEN 1 END) as adult_female,
            COUNT(CASE WHEN congregation_group = 'Youth' THEN 1 END) as youths,
            COUNT(CASE WHEN congregation_group = 'Youth' AND gender = 'male' THEN 1 END) as youth_male,
            COUNT(CASE WHEN congregation_group = 'Youth' AND gender = 'female' THEN 1 END) as youth_female,
            COUNT(CASE WHEN congregation_group = 'Teen' THEN 1 END) as teens,
            COUNT(CASE WHEN congregation_group = 'Teen' AND gender = 'male' THEN 1 END) as teen_male,
            COUNT(CASE WHEN congregation_group = 'Teen' AND gender = 'female' THEN 1 END) as teen_female,
            COUNT(CASE WHEN congregation_group = 'Children' THEN 1 END) as children,
            COUNT(CASE WHEN congregation_group = 'Children' AND gender = 'male' THEN 1 END) as children_male,
            COUNT(CASE WHEN congregation_group = 'Children' AND gender = 'female' THEN 1 END) as children_female
            FROM members m $where_clause";
    } else {
        // Column doesn't exist, use basic stats only
        $stats_sql = "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
            COUNT(CASE WHEN gender = 'female' THEN 1 END) as female,
            COUNT(CASE WHEN baptized = 'yes' THEN 1 END) as baptized,
            COUNT(*) as adults, COUNT(*) as adult_male, 0 as adult_female,
            0 as youths, 0 as youth_male, 0 as youth_female,
            0 as teens, 0 as teen_male, 0 as teen_female,
            0 as children, 0 as children_male, 0 as children_female
            FROM members m $where_clause";
    }
    
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute($params);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = [
        'total' => 0, 'male' => 0, 'female' => 0, 'baptized' => 0,
        'adults' => 0, 'adult_male' => 0, 'adult_female' => 0,
        'youths' => 0, 'youth_male' => 0, 'youth_female' => 0,
        'teens' => 0, 'teen_male' => 0, 'teen_female' => 0,
        'children' => 0, 'children_male' => 0, 'children_female' => 0
    ];
}

// Page configuration
$page_title = "Members Directory - Bridge Ministries International";
$page_header = true;
$page_icon = "bi bi-people-fill";
$page_heading = "Member Management";
$page_description = "Manage church members and their information";
$page_actions = '<a href="add.php" class="btn btn-primary me-2"><i class="bi bi-person-plus"></i> Add New Member</a>';

include '../../includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Members -->
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Total Members</h6>
                        <h2 class="text-primary mb-0"><?php echo number_format($stats['total']); ?></h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Male Members -->
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Male Members</h6>
                        <h2 class="text-info mb-0"><?php echo number_format($stats['male']); ?></h2>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-3 p-3">
                        <i class="bi bi-person text-info fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Female Members -->
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Female Members</h6>
                        <h2 class="text-warning mb-0"><?php echo number_format($stats['female']); ?></h2>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                        <i class="bi bi-person-dress text-warning fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Baptized Members -->
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Baptized</h6>
                        <h2 class="text-success mb-0"><?php echo number_format($stats['baptized']); ?></h2>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-3 p-3">
                        <i class="bi bi-droplet text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Congregation Groups Section -->
<div class="row g-3 mb-4">
    <!-- Adults Section -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Adults</h6>
            </div>
            <div class="card-body p-3">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h4 mb-0 text-primary"><?php echo number_format($stats['adults']); ?></div>
                        <small class="text-muted">Total</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 mb-0 text-info"><?php echo number_format($stats['adult_male']); ?></div>
                        <small class="text-muted"><i class="bi bi-person"></i> Male</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 mb-0 text-warning"><?php echo number_format($stats['adult_female']); ?></div>
                        <small class="text-muted"><i class="bi bi-person-dress"></i> Female</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Youths Section -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Youths</h6>
            </div>
            <div class="card-body p-3">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h4 mb-0 text-success"><?php echo number_format($stats['youths']); ?></div>
                        <small class="text-muted">Total</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 mb-0 text-info"><?php echo number_format($stats['youth_male']); ?></div>
                        <small class="text-muted"><i class="bi bi-person"></i> Male</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 mb-0 text-warning"><?php echo number_format($stats['youth_female']); ?></div>
                        <small class="text-muted"><i class="bi bi-person-dress"></i> Female</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Teens & Children Section -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Teens & Children</h6>
            </div>
            <div class="card-body p-2">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                            <div class="h6 mb-0 text-secondary"><?php echo number_format($stats['teens']); ?></div>
                            <small class="text-muted">Teens</small>
                            <div class="small mt-1">
                                <span class="badge bg-info"><?php echo $stats['teen_male']; ?>M</span>
                                <span class="badge bg-warning"><?php echo $stats['teen_female']; ?>F</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                            <div class="h6 mb-0 text-secondary"><?php echo number_format($stats['children']); ?></div>
                            <small class="text-muted">Children</small>
                            <div class="small mt-1">
                                <span class="badge bg-info"><?php echo $stats['children_male']; ?>M</span>
                                <span class="badge bg-warning"><?php echo $stats['children_female']; ?>F</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0">
            <i class="bi bi-search text-primary me-2"></i>
            Search & Filter Members
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-medium">Search Members</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by name, email, or phone...">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium">Department</label>
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php
                    try {
                        $dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
                        while ($dept = $dept_stmt->fetch()) {
                            $selected = ($department_filter == $dept['id']) ? 'selected' : '';
                            echo '<option value="' . $dept['id'] . '" ' . $selected . '>' . htmlspecialchars($dept['name']) . '</option>';
                        }
                    } catch (Exception $e) {
                        echo '<option value="">No departments found</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium">Status</label>
                <select class="form-select" name="status">
                    <option value="" <?php echo ($status_filter == '') ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium">Congregation Group</label>
                <select class="form-select" name="group">
                    <option value="" <?php echo ($group_filter == '') ? 'selected' : ''; ?>>All Groups</option>
                    <option value="Adult" <?php echo ($group_filter == 'Adult') ? 'selected' : ''; ?>>Adult</option>
                    <option value="Youth" <?php echo ($group_filter == 'Youth') ? 'selected' : ''; ?>>Youth</option>
                    <option value="Teen" <?php echo ($group_filter == 'Teen') ? 'selected' : ''; ?>>Teen</option>
                    <option value="Children" <?php echo ($group_filter == 'Children') ? 'selected' : ''; ?>>Children</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-medium">Baptism</label>
                <select class="form-select" name="baptism">
                    <option value="" <?php echo ($baptism_filter == '') ? 'selected' : ''; ?>>All Members</option>
                    <option value="yes" <?php echo ($baptism_filter == 'yes') ? 'selected' : ''; ?>>Baptized</option>
                    <option value="no" <?php echo ($baptism_filter == 'no') ? 'selected' : ''; ?>>Not Baptized</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Members Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-people-fill text-primary me-2"></i>
                Members List (<?php echo number_format($stats['total']); ?>)
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success btn-sm" onclick="exportData()">
                    <i class="bi bi-download"></i> Export
                </button>
                <a href="add.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Member
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($search || $department_filter || $status_filter != 'active' || $baptism_filter || $group_filter): ?>
    <div class="alert alert-info m-3 mb-0">
        <strong>Active Filters:</strong>
        <?php if ($search): ?>
            <span class="badge bg-primary ms-2">Search: <?php echo htmlspecialchars($search); ?></span>
        <?php endif; ?>
        <?php if ($department_filter): ?>
            <?php 
            $dept_name = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $dept_name->execute([$department_filter]);
            $dept = $dept_name->fetch();
            ?>
            <span class="badge bg-info ms-2">Department: <?php echo htmlspecialchars($dept['name']); ?></span>
        <?php endif; ?>
        <?php if ($status_filter != 'active'): ?>
            <span class="badge bg-warning ms-2">Status: <?php echo ucfirst($status_filter); ?></span>
        <?php endif; ?>
        <?php if ($baptism_filter): ?>
            <span class="badge bg-success ms-2">Baptism: <?php echo ($baptism_filter == 'yes') ? 'Baptized' : 'Not Baptized'; ?></span>
        <?php endif; ?>
        <?php if ($group_filter): ?>
            <span class="badge bg-secondary ms-2">Group: <?php echo htmlspecialchars($group_filter); ?></span>
        <?php endif; ?>
        <a href="list.php" class="btn btn-outline-secondary btn-sm ms-3">Clear All Filters</a>
    </div>
    <?php endif; ?>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Member</th>
                        <th>Contact Info</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Location</th>
                        <th>Occupation</th>
                        <th>Congregation Group</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT m.*, d.name AS department_name 
                            FROM members m 
                            LEFT JOIN departments d ON m.department_id = d.id 
                            $where_clause 
                            ORDER BY m.name";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    if ($stmt->rowCount() == 0) {
                        echo '<tr><td colspan="10" class="text-center py-5">';
                        echo '<div class="text-muted">';
                        echo '<i class="bi bi-inbox display-4 d-block mb-3"></i>';
                        echo '<h5>No members found</h5>';
                        echo '<p>Try adjusting your search filters or <a href="add.php">add a new member</a></p>';
                        echo '</div>';
                        echo '</td></tr>';
                    } else {
                        while ($row = $stmt->fetch()) {
                            $initials = strtoupper(substr($row['name'], 0, 2));
                            echo '<tr>';
                            
                            // Member column
                            echo '<td class="ps-4">';
                            echo '<div class="d-flex align-items-center">';
                            echo '<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 600;">' . $initials . '</div>';
                            echo '<div>';
                            echo '<div class="fw-semibold">' . htmlspecialchars($row['name']) . '</div>';
                            echo '</div>';
                            echo '</div>';
                            echo '</td>';
                            
                            // Contact column
                            echo '<td>';
                            echo '<div>';
                            if ($row['phone']) {
                                echo '<div class="small"><i class="bi bi-telephone text-muted me-1"></i>' . htmlspecialchars($row['phone']) . '</div>';
                            }
                            if ($row['email']) {
                                echo '<div class="small text-muted"><i class="bi bi-envelope me-1"></i>' . htmlspecialchars($row['email']) . '</div>';
                            }
                            if (!$row['phone'] && !$row['email']) {
                                echo '<span class="text-muted">No contact info</span>';
                            }
                            echo '</div>';
                            echo '</td>';
                            
                            // Gender column
                            echo '<td>';
                            if ($row['gender']) {
                                $gender_icon = $row['gender'] == 'male' ? 'bi-person' : 'bi-person-dress';
                                $gender_color = $row['gender'] == 'male' ? 'text-primary' : 'text-danger';
                                echo '<i class="bi ' . $gender_icon . ' ' . $gender_color . ' me-1"></i>' . ucfirst($row['gender']);
                            } else {
                                echo '<span class="text-muted">Not specified</span>';
                            }
                            echo '</td>';
                            
                            // Date of Birth column
                            echo '<td>';
                            if ($row['dob']) {
                                $dob = new DateTime($row['dob']);
                                $now = new DateTime();
                                $age = $now->diff($dob)->y;
                                echo '<div class="small">' . $dob->format('M j, Y') . '</div>';
                                echo '<div class="text-muted small">Age: ' . $age . '</div>';
                            } else {
                                echo '<span class="text-muted">Not provided</span>';
                            }
                            echo '</td>';
                            
                            // Location column
                            echo '<td>';
                            echo $row['location'] ? htmlspecialchars($row['location']) : '<span class="text-muted">Not specified</span>';
                            echo '</td>';
                            
                            // Occupation column
                            echo '<td>';
                            if ($row['occupation']) {
                                echo '<span class="badge bg-light text-dark border">' . htmlspecialchars($row['occupation']) . '</span>';
                            } else {
                                echo '<span class="text-muted">Not specified</span>';
                            }
                            echo '</td>';
                            
                            // Congregation group column
                            echo '<td>';
                            $group = $row['congregation_group'] ?? 'Adult';
                            $group_colors = [
                                'Adult' => 'bg-primary',
                                'Youth' => 'bg-success', 
                                'Teen' => 'bg-info',
                                'Children' => 'bg-warning'
                            ];
                            $color_class = $group_colors[$group] ?? 'bg-secondary';
                            echo '<span class="badge ' . $color_class . '">' . htmlspecialchars($group) . '</span>';
                            echo '</td>';
                            
                            // Department column
                            echo '<td>';
                            echo $row['department_name'] ? '<span class="badge bg-light text-dark">' . htmlspecialchars($row['department_name']) . '</span>' : '<span class="text-muted">Unassigned</span>';
                            echo '</td>';
                            
                            // Status column
                            echo '<td>';
                            $status_class = $row['status'] == 'active' ? 'bg-success' : 'bg-danger';
                            echo '<span class="badge ' . $status_class . '">' . ucfirst($row['status']) . '</span>';
                            if ($row['baptized'] == 'yes') {
                                echo '<br><small class="text-success mt-1"><i class="bi bi-check-circle"></i> Baptized</small>';
                            }
                            echo '</td>';
                            
                            // Actions column
                            echo '<td class="pe-4">';
                            echo '<div class="btn-group btn-group-sm" role="group">';
                            echo '<a href="view.php?id=' . $row['id'] . '" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>';
                            echo '<a href="edit.php?id=' . $row['id'] . '" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>';
                            echo '<button class="btn btn-outline-danger" title="Delete" onclick="confirmDelete(' . $row['id'] . ', \'' . htmlspecialchars($row['name']) . '\')"><i class="bi bi-trash"></i></button>';
                            echo '</div>';
                            echo '</td>';
                            
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportData() {
    const table = document.querySelector('.table');
    const rows = Array.from(table.querySelectorAll('tr'));
    let csv = [];
    
    rows.forEach(row => {
        const cols = Array.from(row.querySelectorAll('td, th'));
        const csvRow = cols.slice(0, -1).map(col => col.innerText.replace(/,/g, ';')); // Exclude actions column
        csv.push(csvRow.join(','));
    });
    
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = 'members_list.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

function confirmDelete(memberId, memberName) {
    if (confirm('Are you sure you want to delete ' + memberName + '? This action cannot be undone.')) {
        // In production, implement AJAX delete
        alert('Delete functionality would be implemented here with proper backend handling.');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>