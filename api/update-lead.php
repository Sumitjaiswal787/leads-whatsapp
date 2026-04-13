<?php
require_once '../config/config.php';
checkAuth();
$tenant_id = getTenantId();

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? 0;
    $status = $_POST['status'] ?? null;
    $tag = $_POST['tag'] ?? null;

    $sql = "UPDATE leads SET ";
    $params = [];
    $types = "";

    if ($status) {
        $sql .= "status = ?, ";
        $params[] = $status;
        $types .= "s";
    }
    if ($tag) {
        $sql .= "tag = ?, ";
        $params[] = $tag;
        $types .= "s";
    }

    $sql = rtrim($sql, ", ") . " WHERE id = ? AND tenant_id = ?";
    $params[] = $lead_id;
    $params[] = $tenant_id;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $response = ['status' => 'success', 'message' => 'Lead updated'];
    }
}

echo json_encode($response);
?>
