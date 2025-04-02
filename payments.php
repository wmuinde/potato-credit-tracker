
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

$success = $error = '';

// Handle forwarding payment to admin
if (isset($_GET['action']) && $_GET['action'] === 'forward' && isset($_GET['id'])) {
    $payment_id = $_GET['id'];
    $forward_amount = $_GET['amount'] ?? 0;
    
    if (isWorker()) {
        // Get payment details
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? AND collected_by = ?");
        $worker_id = $_SESSION['user_id'];
        $stmt->bind_param("ii", $payment_id, $worker_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        if ($payment) {
            $remaining = $payment['amount'] - $payment['forwarded_to_admin'];
            
            if ($forward_amount <= 0) {
                $error = "Forward amount must be greater than zero.";
            } else if ($forward_amount > $remaining) {
                $error = "Forward amount cannot exceed remaining amount.";
            } else {
                $new_forwarded = $payment['forwarded_to_admin'] + $forward_amount;
                $current_date = date('Y-m-d H:i:s');
                
                $stmt = $conn->prepare("UPDATE payments SET forwarded_to_admin = ?, forwarded_date = ? WHERE id = ?");
                $stmt->bind_param("dsi", $new_forwarded, $current_date, $payment_id);
                
                if ($stmt->execute()) {
                    $success = "Amount forwarded to admin successfully.";
                } else {
                    $error = "Failed to forward amount: " . $conn->error;
                }
            }
        } else {
            $error = "Invalid payment or you are not authorized.";
        }
    } else {
        $error = "You are not authorized to perform this action.";
    }
}

// Get all payments with related information
if (isAdmin()) {
    // Admins see all payments
    $query = "SELECT p.*, d.sale_id, d.status as debt_status, 
              s.customer_id, s.bags_quantity, s.price_per_bag, 
              c.full_name as customer_name, u.full_name as collected_by_name 
              FROM payments p 
              JOIN debts d ON p.debt_id = d.id 
              JOIN sales s ON d.sale_id = s.id 
              JOIN customers c ON s.customer_id = c.id 
              JOIN users u ON p.collected_by = u.id 
              ORDER BY p.collection_date DESC";
    $payments = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
} else {
    // Workers see only their collections
    $worker_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT p.*, d.sale_id, d.status as debt_status, 
                           s.customer_id, s.bags_quantity, s.price_per_bag, 
                           c.full_name as customer_name, u.full_name as collected_by_name 
                           FROM payments p 
                           JOIN debts d ON p.debt_id = d.id 
                           JOIN sales s ON d.sale_id = s.id 
                           JOIN customers c ON s.customer_id = c.id 
                           JOIN users u ON p.collected_by = u.id 
                           WHERE p.collected_by = ? 
                           ORDER BY p.collection_date DESC");
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Payment Collections</h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card-tools mb-4">
                <a href="debts.php" class="btn btn-primary">Record New Payment</a>
                <input type="text" id="paymentsSearch" placeholder="Search payments..." class="search-input">
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>All Payments</h2>
                </div>
                <div class="card-body">
                    <table class="data-table" id="paymentsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Collected By</th>
                                <th>Forwarded to Admin</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No payments found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payment['collection_date'])); ?></td>
                                        <td>
                                            <a href="customer_details.php?id=<?php echo $payment['customer_id']; ?>">
                                                <?php echo sanitize($payment['customer_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo sanitize($payment['collected_by_name']); ?></td>
                                        <td>
                                            <?php if ($payment['forwarded_to_admin'] >= $payment['amount']): ?>
                                                <span class="badge success">Fully Forwarded</span>
                                            <?php else: ?>
                                                <span class="badge warning">
                                                    <?php echo formatCurrency($payment['forwarded_to_admin']); ?> / <?php echo formatCurrency($payment['amount']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo sanitize($payment['notes'] ?? '-'); ?></td>
                                        <td>
                                            <?php if (isWorker() && $_SESSION['user_id'] == $payment['collected_by'] && $payment['forwarded_to_admin'] < $payment['amount']): ?>
                                                <button class="btn btn-sm btn-success" onclick="showForwardForm(<?php echo $payment['id']; ?>, <?php echo $payment['amount'] - $payment['forwarded_to_admin']; ?>)">Forward to Admin</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Forward Payment Modal -->
            <div id="forwardModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>Forward Payment to Admin</h3>
                    <form id="forwardForm" action="" method="get">
                        <input type="hidden" name="action" value="forward">
                        <input type="hidden" id="forward_id" name="id" value="">
                        
                        <div class="form-group">
                            <label for="amount">Amount to Forward</label>
                            <input type="number" id="forward_amount" name="amount" step="0.01" min="0.01" required>
                            <small>Maximum: <span id="max_amount"></span></small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Forward</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            width: 80%;
            max-width: 500px;
        }
    </style>
    
    <script src="assets/js/script.js"></script>
    <script>
        // Simple search functionality
        document.getElementById('paymentsSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('paymentsTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(searchTerm) > -1) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        });
        
        // Forward payment modal
        function showForwardForm(id, maxAmount) {
            document.getElementById('forward_id').value = id;
            document.getElementById('max_amount').textContent = formatCurrency(maxAmount);
            document.getElementById('forward_amount').max = maxAmount;
            document.getElementById('forward_amount').value = maxAmount;
            document.getElementById('forwardModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('forwardModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('forwardModal');
            if (event.target === modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>
