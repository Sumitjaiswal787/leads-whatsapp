<?php
require_once '../config/config.php';

$response = ['status' => 'error', 'message' => 'Invalid credentials'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, role, tenant_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            if ($user['role'] === 'superadmin') {
                $_SESSION['tenant_id'] = null;
            } else {
                $_SESSION['tenant_id'] = ($user['role'] === 'admin') ? $user['id'] : $user['tenant_id'];
            }

            $response = ['status' => 'success', 'message' => 'Login successful', 'role' => $user['role']];
        }
    }
}

echo json_encode($response);
?>
