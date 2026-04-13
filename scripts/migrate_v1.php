<?php
require_once '../config/config.php';

echo "Starting Migration...<br>";

// 1. Update users role enum
$sql1 = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'staff', 'superadmin') DEFAULT 'admin'";
if ($conn->query($sql1)) {
    echo "1. Users role updated.<br>";
} else {
    echo "1. Error updating users role: " . $conn->error . "<br>";
}

// 2. Add is_trial to subscriptions
$sql2 = "ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS is_trial BOOLEAN DEFAULT FALSE AFTER status";
if ($conn->query($sql2)) {
    echo "2. Subscriptions table updated.<br>";
} else {
    echo "2. Error updating subscriptions: " . $conn->error . "<br>";
}

// 3. Insert default superadmin
$username = 'superadmin';
$password = password_hash('admin123', PASSWORD_BCRYPT);
$full_name = 'System Super Admin';
$role = 'superadmin';

$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
if ($check->get_result()->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $password, $full_name, $role);
    if ($stmt->execute()) {
        echo "3. Default Super Admin created (superadmin / admin123).<br>";
    } else {
        echo "3. Error creating superadmin: " . $conn->error . "<br>";
    }
} else {
    echo "3. Super Admin already exists.<br>";
}

echo "Migration Complete!";
?>
