<?php
/**
 * Database Update Generator - Example Usage
 * 
 * INSTRUCTIONS:
 * 1. Make your database changes locally (add tables, columns, etc.)
 * 2. Use the methods below to generate SQL for hosting
 * 3. The SQL will be saved to 'database/hosting_updates.sql'
 * 4. Copy that SQL and run it on your hosting database
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/update_generator.php';

$generator = new DatabaseUpdateGenerator($pdo);

echo "<h2>Database Update Generator</h2>";
echo "<p>Use the examples below to generate hosting update scripts.</p>";
echo "<hr>";

// =============================================================================
// EXAMPLE 1: Add a new table
// =============================================================================
/*
$sql = $generator->addNewTable('event_registrations', 'Tracks member event registrations');
echo "<h3>Generated SQL for New Table:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";
echo $generator->saveUpdate($sql, '1.0.1');
*/

// =============================================================================
// EXAMPLE 2: Add a new column to existing table
// =============================================================================
/*
$sql = $generator->addNewColumn(
    'members',                              // Table name
    'middle_name VARCHAR(50) AFTER name',   // Column definition with position
    'Added middle name field for members'   // Description
);
echo "<h3>Generated SQL for New Column:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";
echo $generator->saveUpdate($sql, '1.0.2');
*/

// =============================================================================
// EXAMPLE 3: Modify an existing column
// =============================================================================
/*
$sql = $generator->modifyColumn(
    'members',
    'phone VARCHAR(20) NOT NULL',
    'Made phone number required'
);
echo "<h3>Generated SQL for Column Modification:</h3>";
echo "<pre>" . htmlspecialchars($sql) . "</pre>";
echo $generator->saveUpdate($sql, '1.0.3');
*/

// =============================================================================
// EXAMPLE 4: Multiple changes in one update
// =============================================================================
/*
$version = '1.1.0';
$combined_sql = '';

// Add multiple columns
$combined_sql .= $generator->addNewColumn('members', 'linkedin_profile VARCHAR(255) AFTER email', 'LinkedIn profile URL');
$combined_sql .= $generator->addNewColumn('members', 'twitter_handle VARCHAR(100) AFTER linkedin_profile', 'Twitter handle');

// Modify a column
$combined_sql .= $generator->modifyColumn('services', 'description TEXT NULL', 'Made description optional');

echo "<h3>Combined Update (Multiple Changes):</h3>";
echo "<pre>" . htmlspecialchars($combined_sql) . "</pre>";
echo $generator->saveUpdate($combined_sql, $version);
*/

// =============================================================================
// VIEW DATABASE STRUCTURE
// =============================================================================
echo "<h3>Current Database Structure:</h3>";
echo "<style>table { border-collapse: collapse; width: 100%; margin: 10px 0; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f2f2f2; }</style>";

$structure = $generator->getDatabaseStructure();

foreach ($structure as $table_name => $info) {
    echo "<h4>Table: $table_name <small>(Records: {$info['record_count']})</small></h4>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($info['columns'] as $col) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Uncomment one of the examples above based on what you changed</li>";
echo "<li>Refresh this page to generate the SQL</li>";
echo "<li>Check <code>database/hosting_updates.sql</code> for the generated script</li>";
echo "<li>Copy and run that SQL on your hosting database</li>";
echo "</ol>";
?>
