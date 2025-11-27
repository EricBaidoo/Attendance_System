<?php
// Database Change Generator
// This script helps create SQL update scripts for hosting deployment

class DatabaseUpdateGenerator {
    private $pdo;
    private $update_file = 'database/hosting_updates.sql';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Generate SQL for adding a new table
    public function addNewTable($table_name, $description = '') {
        try {
            $result = $this->pdo->query("SHOW CREATE TABLE $table_name");
            $row = $result->fetch(PDO::FETCH_ASSOC);
            
            $sql = "\n-- UPDATE: Add new table '$table_name'\n";
            $sql .= "-- DATE: " . date('Y-m-d') . "\n";
            $sql .= "-- DESCRIPTION: $description\n\n";
            $sql .= $row['Create Table'] . ";\n\n";
            
            return $sql;
        } catch (Exception $e) {
            return "-- ERROR: Could not generate CREATE statement for $table_name\n";
        }
    }
    
    // Generate SQL for adding new columns
    public function addNewColumn($table_name, $column_info, $description = '') {
        $sql = "\n-- UPDATE: Add column to '$table_name'\n";
        $sql .= "-- DATE: " . date('Y-m-d') . "\n";
        $sql .= "-- DESCRIPTION: $description\n\n";
        $sql .= "ALTER TABLE $table_name ADD COLUMN $column_info;\n\n";
        
        return $sql;
    }
    
    // Generate SQL for modifying columns
    public function modifyColumn($table_name, $column_modification, $description = '') {
        $sql = "\n-- UPDATE: Modify column in '$table_name'\n";
        $sql .= "-- DATE: " . date('Y-m-d') . "\n";
        $sql .= "-- DESCRIPTION: $description\n\n";
        $sql .= "ALTER TABLE $table_name MODIFY COLUMN $column_modification;\n\n";
        
        return $sql;
    }
    
    // Generate SQL for new data insertions
    public function addNewData($table_name, $data_description = '') {
        $sql = "\n-- UPDATE: Insert new data into '$table_name'\n";
        $sql .= "-- DATE: " . date('Y-m-d') . "\n";
        $sql .= "-- DESCRIPTION: $data_description\n\n";
        $sql .= "-- Add your INSERT statements here\n\n";
        
        return $sql;
    }
    
    // Get current database structure for comparison
    public function getDatabaseStructure() {
        try {
            $tables = [];
            $result = $this->pdo->query("SHOW TABLES");
            
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $table_name = $row[0];
                
                // Get table structure
                $desc_result = $this->pdo->query("DESCRIBE $table_name");
                $columns = $desc_result->fetchAll(PDO::FETCH_ASSOC);
                
                // Get record count
                $count_result = $this->pdo->query("SELECT COUNT(*) as count FROM $table_name");
                $count = $count_result->fetch(PDO::FETCH_ASSOC)['count'];
                
                $tables[$table_name] = [
                    'columns' => $columns,
                    'record_count' => $count
                ];
            }
            
            return $tables;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    // Save update to the tracking file
    public function saveUpdate($update_sql, $version = null) {
        if (!$version) {
            $version = date('Y.m.d.H.i');
        }
        
        $header = "\n-- =============================================================================\n";
        $header .= "-- UPDATE VERSION: $version\n";
        $header .= "-- GENERATED: " . date('Y-m-d H:i:s') . "\n";
        $header .= "-- =============================================================================\n";
        
        $full_update = $header . $update_sql;
        
        file_put_contents($this->update_file, $full_update, FILE_APPEND);
        
        return "Update saved to {$this->update_file}";
    }
}

// Example usage (commented out):
/*
require_once 'config/database.php';

$generator = new DatabaseUpdateGenerator($pdo);

// Example: Add new table
$sql = $generator->addNewTable('new_table_name', 'Description of what this table does');
echo $generator->saveUpdate($sql, '1.0.1');

// Example: Add new column
$sql = $generator->addNewColumn('members', 'middle_name VARCHAR(50) AFTER name', 'Added middle name field for members');
echo $generator->saveUpdate($sql, '1.0.2');
*/
?>