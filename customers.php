
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

$success = $error = '';

// Handle form submission for adding new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($full_name)) {
        $error = "Customer name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (full_name, phone, address) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $full_name, $phone, $address);
        
        if ($stmt->execute()) {
            $success = "Customer added successfully.";
        } else {
            $error = "Failed to add customer: " . $conn->error;
        }
    }
}

// Get all customers
$query = "SELECT c.*, 
          (SELECT SUM(d.amount_due - d.amount_paid) FROM sales s 
           JOIN debts d ON s.id = d.sale_id 
           WHERE s.customer_id = c.id AND d.status != 'paid') as total_debt 
          FROM customers c 
          WHERE c.status = 'active' 
          ORDER BY c.full_name";
$result = $conn->query($query);
$customers = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Customers Management</h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Add New Customer</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="full_name">Full Name*</label>
                            <input type="text" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="add_customer" class="btn btn-primary">Add Customer</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Customer List</h2>
                    <div class="card-tools">
                        <input type="text" id="customerSearch" placeholder="Search customers..." class="search-input">
                    </div>
                </div>
                <div class="card-body">
                    <table class="data-table" id="customersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Outstanding Debt</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No customers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><?php echo sanitize($customer['full_name']); ?></td>
                                        <td><?php echo sanitize($customer['phone'] ?? '-'); ?></td>
                                        <td><?php echo sanitize($customer['address'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($customer['total_debt'] > 0): ?>
                                                <span class="text-danger fw-bold"><?php echo formatCurrency($customer['total_debt']); ?></span>
                                            <?php else: ?>
                                                <span class="text-success">No debt</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="customer_details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <a href="record_sale.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary">New Sale</a>
                                            <a href="record_payment.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-success">Record Payment</a>
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
    <script>
        // Simple search functionality
        document.getElementById('customerSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('customersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(searchTerm) > -1) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        });
    </script>
</body>
</html>
