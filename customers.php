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

// Process add customer form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    
    $customerId = addCustomer($conn, $name, $phone);
    
    if ($customerId) {
        header("Location: customers.php?success=added");
        exit();
    }
}

// Process edit customer form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_customer'])) {
    $customerId = $_POST['customer_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    
    $stmt = $conn->prepare("UPDATE customers SET name = :name, phone = :phone WHERE id = :id");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':id', $customerId);
    
    if ($stmt->execute()) {
        header("Location: customers.php?success=updated");
        exit();
    }
}

// Get all customers
$stmt = $conn->prepare("
    SELECT c.*, 
        (SELECT COUNT(*) FROM sales WHERE customer_id = c.id) as sales_count,
        (SELECT SUM(amount) FROM sales WHERE customer_id = c.id) as total_sales,
        (SELECT SUM(s.amount - COALESCE(p.amount_paid, 0)) 
         FROM sales s 
         LEFT JOIN (
             SELECT debt_id, SUM(amount) as amount_paid 
             FROM payments 
             GROUP BY debt_id
         ) p ON s.id = p.debt_id
         WHERE s.customer_id = c.id AND s.payment_type = 'credit'
        ) as outstanding_debt
    FROM customers c
    ORDER BY c.name
");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Potato Sales Management System</title>
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
                    <h1 class="h2">Customers</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                            Add New Customer
                        </button>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        $success = $_GET['success'];
                        if ($success == 'added') echo 'Customer added successfully';
                        elseif ($success == 'updated') echo 'Customer updated successfully';
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Sales Count</th>
                                <th>Total Sales</th>
                                <th>Outstanding Debt</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo $customer['name']; ?></td>
                                <td><?php echo $customer['phone']; ?></td>
                                <td><?php echo $customer['sales_count']; ?></td>
                                <td><?php echo formatCurrency($customer['total_sales'] ?? 0); ?></td>
                                <td>
                                    <?php if (($customer['outstanding_debt'] ?? 0) > 0): ?>
                                        <span class="text-danger"><?php echo formatCurrency($customer['outstanding_debt']); ?></span>
                                    <?php else: ?>
                                        <span class="text-success">No debt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editCustomerModal"
                                            data-customer-id="<?php echo $customer['id']; ?>"
                                            data-customer-name="<?php echo $customer['name']; ?>"
                                            data-customer-phone="<?php echo $customer['phone']; ?>">
                                        Edit
                                    </button>
                                    <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-secondary">Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_customer" class="btn btn-primary">Add Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCustomerModalLabel">Edit Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_customer_id" name="customer_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Customer Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_customer" class="btn btn-primary">Save Changes</button>
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
            
            // Set up edit customer modal
            const editCustomerModal = document.getElementById('editCustomerModal');
            if (editCustomerModal) {
                editCustomerModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const customerId = button.getAttribute('data-customer-id');
                    const customerName = button.getAttribute('data-customer-name');
                    const customerPhone = button.getAttribute('data-customer-phone');
                    
                    const customerIdInput = editCustomerModal.querySelector('#edit_customer_id');
                    const nameInput = editCustomerModal.querySelector('#edit_name');
                    const phoneInput = editCustomerModal.querySelector('#edit_phone');
                    
                    customerIdInput.value = customerId;
                    nameInput.value = customerName;
                    phoneInput.value = customerPhone;
                });
            }
        });
    </script>
</body>
</html>
