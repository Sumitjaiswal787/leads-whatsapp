<?php
require_once '../config/config.php';
checkAuth();
$tenant_id = getTenantId();

$lead_id = $_GET['lead_id'] ?? 0;

// Verify lead
$stmt_lead = $conn->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ?");
$stmt_lead->bind_param("ii", $lead_id, $tenant_id);
$stmt_lead->execute();
if ($stmt_lead->get_result()->num_rows === 0) {
    exit(json_encode([]));
}

$stmt = $conn->prepare("SELECT message, direction, created_at FROM whatsapp_messages WHERE lead_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode($messages);
?>
