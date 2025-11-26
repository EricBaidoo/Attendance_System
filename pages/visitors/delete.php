<?php
session_start();
header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_role = $_SESSION['role'] ?? 'member';
if (!in_array($user_role, ['admin', 'staff'])) {
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
    require '../../config/database.php';
    
    // Check if visitor exists and get their info
    $check_stmt = $pdo->prepare("SELECT id, name FROM visitors WHERE id = ?");
    $check_stmt->execute([$visitor_id]);
    $visitor = $check_stmt->fetch();
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'message' => 'Visitor not found']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Delete visitor attendance records first (foreign key constraint)
    $delete_attendance_stmt = $pdo->prepare("DELETE FROM visitor_attendance WHERE visitor_id = ?");
    $delete_attendance_stmt->execute([$visitor_id]);
    
    // Delete the visitor
    $delete_stmt = $pdo->prepare("DELETE FROM visitors WHERE id = ?");
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
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>