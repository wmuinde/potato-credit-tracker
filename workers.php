
<?php
require_once 'config.php';

// Check if user is admin
checkAccess('admin');

$success = $error = '';

// Handle form submission for adding new worker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_worker'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    
    if (empty($username) || empty($password) || empty($full_name)) {
        $error = "All fields are required.";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username already exists. Please choose another username.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'worker')");
            $stmt->bind_param("sss", $username, $hashed_password, $full_name);
            
            if ($stmt->execute()) {
                $success = "Worker added successfully.";
            } else {
                $error = "Failed to add worker: " . $conn->error;
            }
        }
    }
}

// Handle worker status change
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    
    if ($action === 'deactivate' || $action === 'activate') {
        $status = ($action === 'activate') ? 'active' : 'inactive';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'worker'");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $success = "Worker " . ($action === 'activate' ? 'activated' : 'deactivated') . " successfully.";
        } else {
            $error = "Failed to update worker status: " . $conn->error;
        }
    }
}

// Get all workers
$workers_result = $conn->query("SELECT * FROM users WHERE role = 'worker' ORDER BY full_name");
$workers = $workers_result->fetch_all(MYSQLI_ASSOC);

// Get worker stats
foreach ($workers as &$worker) {
    // Calculate uncollected amount
    $stmt = $conn->prepare("SELECT SUM(amount - forwarded_to_admin) as uncollected FROM payments WHERE collected_by = ? AND forwarded_to_admin < amount");
    $stmt->bind_param("i", $worker['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $worker['uncollected'] = $row['uncollected'] ?? 0;
    
    // Calculate total collections
    $stmt = $conn->prepare("SELECT SUM(amount) as total_collected FROM payments WHERE collected_by = ?");
    $stmt->bind_param("i", $worker['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $worker['total_collected'] = $row['total_collected'] ?? 0;
    
    // Calculate total forwarded
    $stmt = $conn->prepare("SELECT SUM(forwarded_to_admin) as total_forwarded FROM payments WHERE collected_by = ?");
    $stmt->bind_param("i", $worker['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $worker['total_forwarded'] = $row['total_forwarded'] ?? 0;
}
unset($worker);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workers Management - Potato Credit Tracker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="content">
            <h1>Workers Management</h1>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Add New Worker</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="full_name">Full Name*</label>
                            <input type="text" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username*</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password*</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="add_worker" class="btn btn-primary">Add Worker</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Worker List</h2>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Total Collected</th>
                                <th>Total Forwarded</th>
                                <th>Holding Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($workers)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No workers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($workers as $worker): ?>
                                    <tr>
                                        <td><?php echo sanitize($worker['full_name']); ?></td>
                                        <td><?php echo sanitize($worker['username']); ?></td>
                                        <td><?php echo formatCurrency($worker['total_collected']); ?></td>
                                        <td><?php echo formatCurrency($worker['total_forwarded']); ?></td>
                                        <td><?php echo formatCurrency($worker['uncollected']); ?></td>
                                        <td>
                                            <?php if ($worker['status'] === 'active'): ?>
                                                <span class="badge success">Active</span>
                                            <?php else: ?>
                                                <span class="badge danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="worker_details.php?id=<?php echo $worker['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            
                                            <?php if ($worker['status'] === 'active'): ?>
                                                <a href="workers.php?action=deactivate&id=<?php echo $worker['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to deactivate this worker?')">Deactivate</a>
                                            <?php else: ?>
                                                <a href="workers.php?action=activate&id=<?php echo $worker['id']; ?>" class="btn btn-sm btn-success">Activate</a>
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
</body>
</html>
