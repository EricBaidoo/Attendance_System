<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENABLE_MAINTENANCE_TOOLS') !== '1') {
    http_response_code(403);
    exit('Forbidden');
}

require 'config/database.php';

echo "Checking tither phone numbers...\n";
echo "================================\n\n";

$stmt = $pdo->query("
    SELECT id, full_name, phone, member_id, tithe_book_id 
    FROM tithers 
    WHERE full_name LIKE '%eric%' OR full_name LIKE '%baidoo%'
    LIMIT 10
");

$tithers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tithers)) {
    echo "No tithers found with 'Eric' or 'Baidoo' in name\n";
    echo "\nAll tithers:\n";
    $all = $pdo->query("SELECT id, full_name, phone FROM tithers LIMIT 20");
    foreach ($all as $row) {
        echo "ID: {$row['id']}, Name: {$row['full_name']}, Phone: {$row['phone']}\n";
    }
} else {
    foreach ($tithers as $row) {
        echo "ID: {$row['id']}\n";
        echo "Name: {$row['full_name']}\n";
        echo "Phone (raw): {$row['phone']}\n";
        
        // Normalize phone
        $digits = preg_replace('/[^0-9+]/', '', $row['phone']);
        $digits = trim($digits);
        if (strpos($digits, '0') === 0) {
            $normalized = '+233' . ltrim(substr($digits, 1), '0');
        } elseif (strpos($digits, '233') === 0) {
            $normalized = '+' . $digits;
        } elseif (strpos($digits, '+') === 0) {
            $normalized = $digits;
        } else {
            $normalized = '+' . ltrim($digits, '+');
        }
        echo "Phone (normalized): $normalized\n";
        
        // Check if member linked
        if ($row['member_id']) {
            $m_stmt = $pdo->prepare("SELECT p.phone FROM member_roles mr JOIN people p ON mr.person_id = p.id WHERE mr.id = ?");
            $m_stmt->execute([$row['member_id']]);
            $member = $m_stmt->fetch();
            if ($member && $member['phone']) {
                echo "Member phone fallback: {$member['phone']}\n";
            }
        }
        echo "\n";
    }
}
?>
