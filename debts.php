
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

// Get all debts with customer info
$query = "SELECT d.*, s.bags_quantity, s.price_per_bag, s.total_amount, 
          s.customer_id, s.created_by, s.store_id, c.full_name as customer_name,
          u.full_name as recorded_by
          FROM debts d
          JOIN sales s ON d.sale_id = s.id
          JOIN customers c ON s.customer_id = c.id
          JOIN users u ON s.created_by = u.id
          WHERE d.status != 'paid'
          ORDER BY d.last_updated DESC";
$result = $conn->query($query);
$debts = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debts - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Outstanding Debts</h1>
            
            <div class="card">
                <div class="card-header">
                    <h2>All Debts</h2>
                    <div class="card-tools">
                        <input type="text" id="debtSearch" placeholder="Search..." class="search-input">
                    </div>
                </div>
                <div class="card-body">
                    <table class="data-table" id="debtsTable">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Store/Lorry</th>
                                <th>Bags</th>
                                <th>Original Amount</th>
                                <th>Amount Due</th>
                                <th>Amount Paid</th>
                                <th>Recorded By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($debts)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No outstanding debts found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($debts as $debt): ?>
                                    <tr>
                                        <td><?php echo sanitize($debt['customer_name']); ?></td>
                                        <td><?php echo getStoreName($conn, $debt['store_id']); ?></td>
                                        <td><?php echo $debt['bags_quantity']; ?> @ <?php echo formatCurrency($debt['price_per_bag']); ?></td>
                                        <td><?php echo formatCurrency($debt['total_amount']); ?></td>
                                        <td><?php echo formatCurrency($debt['amount_due'] - $debt['amount_paid']); ?></td>
                                        <td><?php echo formatCurrency($debt['amount_paid']); ?></td>
                                        <td><?php echo $debt['recorded_by']; ?></td>
                                        <td>
                                            <?php if ($debt['status'] === 'pending'): ?>
                                                <span class="badge danger">Pending</span>
                                            <?php elseif ($debt['status'] === 'partial'): ?>
                                                <span class="badge warning">Partial</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="record_payment.php?debt_id=<?php echo $debt['id']; ?>" class="btn btn-sm btn-success">Record Payment</a>
                                            <a href="debt_details.php?id=<?php echo $debt['id']; ?>" class="btn btn-sm btn-info">Details</a>
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
        document.getElementById('debtSearch').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('debtsTable');
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
