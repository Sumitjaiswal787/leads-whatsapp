<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? 0;

    // Verify lead belongs to tenant
    $stmt_verify = $conn->prepare("SELECT id FROM leads WHERE id = ? AND tenant_id = ?");
    $stmt_verify->bind_param("ii", $lead_id, $tenant_id);
    $stmt_verify->execute();
    
    if ($stmt_verify->get_result()->num_rows > 0) {
        // Also delete message history
        $stmt_msg = $conn->prepare("DELETE FROM whatsapp_messages WHERE lead_id = ?");
        $stmt_msg->bind_param("i", $lead_id);
        $stmt_msg->execute();

        // Delete lead
        $stmt_del = $conn->prepare("DELETE FROM leads WHERE id = ?");
        $stmt_del->bind_param("i", $lead_id);
        
        if ($stmt_del->execute()) {
            $response = ['status' => 'success', 'message' => 'Lead deleted successfully'];
        } else {
            $response['message'] = 'Failed to delete lead';
        }
    } else {
        $response['message'] = 'Lead not found or unauthorized';
    }
}

echo json_encode($response);
?>
