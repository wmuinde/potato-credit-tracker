
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

$success = $error = '';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: stores.php');
    exit;
}

$store_id = (int)$_GET['id'];

// Get store/vehicle details
$stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$store = $result->fetch_assoc();

if (!$store) {
    header('Location: stores.php');
    exit;
}

// Get vehicle statistics
// 1. Total sales
$stmt = $conn->prepare("SELECT 
    SUM(total_amount) AS total_sales,
    SUM(CASE WHEN payment_type = 'cash' THEN total_amount ELSE 0 END) AS cash_sales,
    SUM(CASE WHEN payment_type = 'credit' THEN total_amount ELSE 0 END) AS credit_sales,
    COUNT(DISTINCT CASE WHEN payment_type = 'credit' THEN customer_id END) AS credit_customers_count
    FROM sales WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$sales_stats = $result->fetch_assoc();

// 2. Get expenses
$stmt = $conn->prepare("SELECT SUM(amount) AS total_expenses FROM expenses WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$expenses_stats = $result->fetch_assoc();

// 3. Get outstanding debts
$stmt = $conn->prepare("SELECT 
    COUNT(d.id) AS total_debts,
    SUM(d.amount_due - d.amount_paid) AS total_outstanding
    FROM debts d 
    JOIN sales s ON d.sale_id = s.id
    WHERE s.store_id = ? AND d.status != 'paid'");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$debts_stats = $result->fetch_assoc();

// 4. Get workers associated with this vehicle
$stmt = $conn->prepare("SELECT DISTINCT u.id, u.full_name, 
                       (SELECT COUNT(*) FROM sales WHERE created_by = u.id AND store_id = ?) as sales_count
                       FROM sales s 
                       JOIN users u ON s.created_by = u.id 
                       WHERE s.store_id = ? AND u.role = 'worker'
                       ORDER BY sales_count DESC");
$stmt->bind_param("ii", $store_id, $store_id);
$stmt->execute();
$result = $stmt->get_result();
$workers = $result->fetch_all(MYSQLI_ASSOC);

// 5. Get recent sales
$stmt = $conn->prepare("SELECT s.*, 
    c.full_name as customer_name, 
    u.full_name as recorded_by_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    JOIN users u ON s.created_by = u.id 
    WHERE s.store_id = ?
    ORDER BY s.created_at DESC LIMIT 10");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_sales = $result->fetch_all(MYSQLI_ASSOC);

// 6. Get recent expenses
$stmt = $conn->prepare("SELECT e.*, 
    u.full_name as recorded_by_name 
    FROM expenses e
    JOIN users u ON e.created_by = u.id 
    WHERE e.store_id = ?
    ORDER BY e.created_at DESC LIMIT 10");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_expenses = $result->fetch_all(MYSQLI_ASSOC);

// 7. Get customers with outstanding debts for this vehicle
$stmt = $conn->prepare("SELECT 
    c.id, 
    c.full_name, 
    COUNT(d.id) AS debt_count, 
    SUM(d.amount_due - d.amount_paid) AS total_outstanding
    FROM customers c
    JOIN sales s ON c.id = s.customer_id
    JOIN debts d ON s.id = d.sale_id
    WHERE s.store_id = ? AND d.status != 'paid'
    GROUP BY c.id
    ORDER BY total_outstanding DESC");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
$customer_debts = $result->fetch_all(MYSQLI_ASSOC);

// 8. Calculate primary worker (most sales for this vehicle)
$primary_worker = null;
if (!empty($workers)) {
    $primary_worker = $workers[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($store['name']); ?> Details - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <div class="back-nav">
                <a href="stores.php" class="btn btn-sm" style="background-color: #8B4513; color: white;">&larr; Back to Vehicles</a>
            </div>
            
            <h1 style="color: #8B4513;"><?php echo ucfirst($store['type']); ?>: <?php echo sanitize($store['name']); ?></h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="vehicle-summary">
                <div class="summary-row">
                    <div class="summary-card">
                        <h3>Vehicle Information</h3>
                        <p><strong>Name:</strong> <?php echo sanitize($store['name']); ?></p>
                        <p><strong>Type:</strong> <?php echo ucfirst($store['type']); ?></p>
                        <p><strong>Location:</strong> <?php echo !empty($store['location']) ? sanitize($store['location']) : 'Not specified'; ?></p>
                        <?php if ($store['type'] === 'lorry' && !empty($store['number_plate'])): ?>
                            <p><strong>Number Plate:</strong> <?php echo sanitize($store['number_plate']); ?></p>
                        <?php endif; ?>
                        <p><strong>Status:</strong> 
                            <?php if ($store['status'] === 'active'): ?>
                                <span class="badge success">Active</span>
                            <?php else: ?>
                                <span class="badge danger">Inactive</span>
                            <?php endif; ?>
                        </p>
                        <?php if ($primary_worker): ?>
                            <p><strong>Primary Worker:</strong> <?php echo sanitize($primary_worker['full_name']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="summary-card">
                        <h3>Financial Summary</h3>
                        <p><strong>Total Sales:</strong> <?php echo formatCurrency($sales_stats['total_sales'] ?? 0); ?></p>
                        <p><strong>Cash Sales:</strong> <?php echo formatCurrency($sales_stats['cash_sales'] ?? 0); ?></p>
                        <p><strong>Credit Sales:</strong> <?php echo formatCurrency($sales_stats['credit_sales'] ?? 0); ?></p>
                        <p><strong>Total Expenses:</strong> <?php echo formatCurrency($expenses_stats['total_expenses'] ?? 0); ?></p>
                        <p><strong>Net Revenue:</strong> <?php echo formatCurrency(($sales_stats['total_sales'] ?? 0) - ($expenses_stats['total_expenses'] ?? 0)); ?></p>
                    </div>
                    
                    <div class="summary-card">
                        <h3>Outstanding Debts</h3>
                        <p><strong>Total Debts:</strong> <?php echo $debts_stats['total_debts'] ?? 0; ?></p>
                        <p><strong>Total Outstanding:</strong> <?php echo formatCurrency($debts_stats['total_outstanding'] ?? 0); ?></p>
                        <p><strong>Credit Customers:</strong> <?php echo $sales_stats['credit_customers_count'] ?? 0; ?></p>
                        <p>
                            <a href="debts.php?vehicle_id=<?php echo $store_id; ?>" class="btn btn-sm btn-primary" style="background-color: #8B4513;">View All Debts</a>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($workers)): ?>
                <div class="summary-card">
                    <h3>Associated Workers</h3>
                    <div class="worker-list">
                        <?php foreach($workers as $worker): ?>
                            <div class="worker-badge" style="background-color: #f5efe6; color: #8B4513;">
                                <?php echo sanitize($worker['full_name']); ?> 
                                <span class="badge" style="background-color: #8B4513; color: white; font-size: 0.8em;"><?php echo $worker['sales_count']; ?> sales</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="tabs" style="margin-top: 20px; border-left: 4px solid #8B4513;">
                <div class="tab-header" style="background-color: #f5efe6;">
                    <button class="tab-btn active" onclick="openTab(event, 'tab-sales')">Recent Sales</button>
                    <button class="tab-btn" onclick="openTab(event, 'tab-expenses')">Recent Expenses</button>
                    <button class="tab-btn" onclick="openTab(event, 'tab-customers')">Customers with Debts</button>
                </div>
                
                <div id="tab-sales" class="tab-content active">
                    <?php if (empty($recent_sales)): ?>
                        <p class="text-center">No sales recorded for this vehicle</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead style="background-color: #f5efe6;">
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Bags</th>
                                    <th>Amount</th>
                                    <th>Payment Type</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_sales as $sale): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                                        <td>
                                            <?php if ($sale['payment_type'] === 'credit'): ?>
                                                <a href="customer_details.php?id=<?php echo $sale['customer_id']; ?>" style="color: #8B4513;">
                                                    <?php echo sanitize($sale['customer_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                Cash Customer
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $sale['bags_quantity']; ?> @ <?php echo formatCurrency($sale['price_per_bag']); ?></td>
                                        <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                        <td>
                                            <?php if ($sale['payment_type'] === 'credit'): ?>
                                                <span class="badge warning">Credit</span>
                                            <?php else: ?>
                                                <span class="badge success">Cash</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo sanitize($sale['recorded_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="text-center mt-4">
                            <a href="sales.php?store_id=<?php echo $store_id; ?>" class="btn btn-sm btn-primary" style="background-color: #8B4513;">View All Sales</a>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div id="tab-expenses" class="tab-content">
                    <?php if (empty($recent_expenses)): ?>
                        <p class="text-center">No expenses recorded for this vehicle</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead style="background-color: #f5efe6;">
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_expenses as $expense): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($expense['created_at'])); ?></td>
                                        <td><?php echo sanitize($expense['description']); ?></td>
                                        <td><?php echo formatCurrency($expense['amount']); ?></td>
                                        <td><?php echo sanitize($expense['recorded_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="text-center mt-4">
                            <a href="expenses.php?store_id=<?php echo $store_id; ?>" class="btn btn-sm btn-primary" style="background-color: #8B4513;">View All Expenses</a>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div id="tab-customers" class="tab-content">
                    <?php if (empty($customer_debts)): ?>
                        <p class="text-center">No customers with outstanding debts for this vehicle</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead style="background-color: #f5efe6;">
                                <tr>
                                    <th>Customer</th>
                                    <th>Number of Debts</th>
                                    <th>Total Outstanding</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customer_debts as $customer): ?>
                                    <tr>
                                        <td>
                                            <a href="customer_details.php?id=<?php echo $customer['id']; ?>" style="color: #8B4513;">
                                                <?php echo sanitize($customer['full_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $customer['debt_count']; ?></td>
                                        <td><?php echo formatCurrency($customer['total_outstanding']); ?></td>
                                        <td>
                                            <a href="customer_details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-info" style="background-color: #8B4513;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .vehicle-summary {
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            flex: 1;
            min-width: 300px;
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #8B4513;
        }
        
        .summary-card h3 {
            color: #8B4513;
            margin-top: 0;
            border-bottom: 1px solid #e8ddcf;
            padding-bottom: 10px;
        }
        
        .worker-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .worker-badge {
            background-color: #f5efe6;
            border: 1px solid #e8ddcf;
            border-radius: 20px;
            padding: 5px 15px;
            display: inline-block;
        }
        
        .back-nav {
            margin-bottom: 20px;
        }
        
        .tabs {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .tab-header {
            display: flex;
            background-color: #f5efe6;
            border-bottom: 1px solid #e8ddcf;
        }
        
        .tab-btn {
            padding: 15px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            color: #6b5a45;
        }
        
        .tab-btn.active {
            background-color: #fff;
            color: #8B4513;
            border-top: 2px solid #8B4513;
        }
        
        .tab-content {
            padding: 20px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Earth brown colors */
        .btn-primary, .btn-info, .btn-success {
            background-color: #8B4513;
            border-color: #8B4513;
            color: white;
        }
        
        .btn-primary:hover, .btn-info:hover, .btn-success:hover {
            background-color: #734012;
            border-color: #734012;
        }
        
        a {
            color: #8B4513;
        }
    </style>
    
    <script src="assets/js/script.js"></script>
    <script>
        function openTab(evt, tabId) {
            // Hide all tab content
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            // Remove active class from all tab buttons
            var tabBtns = document.getElementsByClassName("tab-btn");
            for (var i = 0; i < tabBtns.length; i++) {
                tabBtns[i].classList.remove("active");
            }
            
            // Show the selected tab and add active class to the button
            document.getElementById(tabId).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>
