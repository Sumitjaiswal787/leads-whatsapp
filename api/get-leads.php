<?php
require_once '../config/config.php';
checkAuth();
$tenant_id = getTenantId();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Filter by staff if not admin
$sql = "SELECT l.*, s.session_id as session_logical_id 
        FROM leads l 
        JOIN whatsapp_sessions s ON l.session_id = s.id 
        WHERE l.tenant_id = ?";
$params = [$tenant_id];
$types = "i";

if ($role === 'staff') {
    $sql .= " AND l.staff_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$sql .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$leads = [];
while ($row = $result->fetch_assoc()) {
    $leads[] = $row;
}

echo json_encode($leads);
?>
