<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/log_activity.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: index.php");
    exit();
}

// Get filter parameters
$filterUser = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$sql = "
    SELECT al.*, u.name as user_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($filterUser) {
    $sql .= " AND al.user_id = :user_id";
    $params[':user_id'] = $filterUser;
}

if ($filterType) {
    $sql .= " AND al.type = :type";
    $params[':type'] = $filterType;
}

if ($filterDateFrom) {
    $sql .= " AND DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $filterDateFrom;
}

if ($filterDateTo) {
    $sql .= " AND DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $filterDateTo;
}

$sql .= " ORDER BY al.created_at DESC LIMIT 100";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for filter
$stmt = $conn->prepare("SELECT id, name FROM users ORDER BY name");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Potato Sales Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <div>
                        <h1 class="h2 fw-bold">Activity Logs</h1>
                        <p class="text-muted">View system activity logs</p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filter Logs</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="user_id" class="form-label">User</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo $user['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Log Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="info" <?php echo $filterType == 'info' ? 'selected' : ''; ?>>Info</option>
                                    <option value="success" <?php echo $filterType == 'success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="warning" <?php echo $filterType == 'warning' ? 'selected' : ''; ?>>Warning</option>
                                    <option value="danger" <?php echo $filterType == 'danger' ? 'selected' : ''; ?>>Danger</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $filterDateFrom; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $filterDateTo; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="activity-logs.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Activity Logs</h5>
                        <span class="badge bg-primary"><?php echo count($logs); ?> logs found</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar bg-primary-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i data-feather="user" style="width: 16px; height: 16px;"></i>
                                                </div>
                                                <span class="fw-medium"><?php echo $log['user_name']; ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $log['action']; ?></td>
                                        <td><?php echo $log['details']; ?></td>
                                        <td>
                                            <?php 
                                            $badgeClass = 'bg-secondary';
                                            if ($log['type'] == 'success') $badgeClass = 'bg-success';
                                            elseif ($log['type'] == 'warning') $badgeClass = 'bg-warning';
                                            elseif ($log['type'] == 'danger') $badgeClass = 'bg-danger';
                                            elseif ($log['type'] == 'info') $badgeClass = 'bg-info';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($log['type']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">No logs found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
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
