<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

// --- AJAX Endpoint ---
if (isset($_GET['action']) && $_GET['action'] === 'get_next_number') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'individual';
    function fetchNextVal($pdo, $type) {
        $setting_key = ($type === 'company') ? 'tithe_format_company' : 'tithe_format_member';
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?"); $stmt->execute([$setting_key]);
        $template = $stmt->fetchColumn() ?: (($type === 'company') ? 'CORP{SEQ}-{YY}' : 'BMI{SEQ}-{YY}');
        $prefix = explode('{SEQ}', $template)[0];
        $stmt = $pdo->prepare("SELECT book_number FROM tithe_books WHERE book_number LIKE ?"); $stmt->execute([$prefix . '%']);
        $book_numbers = $stmt->fetchAll(PDO::FETCH_COLUMN); $max_seq = 0;
        foreach ($book_numbers as $bn) { if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', (string)$bn, $m)) { $max_seq = max($max_seq, (int)$m[1]); } }
        return str_replace(['{SEQ}', '{YY}', '{YYYY}'], [str_pad((string)($max_seq + 1), 4, '0', STR_PAD_LEFT), date('y'), date('Y')], $template);
    }
    echo json_encode(['next_number' => fetchNextVal($pdo, $type)]);
    exit;
}

$page_title = 'Tithers & Tithe Books - ' . getInstitutionName($pdo); $page_heading = 'Tithers & Tithe Books'; $page_header = false;
$success = $_SESSION['finance_tithers_flash_success'] ?? ''; unset($_SESSION['finance_tithers_flash_success']); $error = '';

function generateNextTitheBookNum(PDO $pdo, string $type = 'individual'): string {
    $setting_key = ($type === 'company') ? 'tithe_format_company' : 'tithe_format_member';
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?"); $stmt->execute([$setting_key]);
    $template = $stmt->fetchColumn() ?: (($type === 'company') ? 'CORP{SEQ}-{YY}' : 'BMI{SEQ}-{YY}');
    $prefix_match = explode('{SEQ}', $template)[0];
    $stmt = $pdo->prepare("SELECT book_number FROM tithe_books WHERE book_number LIKE ?"); $stmt->execute([$prefix_match . '%']);
    $book_numbers = $stmt->fetchAll(PDO::FETCH_COLUMN); $max_sequence = 0;
    foreach ($book_numbers as $bn) { if (preg_match('/' . preg_quote($prefix_match, '/') . '(\d+)/', (string)$bn, $m)) { $max_sequence = max($max_sequence, (int)$m[1]); } }
    return str_replace(['{SEQ}', '{YY}', '{YYYY}'], [str_pad((string)($max_sequence + 1), 4, '0', STR_PAD_LEFT), date('y'), date('Y')], $template);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) { $error = 'Security mismatch.'; } else {
        $action = $_POST['action'] ?? ''; $tid = (int)($_POST['tither_id'] ?? 0);
        try {
            if ($action === 'add_tither') {
                $pdo->beginTransaction(); $type = $_POST['tither_type'] ?? 'individual'; $book_id = (int)$_POST['tithe_book_id'];
                if ($book_id <= 0) {
                    $bn = generateNextTitheBookNum($pdo, $type);
                    $pdo->prepare("INSERT INTO tithe_books (book_number, issued_date, status) VALUES (?, CURDATE(), 'assigned')")->execute([$bn]);
                    $book_id = (int)$pdo->lastInsertId();
                }
                $pdo->prepare("INSERT INTO tithers (member_id, tither_type, age_group, full_name, phone, email, company_tin, tithe_book_id, status, start_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)")
                    ->execute([$_POST['member_id'] ?: null, $type, ($type==='individual' ? $_POST['age_group'] : null), $_POST['full_name'], $_POST['phone'] ?: null, $_POST['email'] ?: null, $_POST['company_tin'] ?: null, $book_id, $_POST['start_date'] ?: date('Y-m-d')]);
                $pdo->commit(); $_SESSION['finance_tithers_flash_success'] = "Registered successfully.";
            } elseif ($action === 'update_tither') {
                $pdo->prepare("UPDATE tithers SET member_id = ?, tither_type = ?, age_group = ?, full_name = ?, phone = ?, email = ?, company_tin = ?, status = ?, start_date = ? WHERE id = ?")
                    ->execute([$_POST['member_id'] ?: null, $_POST['tither_type'], ($_POST['tither_type']==='individual' ? $_POST['age_group'] : null), $_POST['full_name'], $_POST['phone'] ?: null, $_POST['email'] ?: null, $_POST['company_tin'] ?: null, $_POST['status'], $_POST['start_date'], $tid]);
                $_SESSION['finance_tithers_flash_success'] = "Updated.";
            } elseif ($action === 'toggle_status') {
                $s = $_POST['new_status']; $pdo->prepare("UPDATE tithers SET status = ? WHERE id = ?")->execute([$s, $tid]);
                $_SESSION['finance_tithers_flash_success'] = "Status changed.";
            } elseif ($action === 'retire_tither') {
                $pdo->beginTransaction(); $pdo->prepare("UPDATE tithers SET status = 'retired' WHERE id = ?")->execute([$tid]);
                $bid = (int)$pdo->query("SELECT tithe_book_id FROM tithers WHERE id = $tid")->fetchColumn();
                if ($bid > 0) { $pdo->prepare("UPDATE tithe_books SET status = 'retired' WHERE id = ?")->execute([$bid]); }
                $pdo->commit(); $_SESSION['finance_tithers_flash_success'] = "Retired.";
            } elseif ($action === 'delete_tither') {
                $pdo->beginTransaction(); $bid = (int)$pdo->query("SELECT tithe_book_id FROM tithers WHERE id = $tid")->fetchColumn();
                $pdo->prepare("UPDATE finance_transactions SET tither_id = NULL WHERE tither_id = ?")->execute([$tid]);
                $pdo->prepare("DELETE FROM tithers WHERE id = ?")->execute([$tid]);
                if ($bid > 0) { $pdo->prepare("DELETE FROM tithe_books WHERE id = ? AND status <> 'retired'")->execute([$bid]); }
                $pdo->commit(); $_SESSION['finance_tithers_flash_success'] = "Purged.";
            }
            header('Location: tithers'); exit;
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error = $e->getMessage(); }
    }
}

