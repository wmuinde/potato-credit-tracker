<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/messaging.php';
require_once 'includes/log_activity.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$selectedUserId = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Get all users for chat
$chatUsers = getUsersForChat($conn, $userId);

// If a user is selected, get messages and mark them as read
if ($selectedUserId) {
    $messages = getMessages($conn, $userId, $selectedUserId);
    markMessagesAsRead($conn, $userId, $selectedUserId);
    
    // Get selected user details
    $stmt = $conn->prepare("SELECT name, role FROM users WHERE id = :id");
    $stmt->bindParam(':id', $selectedUserId);
    $stmt->execute();
    $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $messages = [];
    $selectedUser = null;
}

// Process send message form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiverId = $_POST['receiver_id'];
    $messageText = trim($_POST['message']);
    
    if (!empty($messageText) && $receiverId) {
        if (sendMessage($conn, $userId, $receiverId, $messageText)) {
            // Log activity
            logActivity($conn, $userId, "Sent a message", "To user #$receiverId", "info");
            
            // Redirect to avoid form resubmission
            header("Location: messages.php?user=$receiverId&sent=1");
            exit();
        }
    }
}

// Get recent conversations
$conversations = getRecentConversations($conn, $userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .chat-container {
            height: calc(100vh - 200px);
            min-height: 500px;
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        .message {
            margin-bottom: 1rem;
            max-width: 80%;
        }
        .message-outgoing {
            margin-left: auto;
            background-color: var(--primary-light);
            color: var(--primary);
            border-radius: 1rem 0 1rem 1rem;
            padding: 0.75rem 1rem;
        }
        .message-incoming {
            margin-right: auto;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0 1rem 1rem 1rem;
            padding: 0.75rem 1rem;
        }
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
            text-align: right;
        }
        .chat-input {
            padding: 1rem;
            background-color: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
        }
        .user-list {
            height: calc(100vh - 200px);
            min-height: 500px;
            overflow-y: auto;
        }
        .user-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .user-item:hover, .user-item.active {
            background-color: var(--primary-light);
        }
        .user-item .badge {
            font-size: 0.7rem;
        }
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
        }
        .empty-state i {
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Messages</h1>
                </div>
                
                <?php if (isset($_GET['sent'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Message sent successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Users List -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Conversations</h5>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                    <i data-feather="plus" class="me-1" style="width: 14px; height: 14px;"></i> New Message
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="user-list">
                                    <?php if (!empty($conversations)): ?>
                                        <?php foreach ($conversations as $conversation): ?>
                                            <a href="?user=<?php echo $conversation['user_id']; ?>" class="text-decoration-none">
                                                <div class="user-item <?php echo $selectedUserId == $conversation['user_id'] ? 'active' : ''; ?>">
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                            <i data-feather="user" style="width: 20px; height: 20px;"></i>
                                                        </div>
                                                        <div class="flex-grow-1 ms-2">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="fw-medium"><?php echo $conversation['user_name']; ?></span>
                                                                <small class="text-muted"><?php echo date('H:i', strtotime($conversation['last_message_time'])); ?></small>
                                                            </div>
                                                            <div class="small text-truncate <?php echo $conversation['unread_count'] > 0 ? 'fw-medium' : 'text-muted'; ?>">
                                                                <?php 
                                                                echo strlen($conversation['last_message']) > 30 ? 
                                                                    substr($conversation['last_message'], 0, 30) . '...' : 
                                                                    $conversation['last_message']; 
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <?php if ($conversation['unread_count'] > 0): ?>
                                                            <span class="badge bg-primary rounded-pill ms-2"><?php echo $conversation['unread_count']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="p-4 text-center text-muted">
                                            <p>No conversations yet</p>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                                Start a new conversation
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Area -->
                    <div class="col-md-8 mb-4">
                        <div class="card h-100">
                            <?php if ($selectedUser): ?>
                                <div class="card-header d-flex align-items-center">
                                    <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                        <i data-feather="user" style="width: 20px; height: 20px;"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0"><?php echo $selectedUser['name']; ?></h5>
                                        <small class="text-muted"><?php echo ucfirst($selectedUser['role']); ?></small>
                                    </div>
                                </div>
                                <div class="chat-container">
                                    <div class="chat-messages" id="chatMessages">
                                        <?php foreach ($messages as $message): ?>
                                            <div class="message <?php echo $message['sender_id'] == $userId ? 'message-outgoing' : 'message-incoming'; ?>" data-id="<?php echo $message['id']; ?>">
                                                <div class="message-content">
                                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                </div>
                                                <div class="message-time">
                                                    <?php echo date('M d, H:i', strtotime($message['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="chat-input">
                                        <form method="post" id="messageForm">
                                            <input type="hidden" name="receiver_id" value="<?php echo $selectedUserId; ?>">
                                            <div class="input-group">
                                                <textarea class="form-control" name="message" placeholder="Type your message..." rows="1" required></textarea>
                                                <button class="btn btn-primary" type="submit" name="send_message">
                                                    <i data-feather="send" style="width: 16px; height: 16px;"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i data-feather="message-square" style="width: 64px; height: 64px;"></i>
                                    <h4>Select a conversation</h4>
                                    <p>Choose a user from the list or start a new conversation</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                        <i data-feather="plus" class="me-1" style="width: 14px; height: 14px;"></i> New Message
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1" aria-labelledby="newMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newMessageModalLabel">New Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="messages.php">
                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">Select User</label>
                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                <option value="">Select a user</option>
                                <?php foreach ($chatUsers as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo $user['name']; ?> (<?php echo ucfirst($user['role']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Feather icons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
            
            // Auto-resize textarea
            const textarea = document.querySelector('textarea[name="message"]');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
            
            // Scroll to bottom of chat
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Real-time message updates
            <?php if ($selectedUserId): ?>
            let lastMessageId = 0;
            const messageElements = document.querySelectorAll('.message');
            if (messageElements.length > 0) {
                lastMessageId = messageElements[messageElements.length - 1].getAttribute('data-id');
            }
            
            // Poll for new messages every 5 seconds
            setInterval(function() {
                fetch(`get-messages.php?user_id=<?php echo $selectedUserId; ?>&last_id=${lastMessageId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.count > 0) {
                            // Update lastMessageId
                            const newMessages = data.messages;
                            lastMessageId = newMessages[newMessages.length - 1].id;
                            
                            // Append new messages to chat
                            newMessages.forEach(message => {
                                const messageDiv = document.createElement('div');
                                messageDiv.className = `message ${message.isOutgoing ? 'message-outgoing' : 'message-incoming'}`;
                                messageDiv.setAttribute('data-id', message.id);
                                messageDiv.innerHTML = `
                                    <div class="message-content">
                                        ${message.message}
                                    </div>
                                    <div class="message-time">
                                        ${message.time}
                                    </div>
                                `;
                                chatMessages.appendChild(messageDiv);
                            });
                            
                            // Scroll to bottom
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                        }
                    })
                    .catch(error => console.error('Error fetching messages:', error));
            }, 5000);
            <?php endif; ?>
        });
    </script>
</body>
</html>
