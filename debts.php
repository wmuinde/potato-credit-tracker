<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/log_activity.php';

// Check if user is logged in
if (!isLoggedIn()) {
   header("Location: login.php");
   exit();
}

// Get user role
$userRole = $_SESSION['user_role'] ?? 'worker';
$userId = $_SESSION['user_id'] ?? 0;

// Get filter parameters
$filterCustomer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$filterVehicle = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filterMinAmount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$sql = "
    SELECT 
        s.id as sale_id,
        s.date as sale_date,
        s.amount as original_amount,
        c.id as customer_id,
        c.name as customer_name,
        c.phone as customer_phone,
        v.id as vehicle_id,
        v.license_plate,
        u.name as worker_name,
        COALESCE(p.amount_paid, 0) as amount_paid,
        (s.amount - COALESCE(p.amount_paid, 0)) as balance,
        CASE 
            WHEN COALESCE(p.amount_paid, 0) >= s.amount THEN 'Paid'
            WHEN COALESCE(p.amount_paid, 0) > 0 THEN 'Partial'
            ELSE 'Unpaid'
        END as status,
        DATEDIFF(CURRENT_DATE, s.date) as days_outstanding
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN vehicles v ON s.vehicle_id = v.id
    JOIN users u ON s.worker_id = u.id
    LEFT JOIN (
        SELECT debt_id, SUM(amount) as amount_paid 
        FROM payments 
        GROUP BY debt_id
    ) p ON s.id = p.debt_id
    WHERE s.payment_type = 'credit' AND (s.amount - COALESCE(p.amount_paid, 0)) > 0
";

$params = [];

if ($filterCustomer) {
    $sql .= " AND c.id = :customer_id";
    $params[':customer_id'] = $filterCustomer;
}

if ($filterVehicle) {
    $sql .= " AND v.id = :vehicle_id";
    $params[':vehicle_id'] = $filterVehicle;
}

if ($filterDateFrom) {
    $sql .= " AND s.date >= :date_from";
    $params[':date_from'] = $filterDateFrom;
}

if ($filterDateTo) {
    $sql .= " AND s.date <= :date_to";
    $params[':date_to'] = $filterDateTo;
}

if ($filterMinAmount > 0) {
    $sql .= " AND (s.amount - COALESCE(p.amount_paid, 0)) >= :min_amount";
    $params[':min_amount'] = $filterMinAmount;
}

if ($filterStatus) {
    if ($filterStatus === 'Paid') {
        $sql .= " AND COALESCE(p.amount_paid, 0) >= s.amount";
    } elseif ($filterStatus === 'Partial') {
        $sql .= " AND COALESCE(p.amount_paid, 0) > 0 AND COALESCE(p.amount_paid, 0) < s.amount";
    } elseif ($filterStatus === 'Unpaid') {
        $sql .= " AND COALESCE(p.amount_paid, 0) = 0";
    }
}

// Add sorting
$sql .= " ORDER BY balance DESC";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$debts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalOriginalAmount = 0;
$totalAmountPaid = 0;
$totalBalance = 0;

foreach ($debts as $debt) {
    $totalOriginalAmount += $debt['original_amount'];
    $totalAmountPaid += $debt['amount_paid'];
    $totalBalance += $debt['balance'];
}

// Get all customers for filter
$stmt = $conn->prepare("SELECT id, name FROM customers ORDER BY name");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all vehicles for filter
if ($userRole == 'admin') {
    $vehicles = getAllVehicles($conn);
} else {
    $vehicles = getWorkerVehicles($conn, $userId);
}

