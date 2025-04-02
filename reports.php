
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

// Get filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;

// Initialize data arrays
$sales_data = [];
$debt_data = [];
$payment_data = [];
$vehicle_stats = [];

// Get list of stores/vehicles
$stores_result = $conn->query("SELECT * FROM stores WHERE status = 'active' ORDER BY name");
$stores = $stores_result->fetch_all(MYSQLI_ASSOC);

// Build query conditions based on user role
$user_condition = "";
if (!isAdmin()) {
    $worker_id = $_SESSION['user_id'];
    $user_condition = " AND s.created_by = $worker_id";
}

// Store filter condition
$store_condition = "";
if ($store_id) {
    $store_condition = " AND s.store_id = $store_id";
}

// Date range condition
$date_condition = " AND s.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

// 1. Get Sales Data
$sales_query = "SELECT 
    s.id,
    s.created_at, 
    s.bags_quantity, 
    s.price_per_bag, 
    s.total_amount, 
    s.payment_type,
    c.full_name as customer_name,
    st.name as store_name,
    st.type as store_type
FROM sales s
LEFT JOIN customers c ON s.customer_id = c.id
JOIN stores st ON s.store_id = st.id
WHERE 1=1 $user_condition $store_condition $date_condition
ORDER BY s.created_at DESC";

$sales_result = $conn->query($sales_query);

if ($sales_result) {
    $sales_data = $sales_result->fetch_all(MYSQLI_ASSOC);
}

// 2. Get Debt Data
$debt_query = "SELECT 
    d.id,
    d.amount_due,
    d.amount_paid,
    d.status,
    d.last_updated,
    s.created_at as sale_date,
    s.bags_quantity,
    s.price_per_bag,
    s.total_amount,
    c.full_name as customer_name,
    st.id as vehicle_id,
    st.name as vehicle_name
FROM debts d
JOIN sales s ON d.sale_id = s.id
JOIN customers c ON s.customer_id = c.id
JOIN stores st ON s.store_id = st.id
WHERE 1=1 $user_condition $store_condition $date_condition
ORDER BY d.last_updated DESC";

$debt_result = $conn->query($debt_query);

if ($debt_result) {
    $debt_data = $debt_result->fetch_all(MYSQLI_ASSOC);
}

// 3. Get Payment Data
$payment_query = "SELECT 
    p.id,
    p.amount,
    p.collection_date,
    p.forwarded_to_admin,
    p.notes,
    c.full_name as customer_name,
    u.full_name as collected_by_name,
    st.id as vehicle_id,
    st.name as vehicle_name
FROM payments p
JOIN debts d ON p.debt_id = d.id
JOIN sales s ON d.sale_id = s.id
JOIN customers c ON s.customer_id = c.id
JOIN users u ON p.collected_by = u.id
JOIN stores st ON s.store_id = st.id
WHERE p.collection_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    $user_condition $store_condition
ORDER BY p.collection_date DESC";

$payment_result = $conn->query($payment_query);

if ($payment_result) {
    $payment_data = $payment_result->fetch_all(MYSQLI_ASSOC);
}

