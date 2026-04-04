<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';

requireLogin('../../../login');
$user_role = getUserRole();

$member_id = $_GET['id'] ?? null;
if (!$member_id) {
    header('Location: list');
    exit;
}

try {
    require '../../../config/database.php';

    if ($_POST) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $phone2 = !empty(trim($_POST['phone2'])) ? trim($_POST['phone2']) : null;
        $gender = !empty($_POST['gender']) ? strtolower($_POST['gender']) : null;
        $dob = $_POST['dob'] ?: null;
        $marital_status = $_POST['marital_status'] ?: null;
        $location = trim($_POST['location']);
        $occupation = trim($_POST['occupation'] ?? '');
        $department_ids = isset($_POST['department_ids']) ? array_filter(array_map('intval', $_POST['department_ids'])) : [];
        $primary_department_id = !empty($_POST['primary_department_id']) ? intval($_POST['primary_department_id']) : null;
        if (!empty($department_ids) && (!$primary_department_id || !in_array($primary_department_id, $department_ids, true))) {
            $error = 'Please select a valid Primary Department from the selected departments.';
        }
        $department_id = !empty($department_ids) ? ($primary_department_id ?: $department_ids[0]) : null;
        $congregation_group = $_POST['congregation_group'];
        $ministerial_status = $_POST['ministerial_status'] ?: null;
        $baptized = $_POST['baptized'];
        $status = $_POST['status'];
        $role_in_church = !empty(trim($_POST['role_in_church'])) ? trim($_POST['role_in_church']) : null;
        $cell_center_id = !empty($_POST['cell_center_id']) ? intval($_POST['cell_center_id']) : null;

        $update_stmt = $pdo->prepare(
            "UPDATE member_roles SET
             name = ?, email = ?, phone = ?, phone2 = ?, gender = ?, dob = ?, marital_status = ?,
             location = ?, occupation = ?, department_id = ?, congregation_group = ?, ministerial_status = ?,
             baptized = ?, status = ?, role_in_church = ?, cell_center_id = ?
             WHERE id = ?"
        );

        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                $updated = $update_stmt->execute([
                    $name, $email, $phone, $phone2, $gender, $dob, $marital_status,
                    $location, $occupation, $department_id, $congregation_group, $ministerial_status,
                    $baptized, $status, $role_in_church, $cell_center_id, $member_id
                ]);

                if (!$updated) {
                    $pdo->rollBack();
                    $error = 'Failed to update member';
                } else {
                    $pdo->prepare("DELETE FROM member_departments WHERE member_id = ?")->execute([$member_id]);
                    if (!empty($department_ids)) {
                        $dept_ins = $pdo->prepare("INSERT IGNORE INTO member_departments (member_id, department_id) VALUES (?, ?)");
                        foreach ($department_ids as $dept_id) {
                            $dept_ins->execute([$member_id, $dept_id]);
                        }
                    }

                    peopleSyncRecord(
                        $pdo,
                        'member_roles',
                        (int)$member_id,
                        $name,
                        $email,
                        $phone,
                        'member'
                    );

                    $pdo->commit();
                    header('Location: view?id=' . $member_id . '&success=Member updated successfully');
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Database error updating member: ' . $e->getMessage());
                $error = 'Database operation failed. Please try again later.';
            }
        }
    }

    $stmt = $pdo->prepare("SELECT mr.*, p.full_name AS name, p.email, p.phone FROM member_roles mr JOIN people p ON mr.person_id = p.id WHERE mr.id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();

    if (!$member) {
        header('Location: list?error=Member not found');
        exit;
    }

    $dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
    $departments = $dept_stmt->fetchAll();

    try {
        $cur_dept_stmt = $pdo->prepare("SELECT department_id FROM member_departments WHERE member_id = ?");
        $cur_dept_stmt->execute([$member_id]);
        $member_dept_ids = array_column($cur_dept_stmt->fetchAll(), 'department_id');
        if (empty($member_dept_ids) && !empty($member['department_id'])) {
            $member_dept_ids = [$member['department_id']];
        }
    } catch (Exception $e) {
        $member_dept_ids = !empty($member['department_id']) ? [$member['department_id']] : [];
    }

    try {
        $cc_stmt = $pdo->query("SELECT * FROM cell_centers ORDER BY name");
        $cell_centers = $cc_stmt->fetchAll();
    } catch (Exception $e) {
        $cell_centers = [];
    }
} catch (Exception $e) {
    error_log('Database error loading member edit page: ' . $e->getMessage());
    die('Database error. Please contact the administrator.');
}

