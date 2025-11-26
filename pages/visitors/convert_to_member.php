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
    
    // Get visitor information
    $visitor_stmt = $pdo->prepare("SELECT * FROM visitors WHERE id = ?");
    $visitor_stmt->execute([$visitor_id]);
    $visitor = $visitor_stmt->fetch();
    
    if (!$visitor) {
        echo json_encode(['success' => false, 'message' => 'Visitor not found']);
        exit;
    }
    
    // Check if visitor is already converted or has a duplicate member record
    if (!empty($visitor['phone'])) {
        $check_phone_stmt = $pdo->prepare("SELECT id FROM members WHERE phone = ?");
        $check_phone_stmt->execute([$visitor['phone']]);
        if ($check_phone_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A member with this phone number already exists']);
            exit;
        }
    }
    
    if (!empty($visitor['email'])) {
        $check_email_stmt = $pdo->prepare("SELECT id FROM members WHERE email = ?");
        $check_email_stmt->execute([$visitor['email']]);
        if ($check_email_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A member with this email already exists']);
            exit;
        }
    }
    
    $pdo->beginTransaction();
    
    // Create new member record
    $member_sql = "INSERT INTO members (name, phone, email, status, date_joined, created_at, congregation_group) 
                   VALUES (?, ?, ?, 'active', CURDATE(), NOW(), 'adult')";
    $member_stmt = $pdo->prepare($member_sql);
    $member_result = $member_stmt->execute([
        $visitor['name'],
        $visitor['phone'],
        $visitor['email']
    ]);
    
    if (!$member_result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to create member record']);
        exit;
    }
    
    $new_member_id = $pdo->lastInsertId();
    
    // Update visitor status to converted
    $update_visitor_sql = "UPDATE visitors SET status = 'converted', 
                          notes = CONCAT(COALESCE(notes, ''), '\nConverted to member on ', NOW()),
                          converted_date = CURDATE()
                          WHERE id = ?";
    $update_result = $pdo->prepare($update_visitor_sql)->execute([$visitor_id]);
    
    if (!$update_result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update visitor status']);
        exit;
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
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>