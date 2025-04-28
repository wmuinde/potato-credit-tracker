<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_FILES['csv_file'])) {
    header("Location: reports.php");
    exit();
}

// Get default vehicle ID
$vehicleId = $_POST['vehicle_id'] ?? 0;

// Check if file was uploaded without errors
if ($_FILES['csv_file']['error'] != 0) {
   $_SESSION['import_error'] = 'Error uploading file. Please try again.';
   header("Location: reports.php");
   exit();
}

// Get file extension
$fileExtension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

// Check if file is CSV
if ($fileExtension != 'csv') {
   $_SESSION['import_error'] = 'Only CSV files are supported at this time.';
   header("Location: reports.php");
   exit();
}

// Get worker ID
$workerId = $_SESSION['user_id'];

// Process the CSV file
$result = importCustomersFromCSV($conn, $_FILES['csv_file']['tmp_name'], $vehicleId, $workerId);

// Set session variables for results
$_SESSION['import_success'] = $result['success'];
$_SESSION['import_errors'] = $result['errors'];

// Redirect back to reports page
header("Location: reports.php");
exit();
?>
