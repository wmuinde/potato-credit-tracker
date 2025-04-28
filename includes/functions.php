<?php
// Format currency
function formatCurrency($amount) {
    return CURRENCY . ' ' . number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return date(DATE_FORMAT, strtotime($date));
}

// Get status color for badges
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'paid':
            return 'success';
        case 'partial':
            return 'warning';
        case 'unpaid':
            return 'danger';
        case 'processing':
            return 'info';
        default:
            return 'secondary';
    }
}

// Get all vehicles (for admin)
function getAllVehicles($conn) {
    $stmt = $conn->prepare("
        SELECT v.*, 
            (SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id AND payment_type = 'cash') as cash_sales,
            (SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id AND payment_type = 'credit') as credit_sales,
            (SELECT SUM(amount) FROM expenses WHERE vehicle_id = v.id) as expenses,
            (SELECT SUM(bags) FROM lost_damaged WHERE vehicle_id = v.id) as lost_damaged,
            ((SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id) - 
             (SELECT SUM(amount) FROM expenses WHERE vehicle_id = v.id) - 
             ((SELECT SUM(bags) FROM lost_damaged WHERE vehicle_id = v.id) * v.bag_value)) as profit
        FROM vehicles v
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get vehicles assigned to a worker
function getWorkerVehicles($conn, $workerId) {
    $stmt = $conn->prepare("
        SELECT v.*, 
            (SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id AND payment_type = 'cash') as cash_sales,
            (SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id AND payment_type = 'credit') as credit_sales,
            (SELECT SUM(amount) FROM expenses WHERE vehicle_id = v.id) as expenses,
            (SELECT SUM(bags) FROM lost_damaged WHERE vehicle_id = v.id) as lost_damaged,
            ((SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id) - 
             (SELECT SUM(amount) FROM expenses WHERE vehicle_id = v.id) - 
             ((SELECT SUM(bags) FROM lost_damaged WHERE vehicle_id = v.id) * v.bag_value)) as profit
        FROM vehicles v
        JOIN vehicle_assignments va ON v.id = va.vehicle_id
        WHERE va.worker_id = :worker_id
    ");
    $stmt->bindParam(':worker_id', $workerId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get total sales
function getTotalSales($conn) {
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM sales");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

// Get total outstanding debts
function getTotalDebts($conn) {
    $stmt = $conn->prepare("
        SELECT SUM(s.amount - COALESCE(p.amount_paid, 0)) as total 
        FROM sales s
        LEFT JOIN (
            SELECT debt_id, SUM(amount) as amount_paid 
            FROM payments 
            GROUP BY debt_id
        ) p ON s.id = p.debt_id
        WHERE s.payment_type = 'credit'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

// Get total held funds (excluding admin collections)
function getTotalHeldFunds($conn) {
    $stmt = $conn->prepare("
        SELECT SUM(p.amount - COALESCE(f.amount, 0)) as total
        FROM payments p
        JOIN users u ON p.worker_id = u.id
        LEFT JOIN forwarded_funds f ON p.id = f.payment_id
        WHERE u.role = 'worker' -- Only count worker collections
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

// Get recent transactions
function getRecentTransactions($conn, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT 
            s.date, 
            v.license_plate as vehicle, 
            s.payment_type as type, 
            c.name as customer, 
            s.amount,
            CASE 
                WHEN s.payment_type = 'cash' THEN 'Paid'
                WHEN p.amount_paid >= s.amount THEN 'Paid'
                WHEN p.amount_paid > 0 THEN 'Partial'
                ELSE 'Unpaid'
            END as status
        FROM sales s
        JOIN vehicles v ON s.vehicle_id = v.id
        JOIN customers c ON s.customer_id = c.id
        LEFT JOIN (
            SELECT debt_id, SUM(amount) as amount_paid 
            FROM payments 
            GROUP BY debt_id
        ) p ON s.id = p.debt_id
        ORDER BY s.date DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get vehicle details by ID
function getVehicleById($conn, $id) {
    $stmt = $conn->prepare("
        SELECT v.*, 
            (SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id AND payment_type = 'cash') as cash_sales,
            (SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id AND payment_type = 'credit') as credit_sales,
            (SELECT SUM(amount) FROM expenses WHERE vehicle_id = v.id) as expenses,
            (SELECT SUM(bags) FROM lost_damaged WHERE vehicle_id = v.id) as lost_damaged,
            ((SELECT SUM(amount) FROM sales WHERE vehicle_id = v.id) - 
             (SELECT SUM(amount) FROM expenses WHERE vehicle_id = v.id) - 
             ((SELECT SUM(bags) FROM lost_damaged WHERE vehicle_id = v.id) * v.bag_value)) as profit
        FROM vehicles v
        WHERE v.id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get vehicle sales
function getVehicleSales($conn, $vehicleId) {
    $stmt = $conn->prepare("
        SELECT s.*, c.name as customer_name, 
            COALESCE(p.amount_paid, 0) as amount_paid,
            (s.amount - COALESCE(p.amount_paid, 0)) as balance
        FROM sales s
        JOIN customers c ON s.customer_id = c.id
        LEFT JOIN (
            SELECT debt_id, SUM(amount) as amount_paid 
            FROM payments 
            GROUP BY debt_id
        ) p ON s.id = p.debt_id
        WHERE s.vehicle_id = :vehicle_id
        ORDER BY s.date DESC
    ");
    $stmt->bindParam(':vehicle_id', $vehicleId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get vehicle expenses
function getVehicleExpenses($conn, $vehicleId) {
    $stmt = $conn->prepare("
        SELECT e.*, et.name as expense_type
        FROM expenses e
        JOIN expense_types et ON e.expense_type_id = et.id
        WHERE e.vehicle_id = :vehicle_id
        ORDER BY e.date DESC
    ");
    $stmt->bindParam(':vehicle_id', $vehicleId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all debtors
function getAllDebtors($conn, $vehicleId = null) {
    $sql = "
        SELECT 
            c.id, c.name, c.phone, 
            s.id as sale_id, s.date as sale_date, s.amount as original_amount,
            v.license_plate,
            COALESCE(p.amount_paid, 0) as amount_paid,
            (s.amount - COALESCE(p.amount_paid, 0)) as balance,
            w.name as worker_name
        FROM sales s
        JOIN customers c ON s.customer_id = c.id
        JOIN vehicles v ON s.vehicle_id = v.id
        JOIN users w ON s.worker_id = w.id
        LEFT JOIN (
            SELECT debt_id, SUM(amount) as amount_paid 
            FROM payments 
            GROUP BY debt_id
        ) p ON s.id = p.debt_id
        WHERE s.payment_type = 'credit' AND (s.amount - COALESCE(p.amount_paid, 0)) > 0
    ";
    
    if ($vehicleId) {
        $sql .= " AND s.vehicle_id = :vehicle_id";
    }
    
    $sql .= " ORDER BY s.date DESC";
    
    $stmt = $conn->prepare($sql);
    
    if ($vehicleId) {
        $stmt->bindParam(':vehicle_id', $vehicleId);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get payment history for a debt
function getPaymentHistory($conn, $debtId) {
    $stmt = $conn->prepare("
        SELECT p.*, u.name as collected_by
        FROM payments p
        JOIN users u ON p.worker_id = u.id
        WHERE p.debt_id = :debt_id
        ORDER BY p.date DESC
    ");
    $stmt->bindParam(':debt_id', $debtId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add new payment for a debt
function addPayment($conn, $debtId, $workerId, $amount, $date) {
    $stmt = $conn->prepare("
        INSERT INTO payments (debt_id, worker_id, amount, date)
        VALUES (:debt_id, :worker_id, :amount, :date)
    ");
    $stmt->bindParam(':debt_id', $debtId);
    $stmt->bindParam(':worker_id', $workerId);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':date', $date);
    return $stmt->execute();
}

// Forward funds from worker to admin
function forwardFunds($conn, $paymentId, $amount, $date) {
   $stmt = $conn->prepare("
       INSERT INTO forwarded_funds (payment_id, amount, date)
       VALUES (:payment_id, :amount, :date)
   ");
   $stmt->bindParam(':payment_id', $paymentId);
   $stmt->bindParam(':amount', $amount);
   $stmt->bindParam(':date', $date);
   return $stmt->execute();
}

// Get worker held funds
function getWorkerHeldFunds($conn, $workerId) {
   $stmt = $conn->prepare("
       SELECT 
           p.id as payment_id,
           c.name as customer,
           v.license_plate,
           p.amount as payment_amount,
           COALESCE(f.forwarded_amount, 0) as forwarded_amount,
           (p.amount - COALESCE(f.forwarded_amount, 0)) as held_amount,
           p.date as payment_date
       FROM payments p
       JOIN sales s ON p.debt_id = s.id
       JOIN customers c ON s.customer_id = c.id
       JOIN vehicles v ON s.vehicle_id = v.id
       LEFT JOIN (
           SELECT payment_id, SUM(amount) as forwarded_amount
           FROM forwarded_funds
           GROUP BY payment_id
       ) f ON p.id = f.payment_id
       WHERE p.worker_id = :worker_id AND (p.amount - COALESCE(f.forwarded_amount, 0)) > 0
       ORDER BY p.date DESC
   ");
   $stmt->bindParam(':worker_id', $workerId);
   $stmt->execute();
   return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Import customers from CSV
function importCustomersFromCSV($conn, $file, $vehiclePlate, $workerId) {
    $result = [
        'success' => 0,
        'errors' => []
    ];
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        // Skip header row
        fgetcsv($handle, 1000, ",");
        
        $rowNumber = 2; // Start from row 2 (after header)
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Validate data
            if (count($data) < 5) {
                $result['errors'][] = "Row $rowNumber: Not enough columns";
                $rowNumber++;
                continue;
            }
            
            $customerName = trim($data[0]);
            $phoneNumber = trim($data[1]);
            $debtAmount = floatval(trim($data[2]));
            $vehiclePlateFromFile = trim($data[3]);
            $saleDate = trim($data[4]);
            $workerIdFromFile = isset($data[5]) ? trim($data[5]) : $workerId;
            
            // Validate required fields
            if (empty($customerName) || $debtAmount <= 0 || empty($vehiclePlateFromFile) || empty($saleDate)) {
                $result['errors'][] = "Row $rowNumber: Missing required fields";
                $rowNumber++;
                continue;
            }
            
            // Validate vehicle plate
            $vehicleId = getVehicleIdByPlate($conn, $vehiclePlateFromFile);
            if (!$vehicleId) {
                $result['errors'][] = "Row $rowNumber: Vehicle plate '$vehiclePlateFromFile' not found";
                $rowNumber++;
                continue;
            }
            
            // Validate worker ID
            if (!isValidWorkerId($conn, $workerIdFromFile)) {
                $result['errors'][] = "Row $rowNumber: Worker ID '$workerIdFromFile' not found";
                $rowNumber++;
                continue;
            }
            
            // Format date
            $formattedDate = date('Y-m-d', strtotime($saleDate));
            if ($formattedDate === false) {
                $result['errors'][] = "Row $rowNumber: Invalid date format";
                $rowNumber++;
                continue;
            }
            
            // Check if customer exists, if not create
            $customerId = getCustomerIdByName($conn, $customerName);
            if (!$customerId) {
                $customerId = addCustomer($conn, $customerName, $phoneNumber);
            }
            
            // Add sale record
            $bagsSold = 0;
            $pricePerBag = 0;

            // Get vehicle bag value for price per bag
            $stmt = $conn->prepare("SELECT bag_value FROM vehicles WHERE id = :vehicle_id");
            $stmt->bindParam(':vehicle_id', $vehicleId);
            $stmt->execute();
            $vehicleData = $stmt->fetch(PDO::FETCH_ASSOC);
            $pricePerBag = $vehicleData['bag_value'] ?? 0;

            // Calculate bags sold if price per bag is not zero
            if ($pricePerBag > 0) {
                $bagsSold = round($debtAmount / $pricePerBag);
            }

            $saleId = addSale($conn, $vehicleId, $customerId, $workerIdFromFile, $debtAmount, 'credit', $formattedDate, $bagsSold, $pricePerBag);
            
            if ($saleId) {
                $result['success']++;
            } else {
                $result['errors'][] = "Row $rowNumber: Failed to add sale record";
            }
            
            $rowNumber++;
        }
        fclose($handle);
    }
    
    return $result;
}

// Get vehicle ID by license plate
function getVehicleIdByPlate($conn, $plate) {
    $stmt = $conn->prepare("SELECT id FROM vehicles WHERE license_plate = :plate");
    $stmt->bindParam(':plate', $plate);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : false;
}

// Check if worker ID is valid
function isValidWorkerId($conn, $workerId) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = :id AND role = 'worker'");
    $stmt->bindParam(':id', $workerId);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

// Get customer ID by name
function getCustomerIdByName($conn, $name) {
    $stmt = $conn->prepare("SELECT id FROM customers WHERE name = :name");
    $stmt->bindParam(':name', $name);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : false;
}

// Add new customer
function addCustomer($conn, $name, $phone = '') {
    $stmt = $conn->prepare("INSERT INTO customers (name, phone) VALUES (:name, :phone)");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':phone', $phone);
    $stmt->execute();
    return $conn->lastInsertId();
}

// Add new sale
function addSale($conn, $vehicleId, $customerId, $workerId, $amount, $paymentType, $date, $bagsSold = 0, $pricePerBag = 0) {
   $stmt = $conn->prepare("
       INSERT INTO sales (vehicle_id, customer_id, worker_id, amount, payment_type, date, bags_sold, price_per_bag)
       VALUES (:vehicle_id, :customer_id, :worker_id, :amount, :payment_type, :date, :bags_sold, :price_per_bag)
   ");
   $stmt->bindParam(':vehicle_id', $vehicleId);
   $stmt->bindParam(':customer_id', $customerId);
   $stmt->bindParam(':worker_id', $workerId);
   $stmt->bindParam(':amount', $amount);
   $stmt->bindParam(':payment_type', $paymentType);
   $stmt->bindParam(':date', $date);
   $stmt->bindParam(':bags_sold', $bagsSold);
   $stmt->bindParam(':price_per_bag', $pricePerBag);
   $stmt->execute();
   return $conn->lastInsertId();
}

// Generate PDF for debtors list
function generateDebtorsPDF($conn, $vehicleId = null) {
  // Check if mPDF is available
  if (!file_exists('vendor/autoload.php')) {
      // Return an error message
      return "PDF generation failed: mPDF is not installed. Please run 'install-dependencies.php' first.";
  }
  
  require_once 'vendor/autoload.php';
   
   // Get debtors data
   $debtors = getAllDebtors($conn, $vehicleId);
   
   // Create PDF instance
   $mpdf = new \Mpdf\Mpdf([
       'margin_left' => 15,
       'margin_right' => 15,
       'margin_top' => 15,
       'margin_bottom' => 15,
   ]);
   
   // Add watermark with user ID
   $userId = $_SESSION['user_id'];
   $mpdf->SetWatermarkText("Generated by User #$userId");
   $mpdf->showWatermarkText = true;
   
   // Start building HTML content
   $html = '
   <style>
       body { font-family: Arial, sans-serif; }
       .header { text-align: center; margin-bottom: 20px; }
       .logo { max-width: 150px; }
       .company-info { margin-bottom: 10px; }
       table { width: 100%; border-collapse: collapse; }
       th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
       th { background-color: #f2f2f2; }
       .total-row { font-weight: bold; background-color: #f9f9f9; }
       .footer { margin-top: 30px; font-size: 12px; text-align: center; }
       .disclaimer { margin-top: 20px; font-size: 10px; }
   </style>
   
   <div class="header">
       <div class="company-info">
           <h2>' . COMPANY_NAME . '</h2>
           <p>' . COMPANY_ADDRESS . '<br>' . COMPANY_PHONE . '<br>' . COMPANY_EMAIL . '</p>
       </div>
       <h1>Debtors Report</h1>
       <p>Generated on: ' . date(DATE_FORMAT) . '</p>
   </div>
   ';
   
   if ($vehicleId) {
       $vehicle = getVehicleById($conn, $vehicleId);
       $html .= '<h3>Vehicle: ' . $vehicle['license_plate'] . '</h3>';
   }
   
   $html .= '
   <table>
       <thead>
           <tr>
               <th>Customer</th>
               <th>Phone</th>
               <th>Vehicle</th>
               <th>Sale Date</th>
               <th>Original Amount</th>
               <th>Paid Amount</th>
               <th>Balance</th>
               <th>Worker</th>
           </tr>
       </thead>
       <tbody>
   ';
   
   $totalOriginal = 0;
   $totalPaid = 0;
   $totalBalance = 0;
   
   foreach ($debtors as $debtor) {
       $html .= '
       <tr>
           <td>' . $debtor['name'] . '</td>
           <td>' . $debtor['phone'] . '</td>
           <td>' . $debtor['license_plate'] . '</td>
           <td>' . formatDate($debtor['sale_date']) . '</td>
           <td>' . formatCurrency($debtor['original_amount']) . '</td>
           <td>' . formatCurrency($debtor['amount_paid']) . '</td>
           <td>' . formatCurrency($debtor['balance']) . '</td>
           <td>' . $debtor['worker_name'] . '</td>
       </tr>
       ';
       
       $totalOriginal += $debtor['original_amount'];
       $totalPaid += $debtor['amount_paid'];
       $totalBalance += $debtor['balance'];
   }
   
   $html .= '
       <tr class="total-row">
           <td colspan="4">TOTALS</td>
           <td>' . formatCurrency($totalOriginal) . '</td>
           <td>' . formatCurrency($totalPaid) . '</td>
           <td>' . formatCurrency($totalBalance) . '</td>
           <td></td>
       </tr>
       </tbody>
   </table>
   
   <div class="disclaimer">
       <p><strong>Legal Disclaimer:</strong> This document serves as an official record of outstanding debts owed to ' . COMPANY_NAME . '. 
       All debtors are legally obligated to settle their accounts according to the agreed terms. Failure to pay may result in legal action.</p>
   </div>
   
   <div class="footer">
       <p>© ' . date('Y') . ' ' . COMPANY_NAME . ' - All Rights Reserved</p>
   </div>
   ';
   
   // Output PDF
   $mpdf->WriteHTML($html);
   
   // Return the PDF as a string
   return $mpdf->Output('', 'S');
}
?>
