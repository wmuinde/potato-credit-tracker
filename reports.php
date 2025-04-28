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

// Get user role
$userRole = $_SESSION['user_role'] ?? 'worker';
$userId = $_SESSION['user_id'] ?? 0;

// Get vehicles for filter
if ($userRole == 'admin') {
    $vehicles = getAllVehicles($conn);
} else {
    $vehicles = getWorkerVehicles($conn, $userId);
}

// Generate PDF report
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_pdf'])) {
   $vehicleId = !empty($_POST['vehicle_id']) ? $_POST['vehicle_id'] : null;
   $sortBy = $_POST['sort_by'] ?? 'amount';
   
   // Generate PDF content
   $pdfContent = generateDebtorsPDF($conn, $vehicleId);
   
   // Check if we got an error message instead of PDF
   if (is_string($pdfContent) && strpos($pdfContent, 'PDF generation failed') === 0) {
       $_SESSION['pdf_error'] = $pdfContent;
       header("Location: reports.php");
       exit();
   }
   
   // Set headers for PDF download
   header('Content-Type: application/pdf');
   header('Content-Disposition: attachment; filename="debtors_report.pdf"');
   header('Content-Length: ' . strlen($pdfContent));
   
   // Output PDF content
   echo $pdfContent;
   exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Potato Sales Management System</title>
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
                    <h1 class="h2">Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['pdf_error'])): ?>
                   <div class="alert alert-danger alert-dismissible fade show" role="alert">
                       <?php echo $_SESSION['pdf_error']; unset($_SESSION['pdf_error']); ?>
                       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                   </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Debtors Report</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="vehicle_id" class="form-label">Filter by Vehicle</label>
                                        <select class="form-select" id="vehicle_id" name="vehicle_id">
                                            <option value="">All Vehicles</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>"><?php echo $vehicle['license_plate']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="sort_by" class="form-label">Sort By</label>
                                        <select class="form-select" id="sort_by" name="sort_by">
                                            <option value="amount">Debt Amount (High-Low)</option>
                                            <option value="date">Debt Duration (Oldest-Newest)</option>
                                            <option value="name">Customer Name (A-Z)</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="generate_pdf" class="btn btn-primary">Generate PDF</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Import Customer Data</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="import-process.php" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="csv_file" class="form-label">Upload Excel/CSV File</label>
                                        <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,.xlsx,.xls" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="vehicle_id" class="form-label">Default Vehicle</label>
                                        <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>"><?php echo $vehicle['license_plate']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Used if vehicle plate is not specified in the file</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Upload and Import</button>
                                </form>
                                
                                <div class="mt-4">
                                    <h6>File Requirements:</h6>
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Column</th>
                                                <th>Format</th>
                                                <th>Required</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Customer Name</td>
                                                <td>Text</td>
                                                <td>Yes</td>
                                            </tr>
                                            <tr>
                                                <td>Phone Number</td>
                                                <td>Numeric</td>
                                                <td>No</td>
                                            </tr>
                                            <tr>
                                                <td>Debt Amount</td>
                                                <td>Currency</td>
                                                <td>Yes</td>
                                            </tr>
                                            <tr>
                                                <td>Vehicle Plate</td>
                                                <td>Text</td>
                                                <td>Yes</td>
                                            </tr>
                                            <tr>
                                                <td>Sale Date</td>
                                                <td>DD/MM/YYYY</td>
                                                <td>Yes</td>
                                            </tr>
                                            <tr>
                                                <td>Worker ID</td>
                                                <td>Numeric</td>
                                                <td>Yes</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Current Debtors</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Phone</th>
                                                <th>Vehicle</th>
                                                <th>Sale Date</th>
                                                <th>Original Amount</th>
                                                <th>Paid Amount</th>
                                                <th>Balance</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $debtors = getAllDebtors($conn);
                                            foreach ($debtors as $debtor): 
                                            ?>
                                            <tr>
                                                <td><?php echo $debtor['name']; ?></td>
                                                <td><?php echo $debtor['phone']; ?></td>
                                                <td><?php echo $debtor['license_plate']; ?></td>
                                                <td><?php echo formatDate($debtor['sale_date']); ?></td>
                                                <td><?php echo formatCurrency($debtor['original_amount']); ?></td>
                                                <td><?php echo formatCurrency($debtor['amount_paid']); ?></td>
                                                <td><?php echo formatCurrency($debtor['balance']); ?></td>
                                                <td>
                                                    <a href="debt-details.php?id=<?php echo $debtor['sale_id']; ?>" class="btn btn-sm btn-info">Details</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
