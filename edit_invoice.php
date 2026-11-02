<?php

$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$invoice_id = $_GET['id'];


$invoice_sql = "SELECT * FROM invoices WHERE invoice_id = ?";
$stmt = $conn->prepare($invoice_sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice_result = $stmt->get_result();
$invoice = $invoice_result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $customer_name = $_POST['customer_name'];
    $customer_address = $_POST['customer_address'];
    $invoice_date = $_POST['invoice_date'];
    $subtotal = $_POST['subtotal'];
    $discount_percentage = $_POST['discount_percentage'];
    $total_amount = $_POST['total_amount'];
    

  
    $update_sql = "UPDATE invoices SET customer_name=?, customer_address=?, invoice_date=?, subtotal=?, gst=0, discount_percentage=?, total_amount=? WHERE invoice_id=?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssdddi", $customer_name, $customer_address, $invoice_date, $subtotal, $discount_percentage, $total_amount, $invoice_id);
    
    if ($update_stmt->execute()) {
        header("Location: summary.php"); 
        exit();
    } else {
        echo "Error updating invoice: " . $conn->error;
    }
}



$stmt = $conn->prepare("SELECT profile_image FROM login WHERE id = ?");


$userId = 1;
$stmt->bind_param('i', $userId);


$stmt->execute();


$stmt->bind_result($profileImage);


$stmt->fetch();


$stmt->close();

$profileImage = !empty($profileImage) ? $profileImage : 'default.jpg';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="Assets/images/logo1.png" type="image/jpg">
    <title>Edit Invoice #<?php echo $invoice_id; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body.edit-invoice-body {
            background: #f4f6f9;
            color: #202638;
            font-family: Arial, sans-serif;
        }

        body.edit-invoice-body header {
            min-height: 72px;
            padding: 12px 24px;
            background: #fff;
        }

        .edit-invoice-page {
            width: calc(100% - 40px);
            max-width: 760px;
            margin: 28px auto 40px;
        }

        .edit-invoice-card {
            padding: 28px;
            border: 1px solid #e1e5eb;
            border-radius: 6px;
            background: #fff;
        }

        .edit-invoice-card h1 {
            margin: 0 0 24px;
            color: #202638;
            font-size: 26px;
            font-weight: 700;
        }

        .edit-invoice-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 20px;
        }

        .edit-invoice-form .form-group {
            min-width: 0;
            margin-bottom: 16px;
        }

        .edit-invoice-form .address-group,
        .edit-invoice-actions {
            grid-column: 1 / -1;
        }

        .edit-invoice-form label {
            margin-bottom: 6px;
            color: #344054;
            font-size: 14px;
            font-weight: 600;
        }

        .edit-invoice-form .form-control {
            min-height: 42px;
        }

        .edit-invoice-form textarea.form-control {
            min-height: 76px;
        }

        .edit-invoice-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 4px;
        }

        .edit-invoice-actions .btn {
            min-height: 44px;
            min-width: 132px;
            padding: 10px 16px;
        }

        @media (max-width: 991.98px) {
            body.edit-invoice-body header {
                min-height: 72px;
                padding: 10px 16px 10px 64px;
                flex-direction: row;
                align-items: center;
            }

            body.edit-invoice-body header h1 {
                margin: 0;
                font-size: 21px;
            }

            body.edit-invoice-body .profile-icons {
                width: auto;
            }

            body.edit-invoice-body .profile-img {
                width: 42px;
                height: 42px;
            }
        }

        @media (max-width: 575.98px) {
            .edit-invoice-page {
                width: calc(100% - 24px);
                margin: 18px auto 28px;
            }

            .edit-invoice-card {
                padding: 18px 16px;
            }

            .edit-invoice-card h1 {
                margin-bottom: 18px;
                font-size: 23px;
            }

            .edit-invoice-form {
                grid-template-columns: minmax(0, 1fr);
                gap: 0;
            }

            .edit-invoice-form .form-group {
                margin-bottom: 14px;
            }

            .edit-invoice-form .form-control {
                min-height: 44px;
                font-size: 16px;
            }

            .edit-invoice-form textarea.form-control {
                min-height: 76px;
            }

            .edit-invoice-actions {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 8px;
            }

            .edit-invoice-actions .btn {
                min-width: 0;
                width: 100%;
                padding-right: 8px;
                padding-left: 8px;
            }
        }
    </style>
</head>
<body class="edit-invoice-body">
<?php include 'sidenav.php'; ?>
<?php include 'header.php'; ?>
    <main class="edit-invoice-page">
        <section class="edit-invoice-card">
        <h1>Edit Invoice #<?php echo (int) $invoice_id; ?></h1>
        <form action="" method="POST" class="edit-invoice-form">
            <div class="form-group">
                <label for="customer_name">Customer Name</label>
                <input type="text" id="customer_name" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($invoice['customer_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group address-group">
                <label for="customer_address">Customer Address</label>
                <textarea id="customer_address" name="customer_address" class="form-control" required><?php echo htmlspecialchars($invoice['customer_address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label for="invoice_date">Invoice Date</label>
                <input type="date" id="invoice_date" name="invoice_date" class="form-control" value="<?php echo htmlspecialchars($invoice['invoice_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
                <label for="subtotal">Subtotal</label>
                <input type="number" id="subtotal" name="subtotal" class="form-control" value="<?php echo htmlspecialchars($invoice['subtotal'], ENT_QUOTES, 'UTF-8'); ?>" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="discount_percentage">Discount Percentage</label>
                <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" value="<?php echo htmlspecialchars($invoice['discount_percentage']); ?>" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="total_amount">Total Amount</label>
                <input type="number" id="total_amount" name="total_amount" class="form-control" value="<?php echo htmlspecialchars($invoice['total_amount'], ENT_QUOTES, 'UTF-8'); ?>" step="0.01" required>
            </div>
            <div class="edit-invoice-actions">
                <button type="submit" class="btn btn-primary">Update Invoice</button>
                <a href="summary.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        </section>
    </main>
</body>
</html>

<?php
$conn->close();
?>
