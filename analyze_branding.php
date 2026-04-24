<?php
require 'config/database.php';
$stmt = $pdo->query("SELECT setting_key, setting_value, category FROM system_settings");
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);
$output = "LOG START\n";
foreach($all as $r) {
    if(strpos($r['setting_key'], 'church') !== false || strpos($r['setting_key'], 'inst') !== false || strpos($r['category'], 'brand') !== false) {
        $output .= "KEY: {$r['setting_key']} | VAL: {$r['setting_value']} | CAT: {$r['category']}\n";
    }
}
file_put_contents('branding_report.txt', $output);
?>
