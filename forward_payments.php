
<?php
require_once 'config.php';

// Check if user is worker
checkAccess('worker');

$success = $error = '';
$worker_id = $_SESSION['user_id'];

// Get worker's total uncollected amount
$stmt = $conn->prepare("SELECT 
                         SUM(amount) as total_collected, 
                         SUM(forwarded_to_admin) as total_forwarded,
                         SUM(amount) - SUM(forwarded_to_admin) as total_uncollected 
                       FROM payments 
                       WHERE collected_by = ?");
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();
$summary = $result->fetch_assoc();

// Get breakdown by vehicle
$stmt = $conn->prepare("SELECT 
                         s.store_id, 
                         st.name as vehicle_name,
                         st.type as vehicle_type,
                         SUM(p.amount) as collected, 
                         SUM(p.forwarded_to_admin) as forwarded,
                         SUM(p.amount) - SUM(p.forwarded_to_admin) as uncollected
                       FROM payments p
                       JOIN debts d ON p.debt_id = d.id
                       JOIN sales s ON d.sale_id = s.id
                       JOIN stores st ON s.store_id = st.id
                       WHERE p.collected_by = ?
                       GROUP BY s.store_id
                       ORDER BY st.name");
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle_breakdown = $result->fetch_all(MYSQLI_ASSOC);

// Ensure payment_logs table has notes column
$conn->query("ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS notes TEXT");

// Get recent forwarding history
$stmt = $conn->prepare("SELECT 
                         pl.*, 
                         DATE(pl.forwarded_date) as forward_date,
                         u.full_name as worker_name
                       FROM payment_logs pl
                       JOIN users u ON pl.worker_id = u.id
                       WHERE pl.worker_id = ?
                       ORDER BY pl.forwarded_date DESC
                       LIMIT 10");
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();
$forwarding_logs = $result->fetch_all(MYSQLI_ASSOC);

// Handle forwarding total payments to admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_amount'])) {
    $forward_amount = floatval($_POST['forward_amount'] ?? 0);
    $total_uncollected = $summary['total_uncollected'] ?? 0;
    $notes = trim($_POST['notes'] ?? '');
    
    if ($forward_amount <= 0) {
        $error = "Forward amount must be greater than zero.";
    } else if ($forward_amount > $total_uncollected) {
        $error = "Forward amount cannot exceed remaining uncollected amount of " . formatCurrency($total_uncollected);
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get all payments with uncollected amounts
            $stmt = $conn->prepare("SELECT id, amount, forwarded_to_admin FROM payments WHERE collected_by = ? AND forwarded_to_admin < amount ORDER BY collection_date ASC");
            $stmt->bind_param("i", $worker_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $payments = $result->fetch_all(MYSQLI_ASSOC);
            
            $remaining_to_forward = $forward_amount;
            $current_date = date('Y-m-d H:i:s');
            
            // Update each payment until we've forwarded the total amount
            foreach ($payments as $payment) {
                $payment_id = $payment['id'];
                $payment_remaining = $payment['amount'] - $payment['forwarded_to_admin'];
                
                if ($remaining_to_forward <= 0) {
                    break;
                }
                
                $amount_to_forward = min($payment_remaining, $remaining_to_forward);
                $new_forwarded = $payment['forwarded_to_admin'] + $amount_to_forward;
                
                $update_stmt = $conn->prepare("UPDATE payments SET forwarded_to_admin = ?, forwarded_date = ? WHERE id = ?");
                $update_stmt->bind_param("dsi", $new_forwarded, $current_date, $payment_id);
                $update_stmt->execute();
                
                $remaining_to_forward -= $amount_to_forward;
            }
            
            // Log the transaction with notes
            $log_sql = "INSERT INTO payment_logs (worker_id, total_amount, forwarded_date, notes) VALUES (?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("idss", $worker_id, $forward_amount, $current_date, $notes);
            $log_stmt->execute();
            
            $conn->commit();
            $success = "Total amount of " . formatCurrency($forward_amount) . " forwarded to admin successfully.";
            
            // Refresh the data
            header("Location: forward_payments.php?success=" . urlencode($success));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to forward amount: " . $e->getMessage();
        }
    }
}

// Display success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Create payment_logs table if it doesn't exist
$create_logs_table = "CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    forwarded_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (worker_id) REFERENCES users(id)
)";
$conn->query($create_logs_table);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forward Payments - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Forward Payments to Admin</h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="summary-card">
                <h3>Payment Collections Summary</h3>
                <div class="summary-details">
                    <div class="summary-row">
                        <span>Total Collected:</span>
                        <span class="amount"><?php echo formatCurrency($summary['total_collected'] ?? 0); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Total Forwarded to Admin:</span>
                        <span class="amount"><?php echo formatCurrency($summary['total_forwarded'] ?? 0); ?></span>
                    </div>
                    <div class="summary-row highlight">
                        <span>Pending to Forward:</span>
                        <span class="amount"><?php echo formatCurrency($summary['total_uncollected'] ?? 0); ?></span>
                    </div>
                </div>
                
                <?php if (isset($summary['total_uncollected']) && $summary['total_uncollected'] > 0): ?>
                <div class="forward-form">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="forward_amount">Amount to Forward</label>
                            <input type="number" id="forward_amount" name="forward_amount" step="0.01" min="0.01" max="<?php echo $summary['total_uncollected']; ?>" value="<?php echo $summary['total_uncollected']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="2" placeholder="Add any notes about this forwarded payment"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Forward to Admin</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Breakdown by Vehicle</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($vehicle_breakdown)): ?>
                        <p class="text-center">No payment data found</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Total Collected</th>
                                    <th>Total Forwarded</th>
                                    <th>Pending to Forward</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicle_breakdown as $vehicle): ?>
                                    <tr>
                                        <td><?php echo sanitize($vehicle['vehicle_name'] . ' (' . ucfirst($vehicle['vehicle_type']) . ')'); ?></td>
                                        <td><?php echo formatCurrency($vehicle['collected']); ?></td>
                                        <td><?php echo formatCurrency($vehicle['forwarded']); ?></td>
                                        <td><?php echo formatCurrency($vehicle['uncollected']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($forwarding_logs)): ?>
            <div class="transaction-log">
                <h3>Payment Forwarding History</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Forwarded By</th>
                            <th>Amount Forwarded</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forwarding_logs as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($log['forwarded_date'])); ?></td>
                                <td><?php echo sanitize($log['worker_name']); ?></td>
                                <td><?php echo formatCurrency($log['total_amount']); ?></td>
                                <td><?php echo !empty($log['notes']) ? sanitize($log['notes']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
    
    <style>
        .summary-details {
            background-color: #fff;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e8ddcf;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-row.highlight {
            font-weight: bold;
            color: #8B4513;
            font-size: 1.1em;
        }
        
        .amount {
            font-weight: 600;
        }
        
        .forward-form {
            margin-top: 20px;
            background-color: #fff;
            padding: 15px;
            border-radius: 6px;
        }
        
        .summary-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #8B4513;
        }
        
        .summary-card h3 {
            color: #8B4513;
            margin-top: 0;
            border-bottom: 1px solid #e8ddcf;
            padding-bottom: 10px;
        }
        
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #e8ddcf;
            border-radius: 4px;
            resize: vertical;
        }
        
        .transaction-log {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #8B4513;
        }
        
        .transaction-log h3 {
            color: #8B4513;
            margin-top: 0;
            border-bottom: 1px solid #e8ddcf;
            padding-bottom: 10px;
        }
    </style>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
