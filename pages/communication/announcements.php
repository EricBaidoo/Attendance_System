<?php
require_once '../../includes/security.php';
requireLogin('../../login.php');
require_once '../../config/database.php';

$page_title = 'Communication Announcements - Bridge Ministries International';
$page_heading = 'Communication Announcements';
$page_header = false;

$statuses = ['draft', 'published', 'archived'];

$success = '';
$error = '';
$edit_row = null;

$form_values = [
    'title' => '',
    'body' => '',
    'audience' => 'All Members',
    'status' => 'draft',
    'publish_date' => '',
    'expires_at' => '',
];

$form_action = 'add_announcement';
$submit_label = 'Save Announcement';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['add_announcement', 'update_announcement', 'delete_announcement'], true)) {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        if ($action === 'delete_announcement') {
            $announcement_id = (int)($_POST['announcement_id'] ?? 0);
            if ($announcement_id <= 0) {
                $error = 'Invalid announcement selected.';
            } else {
                try {
                    $delete_stmt = $pdo->prepare('DELETE FROM communication_announcements WHERE id = ?');
                    $delete_stmt->execute([$announcement_id]);
                    if ($delete_stmt->rowCount() > 0) {
                        $success = 'Announcement deleted successfully.';
                    } else {
                        $error = 'Announcement not found or already removed.';
                    }
                } catch (Exception $e) {
                    $error = 'Unable to delete announcement right now.';
                }
            }
        }

        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $audience = trim($_POST['audience'] ?? '');
        $status = trim($_POST['status'] ?? 'draft');
        $publish_date = trim($_POST['publish_date'] ?? '');
        $expires_at = trim($_POST['expires_at'] ?? '');

        if ($action !== 'delete_announcement' && $status === 'published' && $publish_date === '') {
            $publish_date = date('Y-m-d H:i:s');
        }

        if ($action !== 'delete_announcement') {
            $form_values = [
                'title' => $title,
                'body' => $body,
                'audience' => $audience,
                'status' => $status,
                'publish_date' => $publish_date,
                'expires_at' => $expires_at,
            ];
        }

        if ($action !== 'delete_announcement' && $title === '') {
            $error = 'Announcement title is required.';
        } elseif ($action !== 'delete_announcement' && $body === '') {
            $error = 'Announcement message is required.';
        } elseif ($action !== 'delete_announcement' && !in_array($status, $statuses, true)) {
            $error = 'Please select a valid status.';
        } elseif ($action !== 'delete_announcement' && $publish_date !== '' && strtotime($publish_date) === false) {
            $error = 'Publish date is invalid.';
        } elseif ($action !== 'delete_announcement' && $expires_at !== '' && strtotime($expires_at) === false) {
            $error = 'Expiry date is invalid.';
        } elseif ($action !== 'delete_announcement' && $publish_date !== '' && $expires_at !== '' && strtotime($expires_at) < strtotime($publish_date)) {
            $error = 'Expiry date cannot be earlier than publish date.';
        } elseif ($action === 'add_announcement') {
            try {
                $insert_stmt = $pdo->prepare(
                    'INSERT INTO communication_announcements (title, body, audience, status, publish_date, expires_at, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $insert_stmt->execute([
                    mb_substr($title, 0, 180),
                    $body,
                    $audience !== '' ? mb_substr($audience, 0, 120) : null,
                    $status,
                    $publish_date !== '' ? date('Y-m-d H:i:s', strtotime($publish_date)) : null,
                    $expires_at !== '' ? date('Y-m-d H:i:s', strtotime($expires_at)) : null,
                    (int)($_SESSION['user_id'] ?? 0) ?: null,
                ]);

                $success = 'Announcement saved successfully.';
                $form_values = [
                    'title' => '',
                    'body' => '',
                    'audience' => 'All Members',
                    'status' => 'draft',
                    'publish_date' => '',
                    'expires_at' => '',
                ];
            } catch (Exception $e) {
                $error = 'Unable to save announcement right now.';
            }
        } elseif ($action === 'update_announcement') {
            $announcement_id = (int)($_POST['announcement_id'] ?? 0);
            if ($announcement_id <= 0) {
                $error = 'Invalid announcement selected for update.';
            } else {
                try {
                    $update_stmt = $pdo->prepare(
                        'UPDATE communication_announcements SET title = ?, body = ?, audience = ?, status = ?, publish_date = ?, expires_at = ?, created_by_user_id = ? WHERE id = ?'
                    );
                    $update_stmt->execute([
                        mb_substr($title, 0, 180),
                        $body,
                        $audience !== '' ? mb_substr($audience, 0, 120) : null,
                        $status,
                        $publish_date !== '' ? date('Y-m-d H:i:s', strtotime($publish_date)) : null,
                        $expires_at !== '' ? date('Y-m-d H:i:s', strtotime($expires_at)) : null,
                        (int)($_SESSION['user_id'] ?? 0) ?: null,
                        $announcement_id,
                    ]);

                    $success = $update_stmt->rowCount() > 0
                        ? 'Announcement updated successfully.'
                        : 'No changes were made to this announcement.';

                    $form_values = [
                        'title' => '',
                        'body' => '',
                        'audience' => 'All Members',
                        'status' => 'draft',
                        'publish_date' => '',
                        'expires_at' => '',
                    ];
                } catch (Exception $e) {
                    $error = 'Unable to update announcement right now.';
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        try {
            $edit_stmt = $pdo->prepare('SELECT id, title, body, audience, status, publish_date, expires_at FROM communication_announcements WHERE id = ? LIMIT 1');
            $edit_stmt->execute([$edit_id]);
            $edit_row = $edit_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($edit_row) {
                $form_action = 'update_announcement';
                $submit_label = 'Update Announcement';
                $form_values = [
                    'title' => (string)$edit_row['title'],
                    'body' => (string)$edit_row['body'],
                    'audience' => (string)($edit_row['audience'] ?? ''),
                    'status' => (string)$edit_row['status'],
                    'publish_date' => !empty($edit_row['publish_date']) ? date('Y-m-d\TH:i', strtotime((string)$edit_row['publish_date'])) : '',
                    'expires_at' => !empty($edit_row['expires_at']) ? date('Y-m-d\TH:i', strtotime((string)$edit_row['expires_at'])) : '',
                ];
            }
        } catch (Exception $e) {
            $error = $error !== '' ? $error : 'Unable to load announcement for editing.';
        }
    }
}

$announcements = [];
$total_announcements = 0;
$published_announcements = 0;

try {
    $summary_stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total_announcements,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_announcements
         FROM communication_announcements"
    );
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total_announcements = (int)($summary['total_announcements'] ?? 0);
    $published_announcements = (int)($summary['published_announcements'] ?? 0);

    $list_stmt = $pdo->query(
        "SELECT a.id, a.title, a.body, a.audience, a.status, a.publish_date, a.expires_at, a.created_at, u.username
         FROM communication_announcements a
         LEFT JOIN users u ON u.id = a.created_by_user_id
         ORDER BY COALESCE(a.publish_date, a.created_at) DESC, a.id DESC
         LIMIT 40"
    );
    $announcements = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $error !== '' ? $error : 'Communication tables are unavailable. Please run database/db_updates.sql.';
}

