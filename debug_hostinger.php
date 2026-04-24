<?php
// Temporary debug script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>System Diagnostic</h1>";

echo "<h2>1. Environment Check</h2>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "<br>";
echo "PHP SAPI: " . php_sapi_name() . "<br>";

require_once 'config/config.php';

echo "<h2>2. Database Check</h2>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";

if ($conn->connect_error) {
    echo "<b style='color:red'>❌ Connection Failed: " . $conn->connect_error . "</b>";
} else {
    echo "<b style='color:green'>✅ Database Connected Successfully!</b>";
    
    // Check if 'users' table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "<br>✅ 'users' table exists.";
    } else {
        echo "<br><b style='color:red'>❌ 'users' table MISSING!</b>";
    }
}

echo "<h2>3. Constants</h2>";
echo "BASE_URL: " . BASE_URL . "<br>";
echo "BACKEND_URL: " . BACKEND_URL . "<br>";

echo "<h2>4. mysqli Presence</h2>";
if (extension_loaded('mysqli')) {
    echo "✅ mysqli extension is loaded.";
} else {
    echo "❌ mysqli extension is NOT loaded.";
}
?>
