<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get debt ID from URL
$debtId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if debt exists
if (!$debtId) {
    header("Location: reports.php");
    exit();
}

// Get debt details
$stmt = $conn->prepare("
    SELECT s.*, c.name as customer_name, c.phone as customer_phone, 
        v.license_plate, u.name as worker_name,
        COALESCE(p.amount_paid, 0) as amount_paid,
        (s.amount - COALESCE(p.amount_paid, 0)) as balance
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN vehicles v ON s.vehicle_id = v.id
    JOIN users u ON s.worker_id = u.id
    LEFT JOIN (
        SELECT debt_id, SUM(amount) as amount_paid 
        FROM payments 
        GROUP BY debt_id
    ) p ON s.id = p.debt_id
    WHERE s.id = :id
");
$stmt->bindParam(':id', $debtId);
$stmt->execute();
$debt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$debt) {
    header("Location: reports.php");
    exit();
}

// Get payment history
$payments = getPaymentHistory($conn, $debtId);

// Process add payment form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    
    if (addPayment($conn, $debtId, $_SESSION['user_id'], $amount, $date)) {
        header("Location: debt-details.php?id=$debtId&success=payment_added");
        exit();
    }
}

// Process forward funds form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forward_funds'])) {
    $paymentId = $_POST['payment_id'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $remarks = $_POST['remarks'];
    
    if (forwardFunds($conn, $paymentId, $amount, $date)) {
        header("Location: debt-details.php?id=$debtId&success=funds_forwarded");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debt Details - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Debt Details: <?php echo $debt['customer_name']; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="reports.php" class="btn btn-sm btn-outline-secondary">Back to Reports</a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        $success = $_GET['success'];
                        if ($success == 'payment_added') echo 'Payment added successfully';
                        elseif ($success == 'funds_forwarded') echo 'Funds forwarded successfully';
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Debt Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th>Customer:</th>
                                        <td><?php echo $debt['customer_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo $debt['customer_phone']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Vehicle:</th>
                                        <td><?php echo $debt['license_plate']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Sale Date:</th>
                                        <td><?php echo formatDate($debt['date']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Original Amount:</th>
                                        <td><?php echo formatCurrency($debt['amount']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Paid Amount:</th>
                                        <td><?php echo formatCurrency($debt['amount_paid']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Balance:</th>
                                        <td class="fw-bold"><?php echo formatCurrency($debt['balance']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php if ($debt['balance'] <= 0): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($debt['amount_paid'] > 0): ?>
                                                <span class="badge bg-warning">Partial</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Recorded By:</th>
                                        <td><?php echo $debt['worker_name']; ?></td>
                                    </tr>
                                </table>
                                
                                <?php if ($debt['balance'] > 0): ?>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                                        Record Payment
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Payment History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($payments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Collected By</th>
                                                <th>Forwarded</th>
                                                <th>Held</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): 
                                                // Get forwarded amount
                                                $stmt = $conn->prepare("SELECT SUM(amount) as forwarded FROM forwarded_funds WHERE payment_id = :payment_id");
                                                $stmt->bindParam(':payment_id', $payment['id']);
                                                $stmt->execute();
                                                $forwarded = $stmt->fetch(PDO::FETCH_ASSOC);
                                                $forwardedAmount = $forwarded['forwarded'] ?? 0;
                                                $heldAmount = $payment['amount'] - $forwardedAmount;
                                            ?>
                                            <tr>
                                                <td><?php echo formatDate($payment['date']); ?></td>
                                                <td><?php echo formatCurrency($payment['amount']); ?></td>
                                                <td><?php echo $payment['collected_by']; ?></td>
                                                <td><?php echo formatCurrency($forwardedAmount); ?></td>
                                                <td><?php echo formatCurrency($heldAmount); ?></td>
                                                <td>
                                                    <?php if ($heldAmount > 0 && isAdmin()): ?>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#forwardFundsModal"
                                                            data-payment-id="<?php echo $payment['id']; ?>"
                                                            data-held-amount="<?php echo $heldAmount; ?>">
                                                        Forward
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">No payment history found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPaymentModalLabel">Record Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" max="<?php echo $debt['balance']; ?>" required>
                            <div class="form-text">Maximum amount: <?php echo formatCurrency($debt['balance']); ?></div>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_payment" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Forward Funds Modal -->
    <div class="modal fade" id="forwardFundsModal" tabindex="-1" aria-labelledby="forwardFundsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="forwardFundsModalLabel">Forward Funds</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="payment_id" name="payment_id">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="forward_amount" name="amount" step="0.01" required>
                            <div class="form-text">Maximum amount: <span id="max_amount"></span></div>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="forward_date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="forward_funds" class="btn btn-primary">Forward Funds</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Feather icons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
            
            // Set up forward funds modal
            const forwardFundsModal = document.getElementById('forwardFundsModal');
            if (forwardFundsModal) {
                forwardFundsModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const paymentId = button.getAttribute('data-payment-id');
                    const heldAmount = button.getAttribute('data-held-amount');
                    
                    const paymentIdInput = forwardFundsModal.querySelector('#payment_id');
                    const amountInput = forwardFundsModal.querySelector('#forward_amount');
                    const maxAmountSpan = forwardFundsModal.querySelector('#max_amount');
                    
                    paymentIdInput.value = paymentId;
                    amountInput.max = heldAmount;
                    amountInput.value = heldAmount;
                    maxAmountSpan.textContent = '<?php echo CURRENCY; ?> ' + Number(heldAmount).toFixed(2);
                });
            }
        });
    </script>
</body>
</html>
