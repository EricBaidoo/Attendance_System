<?php
require_once '../../includes/security.php';
requireLogin('../../login');
requireRole('admin', '../../index');

$success = ''; $error = ''; 
$active_group = $_GET['group'] ?? 'branding';

try {
    require '../../config/database.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrf_token)) { $error = 'Security mismatch.'; } else {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'update_settings') {
                    $settings = $_POST['settings'] ?? [];
                    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                        $target_dir = "../../assets/images/";
                        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                        $file_ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                        if (in_array($file_ext, ['png', 'jpg', 'jpeg', 'svg'])) {
                            $logo_path = "assets/images/logo_" . time() . "." . $file_ext;
                            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], "../../" . $logo_path)) {
                                $settings['institution_logo'] = $logo_path;
                            }
                        }
                    }
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
                    foreach ($settings as $key => $value) { $stmt->execute([$value, $_SESSION['user_id'], $key]); }
                    $pdo->commit(); $success = 'Changes Saved Successfully.';
                }
                
                if ($action === 'add_dir_item') {
                    $type = $_POST['dir_type']; $name = trim($_POST['name'] ?? '');
                    if ($name === '') throw new Exception('Name required.');
                    $table = ($type === 'dept') ? 'departments' : 'cell_centers';
                    $pdo->prepare("INSERT INTO $table (name) VALUES (?)")->execute([$name]);
                    $success = 'Entry Added.'; $active_group = 'directory';
                }
                if ($action === 'delete_dir_item') {
                    $type = $_POST['dir_type']; $id = (int)$_POST['item_id'];
                    if ($type === 'dept') {
                        $pdo->beginTransaction();
                        $pdo->prepare('DELETE FROM member_departments WHERE department_id = ?')->execute([$id]);
                        $pdo->prepare('UPDATE member_roles SET department_id = NULL WHERE department_id = ?')->execute([$id]);
                        $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
                        $pdo->commit();
                    } else {
                        $pdo->prepare('UPDATE member_roles SET cell_center_id = NULL WHERE cell_center_id = ?')->execute([$id]);
                        $pdo->prepare('DELETE FROM cell_centers WHERE id = ?')->execute([$id]);
                    }
                    $success = 'Entry Removed.'; $active_group = 'directory';
                }
            } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error = $e->getMessage(); }
        }
    }

    $departments = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM member_roles mr WHERE mr.department_id = d.id) AS m_count FROM departments d ORDER BY d.name')->fetchAll();
    $cell_centers = $pdo->query('SELECT cc.*, (SELECT COUNT(*) FROM member_roles mr WHERE mr.cell_center_id = cc.id) AS m_count FROM cell_centers cc ORDER BY cc.name')->fetchAll();
    
    // Group settings with case-insensitive normalization
    $raw_settings = $pdo->query("SELECT * FROM system_settings ORDER BY category, id")->fetchAll();
    $config_groups = []; 
    foreach ($raw_settings as $s) { 
        $cat = strtolower($s['category']);
        $config_groups[$cat][] = $s; 
    }

} catch (Exception $e) { die('Error: ' . $e->getMessage()); }

