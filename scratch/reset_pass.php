<?php
require_once 'config/config.php';
$pass = password_hash("admin123", PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin_test'");
$stmt->bind_param("s", $pass);
if ($stmt->execute()) {
    echo "Password reset successfully for admin_test";
} else {
    echo "Error resetting password: " . $conn->error;
}
?>
