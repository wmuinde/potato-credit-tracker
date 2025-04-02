
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

$success = $error = '';
$customer_id = $_GET['customer_id'] ?? null;

// Get customer information if customer_id is provided
$customer = null;
if ($customer_id) {
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
}

// Get all stores/lorries
$stores_result = $conn->query("SELECT * FROM stores WHERE status = 'active'");
$stores = $stores_result->fetch_all(MYSQLI_ASSOC);

// Get all customers for dropdown
$customers_result = $conn->query("SELECT id, full_name FROM customers WHERE status = 'active' ORDER BY full_name");
$customers = $customers_result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_customer_id = $_POST['customer_id'] ?? null;
    $store_id = $_POST['store_id'] ?? null;
    $bags_quantity = $_POST['bags_quantity'] ?? 0;
    $price_per_bag = $_POST['price_per_bag'] ?? 0;
    $payment_type = $_POST['payment_type'] ?? 'cash';
    
    // Validate input
    if (empty($store_id) || empty($bags_quantity) || empty($price_per_bag)) {
        $error = "Please fill all required fields.";
    } else if ($payment_type === 'credit' && empty($post_customer_id)) {
        $error = "Please select a customer for credit sales.";
    } else {
        $total_amount = $bags_quantity * $price_per_bag;
        $user_id = $_SESSION['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into sales table
            $stmt = $conn->prepare("INSERT INTO sales (customer_id, store_id, bags_quantity, price_per_bag, total_amount, payment_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiddsi", $post_customer_id, $store_id, $bags_quantity, $price_per_bag, $total_amount, $payment_type, $user_id);
            $stmt->execute();
            
            $sale_id = $conn->insert_id;
            
            // If credit sale, insert into debts table
            if ($payment_type === 'credit') {
                $stmt = $conn->prepare("INSERT INTO debts (sale_id, amount_due, status) VALUES (?, ?, 'pending')");
                $stmt->bind_param("id", $sale_id, $total_amount);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = "Sale recorded successfully.";
            
            // Reset form values
            $customer_id = null;
            $customer = null;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error recording sale: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Sale - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Record New Sale</h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Sale Information</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="payment_type">Payment Type*</label>
                            <select id="payment_type" name="payment_type" required onchange="toggleCustomerField()">
                                <option value="cash">Cash</option>
                                <option value="credit" <?php echo $customer_id ? 'selected' : ''; ?>>Credit</option>
                            </select>
                        </div>
                        
                        <div id="customer_field" class="form-group" <?php echo !$customer_id ? 'style="display: none;"' : ''; ?>>
                            <label for="customer_id">Customer*</label>
                            <select id="customer_id" name="customer_id">
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $customer_id == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($c['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="store_id">Store/Lorry*</label>
                            <select id="store_id" name="store_id" required>
                                <option value="">Select Store/Lorry</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>">
                                        <?php echo sanitize($store['name']); ?> 
                                        <?php if ($store['type'] === 'lorry' && !empty($store['number_plate'])): ?>
                                            (<?php echo sanitize($store['number_plate']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bags_quantity">Number of Bags*</label>
                            <input type="number" id="bags_quantity" name="bags_quantity" min="1" required onchange="calculateTotal()">
                        </div>
                        
                        <div class="form-group">
                            <label for="price_per_bag">Price Per Bag*</label>
                            <input type="number" id="price_per_bag" name="price_per_bag" step="0.01" min="0" required onchange="calculateTotal()">
                        </div>
                        
                        <div class="form-group">
                            <label for="total_amount">Total Amount</label>
                            <input type="text" id="total_amount" readonly>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Record Sale</button>
                            <a href="sales.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/script.js"></script>
    <script>
        function toggleCustomerField() {
            const paymentType = document.getElementById('payment_type').value;
            const customerField = document.getElementById('customer_field');
            
            if (paymentType === 'credit') {
                customerField.style.display = 'block';
                document.getElementById('customer_id').setAttribute('required', 'required');
            } else {
                customerField.style.display = 'none';
                document.getElementById('customer_id').removeAttribute('required');
            }
        }
        
        function calculateTotal() {
            const quantity = document.getElementById('bags_quantity').value || 0;
            const pricePerBag = document.getElementById('price_per_bag').value || 0;
            const total = quantity * pricePerBag;
            
            document.getElementById('total_amount').value = total.toFixed(2);
        }
        
        // Initial call
        toggleCustomerField();
        calculateTotal();
    </script>
</body>
</html>
