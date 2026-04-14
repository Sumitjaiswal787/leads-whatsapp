<?php
require_once '../config/config.php';

// Disable standard error silencers for this script
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Lead Capture Debugger</h1>";

// 1. Session Info
echo "<h3>1. Session Information</h3>";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
$tenant_id = getTenantId();
echo "Detected Tenant ID: " . ($tenant_id === null ? 'NULL (Problem)' : $tenant_id) . "<br>";

// 2. Database Structure Check
echo "<h3>2. Database Structure Check (Leads Table)</h3>";
$res = $conn->query("DESCRIBE leads");
if ($res) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Error: Could not describe leads table. Does it exist?</p>";
}

// 3. Raw Data Count
echo "<h3>3. Data Count</h3>";
$count_all = $conn->query("SELECT COUNT(*) as total FROM leads")->fetch_assoc()['total'];
$count_tenant = $conn->query("SELECT COUNT(*) as total FROM leads WHERE tenant_id = '$tenant_id'")->fetch_assoc()['total'];

echo "Total Leads in DB (Overall): $count_all<br>";
echo "Total Leads for Current Tenant ($tenant_id): $count_tenant<br>";

// 4. Session & Queue Check
echo "<h3>4. WhatsApp Sessions & Queue Check</h3>";
$res_sessions = $conn->query("SELECT * FROM whatsapp_sessions WHERE tenant_id = '$tenant_id'");
if ($res_sessions && $res_sessions->num_rows > 0) {
    echo "Sessions found: <br><pre>";
    while ($row = $res_sessions->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color:red'>No WhatsApp Sessions found in DB for your account! You need to scan the QR code first.</p>";
}

$res_queue = $conn->query("SELECT * FROM assign_queue WHERE tenant_id = '$tenant_id'");
if ($res_queue && $res_queue->num_rows > 0) {
    echo "Assign Queue is ready.<br>";
} else {
    echo "<p style='color:orange'>Warning: Assign Queue not found. Round-robin assignment might fail.</p>";
}

// 5. Callback Activity Log
echo "<h3>5. Callback Activity Log (Railway Connectivity)</h3>";
if (file_exists('callback_log.txt')) {
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;'>";
    echo file_get_contents('callback_log.txt');
    echo "</pre>";
} else {
    echo "<p>No activity recorded yet. This means Railway has not sent any data to this server.</p>";
}

// 6. Sample Data
echo "<h3>6. Sample Data (Last 5 leads)</h3>";
$res = $conn->query("SELECT * FROM leads ORDER BY id DESC LIMIT 5");
if ($res && $res->num_rows > 0) {
    echo "<pre>";
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "No leads found in table.";
}
?>
