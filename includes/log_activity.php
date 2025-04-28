<?php
/**
 * Log user activity
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param string $action Action performed
 * @param string $details Additional details
 * @param string $type Log type (info, success, warning, danger)
 * @return bool Success status
 */
function logActivity($conn, $userId, $action, $details = '', $type = 'info') {
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, details, type, created_at)
        VALUES (:user_id, :action, :details, :type, NOW())
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':details', $details);
    $stmt->bindParam(':type', $type);
    return $stmt->execute();
}

/**
 * Get recent activity logs
 * 
 * @param PDO $conn Database connection
 * @param int $limit Number of logs to retrieve
 * @return array Activity logs
 */
function getRecentLogs($conn, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT al.*, u.name as user_name
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user activity logs
 * 
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param int $limit Number of logs to retrieve
 * @return array Activity logs
 */
function getUserLogs($conn, $userId, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT al.*, u.name as user_name
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.user_id = :user_id
        ORDER BY al.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
