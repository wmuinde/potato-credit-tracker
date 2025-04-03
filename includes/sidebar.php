
<aside class="sidebar">
    <nav class="main-nav">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="customers.php">Customers</a></li>
            <li><a href="debts.php">Debts</a></li>
            <li><a href="sales.php">Sales</a></li>
            <li><a href="payments.php">Payments</a></li>
            <?php if (isWorker()): ?>
                <li><a href="forward_payments.php">Forward Payments</a></li>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
                <li><a href="workers.php">Workers Management</a></li>
            <?php endif; ?>
            <li><a href="stores.php">Vehicles</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php">My Profile</a></li>
        </ul>
    </nav>
</aside>
