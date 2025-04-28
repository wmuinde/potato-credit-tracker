<?php
require_once 'includes/messaging.php';
$unreadMessageCount = isset($_SESSION['user_id']) ? getUnreadMessageCount($conn, $_SESSION['user_id']) : 0;
?>
<header class="navbar navbar-dark sticky-top flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 d-flex align-items-center" href="index.php">
        <i data-feather="box" class="me-2"></i>
        <?php echo SITE_NAME; ?>
    </a>
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="d-flex align-items-center ms-auto me-3">
       <!-- Theme Toggle Button -->
       <div class="theme-toggle me-3" id="themeToggle">
           <i data-feather="moon" id="darkIcon" style="width: 20px; height: 20px; display: block;"></i>
           <i data-feather="sun" id="lightIcon" style="width: 20px; height: 20px; display: none;"></i>
       </div>
       
       <div class="dropdown me-3">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none position-relative" id="messagesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i data-feather="message-square" style="width: 20px; height: 20px;"></i>
                <?php if ($unreadMessageCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?php echo $unreadMessageCount > 9 ? '9+' : $unreadMessageCount; ?>
                    <span class="visually-hidden">unread messages</span>
                </span>
                <?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="messagesDropdown" style="width: 300px;">
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Messages</h6>
                        <a href="messages.php" class="text-decoration-none">View All</a>
                    </div>
                    <?php 
                    if (isset($_SESSION['user_id'])) {
                        $conversations = getRecentConversations($conn, $_SESSION['user_id']);
                        if (!empty($conversations)) {
                            foreach (array_slice($conversations, 0, 3) as $conversation) {
                                $messagePreview = strlen($conversation['last_message']) > 30 ? 
                                    substr($conversation['last_message'], 0, 30) . '...' : 
                                    $conversation['last_message'];
                                echo '<a href="messages.php?user=' . $conversation['user_id'] . '" class="dropdown-item d-flex align-items-center p-2 ' . ($conversation['unread_count'] > 0 ? 'bg-light' : '') . '">';
                                echo '<div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">';
                                echo '<i data-feather="user" style="width: 20px; height: 20px;"></i>';
                                echo '</div>';
                                echo '<div class="flex-grow-1 ms-2">';
                                echo '<div class="d-flex justify-content-between">';
                                echo '<span class="fw-medium">' . $conversation['user_name'] . '</span>';
                                echo '<small class="text-muted">' . date('H:i', strtotime($conversation['last_message_time'])) . '</small>';
                                echo '</div>';
                                echo '<div class="small text-truncate">' . $messagePreview . '</div>';
                                echo '</div>';
                                echo '</a>';
                            }
                        } else {
                            echo '<div class="text-center py-3 text-muted">No messages yet</div>';
                        }
                    }
                    ?>
                    <div class="d-grid gap-2 mt-2">
                        <a href="messages.php" class="btn btn-primary btn-sm">Open Chat</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="d-flex align-items-center">
                    <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <i data-feather="user" style="width: 16px; height: 16px;"></i>
                    </div>
                    <span class="d-none d-sm-inline"><?php echo $_SESSION['user_name'] ?? 'User'; ?></span>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="profile.php"><i data-feather="user" class="me-2" style="width: 14px; height: 14px;"></i> Profile</a></li>
                <li><a class="dropdown-item" href="messages.php"><i data-feather="message-square" class="me-2" style="width: 14px; height: 14px;"></i> Messages</a></li>
                <li><a class="dropdown-item" href="settings.php"><i data-feather="settings" class="me-2" style="width: 14px; height: 14px;"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i data-feather="log-out" class="me-2" style="width: 14px; height: 14px;"></i> Sign out</a></li>
            </ul>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    const darkIcon = document.getElementById('darkIcon');
    const lightIcon = document.getElementById('lightIcon');
    
    // Check for saved theme preference or use default
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Update icon based on current theme
    if (savedTheme === 'dark') {
        darkIcon.style.display = 'none';
        lightIcon.style.display = 'block';
    }
    
    // Toggle theme
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // Toggle icon
        if (newTheme === 'dark') {
            darkIcon.style.display = 'none';
            lightIcon.style.display = 'block';
        } else {
            darkIcon.style.display = 'block';
            lightIcon.style.display = 'none';
        }
    });
});
</script>
</header>
