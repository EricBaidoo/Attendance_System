<?php
require_once '../../../includes/security.php';
requireLogin('../../../login');
require_once '../../../config/database.php';
require_once '../../../includes/people_sync.php';

$page_title = 'Identity Integrity Report - Bridge Ministries International';
$page_header = false;

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit < 10) {
    $limit = 10;
}
if ($limit > 200) {
    $limit = 200;
}

$summary = [
    'people_total' => 0,
    'members_without_person' => 0,
    'visitors_without_person' => 0,
    'converts_without_person' => 0,
    'members_stage_mismatch' => 0,
    'visitors_stage_mismatch' => 0,
    'converts_stage_mismatch' => 0,
    'duplicate_email_groups' => 0,
    'duplicate_phone_groups' => 0,
];

$duplicate_emails = [];
$duplicate_phones = [];
$members_mismatch_rows = [];
$visitors_mismatch_rows = [];
$converts_mismatch_rows = [];
$error_message = '';

$fix_summary = null;
if (isset($_GET['fix']) && $_GET['fix'] === '1') {
    $fix_summary = [
        'linked_members' => (int)($_GET['linked_members'] ?? 0),
        'linked_visitors' => (int)($_GET['linked_visitors'] ?? 0),
        'linked_converts' => (int)($_GET['linked_converts'] ?? 0),
        'stage_checked_members' => (int)($_GET['stage_checked_members'] ?? 0),
        'stage_checked_visitors' => (int)($_GET['stage_checked_visitors'] ?? 0),
        'stage_checked_converts' => (int)($_GET['stage_checked_converts'] ?? 0),
    ];
}