include '../../includes/header.php';
?>
<link href="../../assets/css/communication.css?v=<?php echo @filemtime('../../assets/css/communication.css'); ?>" rel="stylesheet">

<div class="container-fluid communication-page py-4">
    <?php if ($success): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <nav class="nav nav-pills communication-subnav mb-3">
        <a class="nav-link" href="dashboard.php">Overview</a>
        <a class="nav-link active" href="announcements.php">Announcements</a>
        <a class="nav-link" href="campaigns.php">Campaigns</a>
        <a class="nav-link" href="sms.php">SMS Center</a>
        <a class="nav-link" href="logs.php">Logs</a>
    </nav>

    <section class="row g-3 communication-stats mb-1">
        <div class="col-12 col-md-6">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-blue"><i class="bi bi-megaphone-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Total Announcements</span>
                    <h3 class="communication-stat-value"><?php echo number_format($total_announcements); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-6">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-green"><i class="bi bi-check2-circle"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Published Announcements</span>
                    <h3 class="communication-stat-value"><?php echo number_format($published_announcements); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-4">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-plus-circle-fill"></i> Add Announcement</h2>
                    <p>Create internal or public church announcements.</p>
                </div>

                <form method="POST" class="communication-form mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($form_action); ?>">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="announcement_id" value="<?php echo (int)$edit_row['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" maxlength="180" value="<?php echo htmlspecialchars($form_values['title']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Audience</label>
                        <input type="text" class="form-control" name="audience" maxlength="120" value="<?php echo htmlspecialchars($form_values['audience']); ?>" placeholder="All Members">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>"<?php echo $form_values['status'] === $status ? ' selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Publish Date (Optional)</label>
                        <input type="datetime-local" class="form-control" name="publish_date" value="<?php echo htmlspecialchars($form_values['publish_date']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expires At (Optional)</label>
                        <input type="datetime-local" class="form-control" name="expires_at" value="<?php echo htmlspecialchars($form_values['expires_at']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="body" rows="5" required><?php echo htmlspecialchars($form_values['body']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save2-fill me-1"></i> <?php echo htmlspecialchars($submit_label); ?>
                    </button>
                    <?php if ($edit_row): ?>
                        <a href="announcements.php" class="btn btn-outline-secondary w-100 mt-2">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-list-ul"></i> Announcement List</h2>
                    <p>Latest communication announcements.</p>
                </div>

                <div class="communication-table-wrap mt-3">
                    <table class="table communication-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="communication-col-date">Date</th>
                                <th class="communication-col-title">Title</th>
                                <th>Audience</th>
                                <th>Status</th>
                                <th class="communication-col-content">Message</th>
                                <th class="text-end communication-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($announcements)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No announcements yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $row): ?>
                                    <tr>
                                        <td class="communication-col-date"><?php echo htmlspecialchars(date('d M Y', strtotime((string)($row['publish_date'] ?: $row['created_at'])))); ?></td>
                                        <td class="communication-col-title"><?php echo htmlspecialchars((string)$row['title']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['audience'] ?: 'All')); ?></td>
                                        <td><span class="communication-badge status-<?php echo htmlspecialchars(strtolower((string)$row['status'])); ?>"><?php echo htmlspecialchars((string)$row['status']); ?></span></td>
                                        <td class="communication-col-content"><?php echo htmlspecialchars((string)$row['body']); ?></td>
                                        <td class="text-end communication-col-actions">
                                            <div class="communication-row-actions">
                                                <a href="announcements.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_announcement">
                                                    <input type="hidden" name="announcement_id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
