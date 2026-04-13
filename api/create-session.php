<?php
require_once '../config/config.php';
checkAuth('admin');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = getTenantId();
    
    // Handle 'Force Init' for existing session
    if (isset($_POST['force_init']) && isset($_POST['session_id'])) {
        $logical_id = $_POST['session_id'];
        
        // Safety check: ensure session belongs to tenant
        $stmt_check = $conn->prepare("SELECT id FROM whatsapp_sessions WHERE tenant_id = ? AND session_id = ?");
        $stmt_check->bind_param("is", $tenant_id, $logical_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized or invalid session']);
            exit;
        }

        $data = [
            'tenantId' => $tenant_id,
            'sessionId' => $logical_id,
            'secret' => WORKER_API_SECRET
        ];
        
        $ch = curl_init(rtrim(BACKEND_URL, '/') . '/api/sessions/init');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($http_code === 200) {
            echo json_encode(['status' => 'success', 'message' => 'Session init triggered']);
        } else {
            $msg = "Backend Error ($http_code)";
            if ($http_code === 0) $msg .= ": " . ($curl_err ?: "Connection Refused / Unreachable");
            echo json_encode(['status' => 'error', 'message' => $msg, 'debug_url' => BACKEND_URL]);
        }
        exit;
    }

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
            
            $ch = curl_init(rtrim(BACKEND_URL, '/') . '/api/sessions/init');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $res = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($http_code === 200) {
                $response = ['status' => 'success', 'message' => 'Session initialized', 'session_id' => $logical_id];
            } else {
                $msg = "Backend Error ($http_code)";
                if ($http_code === 0) $msg .= ": " . ($curl_err ?: "Connection Refused / Unreachable");
                $response['message'] = $msg;
                $response['debug_url'] = BACKEND_URL;
            }
        }
    }
}

echo json_encode($response);
?>
