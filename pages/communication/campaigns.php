<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

$page_title = 'Communication Campaigns - ' . getInstitutionName($pdo);
$page_heading = 'Communication Campaigns';
$page_header = false;

$channels = ['sms', 'email', 'whatsapp', 'announcement', 'other'];
$statuses = ['draft', 'scheduled', 'sent', 'cancelled'];

$success = '';
$error = '';
$edit_row = null;

$form_values = [
    'name' => '',
    'channel' => 'sms',
    'audience' => 'All Members',
    'content' => '',
    'status' => 'draft',
    'scheduled_at' => '',
    'sent_at' => '',
    'total_recipients' => '0',
    'delivered_count' => '0',
    'failed_count' => '0',
];

$form_action = 'add_campaign';
$submit_label = 'Save Campaign';

function syncCampaignDeliveryLogs(
    PDO $pdo,
    int $campaign_id,
    string $channel,
    string $status,
    string $audience,
    string $content,
    string $scheduled_at,
    string $sent_at,
    int $total_recipients,
    int $delivered_count,
    int $failed_count
): void {
    if ($campaign_id <= 0) {
        return;
    }

    $total = max(0, $total_recipients);
    $delivered = max(0, $delivered_count);
    $failed = max(0, $failed_count);

    if ($total > 0 && ($delivered + $failed) > $total) {
        $failed = max(0, $total - $delivered);
    }

    $queued = max(0, $total - $delivered - $failed);
    $recipient_label = $audience !== '' ? mb_substr($audience, 0, 190) : 'General Audience';
    $message_preview = mb_substr(trim(preg_replace('/\s+/', ' ', $content)), 0, 255);
    $sent_timestamp = $sent_at !== '' ? date('Y-m-d H:i:s', strtotime($sent_at)) : date('Y-m-d H:i:s');

    $pdo->beginTransaction();

    try {
        $delete_stmt = $pdo->prepare('DELETE FROM communication_logs WHERE campaign_id = ?');
        $delete_stmt->execute([$campaign_id]);

        $insert_stmt = $pdo->prepare(
            'INSERT INTO communication_logs (campaign_id, channel, recipient, status, message_preview, error_detail, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if ($status === 'sent') {
            if ($delivered > 0) {
                $insert_stmt->execute([
                    $campaign_id,
                    $channel,
                    $recipient_label,
                    'delivered',
                    mb_substr($message_preview . ' [Delivered: ' . $delivered . ']', 0, 255),
                    null,
                    $sent_timestamp,
                ]);
            }

            if ($failed > 0) {
                $insert_stmt->execute([
                    $campaign_id,
                    $channel,
                    $recipient_label,
                    'failed',
                    mb_substr($message_preview . ' [Failed: ' . $failed . ']', 0, 255),
                    'Auto-sync: ' . $failed . ' deliveries failed.',
                    $sent_timestamp,
                ]);
            }

            if ($queued > 0) {
                $insert_stmt->execute([
                    $campaign_id,
                    $channel,
                    $recipient_label,
                    'queued',
                    mb_substr($message_preview . ' [Queued: ' . $queued . ']', 0, 255),
                    null,
                    null,
                ]);
            }
        } elseif ($status === 'scheduled' && $total > 0) {
            $insert_stmt->execute([
                $campaign_id,
                $channel,
                $recipient_label,
                'queued',
                mb_substr($message_preview . ' [Scheduled: ' . $total . ']', 0, 255),
                null,
                null,
            ]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['add_campaign', 'update_campaign', 'delete_campaign', 'mark_campaign_sent'], true)) {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        if ($action === 'delete_campaign') {
            $campaign_id = (int)($_POST['campaign_id'] ?? 0);
            if ($campaign_id <= 0) {
                $error = 'Invalid campaign selected.';
            } else {
                try {
                    $status_stmt = $pdo->prepare('SELECT status FROM communication_campaigns WHERE id = ? LIMIT 1');
                    $status_stmt->execute([$campaign_id]);
                    $campaign_status = (string)$status_stmt->fetchColumn();

                    if ($campaign_status === '') {
                        $error = 'Campaign not found or already removed.';
                    } elseif ($campaign_status === 'sent') {
                        $error = 'Sent campaigns cannot be deleted to preserve delivery audit history.';
                    } else {
                    $delete_stmt = $pdo->prepare('DELETE FROM communication_campaigns WHERE id = ?');
                    $delete_stmt->execute([$campaign_id]);
                    if ($delete_stmt->rowCount() > 0) {
                        $success = 'Campaign deleted successfully.';
                    } else {
                        $error = 'Campaign not found or already removed.';
                    }
                    }
                } catch (Exception $e) {
                    $error = 'Unable to delete campaign right now.';
                }
            }
        } elseif ($action === 'mark_campaign_sent') {
            $campaign_id = (int)($_POST['campaign_id'] ?? 0);
            if ($campaign_id <= 0) {
                $error = 'Invalid campaign selected.';
            } else {
                try {
                    $campaign_stmt = $pdo->prepare(
                        'SELECT id, channel, audience, content, status, scheduled_at, sent_at, total_recipients, delivered_count, failed_count FROM communication_campaigns WHERE id = ? LIMIT 1'
                    );
                    $campaign_stmt->execute([$campaign_id]);
                    $campaign_row = $campaign_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                    if (!$campaign_row) {
                        $error = 'Campaign not found.';
                    } else {
                        $current_status = (string)$campaign_row['status'];
                        if ($current_status === 'sent') {
                            $success = 'Campaign is already marked as sent.';
                        } elseif ($current_status === 'cancelled') {
                            $error = 'Cancelled campaigns cannot be marked as sent.';
                        } else {
                            $total_recipients_now = (int)($campaign_row['total_recipients'] ?? 0);
                            $delivered_count_now = (int)($campaign_row['delivered_count'] ?? 0);
                            $failed_count_now = (int)($campaign_row['failed_count'] ?? 0);

                            if ($total_recipients_now > 0 && ($delivered_count_now + $failed_count_now) === 0) {
                                $delivered_count_now = $total_recipients_now;
                            }

                            $sent_at_now = !empty($campaign_row['sent_at'])
                                ? (string)$campaign_row['sent_at']
                                : date('Y-m-d H:i:s');

                            $mark_stmt = $pdo->prepare(
                                'UPDATE communication_campaigns SET status = ?, sent_at = ?, delivered_count = ?, failed_count = ? WHERE id = ?'
                            );
                            $mark_stmt->execute([
                                'sent',
                                $sent_at_now,
                                $delivered_count_now,
                                $failed_count_now,
                                $campaign_id,
                            ]);

                            syncCampaignDeliveryLogs(
                                $pdo,
                                $campaign_id,
                                (string)$campaign_row['channel'],
                                'sent',
                                (string)($campaign_row['audience'] ?? ''),
                                (string)$campaign_row['content'],
                                (string)($campaign_row['scheduled_at'] ?? ''),
                                $sent_at_now,
                                $total_recipients_now,
                                $delivered_count_now,
                                $failed_count_now
                            );

                            $success = 'Campaign marked as sent and logs updated.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Unable to mark campaign as sent right now.';
                }
            }
        }

        $name = trim($_POST['name'] ?? '');
        $channel = trim($_POST['channel'] ?? 'sms');
        $audience = trim($_POST['audience'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $status = trim($_POST['status'] ?? 'draft');
        $scheduled_at = trim($_POST['scheduled_at'] ?? '');
        $sent_at = trim($_POST['sent_at'] ?? '');
        $total_recipients = max(0, (int)($_POST['total_recipients'] ?? 0));
        $delivered_count = max(0, (int)($_POST['delivered_count'] ?? 0));
        $failed_count = max(0, (int)($_POST['failed_count'] ?? 0));

        if ($action !== 'delete_campaign') {
            if ($status === 'sent' && $sent_at === '') {
                $sent_at = date('Y-m-d H:i:s');
                if ($total_recipients > 0 && ($delivered_count + $failed_count) === 0) {
                    $delivered_count = $total_recipients;
                }
            }

            if ($status !== 'sent') {
                $sent_at = '';
            }
        }

        if ($action !== 'delete_campaign') {
            $form_values = [
                'name' => $name,
                'channel' => $channel,
                'audience' => $audience,
                'content' => $content,
                'status' => $status,
                'scheduled_at' => $scheduled_at,
                'sent_at' => $sent_at,
                'total_recipients' => (string)$total_recipients,
                'delivered_count' => (string)$delivered_count,
                'failed_count' => (string)$failed_count,
            ];
        }

        if ($action !== 'delete_campaign' && $name === '') {
            $error = 'Campaign name is required.';
        } elseif ($action !== 'delete_campaign' && !in_array($channel, $channels, true)) {
            $error = 'Please select a valid channel.';
        } elseif ($action !== 'delete_campaign' && $content === '') {
            $error = 'Campaign content is required.';
        } elseif ($action !== 'delete_campaign' && !in_array($status, $statuses, true)) {
            $error = 'Please select a valid status.';
        } elseif ($action !== 'delete_campaign' && $status === 'scheduled' && $scheduled_at === '') {
            $error = 'Scheduled campaigns require a scheduled datetime.';
        } elseif ($action !== 'delete_campaign' && $scheduled_at !== '' && strtotime($scheduled_at) === false) {
            $error = 'Scheduled datetime is invalid.';
        } elseif ($action !== 'delete_campaign' && $sent_at !== '' && strtotime($sent_at) === false) {
            $error = 'Sent datetime is invalid.';
        } elseif ($action !== 'delete_campaign' && $scheduled_at !== '' && $sent_at !== '' && strtotime($sent_at) < strtotime($scheduled_at)) {
            $error = 'Sent datetime cannot be earlier than scheduled datetime.';
        } elseif ($action !== 'delete_campaign' && ($delivered_count + $failed_count > $total_recipients) && $total_recipients > 0) {
            $error = 'Delivered + Failed cannot exceed total recipients.';
        } elseif ($action === 'add_campaign') {
            try {
                $insert_stmt = $pdo->prepare(
                    'INSERT INTO communication_campaigns (name, channel, audience, content, status, scheduled_at, sent_at, total_recipients, delivered_count, failed_count, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert_stmt->execute([
                    mb_substr($name, 0, 180),
                    $channel,
                    $audience !== '' ? mb_substr($audience, 0, 120) : null,
                    $content,
                    $status,
                    $scheduled_at !== '' ? date('Y-m-d H:i:s', strtotime($scheduled_at)) : null,
                    $sent_at !== '' ? date('Y-m-d H:i:s', strtotime($sent_at)) : null,
                    $total_recipients,
                    $delivered_count,
                    $failed_count,
                    (int)($_SESSION['user_id'] ?? 0) ?: null,
                ]);

                $campaign_id = (int)$pdo->lastInsertId();
                syncCampaignDeliveryLogs(
                    $pdo,
                    $campaign_id,
                    $channel,
                    $status,
                    $audience,
                    $content,
                    $scheduled_at,
                    $sent_at,
                    $total_recipients,
                    $delivered_count,
                    $failed_count
                );

                $success = 'Campaign saved successfully.';
                $form_values = [
                    'name' => '',
                    'channel' => 'sms',
                    'audience' => 'All Members',
                    'content' => '',
                    'status' => 'draft',
                    'scheduled_at' => '',
                    'sent_at' => '',
                    'total_recipients' => '0',
                    'delivered_count' => '0',
                    'failed_count' => '0',
                ];
            } catch (Exception $e) {
                $error = 'Unable to save campaign right now.';
            }
        } elseif ($action === 'update_campaign') {
            $campaign_id = (int)($_POST['campaign_id'] ?? 0);
            if ($campaign_id <= 0) {
                $error = 'Invalid campaign selected for update.';
            } else {
                try {
                    $verify_stmt = $pdo->prepare('SELECT status FROM communication_campaigns WHERE id = ? LIMIT 1');
                    $verify_stmt->execute([$campaign_id]);
                    $current_campaign_status = (string)$verify_stmt->fetchColumn();

                    if ($current_campaign_status === 'sent') {
                        throw new Exception('Sent campaigns cannot be updated to preserve audit integrity.');
                    }

                    $update_stmt = $pdo->prepare(
                        'UPDATE communication_campaigns SET name = ?, channel = ?, audience = ?, content = ?, status = ?, scheduled_at = ?, sent_at = ?, total_recipients = ?, delivered_count = ?, failed_count = ?, created_by_user_id = ? WHERE id = ?'
                    );
                    $update_stmt->execute([
                        mb_substr($name, 0, 180),
                        $channel,
                        $audience !== '' ? mb_substr($audience, 0, 120) : null,
                        $content,
                        $status,
                        $scheduled_at !== '' ? date('Y-m-d H:i:s', strtotime($scheduled_at)) : null,
                        $sent_at !== '' ? date('Y-m-d H:i:s', strtotime($sent_at)) : null,
                        $total_recipients,
                        $delivered_count,
                        $failed_count,
                        (int)($_SESSION['user_id'] ?? 0) ?: null,
                        $campaign_id,
                    ]);

                    syncCampaignDeliveryLogs(
                        $pdo,
                        $campaign_id,
                        $channel,
                        $status,
                        $audience,
                        $content,
                        $scheduled_at,
                        $sent_at,
                        $total_recipients,
                        $delivered_count,
                        $failed_count
                    );

                    $success = $update_stmt->rowCount() > 0
                        ? 'Campaign updated successfully.'
                        : 'No changes were made to this campaign.';

                    $form_values = [
                        'name' => '',
                        'channel' => 'sms',
                        'audience' => 'All Members',
                        'content' => '',
                        'status' => 'draft',
                        'scheduled_at' => '',
                        'sent_at' => '',
                        'total_recipients' => '0',
                        'delivered_count' => '0',
                        'failed_count' => '0',
                    ];
                } catch (Exception $e) {
                    $error = 'Unable to update campaign right now.';
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        try {
            $edit_stmt = $pdo->prepare('SELECT id, name, channel, audience, content, status, scheduled_at, sent_at, total_recipients, delivered_count, failed_count FROM communication_campaigns WHERE id = ? LIMIT 1');
            $edit_stmt->execute([$edit_id]);
            $edit_row = $edit_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($edit_row) {
                $form_action = 'update_campaign';
                $submit_label = 'Update Campaign';
                $form_values = [
                    'name' => (string)$edit_row['name'],
                    'channel' => (string)$edit_row['channel'],
                    'audience' => (string)($edit_row['audience'] ?? ''),
                    'content' => (string)$edit_row['content'],
                    'status' => (string)$edit_row['status'],
                    'scheduled_at' => !empty($edit_row['scheduled_at']) ? date('Y-m-d\TH:i', strtotime((string)$edit_row['scheduled_at'])) : '',
                    'sent_at' => !empty($edit_row['sent_at']) ? date('Y-m-d\TH:i', strtotime((string)$edit_row['sent_at'])) : '',
                    'total_recipients' => (string)((int)$edit_row['total_recipients']),
                    'delivered_count' => (string)((int)$edit_row['delivered_count']),
                    'failed_count' => (string)((int)$edit_row['failed_count']),
                ];
            }
        } catch (Exception $e) {
            $error = $error !== '' ? $error : 'Unable to load campaign for editing.';
        }
    }
}

$campaigns = [];
$total_campaigns = 0;
$scheduled_campaigns = 0;
$sent_campaigns = 0;

try {
    $summary_stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total_campaigns,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled_campaigns,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_campaigns
         FROM communication_campaigns"
    );
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total_campaigns = (int)($summary['total_campaigns'] ?? 0);
    $scheduled_campaigns = (int)($summary['scheduled_campaigns'] ?? 0);
    $sent_campaigns = (int)($summary['sent_campaigns'] ?? 0);

    $list_stmt = $pdo->query(
        "SELECT c.id, c.name, c.channel, c.audience, c.content, c.status, c.scheduled_at, c.sent_at, c.total_recipients, c.delivered_count, c.failed_count, c.created_at, u.username
         FROM communication_campaigns c
         LEFT JOIN users u ON u.id = c.created_by_user_id
         ORDER BY COALESCE(c.scheduled_at, c.sent_at, c.created_at) DESC, c.id DESC
         LIMIT 40"
    );
    $campaigns = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <a class="nav-link" href="dashboard">Overview</a>
        <a class="nav-link active" href="campaigns">Campaigns</a>
        <a class="nav-link" href="sms">SMS Center</a>
        <a class="nav-link" href="logs">Logs</a>
    </nav>

    <section class="row g-3 communication-stats mb-1">
        <div class="col-12 col-md-4">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-blue"><i class="bi bi-broadcast-pin"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Total Campaigns</span>
                    <h3 class="communication-stat-value"><?php echo number_format($total_campaigns); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-orange"><i class="bi bi-calendar2-check-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Scheduled</span>
                    <h3 class="communication-stat-value"><?php echo number_format($scheduled_campaigns); ?></h3>
                </div>
            </article>
        </div>
        <div class="col-12 col-md-4">
            <article class="communication-stat-card">
                <div class="communication-stat-icon icon-green"><i class="bi bi-send-check-fill"></i></div>
                <div class="communication-stat-content">
                    <span class="communication-stat-label">Sent</span>
                    <h3 class="communication-stat-value"><?php echo number_format($sent_campaigns); ?></h3>
                </div>
            </article>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-4">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-plus-circle-fill"></i> Add Campaign</h2>
                    <p>Manage SMS, email and messaging campaigns.</p>
                </div>

                <form method="POST" class="communication-form mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($form_action); ?>">
                    <?php if ($edit_row): ?>
                        <input type="hidden" name="campaign_id" value="<?php echo (int)$edit_row['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Campaign Name</label>
                        <input type="text" class="form-control" name="name" maxlength="180" value="<?php echo htmlspecialchars($form_values['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Channel</label>
                        <select class="form-select" name="channel" required>
                            <?php foreach ($channels as $channel): ?>
                                <option value="<?php echo htmlspecialchars($channel); ?>"<?php echo $form_values['channel'] === $channel ? ' selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($channel)); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        <label class="form-label">Scheduled At (Optional)</label>
                        <input type="datetime-local" class="form-control" name="scheduled_at" value="<?php echo htmlspecialchars($form_values['scheduled_at']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sent At (Optional)</label>
                        <input type="datetime-local" class="form-control" name="sent_at" value="<?php echo htmlspecialchars($form_values['sent_at']); ?>">
                    </div>

                    <div class="row g-2">
                        <div class="col-4 mb-3">
                            <label class="form-label">Total</label>
                            <input type="number" min="0" class="form-control" name="total_recipients" value="<?php echo htmlspecialchars($form_values['total_recipients']); ?>">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Delivered</label>
                            <input type="number" min="0" class="form-control" name="delivered_count" value="<?php echo htmlspecialchars($form_values['delivered_count']); ?>">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Failed</label>
                            <input type="number" min="0" class="form-control" name="failed_count" value="<?php echo htmlspecialchars($form_values['failed_count']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea class="form-control" name="content" rows="5" required><?php echo htmlspecialchars($form_values['content']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save2-fill me-1"></i> <?php echo htmlspecialchars($submit_label); ?>
                    </button>
                    <?php if ($edit_row): ?>
                        <a href="campaigns" class="btn btn-outline-secondary w-100 mt-2">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="communication-panel h-100">
                <div class="communication-panel-head">
                    <h2><i class="bi bi-list-ul"></i> Campaign List</h2>
                    <p>Latest campaign records and delivery metrics.</p>
                </div>

                <div class="communication-table-wrap mt-3">
                    <table class="table communication-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="communication-col-date">Date</th>
                                <th class="communication-col-title">Campaign</th>
                                <th>Channel</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Delivered</th>
                                <th>Failed</th>
                                <th class="text-end communication-col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($campaigns)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No campaigns yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($campaigns as $row): ?>
                                    <tr>
                                        <td class="communication-col-date"><?php echo htmlspecialchars(date('d M Y', strtotime((string)($row['scheduled_at'] ?: $row['sent_at'] ?: $row['created_at'])))); ?></td>
                                        <td class="communication-col-title"><?php echo htmlspecialchars((string)$row['name']); ?></td>
                                        <td><?php echo htmlspecialchars(strtoupper((string)$row['channel'])); ?></td>
                                        <td><span class="communication-badge status-<?php echo htmlspecialchars(strtolower((string)$row['status'])); ?>"><?php echo htmlspecialchars((string)$row['status']); ?></span></td>
                                        <td><?php echo number_format((int)$row['total_recipients']); ?></td>
                                        <td><?php echo number_format((int)$row['delivered_count']); ?></td>
                                        <td><?php echo number_format((int)$row['failed_count']); ?></td>
                                        <td class="text-end communication-col-actions">
                                            <div class="communication-row-actions">
                                                <?php if ((string)$row['status'] !== 'sent' && (string)$row['status'] !== 'cancelled'): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Mark this campaign as sent?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="mark_campaign_sent">
                                                        <input type="hidden" name="campaign_id" value="<?php echo (int)$row['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">Mark Sent</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ((string)$row['status'] !== 'sent'): ?>
                                                    <a href="campaigns?edit=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this campaign?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_campaign">
                                                    <input type="hidden" name="campaign_id" value="<?php echo (int)$row['id']; ?>">
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

