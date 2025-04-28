<?php
// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Authenticate user
function authenticateUser($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT id, name, role, password FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    
    return false;
}

// Register new user
function registerUser($conn, $name, $username, $password, $role = 'worker') {
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return false; // Username already exists
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (:name, :username, :password, :role)");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':role', $role);
    
    return $stmt->execute();
}

// Logout user
function logoutUser() {
    session_unset();
    session_destroy();
}

// Check if user has admin role
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

// Check if user has access to a vehicle
function hasVehicleAccess($conn, $userId, $vehicleId) {
    if (isAdmin()) {
        return true; // Admin has access to all vehicles
    }
    
    $stmt = $conn->prepare("
        SELECT 1 FROM vehicle_assignments 
        WHERE worker_id = :worker_id AND vehicle_id = :vehicle_id
    ");
    $stmt->bindParam(':worker_id', $userId);
    $stmt->bindParam(':vehicle_id', $vehicleId);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}
?>
