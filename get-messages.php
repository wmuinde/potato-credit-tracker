<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/messaging.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$otherUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$lastMessageId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if (!$otherUserId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

// Get new messages
$stmt = $conn->prepare("
    SELECT m.*, 
        sender.name as sender_name, 
        receiver.name as receiver_name
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    WHERE ((m.sender_id = :user_id AND m.receiver_id = :other_user_id)
    OR (m.sender_id = :other_user_id AND m.receiver_id = :user_id))
    AND m.id > :last_id
    ORDER BY m.created_at ASC
");
$stmt->bindParam(':user_id', $userId);
$stmt->bindParam(':other_user_id', $otherUserId);
$stmt->bindParam(':last_id', $lastMessageId);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark messages as read
markMessagesAsRead($conn, $userId, $otherUserId);

// Format messages for output
$formattedMessages = [];
foreach ($messages as $message) {
    $isOutgoing = $message['sender_id'] == $userId;
    $formattedMessages[] = [
        'id' => $message['id'],
        'message' => nl2br(htmlspecialchars($message['message'])),
        'isOutgoing' => $isOutgoing,
        'time' => date('M d, H:i', strtotime($message['created_at'])),
        'timestamp' => strtotime($message['created_at'])
    ];
}

// Return messages as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'messages' => $formattedMessages,
    'count' => count($formattedMessages)
]);
