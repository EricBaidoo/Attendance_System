<?php
// Script to update Test User to actual name
require 'config/database.php';

// Show current test users
echo "<h2>Current Members with 'Test User' name:</h2>";
$stmt = $pdo->query("SELECT id, name, phone, email, department_id FROM members WHERE name LIKE '%Test%' OR name LIKE '%test%'");
$test_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($test_users)) {
    echo "<p>No test users found.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Action</th></tr>";
    foreach ($test_users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['phone'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($user['email'] ?? 'N/A') . "</td>";
        echo "<td>";
        echo "<form method='POST' style='display:inline;'>";
        echo "<input type='hidden' name='member_id' value='" . $user['id'] . "'>";
        echo "New Name: <input type='text' name='new_name' required placeholder='Enter real name'>";
        echo "<button type='submit' name='update'>Update</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $member_id = $_POST['member_id'] ?? '';
    $new_name = trim($_POST['new_name'] ?? '');
    
    if ($member_id && $new_name) {
        try {
            $update_stmt = $pdo->prepare("UPDATE members SET name = ? WHERE id = ?");
            $update_stmt->execute([$new_name, $member_id]);
            
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<strong>Success!</strong> Member ID $member_id has been updated to: " . htmlspecialchars($new_name);
            echo "</div>";
            echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
            echo "<strong>Error!</strong> " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }
}

echo "<hr>";
echo "<h2>All Active Members:</h2>";
$all_stmt = $pdo->query("SELECT id, name, phone, email FROM members WHERE status = 'active' ORDER BY name LIMIT 20");
$all_members = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th></tr>";
foreach ($all_members as $member) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($member['id']) . "</td>";
    echo "<td>" . htmlspecialchars($member['name']) . "</td>";
    echo "<td>" . htmlspecialchars($member['phone'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($member['email'] ?? 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p style='margin-top: 30px;'><a href='pages/members/list.php'>Go to Members List</a> | <a href='index.php'>Go to Dashboard</a></p>";
?>
