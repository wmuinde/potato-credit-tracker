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

// Check if user is admin for adding/editing vehicles
$isAdmin = isAdmin();
$canManageVehicles = isLoggedIn(); // Allow all logged-in users to manage vehicles

// Process add vehicle form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_vehicle']) && $canManageVehicles) {
   $licensePlate = trim($_POST['license_plate']);
   $bagsLoaded = $_POST['bags_loaded'];
   $bagValue = $_POST['bag_value'];
   $workerIds = $_POST['worker_ids'] ?? [];
   
   // Check if vehicle with this license plate already exists
   $checkStmt = $conn->prepare("SELECT id FROM vehicles WHERE license_plate = :license_plate");
   $checkStmt->bindParam(':license_plate', $licensePlate);
   $checkStmt->execute();
   
   if ($checkStmt->rowCount() > 0) {
       // Vehicle already exists - set error message
       $error = "A vehicle with license plate '$licensePlate' already exists. Please use a different license plate.";
   } else {
       // Add vehicle
       $stmt = $conn->prepare("
           INSERT INTO vehicles (license_plate, bags_loaded, bag_value)
           VALUES (:license_plate, :bags_loaded, :bag_value)
       ");
       $stmt->bindParam(':license_plate', $licensePlate);
       $stmt->bindParam(':bags_loaded', $bagsLoaded);
       $stmt->bindParam(':bag_value', $bagValue);
       
       if ($stmt->execute()) {
           $vehicleId = $conn->lastInsertId();
           
           // Assign workers to vehicle
           foreach ($workerIds as $workerId) {
               $stmt = $conn->prepare("
                   INSERT INTO vehicle_assignments (vehicle_id, worker_id)
                   VALUES (:vehicle_id, :worker_id)
               ");
               $stmt->bindParam(':vehicle_id', $vehicleId);
               $stmt->bindParam(':worker_id', $workerId);
               $stmt->execute();
           }
           
           header("Location: vehicles.php?success=added");
           exit();
       } else {
           $error = "Failed to add vehicle. Please try again.";
       }
   }
}

// Process edit vehicle form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_vehicle']) && $canManageVehicles) {
    $vehicleId = $_POST['vehicle_id'];
    $licensePlate = $_POST['license_plate'];
    $bagsLoaded = $_POST['bags_loaded'];
    $bagValue = $_POST['bag_value'];
    $workerIds = $_POST['worker_ids'] ?? [];
    
    // Update vehicle
    $stmt = $conn->prepare("
        UPDATE vehicles 
        SET license_plate = :license_plate, bags_loaded = :bags_loaded, bag_value = :bag_value
        WHERE id = :id
    ");
    $stmt->bindParam(':license_plate', $licensePlate);
    $stmt->bindParam(':bags_loaded', $bagsLoaded);
    $stmt->bindParam(':bag_value', $bagValue);
    $stmt->bindParam(':id', $vehicleId);
    
    if ($stmt->execute()) {
        // Remove existing assignments
        $stmt = $conn->prepare("DELETE FROM vehicle_assignments WHERE vehicle_id = :vehicle_id");
        $stmt->bindParam(':vehicle_id', $vehicleId);
        $stmt->execute();
        
        // Add new assignments
        foreach ($workerIds as $workerId) {
            $stmt = $conn->prepare("
                INSERT INTO vehicle_assignments (vehicle_id, worker_id)
                VALUES (:vehicle_id, :worker_id)
            ");
            $stmt->bindParam(':vehicle_id', $vehicleId);
            $stmt->bindParam(':worker_id', $workerId);
            $stmt->execute();
        }
        
        header("Location: vehicles.php?success=updated");
        exit();
    }
}

// Get all vehicles
if ($isAdmin) {
    $vehicles = getAllVehicles($conn);
} else {
    $vehicles = getWorkerVehicles($conn, $_SESSION['user_id']);
}

// Get all workers for assignment
$workers = [];
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'worker' ORDER BY name");
    $stmt->execute();
    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get bag sales data for each vehicle
foreach ($vehicles as &$vehicle) {
    // Get cash sales bags
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(bags_sold), 0) as cash_bags
        FROM sales 
        WHERE vehicle_id = :vehicle_id AND payment_type = 'cash'
    ");
    $stmt->bindParam(':vehicle_id', $vehicle['id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $vehicle['cash_bags'] = $result['cash_bags'] ?? 0;
    
    // Get credit sales bags
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(bags_sold), 0) as credit_bags
        FROM sales 
        WHERE vehicle_id = :vehicle_id AND payment_type = 'credit'
    ");
    $stmt->bindParam(':vehicle_id', $vehicle['id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $vehicle['credit_bags'] = $result['credit_bags'] ?? 0;
    
    // Calculate unsold bags
    $vehicle['total_sold_bags'] = $vehicle['cash_bags'] + $vehicle['credit_bags'];
    $vehicle['unsold_bags'] = $vehicle['bags_loaded'] - $vehicle['total_sold_bags'];
    
    // Get credit payments
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as credit_payments
        FROM payments p
        JOIN sales s ON p.debt_id = s.id
        WHERE s.vehicle_id = :vehicle_id AND s.payment_type = 'credit'
    ");
    $stmt->bindParam(':vehicle_id', $vehicle['id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $vehicle['credit_payments'] = $result['credit_payments'] ?? 0;
    
    // Calculate net cash sales (cash sales - expenses)
    $vehicle['net_cash_sales'] = ($vehicle['cash_sales'] ?? 0) - ($vehicle['expenses'] ?? 0);
    
    // Calculate total collections (cash sales + credit payments - expenses)
    $vehicle['total_collections'] = ($vehicle['cash_sales'] ?? 0) + ($vehicle['credit_payments'] ?? 0) - ($vehicle['expenses'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicles - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
    .vehicle-card .card-body {
        padding: 0.75rem;
    }
    
    .vehicle-card .data-box {
        padding: 0.5rem;
        border-radius: 0.25rem;
        border: 1px solid rgba(0,0,0,0.125);
        height: 100%;
    }
    
    .vehicle-card .data-box h6 {
        font-size: 0.7rem;
        margin-bottom: 0.25rem;
        color: #6c757d;
    }
    
    .vehicle-card .data-box p {
        font-size: 0.85rem;
        margin-bottom: 0;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    @media (max-width: 767px) {
        .vehicle-card .data-box h6 {
            font-size: 0.65rem;
        }
        
        .vehicle-card .data-box p {
            font-size: 0.8rem;
        }
        
        .vehicle-card .card-header h5 {
            font-size: 0.9rem;
        }
        
        .vehicle-card .card-body {
            padding: 0.5rem;
        }
        
        .vehicle-card .row {
            margin-left: -5px;
            margin-right: -5px;
        }
        
        .vehicle-card .col-6 {
            padding-left: 5px;
            padding-right: 5px;
        }
        
        .vehicle-card .data-box {
            padding: 0.35rem;
        }
        
        .vehicle-card .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
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
                    <h1 class="h2">Vehicles</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($canManageVehicles): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                            Add New Vehicle
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        $success = $_GET['success'];
                        if ($success == 'added') echo 'Vehicle added successfully';
                        elseif ($success == 'updated') echo 'Vehicle updated successfully';
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
    <?php foreach ($vehicles as $vehicle): ?>
    <div class="col-lg-4 col-md-6 col-12 mb-3">
        <div class="card h-100 vehicle-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h5 class="mb-0"><?php echo $vehicle['license_plate']; ?></h5>
                <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-sm btn-info" 
                        data-bs-toggle="modal" 
                        data-bs-target="#editVehicleModal"
                        data-vehicle-id="<?php echo $vehicle['id']; ?>"
                        data-license-plate="<?php echo $vehicle['license_plate']; ?>"
                        data-bags-loaded="<?php echo $vehicle['bags_loaded']; ?>"
                        data-bag-value="<?php echo $vehicle['bag_value']; ?>">
                    Edit
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="data-box">
                            <h6>Bags Loaded</h6>
                            <p><?php echo $vehicle['bags_loaded']; ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-box">
                            <h6>Bag Value</h6>
                            <p><?php echo formatCurrency($vehicle['bag_value']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="data-box">
                            <h6>Cash Sales</h6>
                            <p><?php echo formatCurrency($vehicle['cash_sales'] ?? 0); ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-box">
                            <h6>Expenses</h6>
                            <p><?php echo formatCurrency($vehicle['expenses'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="data-box">
                            <h6>Net Cash Sales</h6>
                            <p><?php echo formatCurrency($vehicle['net_cash_sales']); ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-box">
                            <h6>Credit Payments</h6>
                            <p><?php echo formatCurrency($vehicle['credit_payments']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="data-box bg-primary-light">
                            <h6>Total Collections</h6>
                            <p class="text-primary"><?php echo formatCurrency($vehicle['total_collections']); ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="data-box">
                            <h6>Profit</h6>
                            <p><?php echo formatCurrency($vehicle['profit'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
                <div class="d-grid mt-2">
                    <a href="vehicle-details.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
            </main>
        </div>
    </div>
    
    <?php if ($canManageVehicles): ?>
    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="license_plate" class="form-label">License Plate</label>
                            <input type="text" class="form-control" id="license_plate" name="license_plate" required>
                        </div>
                        <div class="mb-3">
                            <label for="bags_loaded" class="form-label">Bags Loaded</label>
                            <input type="number" class="form-control" id="bags_loaded" name="bags_loaded" required>
                        </div>
                        <div class="mb-3">
                            <label for="bag_value" class="form-label">Bag Value</label>
                            <input type="number" class="form-control" id="bag_value" name="bag_value" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="worker_ids" class="form-label">Assign Workers</label>
                            <select class="form-select" id="worker_ids" name="worker_ids[]" multiple>
                                <?php foreach ($workers as $worker): ?>
                                <option value="<?php echo $worker['id']; ?>"><?php echo $worker['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple workers</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_vehicle" class="btn btn-primary">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Edit Vehicle Modal -->
    <div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_vehicle_id" name="vehicle_id">
                        <div class="mb-3">
                            <label for="edit_license_plate" class="form-label">License Plate</label>
                            <input type="text" class="form-control" id="edit_license_plate" name="license_plate" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_bags_loaded" class="form-label">Bags Loaded</label>
                            <input type="number" class="form-control" id="edit_bags_loaded" name="bags_loaded" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_bag_value" class="form-label">Bag Value</label>
                            <input type="number" class="form-control" id="edit_bag_value" name="bag_value" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_worker_ids" class="form-label">Assign Workers</label>
                            <select class="form-select" id="edit_worker_ids" name="worker_ids[]" multiple>
                                <?php foreach ($workers as $worker): ?>
                                <option value="<?php echo $worker['id']; ?>"><?php echo $worker['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple workers</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_vehicle" class="btn btn-primary">Save Changes</button>
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
            
            // Set up edit vehicle modal
            const editVehicleModal = document.getElementById('editVehicleModal');
            if (editVehicleModal) {
                editVehicleModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const vehicleId = button.getAttribute('data-vehicle-id');
                    const licensePlate = button.getAttribute('data-license-plate');
                    const bagsLoaded = button.getAttribute('data-bags-loaded');
                    const bagValue = button.getAttribute('data-bag-value');
                    
                    const vehicleIdInput = editVehicleModal.querySelector('#edit_vehicle_id');
                    const licensePlateInput = editVehicleModal.querySelector('#edit_license_plate');
                    const bagsLoadedInput = editVehicleModal.querySelector('#edit_bags_loaded');
                    const bagValueInput = editVehicleModal.querySelector('#edit_bag_value');
                    
                    vehicleIdInput.value = vehicleId;
                    licensePlateInput.value = licensePlate;
                    bagsLoadedInput.value = bagsLoaded;
                    bagValueInput.value = bagValue;
                    
                    // Load assigned workers
                    fetch('get-vehicle-workers.php?id=' + vehicleId)
                        .then(response => response.json())
                        .then(data => {
                            const workerSelect = editVehicleModal.querySelector('#edit_worker_ids');
                            for (let i = 0; i < workerSelect.options.length; i++) {
                                workerSelect.options[i].selected = data.includes(parseInt(workerSelect.options[i].value));
                            }
                        });
                });
            }
        });
    </script>
</body>
</html>
