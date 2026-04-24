<?php
require_once '../../includes/security.php';

requireLogin('../../login');
requireRole('admin', '../../index');

$success = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'departments';
$edit_department_id = isset($_GET['edit_department']) ? (int) $_GET['edit_department'] : 0;
$edit_cell_center_id = isset($_GET['edit_cell_center']) ? (int) $_GET['edit_cell_center'] : 0;
$edit_department = null;
$edit_cell_center = null;

try {
    require '../../config/database.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrf_token)) {
            $error = 'Invalid request token. Please submit the form again.';
        } else {
            $action = $_POST['action'] ?? '';

        try {
            if ($action === 'add_department') {
                $name = trim($_POST['name'] ?? '');
                if ($name === '') {
                    throw new Exception('Department name is required.');
                }

                $stmt = $pdo->prepare('INSERT INTO departments (name) VALUES (?)');
                $stmt->execute([$name]);
                $success = 'Department created successfully.';
                $active_tab = 'departments';
            }

            if ($action === 'update_department') {
                $department_id = (int) ($_POST['department_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if (!$department_id || $name === '') {
                    throw new Exception('Valid department details are required.');
                }

                $stmt = $pdo->prepare('UPDATE departments SET name = ? WHERE id = ?');
                $stmt->execute([$name, $department_id]);
                $success = 'Department updated successfully.';
                $active_tab = 'departments';
                $edit_department_id = 0;
            }

            if ($action === 'delete_department') {
                $department_id = (int) ($_POST['department_id'] ?? 0);
                if (!$department_id) {
                    throw new Exception('Invalid department selected.');
                }

                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM member_departments WHERE department_id = ?')->execute([$department_id]);
                $pdo->prepare('UPDATE member_roles SET department_id = NULL WHERE department_id = ?')->execute([$department_id]);
                $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$department_id]);
                $pdo->commit();

                $success = 'Department deleted successfully.';
                $active_tab = 'departments';
            }

            if ($action === 'add_cell_center') {
                $name = trim($_POST['name'] ?? '');
                if ($name === '') {
                    throw new Exception('Cell center name is required.');
                }

                $stmt = $pdo->prepare('INSERT INTO cell_centers (name) VALUES (?)');
                $stmt->execute([$name]);
                $success = 'Cell center created successfully.';
                $active_tab = 'cell-centers';
            }

            if ($action === 'update_cell_center') {
                $cell_center_id = (int) ($_POST['cell_center_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if (!$cell_center_id || $name === '') {
                    throw new Exception('Valid cell center details are required.');
                }

                $stmt = $pdo->prepare('UPDATE cell_centers SET name = ? WHERE id = ?');
                $stmt->execute([$name, $cell_center_id]);
                $success = 'Cell center updated successfully.';
                $active_tab = 'cell-centers';
                $edit_cell_center_id = 0;
            }

            if ($action === 'delete_cell_center') {
                $cell_center_id = (int) ($_POST['cell_center_id'] ?? 0);
                if (!$cell_center_id) {
                    throw new Exception('Invalid cell center selected.');
                }

                $pdo->beginTransaction();
                $pdo->prepare('UPDATE member_roles SET cell_center_id = NULL WHERE cell_center_id = ?')->execute([$cell_center_id]);
                $pdo->prepare('DELETE FROM cell_centers WHERE id = ?')->execute([$cell_center_id]);
                $pdo->commit();

                $success = 'Cell center deleted successfully.';
                $active_tab = 'cell-centers';
            }

            if ($action === 'update_settings') {
                $settings = $_POST['settings'] ?? [];
                
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
                
                foreach ($settings as $key => $value) {
                    $stmt->execute([$value, $_SESSION['user_id'], $key]);
                }
                
                $pdo->commit();
                $success = 'System configuration updated successfully.';
                $active_tab = 'configuration';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
        }
    }

    $departments = $pdo->query(
        'SELECT d.*, 
                (SELECT COUNT(*) FROM member_departments md WHERE md.department_id = d.id) AS member_department_count,
                (SELECT COUNT(*) FROM member_roles mr WHERE mr.department_id = d.id) AS primary_member_count
         FROM departments d
         ORDER BY d.name'
    )->fetchAll();

    $cell_centers = [];
    try {
        $cell_centers = $pdo->query(
            'SELECT cc.*, 
                    (SELECT COUNT(*) FROM member_roles mr WHERE mr.cell_center_id = cc.id) AS member_count
             FROM cell_centers cc
             ORDER BY cc.name'
        )->fetchAll();
    } catch (Exception $e) {
        $cell_centers = [];
    }

    if ($edit_department_id) {
        $stmt = $pdo->prepare('SELECT * FROM departments WHERE id = ?');
        $stmt->execute([$edit_department_id]);
        $edit_department = $stmt->fetch();
    }

    if ($edit_cell_center_id) {
        $stmt = $pdo->prepare('SELECT * FROM cell_centers WHERE id = ?');
        $stmt->execute([$edit_cell_center_id]);
        $edit_cell_center = $stmt->fetch();
    }

    $department_count = count($departments);
    $cell_center_count = count($cell_centers);

    // Fetch system settings
    $system_settings_data = $pdo->query("SELECT * FROM system_settings ORDER BY category, id")->fetchAll();
    $grouped_settings = [];
    foreach ($system_settings_data as $s) {
        $category_label = ucwords(str_replace('_', ' ', $s['category']));
        $grouped_settings[$category_label][] = $s;
    }
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

$page_title = 'System Settings - ' . getInstitutionName($pdo);
$page_header = true;
$page_icon = 'bi bi-gear-fill';
$page_heading = 'System Settings';
$page_description = 'Manage departments and cell centers used across the dashboard.';
$page_actions = '<a href="logs" class="btn btn-outline-warning me-2"><i class="bi bi-journal-text"></i> Logs</a>
                 <a href="../../index" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>';

include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/settings.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid settings-page py-4 px-3 px-md-4">
    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm settings-stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="settings-stat-icon bg-primary-subtle text-primary"><i class="bi bi-diagram-3-fill"></i></div>
                    <div>
                        <div class="settings-stat-number"><?php echo number_format($department_count); ?></div>
                        <div class="settings-stat-label">Departments</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm settings-stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="settings-stat-icon bg-success-subtle text-success"><i class="bi bi-geo-alt-fill"></i></div>
                    <div>
                        <div class="settings-stat-number"><?php echo number_format($cell_center_count); ?></div>
                        <div class="settings-stat-label">Cell Centers</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills settings-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'departments' ? 'active' : ''; ?>" href="?tab=departments">
                <i class="bi bi-diagram-3 me-1"></i>Departments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'cell-centers' ? 'active' : ''; ?>" href="?tab=cell-centers">
                <i class="bi bi-geo-alt me-1"></i>Cell Centers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_tab === 'configuration' ? 'active' : ''; ?>" href="?tab=configuration">
                <i class="bi bi-sliders me-1"></i>Configuration
            </a>
        </li>
    </ul>

    <div class="row g-4">
        <div class="col-lg-5 <?php echo $active_tab === 'departments' ? '' : 'd-none'; ?>">
            <div class="card border-0 shadow-sm settings-card h-100">
                <div class="card-body p-4">
                    <h5 class="settings-card-title mb-3">
                        <i class="bi bi-<?php echo $edit_department ? 'pencil-square' : 'plus-circle'; ?> me-2"></i>
                        <?php echo $edit_department ? 'Edit Department' : 'Add Department'; ?>
                    </h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_department ? 'update_department' : 'add_department'; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <?php if ($edit_department): ?>
                            <input type="hidden" name="department_id" value="<?php echo (int) $edit_department['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($edit_department['name'] ?? ''); ?>" required>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i><?php echo $edit_department ? 'Save Changes' : 'Create Department'; ?>
                            </button>
                            <?php if ($edit_department): ?>
                                <a href="?tab=departments" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 <?php echo $active_tab === 'departments' ? '' : 'd-none'; ?>">
            <div class="card border-0 shadow-sm settings-card h-100">
                <div class="card-body p-4">
                    <h5 class="settings-card-title mb-3"><i class="bi bi-list-ul me-2"></i>Departments Directory</h5>
                    <?php if (empty($departments)): ?>
                        <div class="text-center text-muted py-5">No departments found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle settings-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Primary Members</th>
                                        <th>All Membership Links</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $department): ?>
                                        <tr>
                                            <td><div class="fw-semibold"><?php echo htmlspecialchars($department['name']); ?></div></td>
                                            <td><span class="badge text-bg-light"><?php echo (int) $department['primary_member_count']; ?></span></td>
                                            <td><span class="badge text-bg-light"><?php echo (int) $department['member_department_count']; ?></span></td>
                                            <td class="text-end">
                                                <a href="?tab=departments&edit_department=<?php echo (int) $department['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this department? Members using it as primary will be reset, and linked memberships will be removed.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_department">
                                                    <input type="hidden" name="department_id" value="<?php echo (int) $department['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5 <?php echo $active_tab === 'cell-centers' ? '' : 'd-none'; ?>">
            <div class="card border-0 shadow-sm settings-card h-100">
                <div class="card-body p-4">
                    <h5 class="settings-card-title mb-3">
                        <i class="bi bi-<?php echo $edit_cell_center ? 'pencil-square' : 'plus-circle'; ?> me-2"></i>
                        <?php echo $edit_cell_center ? 'Edit Cell Center' : 'Add Cell Center'; ?>
                    </h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_cell_center ? 'update_cell_center' : 'add_cell_center'; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <?php if ($edit_cell_center): ?>
                            <input type="hidden" name="cell_center_id" value="<?php echo (int) $edit_cell_center['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Cell Center Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($edit_cell_center['name'] ?? ''); ?>" required>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i><?php echo $edit_cell_center ? 'Save Changes' : 'Create Cell Center'; ?>
                            </button>
                            <?php if ($edit_cell_center): ?>
                                <a href="?tab=cell-centers" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 <?php echo $active_tab === 'cell-centers' ? '' : 'd-none'; ?>">
            <div class="card border-0 shadow-sm settings-card h-100">
                <div class="card-body p-4">
                    <h5 class="settings-card-title mb-3"><i class="bi bi-list-ul me-2"></i>Cell Centers Directory</h5>
                    <?php if (empty($cell_centers)): ?>
                        <div class="text-center text-muted py-5">No cell centers found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle settings-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Assigned Members</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cell_centers as $cell_center): ?>
                                        <tr>
                                            <td><div class="fw-semibold"><?php echo htmlspecialchars($cell_center['name']); ?></div></td>
                                            <td><span class="badge text-bg-light"><?php echo (int) $cell_center['member_count']; ?></span></td>
                                            <td class="text-end">
                                                <a href="?tab=cell-centers&edit_cell_center=<?php echo (int) $cell_center['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this cell center? Assigned members will be reset to no cell center.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_cell_center">
                                                    <input type="hidden" name="cell_center_id" value="<?php echo (int) $cell_center['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Tab Content -->
    <div class="row <?php echo $active_tab === 'configuration' ? '' : 'd-none'; ?>">
        <div class="col-12">
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="row g-4">
                    <?php foreach ($grouped_settings as $category => $settings): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm settings-card h-100">
                                <div class="card-body p-4">
                                    <h5 class="settings-card-title mb-4">
                                        <i class="bi bi-journal-text me-2"></i><?php echo $category; ?>
                                    </h5>
                                    
                                    <?php foreach ($settings as $setting): ?>
                                        <div class="mb-3">
                                            <label class="form-label d-flex justify-content-between">
                                                <span><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></span>
                                                <?php if ($setting['category'] === 'attendance'): ?>
                                                    <span class="text-info small"><i class="bi bi-info-circle me-1"></i>Behavior</span>
                                                <?php endif; ?>
                                            </label>
                                            
                                            <?php if ($setting['setting_key'] === 'attendance_auto_mark' || $setting['setting_key'] === 'notification_email'): ?>
                                                <select class="form-select" name="settings[<?php echo $setting['setting_key']; ?>]">
                                                    <option value="yes" <?php echo $setting['setting_value'] === 'yes' ? 'selected' : ''; ?>>Yes / Enabled</option>
                                                    <option value="no" <?php echo $setting['setting_value'] === 'no' ? 'selected' : ''; ?>>No / Disabled</option>
                                                </select>
                                            <?php elseif ($setting['setting_key'] === 'sms_provider'): ?>
                                                <select class="form-select" name="settings[<?php echo $setting['setting_key']; ?>]">
                                                    <option value="bulksmsgh" <?php echo $setting['setting_value'] === 'bulksmsgh' ? 'selected' : ''; ?>>BulkSMSGH (Ghana)</option>
                                                    <option value="arkesel" <?php echo $setting['setting_value'] === 'arkesel' ? 'selected' : ''; ?>>Arkesel (Ghana)</option>
                                                    <option value="twilio" <?php echo $setting['setting_value'] === 'twilio' ? 'selected' : ''; ?>>Twilio (International)</option>
                                                    <option value="none" <?php echo $setting['setting_value'] === 'none' ? 'selected' : ''; ?>>None / Disable Sending</option>
                                                </select>
                                            <?php elseif ($setting['setting_key'] === 'sms_batch_size'): ?>
                                                <input type="number" class="form-control" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>" min="10" max="500">
                                            <?php elseif ($setting['setting_key'] === 'sms_batch_time_limit'): ?>
                                                <input type="number" class="form-control" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>" min="5" max="60">
                                            <?php elseif (in_array($setting['setting_key'], ['church_address', 'description', 'ministerial_statuses'])): ?>
                                                <textarea class="form-control" name="settings[<?php echo $setting['setting_key']; ?>]" rows="2"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                            <?php else: ?>
                                                <input type="text" class="form-control" 
                                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($setting['description'])): ?>
                                                <div class="form-text mt-1"><?php echo htmlspecialchars($setting['description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-5 py-2">
                        <i class="bi bi-save me-2"></i>Save All Configurations
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
