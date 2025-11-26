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

if (!isset($input['member_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$member_id = $input['member_id'];
$new_status = $input['status'];

// Validate status
if (!in_array($new_status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

try {
    require '../../config/database.php';
    
    // Check if member exists
    $check_stmt = $pdo->prepare("SELECT id, name FROM members WHERE id = ?");
    $check_stmt->execute([$member_id]);
    $member = $check_stmt->fetch();
    
    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }
    
    // Update member status
    $update_stmt = $pdo->prepare("UPDATE members SET status = ? WHERE id = ?");
    $result = $update_stmt->execute([$new_status, $member_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Status updated successfully',
            'member_name' => $member['name'],
            'new_status' => $new_status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>