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

// Get date range parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Get profit and loss data
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN payment_type = 'cash' THEN amount ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_type = 'credit' THEN amount ELSE 0 END) as credit_sales,
        SUM(amount) as total_sales
    FROM sales
    WHERE date BETWEEN :start_date AND :end_date
");
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$salesData = $stmt->fetch(PDO::FETCH_ASSOC);

// Get expenses data
$stmt = $conn->prepare("
    SELECT 
        et.name as expense_type,
        SUM(e.amount) as total_amount
    FROM expenses e
    JOIN expense_types et ON e.expense_type_id = et.id
    WHERE e.date BETWEEN :start_date AND :end_date
    GROUP BY et.name
    ORDER BY total_amount DESC
");
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$expensesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total expenses
$totalExpenses = array_sum(array_column($expensesData, 'total_amount'));

// Get lost/damaged bags data
$stmt = $conn->prepare("
    SELECT 
        SUM(ld.bags) as total_bags,
        SUM(ld.bags * v.bag_value) as total_value
    FROM lost_damaged ld
    JOIN vehicles v ON ld.vehicle_id = v.id
    WHERE ld.date BETWEEN :start_date AND :end_date
");
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$lostData = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate profit
$totalRevenue = $salesData['total_sales'] ?? 0;
$totalCosts = $totalExpenses + ($lostData['total_value'] ?? 0);
$netProfit = $totalRevenue - $totalCosts;
$profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

// Get monthly profit data for chart
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(s.date, '%Y-%m') as month,
        SUM(s.amount) as revenue,
        COALESCE(SUM(e.expense_amount), 0) as expenses,
        COALESCE(SUM(l.lost_value), 0) as lost_value,
        SUM(s.amount) - COALESCE(SUM(e.expense_amount), 0) - COALESCE(SUM(l.lost_value), 0) as profit
    FROM (
        SELECT date, SUM(amount) as amount
        FROM sales
        WHERE date >= DATE_SUB(:end_date, INTERVAL 11 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
    ) s
    LEFT JOIN (
        SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as expense_amount
        FROM expenses
        WHERE date >= DATE_SUB(:end_date, INTERVAL 11 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
    ) e ON DATE_FORMAT(s.date, '%Y-%m') = e.month
    LEFT JOIN (
        SELECT DATE_FORMAT(ld.date, '%Y-%m') as month, SUM(ld.bags * v.bag_value) as lost_value
        FROM lost_damaged ld
        JOIN vehicles v ON ld.vehicle_id = v.id
        WHERE ld.date >= DATE_SUB(:end_date, INTERVAL 11 MONTH)
        GROUP BY DATE_FORMAT(ld.date, '%Y-%m')
    ) l ON DATE_FORMAT(s.date, '%Y-%m') = l.month
    GROUP BY DATE_FORMAT(s.date, '%Y-%m')
    ORDER BY month
");
$stmt->bindParam(':end_date', $endDate);
$stmt->execute();
$monthlyProfitData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format monthly profit data for chart
$months = [];
$revenues = [];
$expenses = [];
$profits = [];

foreach ($monthlyProfitData as $data) {
    $monthName = date('M Y', strtotime($data['month'] . '-01'));
    $months[] = $monthName;
    $revenues[] = floatval($data['revenue']);
    $expenses[] = floatval($data['expenses'] + $data['lost_value']);
    $profits[] = floatval($data['profit']);
}

// Get expense breakdown data for pie chart
$expenseLabels = [];
$expenseValues = [];
$expenseColors = ['#009ef7', '#50cd89', '#ffc700', '#f1416c', '#7239ea', '#181c32'];

foreach ($expensesData as $index => $expense) {
    $expenseLabels[] = $expense['expense_type'];
    $expenseValues[] = floatval($expense['total_amount']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght  rel="stylesheet">
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
                        <h1 class="h2 fw-bold">Profit & Loss</h1>
                        <p class="text-muted">Financial performance analysis</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form method="get" class="row g-3">
                            <div class="col-auto">
                                <label for="start_date" class="visually-hidden">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-auto">
                                <label for="end_date" class="visually-hidden">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card primary">
                                    <div class="stat-icon">
                                        <i data-feather="dollar-sign"></i>
                                    </div>
                                    <div class="stat-title">Total Revenue</div>
                                    <div class="stat-value"><?php echo formatCurrency($totalRevenue); ?></div>
                                    <div class="stat-desc">
                                        Cash: <?php echo formatCurrency($salesData['cash_sales'] ?? 0); ?> | 
                                        Credit: <?php echo formatCurrency($salesData['credit_sales'] ?? 0); ?>
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
                                        <i data-feather="trending-down"></i>
                                    </div>
                                    <div class="stat-title">Total Expenses</div>
                                    <div class="stat-value"><?php echo formatCurrency($totalCosts); ?></div>
                                    <div class="stat-desc">
                                        Expenses: <?php echo formatCurrency($totalExpenses); ?> | 
                                        Lost: <?php echo formatCurrency($lostData['total_value'] ?? 0); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card <?php echo $netProfit >= 0 ? 'success' : 'danger'; ?>">
                                    <div class="stat-icon">
                                        <i data-feather="<?php echo $netProfit >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                                    </div>
                                    <div class="stat-title">Net Profit</div>
                                    <div class="stat-value"><?php echo formatCurrency($netProfit); ?></div>
                                    <div class="stat-desc">
                                        <?php echo $netProfit >= 0 ? 'Profit' : 'Loss'; ?> for the selected period
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card <?php echo $profitMargin >= 0 ? 'info' : 'danger'; ?>">
                                    <div class="stat-icon">
                                        <i data-feather="percent"></i>
                                    </div>
                                    <div class="stat-title">Profit Margin</div>
                                    <div class="stat-value"><?php echo number_format($profitMargin, 2); ?>%</div>
                                    <div class="stat-desc">
                                        Net profit as percentage of revenue
                                    </div>
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
                                <h5 class="mb-0">Monthly Profit Overview</h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="profitChartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i data-feather="more-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profitChartDropdown">
                                        <li><a class="dropdown-item" href="#">Last 6 Months</a></li>
                                        <li><a class="dropdown-item" href="#">Last 12 Months</a></li>
                                        <li><a class="dropdown-item" href="#">Export Data</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="profitChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Expense Breakdown</h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="expenseChartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i data-feather="more-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="expenseChartDropdown">
                                        <li><a class="dropdown-item" href="#">View Details</a></li>
                                        <li><a class="dropdown-item" href="#">Export Data</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="expenseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expense Details -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Expense Details</h5>
                                <a href="#" class="btn btn-sm btn-primary">Export to Excel</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Expense Type</th>
                                                <th>Amount</th>
                                                <th>Percentage</th>
                                                <th>Visualization</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expensesData as $expense): 
                                                $percentage = $totalExpenses > 0 ? ($expense['total_amount'] / $totalExpenses) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $expense['expense_type']; ?></td>
                                                <td><?php echo formatCurrency($expense['total_amount']); ?></td>
                                                <td><?php echo number_format($percentage, 2); ?>%</td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($expensesData)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-3">No expenses found for the selected period</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <td>Total</td>
                                                <td><?php echo formatCurrency($totalExpenses); ?></td>
                                                <td>100%</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
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
            
            // Profit Chart
            const profitCtx = document.getElementById('profitChart').getContext('2d');
            const profitChart = new Chart(profitCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: <?php echo json_encode($revenues); ?>,
                            backgroundColor: 'rgba(0, 158, 247, 0.1)',
                            borderColor: '#009ef7',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Expenses',
                            data: <?php echo json_encode($expenses); ?>,
                            backgroundColor: 'rgba(241, 65, 108, 0.1)',
                            borderColor: '#f1416c',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Profit',
                            data: <?php echo json_encode($profits); ?>,
                            backgroundColor: 'rgba(80, 205, 137, 0.1)',
                            borderColor: '#50cd89',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
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
            
            // Expense Chart
            const expenseCtx = document.getElementById('expenseChart').getContext('2d');
            const expenseChart = new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($expenseLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($expenseValues); ?>,
                        backgroundColor: <?php echo json_encode($expenseColors); ?>,
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
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return context.label + ': <?php echo CURRENCY; ?> ' + value.toLocaleString() + ' (' + percentage + '%)';
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
