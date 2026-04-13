<?php
require_once '../config/config.php';
checkAuth('admin');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = getTenantId();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';

    // Check plan limits
    $plan = getTenantPlan($tenant_id);
    $max_staff = $plan['max_staff'] ?? 3; // Default
    
    $stmt_count = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND role = 'staff'");
    $stmt_count->bind_param("i", $tenant_id);
    $stmt_count->execute();
    $current_count = $stmt_count->get_result()->fetch_assoc()['count'];

    if ($current_count >= $max_staff) {
        $response['message'] = "Staff limit reached ($max_staff). Upgrade your plan.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, tenant_id) VALUES (?, ?, ?, 'staff', ?)");
        $stmt->bind_param("sssi", $username, $hashed_password, $full_name, $tenant_id);
        
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Staff added successfully'];
        } else {
            $response['message'] = 'Username already exists';
        }
    }
}

echo json_encode($response);
?>
