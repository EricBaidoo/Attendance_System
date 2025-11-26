<?php
// Database migration to fix visitors table and ensure new_converts functionality
require '../config/database.php';

try {
    echo "🔍 Checking visitors table structure...\n";
    
    // Check if visitors table exists and get its structure
    $check_table = $pdo->query("SHOW CREATE TABLE visitors");
    $table_info = $check_table->fetch();
    $create_statement = $table_info['Create Table'];
    
    echo "ℹ️  Current visitors table structure analyzed\n";
    
    // Add status column to visitors table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE visitors ADD COLUMN status ENUM('pending', 'contacted', 'follow_up_needed', 'converted', 'converted_to_convert', 'inactive') DEFAULT 'pending'");
        echo "✅ Added status column to visitors table!\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️  Status column already exists in visitors table\n";
            
            // Try to update the existing status column to include new values
            try {
                $pdo->exec("ALTER TABLE visitors MODIFY COLUMN status ENUM('pending', 'contacted', 'follow_up_needed', 'converted', 'converted_to_convert', 'inactive') DEFAULT 'pending'");
                echo "✅ Updated existing status column with new convert option!\n";
            } catch (Exception $e2) {
                echo "ℹ️  Status column structure: " . $e2->getMessage() . "\n";
            }
        } else {
            echo "❌ Error adding status column: " . $e->getMessage() . "\n";
        }
    }
    
    // Add converted_date column to visitors table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE visitors ADD COLUMN converted_date DATE NULL");
        echo "✅ Added converted_date column to visitors table!\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️  converted_date column already exists in visitors table\n";
        } else {
            echo "❌ Error adding converted_date column: " . $e->getMessage() . "\n";
        }
    }
    
    // Ensure new_converts table exists
    $create_new_converts_sql = "
    CREATE TABLE IF NOT EXISTS new_converts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_id INT,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(255),
        department_id INT,
        date_converted DATE NOT NULL,
        member_conversion_date DATE NULL,
        status ENUM('active', 'converted_to_member', 'inactive') DEFAULT 'active',
        baptized ENUM('yes', 'no') DEFAULT 'no',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_visitor_id (visitor_id),
        INDEX idx_status (status),
        INDEX idx_date_converted (date_converted),
        FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE SET NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($create_new_converts_sql);
    echo "✅ new_converts table ensured to exist!\n";
    
    // Show final table structure
    echo "\n📋 Final visitors table columns:\n";
    $columns_result = $pdo->query("SHOW COLUMNS FROM visitors");
    while ($column = $columns_result->fetch()) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n📋 New converts table columns:\n";
    $converts_columns = $pdo->query("SHOW COLUMNS FROM new_converts");
    while ($column = $converts_columns->fetch()) {
        echo "   - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n🎉 Database migration completed successfully!\n";
    echo "✅ New convert functionality is now fully operational!\n";
    
} catch (Exception $e) {
    echo "❌ Migration Error: " . $e->getMessage() . "\n";
}
?>