<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/log_activity.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get user role - add default value to prevent undefined array key error
$userRole = $_SESSION['user_role'] ?? 'worker';
$userId = $_SESSION['user_id'] ?? 0;

// Get dashboard data
$vehicleData = [];
if ($userRole == 'admin') {
    $vehicleData = getAllVehicles($conn);
} else {
    $vehicleData = getWorkerVehicles($conn, $userId);
}

// Get total sales, debts, and held funds
$totalSales = getTotalSales($conn);
$totalDebts = getTotalDebts($conn);
$totalHeldFunds = getTotalHeldFunds($conn);

// Get recent transactions
$transactions = getRecentTransactions($conn, 10);

// Get recent activity logs
$activityLogs = getRecentLogs($conn, 5);

// Get monthly sales data for chart
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        SUM(CASE WHEN payment_type = 'cash' THEN amount ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_type = 'credit' THEN amount ELSE 0 END) as credit_sales
    FROM sales
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
");
$stmt->execute();
$monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format monthly sales data for chart
$months = [];
$cashSales = [];
$creditSales = [];

foreach ($monthlySales as $data) {
    $monthName = date('M Y', strtotime($data['month'] . '-01'));
    $months[] = $monthName;
    $cashSales[] = floatval($data['cash_sales']);
    $creditSales[] = floatval($data['cash_sales']);
    $creditSales[] = floatval($data['credit_sales']);
}

// Get vehicle performance data for chart
$vehicleLabels = [];
$vehicleProfits = [];
$vehicleColors = ['#009ef7', '#50cd89', '#ffc700', '#f1416c', '#7239ea', '#181c32'];

