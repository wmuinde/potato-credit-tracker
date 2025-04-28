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

// Get vehicle ID from URL
$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if vehicle exists and user has access
if (!$vehicleId || !hasVehicleAccess($conn, $_SESSION['user_id'] ?? 0, $vehicleId)) {
   header("Location: index.php");
   exit();
}

// Get vehicle details
$vehicle = getVehicleById($conn, $vehicleId);
$sales = getVehicleSales($conn, $vehicleId);
$expenses = getVehicleExpenses($conn, $vehicleId);
// Ensure expenses is an array
if (!is_array($expenses)) {
   $expenses = [];
}

// Calculate total collections (cash sales + credit payments - expenses)
$cashSales = $vehicle['cash_sales'] ?? 0;
$expenses = $vehicle['expenses'] ?? 0;

// Get credit payments
$stmt = $conn->prepare("
   SELECT SUM(p.amount) as credit_payments
   FROM payments p
   JOIN sales s ON p.debt_id = s.id
   WHERE s.vehicle_id = :vehicle_id AND s.payment_type = 'credit'
");
$stmt->bindParam(':vehicle_id', $vehicleId);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$creditPayments = $result['credit_payments'] ?? 0;

$totalCollections = $cashSales + $creditPayments - $expenses;

// Get total bags sold
$stmt = $conn->prepare("
   SELECT COALESCE(SUM(bags_sold), 0) as total_bags_sold
   FROM sales
   WHERE vehicle_id = :vehicle_id
");
$stmt->bindParam(':vehicle_id', $vehicleId);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBagsSold = $result['total_bags_sold'] ?? 0;

// Calculate profit correctly: total collections - (bags sold * initial bag value)
$bagCost = $totalBagsSold * $vehicle['bag_value'];
$correctProfit = $totalCollections - $bagCost;

// Process add sale form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_sale'])) {
   $customerId = $_POST['customer_id'];
   $amount = $_POST['amount'];
   $paymentType = $_POST['payment_type'];
   $date = $_POST['date'];
   $bags_sold = $_POST['bags_sold'];
   $price_per_bag = $_POST['price_per_bag'];
   
   // Check if we need to create a new customer
   if ($customerId === 'new') {
       $newCustomerName = $_POST['new_customer_name'];
       $newCustomerPhone = $_POST['new_customer_phone'] ?? '';
       
       if (!empty($newCustomerName)) {
           // Add the new customer
           $customerId = addCustomer($conn, $newCustomerName, $newCustomerPhone);
       }
   }
   
   // Get total bags already sold for this vehicle
   $stmt = $conn->prepare("
       SELECT COALESCE(SUM(bags_sold), 0) as total_bags_sold
       FROM sales
       WHERE vehicle_id = :vehicle_id
   ");
   $stmt->bindParam(':vehicle_id', $vehicleId);
   $stmt->execute();
   $result = $stmt->fetch(PDO::FETCH_ASSOC);
   $totalBagsSold = $result['total_bags_sold'] ?? 0;
   
   // Check if adding these bags would exceed the loaded amount
   if (($totalBagsSold + $bags_sold) > $vehicle['bags_loaded']) {
       // Too many bags - redirect with error
       header("Location: vehicle-details.php?id=$vehicleId&error=too_many_bags&available=".($vehicle['bags_loaded'] - $totalBagsSold));
       exit();
   }
   
   $saleId = addSale($conn, $vehicleId, $customerId, $_SESSION['user_id'], $amount, $paymentType, $date, $bags_sold, $price_per_bag);
   
   if ($saleId) {
       header("Location: vehicle-details.php?id=$vehicleId&success=sale_added");
       exit();
   }
}

// Process add expense form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
   $expenseTypeId = $_POST['expense_type_id'];
   $amount = $_POST['amount'];
   $date = $_POST['date'];
   $description = $_POST['description'];
   
   $stmt = $conn->prepare("
       INSERT INTO expenses (vehicle_id, expense_type_id, amount, date, description)
       VALUES (:vehicle_id, :expense_type_id, :amount, :date, :description)
   ");
   $stmt->bindParam(':vehicle_id', $vehicleId);
   $stmt->bindParam(':expense_type_id', $expenseTypeId);
   $stmt->bindParam(':amount', $amount);
   $stmt->bindParam(':date', $date);
   $stmt->bindParam(':description', $description);
   
   if ($stmt->execute()) {
       header("Location: vehicle-details.php?id=$vehicleId&success=expense_added");
       exit();
   }
}

// Process add lost/damaged bags form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_lost'])) {
   $bags = $_POST['bags'];
   $date = $_POST['date'];
   $reason = $_POST['reason'];
   
   $stmt = $conn->prepare("
       INSERT INTO lost_damaged (vehicle_id, bags, date, reason)
       VALUES (:vehicle_id, :bags, :date, :reason)
   ");
   $stmt->bindParam(':vehicle_id', $vehicleId);
   $stmt->bindParam(':bags', $bags);
   $stmt->bindParam(':date', $date);
   $stmt->bindParam(':reason', $reason);
   
   if ($stmt->execute()) {
       header("Location: vehicle-details.php?id=$vehicleId&success=lost_added");
       exit();
   }
}

// Get bags sold at different price points
$stmt = $conn->prepare("
   SELECT price_per_bag, SUM(bags_sold) as total_bags, SUM(amount) as total_amount
   FROM sales
   WHERE vehicle_id = :vehicle_id
   GROUP BY price_per_bag
   ORDER BY price_per_bag DESC
");
$stmt->bindParam(':vehicle_id', $vehicleId);
$stmt->execute();
$bagPriceBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Vehicle Details - Potato Sales Management System</title>
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link rel="stylesheet" href="assets/css/style.css">
   <link rel="stylesheet" href="assets/css/theme.css">
   <style>
      /* Responsive styles for vehicle details */
      .stat-card {
         padding: 1rem;
         border-radius: 0.5rem;
         height: 100%;
      }
      
      .stat-card .card-title {
         font-size: 0.9rem;
         margin-bottom: 0.5rem;
      }
      
      .stat-card .card-text {
         font-size: 1.5rem;
         font-weight: 600;
         margin-bottom: 0;
      }
      
      .table-responsive {
         margin-bottom: 1rem;
      }
      
      /* Mobile optimizations */
      @media (max-width: 767px) {
         .stat-card {
            padding: 0.75rem;
         }
         
         .stat-card .card-title {
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
         }
         
         .stat-card .card-text {
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
         }
         
         .table th, .table td {
            font-size: 0.75rem;
            padding: 0.4rem;
         }
         
         .card-header h5 {
            font-size: 0.9rem;
         }
         
         .btn-sm {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
         }
         
         .col-md-3, .col-md-6 {
            padding-left: 5px;
            padding-right: 5px;
         }
         
         .row {
            margin-left: -5px;
            margin-right: -5px;
         }
         
         .mb-4 {
            margin-bottom: 0.75rem !important;
         }
         
         .card-body {
            padding: 0.75rem;
         }
         
         .display-4 {
            font-size: 1.5rem;
         }
      }
   </style>
</head>
<body>
   <?php include 'includes/header.php'; ?>
   
   <div class="container-fluid">
       <div class="row">
           <?php include 'includes/sidebar.php'; ?>
           
           <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
               <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                   <h1 class="h2">Vehicle Details: <?php echo $vehicle['license_plate']; ?></h1>
                   <div class="btn-toolbar mb-2 mb-md-0">
                       <div class="btn-group me-2">
                           <a href="reports.php?vehicle_id=<?php echo $vehicleId; ?>" class="btn btn-sm btn-outline-secondary">Generate Report</a>
                           <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
                       </div>
                   </div>
               </div>
               
               <?php if (isset($_GET['success'])): ?>
                   <div class="alert alert-success alert-dismissible fade show" role="alert">
                       <?php 
                       $success = $_GET['success'];
                       if ($success == 'sale_added') echo 'Sale added successfully';
                       elseif ($success == 'expense_added') echo 'Expense added successfully';
                       elseif ($success == 'lost_added') echo 'Lost/damaged bags recorded successfully';
                       ?>
                       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                   </div>
               <?php endif; ?>

               <?php if (isset($_GET['error'])): ?>
                   <div class="alert alert-danger alert-dismissible fade show" role="alert">
                       <?php 
                       $error = $_GET['error'];
                       if ($error == 'too_many_bags') {
                           echo 'Cannot add sale: Not enough bags available. You can only sell ' . $_GET['available'] . ' more bags.';
                       }
                       ?>
                       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                   </div>
               <?php endif; ?>
               
               <div class="row g-2 mb-3">
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Bags Loaded</h5>
                               <p class="card-text"><?php echo $vehicle['bags_loaded']; ?></p>
                           </div>
                       </div>
                   </div>
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card text-white bg-success h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Cash Sales</h5>
                               <p class="card-text"><?php echo formatCurrency($vehicle['cash_sales'] ?? 0); ?></p>
                           </div>
                       </div>
                   </div>
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card text-white bg-warning h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Credit Sales</h5>
                               <p class="card-text"><?php echo formatCurrency($vehicle['credit_sales'] ?? 0); ?></p>
                           </div>
                       </div>
                   </div>
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card text-white bg-info h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Profit</h5>
                               <p class="card-text"><?php echo formatCurrency($correctProfit); ?></p>
                               <small class="text-white" style="font-size: 0.65rem;">Collections - (Bags × <?php echo formatCurrency($vehicle['bag_value']); ?>)</small>
                           </div>
                       </div>
                   </div>
               </div>
               
               <div class="row g-2 mb-3">
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card text-white bg-primary h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Total Collections</h5>
                               <p class="card-text"><?php echo formatCurrency($totalCollections); ?></p>
                               <small class="text-white" style="font-size: 0.65rem;">Cash + Credit Payments - Expenses</small>
                           </div>
                       </div>
                   </div>
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card text-white bg-danger h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Total Expenses</h5>
                               <p class="card-text"><?php echo formatCurrency($expenses); ?></p>
                           </div>
                       </div>
                   </div>
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card text-white bg-success h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Net Cash Sales</h5>
                               <p class="card-text"><?php echo formatCurrency($cashSales - $expenses); ?></p>
                               <small class="text-white" style="font-size: 0.65rem;">Cash Sales - Expenses</small>
                           </div>
                       </div>
                   </div>
                   <div class="col-md-3 col-6 mb-2">
                       <div class="card text-white bg-warning h-100">
                           <div class="card-body p-2">
                               <h5 class="card-title fs-6">Credit Payments</h5>
                               <p class="card-text"><?php echo formatCurrency($creditPayments); ?></p>
                           </div>
                       </div>
                   </div>
               </div>
               
               <!-- Bags Sold at Different Price Points -->
               <div class="row mb-3">
                   <div class="col-md-12">
                       <div class="card">
                           <div class="card-header py-2">
                               <h5 class="mb-0 fs-6">Bags Sold at Different Price Points</h5>
                           </div>
                           <div class="card-body p-2">
                               <div class="table-responsive">
                                   <table class="table table-striped table-sm">
                                       <thead>
                                           <tr>
                                               <th>Price Per Bag</th>
                                               <th>Number of Bags</th>
                                               <th>Total Amount</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($bagPriceBreakdown as $breakdown): ?>
                                           <tr>
                                               <td><?php echo formatCurrency($breakdown['price_per_bag']); ?></td>
                                               <td><?php echo $breakdown['total_bags']; ?></td>
                                               <td><?php echo formatCurrency($breakdown['total_amount']); ?></td>
                                           </tr>
                                           <?php endforeach; ?>
                                           <?php if (empty($bagPriceBreakdown)): ?>
                                           <tr>
                                               <td colspan="3" class="text-center">No sales data available</td>
                                           </tr>
                                           <?php endif; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
               
               <div class="row g-2 mb-3">
                   <div class="col-md-6 mb-2">
                       <div class="card">
                           <div class="card-header d-flex justify-content-between align-items-center py-2">
                               <h5 class="mb-0 fs-6">Sales</h5>
                               <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                                   Add Sale
                               </button>
                           </div>
                           <div class="card-body p-2">
                               <div class="table-responsive">
                                   <table class="table table-striped table-sm">
                                       <thead>
                                           <tr>
                                               <th>Date</th>
                                               <th>Customer</th>
                                               <th>Type</th>
                                               <th>Bags</th>
                                               <th>Price/Bag</th>
                                               <th>Amount</th>
                                               <th>Status</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($sales as $sale): ?>
                                           <tr>
                                               <td><?php echo formatDate($sale['date']); ?></td>
                                               <td><?php echo $sale['customer_name']; ?></td>
                                               <td><?php echo ucfirst($sale['payment_type']); ?></td>
                                               <td><?php echo $sale['bags_sold'] ?? 0; ?></td>
                                               <td><?php echo formatCurrency($sale['price_per_bag'] ?? 0); ?></td>
                                               <td><?php echo formatCurrency($sale['amount']); ?></td>
                                               <td>
                                                   <?php if ($sale['payment_type'] == 'cash'): ?>
                                                       <span class="badge bg-success">Paid</span>
                                                   <?php elseif ($sale['balance'] <= 0): ?>
                                                       <span class="badge bg-success">Paid</span>
                                                   <?php elseif ($sale['amount_paid'] > 0): ?>
                                                       <span class="badge bg-warning">Partial</span>
                                                   <?php else: ?>
                                                       <span class="badge bg-danger">Unpaid</span>
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
                   
                   <div class="col-md-6 mb-2">
                       <div class="card">
                           <div class="card-header d-flex justify-content-between align-items-center py-2">
                               <h5 class="mb-0 fs-6">Expenses</h5>
                               <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                   Add Expense
                               </button>
                           </div>
                           <div class="card-body p-2">
                               <div class="table-responsive">
                                   <table class="table table-striped table-sm">
                                       <thead>
                                           <tr>
                                               <th>Date</th>
                                               <th>Type</th>
                                               <th>Amount</th>
                                               <th>Description</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php 
                                           // Make sure $expenses is an array before looping
                                           $expensesList = is_array($expenses) ? $expenses : [];
                                           foreach ($expensesList as $expense): 
                                           ?>
                                           <tr>
                                               <td><?php echo formatDate($expense['date']); ?></td>
                                               <td><?php echo $expense['expense_type']; ?></td>
                                               <td><?php echo formatCurrency($expense['amount']); ?></td>
                                               <td><?php echo $expense['description']; ?></td>
                                           </tr>
                                           <?php endforeach; ?>
                                           <?php if (empty($expensesList)): ?>
                                           <tr>
                                               <td colspan="4" class="text-center">No expenses found</td>
                                           </tr>
                                           <?php endif; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
               
               <div class="row g-2 mb-3">
                   <div class="col-md-6 mb-2">
                       <div class="card">
                           <div class="card-header d-flex justify-content-between align-items-center py-2">
                               <h5 class="mb-0 fs-6">Lost/Damaged Bags</h5>
                               <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLostModal">
                                   Add Record
                               </button>
                           </div>
                           <div class="card-body p-2">
                               <div class="table-responsive">
                                   <table class="table table-striped table-sm">
                                       <thead>
                                           <tr>
                                               <th>Date</th>
                                               <th>Bags</th>
                                               <th>Value</th>
                                               <th>Reason</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php 
                                           $stmt = $conn->prepare("SELECT * FROM lost_damaged WHERE vehicle_id = :vehicle_id ORDER BY date DESC");
                                           $stmt->bindParam(':vehicle_id', $vehicleId);
                                           $stmt->execute();
                                           $lostBags = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                           
                                           foreach ($lostBags as $lost): 
                                               $value = $lost['bags'] * $vehicle['bag_value'];
                                           ?>
                                           <tr>
                                               <td><?php echo formatDate($lost['date']); ?></td>
                                               <td><?php echo $lost['bags']; ?></td>
                                               <td><?php echo formatCurrency($value); ?></td>
                                               <td><?php echo $lost['reason']; ?></td>
                                           </tr>
                                           <?php endforeach; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                       </div>
                   </div>
                   
                   <div class="col-md-6 mb-2">
                       <div class="card">
                           <div class="card-header py-2">
                               <h5 class="mb-0 fs-6">Vehicle Information</h5>
                           </div>
                           <div class="card-body p-2">
                               <table class="table table-sm">
                                   <tr>
                                       <th>License Plate:</th>
                                       <td><?php echo $vehicle['license_plate']; ?></td>
                                   </tr>
                                   <tr>
                                       <th>Bag Value:</th>
                                       <td><?php echo formatCurrency($vehicle['bag_value']); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Assigned Workers:</th>
                                       <td>
                                           <?php 
                                           $stmt = $conn->prepare("
                                               SELECT u.name FROM users u
                                               JOIN vehicle_assignments va ON u.id = va.worker_id
                                               WHERE va.vehicle_id = :vehicle_id
                                           ");
                                           $stmt->bindParam(':vehicle_id', $vehicleId);
                                           $stmt->execute();
                                           $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                           
                                           foreach ($workers as $index => $worker) {
                                               echo $worker['name'];
                                               if ($index < count($workers) - 1) {
                                                   echo ', ';
                                               }
                                           }
                                           ?>
                                       </td>
                                   </tr>
                                   <tr>
                                       <th>Total Expenses:</th>
                                       <td><?php echo formatCurrency($vehicle['expenses'] ?? 0); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Lost/Damaged Bags:</th>
                                       <td><?php echo $vehicle['lost_damaged'] ?? 0; ?> bags</td>
                                   </tr>
                                   <tr>
                                       <th>Lost Value:</th>
                                       <td><?php echo formatCurrency(($vehicle['lost_damaged'] ?? 0) * $vehicle['bag_value']); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Cash Sales:</th>
                                       <td><?php echo formatCurrency($cashSales); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Expenses:</th>
                                       <td><?php echo formatCurrency($expenses); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Net Cash Sales:</th>
                                       <td><?php echo formatCurrency($cashSales - $expenses); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Credit Payments:</th>
                                       <td><?php echo formatCurrency($creditPayments); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Total Collections:</th>
                                       <td><?php echo formatCurrency($totalCollections); ?></td>
                                   </tr>
                                   <tr>
                                       <th>Profit:</th>
                                       <td><?php echo formatCurrency($correctProfit); ?></td>
                                   </tr>
                               </table>
                           </div>
                       </div>
                   </div>
               </div>
           </main>
       </div>
   </div>
   
   <!-- Add Sale Modal -->
   <div class="modal fade" id="addSaleModal" tabindex="-1" aria-labelledby="addSaleModalLabel" aria-hidden="true">
       <div class="modal-dialog">
           <div class="modal-content">
               <form method="post">
                   <div class="modal-header">
                       <h5 class="modal-title" id="addSaleModalLabel">Add New Sale</h5>
                       <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                   </div>
                   <div class="modal-body">
                       <div class="mb-3">
                           <label for="customer_id" class="form-label">Customer</label>
                           <select class="form-select" id="customer_id" name="customer_id" required>
                               <option value="">Select Customer</option>
                               <option value="new">+ Add New Customer</option>
                               <?php 
                               $stmt = $conn->prepare("SELECT id, name FROM customers ORDER BY name");
                               $stmt->execute();
                               $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                               
                               foreach ($customers as $customer): 
                               ?>
                               <option value="<?php echo $customer['id']; ?>"><?php echo $customer['name']; ?></option>
                               <?php endforeach; ?>
                           </select>
                       </div>

                       <!-- New Customer Fields (initially hidden) -->
                       <div id="newCustomerFields" style="display: none;">
                           <div class="mb-3">
                               <label for="new_customer_name" class="form-label">New Customer Name</label>
                               <input type="text" class="form-control" id="new_customer_name" name="new_customer_name">
                           </div>
                           <div class="mb-3">
                               <label for="new_customer_phone" class="form-label">New Customer Phone</label>
                               <input type="text" class="form-control" id="new_customer_phone" name="new_customer_phone">
                           </div>
                       </div>
                       
                       <div class="mb-3">
                           <label for="bags_sold" class="form-label">Number of Bags Sold</label>
                           <input type="number" class="form-control" id="bags_sold" name="bags_sold" min="1" required>
                       </div>
                       
                       <div class="mb-3">
                           <label for="price_per_bag" class="form-label">Price Per Bag</label>
                           <input type="number" class="form-control" id="price_per_bag" name="price_per_bag" step="0.01" value="<?php echo $vehicle['bag_value']; ?>" required>
                       </div>
                       
                       <div class="mb-3">
                           <label for="total_amount" class="form-label">Total Amount</label>
                           <input type="number" class="form-control" id="total_amount" name="amount" step="0.01" readonly required>
                       </div>
                       
                       <div class="mb-3">
                           <label for="payment_type" class="form-label">Payment Type</label>
                           <select class="form-select" id="payment_type" name="payment_type" required>
                               <option value="cash">Cash</option>
                               <option value="credit">Credit</option>
                           </select>
                       </div>
                       <div class="mb-3">
                           <label for="date" class="form-label">Date</label>
                           <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                       </div>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                       <button type="submit" name="add_sale" class="btn btn-primary">Add Sale</button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   
   <!-- Add Expense Modal -->
   <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
       <div class="modal-dialog">
           <div class="modal-content">
               <form method="post">
                   <div class="modal-header">
                       <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                       <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                   </div>
                   <div class="modal-body">
                       <div class="mb-3">
                           <label for="expense_type_id" class="form-label">Expense Type</label>
                           <select class="form-select" id="expense_type_id" name="expense_type_id" required>
                               <option value="">Select Type</option>
                               <?php 
                               $stmt = $conn->prepare("SELECT id, name FROM expense_types ORDER BY name");
                               $stmt->execute();
                               $expenseTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                               
                               foreach ($expenseTypes as $type): 
                               ?>
                               <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?></option>
                               <?php endforeach; ?>
                           </select>
                       </div>
                       <div class="mb-3">
                           <label for="amount" class="form-label">Amount</label>
                           <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                       </div>
                       <div class="mb-3">
                           <label for="date" class="form-label">Date</label>
                           <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                       </div>
                       <div class="mb-3">
                           <label for="description" class="form-label">Description</label>
                           <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                       </div>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                       <button type="submit" name="add_expense" class="btn btn-primary">Add Expense</button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   
   <!-- Add Lost/Damaged Modal -->
   <div class="modal fade" id="addLostModal" tabindex="-1" aria-labelledby="addLostModalLabel" aria-hidden="true">
       <div class="modal-dialog">
           <div class="modal-content">
               <form method="post">
                   <div class="modal-header">
                       <h5 class="modal-title" id="addLostModalLabel">Add Lost/Damaged Bags</h5>
                       <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                   </div>
                   <div class="modal-body">
                       <div class="mb-3">
                           <label for="bags" class="form-label">Number of Bags</label>
                           <input type="number" class="form-control" id="bags" name="bags" required>
                       </div>
                       <div class="mb-3">
                           <label for="date" class="form-label">Date</label>
                           <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                       </div>
                       <div class="mb-3">
                           <label for="reason" class="form-label">Reason</label>
                           <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                       </div>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                       <button type="submit" name="add_lost" class="btn btn-primary">Add Record</button>
                   </div>
               </form>
           </div>
       </div>
   </div>
   
   <?php include 'includes/footer.php'; ?>
   
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
   <script src="assets/js/dashboard.js"></script>
   <script>
document.addEventListener('DOMContentLoaded', function() {
   const customerSelect = document.getElementById('customer_id');
   const newCustomerFields = document.getElementById('newCustomerFields');
   
   customerSelect.addEventListener('change', function() {
       if (this.value === 'new') {
           newCustomerFields.style.display = 'block';
           document.getElementById('new_customer_name').setAttribute('required', 'required');
       } else {
           newCustomerFields.style.display = 'none';
           document.getElementById('new_customer_name').removeAttribute('required');
       }
   });

   const bagsSoldInput = document.getElementById('bags_sold');
   const pricePerBagInput = document.getElementById('price_per_bag');
   const totalAmountInput = document.getElementById('total_amount');

   function calculateTotal() {
       const bagsSold = parseFloat(bagsSoldInput.value) || 0;
       const pricePerBag = parseFloat(pricePerBagInput.value) || 0;
       const totalAmount = bagsSold * pricePerBag;
       totalAmountInput.value = totalAmount.toFixed(2);
   }

   bagsSoldInput.addEventListener('input', calculateTotal);
   pricePerBagInput.addEventListener('input', calculateTotal);

// Add validation for maximum bags
const bagsLoadedTotal = <?php echo $vehicle['bags_loaded']; ?>;
const bagsSoldSoFar = <?php echo $totalBagsSold; ?>;
const bagsAvailable = bagsLoadedTotal - bagsSoldSoFar;

bagsSoldInput.setAttribute('max', bagsAvailable);
bagsSoldInput.addEventListener('input', function() {
    if (parseInt(this.value) > bagsAvailable) {
        this.setCustomValidity(`You can only sell up to ${bagsAvailable} more bags`);
    } else {
        this.setCustomValidity('');
    }
});

// Add a helpful note about available bags to the form
const bagsSoldFormGroup = bagsSoldInput.closest('.mb-3');
const helpText = document.createElement('div');
helpText.className = 'form-text';
helpText.textContent = `Available bags: ${bagsAvailable} (${bagsSoldSoFar} already sold out of ${bagsLoadedTotal})`;
bagsSoldFormGroup.appendChild(helpText);
});
</script>
</body>
</html>
