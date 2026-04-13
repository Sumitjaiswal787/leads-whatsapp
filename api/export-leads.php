<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

$stmt = $conn->prepare("SELECT l.name, l.number, l.last_message, l.status, l.tag, l.created_at, u.full_name as assigned_to 
                        FROM leads l 
                        LEFT JOIN users u ON l.staff_id = u.id 
                        WHERE l.tenant_id = ? 
                        ORDER BY l.created_at DESC");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Name', 'Number', 'Last Message', 'Status', 'Tag', 'Created At', 'Assigned To']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
exit();
?>
