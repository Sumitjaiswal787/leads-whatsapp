<?php
require_once 'config/config.php';

$username = 'admin@test.com';
$password = 'admin123';

$stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    echo "Verification: SUCCESS\n";
} else {
    echo "Verification: FAILURE\n";
    if (!$user) echo "User not found\n";
    else echo "Password mismatch\n";
}
?>