// Log activity
logActivity($conn, $userId, "Viewed debts page", "", "info");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debts - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <h1 class="h2 fw-bold">Outstanding Debts</h1>
                        <p class="text-muted">Manage and track all credit sales with outstanding balances</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                                <i data-feather="filter" class="me-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                <i data-feather="printer" class="me-1"></i> Print
                            </button>
                            <a href="reports.php" class="btn btn-sm btn-outline-primary">
                                <i data-feather="file-text" class="me-1"></i> Generate PDF
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card warning">
                                    <div class="stat-icon">
                                        <i data-feather="alert-circle"></i>
                                    </div>
                                    <div class="stat-title">Total Outstanding</div>
                                    <div class="stat-value" style="font-size: 1.3rem;"><?php echo formatCurrency($totalBalance); ?></div>
                                    <div class="stat-desc">
                                        From <?php echo count($debts); ?> unpaid credit sales
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card primary">
                                    <div class="stat-icon">
                                        <i data-feather="dollar-sign"></i>
                                    </div>
                                    <div class="stat-title">Original Amount</div>
                                    <div class="stat-value" style="font-size: 1.3rem;"><?php echo formatCurrency($totalOriginalAmount); ?></div>
                                    <div class="stat-desc">
                                        Total value of credit sales
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <div class="stat-card success">
                                    <div class="stat-icon">
                                        <i data-feather="check-circle"></i>
                                    </div>
                                    <div class="stat-title">Amount Paid</div>
                                    <div class="stat-value" style="font-size: 1.3rem;"><?php echo formatCurrency($totalAmountPaid); ?></div>
                                    <div class="stat-desc">
                                        <?php
                                        if ($totalOriginalAmount > 0) {
                                            echo number_format(($totalAmountPaid / $totalOriginalAmount) * 100, 1) . '% of total credit sales';
                                        } else {
                                            echo '0% of total credit sales';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Debts Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Outstanding Debts</h5>
                        <span class="badge bg-primary"><?php echo count($debts); ?> records found</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Vehicle</th>
                                        <th>Sale Date</th>
                                        <th>Original Amount</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Days</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($debts as $debt): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i data-feather="user" style="width: 16px; height: 16px;"></i>
                                                </div>
                                                <a href="customer-details.php?id=<?php echo $debt['customer_id']; ?>" class="fw-medium text-decoration-none">
                                                    <?php echo $debt['customer_name']; ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?php echo $debt['customer_phone']; ?></td>
                                        <td>
                                            <a href="vehicle-details.php?id=<?php echo $debt['vehicle_id']; ?>" class="text-decoration-none">
                                                <?php echo $debt['license_plate']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo formatDate($debt['sale_date']); ?></td>
                                        <td><?php echo formatCurrency($debt['original_amount']); ?></td>
                                        <td><?php echo formatCurrency($debt['amount_paid']); ?></td>
                                        <td class="fw-medium"><?php echo formatCurrency($debt['balance']); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'bg-secondary';
                                            if ($debt['status'] == 'Paid') {
                                                $statusClass = 'bg-success';
                                            } elseif ($debt['status'] == 'Partial') {
                                                $statusClass = 'bg-warning';
                                            } elseif ($debt['status'] == 'Unpaid') {
                                                $statusClass = 'bg-danger';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo $debt['status']; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $days = $debt['days_outstanding'];
                                            $daysClass = $days > 30 ? 'text-danger fw-bold' : ($days > 14 ? 'text-warning' : '');
                                            echo '<span class="' . $daysClass . '">' . $days . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <a href="debt-details.php?id=<?php echo $debt['sale_id']; ?>" class="btn btn-sm btn-primary">
                                                <i data-feather="eye" style="width: 14px; height: 14px;"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($debts)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-3">No outstanding debts found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="4">TOTALS</td>
                                        <td><?php echo formatCurrency($totalOriginalAmount); ?></td>
                                        <td><?php echo formatCurrency($totalAmountPaid); ?></td>
                                        <td><?php echo formatCurrency($totalBalance); ?></td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="get">
                    <div class="modal-header">
                        <h5 class="modal-title" id="filterModalLabel">Filter Debts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Customer</label>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="">All Customers</option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo $filterCustomer == $customer['id'] ? 'selected' : ''; ?>>
                                    <?php echo $customer['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Vehicle</label>
                            <select class="form-select" id="vehicle_id" name="vehicle_id">
                                <option value="">All Vehicles</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['id']; ?>" <?php echo $filterVehicle == $vehicle['id'] ? 'selected' : ''; ?>>
                                    <?php echo $vehicle['license_plate']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $filterDateFrom; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $filterDateTo; ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="min_amount" class="form-label">Minimum Balance</label>
                            <input type="number" class="form-control" id="min_amount" name="min_amount" value="<?php echo $filterMinAmount; ?>" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="Unpaid" <?php echo $filterStatus == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="Partial" <?php echo $filterStatus == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="debts.php" class="btn btn-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
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
        });
    </script>
</body>
</html>
