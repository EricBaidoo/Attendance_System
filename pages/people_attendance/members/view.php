<?php
require_once '../../../includes/security.php';

requireLogin('../../../login.php');
$user_role = getUserRole();

$member_id = $_GET['id'] ?? null;
if (!$member_id) {
    header('Location: list.php');
    exit;
}

try {
    require '../../../config/database.php';

    $stmt = $pdo->prepare("SELECT mr.*, p.full_name AS name, p.email, p.phone, d.name AS department_name
                           FROM member_roles mr
                           JOIN people p ON mr.person_id = p.id
                           LEFT JOIN departments d ON mr.department_id = d.id
                           WHERE mr.id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();

    if (!$member) {
        header('Location: list.php?error=Member not found');
        exit;
    }

    try {
        $dept_stmt = $pdo->prepare("SELECT d.id, d.name
                                    FROM member_departments md
                                    JOIN departments d ON md.department_id = d.id
                                    WHERE md.member_id = ?
                                    ORDER BY d.name");
        $dept_stmt->execute([$member_id]);
        $member_departments = $dept_stmt->fetchAll();

        if (empty($member_departments) && !empty($member['department_id'])) {
            $fallback = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
            $fallback->execute([$member['department_id']]);
            $member_departments = $fallback->fetchAll();
        }
    } catch (Exception $e) {
        $member_departments = [];
    }

    $cell_center_name = null;
    if (!empty($member['cell_center_id'])) {
        try {
            $cc_stmt = $pdo->prepare("SELECT name FROM cell_centers WHERE id = ?");
            $cc_stmt->execute([$member['cell_center_id']]);
            $cc_row = $cc_stmt->fetch();
            $cell_center_name = $cc_row ? $cc_row['name'] : null;
        } catch (Exception $e) {
            $cell_center_name = null;
        }
    }

    $source_visitor = null;
    $source_convert = null;

    $source_conditions_people = [];
    $source_conditions_joined = [];
    $source_params = [];
    if (!empty($member['phone'])) {
        $source_conditions_people[] = "phone = ?";
        $source_conditions_joined[] = "p.phone = ?";
        $source_params[] = $member['phone'];
    }
    if (!empty($member['email'])) {
        $source_conditions_people[] = "email = ?";
        $source_conditions_joined[] = "p.email = ?";
        $source_params[] = $member['email'];
    }

    if (!empty($source_conditions_people)) {
        $source_convert_sql = "SELECT * FROM new_convert_roles WHERE person_id IN (SELECT id FROM people WHERE " . implode(' OR ', $source_conditions_people) . ") ORDER BY date_converted DESC, id DESC LIMIT 1";
        $source_convert_stmt = $pdo->prepare($source_convert_sql);
        $source_convert_stmt->execute($source_params);
        $source_convert = $source_convert_stmt->fetch();

        if (!empty($source_convert['visitor_id'])) {
            $source_visitor_stmt = $pdo->prepare("SELECT vr.id, p.full_name AS name, vr.created_at, vr.status FROM visitor_roles vr JOIN people p ON vr.person_id = p.id WHERE vr.id = ?");
            $source_visitor_stmt->execute([$source_convert['visitor_id']]);
            $source_visitor = $source_visitor_stmt->fetch();
        } else {
            $source_visitor_sql = "SELECT vr.id, p.full_name AS name, vr.created_at, vr.status FROM visitor_roles vr JOIN people p ON vr.person_id = p.id WHERE " . implode(' OR ', $source_conditions_joined) . " ORDER BY vr.created_at DESC, vr.id DESC LIMIT 1";
            $source_visitor_stmt = $pdo->prepare($source_visitor_sql);
            $source_visitor_stmt->execute($source_params);
            $source_visitor = $source_visitor_stmt->fetch();
        }
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = "View Member - {$member['name']}";
$page_header = true;
$page_icon = "bi bi-person";
$page_heading = "Member Details";
$page_description = "View member information and details";
$page_actions = '<a href="edit.php?id=' . $member['id'] . '" class="btn btn-warning"><i class="bi bi-pencil"></i> Edit Member</a>
                <a href="list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../../includes/header.php';

$department_names = !empty($member_departments)
    ? implode(', ', array_map(function ($dept) {
        return $dept['name'];
    }, $member_departments))
    : 'No Department Assigned';

$primary_department_name = !empty($member['department_name'])
    ? $member['department_name']
    : 'Not assigned';

$status_text = ucfirst($member['status'] ?? 'inactive');
$status_class = ($member['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary';
$ministerial = $member['ministerial_status'] ?? '';
$ministerial_label = $ministerial ?: 'Not Set';
?>
<link href="../../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/members.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid member-view-page py-4 px-3 px-md-4">
    <div class="card border-0 shadow-sm member-view-hero mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="member-view-avatar">
                        <?php echo strtoupper(substr($member['name'], 0, 2)); ?>
                    </div>
                    <div>
                        <h2 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($member['name']); ?></h2>
                        <p class="mb-2 text-muted"><?php echo htmlspecialchars($department_names); ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge <?php echo $status_class; ?> member-view-badge"><?php echo $status_text; ?></span>
                            <span class="badge text-bg-light member-view-badge">
                                <?php echo ($member['baptized'] ?? 'no') === 'yes' ? 'Baptized' : 'Not Baptized'; ?>
                            </span>
                            <span class="badge text-bg-primary member-view-badge"><?php echo htmlspecialchars($ministerial_label); ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 member-view-actions">
                    <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-1"></i>Edit Member
                    </a>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to List
                    </a>
                    <a href="../../../pages/people_attendance/attendance/view.php?member_id=<?php echo $member['id']; ?>" class="btn btn-outline-info">
                        <i class="bi bi-calendar-check me-1"></i>Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100 member-view-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><i class="bi bi-person-circle me-2"></i>Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="member-view-info-grid">
                        <div class="member-view-item">
                            <div class="member-view-label">Full Name</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($member['name']); ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Gender</div>
                            <div class="member-view-value"><?php echo ucfirst($member['gender'] ?? 'Not specified'); ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Date of Birth</div>
                            <div class="member-view-value"><?php echo !empty($member['dob']) ? date('F d, Y', strtotime($member['dob'])) : 'Not provided'; ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Marital Status</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($member['marital_status'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="member-view-item member-view-item-full">
                            <div class="member-view-label">Location</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($member['location'] ?? 'Not provided'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100 member-view-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><i class="bi bi-telephone me-2"></i>Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="member-view-info-grid">
                        <div class="member-view-item member-view-item-full">
                            <div class="member-view-label">Email Address</div>
                            <div class="member-view-value">
                                <?php if (!empty($member['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($member['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Primary Phone</div>
                            <div class="member-view-value">
                                <?php if (!empty($member['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($member['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Alternative Phone</div>
                            <div class="member-view-value">
                                <?php if (!empty($member['phone2'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($member['phone2']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($member['phone2']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100 member-view-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><i class="bi bi-building me-2"></i>Church Information</h5>
                </div>
                <div class="card-body">
                    <div class="member-view-info-grid">
                        <div class="member-view-item member-view-item-full">
                            <div class="member-view-label">Department(s)</div>
                            <div class="member-view-value">
                                <?php if (!empty($member_departments)): ?>
                                    <?php foreach ($member_departments as $dept): ?>
                                        <span class="badge text-bg-primary me-1 mb-1"><?php echo htmlspecialchars($dept['name']); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Primary Department</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($primary_department_name); ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Congregation Group</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($member['congregation_group'] ?? 'Adult'); ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Ministerial Status</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($ministerial_label); ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Role in Church</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($member['role_in_church'] ?? 'Not provided'); ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Cell Center</div>
                            <div class="member-view-value"><?php echo htmlspecialchars($cell_center_name ?? 'Not assigned'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100 member-view-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><i class="bi bi-info-circle me-2"></i>Additional Information</h5>
                </div>
                <div class="card-body">
                    <div class="member-view-info-grid">
                        <div class="member-view-item">
                            <div class="member-view-label">Member ID</div>
                            <div class="member-view-value">#<?php echo str_pad($member['id'], 4, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div class="member-view-item">
                            <div class="member-view-label">Date Joined</div>
                            <div class="member-view-value"><?php echo !empty($member['date_joined']) ? date('F d, Y', strtotime($member['date_joined'])) : 'Not recorded'; ?></div>
                        </div>
                        <div class="member-view-item member-view-item-full">
                            <div class="member-view-label">Last Updated</div>
                            <div class="member-view-value">
                                <?php echo (!empty($member['updated_at'])) ? date('F d, Y \a\t g:i A', strtotime($member['updated_at'])) : 'Not available'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm member-view-card">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="fw-bold text-primary mb-0"><i class="bi bi-diagram-3 me-2"></i>Journey Tracking</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-stretch">
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100">
                                <div class="small text-muted mb-1">Stage 1</div>
                                <h6 class="mb-1">Visitor</h6>
                                <?php if (!empty($source_visitor)): ?>
                                    <div class="mb-2">#<?php echo (int)$source_visitor['id']; ?> — <?php echo htmlspecialchars($source_visitor['name']); ?></div>
                                    <a class="btn btn-sm btn-outline-primary" href="../visitors/view.php?id=<?php echo (int)$source_visitor['id']; ?>">Open Visitor</a>
                                <?php else: ?>
                                    <div class="text-muted">No linked visitor found</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100">
                                <div class="small text-muted mb-1">Stage 2</div>
                                <h6 class="mb-1">New Convert</h6>
                                <?php if (!empty($source_convert)): ?>
                                    <div class="mb-2">#<?php echo (int)$source_convert['id']; ?> — <?php echo htmlspecialchars($source_convert['name']); ?></div>
                                    <?php if (!empty($source_convert['visitor_id'])): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="../visitors/convert.php?id=<?php echo (int)$source_convert['visitor_id']; ?>">Open Convert Flow</a>
                                    <?php else: ?>
                                        <span class="text-muted small">No visitor link on convert</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-muted">No linked new convert found</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100">
                                <div class="small text-muted mb-1">Stage 3</div>
                                <h6 class="mb-1">Member</h6>
                                <div class="mb-2">#<?php echo (int)$member['id']; ?> — <?php echo htmlspecialchars($member['name']); ?></div>
                                <span class="badge text-bg-success">Current Record</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>