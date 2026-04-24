<?php
require_once 'config/config.php';

$username = 'admin@test.com';
$password = 'admin123';
$full_name = 'Test Admin';
$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE password = VALUES(password)");
$stmt->bind_param("sss", $username, $hashed, $full_name);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id ?: $conn->query("SELECT id FROM users WHERE username = '$username'")->fetch_assoc()['id'];
    $conn->query("INSERT IGNORE INTO assign_queue (tenant_id) VALUES ($user_id)");
    echo "User $username created with password $password\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