// Fetch
$books = $pdo->query("SELECT * FROM tithe_books ORDER BY book_number DESC")->fetchAll(PDO::FETCH_ASSOC);
$t_all = $pdo->query("SELECT t.*, b.book_number FROM tithers t LEFT JOIN tithe_books b ON b.id = t.tithe_book_id ORDER BY t.full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$members_list = array_filter($t_all, fn($t) => $t['tither_type'] === 'individual');
$companies_list = array_filter($t_all, fn($t) => $t['tither_type'] === 'company');
$church_members = $pdo->query("SELECT mr.id, p.full_name FROM member_roles mr JOIN people p ON p.id = mr.person_id WHERE mr.status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

$edit_tither = null;
if (isset($_GET['edit_tither'])) { $eid = (int)$_GET['edit_tither']; foreach ($t_all as $t) if ($t['id'] == $eid) $edit_tither = $t; }

include '../../includes/header.php';
?>
<link href="../../assets/css/finance.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid finance-page py-4">
    <header class="finance-hero mb-4">
        <div class="finance-hero-main">
            <p class="finance-eyebrow">ADMINISTRATION</p>
            <h1>Tithers Oversight</h1>
            <div class="finance-quick-actions">
                <a href="dashboard" class="finance-quick-btn"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="income" class="finance-quick-btn"><i class="bi bi-wallet2"></i> Post Income</a>
            </div>
        </div>
        <div class="finance-date-wrap d-none d-md-block text-end">
            <span class="finance-date-label">Statistics</span>
            <div class="finance-date"><?php echo count($members_list); ?> Members / <?php echo count($companies_list); ?> Orgs</div>
        </div>
    </header>

    <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm mb-4"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="finance-panel glass-panel sticky-top" style="top: 2rem;">
                <div class="finance-panel-head border-bottom pb-3"><h2 class="h5 mb-1"><i class="bi bi-person-fill-add text-primary"></i> <?php echo $edit_tither ? 'Modify Record' : 'New Contributor'; ?></h2></div>
                <form method="POST" class="finance-form mt-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="<?php echo $edit_tither ? 'update_tither' : 'add_tither'; ?>">
                    <?php if ($edit_tither): ?><input type="hidden" name="tither_id" value="<?php echo (int)$edit_tither['id']; ?>"><?php endif; ?>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label small fw-bold">TYPE</label><select class="form-select" name="tither_type" id="t_type" onchange="toggleTType(this.value)"><option value="individual" <?php echo ($edit_tither['tither_type'] ?? '')==='individual'?'selected':'';?>>Individual</option><option value="company" <?php echo ($edit_tither['tither_type'] ?? '')==='company'?'selected':'';?>>Company</option></select></div>
                        <div class="col-6" id="age_group_wrap"><label class="form-label small fw-bold">AGE GROUP</label><select class="form-select" name="age_group"><option value="adult" <?php echo ($edit_tither['age_group'] ?? '')==='adult'?'selected':'';?>>Adult</option><option value="youth" <?php echo ($edit_tither['age_group'] ?? '')==='youth'?'selected':'';?>>Youth</option><option value="child" <?php echo ($edit_tither['age_group'] ?? '')==='child'?'selected':'';?>>Child</option></select></div>
                    </div>
                    <div id="m_link_wrap" class="mb-3"><label class="form-label small fw-bold">LINK MEMBER</label><select class="form-select" name="member_id" id="m_select"><option value="">-- Manual/External --</option><?php foreach ($church_members as $m): ?><option value="<?php echo (int)$m['id']; ?>" data-name="<?php echo htmlspecialchars($m['full_name']); ?>" <?php echo ($edit_tither['member_id'] ?? '') == (int)$m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label small fw-bold" id="n_label">FULL NAME / ORG</label><input type="text" class="form-control" name="full_name" id="f_name" value="<?php echo htmlspecialchars($edit_tither['full_name'] ?? ''); ?>" required></div>
                    <div id="c_fields" class="mb-3" style="display: none;"><label class="form-label small fw-bold text-danger">BUSINESS TIN</label><input type="text" class="form-control" name="company_tin" value="<?php echo htmlspecialchars($edit_tither['company_tin'] ?? ''); ?>"></div>
                    <div class="mb-3 border-top pt-3"><div class="d-flex justify-content-between mb-1"><label class="form-label small fw-bold">TITHE BOOK</label><span id="next_no" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Next: ...</span></div><?php if ($edit_tither): ?><div class="form-control bg-light"><?php echo htmlspecialchars($edit_tither['book_number'] ?: 'Unassigned'); ?></div>
                        <?php else: ?><select class="form-select" name="tithe_book_id" id="t_book_id"><option value="0" id="a_label">Auto-Generate</option><?php foreach ($books as $b): if ($b['status'] === 'available'): ?><option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['book_number']); ?></option><?php endif; endforeach; ?></select><?php endif; ?>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label small fw-bold">STATUS</label><select class="form-select" name="status"><option value="active" <?php echo ($edit_tither['status'] ?? '')==='active'?'selected':'';?>>Active</option><option value="inactive" <?php echo ($edit_tither['status'] ?? '')==='inactive'?'selected':'';?>>Inactive</option><option value="retired" <?php echo ($edit_tither['status'] ?? '')==='retired'?'selected':'';?>>Retired</option></select></div>
                        <div class="col-6"><label class="form-label small fw-bold">START DATE</label><input type="date" class="form-control" name="start_date" value="<?php echo $edit_tither['start_date'] ?? date('Y-m-d'); ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Save Profile</button>
                    <?php if ($edit_tither): ?><a href="tithers" class="btn btn-outline-secondary w-100 mt-2">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="finance-panel border-0 shadow-sm active-card h-100 d-flex flex-column" style="min-height: 500px;">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <nav class="nav nav-pills bg-light p-1 rounded-pill flex-shrink-0">
                        <button class="nav-link active rounded-pill px-4 border-0" data-bs-toggle="tab" data-bs-target="#m_pane">👥 MEMBERS</button>
                        <button class="nav-link rounded-pill px-4 border-0" data-bs-toggle="tab" data-bs-target="#c_pane">🏢 COMPANIES</button>
                    </nav>
                    <div class="search-wrap position-relative flex-grow-1" style="max-width: 350px;">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="z-index: 5;"></i>
                        <input type="text" id="titherSearch" class="form-control rounded-pill border-0 bg-light-subtle shadow-sm" placeholder="Search contributor or book..." style="padding-left: 45px !important;">
                    </div>
                </div>

                <div class="tab-content flex-grow-1 overflow-hidden">
                    <div class="tab-pane fade show active h-100" id="m_pane">
                        <div class="finance-table-scrollable">
                            <table class="table finance-table align-middle m-0" id="membersTable">
                                <thead class="sticky-top bg-white"><tr><th>Name</th><th>Book #</th><th class="text-center">Lifecycle</th></tr></thead>
                                <tbody>
                                    <?php foreach ($members_list as $t): ?>
                                        <tr class="tither-row <?php echo $t['status']==='retired'?'opacity-50':'';?>" data-search="<?php echo strtolower($t['full_name'] . ' ' . $t['book_number'] . ' ' . ($t['phone']??'')); ?>">
                                            <td style="min-width: 140px;"><div class="fw-bold"><?php echo htmlspecialchars($t['full_name']); ?></div><div class="small text-muted"><?php echo ucfirst($t['age_group']); ?></div></td>
                                            <td><span class="badge bg-light text-dark border px-3"><?php echo htmlspecialchars($t['book_number'] ?: '-'); ?></span></td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-2">
                                                    <a href="tithers?edit_tither=<?php echo $t['id'];?>" class="btn btn-icon-action text-primary"><i class="bi bi-pencil-fill"></i></a>
                                                    <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="tither_id" value="<?php echo $t['id'];?>"><input type="hidden" name="new_status" value="<?php echo $t['status']==='active'?'inactive':'active';?>"><button class="btn btn-icon-action <?php echo $t['status']==='active'?'text-success':'text-warning';?>" type="submit"><i class="bi bi-power"></i></button></form>
                                                    <?php if ($t['status']!=='retired'): ?><form method="POST" class="d-inline" onsubmit="return confirm('Retire?');"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="retire_tither"><input type="hidden" name="tither_id" value="<?php echo $t['id'];?>"><button class="btn btn-icon-action text-info" type="submit"><i class="bi bi-archive-fill"></i></button></form><?php endif; ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Purge?');"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="delete_tither"><input type="hidden" name="tither_id" value="<?php echo $t['id'];?>"><button class="btn btn-icon-action text-danger" type="submit"><i class="bi bi-trash3-fill"></i></button></form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade h-100" id="c_pane">
                        <div class="finance-table-scrollable">
                            <table class="table finance-table align-middle m-0" id="companiesTable">
                                <thead class="sticky-top bg-white"><tr><th>Org Name</th><th>TIN</th><th class="text-center">Lifecycle</th></tr></thead>
                                <tbody>
                                    <?php foreach ($companies_list as $t): ?>
                                        <tr class="tither-row <?php echo $t['status']==='retired'?'opacity-50':'';?>" data-search="<?php echo strtolower($t['full_name'] . ' ' . $t['book_number'] . ' ' . ($t['company_tin']??'')); ?>">
                                            <td style="min-width: 140px;"><div class="fw-bold"><i class="bi bi-building me-1"></i> <?php echo htmlspecialchars($t['full_name']); ?></div><div class="small text-muted text-nowrap"><?php echo htmlspecialchars($t['book_number'] ?: '-'); ?></div></td>
                                            <td><code class="small text-danger"><?php echo htmlspecialchars($t['company_tin'] ?: '-'); ?></code></td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-2">
                                                    <a href="tithers?edit_tither=<?php echo $t['id'];?>" class="btn btn-icon-action text-primary"><i class="bi bi-pencil-fill"></i></a>
                                                    <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="tither_id" value="<?php echo $t['id'];?>"><input type="hidden" name="new_status" value="<?php echo $t['status']==='active'?'inactive':'active';?>"><button class="btn btn-icon-action <?php echo $t['status']==='active'?'text-success':'text-warning';?>" type="submit"><i class="bi bi-power"></i></button></form>
                                                    <?php if ($t['status']!=='retired'): ?><form method="POST" class="d-inline" onsubmit="return confirm('Retire?');"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="retire_tither"><input type="hidden" name="tither_id" value="<?php echo $t['id'];?>"><button class="btn btn-icon-action text-info" type="submit"><i class="bi bi-archive-fill"></i></button></form><?php endif; ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Purge?');"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="delete_tither"><input type="hidden" name="tither_id" value="<?php echo $t['id'];?>"><button class="btn btn-icon-action text-danger" type="submit"><i class="bi bi-trash3-fill"></i></button></form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function refreshNextNumber() {
    const t = document.getElementById('t_type').value; const b = document.getElementById('next_no'); const l = document.getElementById('a_label');
    try {
        const r = await fetch(`tithers.php?action=get_next_number&type=${t}`); const d = await r.json();
        b.innerText = `Next: ${d.next_number}`; if (l) l.innerText = `Auto (Next: ${d.next_number})`;
    } catch(e) { b.innerText = "Next: ..."; }
}
function toggleTType(v) {
    const ag = document.getElementById('age_group_wrap'); const ml = document.getElementById('m_link_wrap'); const nl = document.getElementById('n_label'); const cf = document.getElementById('c_fields');
    if (v==='company') { ag.style.display='none'; ml.style.display='none'; nl.innerText='ORG'; cf.style.display='block'; }
    else { ag.style.display='block'; ml.style.display='block'; nl.innerText='NAME'; cf.style.display='none'; }
    refreshNextNumber();
}
document.addEventListener('DOMContentLoaded', function() {
    const ms = document.getElementById('m_select'); if (ms) { ms.addEventListener('change', function() { const s = this.options[this.selectedIndex]; if (s && s.value !== "") { document.getElementById('f_name').value = s.getAttribute('data-name'); } }); }
    toggleTType(document.getElementById('t_type').value);

    // Filter Logic
    const searchInput = document.getElementById('titherSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            document.querySelectorAll('.tither-row').forEach(row => {
                const searchData = row.getAttribute('data-search');
                row.style.display = searchData.includes(query) ? '' : 'none';
            });
        });
    }
});
</script>

<style>
.glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); }
.active-card { border-left: 4px solid var(--finance-primary) !important; }
.search-wrap .form-control { background: #f1f5f9; border: 1px solid #e2e8f0; height: 44px; transition: 0.3s; }
.search-wrap .form-control:focus { background: #fff; border-color: var(--finance-primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); outline: none; }
.finance-table-scrollable { height: 60vh; max-height: 500px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #cbd5e1 #f8fafc; }
.finance-table-scrollable::-webkit-scrollbar { width: 6px; }
.finance-table-scrollable::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.sticky-top { top: 0; z-index: 10; background: #fff !important; border-bottom: 2px solid #f1f5f9; }
.btn-icon-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; transition: 0.2s; padding: 0; }
.btn-icon-action:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-color: currentColor; }
.opacity-50 { opacity: 0.35; filter: grayscale(1); }
.nav-pills .nav-link { color: #64748b; font-weight: 600; font-size: 0.75rem; border: none; }
.nav-pills .nav-link.active { background: #fff !important; color: var(--finance-primary); box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
</style>

<?php include '../../includes/header.php'; ?>
