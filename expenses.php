
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

$success = $error = '';
$store_id = $_GET['store_id'] ?? null;

// Get store information if store_id is provided
$store = null;
if ($store_id) {
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $store = $result->fetch_assoc();
}

// Handle form submission for adding new expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $post_store_id = $_POST['store_id'] ?? null;
    $amount = $_POST['amount'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    
    if (empty($post_store_id) || empty($amount) || empty($description) || empty($expense_date)) {
        $error = "All fields are required.";
    } else {
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("INSERT INTO expenses (store_id, amount, description, recorded_by, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idsss", $post_store_id, $amount, $description, $user_id, $expense_date);
        
        if ($stmt->execute()) {
            $success = "Expense recorded successfully.";
        } else {
            $error = "Failed to record expense: " . $conn->error;
        }
    }
}

// Get all stores for dropdown
$stores_result = $conn->query("SELECT * FROM stores WHERE status = 'active' ORDER BY name");
$stores = $stores_result->fetch_all(MYSQLI_ASSOC);

// Get expenses
$expenses_query = "SELECT e.*, s.name as store_name, s.type as store_type, u.full_name as recorded_by_name 
                  FROM expenses e 
                  JOIN stores s ON e.store_id = s.id 
                  JOIN users u ON e.recorded_by = u.id";

// If store_id is provided, filter expenses by store
if ($store_id) {
    $stmt = $conn->prepare($expenses_query . " WHERE e.store_id = ? ORDER BY e.expense_date DESC");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expenses = $result->fetch_all(MYSQLI_ASSOC);
} else if (isAdmin()) {
    // If admin, get all expenses
    $expenses = $conn->query($expenses_query . " ORDER BY e.expense_date DESC")->fetch_all(MYSQLI_ASSOC);
} else {
    // If worker, get expenses recorded by the worker
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare($expenses_query . " WHERE e.recorded_by = ? ORDER BY e.expense_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expenses = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>
                Expenses Management
                <?php if ($store): ?>
                    for <?php echo ucfirst($store['type']); ?>: <?php echo sanitize($store['name']); ?>
                <?php endif; ?>
            </h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Add New Expense</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="store_id">Store/Lorry*</label>
                            <select id="store_id" name="store_id" required>
                                <option value="">Select Store/Lorry</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $store_id == $s['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($s['name']); ?> 
                                        (<?php echo ucfirst($s['type']); ?>
                                        <?php if ($s['type'] === 'lorry' && !empty($s['number_plate'])): ?>
                                            - <?php echo sanitize($s['number_plate']); ?>
                                        <?php endif; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Amount*</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description*</label>
                            <textarea id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="expense_date">Date*</label>
                            <input type="date" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="add_expense" class="btn btn-primary">Record Expense</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h2>
                        Expenses List 
                        <?php if ($store): ?>
                            for <?php echo sanitize($store['name']); ?>
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Store/Lorry</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No expenses found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                                        <td>
                                            <?php echo sanitize($expense['store_name']); ?> 
                                            (<?php echo ucfirst($expense['store_type']); ?>)
                                        </td>
                                        <td><?php echo formatCurrency($expense['amount']); ?></td>
                                        <td><?php echo sanitize($expense['description']); ?></td>
                                        <td><?php echo sanitize($expense['recorded_by_name']); ?></td>
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
