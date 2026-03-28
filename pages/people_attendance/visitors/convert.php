<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';

requireLogin('../../../login.php');
$user_role = getUserRole();

// Helper function to convert empty strings to null for database
function emptyToNull($value) {
    return (!empty($value)) ? $value : null;
}

// Get visitor ID
$visitor_id = $_GET['id'] ?? null;
if (!$visitor_id) {
    header('Location: list.php');
    exit;
}

$message = '';
$error = '';
$has_visitors_notes = false;
$has_visitors_converted_date = false;
$has_visitors_became_member = false;
$has_new_converts_notes = false;
$has_new_converts_member_conversion_date = false;
$has_new_converts_created_at = false;
$has_members_notes = false;
$has_members_created_at = false;
$visitor_status_new_convert = 'converted_to_convert';
$visitor_status_member = 'converted_to_member';
$new_convert_status_member = 'converted_to_member';

try {
    require '../../../config/database.php';

    $hasColumn = function ($table, $column) use ($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $getEnumValues = function ($table, $column) use ($pdo) {
        $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        $columnType = (string)$stmt->fetchColumn();
        if (stripos($columnType, 'enum(') !== 0) {
            return [];
        }

        preg_match_all("/'([^']*)'/", $columnType, $matches);
        return $matches[1] ?? [];
    };

    $pickStatus = function ($allowed, $preferred, $fallbacks) {
        $candidates = array_merge([$preferred], $fallbacks);
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }
        return !empty($allowed) ? $allowed[0] : $preferred;
    };

    $has_visitors_notes = $hasColumn('visitors', 'notes');
    $has_visitors_converted_date = $hasColumn('visitors', 'converted_date');
    $has_visitors_became_member = $hasColumn('visitors', 'became_member');
    $has_new_converts_notes = $hasColumn('new_converts', 'notes');
    $has_new_converts_member_conversion_date = $hasColumn('new_converts', 'member_conversion_date');
    $has_new_converts_created_at = $hasColumn('new_converts', 'created_at');
    $has_members_notes = $hasColumn('members', 'notes');
    $has_members_created_at = $hasColumn('members', 'created_at');

    $visitor_status_values = $getEnumValues('visitors', 'status');
    $new_convert_status_values = $getEnumValues('new_converts', 'status');

    if (!empty($visitor_status_values)) {
        $visitor_status_new_convert = $pickStatus($visitor_status_values, 'converted_to_convert', ['converted', 'contacted', 'active', 'pending']);
        $visitor_status_member = $pickStatus($visitor_status_values, 'converted_to_member', ['converted', 'contacted', 'active', 'pending']);
    }

    if (!empty($new_convert_status_values)) {
        $new_convert_status_member = $pickStatus($new_convert_status_values, 'converted_to_member', ['converted', 'active', 'inactive']);
    }
    
    // Get visitor information
    $visitor_stmt = $pdo->prepare("SELECT v.*, s.name as service_name FROM visitor_roles v 
                                   LEFT JOIN services s ON v.service_id = s.id 
                                   WHERE v.id = ?");
    $visitor_stmt->execute([$visitor_id]);
    $visitor = $visitor_stmt->fetch();
    
    if (!$visitor) {
        header('Location: list.php?error=' . urlencode('Visitor not found'));
        exit;
    }
    
    // Get departments for dropdown
    $departments_stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
    $departments = $departments_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission - Convert to New Convert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_new_convert'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department_id = emptyToNull($_POST['department_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($name)) {
        $error = "Name is required.";
    } else {
        try {
            $existing_for_visitor_stmt = $pdo->prepare("SELECT id FROM new_convert_roles WHERE visitor_id = ? LIMIT 1");
            $existing_for_visitor_stmt->execute([$visitor_id]);
            $existing_for_visitor = $existing_for_visitor_stmt->fetch();

            if ($existing_for_visitor) {
                $error = "This visitor already has a New Convert record.";
            } else {
            // Check if convert already exists (by phone or email)
            $existing_check = null;
            if (!empty($phone) || !empty($email)) {
                $check_sql = "SELECT id FROM new_convert_roles WHERE";
                $check_params = [];
                $conditions = [];
                
                if (!empty($phone)) {
                    $conditions[] = "phone = ?";
                    $check_params[] = $phone;
                }
                if (!empty($email)) {
                    $conditions[] = "email = ?";
                    $check_params[] = $email;
                }
                
                $check_sql .= " " . implode(' OR ', $conditions);
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute($check_params);
                $existing_check = $check_stmt->fetch();
            }
            
                if ($existing_check) {
                    $error = "A new convert with this phone number or email already exists.";
                } else {
                $pdo->beginTransaction();
                
                $convert_columns = ['name', 'phone', 'email', 'department_id'];
                $convert_values = ['?', '?', '?', '?'];
                $convert_params = [$name, $phone, $email, $department_id];

                if ($has_new_converts_notes) {
                    $convert_columns[] = 'notes';
                    $convert_values[] = '?';
                    $convert_params[] = $notes;
                }

                $convert_columns[] = 'date_converted';
                $convert_values[] = 'CURDATE()';

                if ($has_new_converts_created_at) {
                    $convert_columns[] = 'created_at';
                    $convert_values[] = 'NOW()';
                }

                $convert_columns[] = 'visitor_id';
                $convert_values[] = '?';
                $convert_params[] = $visitor_id;

                $convert_sql = "INSERT INTO new_convert_roles (" . implode(', ', $convert_columns) . ") VALUES (" . implode(', ', $convert_values) . ")";

                $convert_stmt = $pdo->prepare($convert_sql);
                $convert_result = $convert_stmt->execute($convert_params);
                $new_convert_id = $convert_result ? (int)$pdo->lastInsertId() : 0;
                
                if (!$convert_result) {
                    $pdo->rollBack();
                    $error = "Failed to create new convert record.";
                } else {
                    // Update visitor status to converted
                    $visitor_updates = ["status = ?"];
                    $visitor_update_params = [$visitor_status_new_convert];
                    if ($has_visitors_notes) {
                        $visitor_updates[] = "notes = CONCAT(COALESCE(notes, ''), '\nConverted to New Convert on ', NOW())";
                    }
                    if ($has_visitors_converted_date) {
                        $visitor_updates[] = "converted_date = CURDATE()";
                    }

                    $update_visitor_sql = "UPDATE visitor_roles SET " . implode(', ', $visitor_updates) . " WHERE id = ?";
                    $visitor_update_params[] = $visitor_id;
                    $update_result = $pdo->prepare($update_visitor_sql)->execute($visitor_update_params);
                    
                    if (!$update_result) {
                        $pdo->rollBack();
                        $error = "Failed to update visitor status.";
                    } else {
                        $visitor_pid = !empty($visitor['person_id']) ? (int)$visitor['person_id'] : null;
                        $pid = $visitor_pid ?: peopleFindOrCreateCompat($pdo, $name, $email, $phone, 'new_convert');
                        if ($pid && $new_convert_id) {
                            peopleRelinkRecord($pdo, 'visitors', (int)$visitor_id, $pid);
                            peopleRelinkRecord($pdo, 'new_converts', $new_convert_id, $pid);
                            peopleEnsureStage($pdo, $pid, 'new_convert');
                            peopleAddLifecycleEvent($pdo, $pid, 'became_new_convert', 'new_converts', $new_convert_id);
                        }

                        $pdo->commit();

                        $message = "Visitor successfully converted to New Convert!";
                        
                        // Redirect to new converts page after successful conversion
                        header('Location: new_converts.php?message=' . urlencode($message));
                        exit;
                    }
                }
                }
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle form submission - Convert to Full Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_to_member'])) {
    $convert_id = $_POST['convert_id'] ?? null;
    $congregation_group = $_POST['congregation_group'] ?? 'adult';
    $baptized = $_POST['baptized'] ?? 'no';
    $member_notes = trim($_POST['member_notes'] ?? '');
    
    if (!$convert_id) {
        $error = "Invalid convert ID.";
    } else {
        try {
            // Get new convert information
            $convert_stmt = $pdo->prepare("SELECT * FROM new_convert_roles WHERE id = ?");
            $convert_stmt->execute([$convert_id]);
            $convert = $convert_stmt->fetch();
            
            if (!$convert) {
                $error = "New convert not found.";
            } else {
                // Check if member already exists
                $existing_member = null;
                if (!empty($convert['phone']) || !empty($convert['email'])) {
                    $check_sql = "SELECT id FROM member_roles WHERE";
                    $check_params = [];
                    $conditions = [];
                    
                    if (!empty($convert['phone'])) {
                        $conditions[] = "phone = ?";
                        $check_params[] = $convert['phone'];
                    }
                    if (!empty($convert['email'])) {
                        $conditions[] = "email = ?";
                        $check_params[] = $convert['email'];
                    }
                    
                    $check_sql .= " " . implode(' OR ', $conditions);
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute($check_params);
                    $existing_member = $check_stmt->fetch();
                }
                
                if ($existing_member) {
                    $error = "A member with this phone number or email already exists.";
                } else {
                    $pdo->beginTransaction();
                    
                    // Create member record
                    $member_columns = ['name', 'phone', 'email', 'department_id', 'congregation_group', 'baptized', 'status', 'date_joined'];
                    $member_values = ['?', '?', '?', '?', '?', '?', "'active'", 'CURDATE()'];
                    $member_params = [
                        $convert['name'],
                        $convert['phone'],
                        $convert['email'],
                        emptyToNull($convert['department_id']),
                        $congregation_group,
                        $baptized
                    ];

                    if ($has_members_notes) {
                        $member_columns[] = 'notes';
                        $member_values[] = '?';
                        $member_params[] = trim($member_notes . "\nSource: New Convert #" . $convert['id'] . (!empty($convert['visitor_id']) ? " (Visitor #" . $convert['visitor_id'] . ")" : ""));
                    }

                    if ($has_members_created_at) {
                        $member_columns[] = 'created_at';
                        $member_values[] = 'NOW()';
                    }

                    $member_sql = "INSERT INTO member_roles (" . implode(', ', $member_columns) . ") VALUES (" . implode(', ', $member_values) . ")";
                    $member_stmt = $pdo->prepare($member_sql);
                    $member_result = $member_stmt->execute($member_params);
                    $new_member_id = $member_result ? (int)$pdo->lastInsertId() : 0;
                    
                    if (!$member_result) {
                        $pdo->rollBack();
                        $error = "Failed to create member record.";
                    } else {
                        // Update new convert status
                        $convert_updates = ["status = ?"];
                        $convert_update_params = [$new_convert_status_member];
                        if ($has_new_converts_member_conversion_date) {
                            $convert_updates[] = "member_conversion_date = CURDATE()";
                        }
                        if ($has_new_converts_notes) {
                            $convert_updates[] = "notes = CONCAT(COALESCE(notes, ''), '\nConverted to Full Member on ', NOW())";
                        }

                        $update_convert_sql = "UPDATE new_convert_roles SET " . implode(', ', $convert_updates) . " WHERE id = ?";
                        $convert_update_params[] = $convert_id;
                        $update_result = $pdo->prepare($update_convert_sql)->execute($convert_update_params);
                        
                        if (!$update_result) {
                            $pdo->rollBack();
                            $error = "Failed to update new convert status.";
                        } else {
                            if (!empty($convert['visitor_id'])) {
                                $visitor_updates = ["status = ?"];
                                $visitor_update_params = [$visitor_status_member];
                                if ($has_visitors_became_member) {
                                    $visitor_updates[] = "became_member = 'yes'";
                                }
                                if ($has_visitors_converted_date) {
                                    $visitor_updates[] = "converted_date = CURDATE()";
                                }
                                if ($has_visitors_notes) {
                                    $visitor_updates[] = "notes = CONCAT(COALESCE(notes, ''), '\nPromoted to member on ', NOW())";
                                }

                                $update_visitor_sql = "UPDATE visitor_roles SET " . implode(', ', $visitor_updates) . " WHERE id = ?";
                                $visitor_update_params[] = $convert['visitor_id'];
                                $pdo->prepare($update_visitor_sql)->execute($visitor_update_params);
                            }

                            $convert_pid = !empty($convert['person_id']) ? (int)$convert['person_id'] : null;
                            $pid = $convert_pid ?: peopleFindOrCreateCompat($pdo, $convert['name'], $convert['email'], $convert['phone'], 'member');
                            if ($pid && $new_member_id) {
                                peopleRelinkRecord($pdo, 'members', $new_member_id, $pid);
                                peopleRelinkRecord($pdo, 'new_converts', (int)$convert_id, $pid);
                                if (!empty($convert['visitor_id'])) {
                                    peopleRelinkRecord($pdo, 'visitors', (int)$convert['visitor_id'], $pid);
                                }
                                peopleEnsureStage($pdo, $pid, 'member');
                                peopleAddLifecycleEvent($pdo, $pid, 'became_member', 'members', $new_member_id);
                            }

                            $pdo->commit();

                            $message = "New Convert successfully converted to Full Member!";
                            
                            // Redirect to members list
                            header('Location: ../members/list.php?message=' . urlencode($message));
                            exit;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get existing new converts for this visitor
try {
    $converts_stmt = $pdo->prepare("SELECT nc.*, d.name as department_name 
                                   FROM new_convert_roles nc 
                                   LEFT JOIN departments d ON nc.department_id = d.id 
                                   WHERE nc.visitor_id = ? 
                                   ORDER BY nc.date_converted DESC");
    $converts_stmt->execute([$visitor_id]);
    $existing_converts = $converts_stmt->fetchAll();
} catch (Exception $e) {
    $existing_converts = [];
}

// Page configuration
$page_title = "Convert Visitor to New Convert";
$page_header = true;
$page_icon = "bi bi-person-plus-fill";
$page_heading = "Convert Visitor to New Convert";
$page_description = "Convert visitor to new convert status or promote to full membership";

include '../../../includes/header.php';
?>

<link href="../../../assets/css/dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/visitors.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="../../../assets/css/convert-workflow.css?v=<?php echo time(); ?>" rel="stylesheet">

<div class="container-fluid py-4 convert-workflow-page">

<!-- Using Bootstrap classes only -->



<div class="row">
    <div class="col-lg-8">
        <!-- Visitor Information -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="conversion-card">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-person-circle person-circle-large"></i>
                    </div>
                    <h3 class="mb-2"><?= htmlspecialchars($visitor['name']) ?></h3>
                    <?php $visitLabel = !empty($visitor['date']) ? date('F d, Y', strtotime($visitor['date'])) : 'Unknown date'; ?>
                    <p class="mb-1">
                        <i class="bi bi-calendar-event me-2"></i>
                        Visited on <?= htmlspecialchars($visitLabel) ?>
                    </p>
                    <?php if ($visitor['service_name']): ?>
                    <p class="mb-1">
                        <i class="bi bi-building me-2"></i>
                        Service: <?= htmlspecialchars($visitor['service_name']) ?>
                    </p>
                    <?php endif; ?>
                    <p class="mb-0">
                        <i class="bi bi-star me-2"></i>
                        <?= $visitor['first_time'] == 'yes' ? 'First Time Visitor' : 'Return Visitor' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Convert to New Convert Form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-success bg-opacity-10 border-bottom-0">
                <h5 class="mb-0 text-success">
                    <i class="bi bi-person-plus-fill me-2"></i>Step 1: Convert to New Convert
                </h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">
                                <i class="bi bi-person me-2"></i>Full Name *
                            </label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($visitor['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label fw-semibold">
                                <i class="bi bi-building me-2"></i>Department
                            </label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Select Department (Optional)</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">
                                <i class="bi bi-telephone me-2"></i>Phone Number
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($visitor['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">
                                <i class="bi bi-envelope me-2"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($visitor['email']) ?>">
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label fw-semibold">
                                <i class="bi bi-journal-text me-2"></i>Notes
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Any additional notes about this new convert..."></textarea>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to List
                        </a>
                        <button type="submit" name="convert_to_new_convert" class="btn btn-convert">
                            <i class="bi bi-person-plus-fill me-2"></i>Convert to New Convert
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Existing New Converts -->
        <?php if (!empty($existing_converts)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning bg-opacity-10 border-bottom-0">
                <h6 class="mb-0 text-warning">
                    <i class="bi bi-people-fill me-2"></i>Existing New Converts
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($existing_converts as $convert): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($convert['name']) ?></h6>
                            <small class="text-muted">
                                Converted on <?= date('M d, Y', strtotime($convert['date_converted'])) ?>
                            </small>
                        </div>
                        <?php if ($convert['status'] == 'active'): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-primary">Member</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($convert['status'] == 'active'): ?>
                    <!-- Convert to Member Form -->
                    <form method="POST" action="" class="mt-3">
                        <input type="hidden" name="convert_id" value="<?= $convert['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Congregation Group</label>
                            <select class="form-select form-select-sm" name="congregation_group" required>
                                <option value="adult">Adult</option>
                                <option value="youth">Youth</option>
                                <option value="children">Children</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Baptized Status</label>
                            <select class="form-select form-select-sm" name="baptized">
                                <option value="no">Not Baptized</option>
                                <option value="yes">Baptized</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Additional Notes</label>
                            <textarea class="form-control form-control-sm" name="member_notes" rows="2" 
                                      placeholder="Member notes..."></textarea>
                        </div>
                        
                        <button type="submit" name="convert_to_member" class="btn btn-member btn-sm w-100">
                            <i class="bi bi-arrow-up-circle me-2"></i>Convert to Full Member
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Conversion Process Info -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-info bg-opacity-10 border-bottom-0">
                <h6 class="mb-0 text-info">
                    <i class="bi bi-info-circle-fill me-2"></i>Conversion Process
                </h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary rounded-circle p-2 me-3">
                        <i class="bi bi-1-circle-fill text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Visitor</h6>
                        <small class="text-muted">Initial visit recorded</small>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success rounded-circle p-2 me-3">
                        <i class="bi bi-2-circle-fill text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">New Convert</h6>
                        <small class="text-muted">Expressed interest in faith</small>
                    </div>
                </div>
                
                <div class="d-flex align-items-center">
                    <div class="bg-warning rounded-circle p-2 me-3">
                        <i class="bi bi-3-circle-fill text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Full Member</h6>
                        <small class="text-muted">Complete church membership</small>
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="alert alert-light mb-0">
                    <small class="text-muted">
                        <i class="bi bi-lightbulb me-1"></i>
                        The conversion process allows for proper discipleship and integration into church community.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<?php include '../../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fill phone and email if visitor has them
    const visitorPhone = "<?= addslashes($visitor['phone']) ?>";
    const visitorEmail = "<?= addslashes($visitor['email']) ?>";
    
    if (visitorPhone) {
        document.getElementById('phone').value = visitorPhone;
    }
    if (visitorEmail) {
        document.getElementById('email').value = visitorEmail;
    }
});
</script>