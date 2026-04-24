<?php
require_once '../../includes/security.php';
requireLogin('../../login');
require_once '../../config/database.php';

echo "<h2>Starting Finance Module Database Migration...</h2>";

try {
    // 1. Update tithers table
    $columns = [
        "tither_type" => "ALTER TABLE tithers ADD COLUMN tither_type ENUM('individual', 'company') DEFAULT 'individual' AFTER member_id",
        "age_group"   => "ALTER TABLE tithers ADD COLUMN age_group ENUM('adult', 'youth', 'child') DEFAULT 'adult' AFTER tither_type",
        "company_tin" => "ALTER TABLE tithers ADD COLUMN company_tin VARCHAR(50) NULL AFTER full_name",
        "status"      => "ALTER TABLE tithers ADD COLUMN status ENUM('active', 'inactive', 'retired') DEFAULT 'active' AFTER tithe_book_id",
        "start_date"  => "ALTER TABLE tithers ADD COLUMN start_date DATE NULL AFTER status"
    ];

    foreach ($columns as $col => $sql) {
        $check = $pdo->query("SHOW COLUMNS FROM tithers LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Added column: <strong>$col</strong></p>";
        } else {
            echo "<p style='color: gray;'>ℹ️ Column <strong>$col</strong> already exists.</p>";
        }
    }

    // 2. Update tithe_books table for status support
    $check_book_status = $pdo->query("SHOW COLUMNS FROM tithe_books LIKE 'status'")->fetch();
    if ($check_book_status) {
        // Ensure status enum includes 'retired'
        $pdo->exec("ALTER TABLE tithe_books MODIFY COLUMN status ENUM('available', 'assigned', 'retired', 'lost') DEFAULT 'available'");
        echo "<p style='color: green;'>✅ Updated tithe_books status enum to include 'retired'.</p>";
    }

    echo "<h3>🎉 Migration Complete! You can now delete this file.</h3>";
    echo "<a href='tithers' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Return to Tithers</a>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ ERROR: " . $e->getMessage() . "</p>";
}
?>
