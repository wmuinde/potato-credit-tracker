<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/log_activity.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: index.php");
    exit();
}

// Get worker ID from URL
$workerId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if worker exists
if (!$workerId) {
    header("Location: held-funds.php");
    exit();
}

// Get worker details
$stmt = $conn->prepare("SELECT name FROM users WHERE id = :id AND role = 'worker'");
$stmt->bindParam(':id', $workerId);
$stmt->execute();
$worker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$worker) {
    header("Location: held-funds.php");
    exit();
}

// Get worker held funds
$heldFunds = getWorkerHeldFunds($conn, $workerId);

// Process forward funds form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forward_funds'])) {
    $paymentId = $_POST['payment_id'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    
    if (forwardFunds($conn, $paymentId, $amount, $date)) {
        header("Location: worker-funds.php?id=$workerId&success=funds_forwarded");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Held Funds - Potato Sales Management System</title>
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
                    <h1 class="h2">Held Funds: <?php echo $worker['name']; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="held-funds.php" class="btn btn-sm btn-outline-secondary">Back to Held Funds</a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        $success = $_GET['success'];
                        if ($success == 'funds_forwarded') echo 'Funds forwarded successfully';
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Held Funds Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>Payment Date</th>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Payment Amount</th>
                <th>Forwarded Amount</th>
                <th>Held Amount</th>
                <th>Transfer Method</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($heldFunds as $fund): ?>
            <tr>
                <td><?php echo formatDate($fund['payment_date']); ?></td>
                <td><?php echo $fund['customer']; ?></td>
                <td><?php echo $fund['license_plate']; ?></td>
                <td><?php echo formatCurrency($fund['payment_amount']); ?></td>
                <td><?php echo formatCurrency($fund['forwarded_amount']); ?></td>
                <td><?php echo formatCurrency($fund['held_amount']); ?></td>
                <td>N/A</td>
                <td>
                    <button type="button" class="btn btn-sm btn-warning" 
                            data-bs-toggle="modal" 
                            data-bs-target="#forwardFundsModal"
                            data-payment-id="<?php echo $fund['payment_id']; ?>"
                            data-held-amount="<?php echo $fund['held_amount']; ?>">
                        Forward
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
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
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
