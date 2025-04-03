
<aside class="sidebar">
    <nav class="main-nav">
        <ul>
            <li><a href="index.php" class="nav-link">Dashboard</a></li>
            <li><a href="customers.php" class="nav-link">Customers</a></li>
            <li><a href="debts.php" class="nav-link">Debts</a></li>
            <li><a href="sales.php" class="nav-link">Sales</a></li>
            <li><a href="payments.php" class="nav-link">Payments</a></li>
            <?php if (isWorker()): ?>
                <li><a href="forward_payments.php" class="nav-link">Forward Payments</a></li>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
                <li><a href="workers.php" class="nav-link">Workers Management</a></li>
            <?php endif; ?>
            <li><a href="stores.php" class="nav-link">Vehicles</a></li>
            <li><a href="expenses.php" class="nav-link">Expenses</a></li>
            <li><a href="reports.php" class="nav-link">Reports</a></li>
            <li><a href="profile.php" class="nav-link">My Profile</a></li>
        </ul>
    </nav>
    <style>
        .sidebar {
            background-color: #f5efe6;
            border-right: 1px solid #e8ddcf;
            box-shadow: 0 4px 6px -1px rgba(139, 69, 19, 0.1);
            transition: all 0.3s ease;
        }
        
        .main-nav ul li {
            margin-bottom: 2px;
        }
        
        .main-nav ul li a.nav-link {
            color: #8B4513;
            border-left: 3px solid transparent;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 0 4px 4px 0;
        }
        
        .main-nav ul li a.nav-link:hover, 
        .main-nav ul li a.nav-link.active {
            background-color: #e8ddcf;
            border-left-color: #8B4513;
            transform: translateX(5px);
        }
        
        /* Add active class detection based on current URL */
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        echo ".main-nav ul li a[href='$current_page'] { 
            background-color: #e8ddcf; 
            border-left-color: #8B4513;
        }";
        ?>
    </style>
</aside>
