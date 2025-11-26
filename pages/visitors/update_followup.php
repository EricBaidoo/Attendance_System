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

if (!isset($input['visitor_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$visitor_id = $input['visitor_id'];
$action = $input['action'];

if ($action !== 'complete') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    require '../../config/database.php';
    
    // Check if visitor exists
    $check_stmt = $pdo->prepare("SELECT id, name FROM visitors WHERE id = ?");
    $check_stmt->execute([$visitor_id]);
    $visitor = $check_stmt->fetch();
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'message' => 'Visitor not found']);
        exit;
    }
    
    // Update follow-up status
    $update_stmt = $pdo->prepare("UPDATE visitors SET follow_up_completed = 'yes', follow_up_date = NOW() WHERE id = ?");
    $result = $update_stmt->execute([$visitor_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Follow-up marked as completed',
            'visitor_name' => $visitor['name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update follow-up status']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>