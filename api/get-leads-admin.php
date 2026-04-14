<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

// Fetch leads for this tenant, with optional date filtering
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';

$query = "
    SELECT 
        l.*, 
        u.full_name as staff_name 
    FROM leads l
    LEFT JOIN users u ON l.staff_id = u.id
    WHERE l.tenant_id = ?
";

if ($tenant_id === null) {
    $tenant_id = 1; // Default to tenant 1 for safety
}

$params = [$tenant_id];
$types = "i";

if ($startDate && $endDate) {
    $query .= " AND DATE(l.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$leads = [];
while ($row = $result->fetch_assoc()) {
    // Force string type for the number to prevent scientific notation in JSON/JS
    $row['number'] = (string)$row['number'];
    $leads[] = $row;
}

echo json_encode($leads);
?>
