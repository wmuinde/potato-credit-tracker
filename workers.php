<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: index.php");
    exit();
}

// Process add worker form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_worker'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (registerUser($conn, $name, $username, $password, 'worker')) {
        header("Location: workers.php?success=added");
        exit();
    } else {
        $error = "Username already exists";
    }
}

// Process edit worker form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_worker'])) {
    $workerId = $_POST['worker_id'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Check if username already exists for another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':id', $workerId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $error = "Username already exists";
    } else {
        // Update worker
        if (empty($password)) {
            // Update without changing password
            $stmt = $conn->prepare("UPDATE users SET name = :name, username = :username WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':id', $workerId);
        } else {
            // Update with new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = :name, username = :username, password = :password WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':id', $workerId);
        }
        
        if ($stmt->execute()) {
            header("Location: workers.php?success=updated");
            exit();
        }
    }
}

// Get all workers
$stmt = $conn->prepare("
    SELECT u.*, 
        (SELECT COUNT(DISTINCT va.vehicle_id) FROM vehicle_assignments va WHERE va.worker_id = u.id) as vehicle_count,
        (SELECT SUM(p.amount - COALESCE(f.forwarded_amount, 0)) 
         FROM payments p 
         LEFT JOIN (
             SELECT payment_id, SUM(amount) as forwarded_amount
             FROM forwarded_funds
             GROUP BY payment_id
         ) f ON p.id = f.payment_id
         WHERE p.worker_id = u.id
        ) as held_funds
    FROM users u
    WHERE u.role = 'worker'
    ORDER BY u.name
");
$stmt->execute();
$workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workers - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Workers</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkerModal">
                            Add New Worker
                        </button>
                    </div>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        $success = $_GET['success'];
                        if ($success == 'added') echo 'Worker added successfully';
                        elseif ($success == 'updated') echo 'Worker updated successfully';
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
                
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Assigned Vehicles</th>
                                <th>Held Funds</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $worker): ?>
                            <tr>
                                <td><?php echo $worker['name']; ?></td>
                                <td><?php echo $worker['username']; ?></td>
                                <td><?php echo $worker['vehicle_count']; ?></td>
                                <td>
                                    <?php 
                                    $heldFunds = $worker['held_funds'] ?? 0;
                                    echo formatCurrency($heldFunds);
                                    if ($heldFunds > HELD_FUNDS_THRESHOLD) {
                                        echo ' <span class="badge bg-danger">Exceeds Threshold</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editWorkerModal"
                                            data-worker-id="<?php echo $worker['id']; ?>"
                                            data-worker-name="<?php echo $worker['name']; ?>"
                                            data-worker-username="<?php echo $worker['username']; ?>">
                                        Edit
                                    </button>
                                    <?php if ($heldFunds > 0): ?>
                                    <a href="worker-funds.php?id=<?php echo $worker['id']; ?>" class="btn btn-sm btn-warning">View Funds</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Worker Modal -->
    <div class="modal fade" id="addWorkerModal" tabindex="-1" aria-labelledby="addWorkerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addWorkerModalLabel">Add New Worker</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_worker" class="btn btn-primary">Add Worker</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Worker Modal -->
    <div class="modal fade" id="editWorkerModal" tabindex="-1" aria-labelledby="editWorkerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editWorkerModalLabel">Edit Worker</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_worker_id" name="worker_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <div class="form-text">Leave blank to keep current password</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_worker" class="btn btn-primary">Save Changes</button>
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
            
            // Set up edit worker modal
            const editWorkerModal = document.getElementById('editWorkerModal');
            if (editWorkerModal) {
                editWorkerModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const workerId = button.getAttribute('data-worker-id');
                    const workerName = button.getAttribute('data-worker-name');
                    const workerUsername = button.getAttribute('data-worker-username');
                    
                    const workerIdInput = editWorkerModal.querySelector('#edit_worker_id');
                    const nameInput = editWorkerModal.querySelector('#edit_name');
                    const usernameInput = editWorkerModal.querySelector('#edit_username');
                    
                    workerIdInput.value = workerId;
                    nameInput.value = workerName;
                    usernameInput.value = workerUsername;
                });
            }
        });
    </script>
</body>
</html>
