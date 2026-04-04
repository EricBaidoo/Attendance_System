<?php
require_once '../../includes/security.php';
requireLogin('../../login');
requireRole('admin', '../../index');
require_once '../../config/database.php';

$success = '';
$error = '';

$role_options = function_exists('getAssignableRoles') ? getAssignableRoles() : [
    'data_staff' => 'Data Staff',
    'accountant' => 'Accountant',
    'communication_team' => 'Communication Team',
    'general_admin' => 'General Admin',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create_user') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role_key = trim($_POST['role'] ?? '');

                if ($username === '' || $password === '' || !isset($role_options[$role_key])) {
                    throw new Exception('Username, password and role are required.');
                }

                if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
                    throw new Exception('Username must be 3-50 characters and can contain letters, numbers, dots, dashes and underscores.');
                }

                if (strlen($password) < 6) {
                    throw new Exception('Password must be at least 6 characters long.');
                }

                $check_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
                $check_stmt->execute([$username]);
                if ((int)$check_stmt->fetchColumn() > 0) {
                    throw new Exception('That username already exists.');
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
                $insert_stmt->execute([$username, $password_hash, $role_key]);
                $success = 'User account created successfully.';
            }

            if ($action === 'update_role') {
                $user_id = (int)($_POST['user_id'] ?? 0);
                $role_key = trim($_POST['role'] ?? '');

                if ($user_id <= 0 || !isset($role_options[$role_key])) {
                    throw new Exception('A valid user and role are required.');
                }

                if ($user_id === (int)($_SESSION['user_id'] ?? 0) && $role_key !== 'general_admin') {
                    throw new Exception('You cannot remove your own General Admin access from this page.');
                }

                $update_stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $update_stmt->execute([$role_key, $user_id]);
                $success = 'User role updated successfully.';
            }

            if ($action === 'reset_password') {
                $user_id = (int)($_POST['user_id'] ?? 0);
                $new_password = $_POST['new_password'] ?? '';

                if ($user_id <= 0 || $new_password === '') {
                    throw new Exception('A valid user and new password are required.');
                }

                if (strlen($new_password) < 6) {
                    throw new Exception('New password must be at least 6 characters long.');
                }

                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update_stmt->execute([$password_hash, $user_id]);
                $success = 'Password reset successfully.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$users = $pdo->query('SELECT id, username, role FROM users ORDER BY username ASC')->fetchAll();

$role_stats = [
    'data_staff' => 0,
    'accountant' => 0,
    'communication_team' => 0,
    'general_admin' => 0,
];

foreach ($users as $user_row) {
    $normalized_role = strtolower(trim((string)$user_row['role']));
    if ($normalized_role === 'staff') {
        $normalized_role = 'data_staff';
    }
    if ($normalized_role === 'admin') {
        $normalized_role = 'general_admin';
    }
    if (isset($role_stats[$normalized_role])) {
        $role_stats[$normalized_role]++;
    }
}

$page_title = 'User Management - Bridge Ministries International';
$page_header = true;
$page_icon = 'bi bi-person-gear';
$page_heading = 'User Management';
$page_description = 'Create users, assign module roles and reset staff passwords.';
$page_actions = '<a href="logs" class="btn btn-outline-warning me-2"><i class="bi bi-journal-text"></i> Logs</a>
                 <a href="../../index" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>';

include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../assets/css/users.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid users-page py-4 px-3 px-md-4">
    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm user-stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="user-stat-icon bg-primary-subtle text-primary"><i class="bi bi-database-fill"></i></div>
                    <div>
                        <div class="user-stat-number"><?php echo number_format($role_stats['data_staff']); ?></div>
                        <div class="user-stat-label">Data Staff</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm user-stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="user-stat-icon bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="user-stat-number"><?php echo number_format($role_stats['accountant']); ?></div>
                        <div class="user-stat-label">Accountants</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm user-stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="user-stat-icon bg-info-subtle text-info"><i class="bi bi-chat-dots-fill"></i></div>
                    <div>
                        <div class="user-stat-number"><?php echo number_format($role_stats['communication_team']); ?></div>
                        <div class="user-stat-label">Communication</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm user-stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="user-stat-icon bg-warning-subtle text-warning"><i class="bi bi-shield-lock-fill"></i></div>
                    <div>
                        <div class="user-stat-number"><?php echo number_format($role_stats['general_admin']); ?></div>
                        <div class="user-stat-label">General Admin</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm user-card h-100">
                <div class="card-body p-4">
                    <h5 class="user-card-title mb-3"><i class="bi bi-person-plus-fill me-2"></i>Create User</h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="create_user">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Temporary Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select role</option>
                                <?php foreach ($role_options as $role_key => $role_label): ?>
                                    <option value="<?php echo htmlspecialchars($role_key); ?>"><?php echo htmlspecialchars($role_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>Create User</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm user-card">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="user-card-title mb-0"><i class="bi bi-people-fill me-2"></i>System Users</h5>
                        <span class="text-muted small"><?php echo number_format(count($users)); ?> total accounts</span>
                    </div>

                    <?php if (empty($users)): ?>
                        <div class="text-center text-muted py-5">No users found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle users-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Assigned Role</th>
                                        <th>Update Role</th>
                                        <th>Reset Password</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user_row): ?>
                                        <?php
                                            $current_role = strtolower(trim((string)$user_row['role']));
                                            if ($current_role === 'staff') {
                                                $current_role = 'data_staff';
                                            }
                                            if ($current_role === 'admin') {
                                                $current_role = 'general_admin';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($user_row['username']); ?></div>
                                                <small class="text-muted">User ID: <?php echo (int)$user_row['id']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-light users-role-badge"><?php echo htmlspecialchars(getRoleLabel($user_row['role'])); ?></span>
                                            </td>
                                            <td>
                                                <form method="POST" class="row g-2 align-items-center">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$user_row['id']; ?>">
                                                    <div class="col-12 col-lg-8">
                                                        <select class="form-select form-select-sm" name="role">
                                                            <?php foreach ($role_options as $role_key => $role_label): ?>
                                                                <option value="<?php echo htmlspecialchars($role_key); ?>" <?php echo $current_role === $role_key ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($role_label); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-lg-4">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">Save</button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="POST" class="row g-2 align-items-center">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$user_row['id']; ?>">
                                                    <div class="col-12 col-lg-8">
                                                        <input type="password" class="form-control form-control-sm" name="new_password" placeholder="New password" required>
                                                    </div>
                                                    <div class="col-12 col-lg-4">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Reset</button>
                                                    </div>
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
</div>

<?php include '../../includes/footer.php'; ?>

