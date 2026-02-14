<?php
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['search'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$search = '%' . $_POST['search'] . '%';

try {
    // Search members by phone or name
    $stmt = $pdo->prepare("
        SELECT m.id, m.name, m.phone, 
               d.name as department_name,
               m.ministerial_status
        FROM members m 
        LEFT JOIN departments d ON m.department_id = d.id 
        WHERE (m.phone LIKE ? OR m.phone2 LIKE ? OR m.name LIKE ?)
        AND m.status = 'active'
        ORDER BY 
            CASE 
                WHEN m.phone = ? THEN 1
                WHEN m.phone LIKE ? THEN 2
                WHEN m.name LIKE ? THEN 3
                ELSE 4
            END,
            m.name ASC
        LIMIT 10
    ");

    $search_term = $_POST['search'];
    $stmt->execute([
        $search,           // phone LIKE
        $search,           // phone2 LIKE
        $search,           // name LIKE
        $search_term,      // exact phone match
        $search . '%',     // phone starts with
        $search . '%'      // name starts with
    ]);

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
