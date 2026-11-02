<?php
$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';

$suggestions = [];

if (!empty($searchTerm)) {
  
    $stmt = $conn->prepare("
        SELECT DISTINCT customer_name FROM invoices WHERE customer_name LIKE ?
        UNION
        SELECT DISTINCT product_name FROM invoice_items WHERE product_name LIKE ?
    ");
    
    $likeTerm = '%' . $searchTerm . '%';
    $stmt->bind_param('ss', $likeTerm, $likeTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['customer_name']; 
    }
    
    $stmt->close();
}

$conn->close();

echo json_encode($suggestions);
