
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

// Get dashboard statistics
$stats = [];

// Get total customers with debt
$result = $conn->query("SELECT COUNT(DISTINCT c.id) as total FROM customers c 
                        JOIN sales s ON c.id = s.customer_id 
                        JOIN debts d ON s.id = d.sale_id 
                        WHERE d.status != 'paid'");
$stats['total_debtors'] = $result->fetch_assoc()['total'] ?? 0;

// Get total debt amount
$result = $conn->query("SELECT SUM(amount_due - amount_paid) as total FROM debts WHERE status != 'paid'");
$stats['total_debt'] = $result->fetch_assoc()['total'] ?? 0;

// Get total uncollected payment amount by workers (for admin)
if (isAdmin()) {
    $result = $conn->query("SELECT SUM(amount - forwarded_to_admin) as total FROM payments WHERE forwarded_to_admin < amount");
    $stats['uncollected_payments'] = $result->fetch_assoc()['total'] ?? 0;
} else {
    // For workers, get their uncollected amount
    $worker_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(amount - forwarded_to_admin) as total FROM payments WHERE collected_by = ? AND forwarded_to_admin < amount");
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['my_uncollected'] = $result->fetch_assoc()['total'] ?? 0;
}

// Get recent payments
if (isAdmin()) {
    $query = "SELECT p.*, d.sale_id, c.full_name as customer_name
              FROM payments p
              JOIN debts d ON p.debt_id = d.id
              JOIN sales s ON d.sale_id = s.id
              JOIN customers c ON s.customer_id = c.id
              ORDER BY p.collection_date DESC LIMIT 5";
    $recent_payments = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
} else {
    $worker_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT p.*, d.sale_id, c.full_name as customer_name
                           FROM payments p
                           JOIN debts d ON p.debt_id = d.id
                           JOIN sales s ON d.sale_id = s.id
                           JOIN customers c ON s.customer_id = c.id
                           WHERE p.collected_by = ?
                           ORDER BY p.collection_date DESC LIMIT 5");
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_payments = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Dashboard</h1>
            
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Debtors</h3>
                    <p class="stat-value"><?php echo $stats['total_debtors']; ?></p>
                </div>
                
                <div class="stat-card">
                    <h3>Total Outstanding Debt</h3>
                    <p class="stat-value"><?php echo formatCurrency($stats['total_debt']); ?></p>
                </div>
                
                <?php if (isAdmin()): ?>
                <div class="stat-card">
                    <h3>Uncollected From Workers</h3>
                    <p class="stat-value"><?php echo formatCurrency($stats['uncollected_payments']); ?></p>
                </div>
                <?php else: ?>
                <div class="stat-card">
                    <h3>My Uncollected Amount</h3>
                    <p class="stat-value"><?php echo formatCurrency($stats['my_uncollected']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="recent-section">
                <h2>Recent Collections</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Collected By</th>
                            <th>Date</th>
                            <th>Forwarded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_payments)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No recent collections found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo sanitize($payment['customer_name']); ?></td>
                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                    <td><?php echo getUserName($conn, $payment['collected_by']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['collection_date'])); ?></td>
                                    <td>
                                        <?php if ($payment['forwarded_to_admin'] >= $payment['amount']): ?>
                                            <span class="badge success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge warning">
                                                <?php echo formatCurrency($payment['forwarded_to_admin']); ?> / <?php echo formatCurrency($payment['amount']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
