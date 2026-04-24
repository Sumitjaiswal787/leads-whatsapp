<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

$staff_id = $_POST['staff_id'] ?? 0;
$status = $_POST['status'] ?? 0; // 1 to enable, 0 to disable

if (!$staff_id) {
    echo json_encode(['status' => 'error', 'message' => 'Staff ID is required']);
    exit;
}

// Verify this staff belongs to this tenant
$stmt = $conn->prepare("UPDATE users SET is_active_for_leads = ? WHERE id = ? AND tenant_id = ? AND role = 'staff'");
$stmt->bind_param("iii", $status, $staff_id, $tenant_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Staff assignment status updated']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status']);
}
?>
