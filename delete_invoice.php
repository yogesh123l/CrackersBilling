<?php

$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = $_POST['invoice_id'];

   
    $delete_sql = "DELETE FROM invoices WHERE invoice_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $invoice_id);

    if ($delete_stmt->execute()) {
        header("Location: summary.php");
        exit();
    } else {
        echo "Error deleting invoice: " . $conn->error;
    }
}

$conn->close();
?>
