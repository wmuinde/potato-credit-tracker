<?php
/**
 * Get all messages between two users
 * 
 * @param PDO $conn Database connection
 * @param int $userId Current user ID
 * @param int $otherUserId Other user ID
 * @return array Messages
 */
function getMessages($conn, $userId, $otherUserId) {
    $stmt = $conn->prepare("
        SELECT m.*, 
            sender.name as sender_name, 
            receiver.name as receiver_name
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        WHERE (m.sender_id = :user_id AND m.receiver_id = :other_user_id)
        OR (m.sender_id = :other_user_id AND m.receiver_id = :user_id)
        ORDER BY m.created_at ASC
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':other_user_id', $otherUserId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send a message
 * 
 * @param PDO $conn Database connection
 * @param int $senderId Sender user ID
 * @param int $receiverId Receiver user ID
 * @param string $message Message content
 * @return bool Success status
 */
function sendMessage($conn, $senderId, $receiverId, $message) {
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, created_at)
        VALUES (:sender_id, :receiver_id, :message, NOW())
    ");
    $stmt->bindParam(':sender_id', $senderId);
    $stmt->bindParam(':receiver_id', $receiverId);
    $stmt->bindParam(':message', $message);
    return $stmt->execute();
}

/**
 * Mark messages as read
 * 
 * @param PDO $conn Database connection
 * @param int $userId Current user ID
 * @param int $otherUserId Other user ID
 * @return bool Success status
 */
function markMessagesAsRead($conn, $userId, $otherUserId) {
    $stmt = $conn->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE receiver_id = :user_id AND sender_id = :other_user_id AND is_read = 0
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':other_user_id', $otherUserId);
    return $stmt->execute();
}

/**
 * Get unread message count for a user
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return int Unread message count
 */
function getUnreadMessageCount($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM messages
        WHERE receiver_id = :user_id AND is_read = 0
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}

/**
 * Get recent conversations for a user
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return array Conversations
 */
function getRecentConversations($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.name as user_name,
            u.role as user_role,
            (
                SELECT message 
                FROM messages 
                WHERE (sender_id = :user_id AND receiver_id = u.id) 
                OR (sender_id = u.id AND receiver_id = :user_id) 
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM messages 
                WHERE (sender_id = :user_id AND receiver_id = u.id) 
                OR (sender_id = u.id AND receiver_id = :user_id) 
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*) 
                FROM messages 
                WHERE receiver_id = :user_id AND sender_id = u.id AND is_read = 0
            ) as unread_count
        FROM users u
        WHERE u.id != :user_id
        AND EXISTS (
            SELECT 1 
            FROM messages 
            WHERE (sender_id = :user_id AND receiver_id = u.id) 
            OR (sender_id = u.id AND receiver_id = :user_id)
        )
        ORDER BY last_message_time DESC
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all users for starting a new conversation
 * 
 * @param PDO $conn Database connection
 * @param int $userId Current user ID
 * @return array Users
 */
function getUsersForChat($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT id, name, role
        FROM users
        WHERE id != :user_id
        ORDER BY name
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
