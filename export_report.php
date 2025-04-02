
<?php
require_once 'config.php';

// Check if user is logged in
checkAccess();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');

// Get filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;

// Create output stream
$output = fopen('php://output', 'w');

// Build query conditions based on user role
$user_condition = "";
if (!isAdmin()) {
    $worker_id = $_SESSION['user_id'];
    $user_condition = " AND s.created_by = $worker_id";
}

// Store filter condition
$store_condition = "";
if ($store_id) {
    $store_condition = " AND s.store_id = $store_id";
}

// Date range condition
$date_condition = " AND s.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";

// Write report header
fputcsv($output, [
    'Potato Credit Tracker - Report',
    'From: ' . date('M d, Y', strtotime($start_date)),
    'To: ' . date('M d, Y', strtotime($end_date))
]);
fputcsv($output, []); // Empty row

// 1. Export Vehicle Statistics
fputcsv($output, ['Vehicle Statistics']);
fputcsv($output, ['Vehicle', 'Total Bags', 'Cash Sales', 'Credit Sales', 'Debtors', 'Outstanding', 'Payments']);

$vehicle_stats_query = "SELECT 
    st.id as vehicle_id,
    st.name as vehicle_name,
    SUM(CASE WHEN s.payment_type = 'cash' THEN s.total_amount ELSE 0 END) as total_cash_sales,
    SUM(CASE WHEN s.payment_type = 'credit' THEN s.total_amount ELSE 0 END) as total_credit_sales,
    SUM(s.bags_quantity) as total_bags,
    COUNT(DISTINCT CASE WHEN s.payment_type = 'credit' THEN s.customer_id END) as debtors_count,
    (SELECT SUM(amount_due - amount_paid) 
     FROM debts d 
     JOIN sales s2 ON d.sale_id = s2.id 
     WHERE s2.store_id = st.id AND d.status != 'paid') as outstanding_debt,
    (SELECT SUM(p.amount) 
     FROM payments p 
     JOIN debts d ON p.debt_id = d.id 
     JOIN sales s3 ON d.sale_id = s3.id 
     WHERE s3.store_id = st.id 
     AND p.collection_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59') as total_payments
FROM stores st
LEFT JOIN sales s ON st.id = s.store_id
WHERE 1=1 $user_condition $store_condition
AND (s.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' OR s.created_at IS NULL)
GROUP BY st.id, st.name";

$vehicle_stats_result = $conn->query($vehicle_stats_query);

if ($vehicle_stats_result) {
    while ($row = $vehicle_stats_result->fetch_assoc()) {
        fputcsv($output, [
            $row['vehicle_name'],
            $row['total_bags'] ?? 0,
            $row['total_cash_sales'] ?? 0,
            $row['total_credit_sales'] ?? 0,
            $row['debtors_count'] ?? 0,
            $row['outstanding_debt'] ?? 0,
            $row['total_payments'] ?? 0
        ]);
    }
}

fputcsv($output, []); // Empty row

// 2. Export Sales Data
fputcsv($output, ['Sales Transactions']);
fputcsv($output, ['Date', 'Customer', 'Vehicle', 'Bags', 'Price Per Bag', 'Amount', 'Type']);

$sales_query = "SELECT 
    s.id,
    s.created_at, 
    s.bags_quantity, 
    s.price_per_bag, 
    s.total_amount, 
    s.payment_type,
    c.full_name as customer_name,
    st.name as store_name,
    st.type as store_type
FROM sales s
LEFT JOIN customers c ON s.customer_id = c.id
JOIN stores st ON s.store_id = st.id
WHERE 1=1 $user_condition $store_condition $date_condition
ORDER BY s.created_at DESC";

$sales_result = $conn->query($sales_query);

if ($sales_result) {
    while ($row = $sales_result->fetch_assoc()) {
        $customer = $row['customer_name'] ? $row['customer_name'] : 'Cash Customer';
        fputcsv($output, [
            date('Y-m-d', strtotime($row['created_at'])),
            $customer,
            $row['store_name'],
            $row['bags_quantity'],
            $row['price_per_bag'],
            $row['total_amount'],
            ucfirst($row['payment_type'])
        ]);
    }
}

fputcsv($output, []); // Empty row

// 3. Export Outstanding Debts
fputcsv($output, ['Outstanding Debts']);
fputcsv($output, ['Customer', 'Vehicle', 'Sale Date', 'Bags', 'Total Amount', 'Amount Paid', 'Outstanding', 'Status']);

$debt_query = "SELECT 
    d.id,
    d.amount_due,
    d.amount_paid,
    d.status,
    d.last_updated,
    s.created_at as sale_date,
    s.bags_quantity,
    s.price_per_bag,
    s.total_amount,
    c.full_name as customer_name,
    st.id as vehicle_id,
    st.name as vehicle_name
FROM debts d
JOIN sales s ON d.sale_id = s.id
JOIN customers c ON s.customer_id = c.id
JOIN stores st ON s.store_id = st.id
WHERE d.status != 'paid' $user_condition $store_condition
ORDER BY d.last_updated DESC";

$debt_result = $conn->query($debt_query);

if ($debt_result) {
    while ($row = $debt_result->fetch_assoc()) {
        $outstanding = $row['amount_due'] - $row['amount_paid'];
        $status = ($row['status'] === 'pending') ? 'Pending' : 'Partial';
        
        fputcsv($output, [
            $row['customer_name'],
            $row['vehicle_name'],
            date('Y-m-d', strtotime($row['sale_date'])),
            $row['bags_quantity'],
            $row['amount_due'],
            $row['amount_paid'],
            $outstanding,
            $status
        ]);
    }
}

fputcsv($output, []); // Empty row

// 4. Export Payments
fputcsv($output, ['Payments Collected']);
fputcsv($output, ['Date', 'Customer', 'Vehicle', 'Amount', 'Collected By', 'Forwarded to Admin', 'Notes']);

$payment_query = "SELECT 
    p.id,
    p.amount,
    p.collection_date,
    p.forwarded_to_admin,
    p.notes,
    c.full_name as customer_name,
    u.full_name as collected_by_name,
    st.id as vehicle_id,
    st.name as vehicle_name
FROM payments p
JOIN debts d ON p.debt_id = d.id
JOIN sales s ON d.sale_id = s.id
JOIN customers c ON s.customer_id = c.id
JOIN users u ON p.collected_by = u.id
JOIN stores st ON s.store_id = st.id
WHERE p.collection_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    $user_condition $store_condition
ORDER BY p.collection_date DESC";

$payment_result = $conn->query($payment_query);

if ($payment_result) {
    while ($row = $payment_result->fetch_assoc()) {
        fputcsv($output, [
            date('Y-m-d', strtotime($row['collection_date'])),
            $row['customer_name'],
            $row['vehicle_name'],
            $row['amount'],
            $row['collected_by_name'],
            $row['forwarded_to_admin'],
            $row['notes'] ?? ''
        ]);
    }
}

// Close output stream
fclose($output);
exit;
