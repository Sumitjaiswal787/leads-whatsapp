<?php
require_once '../config/config.php';
checkAuth();
$tenant_id = getTenantId();

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['lead_id'] ?? 0;
    $message = $_POST['message'] ?? '';

    // Verify lead belongs to tenant (and staff if role is staff)
    $stmt_lead = $conn->prepare("SELECT l.*, s.session_id as session_logical_id FROM leads l JOIN whatsapp_sessions s ON l.session_id = s.id WHERE l.id = ? AND l.tenant_id = ?");
    $stmt_lead->bind_param("ii", $lead_id, $tenant_id);
    $stmt_lead->execute();
    $lead = $stmt_lead->get_result()->fetch_assoc();

    if ($lead) {
        // Send to Node.js
        $data = [
            'sessionId' => $lead['session_logical_id'],
            'number' => $lead['number'],
            'message' => $message,
            'secret' => WORKER_API_SECRET
        ];

        $ch = curl_init(BACKEND_URL . '/api/messages/send');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $res_data = json_decode($res, true);
        curl_close($ch);

        if (isset($res_data['success']) && $res_data['success']) {
            // Save to messages history
            $stmt_msg = $conn->prepare("INSERT INTO whatsapp_messages (lead_id, message, direction) VALUES (?, ?, 'outgoing')");
            $stmt_msg->bind_param("is", $lead_id, $message);
            $stmt_msg->execute();

            $response = ['status' => 'success', 'message' => 'Message sent'];
        } else {
            $response['message'] = $res_data['error'] ?? 'Node.js backend error';
        }
    } else {
        $response['message'] = 'Lead not found or unauthorized';
    }
}

echo json_encode($response);
?>
