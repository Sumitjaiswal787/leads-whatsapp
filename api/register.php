<?php
require_once '../config/config.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';

    if (empty($username) || empty($password)) {
        $response['message'] = 'Username and password are required';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin')");
        $stmt->bind_param("sss", $username, $hashed_password, $full_name);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Create assign queue for the new tenant
            $stmt_queue = $conn->prepare("INSERT INTO assign_queue (tenant_id) VALUES (?)");
            $stmt_queue->bind_param("i", $user_id);
            $stmt_queue->execute();

            $response = ['status' => 'success', 'message' => 'Registration successful'];
        } else {
            $response['message'] = 'Username already exists or database error';
        }
    }
}

echo json_encode($response);
?>
