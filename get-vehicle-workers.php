<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get vehicle ID from URL
$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if vehicle exists
if (!$vehicleId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid vehicle ID']);
    exit();
}

// Get assigned workers
$stmt = $conn->prepare("
    SELECT worker_id FROM vehicle_assignments WHERE vehicle_id = :vehicle_id
");
$stmt->bindParam(':vehicle_id', $vehicleId);
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$workerIds = array_map(function($assignment) {
    return intval($assignment['worker_id']);
}, $assignments);

// Return worker IDs as JSON
header('Content-Type: application/json');
echo json_encode($workerIds);
?>
