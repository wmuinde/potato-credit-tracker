<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: index.php");
    exit();
}

// Get all workers with held funds
$stmt = $conn->prepare("
    SELECT 
        u.id as worker_id,
        u.name as worker_name,
        COUNT(DISTINCT p.id) as payment_count,
        SUM(p.amount) as total_collected,
        SUM(COALESCE(f.forwarded_amount, 0)) as total_forwarded,
        SUM(p.amount - COALESCE(f.forwarded_amount, 0)) as total_held
    FROM users u
    JOIN payments p ON u.id = p.worker_id
    LEFT JOIN (
        SELECT payment_id, SUM(amount) as forwarded_amount
        FROM forwarded_funds
        GROUP BY payment_id
    ) f ON p.id = f.payment_id
    WHERE u.role = 'worker' AND (p.amount - COALESCE(f.forwarded_amount, 0)) > 0
    GROUP BY u.id, u.name
    ORDER BY total_held DESC
");
$stmt->execute();
$workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Held Funds - Potato Sales Management System</title>
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
                    <h1 class="h2">Held Funds</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Workers with Held Funds</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Worker</th>
                                                <th>Payments</th>
                                                <th>Total Collected</th>
                                                <th>Total Forwarded</th>
                                                <th>Total Held</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($workers as $worker): ?>
                                            <tr>
                                                <td><?php echo $worker['worker_name']; ?></td>
                                                <td><?php echo $worker['payment_count']; ?></td>
                                                <td><?php echo formatCurrency($worker['total_collected']); ?></td>
                                                <td><?php echo formatCurrency($worker['total_forwarded']); ?></td>
                                                <td class="<?php echo $worker['total_held'] > HELD_FUNDS_THRESHOLD ? 'text-danger fw-bold' : ''; ?>">
                                                    <?php echo formatCurrency($worker['total_held']); ?>
                                                    <?php if ($worker['total_held'] > HELD_FUNDS_THRESHOLD): ?>
                                                        <span class="badge bg-danger">Exceeds Threshold</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="worker-funds.php?id=<?php echo $worker['worker_id']; ?>" class="btn btn-sm btn-info">View Details</a>
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
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
