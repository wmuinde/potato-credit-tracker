<?php
require_once 'includes/messaging.php';
$unreadMessageCount = isset($_SESSION['user_id']) ? getUnreadMessageCount($conn, $_SESSION['user_id']) : 0;
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="px-3 mb-3">
            <div class="d-flex align-items-center">
                <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                    <i data-feather="user" style="width: 20px; height: 20px;"></i>
                </div>
                <div>
                    <div class="fw-semibold"><?php echo $_SESSION['user_name'] ?? 'User'; ?></div>
                    <div class="text-muted fs-sm"><?php echo ucfirst($_SESSION['user_role'] ?? 'user'); ?></div>
                </div>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i data-feather="home"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vehicles.php' ? 'active' : ''; ?>" href="vehicles.php">
                    <i data-feather="truck"></i>
                    Vehicles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i data-feather="users"></i>
                    Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'debts.php' ? 'active' : ''; ?>" href="debts.php">
                    <i data-feather="alert-circle"></i>
                    Outstanding Debts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                    <i data-feather="message-square"></i>
                    Messages
                    <?php if ($unreadMessageCount > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-2"><?php echo $unreadMessageCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i data-feather="file-text"></i>
                    Reports
                </a>
            </li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'workers.php' ? 'active' : ''; ?>" href="workers.php">
                    <i data-feather="user"></i>
                    Workers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i data-feather="settings"></i>
                    Settings
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Admin Reports</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'held-funds.php' ? 'active' : ''; ?>" href="held-funds.php">
                    <i data-feather="dollar-sign"></i>
                    Held Funds
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profit-loss.php' ? 'active' : ''; ?>" href="profit-loss.php">
                    <i data-feather="bar-chart-2"></i>
                    Profit & Loss
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity-logs.php' ? 'active' : ''; ?>" href="activity-logs.php">
                    <i data-feather="activity"></i>
                    Activity Logs
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</nav>
