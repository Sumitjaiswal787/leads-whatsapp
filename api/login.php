<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid credentials'];

try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT id, username, password, role, tenant_id FROM users WHERE username = ?");
        
        if (!$stmt) {
            throw new Exception("Database error: Table 'users' may be missing or schema is incorrect.");
        }

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
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
