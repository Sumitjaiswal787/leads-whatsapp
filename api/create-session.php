<?php
require_once '../config/config.php';
checkAuth('admin');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = getTenantId();
    $session_name = $_POST['session_name'] ?? 'Session 1';
    
    // Check plan limits
    $plan = getTenantPlan($tenant_id);
    $max_sessions = $plan['max_sessions'] ?? 1; // Default to 1 if no sub
    
    $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM whatsapp_sessions WHERE tenant_id = ?");
    $stmt_count->bind_param("i", $tenant_id);
    $stmt_count->execute();
    $current_count = $stmt_count->get_result()->fetch_assoc()['count'];

    if ($current_count >= $max_sessions) {
        $response['message'] = "Plan limit reached ($max_sessions). Upgrade to add more sessions.";
    } else {
        $logical_id = "tenant_{$tenant_id}_" . uniqid();
        
        $stmt = $conn->prepare("INSERT INTO whatsapp_sessions (tenant_id, session_id, status) VALUES (?, ?, 'initializing')");
        $stmt->bind_param("is", $tenant_id, $logical_id);
        
        if ($stmt->execute()) {
            $session_db_id = $stmt->insert_id;
            
            // Create auto-reply entry
            $conn->query("INSERT INTO auto_reply_settings (session_id) VALUES ($session_db_id)");

            // Tell Node.js to start the session
            $data = [
                'tenantId' => $tenant_id,
                'sessionId' => $logical_id,
                'secret' => WORKER_API_SECRET
            ];
            
            $ch = curl_init(BACKEND_URL . '/api/sessions/init');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $response = ['status' => 'success', 'message' => 'Session initialized', 'session_id' => $logical_id];
            } else {
                $response['message'] = "Backend Error ($http_code): " . ($res ?: $err ?: 'No response');
                $response['debug_url'] = BACKEND_URL;
            }
            curl_close($ch);
        }
    }
}

echo json_encode($response);
?>
