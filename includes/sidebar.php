
<aside class="sidebar">
    <nav class="main-nav">
        <ul>
            <li><a href="index.php" style="color: #8B4513;">Dashboard</a></li>
            <li><a href="customers.php" style="color: #8B4513;">Customers</a></li>
            <li><a href="debts.php" style="color: #8B4513;">Debts</a></li>
            <li><a href="sales.php" style="color: #8B4513;">Sales</a></li>
            <li><a href="payments.php" style="color: #8B4513;">Payments</a></li>
            <?php if (isWorker()): ?>
                <li><a href="forward_payments.php" style="color: #8B4513;">Forward Payments</a></li>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
                <li><a href="workers.php" style="color: #8B4513;">Workers Management</a></li>
            <?php endif; ?>
            <li><a href="stores.php" style="color: #8B4513;">Vehicles</a></li>
            <li><a href="expenses.php" style="color: #8B4513;">Expenses</a></li>
            <li><a href="reports.php" style="color: #8B4513;">Reports</a></li>
            <li><a href="profile.php" style="color: #8B4513;">My Profile</a></li>
        </ul>
    </nav>
    <style>
        .sidebar {
            background-color: #f5efe6;
            border-right: 1px solid #e8ddcf;
        }
        
        .main-nav ul li a {
            color: #8B4513;
            border-left: 3px solid transparent;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .main-nav ul li a:hover, 
        .main-nav ul li a.active {
            background-color: #e8ddcf;
            border-left-color: #8B4513;
        }
    </style>
</aside>
