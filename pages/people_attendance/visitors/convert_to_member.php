<?php
require_once '../../../includes/security.php';
require_once '../../../includes/people_sync.php';
header('Content-Type: application/json');

// Authentication check
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = getUserRole();
if (!canAccessModule('people_attendance', $user_role)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['visitor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing visitor ID']);
    exit;
}

$visitor_id = $input['visitor_id'];

try {
    require '../../../config/database.php';

    $visitor_status_member = 'converted_to_member';

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

    $has_visitors_notes = $hasColumn('visitor_roles', 'notes');
    $has_visitors_converted_date = $hasColumn('visitor_roles', 'converted_date');
    $has_visitors_became_member = $hasColumn('visitor_roles', 'became_member');
    $has_members_created_at = $hasColumn('member_roles', 'created_at');

    $visitor_status_values = $getEnumValues('visitor_roles', 'status');
    if (!empty($visitor_status_values)) {
        foreach (['converted_to_member', 'converted', 'contacted', 'active', 'pending'] as $candidate) {
            if (in_array($candidate, $visitor_status_values, true)) {
                $visitor_status_member = $candidate;
                break;
            }
        }
    }
    
    // Get visitor information
    $visitor_stmt = $pdo->prepare("SELECT * FROM visitor_roles WHERE id = ?");
    $visitor_stmt->execute([$visitor_id]);
    $visitor = $visitor_stmt->fetch();
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'message' => 'Visitor not found']);
        exit;
    }
    
    // Check if visitor is already converted or has a duplicate member record
    if (!empty($visitor['phone'])) {
        $check_phone_stmt = $pdo->prepare("SELECT id FROM member_roles WHERE phone = ?");
        $check_phone_stmt->execute([$visitor['phone']]);
        if ($check_phone_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A member with this phone number already exists']);
            exit;
        }
    }
    
    if (!empty($visitor['email'])) {
        $check_email_stmt = $pdo->prepare("SELECT id FROM member_roles WHERE email = ?");
        $check_email_stmt->execute([$visitor['email']]);
        if ($check_email_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A member with this email already exists']);
            exit;
        }
    }
    
    $pdo->beginTransaction();

    $person_id = !empty($visitor['person_id']) ? (int)$visitor['person_id'] : null;
    if (!$person_id) {
        $person_id = peopleFindOrCreateCompat(
            $pdo,
            (string)($visitor['name'] ?? ''),
            (string)($visitor['email'] ?? ''),
            (string)($visitor['phone'] ?? ''),
            'member'
        );
    }

    if (!$person_id) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not resolve person profile for conversion']);
        exit;
    }
    
    // Create new member record
    if ($has_members_created_at) {
        $member_sql = "INSERT INTO member_roles (person_id, name, phone, email, status, date_joined, created_at, congregation_group)
                       VALUES (?, ?, ?, ?, 'active', CURDATE(), NOW(), 'adult')";
    } else {
        $member_sql = "INSERT INTO member_roles (person_id, name, phone, email, status, date_joined, congregation_group)
                       VALUES (?, ?, ?, ?, 'active', CURDATE(), 'adult')";
    }
    $member_stmt = $pdo->prepare($member_sql);
    $member_result = $member_stmt->execute([
        $person_id,
        $visitor['name'],
        $visitor['phone'],
        $visitor['email']
    ]);
    
    if (!$member_result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to create member record']);
        exit;
    }
    
    $new_member_id = (int)$pdo->lastInsertId();
    
    // Update visitor status to converted
    $updates = ["status = ?"];
    $update_params = [$visitor_status_member];
    if ($has_visitors_became_member) {
        $updates[] = "became_member = 'yes'";
    }
    if ($has_visitors_notes) {
        $updates[] = "notes = CONCAT(COALESCE(notes, ''), '\nConverted to member on ', NOW())";
    }
    if ($has_visitors_converted_date) {
        $updates[] = "converted_date = CURDATE()";
    }

    $update_visitor_sql = "UPDATE visitor_roles SET " . implode(', ', $updates) . " WHERE id = ?";
    $update_params[] = $visitor_id;
    $update_result = $pdo->prepare($update_visitor_sql)->execute($update_params);
    
    if (!$update_result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update visitor status']);
        exit;
    }

    if ($person_id) {
        peopleRelinkRecord($pdo, 'visitors', (int)$visitor_id, $person_id);
        peopleRelinkRecord($pdo, 'members', $new_member_id, $person_id);
        peopleEnsureStage($pdo, $person_id, 'member');
        peopleAddLifecycleEvent($pdo, $person_id, 'became_member', 'member_roles', $new_member_id, 'Converted via convert_to_member endpoint');
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Visitor successfully converted to member',
        'visitor_name' => $visitor['name'],
        'member_id' => $new_member_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => reportDatabaseException($e)]);
}
?>