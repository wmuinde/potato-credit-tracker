
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
    
    // Get vehicles with debts
    $vehicles_query = "SELECT s.*, 
                        (SELECT COUNT(DISTINCT c.id) FROM customers c 
                         JOIN sales sl ON c.id = sl.customer_id 
                         JOIN debts d ON sl.id = d.sale_id 
                         WHERE sl.store_id = s.id AND d.status != 'paid') as debtor_count,
                         (SELECT SUM(d.amount_due - d.amount_paid) FROM sales sl 
                          JOIN debts d ON sl.id = d.sale_id 
                          WHERE sl.store_id = s.id AND d.status != 'paid') as total_debt
                      FROM stores s
                      ORDER BY s.name";
    $vehicles = $conn->query($vehicles_query)->fetch_all(MYSQLI_ASSOC);
} else {
    // For workers, get their uncollected amount
    $worker_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(amount - forwarded_to_admin) as total FROM payments WHERE collected_by = ? AND forwarded_to_admin < amount");
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['my_uncollected'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Get vehicles assigned to this worker
    $worker_vehicles_query = "SELECT DISTINCT s.*, 
                              (SELECT COUNT(DISTINCT c.id) FROM customers c 
                               JOIN sales sl ON c.id = sl.customer_id 
                               JOIN debts d ON sl.id = d.sale_id 
                               WHERE sl.store_id = s.id AND d.status != 'paid') as debtor_count,
                              (SELECT SUM(d.amount_due - d.amount_paid) FROM sales sl 
                               JOIN debts d ON sl.id = d.sale_id 
                               WHERE sl.store_id = s.id AND d.status != 'paid') as total_debt
                            FROM stores s
                            JOIN sales sl ON s.id = sl.store_id
                            WHERE sl.created_by = ?
                            GROUP BY s.id
                            ORDER BY s.name";
    $stmt = $conn->prepare($worker_vehicles_query);
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $worker_vehicles = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get total uncollected amount
    $stmt = $conn->prepare("SELECT SUM(amount) - SUM(forwarded_to_admin) as total_uncollected FROM payments WHERE collected_by = ?");
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_uncollected = $result->fetch_assoc()['total_uncollected'] ?? 0;
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
    $stmt = $conn->prepare("SELECT p.*, d.sale_id, c.full_name as customer_name, s.store_id
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

// Get payment logs for workers
if (!isAdmin()) {
    $worker_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT 
                                DATE(p.forwarded_date) as forward_date,
                                SUM(p.forwarded_to_admin) as total_forwarded,
                                COUNT(*) as payment_count
                            FROM payments p
                            WHERE p.collected_by = ? AND p.forwarded_to_admin > 0
                            GROUP BY DATE(p.forwarded_date)
                            ORDER BY p.forwarded_date DESC
                            LIMIT 10");
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $forwarding_logs = $result->fetch_all(MYSQLI_ASSOC);
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
                    <p class="stat-value"><?php echo formatCurrency($stats['my_uncollected'] ?? 0); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!isAdmin() && !empty($worker_vehicles)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>My Assigned Vehicles</h2>
                </div>
                <div class="card-body">
                    <?php foreach($worker_vehicles as $vehicle): ?>
                        <div class="vehicle-card">
                            <h4><?php echo sanitize($vehicle['name']); ?> (<?php echo ucfirst($vehicle['type']); ?>)</h4>
                            <div class="flex-between">
                                <div>
                                    <p><strong>Debtors:</strong> <?php echo $vehicle['debtor_count']; ?></p>
                                </div>
                                <div>
                                    <p><strong>Outstanding Debt:</strong> <?php echo formatCurrency($vehicle['total_debt'] ?? 0); ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="debts.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-sm btn-info">View Debts</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!isAdmin()): ?>
            <div class="summary-card">
                <h3>Payment Collections Summary</h3>
                <div>
                    <p><strong>Total Collected:</strong> <?php echo formatCurrency($stats['my_uncollected'] + ($total_forwarded ?? 0)); ?></p>
                    <p><strong>Total Forwarded to Admin:</strong> <?php echo formatCurrency($total_forwarded ?? 0); ?></p>
                    <p><strong>Pending to Forward:</strong> <?php echo formatCurrency($stats['my_uncollected'] ?? 0); ?></p>
                </div>
                
                <?php if (isset($stats['my_uncollected']) && $stats['my_uncollected'] > 0): ?>
                <div class="forward-total-btn">
                    <a href="forward_payments.php" class="btn btn-primary">Forward Payments to Admin</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="recent-section">
                <h2>Recent Collections</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Vehicle</th>
                            <th>Collected By</th>
                            <th>Date</th>
                            <th>Forwarded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_payments)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No recent collections found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo sanitize($payment['customer_name']); ?></td>
                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                    <td><?php echo getStoreName($conn, $payment['store_id'] ?? 0); ?></td>
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
            
            <?php if (!isAdmin() && !empty($forwarding_logs)): ?>
            <div class="transaction-log">
                <h3>Payment Forwarding History</h3>
                <?php foreach($forwarding_logs as $log): ?>
                <div class="log-item">
                    <p><strong>Date:</strong> <span class="log-date"><?php echo date('M d, Y', strtotime($log['forward_date'])); ?></span></p>
                    <p><strong>Amount Forwarded:</strong> <?php echo formatCurrency($log['total_forwarded']); ?></p>
                    <p><strong>Payments Included:</strong> <?php echo $log['payment_count']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