$page_title = 'System Control - ' . getInstitutionName($pdo); include '../../includes/header.php';
?>
<link href="../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<style>
:root { --set-bg: #f8fafc; --set-sidebar: #ffffff; --set-accent: var(--finance-primary, #4f46e5); }
.settings-layout { display: grid; grid-template-columns: 280px 1fr; min-height: 80vh; gap: 2rem; position: relative; }
.settings-sidebar { background: #fff; border-radius: 1.5rem; padding: 1.5rem; border: 1px solid #edf2f7; height: fit-content; position: sticky; top: 2rem; }
.settings-nav-btn { display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1.25rem; border-radius: 1rem; color: #64748b; text-decoration: none !important; transition: 0.2s; font-weight: 600; font-size: 0.9rem; border: none; background: none; width: 100%; text-align: left; margin-bottom: 0.5rem; }
.settings-nav-btn i { font-size: 1.25rem; }
.settings-nav-btn:hover { background: #f1f5f9; color: var(--set-accent); }
.settings-nav-btn.active { background: var(--set-accent); color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }
.settings-content-card { background: #fff; border-radius: 1.5rem; border: 1px solid #edf2f7; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
.logo-giant-preview { width: 140px; height: 140px; border-radius: 2rem; background: #f8fafc; display: flex; align-items: center; justify-content: center; border: 2px dashed #e2e8f0; position: relative; overflow: hidden; }
.logo-giant-preview img { max-width: 90%; max-height: 90%; object-fit: contain; }
.hover-upload { position: absolute; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; color: #fff; opacity: 0; transition: 0.3s; cursor: pointer; }
.logo-giant-preview:hover .hover-upload { opacity: 1; }
.config-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
@media (max-width: 992px) { .settings-layout { grid-template-columns: 1fr; } .settings-sidebar { display: flex; overflow-x: auto; padding: 1rem; gap: 0.5rem; position: static; } .settings-nav-btn { margin-bottom: 0; white-space: nowrap; } }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
    <header class="mb-4 d-flex justify-content-between align-items-center">
        <div><h1 class="h3 fw-bold mb-1">Control Center</h1><p class="text-muted small mb-0">System-wide governance and identity management.</p></div>
    </header>

    <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm mb-4 rounded-4"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm mb-4 rounded-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div><?php endif; ?>

    <div class="settings-layout">
        <aside class="settings-sidebar">
            <a href="?group=branding" class="settings-nav-btn <?php echo $active_group==='branding'?'active':'';?>"><i class="bi bi-palette-fill"></i> Branding & Identity</a>
            <a href="?group=workflow" class="settings-nav-btn <?php echo $active_group==='workflow'?'active':'';?>"><i class="bi bi-gear-wide-connected"></i> Core Workflows</a>
            <a href="?group=directory" class="settings-nav-btn <?php echo $active_group==='directory'?'active':'';?>"><i class="bi bi-folder-fill"></i> Global Directories</a>
            <a href="?group=governance" class="settings-nav-btn <?php echo $active_group==='governance'?'active':'';?>"><i class="bi bi-shield-lock-fill"></i> Security & Governance</a>
            <hr class="my-4 opacity-10"><a href="logs" class="settings-nav-btn text-warning"><i class="bi bi-journal-text"></i> Audit Logs</a>
        </aside>

        <main class="settings-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_settings"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <?php if ($active_group === 'branding'): ?>
                <div class="settings-content-card p-4 p-md-5">
                    <div class="row align-items-center mb-5">
                        <div class="col-md-auto text-center text-md-start mb-4 mb-md-0">
                            <div class="logo-giant-preview mx-auto">
                                <img src="../../<?php echo getInstitutionLogo($pdo); ?>" id="logo-preview" alt="Logo">
                                <label class="hover-upload"><i class="bi bi-camera-fill h4 mb-0"></i><input type="file" name="logo_file" class="d-none" onchange="previewLogo(this)"></label>
                            </div>
                        </div>
                        <div class="col-md ms-md-4">
                            <h2 class="h4 fw-bold mb-2">Institutional Identity</h2>
                            <p class="text-muted small mb-0">Manage your official name, address, and primary logo.</p>
                        </div>
                    </div>

                    <div class="config-row">
                        <?php 
                        $branding_items = $config_groups['branding'] ?? [];
                        if (empty($branding_items)) {
                            // Recovery logic if category is missing
                            $branding_items = $pdo->query("SELECT * FROM system_settings WHERE setting_key IN ('church_name', 'church_address', 'church_email', 'church_phone')")->fetchAll();
                        }
                        foreach ($branding_items as $s): if ($s['setting_key'] === 'institution_logo') continue; ?>
                            <div class="col-12 <?php echo ($s['setting_key'] === 'church_address') ? 'col-md-12' : 'col-md-6'; ?>">
                                <label class="form-label small fw-bold text-muted text-uppercase ls-1"><?php echo str_replace('_', ' ', $s['setting_key']); ?></label>
                                <?php if ($s['setting_key'] === 'church_address'): ?>
                                    <textarea class="form-control rounded-3 border-light bg-light" name="settings[<?php echo $s['setting_key']; ?>]" rows="2"><?php echo htmlspecialchars($s['setting_value']); ?></textarea>
                                <?php else: ?>
                                    <input type="text" class="form-control rounded-3 border-light bg-light" name="settings[<?php echo $s['setting_key']; ?>]" value="<?php echo htmlspecialchars($s['setting_value']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-end border-top pt-4"><button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold shadow-sm">Update Identity</button></div>
                </div>
                <?php endif; ?>

                <?php if ($active_group === 'workflow'): ?>
                <div class="settings-content-card p-4 p-md-5">
                    <h3 class="h5 fw-bold mb-4 border-bottom pb-3">Module Governance</h3>
                    <?php foreach (['attendance', 'communication', 'finance'] as $cat): $items = $config_groups[$cat] ?? []; if (!$items) continue; ?>
                        <div class="mb-5">
                            <h4 class="h6 text-primary fw-bold text-uppercase ls-1 mb-3"><?php echo $cat; ?> Flow</h4>
                            <div class="row g-3">
                                <?php foreach ($items as $s): ?>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted"><?php echo str_replace('_', ' ', $s['setting_key']); ?></label>
                                        <?php if (in_array($s['setting_key'], ['attendance_auto_mark', 'notification_email'])): ?>
                                            <select class="form-select rounded-3 border-light bg-light" name="settings[<?php echo $s['setting_key']; ?>]">
                                                <option value="yes" <?php echo $s['setting_value']==='yes'?'selected':'';?>>Enabled</option>
                                                <option value="no" <?php echo $s['setting_value']==='no'?'selected':'';?>>Disabled</option>
                                            </select>
                                        <?php elseif ($s['setting_key'] === 'sms_provider'): ?>
                                            <select class="form-select rounded-3 border-light bg-light" name="settings[<?php echo $s['setting_key']; ?>]">
                                                <option value="bulksmsgh" <?php echo $s['setting_value']==='bulksmsgh'?'selected':'';?>>BulkSMSGH</option>
                                                <option value="arkesel" <?php echo $s['setting_value']==='arkesel'?'selected':'';?>>Arkesel</option>
                                                <option value="twilio" <?php echo $s['setting_value']==='twilio'?'selected':'';?>>Twilio</option>
                                                <option value="none" <?php echo $s['setting_value']==='none'?'selected':'';?>>None</option>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" class="form-control rounded-3 border-light bg-light" name="settings[<?php echo $s['setting_key']; ?>]" value="<?php echo htmlspecialchars($s['setting_value']); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-end border-top pt-4"><button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold shadow-sm">Save Config</button></div>
                </div>
                <?php endif; ?>

                <?php if ($active_group === 'directory'): ?>
                <div class="row g-4">
                    <div class="col-md-6"><div class="settings-content-card p-4"><h4 class="h6 fw-bold mb-3 d-flex justify-content-between">Departments <span class="badge bg-light text-dark rounded-pill"><?php echo count($departments); ?></span></h4><div class="table-responsive" style="max-height: 400px; overflow-y: auto;"><table class="table table-sm align-middle"><tbody id="deptList"><?php foreach ($departments as $d): ?><tr><td class="fw-bold"><?php echo htmlspecialchars($d['name']); ?></td><td class="text-end text-muted small"><?php echo $d['m_count'];?> m</td><td class="text-end"><form method="POST" class="d-inline" onsubmit="return confirm('Purge?');"><input type="hidden" name="action" value="delete_dir_item"><input type="hidden" name="dir_type" value="dept"><input type="hidden" name="item_id" value="<?php echo $d['id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash3"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div><div class="input-group mt-3"><input type="text" id="newDeptName" class="form-control border-light bg-light" placeholder="New Dept..."><button type="button" class="btn btn-primary px-3" onclick="addDirItem('dept')"><i class="bi bi-plus"></i></button></div></div></div>
                    <div class="col-md-6"><div class="settings-content-card p-4"><h4 class="h6 fw-bold mb-3 d-flex justify-content-between">Cell Centers <span class="badge bg-light text-dark rounded-pill"><?php echo count($cell_centers); ?></span></h4><div class="table-responsive" style="max-height: 400px; overflow-y: auto;"><table class="table table-sm align-middle"><tbody><?php foreach ($cell_centers as $c): ?><tr><td class="fw-bold"><?php echo htmlspecialchars($c['name']); ?></td><td class="text-end text-muted small"><?php echo $c['m_count'];?> m</td><td class="text-end"><form method="POST" class="d-inline" onsubmit="return confirm('Purge?');"><input type="hidden" name="action" value="delete_dir_item"><input type="hidden" name="dir_type" value="cell"><input type="hidden" name="item_id" value="<?php echo $c['id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash3"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div><div class="input-group mt-3"><input type="text" id="newCellName" class="form-control border-light bg-light" placeholder="New Center..."><button type="button" class="btn btn-primary px-3" onclick="addDirItem('cell')"><i class="bi bi-plus"></i></button></div></div></div>
                </div>
                <form id="dirForm" method="POST" style="display:none;"><input type="hidden" name="action" value="add_dir_item"><input type="hidden" name="dir_type" id="dir_type"><input type="hidden" name="name" id="dir_name"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"></form>
                <?php endif; ?>

                <?php if ($active_group === 'governance'): ?>
                <div class="settings-content-card p-4 p-md-5">
                    <h3 class="h5 fw-bold mb-4 border-bottom pb-3">Session & Security</h3>
                    <div class="row g-4">
                        <?php foreach (['security', 'system'] as $cat): $items = $config_groups[$cat] ?? []; foreach ($items as $s): ?>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted"><?php echo str_replace('_', ' ', $s['setting_key']); ?></label>
                                <input type="text" class="form-control rounded-3 border-light bg-light" name="settings[<?php echo $s['setting_key']; ?>]" value="<?php echo htmlspecialchars($s['setting_value']); ?>">
                                <?php if ($s['description']): ?><div class="form-text small"><?php echo htmlspecialchars($s['description']); ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; endforeach; ?>
                    </div>
                    <div class="text-end border-top pt-4"><button type="submit" class="btn btn-primary px-5 rounded-pill fw-bold shadow-sm">Save Governance</button></div>
                </div>
                <?php endif; ?>

            </form>
        </main>
    </div>
</div>

<script>
function previewLogo(input) { if (input.files && input.files[0]) { var r = new FileReader(); r.onload = function(e) { document.getElementById('logo-preview').src = e.target.result; }; r.readAsDataURL(input.files[0]); } }
function addDirItem(t) { const n = t === 'dept' ? document.getElementById('newDeptName').value : document.getElementById('newCellName').value; if (!n) return; document.getElementById('dir_type').value = t; document.getElementById('dir_name').value = n; document.getElementById('dirForm').submit(); }
</script>

<?php include '../../includes/footer.php'; ?>
