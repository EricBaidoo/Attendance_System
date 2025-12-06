<?php
// Check what members exist in the database
require 'config/database.php';

echo "<h2>All Active Members in Database:</h2>";
echo "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid black; padding: 8px; text-align: left; }</style>";

try {
    $stmt = $pdo->query("SELECT id, name, phone, phone2, email, status FROM members WHERE status = 'active' ORDER BY id");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Total Active Members: " . count($members) . "</strong></p>";
    
    if (empty($members)) {
        echo "<p style='color: red;'>NO ACTIVE MEMBERS FOUND IN DATABASE!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Phone</th><th>Phone2</th><th>Email</th><th>Status</th></tr>";
        
        foreach ($members as $member) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($member['id']) . "</td>";
            echo "<td>" . htmlspecialchars($member['name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['phone'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['phone2'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['email'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($member['status']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    echo "<hr>";
    echo "<h2>Search Test for 'FRED ADOTEI':</h2>";
    
    $search_term = "FRED ADOTEI";
    $search_like = "%$search_term%";
    $search_clean = preg_replace('/[^0-9]/', '', $search_term);
    $search_clean_like = "%$search_clean%";
    
    $search_sql = "SELECT m.*, d.name as department_name 
                   FROM members m 
                   LEFT JOIN departments d ON m.department_id = d.id 
                   WHERE (
                       m.phone LIKE ? OR 
                       REPLACE(REPLACE(REPLACE(REPLACE(m.phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ? OR
                       m.phone2 LIKE ? OR 
                       REPLACE(REPLACE(REPLACE(REPLACE(m.phone2, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ? OR
                       m.name LIKE ?
                   )
                   AND m.status = 'active'
                   LIMIT 1";
    
    $stmt = $pdo->prepare($search_sql);
    $stmt->execute([$search_like, $search_clean_like, $search_like, $search_clean_like, $search_like]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<p style='color: green;'><strong>FOUND:</strong> " . htmlspecialchars($result['name']) . " (ID: " . $result['id'] . ")</p>";
    } else {
        echo "<p style='color: red;'><strong>NOT FOUND!</strong> No member matching 'FRED ADOTEI'</p>";
    }
    
    echo "<hr>";
    echo "<h2>All Members (Including Inactive):</h2>";
    $all_stmt = $pdo->query("SELECT id, name, phone, phone2, status FROM members ORDER BY id LIMIT 50");
    $all_members = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Phone</th><th>Phone2</th><th>Status</th></tr>";
    
    foreach ($all_members as $member) {
        $rowColor = $member['status'] !== 'active' ? 'background-color: #ffcccc;' : '';
        echo "<tr style='$rowColor'>";
        echo "<td>" . htmlspecialchars($member['id']) . "</td>";
        echo "<td>" . htmlspecialchars($member['name']) . "</td>";
        echo "<td>" . htmlspecialchars($member['phone'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($member['phone2'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($member['status']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p style='margin-top: 30px;'><a href='index.php'>Back to Dashboard</a> | <a href='pages/members/list.php'>Members List</a></p>";
?>
