<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

$stmt = $conn->prepare("SELECT session_id, status, created_at FROM whatsapp_sessions WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

echo json_encode($sessions);
?>
