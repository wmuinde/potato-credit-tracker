
<header class="main-header">
    <div class="logo">
        <h1>Potato Credit Tracker</h1>
    </div>
    <div class="user-info">
        <span><?php echo sanitize($_SESSION['full_name']); ?></span>
        <span class="role-badge"><?php echo ucfirst($_SESSION['role']); ?></span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>