$page_title = "Edit Member - {$member['name']}";
$page_header = true;
$page_icon = 'bi bi-pencil';
$page_heading = 'Edit Member';
$page_description = 'Update member information and details';
$page_actions = '<a href="view?id=' . $member['id'] . '" class="btn btn-secondary"><i class="bi bi-eye"></i> View Member</a>
                <a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

include '../../../includes/header.php';
?>
<link href="../../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/members.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid member-form-page py-4 px-3 px-md-4">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm member-form-shell">
        <div class="card-body p-4 p-lg-5">
            <div class="member-form-hero mb-4">
                <div class="member-form-avatar"><?php echo strtoupper(substr($member['name'], 0, 2)); ?></div>
                <div>
                    <h3 class="mb-1 fw-bold">Edit Member Profile</h3>
                    <p class="mb-0 text-muted">Update personal, contact and church information.</p>
                </div>
            </div>

            <form method="POST" class="needs-validation" novalidate>
                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="member-form-card h-100">
                            <h5 class="member-form-title"><i class="bi bi-person-circle me-2"></i>Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($member['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="">Select</option>
                                        <option value="male" <?php echo ($member['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($member['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($member['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($member['dob'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Marital Status</label>
                                    <select name="marital_status" class="form-select">
                                        <option value="">Select</option>
                                        <?php foreach (['Single', 'Married', 'Divorced', 'Widowed'] as $ms_opt): ?>
                                            <option value="<?php echo $ms_opt; ?>" <?php echo ($member['marital_status'] ?? '') === $ms_opt ? 'selected' : ''; ?>>
                                                <?php echo $ms_opt; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="member-form-card h-100">
                            <h5 class="member-form-title"><i class="bi bi-telephone me-2"></i>Contact Information</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Primary Phone <span class="required">*</span></label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alternative Phone</label>
                                    <input type="tel" name="phone2" class="form-control" value="<?php echo htmlspecialchars($member['phone2'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($member['location'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Occupation</label>
                                    <input type="text" name="occupation" class="form-control" value="<?php echo htmlspecialchars($member['occupation'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="member-form-card">
                            <h5 class="member-form-title"><i class="bi bi-building me-2"></i>Church Information</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Department(s)</label>
                                    <div class="member-dept-box">
                                        <div class="form-check border-bottom pb-1 mb-1">
                                            <input class="form-check-input" type="checkbox" id="edit_dept_none" <?php echo empty($member_dept_ids) ? 'checked' : ''; ?>>
                                            <label class="form-check-label text-muted fst-italic" for="edit_dept_none">None</label>
                                        </div>
                                        <?php foreach ($departments as $dept): ?>
                                            <div class="form-check">
                                                <input class="form-check-input edit-dept-cb" type="checkbox"
                                                       name="department_ids[]"
                                                       value="<?php echo $dept['id']; ?>"
                                                       id="edit_dept_<?php echo $dept['id']; ?>"
                                                       <?php echo in_array($dept['id'], $member_dept_ids) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="edit_dept_<?php echo $dept['id']; ?>">
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Primary Department (Highest Priority)</label>
                                    <select class="form-select" name="primary_department_id" id="edit_primary_department"
                                            data-current-primary="<?php echo htmlspecialchars($member['department_id'] ?? ''); ?>">
                                        <option value="">-- Select Primary Department --</option>
                                    </select>
                                    <small class="text-muted">Choose one from the selected departments.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Congregation Group</label>
                                    <select name="congregation_group" class="form-select">
                                        <option value="Adult" <?php echo ($member['congregation_group'] ?? '') === 'Adult' ? 'selected' : ''; ?>>Adult</option>
                                        <option value="Youth" <?php echo ($member['congregation_group'] ?? '') === 'Youth' ? 'selected' : ''; ?>>Youth</option>
                                        <option value="Teen" <?php echo ($member['congregation_group'] ?? '') === 'Teen' ? 'selected' : ''; ?>>Teen</option>
                                        <option value="Children" <?php echo ($member['congregation_group'] ?? '') === 'Children' ? 'selected' : ''; ?>>Children</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Ministerial Status</label>
                                    <select name="ministerial_status" class="form-select">
                                        <option value="">Not Set</option>
                                        <?php foreach (['Levite', 'Shepherd', 'Minister', 'Junior Pastor', 'Senior Pastor', 'General Overseer'] as $opt): ?>
                                            <option value="<?php echo $opt; ?>" <?php echo ($member['ministerial_status'] ?? '') === $opt ? 'selected' : ''; ?>>
                                                <?php echo $opt; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Baptism Status</label>
                                    <select name="baptized" class="form-select">
                                        <option value="no" <?php echo ($member['baptized'] ?? 'no') === 'no' ? 'selected' : ''; ?>>Not Baptized</option>
                                        <option value="yes" <?php echo ($member['baptized'] ?? '') === 'yes' ? 'selected' : ''; ?>>Baptized</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Member Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?php echo ($member['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($member['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Role in Church</label>
                                    <input type="text" name="role_in_church" class="form-control" value="<?php echo htmlspecialchars($member['role_in_church'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Cell Center</label>
                                    <select name="cell_center_id" class="form-select">
                                        <option value="">-- Select Cell Center --</option>
                                        <?php foreach ($cell_centers as $cc): ?>
                                            <option value="<?php echo $cc['id']; ?>" <?php echo ($member['cell_center_id'] ?? '') == $cc['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cc['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
                    <a href="view?id=<?php echo $member['id']; ?>" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i>View</a>
                    <a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const editNoneCb = document.getElementById('edit_dept_none');
const editDeptCbs = document.querySelectorAll('.edit-dept-cb');
const editPrimarySelect = document.getElementById('edit_primary_department');

function syncEditPrimaryDepartmentOptions() {
    if (!editPrimarySelect) return;
    const previousValue = editPrimarySelect.value || editPrimarySelect.dataset.currentPrimary || '';
    editPrimarySelect.innerHTML = '<option value="">-- Select Primary Department --</option>';

    editDeptCbs.forEach(cb => {
        if (cb.checked) {
            const option = document.createElement('option');
            option.value = cb.value;
            const label = document.querySelector('label[for="' + cb.id + '"]');
            option.textContent = label ? label.textContent.trim() : ('Department ' + cb.value);
            editPrimarySelect.appendChild(option);
        }
    });

    if (previousValue && [...editPrimarySelect.options].some(opt => opt.value === previousValue)) {
        editPrimarySelect.value = previousValue;
    } else if (editPrimarySelect.options.length === 2) {
        editPrimarySelect.selectedIndex = 1;
    }

    editPrimarySelect.dataset.currentPrimary = editPrimarySelect.value;
}

if (editNoneCb) {
    editNoneCb.addEventListener('change', function() {
        if (this.checked) {
            editDeptCbs.forEach(cb => cb.checked = false);
            syncEditPrimaryDepartmentOptions();
        }
    });
    editDeptCbs.forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) editNoneCb.checked = false;
            if ([...editDeptCbs].every(c => !c.checked)) editNoneCb.checked = true;
            syncEditPrimaryDepartmentOptions();
        });
    });
}

syncEditPrimaryDepartmentOptions();

(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../../includes/footer.php'; ?>
