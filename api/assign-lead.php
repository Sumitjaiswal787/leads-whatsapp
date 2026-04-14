<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? 0;
    $staff_id = $_POST['staff_id'] ?? null;

    // Verify lead belongs to tenant
    $stmt_verify = $conn->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ?");
    $stmt_verify->bind_param("ii", $lead_id, $tenant_id);
    $stmt_verify->execute();
    if ($stmt_verify->get_result()->num_rows > 0) {
        
        $stmt_assign = $conn->prepare("UPDATE leads SET staff_id = ? WHERE id = ?");
        $stmt_assign->bind_param("ii", $staff_id, $lead_id);
        
        if ($stmt_assign->execute()) {
            $response = ['status' => 'success', 'message' => 'Lead assigned successfully'];
        } else {
            $response['message'] = 'Failed to assign lead';
        }
    } else {
        $response['message'] = 'Lead not found or unauthorized';
    }
}

echo json_encode($response);
?>
