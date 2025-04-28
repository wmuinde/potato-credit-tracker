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

// Process update settings form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $siteName = $_POST['site_name'];
    $currency = $_POST['currency'];
    $dateFormat = $_POST['date_format'];
    $heldFundsThreshold = $_POST['held_funds_threshold'];
    $companyName = $_POST['company_name'];
    $companyAddress = $_POST['company_address'];
    $companyPhone = $_POST['company_phone'];
    $companyEmail = $_POST['company_email'];
    
    // Update config.php file
    $configFile = 'includes/config.php';
    $configContent = file_get_contents($configFile);
    
    // Replace values
    $configContent = preg_replace('/define$$\'SITE_NAME\',\s*\'.*?\'$$;/', "define('SITE_NAME', '$siteName');", $configContent);
    $configContent = preg_replace('/define$$\'CURRENCY\',\s*\'.*?\'$$;/', "define('CURRENCY', '$currency');", $configContent);
    $configContent = preg_replace('/define$$\'DATE_FORMAT\',\s*\'.*?\'$$;/', "define('DATE_FORMAT', '$dateFormat');", $configContent);
    $configContent = preg_replace('/define$$\'HELD_FUNDS_THRESHOLD\',\s*\d+$$;/', "define('HELD_FUNDS_THRESHOLD', $heldFundsThreshold);", $configContent);
    $configContent = preg_replace('/define$$\'COMPANY_NAME\',\s*\'.*?\'$$;/', "define('COMPANY_NAME', '$companyName');", $configContent);
    $configContent = preg_replace('/define$$\'COMPANY_ADDRESS\',\s*\'.*?\'$$;/', "define('COMPANY_ADDRESS', '$companyAddress');", $configContent);
    $configContent = preg_replace('/define$$\'COMPANY_PHONE\',\s*\'.*?\'$$;/', "define('COMPANY_PHONE', '$companyPhone');", $configContent);
    $configContent = preg_replace('/define$$\'COMPANY_EMAIL\',\s*\'.*?\'$$;/', "define('COMPANY_EMAIL', '$companyEmail');", $configContent);
    
    // Write back to file
    if (file_put_contents($configFile, $configContent)) {
        header("Location: settings.php?success=updated");
        exit();
    } else {
        $error = "Failed to update settings. Check file permissions.";
    }
}

// Process change admin password form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        $passwordError = "Current password is incorrect";
    } elseif ($newPassword != $confirmPassword) {
        $passwordError = "New passwords do not match";
    } else {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            header("Location: settings.php?success=password_changed");
            exit();
        } else {
            $passwordError = "Failed to update password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Potato Sales Management System</title>
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
                    <h1 class="h2">System Settings</h1>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        $success = $_GET['success'];
                        if ($success == 'updated') echo 'Settings updated successfully';
                        elseif ($success == 'password_changed') echo 'Password changed successfully';
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
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">General Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">Site Name</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo SITE_NAME; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="currency" class="form-label">Currency</label>
                                        <input type="text" class="form-control" id="currency" name="currency" value="<?php echo CURRENCY; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="date_format" class="form-label">Date Format</label>
                                        <input type="text" class="form-control" id="date_format" name="date_format" value="<?php echo DATE_FORMAT; ?>" required>
                                        <div class="form-text">PHP date format (e.g., d/m/Y)</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="held_funds_threshold" class="form-label">Held Funds Threshold</label>
                                        <input type="number" class="form-control" id="held_funds_threshold" name="held_funds_threshold" value="<?php echo HELD_FUNDS_THRESHOLD; ?>" required>
                                        <div class="form-text">Alert if worker holds more than this amount</div>
                                    </div>
                                    <hr>
                                    <h6>Company Information</h6>
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo COMPANY_NAME; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="company_address" class="form-label">Company Address</label>
                                        <input type="text" class="form-control" id="company_address" name="company_address" value="<?php echo COMPANY_ADDRESS; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="company_phone" class="form-label">Company Phone</label>
                                        <input type="text" class="form-control" id="company_phone" name="company_phone" value="<?php echo COMPANY_PHONE; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="company_email" class="form-label">Company Email</label>
                                        <input type="email" class="form-control" id="company_email" name="company_email" value="<?php echo COMPANY_EMAIL; ?>" required>
                                    </div>
                                    <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Change Admin Password</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($passwordError)): ?>
                                    <div class="alert alert-danger"><?php echo $passwordError; ?></div>
                                <?php endif; ?>
                                
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">System Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th>PHP Version:</th>
                                        <td><?php echo phpversion(); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Database:</th>
                                        <td>MySQL</td>
                                    </tr>
                                    <tr>
                                        <th>Server:</th>
                                        <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>System Time:</th>
                                        <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                    </tr>
                                </table>
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
