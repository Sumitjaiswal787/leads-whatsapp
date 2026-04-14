<?php
/**
 * WhatsApp Lead Grabber CRM - Global Configuration
 */

// Error Reporting (Production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // SILENCE: Prevent errors from breaking JSON
ini_set('log_errors', 1);

// Database Configuration (Dynamic Environment Detection)
$is_local = (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === 'localhost:8080' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) 
            || (php_sapi_name() === 'cli'); 

if ($is_local) {
    // LOCAL SETTINGS (XAMPP / Local Dev)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'whatsapp_crm');
    define('BASE_URL', 'http://localhost:8080/');
    define('BACKEND_URL', 'http://localhost:3000');
} else {
    // HOSTINGER SETTINGS (Production)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u828453283_whats');
    define('DB_PASS', 'Sumit@787870');
    define('DB_NAME', 'u828453283_whats');
    define('BASE_URL', 'https://whatsapp.tezikaro.com/'); 
    define('BACKEND_URL', 'https://leads-whatsapp-backend-production.up.railway.app');
}
define('DB_PORT', 3306);

// App Configuration
define('APP_NAME', 'WhatsApp Lead Grabber CRM');

// Secret for PHP-Node communication
define('WORKER_API_SECRET', getenv('WORKER_API_SECRET') ?: 'whatsapp_crm_secret_2026');

// Razorpay Credentials
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_XXXXXXXXXXXXXX');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'XXXXXXXXXXXXXXXXXXXXXXXX');

// Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // For debugging 500 errors, we temporarily enable error display if connection fails
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        die("Database Connection Error: " . $e->getMessage());
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

/**
 * Helper to check if user is logged in
 */
function checkAuth($role = null)
{
    if (!isset($_SESSION['user_id'])) {
        // If this is an AJAX request, return JSON instead of redirecting
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Your session has expired. Please log in again.', 'redirect' => true]);
            exit();
        }
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
    if ($role) {
        if ($_SESSION['role'] === 'superadmin') {
            return; // Superadmin has access to everything
        }

        // Check for subscription status
        $plan = getTenantPlan(getTenantId());
        if ($plan && ($plan['status'] === 'suspended' || $plan['status'] === 'expired')) {
            // Only allow them to see the dashboard to see the notice, block other pages
            $currentPage = basename($_SERVER['PHP_SELF']);
            if ($currentPage !== 'dashboard.php') {
                header("Location: " . BASE_URL . "admin/dashboard.php");
                exit();
            }
        }

        if ($_SESSION['role'] !== $role) {
            header("Location: " . BASE_URL . "index.php?error=unauthorized");
            exit();
        }
    }
}

/**
 * Helper to get current tenant ID
 */
function getTenantId()
{
    if (!isset($_SESSION['role'])) return null;
    
    if ($_SESSION['role'] === 'admin') {
        return $_SESSION['user_id'];
    }
    
    // For Super Admin, we default to Tenant 1 if they haven't picked one
    if ($_SESSION['role'] === 'superadmin') {
        return $_SESSION['active_tenant_id'] ?? 1;
    }
    
    return $_SESSION['tenant_id'] ?? 1;
}

/**
 * Get the active plan for a tenant
 */
function getTenantPlan($tenant_id)
{
    global $conn;
    // Get the most recent non-pending subscription
    $sql = "SELECT p.*, s.status, s.end_date 
            FROM subscriptions s
            LEFT JOIN plans p ON s.plan_id = p.id 
            WHERE s.tenant_id = ? AND s.status != 'pending'
            ORDER BY s.created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $tenant_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    return null;
}
?>