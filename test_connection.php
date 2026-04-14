<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Detailed Database Diagnostic</h1>";

$host = 'localhost';
$user = 'u828453283_whats';
$pass = 'Sumit@787870';
$db   = 'u828453283_whats';

echo "Attempting to connect to <b>$db</b>...<br>";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("<h2 style='color:red'>❌ Connection Failed: " . $conn->connect_error . "</h2>");
    }
    
    echo "<h2 style='color:green'>✅ Database Connected!</h2>";
    
    // Check tables
    $tables = ['users', 'leads', 'plans', 'subscriptions'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✅ Table <b>$table</b> exists.<br>";
        } else {
            echo "<b style='color:red'>❌ Table '$table' is MISSING!</b><br>";
        }
    }
    
    // Check for at least one superadmin
    $res = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'superadmin'");
    if ($res) {
        $row = $res->fetch_assoc();
        echo "Users found: " . $row['count'] . "<br>";
    }

    $conn->close();
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Error: " . $e->getMessage() . "</h2>";
}
?>
