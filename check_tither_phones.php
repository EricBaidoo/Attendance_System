<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENABLE_MAINTENANCE_TOOLS') !== '1') {
    http_response_code(403);
    exit('Forbidden');
}

require_once 'config/database.php';

try {
    $stmt = $pdo->query(
        "SELECT id, full_name, phone, member_id FROM tithers ORDER BY full_name ASC LIMIT 20"
    );
    $tithers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== TITHERS PHONE NUMBERS IN DATABASE ===\n\n";
    
    foreach ($tithers as $tither) {
        $phone = $tither['phone'] ?? 'NULL';
        echo "ID: {$tither['id']}\n";
        echo "Name: {$tither['full_name']}\n";
        echo "Phone: $phone\n";
        
        if (!empty($phone)) {
            // Show what normalization would do
            $digits = preg_replace('/[^0-9+]/', '', $phone);
            $digits = ltrim($digits, '+');
            if (strpos($digits, '233') === 0) {
                $digits = substr($digits, 3);
            }
            if (strpos($digits, '0') === 0) {
                $digits = substr($digits, 1);
            }
            echo "Normalized: $digits\n";
            echo "Length: " . strlen($digits) . " digits\n";
        }
        echo "---\n\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
