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

// Get customer ID from URL
$customerId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if customer exists
if (!$customerId) {
    header("Location: customers.php");
    exit();
}

// Get customer details
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = :id");
$stmt->bindParam(':id', $customerId);
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: customers.php");
    exit();
}

// Get customer sales
$stmt = $conn->prepare("
    SELECT s.*, v.license_plate, 
        CASE 
            WHEN s.payment_type = 'cash' THEN 'Paid'
            WHEN p.amount_paid >= s.amount THEN 'Paid'
            WHEN p.amount_paid > 0 THEN 'Partial'
            ELSE 'Unpaid'
        END as status,
        COALESCE(p.amount_paid, 0) as amount_paid,
        (s.amount - COALESCE(p.amount_paid, 0)) as balance
    FROM sales s
    JOIN vehicles v ON s.vehicle_id = v.id
    LEFT JOIN (
        SELECT debt_id, SUM(amount) as amount_paid 
        FROM payments 
        GROUP BY debt_id
    ) p ON s.id = p.debt_id
    WHERE s.customer_id = :customer_id
    ORDER BY s.date DESC
");
$stmt->bindParam(':customer_id', $customerId);
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Customer Details: <?php echo $customer['name']; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="customers.php" class="btn btn-sm btn-outline-secondary">Back to Customers</a>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th>Name:</th>
                                        <td><?php echo $customer['name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo $customer['phone'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Created:</th>
                                        <td><?php echo formatDate($customer['created_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Sales:</th>
                                        <td>
                                            <?php 
                                            $totalSales = array_sum(array_column($sales, 'amount'));
                                            echo formatCurrency($totalSales);
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Outstanding Debt:</th>
                                        <td>
                                            <?php 
                                            $outstandingDebt = array_sum(array_column($sales, 'balance'));
                                            if ($outstandingDebt > 0) {
                                                echo '<span class="text-danger">' . formatCurrency($outstandingDebt) . '</span>';
                                            } else {
                                                echo '<span class="text-success">No debt</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Sales History</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Vehicle</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Paid</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sales as $sale): ?>
                                            <tr>
                                                <td><?php echo formatDate($sale['date']); ?></td>
                                                <td><?php echo $sale['license_plate']; ?></td>
                                                <td><?php echo ucfirst($sale['payment_type']); ?></td>
                                                <td><?php echo formatCurrency($sale['amount']); ?></td>
                                                <td><?php echo formatCurrency($sale['amount_paid']); ?></td>
                                                <td><?php echo formatCurrency($sale['balance']); ?></td>
                                                <td>
                                                    <?php 
                                                    $statusClass = 'bg-secondary';
                                                    if ($sale['status'] == 'Paid') {
                                                        $statusClass = 'bg-success';
                                                    } elseif ($sale['status'] == 'Partial') {
                                                        $statusClass = 'bg-warning';
                                                    } elseif ($sale['status'] == 'Unpaid') {
                                                        $statusClass = 'bg-danger';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $sale['status']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($sale['payment_type'] == 'credit' && $sale['balance'] > 0): ?>
                                                    <a href="debt-details.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-info">Debt Details  > 0): ?>
                                                    <a href="debt-details.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-info">Debt Details</a>
                                                    <?php endif; ?>
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
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Feather icons
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
    </script>
</body>
</html>