// 4. Get Vehicle Stats
if ($store_id) {
    // Stats for a specific vehicle
    $vehicle_stats_query = "SELECT 
        st.id as vehicle_id,
        st.name as vehicle_name,
        SUM(CASE WHEN s.payment_type = 'cash' THEN s.total_amount ELSE 0 END) as total_cash_sales,
        SUM(CASE WHEN s.payment_type = 'credit' THEN s.total_amount ELSE 0 END) as total_credit_sales,
        SUM(s.bags_quantity) as total_bags,
        COUNT(DISTINCT CASE WHEN s.payment_type = 'credit' THEN s.customer_id END) as debtors_count,
        (SELECT SUM(amount_due - amount_paid) 
         FROM debts d 
         JOIN sales s2 ON d.sale_id = s2.id 
         WHERE s2.store_id = st.id AND d.status != 'paid') as outstanding_debt,
        (SELECT SUM(p.amount) 
         FROM payments p 
         JOIN debts d ON p.debt_id = d.id 
         JOIN sales s3 ON d.sale_id = s3.id 
         WHERE s3.store_id = st.id 
         AND p.collection_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59') as total_payments
    FROM stores st
    LEFT JOIN sales s ON st.id = s.store_id
    WHERE st.id = $store_id
    AND s.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    GROUP BY st.id, st.name";
} else {
    // Stats for all vehicles
    $vehicle_stats_query = "SELECT 
        st.id as vehicle_id,
        st.name as vehicle_name,
        SUM(CASE WHEN s.payment_type = 'cash' THEN s.total_amount ELSE 0 END) as total_cash_sales,
        SUM(CASE WHEN s.payment_type = 'credit' THEN s.total_amount ELSE 0 END) as total_credit_sales,
        SUM(s.bags_quantity) as total_bags,
        COUNT(DISTINCT CASE WHEN s.payment_type = 'credit' THEN s.customer_id END) as debtors_count,
        (SELECT SUM(amount_due - amount_paid) 
         FROM debts d 
         JOIN sales s2 ON d.sale_id = s2.id 
         WHERE s2.store_id = st.id AND d.status != 'paid') as outstanding_debt,
        (SELECT SUM(p.amount) 
         FROM payments p 
         JOIN debts d ON p.debt_id = d.id 
         JOIN sales s3 ON d.sale_id = s3.id 
         WHERE s3.store_id = st.id 
         AND p.collection_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59') as total_payments
    FROM stores st
    LEFT JOIN sales s ON st.id = s.store_id
    WHERE 1=1 $user_condition
    AND s.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    GROUP BY st.id, st.name";
}

$vehicle_stats_result = $conn->query($vehicle_stats_query);

if ($vehicle_stats_result) {
    $vehicle_stats = $vehicle_stats_result->fetch_all(MYSQLI_ASSOC);
}

// Calculate summaries
$total_bags = 0;
$total_cash = 0;
$total_credit = 0;
$total_payments = 0;
$total_outstanding = 0;

foreach ($vehicle_stats as $stat) {
    $total_bags += $stat['total_bags'] ?? 0;
    $total_cash += $stat['total_cash_sales'] ?? 0;
    $total_credit += $stat['total_credit_sales'] ?? 0;
    $total_payments += $stat['total_payments'] ?? 0;
    $total_outstanding += $stat['outstanding_debt'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Reports</h1>
            
            <!-- Report Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Filter Reports</h2>
                </div>
                <div class="card-body">
                    <form action="" method="get" class="row">
                        <div class="form-group" style="display: inline-block; width: 30%; margin-right: 1%;">
                            <label for="start_date">Start Date:</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                        </div>
                        <div class="form-group" style="display: inline-block; width: 30%; margin-right: 1%;">
                            <label for="end_date">End Date:</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                        </div>
                        <div class="form-group" style="display: inline-block; width: 30%;">
                            <label for="store_id">Vehicle:</label>
                            <select id="store_id" name="store_id" class="form-control">
                                <option value="">All Vehicles</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>" <?php if($store_id == $store['id']) echo 'selected'; ?>>
                                        <?php echo sanitize($store['name']); ?> (<?php echo ucfirst($store['type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                            <a href="export_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?><?php if($store_id) echo "&store_id=$store_id"; ?>" 
                               class="btn btn-secondary" style="margin-left: 10px;">
                                Export to CSV
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Summary Section -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Bags Sold</h3>
                    <p class="stat-value"><?php echo number_format($total_bags); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Cash Sales</h3>
                    <p class="stat-value"><?php echo formatCurrency($total_cash); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Credit Sales</h3>
                    <p class="stat-value"><?php echo formatCurrency($total_credit); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Payments Collected</h3>
                    <p class="stat-value"><?php echo formatCurrency($total_payments); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Outstanding Debts</h3>
                    <p class="stat-value"><?php echo formatCurrency($total_outstanding); ?></p>
                </div>
            </div>
            
            <!-- Vehicle Statistics -->
            <div class="card">
                <div class="card-header">
                    <h2>Vehicle Statistics</h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Total Bags</th>
                                <th>Cash Sales</th>
                                <th>Credit Sales</th>
                                <th>Debtors</th>
                                <th>Outstanding</th>
                                <th>Payments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vehicle_stats)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No data available for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vehicle_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo sanitize($stat['vehicle_name']); ?></td>
                                        <td><?php echo number_format($stat['total_bags'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($stat['total_cash_sales'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($stat['total_credit_sales'] ?? 0); ?></td>
                                        <td><?php echo number_format($stat['debtors_count'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($stat['outstanding_debt'] ?? 0); ?></td>
                                        <td><?php echo formatCurrency($stat['total_payments'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Sales List -->
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Sales Transactions</h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Bags</th>
                                <th>Amount</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No sales data for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_data as $sale): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                                        <td>
                                            <?php if ($sale['customer_name']): ?>
                                                <?php echo sanitize($sale['customer_name']); ?>
                                            <?php else: ?>
                                                Cash Customer
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="vehicle-tag"><?php echo sanitize($sale['store_name']); ?></span></td>
                                        <td><?php echo $sale['bags_quantity']; ?> @ <?php echo formatCurrency($sale['price_per_bag']); ?></td>
                                        <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                        <td>
                                            <?php if ($sale['payment_type'] === 'credit'): ?>
                                                <span class="badge warning">Credit</span>
                                            <?php else: ?>
                                                <span class="badge success">Cash</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Debt List -->
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Outstanding Debts</h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Sale Date</th>
                                <th>Bags</th>
                                <th>Total Amount</th>
                                <th>Amount Paid</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($debt_data)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No outstanding debts for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($debt_data as $debt): ?>
                                    <tr>
                                        <td><?php echo sanitize($debt['customer_name']); ?></td>
                                        <td><span class="vehicle-tag"><?php echo sanitize($debt['vehicle_name']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($debt['sale_date'])); ?></td>
                                        <td><?php echo $debt['bags_quantity']; ?></td>
                                        <td><?php echo formatCurrency($debt['amount_due']); ?></td>
                                        <td><?php echo formatCurrency($debt['amount_paid']); ?></td>
                                        <td class="fw-bold text-danger">
                                            <?php echo formatCurrency($debt['amount_due'] - $debt['amount_paid']); ?>
                                        </td>
                                        <td>
                                            <?php if ($debt['status'] === 'pending'): ?>
                                                <span class="badge danger">Pending</span>
                                            <?php elseif ($debt['status'] === 'partial'): ?>
                                                <span class="badge warning">Partial</span>
                                            <?php else: ?>
                                                <span class="badge success">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Payments List -->
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Payments Collected</h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Amount</th>
                                <th>Collected By</th>
                                <th>Forwarded to Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payment_data)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No payments for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payment_data as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payment['collection_date'])); ?></td>
                                        <td><?php echo sanitize($payment['customer_name']); ?></td>
                                        <td><span class="vehicle-tag"><?php echo sanitize($payment['vehicle_name']); ?></span></td>
                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo sanitize($payment['collected_by_name']); ?></td>
                                        <td>
                                            <?php if ($payment['forwarded_to_admin'] >= $payment['amount']): ?>
                                                <span class="badge success">All Forwarded</span>
                                            <?php elseif ($payment['forwarded_to_admin'] > 0): ?>
                                                <span class="badge warning">
                                                    <?php echo formatCurrency($payment['forwarded_to_admin']); ?> / <?php echo formatCurrency($payment['amount']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge danger">Not Forwarded</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
