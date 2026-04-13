<?php
require_once '../config/config.php';

echo "Starting Migration V2...<br>";

// 1. Update subscriptions status enum
$sql = "ALTER TABLE subscriptions MODIFY COLUMN status ENUM('active', 'expired', 'pending', 'suspended') DEFAULT 'active'";
if ($conn->query($sql)) {
    echo "1. Subscriptions status enum updated to include 'suspended'.<br>";
} else {
    echo "1. Error updating subscriptions status: " . $conn->error . "<br>";
}

echo "Migration Complete!";
?>
