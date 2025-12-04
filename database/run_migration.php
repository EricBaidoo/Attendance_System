<?php
// Set up environment for CLI
$_SERVER['HTTP_HOST'] = 'localhost';

include_once '../config/database.php';

echo "Starting database migration...\n";

try {
    // Check current schema
    echo "Current schema before migration:\n";
    $result = $pdo->query("DESCRIBE members")->fetchAll();
    foreach ($result as $row) {
        if (strpos($row['Field'], 'phone') !== false) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
    
    echo "\n";
    
    // Add phone2 field first
    try {
        $pdo->exec("ALTER TABLE members ADD COLUMN phone2 VARCHAR(20) DEFAULT NULL AFTER phone");
        echo "✓ Added phone2 column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "- phone2 column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Rename phone to phone1
    try {
        $pdo->exec("ALTER TABLE members CHANGE COLUMN phone phone1 VARCHAR(20) NOT NULL");
        echo "✓ Renamed phone column to phone1\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Unknown column 'phone'") !== false) {
            echo "- phone column already renamed to phone1\n";
        } else {
            throw $e;
        }
    }
    
    // Create indexes for better performance
    try {
        $pdo->exec("CREATE INDEX idx_members_phone1 ON members(phone1)");
        echo "✓ Created index on phone1\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "- Index on phone1 already exists\n";
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_members_phone2 ON members(phone2)");
        echo "✓ Created index on phone2\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "- Index on phone2 already exists\n";
        }
    }
    
    echo "\nFinal schema after migration:\n";
    $result = $pdo->query("DESCRIBE members")->fetchAll();
    foreach ($result as $row) {
        if (strpos($row['Field'], 'phone') !== false) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ") " . 
                 ($row['Null'] == 'YES' ? '[NULL]' : '[NOT NULL]') . "\n";
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
?>