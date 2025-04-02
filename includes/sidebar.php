
<aside class="sidebar">
    <nav class="main-nav">
        <ul>
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="customers.php">Customers</a></li>
            <li><a href="debts.php">Debts</a></li>
            <li><a href="sales.php">Sales</a></li>
            <li><a href="payments.php">Payments</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="workers.php">Workers Management</a></li>
            <?php endif; ?>
            <li><a href="stores.php">Stores/Lorries</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php">My Profile</a></li>
        </ul>
    </nav>
</aside>