foreach (array_slice($vehicleData, 0, 6) as $index => $vehicle) {
    $vehicleLabels[] = $vehicle['license_plate'];
    $vehicleProfits[] = floatval($vehicle['profit'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <h1 class="h2 fw-bold">Dashboard</h1>
                        <p class="text-muted">Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="reports.php" class="btn btn-sm btn-outline-primary">
                                <i data-feather="file-text" class="me-1"></i> Generate Reports
                            </a>
                            <a href="import.php" class="btn btn-sm btn-outline-primary">
                                <i data-feather="upload" class="me-1"></i> Import Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card primary">
                                    <div class="stat-icon">
                                        <i data-feather="truck"></i>
                                    </div>
                                    <div class="stat-title">Total Vehicles</div>
                                    <div class="stat-value" style="font-size: 1.3rem;"><?php echo count($vehicleData); ?></div>
                                    <div class="stat-desc">Active vehicles in the system</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card success">
                                    <div class="stat-icon">
                                        <i data-feather="dollar-sign"></i>
                                    </div>
                                    <div class="stat-title">Cash Sales</div>
                                    <?php
                                    // Get maximum cash sales
                                    $stmt = $conn->prepare("SELECT MAX(amount) as max_sale FROM sales WHERE payment_type = 'cash'");
                                    $stmt->execute();
                                    $maxSale = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $maxCashSale = $maxSale['max_sale'] ?? 0;
                                    
                                    // Get total cash sales
                                    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM sales WHERE payment_type = 'cash'");
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $totalCashSales = $result['total'] ?? 0;
                                    ?>
                                    <div class="stat-value" style="font-size: 1.3rem;"><?php echo formatCurrency($totalCashSales); ?></div>
                                    <div class="stat-desc">
                                        Max: <?php echo formatCurrency($maxCashSale); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card warning">
                                    <div class="stat-icon">
                                        <i data-feather="alert-circle"></i>
                                    </div>
                                    <div class="stat-title">Outstanding Debts</div>
                                    <div class="stat-value" style="font-size: 1.3rem;"><?php echo formatCurrency($totalDebts); ?></div>
                                    <div class="stat-desc">
                                        <a href="debts.php" class="text-white text-decoration-none">View All Debts</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card danger">
                                    <div class="stat-icon">
                                        <i data-feather="credit-card"></i>
                                    </div>
                                    <div class="stat-title">Held Funds</div>
                                    <div class="stat-value" style="font-size: 1.3rem;"><?php echo formatCurrency($totalHeldFunds); ?></div>
                                    <div class="stat-desc">Money held by workers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Monthly Sales Overview</h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="salesChartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i data-feather="more-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="salesChartDropdown">
                                        <li><a class="dropdown-item" href="#">Last 6 Months</a></li>
                                        <li><a class="dropdown-item" href="#">Last 12 Months</a></li>
                                        <li><a class="dropdown-item" href="#">Export Data</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Vehicle Profitability</h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="vehicleChartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i data-feather="more-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="vehicleChartDropdown">
                                        <li><a class="dropdown-item" href="#">Top 5 Vehicles</a></li>
                                        <li><a class="dropdown-item" href="#">All Vehicles</a></li>
                                        <li><a class="dropdown-item" href="#">Export Data</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="vehicleChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Performance and Recent Transactions -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Vehicle Performance</h5>
                                <a href="vehicles.php" class="btn btn-sm btn-primary">View All Vehicles</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>License Plate</th>
                                                <th>Bags Loaded</th>
                                                <th>Cash Sales</th>
                                                <th>Credit Sales</th>
                                                <th>Expenses</th>
                                                <th>Profit</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($vehicleData, 0, 5) as $vehicle): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                            <i data-feather="truck" style="width: 16px; height: 16px;"></i>
                                                        </div>
                                                        <span class="fw-medium"><?php echo $vehicle['license_plate']; ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo $vehicle['bags_loaded']; ?></td>
                                                <td><?php echo formatCurrency($vehicle['cash_sales'] ?? 0); ?></td>
                                                <td><?php echo formatCurrency($vehicle['credit_sales'] ?? 0); ?></td>
                                                <td><?php echo formatCurrency($vehicle['expenses'] ?? 0); ?></td>
                                                <td>
                                                    <?php 
                                                    $profit = $vehicle['profit'] ?? 0;
                                                    $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
                                                    echo '<span class="' . $profitClass . '">' . formatCurrency($profit) . '</span>';
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="vehicle-details.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-sm btn-primary">Details</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Activity</h5>
                                <?php if ($userRole == 'admin'): ?>
                                <a href="activity-logs.php" class="btn btn-sm btn-primary">View All</a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <ul class="activity-log">
                                    <?php foreach ($activityLogs as $log): ?>
                                    <li class="<?php echo $log['type']; ?>">
                                        <div class="log-time"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></div>
                                        <div class="log-message"><?php echo $log['action']; ?></div>
                                        <div class="log-details">
                                            <span class="fw-medium"><?php echo $log['user_name']; ?></span> - 
                                            <?php echo $log['details']; ?>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($activityLogs)): ?>
                                    <li class="text-center py-3">
                                        <div class="text-muted">No recent activity</div>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add this after the "Recent Activity" card in the dashboard -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Messages</h5>
                            <a href="messages.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php 
                            $recentConversations = getRecentConversations($conn, $userId);
                            if (!empty($recentConversations)): 
                            ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach (array_slice($recentConversations, 0, 5) as $conversation): ?>
                                        <li class="list-group-item px-0">
                                            <a href="messages.php?user=<?php echo $conversation['user_id']; ?>" class="d-flex align-items-center text-decoration-none">
                                                <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                    <i data-feather="user" style="width: 20px; height: 20px;"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-2">
                                                    <div class="d-flex justify-content-between">
                                                        <span class="fw-medium"><?php echo $conversation['user_name']; ?></span>
                                                        <small class="text-muted"><?php echo date('H:i', strtotime($conversation['last_message_time'])); ?></small>
                                                    </div>
                                                    <div class="small text-truncate <?php echo $conversation['unread_count'] > 0 ? 'fw-medium' : 'text-muted'; ?>">
                                                        <?php 
                                                        echo strlen($conversation['last_message']) > 30 ? 
                                                            substr($conversation['last_message'], 0, 30) . '...' : 
                                                            $conversation['last_message']; 
                                                        ?>
                                                    </div>
                                                </div>
                                                <?php if ($conversation['unread_count'] > 0): ?>
                                                    <span class="badge bg-primary rounded-pill ms-2"><?php echo $conversation['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i data-feather="message-square" style="width: 40px; height: 40px; opacity: 0.5;"></i>
                                    <p class="mt-2">No messages yet</p>
                                    <a href="messages.php" class="btn btn-sm btn-primary">Start a conversation</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Transactions</h5>
                                <a href="reports.php" class="btn btn-sm btn-primary">View All Transactions</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Vehicle</th>
                                                <th>Type</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo formatDate($transaction['date']); ?></td>
                                                <td><?php echo $transaction['vehicle']; ?></td>
                                                <td>
                                                    <?php if ($transaction['type'] == 'cash'): ?>
                                                    <span class="badge bg-success">Cash</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-warning">Credit</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $transaction['customer']; ?></td>
                                                <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($transaction['status']); ?>">
                                                        <?php echo $transaction['status']; ?>
                                                    </span>
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
            
            // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(salesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [
                        {
                            label: 'Cash Sales',
                            data: <?php echo json_encode($cashSales); ?>,
                            backgroundColor: '#009ef7',
                            borderColor: '#009ef7',
                            borderWidth: 1
                        },
                        {
                            label: 'Credit Sales',
                            data: <?php echo json_encode($creditSales); ?>,
                            backgroundColor: '#ffc700',
                            borderColor: '#ffc700',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '<?php echo CURRENCY; ?> ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': <?php echo CURRENCY; ?> ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Vehicle Chart
            const vehicleCtx = document.getElementById('vehicleChart').getContext('2d');
            const vehicleChart = new Chart(vehicleCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($vehicleLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($vehicleProfits); ?>,
                        backgroundColor: <?php echo json_encode($vehicleColors); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': <?php echo CURRENCY; ?> ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
