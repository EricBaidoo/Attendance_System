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

    $hasTable = function ($table) use ($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    };
    
    // Check if visitor exists and get their info
    $check_stmt = $pdo->prepare("SELECT id, name, person_id FROM visitor_roles WHERE id = ?");
    $check_stmt->execute([$visitor_id]);
    $visitor = $check_stmt->fetch();
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'message' => 'Visitor not found']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Delete dependent visitor records first (foreign key constraint).
    if ($hasTable('visitor_checkins')) {
        $delete_checkins_stmt = $pdo->prepare("DELETE FROM visitor_checkins WHERE visitor_id = ?");
        $delete_checkins_stmt->execute([$visitor_id]);
    }

    // Backward compatibility for older schemas that still contain this table.
    if ($hasTable('visitor_attendance')) {
        $delete_attendance_stmt = $pdo->prepare("DELETE FROM visitor_attendance WHERE visitor_id = ?");
        $delete_attendance_stmt->execute([$visitor_id]);
    }

    if (!empty($visitor['person_id'])) {
        peopleAddLifecycleEvent(
            $pdo,
            (int)$visitor['person_id'],
            'visitor_deleted',
            'visitor_roles',
            (int)$visitor_id,
            'Visitor record deleted'
        );
    }

    // Delete the visitor
    $delete_stmt = $pdo->prepare("DELETE FROM visitor_roles WHERE id = ?");
    $result = $delete_stmt->execute([$visitor_id]);
    
    if ($result && $delete_stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Visitor deleted successfully',
            'visitor_name' => $visitor['name']
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete visitor']);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Check if it's a foreign key constraint error
    if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete visitor with existing records. Please contact administrator.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => reportDatabaseException($e)]);
    }
}
?>