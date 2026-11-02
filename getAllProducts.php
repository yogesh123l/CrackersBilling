<?php
$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['error' => 'Could not connect to the product database.']);
    exit;
}

$conn->set_charset('utf8mb4');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$sql = "SELECT * FROM products ORDER BY id DESC";
$result = $conn->query($sql);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load the product list.']);
    $conn->close();
    exit;
}

$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
$conn->close();
echo json_encode($products);
?>
