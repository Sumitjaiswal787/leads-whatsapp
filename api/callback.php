<?php
require_once '../config/config.php';

// Raw input from Node.js
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || ($input['secret'] ?? '') !== WORKER_API_SECRET) {
    http_response_code(401);
    exit('Unauthorized');
}

$tenant_id = $input['tenant_id'];
$session_id_str = $input['session_id']; // This is the string logical ID
$jid = $input['jid'] ?? ($input['from'] . '@s.whatsapp.net'); // Fallback if old backend

// 1. Handle Status Update
if (isset($input['event']) && $input['event'] === 'status_update') {
    $status = $input['status'];
    $stmt = $conn->prepare("UPDATE whatsapp_sessions SET status = ? WHERE session_id = ?");
    $stmt->bind_param("ss", $status, $session_id_str);
    $stmt->execute();
    exit('Status updated');
}

// 2. Handle Incoming Message (Lead Capture)
if (isset($input['message'])) {
    $from = $input['from'];
    $name = $input['name'];
    $message = $input['message'];

    // Get Session DB ID
    $stmt_session = $conn->prepare("SELECT id FROM whatsapp_sessions WHERE session_id = ?");
    $stmt_session->bind_param("s", $session_id_str);
    $stmt_session->execute();
    $session_db_id = $stmt_session->get_result()->fetch_assoc()['id'] ?? 0;

    if (!$session_db_id) exit('Session not found in DB');

    // Check if lead exists (by number or JID)
    $stmt_lead = $conn->prepare("SELECT id, staff_id FROM leads WHERE tenant_id = ? AND (number = ? OR jid = ?)");
    $stmt_lead->bind_param("iss", $tenant_id, $from, $jid);
    $stmt_lead->execute();
    $lead_result = $stmt_lead->get_result();
    $lead = $lead_result->fetch_assoc();

    $is_new_lead = false;
    $lead_id = 0;
    $assigned_staff_id = null;

    if (!$lead) {
        $is_new_lead = true;
        
        // --- Round Robin Assignment Logic ---
        $stmt_staff = $conn->prepare("SELECT id FROM users WHERE tenant_id = ? AND role = 'staff'");
        $stmt_staff->bind_param("i", $tenant_id);
        $stmt_staff->execute();
        $staff_members = $stmt_staff->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($staff_members) > 0) {
            // Get last index
            $stmt_queue = $conn->prepare("SELECT last_staff_index FROM assign_queue WHERE tenant_id = ?");
            $stmt_queue->bind_param("i", $tenant_id);
            $stmt_queue->execute();
            $last_index = $stmt_queue->get_result()->fetch_assoc()['last_staff_index'] ?? 0;

            $next_index = ($last_index + 1) % count($staff_members);
            $assigned_staff_id = $staff_members[$next_index]['id'];

            // Update queue
            $stmt_upd_queue = $conn->prepare("UPDATE assign_queue SET last_staff_index = ? WHERE tenant_id = ?");
            $stmt_upd_queue->bind_param("ii", $next_index, $tenant_id);
            $stmt_upd_queue->execute();
        }

        // Create new lead
        $stmt_create = $conn->prepare("INSERT INTO leads (tenant_id, session_id, staff_id, name, number, jid, last_message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_create->bind_param("iiissss", $tenant_id, $session_db_id, $assigned_staff_id, $name, $from, $jid, $message);
        $stmt_create->execute();
        $lead_id = $stmt_create->insert_id;

        // Auto-reply logic
        $stmt_reply = $conn->prepare("SELECT welcome_message, is_enabled FROM auto_reply_settings WHERE session_id = ?");
        $stmt_reply->bind_param("i", $session_db_id);
        $stmt_reply->execute();
        $reply_settings = $stmt_reply->get_result()->fetch_assoc();

        if ($reply_settings && $reply_settings['is_enabled'] && !empty($reply_settings['welcome_message'])) {
            // Send back to Node.js to reply
            $reply_data = [
                'sessionId' => $session_id_str,
                'jid' => $jid, // Using direct JID for routing
                'message' => $reply_settings['welcome_message'],
                'secret' => WORKER_API_SECRET
            ];
            $ch = curl_init(BACKEND_URL . '/api/messages/send');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reply_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $curl_res = curl_exec($ch);
            if ($curl_res === false) {
                error_log("Curl error sending auto-reply: " . curl_error($ch));
            }
            curl_close($ch);
        }
    } else {
        $lead_id = $lead['id'];
        $stmt_upd = $conn->prepare("UPDATE leads SET last_message = ?, status = 'new' WHERE id = ?");
        $stmt_upd->bind_param("si", $message, $lead_id);
        $stmt_upd->execute();
    }

    // Save message history
    $stmt_msg = $conn->prepare("INSERT INTO whatsapp_messages (lead_id, message, direction) VALUES (?, ?, 'incoming')");
    $stmt_msg->bind_param("is", $lead_id, $message);
    $stmt_msg->execute();

    echo json_encode(['status' => 'success', 'lead_id' => $lead_id, 'assigned_to' => $assigned_staff_id]);
}
?>
