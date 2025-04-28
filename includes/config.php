<?php
// Database configuration
$dbHost = 'localhost';
$dbName = 'potato_sales';
$dbUser = 'root';
$dbPass = '';

// Create database connection
try {
    $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// System configuration
define('SITE_NAME', 'Potato Sales Management');
define('CURRENCY', 'KES'); // Kenya Shillings
define('DATE_FORMAT', 'd/m/Y');
define('HELD_FUNDS_THRESHOLD', 10000); // Alert if worker holds more than this amount
define('COMPANY_NAME', 'Potato Distributors Ltd');
define('COMPANY_ADDRESS', 'P.O. Box 12345, Nairobi, Kenya');
define('COMPANY_PHONE', '+254 700 123456');
define('COMPANY_EMAIL', 'info@potatodistributors.co.ke');
?>
