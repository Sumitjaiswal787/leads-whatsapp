<?php
require_once '../config/config.php';

// Raw input from Node.js
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// DEBUG LOG: Record the hit
file_put_contents('callback_log.txt', "[" . date('Y-m-d H:i:s') . "] Raw: " . $raw_input . PHP_EOL, FILE_APPEND);

if (!$input || ($input['secret'] ?? '') !== WORKER_API_SECRET) {
    http_response_code(401);
    exit('Unauthorized');
}

$tenant_id = $input['tenant_id'];
$session_id_str = $input['session_id'] ?? null; // Logical ID for WhatsApp
$source = $input['source'] ?? 'whatsapp';
$project_name = $input['project_name'] ?? null;
$jid = $input['jid'] ?? (($input['from'] ?? '') . '@s.whatsapp.net');

// 1. Handle Status Update
if (isset($input['event']) && $input['event'] === 'status_update') {
    $status = $input['status'];
    $stmt = $conn->prepare("UPDATE whatsapp_sessions SET status = ? WHERE session_id = ?");
    $stmt->bind_param("ss", $status, $session_id_str);
    $stmt->execute();
    exit('Status updated');
}

// 2. Handle Incoming Message / Lead Capture
if (isset($input['message'])) {
    $from = $input['from'];
    $name = $input['name'];
    $message = $input['message'];

    // Get Session DB ID (If not provided, find the first connected session for this tenant)
    $session_db_id = 0;
    if ($session_id_str) {
        $stmt_session = $conn->prepare("SELECT id FROM whatsapp_sessions WHERE session_id = ?");
        $stmt_session->bind_param("s", $session_id_str);
        $stmt_session->execute();
        $session_db_id = $stmt_session->get_result()->fetch_assoc()['id'] ?? 0;
    } else {
        // Find any connected session to link the meta lead to (for auto-reply)
        $stmt_session = $conn->prepare("SELECT id, session_id FROM whatsapp_sessions WHERE tenant_id = ? AND status = 'connected' LIMIT 1");
        $stmt_session->bind_param("i", $tenant_id);
        $stmt_session->execute();
        $sess_row = $stmt_session->get_result()->fetch_assoc();
        $session_db_id = $sess_row['id'] ?? 0;
        $session_id_str = $sess_row['session_id'] ?? null;
    }

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
        $stmt_staff = $conn->prepare("SELECT id FROM users WHERE tenant_id = ? AND role = 'staff' AND is_active_for_leads = 1 ORDER BY id ASC");
        $stmt_staff->bind_param("i", $tenant_id);
        $stmt_staff->execute();
        $staff_rows = $stmt_staff->get_result()->fetch_all(MYSQLI_ASSOC);
        $active_staff_ids = array_column($staff_rows, 'id');

        if (count($active_staff_ids) > 0) {
            $stmt_queue = $conn->prepare("SELECT last_staff_id FROM assign_queue WHERE tenant_id = ?");
            $stmt_queue->bind_param("i", $tenant_id);
            $stmt_queue->execute();
            $queue_res = $stmt_queue->get_result()->fetch_assoc();
            $last_staff_id = $queue_res['last_staff_id'] ?? 0;

            $next_staff_id = $active_staff_ids[0];
            foreach ($active_staff_ids as $sid) {
                if ($sid > $last_staff_id) {
                    $next_staff_id = $sid;
                    break;
                }
            }
            $assigned_staff_id = $next_staff_id;

            $stmt_upd_queue = $conn->prepare("INSERT INTO assign_queue (tenant_id, last_staff_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_staff_id = VALUES(last_staff_id)");
            $stmt_upd_queue->bind_param("ii", $tenant_id, $assigned_staff_id);
            $stmt_upd_queue->execute();
        }

        // Create new lead with Meta/Project info
        $stmt_create = $conn->prepare("INSERT INTO leads (tenant_id, session_id, staff_id, project_name, source, name, number, jid, last_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_create->bind_param("iiissssss", $tenant_id, $session_db_id, $assigned_staff_id, $project_name, $source, $name, $from, $jid, $message);
        $stmt_create->execute();
        $lead_id = $stmt_create->insert_id;

        // Auto-reply logic (WhatsApp welcome for Meta lead)
        if ($session_db_id > 0) {
            $stmt_reply = $conn->prepare("SELECT welcome_message, is_enabled FROM auto_reply_settings WHERE session_id = ?");
            $stmt_reply->bind_param("i", $session_db_id);
            $stmt_reply->execute();
            $reply_settings = $stmt_reply->get_result()->fetch_assoc();

            if ($reply_settings && $reply_settings['is_enabled'] && !empty($reply_settings['welcome_message'])) {
                $reply_data = [
                    'sessionId' => $session_id_str,
                    'jid' => $jid,
                    'message' => $reply_settings['welcome_message'],
                    'secret' => WORKER_API_SECRET
                ];
                $ch = curl_init(BACKEND_URL . '/api/messages/send');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reply_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
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