try {
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $has_visitors_became_member = $hasColumn('visitor_roles', 'became_member');
    $has_visitors_status = $hasColumn('visitor_roles', 'status');
    $has_converts_status = $hasColumn('new_convert_roles', 'status');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_auto_fix'])) {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrf_token)) {
            throw new RuntimeException('Invalid request token. Please retry.');
        }

        $fix_counts = [
            'linked_members' => 0,
            'linked_visitors' => 0,
            'linked_converts' => 0,
            'stage_checked_members' => 0,
            'stage_checked_visitors' => 0,
            'stage_checked_converts' => 0,
        ];

        $visitorExpectedStage = static function (array $row, bool $has_became_member): string {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            $became = strtolower(trim((string)($row['became_member'] ?? 'no')));

            if ($status === 'converted_to_member' || $status === 'converted' || ($has_became_member && $became === 'yes')) {
                return 'member';
            }
            if ($status === 'converted_to_convert' || $status === 'converted_to_new_convert') {
                return 'new_convert';
            }
            return 'visitor';
        };

        $pdo->beginTransaction();

        $members_stage_rows = $pdo->query("SELECT person_id FROM member_roles WHERE person_id IS NOT NULL AND person_id <> 0")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($members_stage_rows as $row) {
            peopleEnsureStage($pdo, (int)$row['person_id'], 'member');
            $fix_counts['stage_checked_members']++;
        }

        $visitors_stage_rows = $pdo->query("SELECT person_id" . ($has_visitors_status ? ", status" : "") . ($has_visitors_became_member ? ", became_member" : "") . " FROM visitor_roles WHERE person_id IS NOT NULL AND person_id <> 0")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($visitors_stage_rows as $row) {
            peopleEnsureStage($pdo, (int)$row['person_id'], $visitorExpectedStage($row, $has_visitors_became_member));
            $fix_counts['stage_checked_visitors']++;
        }

        $converts_stage_rows = $pdo->query("SELECT person_id" . ($has_converts_status ? ", status" : "") . " FROM new_convert_roles WHERE person_id IS NOT NULL AND person_id <> 0")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($converts_stage_rows as $row) {
            $expected_stage = (strtolower(trim((string)($row['status'] ?? ''))) === 'converted_to_member') ? 'member' : 'new_convert';
            peopleEnsureStage($pdo, (int)$row['person_id'], $expected_stage);
            $fix_counts['stage_checked_converts']++;
        }

        $pdo->commit();

        $redirect_query = http_build_query(array_merge(['limit' => $limit, 'fix' => 1], $fix_counts));
        header('Location: identity_integrity?' . $redirect_query);
        exit;
    }

    $summary['people_total'] = (int)$pdo->query("SELECT COUNT(*) FROM people")->fetchColumn();
    $summary['members_without_person'] = (int)$pdo->query("SELECT COUNT(*) FROM member_roles mr LEFT JOIN people p ON p.id = mr.person_id WHERE mr.person_id IS NULL OR mr.person_id = 0 OR p.id IS NULL")->fetchColumn();
    $summary['visitors_without_person'] = (int)$pdo->query("SELECT COUNT(*) FROM visitor_roles vr LEFT JOIN people p ON p.id = vr.person_id WHERE vr.person_id IS NULL OR vr.person_id = 0 OR p.id IS NULL")->fetchColumn();
    $summary['converts_without_person'] = (int)$pdo->query("SELECT COUNT(*) FROM new_convert_roles nc LEFT JOIN people p ON p.id = nc.person_id WHERE nc.person_id IS NULL OR nc.person_id = 0 OR p.id IS NULL")->fetchColumn();

    $dupEmailSql = "SELECT
            email_key,
            COUNT(*) AS duplicate_count,
            GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS person_ids,
            GROUP_CONCAT(full_name ORDER BY id SEPARATOR ' | ') AS names
        FROM people
        WHERE email_key IS NOT NULL AND TRIM(email_key) <> ''
        GROUP BY email_key
        HAVING COUNT(*) > 1
        ORDER BY duplicate_count DESC, email_key ASC
        LIMIT {$limit}";
    $duplicate_emails = $pdo->query($dupEmailSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $dupPhoneSql = "SELECT
            phone_key,
            COUNT(*) AS duplicate_count,
            GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS person_ids,
            GROUP_CONCAT(full_name ORDER BY id SEPARATOR ' | ') AS names
        FROM people
        WHERE phone_key IS NOT NULL AND TRIM(phone_key) <> ''
        GROUP BY phone_key
        HAVING COUNT(*) > 1
        ORDER BY duplicate_count DESC, phone_key ASC
        LIMIT {$limit}";
    $duplicate_phones = $pdo->query($dupPhoneSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summary['duplicate_email_groups'] = count($duplicate_emails);
    $summary['duplicate_phone_groups'] = count($duplicate_phones);

    $membersMismatchSql = "SELECT
            mr.id,
            p.full_name AS name,
            p.email,
            p.phone,
            mr.person_id,
            p.current_stage
        FROM member_roles mr
        LEFT JOIN people p ON p.id = mr.person_id
        WHERE mr.status = 'active'
            AND mr.person_id IS NOT NULL
            AND mr.person_id <> 0
            AND (p.id IS NULL OR p.current_stage <> 'member')
        ORDER BY mr.id DESC
        LIMIT {$limit}";
    $members_mismatch_rows = $pdo->query($membersMismatchSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $visitorsExpectedStage = "CASE
        WHEN " . ($has_visitors_status ? "COALESCE(vr.status, '') IN ('converted_to_member', 'converted')" : "0") . ($has_visitors_became_member ? " OR COALESCE(vr.became_member, 'no') = 'yes'" : '') . " THEN 'member'
        WHEN " . ($has_visitors_status ? "COALESCE(vr.status, '') IN ('converted_to_convert', 'converted_to_new_convert')" : "0") . " THEN 'new_convert'
        ELSE 'visitor'
    END";

    $visitorsMismatchSql = "SELECT
            vr.id,
            p.full_name AS name,
            p.email,
            p.phone,
            " . ($has_visitors_status ? "vr.status" : "''") . " AS status,
            vr.person_id,
            {$visitorsExpectedStage} AS expected_stage,
            p.current_stage
        FROM visitor_roles vr
        LEFT JOIN people p ON p.id = vr.person_id
        WHERE vr.person_id IS NOT NULL
            AND vr.person_id <> 0
            AND (p.id IS NULL OR p.current_stage <> {$visitorsExpectedStage})
        ORDER BY vr.id DESC
        LIMIT {$limit}";
    $visitors_mismatch_rows = $pdo->query($visitorsMismatchSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $convertsMismatchSql = "SELECT
            nc.id,
            p.full_name AS name,
            p.email,
            p.phone,
            " . ($has_converts_status ? "nc.status" : "''") . " AS status,
            nc.person_id,
            CASE
                WHEN " . ($has_converts_status ? "COALESCE(nc.status, '') = 'converted_to_member'" : "0") . " THEN 'member'
                ELSE 'new_convert'
            END AS expected_stage,
            p.current_stage
        FROM new_convert_roles nc
        LEFT JOIN people p ON p.id = nc.person_id
        WHERE nc.person_id IS NOT NULL
            AND nc.person_id <> 0
            AND (
                p.id IS NULL
                OR p.current_stage <> CASE
                    WHEN " . ($has_converts_status ? "COALESCE(nc.status, '') = 'converted_to_member'" : "0") . " THEN 'member'
                    ELSE 'new_convert'
                END
            )
        ORDER BY nc.id DESC
        LIMIT {$limit}";
    $converts_mismatch_rows = $pdo->query($convertsMismatchSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summary['members_stage_mismatch'] = count($members_mismatch_rows);
    $summary['visitors_stage_mismatch'] = count($visitors_mismatch_rows);
    $summary['converts_stage_mismatch'] = count($converts_mismatch_rows);
} catch (Exception $e) {
    $error_message = 'Unable to generate identity integrity report.';
}

$identity_health_score = 100;
$identity_health_score -= min(40, ($summary['duplicate_email_groups'] + $summary['duplicate_phone_groups']) * 2);
$identity_health_score -= min(30, $summary['members_without_person'] + $summary['visitors_without_person'] + $summary['converts_without_person']);
$identity_health_score -= min(30, $summary['members_stage_mismatch'] + $summary['visitors_stage_mismatch'] + $summary['converts_stage_mismatch']);
if ($identity_health_score < 0) {
    $identity_health_score = 0;
}

$health_label = 'Healthy';
if ($identity_health_score < 85) {
    $health_label = 'Monitor';
}
if ($identity_health_score < 65) {
    $health_label = 'At Risk';
}

include '../../../includes/header.php';
?>
<link href="../../../assets/css/dashboard.css?v=<?php echo @filemtime('../../../assets/css/dashboard.css'); ?>" rel="stylesheet">
<link href="../../../assets/css/reports.css?v=<?php echo @filemtime('../../../assets/css/reports.css'); ?>" rel="stylesheet">

<div class="container-fluid py-4">
    <?php if ($error_message): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($fix_summary): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3">
            <i class="bi bi-check-circle me-2"></i>
            Auto-fix completed.
            Linked: Members <?php echo number_format($fix_summary['linked_members']); ?>,
            Visitors <?php echo number_format($fix_summary['linked_visitors']); ?>,
            New Converts <?php echo number_format($fix_summary['linked_converts']); ?>.
            Stage checks: Members <?php echo number_format($fix_summary['stage_checked_members']); ?>,
            Visitors <?php echo number_format($fix_summary['stage_checked_visitors']); ?>,
            New Converts <?php echo number_format($fix_summary['stage_checked_converts']); ?>.
        </div>
    <?php endif; ?>

    <div class="card report-card mb-3">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 text-primary mb-1 fw-bold"><i class="bi bi-shield-check"></i> Identity Integrity Report</h1>
                    <p class="text-muted mb-0">Data quality checks for unified identity across People, Members, Visitors, and New Converts.</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary-subtle text-primary-emphasis p-2">Health Score: <?php echo (int)$identity_health_score; ?>/100</span>
                    <span class="badge bg-<?php echo $identity_health_score >= 85 ? 'success' : ($identity_health_score >= 65 ? 'warning' : 'danger'); ?> p-2"><?php echo htmlspecialchars($health_label); ?></span>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        <button type="submit" name="run_auto_fix" class="btn btn-warning btn-sm" onclick="return confirm('Run safe auto-fix for missing links and stage consistency?');">
                            <i class="bi bi-wrench-adjustable"></i> Auto-Fix Safe Issues
                        </button>
                    </form>
                    <a href="report" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Reports</a>
                </div>
            </div>
            <form method="GET" class="row g-2 align-items-end mt-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Rows per section</label>
                    <input type="number" min="10" max="200" step="10" name="limit" class="form-control form-control-sm" value="<?php echo (int)$limit; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card bg-primary h-100"><div class="card-body p-3"><div class="stat-number"><?php echo number_format($summary['people_total']); ?></div><div class="stat-label">People Records</div></div></div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card bg-warning h-100"><div class="card-body p-3"><div class="stat-number"><?php echo number_format($summary['members_without_person']); ?></div><div class="stat-label">Members Missing Link</div></div></div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card bg-warning h-100"><div class="card-body p-3"><div class="stat-number"><?php echo number_format($summary['visitors_without_person']); ?></div><div class="stat-label">Visitors Missing Link</div></div></div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card bg-warning h-100"><div class="card-body p-3"><div class="stat-number"><?php echo number_format($summary['converts_without_person']); ?></div><div class="stat-label">Converts Missing Link</div></div></div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card bg-danger h-100"><div class="card-body p-3"><div class="stat-number"><?php echo number_format($summary['duplicate_email_groups'] + $summary['duplicate_phone_groups']); ?></div><div class="stat-label">Duplicate Groups</div></div></div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card bg-info h-100"><div class="card-body p-3"><div class="stat-number"><?php echo number_format($summary['members_stage_mismatch'] + $summary['visitors_stage_mismatch'] + $summary['converts_stage_mismatch']); ?></div><div class="stat-label">Stage Mismatches</div></div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent"><strong><i class="bi bi-envelope-exclamation"></i> Duplicate Emails in People</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Email</th><th>Count</th><th>Person IDs</th></tr></thead>
                            <tbody>
                                <?php if (empty($duplicate_emails)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No duplicate email groups found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($duplicate_emails as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)$row['email_key']); ?></td>
                                            <td><?php echo (int)$row['duplicate_count']; ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars((string)$row['person_ids']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent"><strong><i class="bi bi-telephone-x"></i> Duplicate Phones in People</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Phone (normalized)</th><th>Count</th><th>Person IDs</th></tr></thead>
                            <tbody>
                                <?php if (empty($duplicate_phones)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No duplicate phone groups found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($duplicate_phones as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)$row['phone_key']); ?></td>
                                            <td><?php echo (int)$row['duplicate_count']; ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars((string)$row['person_ids']); ?></td>
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

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-4">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent"><strong><i class="bi bi-person-x"></i> Member Stage Mismatches</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>ID</th><th>Name</th><th>Person</th><th>Stage</th></tr></thead>
                            <tbody>
                                <?php if (empty($members_mismatch_rows)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No mismatches.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($members_mismatch_rows as $row): ?>
                                        <tr>
                                            <td><?php echo (int)$row['id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['name']); ?></td>
                                            <td><?php echo (int)$row['person_id']; ?></td>
                                            <td><span class="badge bg-danger-subtle text-danger-emphasis"><?php echo htmlspecialchars((string)($row['current_stage'] ?? 'missing')); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent"><strong><i class="bi bi-person-badge"></i> Visitor Stage Mismatches</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>ID</th><th>Name</th><th>Expected</th><th>Actual</th></tr></thead>
                            <tbody>
                                <?php if (empty($visitors_mismatch_rows)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No mismatches.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($visitors_mismatch_rows as $row): ?>
                                        <tr>
                                            <td><?php echo (int)$row['id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['name']); ?></td>
                                            <td><span class="badge bg-warning-subtle text-warning-emphasis"><?php echo htmlspecialchars((string)$row['expected_stage']); ?></span></td>
                                            <td><span class="badge bg-danger-subtle text-danger-emphasis"><?php echo htmlspecialchars((string)($row['current_stage'] ?? 'missing')); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card report-card h-100">
                <div class="card-header bg-transparent"><strong><i class="bi bi-person-check"></i> New Convert Stage Mismatches</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>ID</th><th>Name</th><th>Expected</th><th>Actual</th></tr></thead>
                            <tbody>
                                <?php if (empty($converts_mismatch_rows)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No mismatches.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($converts_mismatch_rows as $row): ?>
                                        <tr>
                                            <td><?php echo (int)$row['id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['name']); ?></td>
                                            <td><span class="badge bg-warning-subtle text-warning-emphasis"><?php echo htmlspecialchars((string)$row['expected_stage']); ?></span></td>
                                            <td><span class="badge bg-danger-subtle text-danger-emphasis"><?php echo htmlspecialchars((string)($row['current_stage'] ?? 'missing')); ?></span></td>
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
</div>

<?php include '../../../includes/footer.php'; ?>

