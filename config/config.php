<?php
/**
 * WhatsApp Lead Grabber CRM - Global Configuration
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'whatsapp_crm');

// App Configuration
define('APP_NAME', 'WhatsApp Lead Grabber CRM');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost:8080/');
define('BACKEND_URL', getenv('BACKEND_URL') ?: 'https://leads-whatsapp-production.up.railway.app'); // Production fallback

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
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

/**
 * Helper to check if user is logged in
 */
function checkAuth($role = null)
{
    if (!isset($_SESSION['user_id'])) {
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
    return $_SESSION['role'] === 'admin' ? $_SESSION['user_id'] : ($_SESSION['tenant_id'] ?? null);
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
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
?>