<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';

requireLogin('../../../login');
$user_role = getUserRole();

try {
    require '../../../config/database.php';

    $hasColumn = function ($table, $column) use ($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $has_visitors_notes = $hasColumn('visitor_roles', 'notes');
    $has_visitors_converted_date = $hasColumn('visitor_roles', 'converted_date');
    $has_visitors_became_member = $hasColumn('visitor_roles', 'became_member');
    $visitor_status_member = 'converted_to_member';

    $status_type_stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'visitor_roles' AND COLUMN_NAME = 'status'");
    $status_type_stmt->execute();
    $status_col_type = (string)$status_type_stmt->fetchColumn();
    if (stripos($status_col_type, 'enum(') === 0) {
        preg_match_all("/'([^']*)'/", $status_col_type, $status_matches);
        $status_values = $status_matches[1] ?? [];
        foreach (['converted_to_member', 'converted', 'contacted', 'active', 'pending'] as $candidate) {
            if (in_array($candidate, $status_values, true)) {
                $visitor_status_member = $candidate;
                break;
            }
        }
    }

    $from_visitor_id = isset($_GET['from_visitor']) ? (int)$_GET['from_visitor'] : (isset($_POST['from_visitor']) ? (int)$_POST['from_visitor'] : 0);
    $prefill_name = '';
    $prefill_email = '';
    $prefill_phone = '';

    if ($from_visitor_id > 0) {
        $visitor_prefill_stmt = $pdo->prepare("SELECT vr.id, p.full_name AS name, p.email, p.phone FROM visitor_roles vr JOIN people p ON vr.person_id = p.id WHERE vr.id = ?");
        $visitor_prefill_stmt->execute([$from_visitor_id]);
        $visitor_prefill = $visitor_prefill_stmt->fetch();

        if ($visitor_prefill) {
            $prefill_name = $visitor_prefill['name'] ?? '';
            $prefill_email = $visitor_prefill['email'] ?? '';
            $prefill_phone = $visitor_prefill['phone'] ?? '';
        } else {
            $from_visitor_id = 0;
        }
    }

    $resolvePersonCompat = function (string $fullName, ?string $email, ?string $phone, string $stage) use ($pdo, $hasColumn) {
        if (!peopleSyncTableExists($pdo)) {
            return null;
        }

        $fullName = trim($fullName);
        $emailNorm = trim(strtolower((string)$email));
        $emailNorm = $emailNorm === '' ? null : $emailNorm;

        $phoneNorm = preg_replace('/[^0-9]/', '', trim((string)$phone));
        $phoneNorm = $phoneNorm === '' ? null : $phoneNorm;

        $hasEmailKey = $hasColumn('people', 'email_key');
        $hasPhoneKey = $hasColumn('people', 'phone_key');

        $findByEmail = function (?string $emailKey) use ($pdo, $hasEmailKey) {
            if ($emailKey === null) {
                return null;
            }

            if ($hasEmailKey) {
                $stmt = $pdo->prepare('SELECT id FROM people WHERE email_key = ? ORDER BY id ASC LIMIT 1');
                $stmt->execute([$emailKey]);
            } else {
                $stmt = $pdo->prepare('SELECT id FROM people WHERE LOWER(TRIM(email)) = ? ORDER BY id ASC LIMIT 1');
                $stmt->execute([$emailKey]);
            }

            $id = $stmt->fetchColumn();
            return $id === false ? null : (int)$id;
        };

        $findByPhone = function (?string $phoneKey) use ($pdo, $hasPhoneKey) {
            if ($phoneKey === null) {
                return null;
            }

            if ($hasPhoneKey) {
                $stmt = $pdo->prepare('SELECT id FROM people WHERE phone_key = ? ORDER BY id ASC LIMIT 1');
                $stmt->execute([$phoneKey]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM people WHERE REGEXP_REPLACE(TRIM(phone), '[^0-9]', '') = ? ORDER BY id ASC LIMIT 1");
                $stmt->execute([$phoneKey]);
            }

            $id = $stmt->fetchColumn();
            return $id === false ? null : (int)$id;
        };

        $emailMatchId = $findByEmail($emailNorm);
        $phoneMatchId = $findByPhone($phoneNorm);

        if ($emailMatchId !== null && $phoneMatchId !== null && $emailMatchId !== $phoneMatchId) {
            throw new RuntimeException('Identity conflict: this email and phone are linked to different people records.');
        }

        $matchedId = $emailMatchId ?? $phoneMatchId;

        if ($matchedId === null && $fullName !== '') {
            $stmt = $pdo->prepare('SELECT id FROM people WHERE LOWER(TRIM(full_name)) = LOWER(?) ORDER BY id ASC LIMIT 1');
            $stmt->execute([$fullName]);
            $nameId = $stmt->fetchColumn();
            $matchedId = $nameId === false ? null : (int)$nameId;
        }

        $stageRank = function (string $value) {
            $rank = [
                'visitor' => 1,
                'new_convert' => 2,
                'member' => 3,
            ];
            $k = strtolower(trim($value));
            return $rank[$k] ?? 0;
        };

        if ($matchedId !== null) {
            $currentStmt = $pdo->prepare('SELECT current_stage FROM people WHERE id = ? LIMIT 1');
            $currentStmt->execute([$matchedId]);
            $currentStage = (string)$currentStmt->fetchColumn();
            $newStage = $stageRank($stage) > $stageRank($currentStage) ? $stage : ($currentStage !== '' ? $currentStage : $stage);

            if ($hasEmailKey && $hasPhoneKey) {
                $upd = $pdo->prepare('UPDATE people
                    SET full_name = CASE WHEN LENGTH(TRIM(?)) = 0 THEN full_name ELSE ? END,
                        email = COALESCE(email, ?),
                        phone = COALESCE(phone, ?),
                        email_key = COALESCE(email_key, ?),
                        phone_key = COALESCE(phone_key, ?),
                        current_stage = ?,
                        last_seen_at = NOW()
                    WHERE id = ?');
                $upd->execute([$fullName, $fullName, $emailNorm, $phoneNorm, $emailNorm, $phoneNorm, $newStage, $matchedId]);
            } else {
                $upd = $pdo->prepare('UPDATE people
                    SET full_name = CASE WHEN LENGTH(TRIM(?)) = 0 THEN full_name ELSE ? END,
                        email = COALESCE(email, ?),
                        phone = COALESCE(phone, ?),
                        current_stage = ?,
                        last_seen_at = NOW()
                    WHERE id = ?');
                $upd->execute([$fullName, $fullName, $emailNorm, $phoneNorm, $newStage, $matchedId]);
            }

            return $matchedId;
        }

        if ($hasEmailKey && $hasPhoneKey) {
            $ins = $pdo->prepare('INSERT INTO people (full_name, email, phone, email_key, phone_key, current_stage, first_seen_at, last_seen_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $ins->execute([$fullName, $emailNorm, $phoneNorm, $emailNorm, $phoneNorm, $stage]);
        } else {
            $ins = $pdo->prepare('INSERT INTO people (full_name, email, phone, current_stage, first_seen_at, last_seen_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())');
            $ins->execute([$fullName, $emailNorm, $phoneNorm, $stage]);
        }

        return (int)$pdo->lastInsertId();
    };

    $resolvePersonForMember = function (string $fullName, ?string $email, ?string $phone, string $stage) use ($pdo, $resolvePersonCompat) {
        try {
            return peopleFindOrCreate($pdo, $fullName, $email, $phone, $stage);
        } catch (Exception $e) {
            $msg = (string)$e->getMessage();
            if (stripos($msg, "Unknown column 'p.phone'") !== false) {
                return $resolvePersonCompat($fullName, $email, $phone, $stage);
            }
            throw $e;
        }
    };

    if ($_POST) {
        $name = trim($_POST['name']);
        $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
        $phone = trim($_POST['phone']);
        $phone2 = !empty(trim($_POST['phone2'])) ? trim($_POST['phone2']) : null;
        $gender = !empty($_POST['gender']) ? strtolower($_POST['gender']) : null;
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $marital_status = !empty($_POST['marital_status']) ? $_POST['marital_status'] : null;
        $location = !empty(trim($_POST['location'])) ? trim($_POST['location']) : null;
        $occupation = !empty(trim($_POST['occupation'])) ? trim($_POST['occupation']) : null;
        $department_ids = isset($_POST['department_ids']) ? array_filter(array_map('intval', $_POST['department_ids'])) : [];
        $primary_department_id = !empty($_POST['primary_department_id']) ? intval($_POST['primary_department_id']) : null;
        if (!empty($department_ids) && (!$primary_department_id || !in_array($primary_department_id, $department_ids, true))) {
            $error = 'Please select a valid Primary Department from the selected departments.';
        }
        $department_id = !empty($department_ids) ? ($primary_department_id ?: $department_ids[0]) : null;
        $congregation_group = !empty($_POST['congregation_group']) ? $_POST['congregation_group'] : 'Adult';
        $ministerial_status = !empty($_POST['ministerial_status']) ? $_POST['ministerial_status'] : null;
        $baptized = !empty($_POST['baptized']) ? $_POST['baptized'] : 'no';
        $role_in_church = !empty(trim($_POST['role_in_church'])) ? trim($_POST['role_in_church']) : null;
        $cell_center_id = !empty($_POST['cell_center_id']) ? intval($_POST['cell_center_id']) : null;

        if ($name && $phone && empty($error)) {
            try {
                $resolved_person_id = null;
                if (peopleSyncTableExists($pdo)) {
                    $resolved_person_id = $resolvePersonForMember($name, $email ?? '', $phone, 'member');
                    if ($resolved_person_id) {
                        $existing_member_stmt = $pdo->prepare("SELECT id FROM member_roles WHERE person_id = ? AND status = 'active' LIMIT 1");
                        $existing_member_stmt->execute([$resolved_person_id]);
                        $existing_member_id = (int)$existing_member_stmt->fetchColumn();

                        if ($existing_member_id > 0) {
                            throw new RuntimeException('A member already exists with this email or phone number.');
                        }
                    }
                }

                $pid_for_insert = $resolved_person_id ?: $resolvePersonForMember($name, $email ?? '', $phone, 'member');
                if (!$pid_for_insert) {
                    throw new RuntimeException('Could not resolve person profile for this member.');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO member_roles
                     (person_id, name, email, phone, phone2, gender, dob, location, occupation,
                      department_id, congregation_group, baptized, ministerial_status, marital_status,
                      role_in_church, cell_center_id, status, date_joined)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())"
                );

                $pdo->beginTransaction();

                if ($stmt->execute([
                    $pid_for_insert, $name, $email, $phone, $phone2, $gender, $dob, $location, $occupation,
                    $department_id, $congregation_group, $baptized, $ministerial_status, $marital_status,
                    $role_in_church, $cell_center_id
                ])) {
                    $member_id = (int)$pdo->lastInsertId();

                    if (!empty($department_ids)) {
                        $dept_ins = $pdo->prepare("INSERT IGNORE INTO member_departments (member_id, department_id) VALUES (?, ?)");
                        foreach ($department_ids as $dept_id) {
                            $dept_ins->execute([$member_id, $dept_id]);
                        }
                    }

                    if ($from_visitor_id > 0) {
                        $updates = ["status = ?"];
                        $update_params = [$visitor_status_member];
                        if ($has_visitors_became_member) {
                            $updates[] = "became_member = 'yes'";
                        }
                        if ($has_visitors_converted_date) {
                            $updates[] = "converted_date = CURDATE()";
                        }
                        if ($has_visitors_notes) {
                            $updates[] = "notes = CONCAT(COALESCE(notes, ''), '\nConverted via members/add on ', NOW())";
                        }

                        $visitor_update = $pdo->prepare("UPDATE visitor_roles SET " . implode(', ', $updates) . " WHERE id = ?");
                        $update_params[] = $from_visitor_id;
                        $visitor_update->execute($update_params);
                    }

                    // Unified people sync (enforced)
                    $pid = $resolved_person_id ?: $resolvePersonForMember($name, $email ?? '', $phone, 'member');
                    if ($pid) {
                        peopleRelinkRecord($pdo, 'member_roles', $member_id, $pid);
                        if ($from_visitor_id > 0) {
                            peopleRelinkRecord($pdo, 'visitor_roles', $from_visitor_id, $pid);
                        }
                        peopleEnsureStage($pdo, $pid, 'member');
                        peopleAddLifecycleEvent($pdo, $pid, 'became_member', 'member_roles', $member_id);
                    }

                    $pdo->commit();
                    header('Location: view?id=' . $member_id . '&success=Member added successfully');
                    exit;
                }

                $pdo->rollBack();
                $error = 'Failed to add member';
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Runtime error adding member: ' . $e->getMessage());
                $error = 'Unable to add member right now.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Database error adding member: ' . $e->getMessage());
                $error = 'Database operation failed. Please try again later.';
            }
        } else {
            $error = 'Please provide both name and phone number';
        }
    }

    $dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
    $departments = $dept_stmt->fetchAll();

    try {
        $cc_stmt = $pdo->query("SELECT * FROM cell_centers ORDER BY name");
        $cell_centers = $cc_stmt->fetchAll();
    } catch (Exception $e) {
        $cell_centers = [];
    }
} catch (Exception $e) {
    error_log('Database error loading member add page: ' . $e->getMessage());
    die('Database error. Please contact the administrator.');
}

$page_title = 'Add Member - Bridge Ministries International';
$page_header = true;
$page_icon = 'bi bi-person-plus';
$page_heading = 'Add New Member';
$page_description = 'Register a new church member';
$page_actions = '<a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>';

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
                <div class="member-form-avatar"><i class="bi bi-person-plus"></i></div>
                <div>
                    <h3 class="mb-1 fw-bold">Create Member Profile</h3>
                    <p class="mb-0 text-muted">Complete the form below to add a member to the directory.</p>
                </div>
            </div>

            <form method="POST" id="memberForm" novalidate>
                <?php if (!empty($from_visitor_id)): ?>
                    <input type="hidden" name="from_visitor" value="<?php echo (int)$from_visitor_id; ?>">
                <?php endif; ?>
                <div class="row g-4">
                    <div class="col-12 col-xl-6">
                        <div class="member-form-card h-100">
                            <h5 class="member-form-title"><i class="bi bi-person-circle me-2"></i>Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $prefill_name ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="">Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="dob">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Marital Status</label>
                                    <select class="form-select" name="marital_status">
                                        <option value="">Select</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
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
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $prefill_email ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Primary Phone <span class="required">*</span></label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $prefill_phone ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alternative Phone</label>
                                    <input type="tel" class="form-control" name="phone2">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Occupation</label>
                                    <input type="text" class="form-control" name="occupation">
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
                                    <div class="member-dept-box" id="add_dept_list">
                                        <div class="form-check border-bottom pb-1 mb-1">
                                            <input class="form-check-input" type="checkbox" id="add_dept_none" checked>
                                            <label class="form-check-label text-muted fst-italic" for="add_dept_none">None</label>
                                        </div>
                                        <?php foreach ($departments as $dept): ?>
                                            <div class="form-check">
                                                <input class="form-check-input add-dept-cb" type="checkbox"
                                                       name="department_ids[]"
                                                       value="<?php echo $dept['id']; ?>"
                                                       id="add_dept_<?php echo $dept['id']; ?>">
                                                <label class="form-check-label" for="add_dept_<?php echo $dept['id']; ?>">
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($departments)): ?>
                                            <span class="text-muted small">No departments available</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Primary Department (Highest Priority)</label>
                                    <select class="form-select" name="primary_department_id" id="add_primary_department">
                                        <option value="">-- Select Primary Department --</option>
                                    </select>
                                    <small class="text-muted">Choose one from the selected departments.</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Congregation Group</label>
                                    <select class="form-select" name="congregation_group">
                                        <option value="Adult">Adult</option>
                                        <option value="Youth">Youth</option>
                                        <option value="Teen">Teen</option>
                                        <option value="Children">Children</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Ministerial Status</label>
                                    <select class="form-select" name="ministerial_status">
                                        <option value="">Not Set</option>
                                        <option value="Levite">Levite</option>
                                        <option value="Shepherd">Shepherd</option>
                                        <option value="Minister">Minister</option>
                                        <option value="Junior Pastor">Junior Pastor</option>
                                        <option value="Senior Pastor">Senior Pastor</option>
                                        <option value="General Overseer">General Overseer</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Baptized Status</label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="baptized" value="no" id="baptized_no" checked>
                                            <label class="form-check-label" for="baptized_no">Not Baptized</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="baptized" value="yes" id="baptized_yes">
                                            <label class="form-check-label" for="baptized_yes">Baptized</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Role in Church</label>
                                    <input type="text" class="form-control" name="role_in_church" placeholder="e.g. Choir, Usher, Youth Leader">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Cell Center</label>
                                    <select class="form-select" name="cell_center_id">
                                        <option value="">-- Select Cell Center --</option>
                                        <?php foreach ($cell_centers as $cc): ?>
                                            <option value="<?php echo $cc['id']; ?>"><?php echo htmlspecialchars($cc['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
                    <a href="list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
                    <button type="reset" class="btn btn-outline-warning"><i class="bi bi-arrow-clockwise me-1"></i>Reset</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle-fill me-1"></i>Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const addNoneCb = document.getElementById('add_dept_none');
const addDeptCbs = document.querySelectorAll('.add-dept-cb');
const addPrimarySelect = document.getElementById('add_primary_department');

function syncAddPrimaryDepartmentOptions() {
    if (!addPrimarySelect) return;
    const previousValue = addPrimarySelect.value;
    addPrimarySelect.innerHTML = '<option value="">-- Select Primary Department --</option>';

    addDeptCbs.forEach(cb => {
        if (cb.checked) {
            const option = document.createElement('option');
            option.value = cb.value;
            const label = document.querySelector('label[for="' + cb.id + '"]');
            option.textContent = label ? label.textContent.trim() : ('Department ' + cb.value);
            addPrimarySelect.appendChild(option);
        }
    });

    if (previousValue && [...addPrimarySelect.options].some(opt => opt.value === previousValue)) {
        addPrimarySelect.value = previousValue;
    } else if (addPrimarySelect.options.length === 2) {
        addPrimarySelect.selectedIndex = 1;
    }
}

if (addNoneCb) {
    addNoneCb.addEventListener('change', function() {
        if (this.checked) {
            addDeptCbs.forEach(cb => cb.checked = false);
            syncAddPrimaryDepartmentOptions();
        }
    });
    addDeptCbs.forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) addNoneCb.checked = false;
            if ([...addDeptCbs].every(c => !c.checked)) addNoneCb.checked = true;
            syncAddPrimaryDepartmentOptions();
        });
    });
}

syncAddPrimaryDepartmentOptions();

document.getElementById('memberForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    if (!isValid) {
        e.preventDefault();
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
