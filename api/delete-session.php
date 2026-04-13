<?php
require_once '../config/config.php';
checkAuth('admin');
$tenant_id = getTenantId();

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id_str = $_POST['session_id'] ?? '';

    // Verify session belongs to tenant
    $stmt_session = $conn->prepare("SELECT id FROM whatsapp_sessions WHERE session_id = ? AND tenant_id = ?");
    $stmt_session->bind_param("si", $session_id_str, $tenant_id);
    $stmt_session->execute();
    $session = $stmt_session->get_result()->fetch_assoc();

    if ($session) {
        // 1. Tell Node.js to delete session
        $data = [
            'tenantId' => $tenant_id,
            'sessionId' => $session_id_str,
            'secret' => WORKER_API_SECRET
        ];
        
        $ch = curl_init(BACKEND_URL . '/api/sessions/delete');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        // 2. Delete from DB
        $stmt_del = $conn->prepare("DELETE FROM whatsapp_sessions WHERE session_id = ?");
        $stmt_del->bind_param("s", $session_id_str);
        
        if ($stmt_del->execute()) {
            $response = ['status' => 'success', 'message' => 'Session deleted successfully'];
        } else {
            $response['message'] = 'Database error';
        }
    } else {
        $response['message'] = 'Session not found or unauthorized';
    }
}

echo json_encode($response);
?>
