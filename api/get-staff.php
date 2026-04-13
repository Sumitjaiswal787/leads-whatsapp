<?php
require_once '../config/config.php';
checkAuth(); // Admin or Staff can call this depending on context, but here it's for Admin
$tenant_id = getTenantId();

$stmt = $conn->prepare("SELECT id, username, full_name, created_at FROM users WHERE tenant_id = ? AND role = 'staff'");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

$staff = [];
while ($row = $result->fetch_assoc()) {
    $staff[] = $row;
}

echo json_encode($staff);
?>
