
<?php
// Database connection configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "potato_credit_tracker";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to check if user is worker
function isWorker() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'worker';
}

// Function to redirect to a page
function redirect($page) {
    header("Location: $page");
    exit;
}

// Function to secure against XSS
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

// Format currency
function formatCurrency($amount) {
    return number_format($amount, 2);
}

// Get user name by ID
function getUserName($conn, $user_id) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['full_name'];
    }
    return "Unknown";
}

// Get store name by ID
function getStoreName($conn, $store_id) {
    $stmt = $conn->prepare("SELECT name, type, number_plate FROM stores WHERE id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['type'] === 'lorry' && !empty($row['number_plate'])) {
            return $row['name'] . " (" . $row['number_plate'] . ")";
        }
        return $row['name'];
    }
    return "Unknown";
}

// Get customer name by ID
function getCustomerName($conn, $customer_id) {
    if (!$customer_id) return "Cash Customer";
    
    $stmt = $conn->prepare("SELECT full_name FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['full_name'];
    }
    return "Unknown";
}

// Protect against unauthorized access
function checkAccess($requiredRole = null) {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    if ($requiredRole === 'admin' && !isAdmin()) {
        redirect('index.php');
    }
}
?>
