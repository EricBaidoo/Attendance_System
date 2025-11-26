<?php
// Database migration to create new_converts table
require '../config/database.php';

try {
    // Create new_converts table
    $create_table_sql = "
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
    
    $pdo->exec($create_table_sql);
    echo "✅ new_converts table created successfully!\n";
    
    // Add converted_date column to visitors table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE visitors ADD COLUMN converted_date DATE NULL");
        echo "✅ converted_date column added to visitors table!\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️  converted_date column already exists in visitors table\n";
        } else {
            echo "❌ Error adding converted_date column: " . $e->getMessage() . "\n";
        }
    }
    
    // Update visitors table status enum if needed
    try {
        $pdo->exec("ALTER TABLE visitors MODIFY COLUMN status ENUM('pending', 'contacted', 'follow_up_needed', 'converted', 'converted_to_convert', 'inactive') DEFAULT 'pending'");
        echo "✅ Updated visitors status enum with new convert option!\n";
    } catch (Exception $e) {
        echo "❌ Error updating visitors status enum: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎉 Database migration completed successfully!\n";
    echo "You can now use the new convert functionality.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>