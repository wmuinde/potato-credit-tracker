
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

$success = $error = '';
$debt_id = $_GET['debt_id'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;

// Get debt information if debt_id is provided
$debt = null;
if ($debt_id) {
    $stmt = $conn->prepare("SELECT d.*, s.customer_id, s.bags_quantity, s.price_per_bag, s.total_amount, c.full_name as customer_name 
                           FROM debts d 
                           JOIN sales s ON d.sale_id = s.id 
                           JOIN customers c ON s.customer_id = c.id 
                           WHERE d.id = ? AND d.status != 'paid'");
    $stmt->bind_param("i", $debt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $debt = $result->fetch_assoc();
    
    if ($debt) {
        $customer_id = $debt['customer_id'];
    }
}

// Get customer's debts if customer_id is provided
$customer_debts = [];
if ($customer_id) {
    $stmt = $conn->prepare("SELECT d.*, s.bags_quantity, s.price_per_bag, s.total_amount, s.store_id, c.full_name as customer_name 
                           FROM debts d 
                           JOIN sales s ON d.sale_id = s.id 
                           JOIN customers c ON s.customer_id = c.id 
                           WHERE s.customer_id = ? AND d.status != 'paid'");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer_debts = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get customer information
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_debt_id = $_POST['debt_id'] ?? null;
    $amount = $_POST['amount'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    $forward_amount = $_POST['forward_amount'] ?? 0;
    
    // Validate input
    if (empty($post_debt_id) || empty($amount)) {
        $error = "Please fill all required fields.";
    } else {
        $user_id = $_SESSION['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get debt details
            $stmt = $conn->prepare("SELECT amount_due, amount_paid FROM debts WHERE id = ?");
            $stmt->bind_param("i", $post_debt_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $debt_info = $result->fetch_assoc();
            
            $remaining = $debt_info['amount_due'] - $debt_info['amount_paid'];
            
            // Check if payment is valid
            if ($amount <= 0) {
                throw new Exception("Payment amount must be greater than zero.");
            }
            
            if ($amount > $remaining) {
                throw new Exception("Payment amount cannot exceed remaining debt ($remaining).");
            }
            
            // Insert into payments table
            $stmt = $conn->prepare("INSERT INTO payments (debt_id, amount, collected_by, forwarded_to_admin, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("idiis", $post_debt_id, $amount, $user_id, $forward_amount, $notes);
            $stmt->execute();
            
            // Update debt
            $new_paid = $debt_info['amount_paid'] + $amount;
            $status = ($new_paid >= $debt_info['amount_due']) ? 'paid' : 'partial';
            
            $stmt = $conn->prepare("UPDATE debts SET amount_paid = ?, status = ? WHERE id = ?");
            $stmt->bind_param("dsi", $new_paid, $status, $post_debt_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $success = "Payment recorded successfully.";
            
            // Redirect to avoid form resubmission
            if ($status === 'paid') {
                // If debt is fully paid, redirect to customer page
                header("Location: debts.php?success=Payment recorded successfully. Debt fully paid.");
                exit;
            } else {
                // Refresh the page to update debt information
                header("Location: record_payment.php?debt_id=$post_debt_id&success=Payment recorded successfully.");
                exit;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error recording payment: " . $e->getMessage();
        }
    }
}

// Check for success message in GET parameters
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Record Payment</h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($customer)): ?>
            <div class="customer-info">
                <h2>Customer: <?php echo sanitize($customer['full_name']); ?></h2>
                <?php if (!empty($customer['phone'])): ?>
                    <p>Phone: <?php echo sanitize($customer['phone']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($debt): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Record Payment</h2>
                </div>
                <div class="card-body">
                    <div class="debt-details">
                        <p><strong>Customer:</strong> <?php echo sanitize($debt['customer_name']); ?></p>
                        <p><strong>Original Amount:</strong> <?php echo formatCurrency($debt['total_amount']); ?> (<?php echo $debt['bags_quantity']; ?> bags @ <?php echo formatCurrency($debt['price_per_bag']); ?>)</p>
                        <p><strong>Amount Paid:</strong> <?php echo formatCurrency($debt['amount_paid']); ?></p>
                        <p><strong>Remaining:</strong> <?php echo formatCurrency($debt['amount_due'] - $debt['amount_paid']); ?></p>
                    </div>
                    
                    <form method="post" action="">
                        <input type="hidden" name="debt_id" value="<?php echo $debt['id']; ?>">
                        
                        <div class="form-group">
                            <label for="amount">Payment Amount*</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="<?php echo $debt['amount_due'] - $debt['amount_paid']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="forward_amount">Amount Forwarded to Admin</label>
                            <input type="number" id="forward_amount" name="forward_amount" step="0.01" min="0" value="0">
                            <small>Leave 0 if not forwarding to admin immediately</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Record Payment</button>
                            <a href="debts.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php elseif (!empty($customer_debts)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Select Debt to Pay</h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Store/Lorry</th>
                                <th>Bags</th>
                                <th>Original Amount</th>
                                <th>Remaining</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer_debts as $d): ?>
                                <tr>
                                    <td><?php echo getStoreName($conn, $d['store_id']); ?></td>
                                    <td><?php echo $d['bags_quantity']; ?> @ <?php echo formatCurrency($d['price_per_bag']); ?></td>
                                    <td><?php echo formatCurrency($d['total_amount']); ?></td>
                                    <td><?php echo formatCurrency($d['amount_due'] - $d['amount_paid']); ?></td>
                                    <td>
                                        <a href="record_payment.php?debt_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-success">Pay This</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">No debt selected or the customer has no outstanding debts.</div>
            <a href="debts.php" class="btn btn-primary">View All Debts</a>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="assets/js/script.js"></script>
    <script>
        document.getElementById('amount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const forwardAmount = document.getElementById('forward_amount');
            forwardAmount.max = amount;
            
            if (parseFloat(forwardAmount.value) > amount) {
                forwardAmount.value = amount;
            }
        });
    </script>
</body>
</html>
