<?php
$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Invoice_Report_' . date('Ymd') . '.xls"');

echo "Invoice ID\tCustomer Name\tCustomer Address\tInvoice Date\tProducts\tSubtotal\tDiscount Percentage\tTotal Amount\n";

$query = "
    SELECT 
        i.invoice_id, 
        i.customer_name, 
        i.customer_address,
        i.invoice_date, 
        GROUP_CONCAT(CONCAT_WS(' ', it.product_name, '(Size:', it.product_size, ', Qty:', it.quantity, ')') SEPARATOR ', ') AS product_details,
        i.subtotal, 
        i.discount_percentage, 
        i.total_amount
    FROM invoices i
    LEFT JOIN invoice_items it ON i.invoice_id = it.invoice_id
";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " WHERE i.customer_name LIKE ? OR it.product_name LIKE ?";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam; 
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($from_date) && !empty($to_date)) {
    $query .= (empty($search) ? " WHERE " : " AND ") . "i.invoice_date BETWEEN ? AND ?";
    $params[] = $from_date; 
    $params[] = $to_date;   
    $types .= 'ss'; 
}

$query .= " GROUP BY i.invoice_id ORDER BY i.invoice_id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $bindParams = [];
    foreach ($params as $key => $param) {
        $bindParams[$key] = &$params[$key];
    }
    array_unshift($bindParams, $types);
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo implode("\t", [
        $row['invoice_id'],
        $row['customer_name'],
        $row['customer_address'],
        date("d/m/Y", strtotime($row['invoice_date'])),
        $row['product_details'],
        'Rs.' . number_format($row['subtotal'], 2),
        number_format($row['discount_percentage'], 2) . '%',
        'Rs.' . number_format($row['total_amount'], 2)
    ]) . "\n";
}

$stmt->close();
$conn->close();
exit;
?>
