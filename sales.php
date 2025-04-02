
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

// Get all sales with related information
if (isAdmin()) {
    // Admins see all sales
    $query = "SELECT s.*, c.full_name as customer_name, u.full_name as recorded_by_name, 
              st.name as store_name, st.type as store_type
              FROM sales s 
              LEFT JOIN customers c ON s.customer_id = c.id 
              JOIN users u ON s.created_by = u.id 
              JOIN stores st ON s.store_id = st.id
              ORDER BY s.created_at DESC";
    $sales = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
} else {
    // Workers see only their sales
    $worker_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT s.*, c.full_name as customer_name, u.full_name as recorded_by_name, 
                           st.name as store_name, st.type as store_type
                           FROM sales s 
                           LEFT JOIN customers c ON s.customer_id = c.id 
                           JOIN users u ON s.created_by = u.id 
                           JOIN stores st ON s.store_id = st.id
                           WHERE s.created_by = ?
                           ORDER BY s.created_at DESC");
    $stmt->bind_param("i", $worker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sales = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Sales History</h1>
            
            <div class="card-tools mb-4">
                <a href="record_sale.php" class="btn btn-primary">Record New Sale</a>
                <input type="text" id="salesSearch" placeholder="Search sales..." class="search-input">
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>All Sales</h2>
                </div>
                <div class="card-body">
                    <table class="data-table" id="salesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Store/Lorry</th>
                                <th>Bags</th>
                                <th>Amount</th>
                                <th>Payment Type</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No sales found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></td>
                                        <td>
                                            <?php if ($sale['payment_type'] === 'credit'): ?>
                                                <a href="customer_details.php?id=<?php echo $sale['customer_id']; ?>">
                                                    <?php echo sanitize($sale['customer_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                Cash Customer
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo sanitize($sale['store_name']); ?> 
                                            (<?php echo ucfirst($sale['store_type']); ?>)
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
        document.getElementById('salesSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('salesTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
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
