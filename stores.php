
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

$success = $error = '';

// Handle form submission for adding new vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_store'])) {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'store';
    $location = trim($_POST['location'] ?? '');
    $number_plate = trim($_POST['number_plate'] ?? '');
    
    if (empty($name)) {
        $error = "Name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO stores (name, type, location, number_plate) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $type, $location, $number_plate);
        
        if ($stmt->execute()) {
            $success = ($type === 'store' ? "Store" : "Lorry") . " added successfully.";
        } else {
            $error = "Failed to add " . ($type === 'store' ? "store" : "lorry") . ": " . $conn->error;
        }
    }
}

// Handle vehicle status change
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    
    if ($action === 'deactivate' || $action === 'activate') {
        $status = ($action === 'activate') ? 'active' : 'inactive';
        
        $stmt = $conn->prepare("UPDATE stores SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $success = "Vehicle " . ($action === 'activate' ? 'activated' : 'deactivated') . " successfully.";
        } else {
            $error = "Failed to update status: " . $conn->error;
        }
    }
}

// Get all vehicles
$stores_result = $conn->query("SELECT * FROM stores ORDER BY type, name");
$stores = $stores_result->fetch_all(MYSQLI_ASSOC);

// Get vehicle stats
foreach ($stores as &$store) {
    // Calculate total sales
    $stmt = $conn->prepare("SELECT SUM(total_amount) as total_sales FROM sales WHERE store_id = ?");
    $stmt->bind_param("i", $store['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $store['total_sales'] = $row['total_sales'] ?? 0;
    
    // Calculate cash sales
    $stmt = $conn->prepare("SELECT SUM(total_amount) as cash_sales FROM sales WHERE store_id = ? AND payment_type = 'cash'");
    $stmt->bind_param("i", $store['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $store['cash_sales'] = $row['cash_sales'] ?? 0;
    
    // Calculate credit sales
    $stmt = $conn->prepare("SELECT SUM(total_amount) as credit_sales FROM sales WHERE store_id = ? AND payment_type = 'credit'");
    $stmt->bind_param("i", $store['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $store['credit_sales'] = $row['credit_sales'] ?? 0;
    
    // Calculate expenses
    $stmt = $conn->prepare("SELECT SUM(amount) as total_expenses FROM expenses WHERE store_id = ?");
    $stmt->bind_param("i", $store['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $store['expenses'] = $row['total_expenses'] ?? 0;
}
unset($store);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicles Management - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Vehicles Management</h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card" style="border-left: 4px solid #8B4513;">
                <div class="card-header" style="background-color: #f5efe6; border-bottom: 1px solid #e8ddcf;">
                    <h2 style="color: #8B4513;">Add New Vehicle</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="type">Type*</label>
                            <select id="type" name="type" required onchange="toggleNumberPlateField()" style="border: 1px solid #e8ddcf; border-radius: 4px;">
                                <option value="store">Store</option>
                                <option value="lorry">Lorry</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Name*</label>
                            <input type="text" id="name" name="name" required style="border: 1px solid #e8ddcf; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" style="border: 1px solid #e8ddcf; border-radius: 4px;">
                        </div>
                        
                        <div id="number_plate_field" class="form-group" style="display: none;">
                            <label for="number_plate">Number Plate</label>
                            <input type="text" id="number_plate" name="number_plate" style="border: 1px solid #e8ddcf; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="add_store" class="btn btn-primary" style="background-color: #8B4513; border-color: #8B4513;">Add Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4" style="border-left: 4px solid #8B4513;">
                <div class="card-header" style="background-color: #f5efe6; border-bottom: 1px solid #e8ddcf;">
                    <h2 style="color: #8B4513;">Vehicles List</h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead style="background-color: #f5efe6;">
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Number Plate</th>
                                <th>Total Sales</th>
                                <th>Cash Sales</th>
                                <th>Credit Sales</th>
                                <th>Expenses</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stores)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No vehicles found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($stores as $store): ?>
                                    <tr>
                                        <td><?php echo sanitize($store['name']); ?></td>
                                        <td><?php echo ucfirst($store['type']); ?></td>
                                        <td><?php echo sanitize($store['location'] ?? '-'); ?></td>
                                        <td><?php echo $store['type'] === 'lorry' ? sanitize($store['number_plate'] ?? '-') : '-'; ?></td>
                                        <td><?php echo formatCurrency($store['total_sales']); ?></td>
                                        <td><?php echo formatCurrency($store['cash_sales']); ?></td>
                                        <td><?php echo formatCurrency($store['credit_sales']); ?></td>
                                        <td><?php echo formatCurrency($store['expenses']); ?></td>
                                        <td>
                                            <?php if ($store['status'] === 'active'): ?>
                                                <span class="badge success">Active</span>
                                            <?php else: ?>
                                                <span class="badge danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="store_details.php?id=<?php echo $store['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <a href="expenses.php?store_id=<?php echo $store['id']; ?>" class="btn btn-sm btn-primary">Expenses</a>
                                            
                                            <?php if ($store['status'] === 'active'): ?>
                                                <a href="stores.php?action=deactivate&id=<?php echo $store['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to deactivate this vehicle?')">Deactivate</a>
                                            <?php else: ?>
                                                <a href="stores.php?action=activate&id=<?php echo $store['id']; ?>" class="btn btn-sm btn-success">Activate</a>
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
    <script>
        function toggleNumberPlateField() {
            const type = document.getElementById('type').value;
            const numberPlateField = document.getElementById('number_plate_field');
            
            if (type === 'lorry') {
                numberPlateField.style.display = 'block';
            } else {
                numberPlateField.style.display = 'none';
                document.getElementById('number_plate').value = '';
            }
        }
        
        // Initial call
        toggleNumberPlateField();
    </script>
</body>
</html>
