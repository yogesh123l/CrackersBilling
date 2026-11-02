<?php
$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";


$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$alertMessage = '';
$alertType = '';


$productQuery = "SELECT id, name, image, size, amount FROM products";
$productResult = $conn->query($productQuery);
$products = [];

if ($productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = $conn->real_escape_string(trim($_POST['customer_name']));
    $customer_address = $conn->real_escape_string(trim($_POST['customer_address']));
    $invoice_date = date('Y-m-d');
    $discount_percentage = max(0, min(100, floatval($_POST['discount_percentage'] ?? 0)));
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $discount = $subtotal * ($discount_percentage / 100);
    $subtotalAfterDiscount = $subtotal - $discount;
    $gst = 0;
    $total_amount = round($subtotalAfterDiscount, 2);

    try {
        if (empty($_POST['products']) || !is_array($_POST['products'])) {
            throw new RuntimeException('Add at least one product before generating the invoice.');
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO invoices (customer_name, customer_address, invoice_date, subtotal, gst, total_amount, discount_percentage) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdddd", $customer_name, $customer_address, $invoice_date, $subtotal, $gst, $total_amount, $discount_percentage);
        $stmt->execute();
        $invoice_id = $stmt->insert_id;

        $stmt_stock = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
        $stmt_item = $conn->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, product_size, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($_POST['products'] as $product) {
            $product_id = intval($product['name'] ?? 0);
            $quantity = intval($product['quantity'] ?? 0);
            $product_name = '';

            foreach ($products as $prod) {
                if ((int) $prod['id'] === $product_id) {
                    $product_name = $prod['name'];
                    break;
                }
            }

            if ($product_id < 1 || $quantity < 1 || $product_name === '') {
                throw new RuntimeException('A product or quantity is invalid. The invoice was not saved.');
            }

            $stmt_stock->bind_param("iii", $quantity, $product_id, $quantity);
            $stmt_stock->execute();
            if ($stmt_stock->affected_rows !== 1) {
                throw new RuntimeException('Not enough stock for ' . $product_name . '. The invoice was not saved.');
            }

            $product_size = $conn->real_escape_string(trim($product['size'] ?? ''));
            $price = floatval($product['price'] ?? 0);
            $total = floatval($product['total'] ?? 0);

            $stmt_item->bind_param("iissidd", $invoice_id, $product_id, $product_name, $product_size, $quantity, $price, $total);
            $stmt_item->execute();
        }

        $conn->commit();
        $stmt_stock->close();
        $stmt_item->close();
        $stmt->close();

        $alertMessage = 'Invoice generated successfully and product stock has been updated.';
        $alertType = 'success';
    } catch (Throwable $error) {
        $conn->rollback();
        $alertMessage = $error->getMessage();
        $alertType = 'error';
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
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="Assets/images/logo1.png" type="image/jpg">
    <title>Crackers Billing</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="./Assets/css/min.css">

    <style>
        .billing-body {
            background: #f4f6f9;
            color: #202638;
            font-family: Arial, sans-serif;
        }

        .billing-body header {
            min-height: 78px;
            padding: 14px 28px;
            background: #fff;
        }

        .billing-body header h1 {
            margin: 0;
            color: #202638;
            font-size: 24px;
        }

        .invoice-page {
            width: calc(100% - 48px);
            max-width: 1120px;
            margin: 28px auto 40px;
        }

        .invoice-sheet {
            padding: 28px 32px;
            border: 1px solid #e1e5eb;
            border-radius: 6px;
            background: #fff;
        }

        .invoice-heading,
        .company-info,
        .invoice-line-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .invoice-heading {
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e8ebf0;
        }

        .invoice-kicker {
            margin: 0 0 4px;
            color: #167d8d;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .invoice-heading h1 {
            margin: 0;
            color: #202638;
            font-size: 27px;
            font-weight: 700;
        }

        .invoice-date {
            color: #667085;
            font-size: 14px;
            text-align: right;
        }

        .company-info {
            margin-bottom: 28px;
            padding: 18px 20px;
            border-radius: 5px;
            background: #f7f8fa;
        }

        .company-details h2 {
            margin: 0 0 8px;
            color: #212545;
            font-size: 19px;
            font-weight: 700;
        }

        .company-details p {
            margin: 4px 0;
            color: #5d6677;
            font-size: 13px;
        }

        .company-logo {
            width: 90px;
            height: 72px;
            object-fit: contain;
        }

        .invoice-section-heading {
            margin: 0 0 14px;
            color: #202638;
            font-size: 18px;
            font-weight: 700;
        }

        .customer-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 16px;
        }

        .customer-fields .form-group {
            margin-bottom: 16px;
        }

        .customer-fields label,
        .discount-control label {
            margin-bottom: 6px;
            color: #4b5565;
            font-size: 13px;
            font-weight: 600;
        }

        .customer-fields .form-control,
        .discount-control .form-control {
            min-height: 42px;
            border-color: #d8dee7;
            border-radius: 4px;
        }

        .customer-fields textarea.form-control {
            min-height: 42px;
            resize: vertical;
        }

        .customer-address-field {
            grid-column: 1 / -1;
        }

        .invoice-lines {
            margin: 8px 0 22px;
        }

        .invoice-line-heading {
            margin-bottom: 12px;
        }

        .invoice-line-heading h2 {
            margin: 0 0 4px;
            color: #202638;
            font-size: 18px;
            font-weight: 700;
        }

        .invoice-line-heading p {
            margin: 0;
            color: #667085;
            font-size: 12px;
        }

        .add-product-button {
            flex: 0 0 auto;
            padding: 9px 13px;
            border-color: #167d8d;
            background: #167d8d;
            white-space: nowrap;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(22, 125, 141, 0.18);
        }

        .add-product-button:hover {
            border-color: #116675;
            background: #116675;
        }

        .product-picker-panel {
            display: none;
            margin: 18px 0 20px;
            padding: 18px 18px 12px;
            border: 1px solid #e7edf5;
            border-radius: 18px;
            background: linear-gradient(180deg, #f7f9fc 0%, #eef4f8 100%);
            box-shadow: 0 18px 30px rgba(16, 36, 66, 0.07);
        }

        .product-picker-panel.is-open {
            display: block;
        }

        .product-picker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .product-picker-header h3 {
            margin: 0;
            color: #17212e;
            font-size: 18px;
            font-weight: 700;
        }

        .product-picker-header span {
            color: #5d6980;
            font-size: 12px;
        }

        .product-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
        }

        .product-card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 196px;
            padding: 14px 12px 12px;
            border: 1px solid #e4ebf5;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 10px 18px rgba(22, 32, 54, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .product-card.is-added {
            border: 2px solid #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12), 0 10px 18px rgba(22, 32, 54, 0.06);
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 24px rgba(22, 32, 54, 0.08);
        }

        .product-card-thumb {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 58px;
            height: 58px;
            margin: 0 auto 10px;
            border-radius: 16px;
            background: linear-gradient(135deg, #e9fbf6 0%, #e7f5ff 100%);
            color: #167d8d;
            font-size: 26px;
            font-weight: 700;
            overflow: hidden;
        }

        .product-card.is-added .product-card-thumb {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        .product-card-added {
            position: absolute;
            top: 8px;
            right: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 7px;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
        }

        .product-card-added[hidden],
        .product-added-message[hidden] {
            display: none;
        }

        .product-added-message {
            margin: 0 0 12px;
            padding: 10px 12px;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 600;
        }

        .product-card-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .product-card-title {
            margin: 0 0 6px;
            color: #1e2e3d;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
        }

        .product-card-price {
            margin: 0 0 10px;
            color: #14805b;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
        }

        .product-card-size {
            margin: 0 0 8px;
            color: #667085;
            font-size: 12px;
            text-align: center;
        }

        .product-card-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: auto;
        }

        .product-card-qty {
            min-width: 26px;
            text-align: center;
            color: #1d2b3a;
            font-size: 15px;
            font-weight: 700;
        }

        .product-card-btn {
            display: inline-flex;
            width: 28px;
            height: 28px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 8px;
            background: #edf3f8;
            color: #173553;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .product-card-btn.plus {
            background: #32b98f;
            color: #fff;
        }

        .product-card-btn.minus {
            background: #f0f3f9;
            color: #4b5565;
        }

        .product-card-add {
            margin-top: 12px;
            padding: 8px 10px;
            border: 0;
            border-radius: 10px;
            background: #167d8d;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
        }

        .product-card-add:hover {
            background: #126a78;
        }

        .invoice-empty-state {
            margin: 0;
            padding: 20px 14px;
            border: 1px dashed #c9d1dc;
            border-radius: 5px;
            color: #667085;
            font-size: 13px;
            text-align: center;
        }

        .invoice-lines:not(.is-empty) .invoice-empty-state {
            display: none;
        }

        .invoice-lines.is-empty .invoice-table-wrap {
            display: none;
        }

        .invoice-table-wrap {
            overflow-x: auto;
            border: 1px solid #e1e5eb;
            border-radius: 5px;
        }

        #productTable {
            min-width: 760px;
            margin: 0;
            background: #fff;
            font-size: 13px;
        }

        #productTable th {
            padding: 12px;
            border-top: 0;
            background: #212545;
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
        }

        #productTable td {
            padding: 9px;
            vertical-align: middle;
        }

        #productTable .form-control {
            min-width: 90px;
            min-height: 38px;
            border-color: #d8dee7;
            font-size: 13px;
        }

        #productTable .product-name {
            min-width: 180px;
        }

        .invoice-bottom {
            display: grid;
            grid-template-columns: minmax(200px, 0.8fr) minmax(300px, 1.2fr);
            align-items: start;
            gap: 28px;
            padding-top: 20px;
            border-top: 1px solid #e8ebf0;
        }

        .discount-control .form-control {
            max-width: 220px;
        }

        .discount-help {
            display: block;
            margin-top: 6px;
            color: #7b8493;
            font-size: 12px;
        }

        .invoice-totals {
            padding: 14px 16px;
            border: 1px solid #e1e5eb;
            border-radius: 5px;
            background: #f8f9fb;
        }

        .invoice-total-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(100px, 150px);
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .invoice-total-row:last-child {
            margin-bottom: 0;
        }

        .invoice-total-row label {
            margin: 0;
            color: #4b5565;
            font-size: 13px;
        }

        .invoice-total-row .form-control {
            height: 38px;
            border-color: #e1e5eb;
            background: #fff;
            color: #202638;
            text-align: right;
        }

        .invoice-total-row.grand-total {
            margin: 12px -16px -14px;
            padding: 13px 16px;
            border-radius: 0 0 5px 5px;
            background: #212545;
        }

        .invoice-total-row.grand-total label,
        .invoice-total-row.grand-total .form-control {
            color: #fff;
            font-size: 15px;
            font-weight: 700;
        }

        .invoice-total-row.grand-total .form-control {
            border-color: transparent;
            background: transparent;
        }

        .invoice-submit-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .generate-invoice-button {
            min-height: 44px;
            padding: 10px 18px;
            border-color: #167d8d;
            background: #167d8d;
            font-weight: 600;
        }

        .generate-invoice-button:hover {
            border-color: #116675;
            background: #116675;
        }

        .billing-body #sidebarToggler {
            border: 0;
        }

        @media (max-width: 991.98px) {
            .billing-body header {
                min-height: 72px;
                padding: 10px 16px 10px 58px;
                flex-direction: row;
                align-items: center;
            }

            .billing-body header h1 {
                font-size: 21px;
            }

            .billing-body .profile-icons {
                width: auto;
            }

            .billing-body .profile-img {
                width: 42px;
                height: 42px;
            }

            .billing-body #sidebarToggler {
                top: 18px;
                left: 12px;
                display: flex;
                width: 36px;
                height: 36px;
                align-items: center;
                justify-content: center;
            }

            .invoice-page {
                width: calc(100% - 32px);
                margin-top: 20px;
            }

            .invoice-sheet {
                padding: 22px;
            }
        }

        @media (max-width: 575.98px) {
            .invoice-page {
                width: calc(100% - 20px);
                margin: 12px auto 24px;
            }

            .invoice-sheet {
                padding: 16px 13px;
            }

            .invoice-heading {
                margin-bottom: 16px;
            }

            .invoice-heading h1 {
                font-size: 23px;
            }

            .invoice-date {
                max-width: 118px;
                font-size: 12px;
            }

            .company-info {
                align-items: flex-start;
                margin-bottom: 20px;
                padding: 14px;
            }

            .company-details h2 {
                font-size: 16px;
            }

            .company-details p {
                font-size: 12px;
                line-height: 1.45;
            }

            .company-logo {
                width: 58px;
                height: 52px;
            }

            .customer-fields {
                grid-template-columns: 1fr;
            }

            .customer-address-field {
                grid-column: auto;
            }

            .invoice-line-heading {
                align-items: flex-start;
            }

            .add-product-button {
                padding: 8px 10px;
                font-size: 13px;
            }

            .invoice-bottom {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .discount-control .form-control {
                max-width: none;
            }

            .invoice-submit-row .btn {
                width: 100%;
            }
        }

        @media print {
            .billing-body {
                background: #fff;
            }

            .billing-body > *:not(.invoice-page),
            .invoice-line-heading .add-product-button,
            .invoice-submit-row {
                display: none !important;
            }

            .invoice-page {
                width: 100%;
                max-width: none;
                margin: 0;
            }

            .invoice-sheet {
                padding: 0;
                border: 0;
            }
        }
    </style>
</head>

<body class="billing-body">
    <?php include 'sidenav.php'; ?>
    <?php include 'header.php'; ?>
    
    <main class="invoice-page">
        <section class="invoice-sheet">
        <div class="invoice-heading">
            <div>
                <p class="invoice-kicker">Sales</p>
                <h1>New Invoice</h1>
            </div>
            <div class="invoice-date">Invoice date<br><strong><?php echo date('F j, Y'); ?></strong></div>
        </div>

        <div class="company-info">
            <div class="company-details">
                <h2>Yogesh Fire Crackers</h2>
                <p>Amaleshwar Nagar, Amalner, Dist. Jalgaon, Maharashtra 425201</p>
                
                <p>Phone: 9834394949</p>
            </div>
            <img class="company-logo" src="Assets/images/logo1.png" alt="Company logo">
        </div>

        <form method="POST" action="billing.php" id="invoiceForm">
            <section class="customer-section">
                <h2 class="invoice-section-heading">Customer details</h2>
                <div class="customer-fields">
                    <div class="form-group">
                        <label for="customer_name">Customer name</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                    </div>
                    <div class="form-group customer-address-field">
                        <label for="customer_address">Customer address</label>
                        <textarea class="form-control" id="customer_address" name="customer_address" rows="2" required></textarea>
                    </div>
                </div>
            </section>

            <section class="invoice-lines is-empty" id="invoiceLines">
                <div class="invoice-line-heading">
                    <div>
                        <h2>Invoice items</h2>
                        <p>Add products and set the quantity for each item.</p>
                    </div>
                    <button type="button" class="btn btn-success add-product-button" id="toggleProductPicker">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add product
                    </button>
                </div>

                <div class="product-picker-panel" id="productPickerPanel">
                    <div class="product-picker-header">
                        <h3>Best Seller Items</h3>
                        <span id="selectedCount">0 items selected</span>
                    </div>
                    <p class="product-added-message" id="productAddedMessage" role="status" aria-live="polite" hidden></p>
                    <div class="product-picker-grid" id="productPickerGrid"></div>
                </div>

                <p class="invoice-empty-state" id="emptyProducts">No products added yet. Add an item to start this invoice.</p>
                <div class="invoice-table-wrap">
            <table class="table table-bordered" id="productTable">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Product Size</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                 
                </tbody>
            </table>
                </div>
            </section>

            <div class="invoice-bottom">
                <div class="form-group discount-control">
                    <label for="discount_percentage">Discount percentage</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" value="0" min="0" max="100" step="0.01">
                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                    </div>
                    <small class="discount-help">Discount is applied to the subtotal.</small>
                </div>

                <div class="invoice-totals" aria-label="Invoice totals">
                    <div class="invoice-total-row">
                        <label for="subtotal">Subtotal</label>
                        <input type="text" class="form-control" id="subtotal" name="subtotal" value="0.00" readonly>
                    </div>
                    <div class="invoice-total-row">
                        <label for="total_discount">Total discount</label>
                        <input type="text" class="form-control" id="total_discount" name="total_discount" value="0.00" readonly>
                    </div>
                    <div class="invoice-total-row grand-total">
                        <label for="total_amount">Total amount</label>
                        <input type="text" class="form-control" id="total_amount" name="total_amount" value="0.00" readonly>
                    </div>
                </div>
            </div>

            <div class="invoice-submit-row">
                <button type="submit" class="btn btn-success generate-invoice-button"><i class="fas fa-file-invoice" aria-hidden="true"></i> Generate invoice</button>
            </div>
        </form>
        </section>
    </main>
    </div>

    <script>
        
        $(document).ready(function() {
            var counter = 0;
            var productData = <?php echo json_encode($products); ?>;

            function buildProductPicker() {
                var grid = $('#productPickerGrid');
                grid.empty();

                productData.forEach(function(product) {
                    var sizes = (product.size || '').split(',').map(function(size) {
                        return size.trim();
                    }).filter(Boolean);

                    var imageHtml = '';
                    if (product.image) {
                        imageHtml = `<img src="image/${product.image}" alt="${(product.name || 'Product').replace(/"/g, '&quot;')}">`;
                    } else {
                        imageHtml = `<span>${(product.name || 'P').charAt(0).toUpperCase()}</span>`;
                    }

                    var card = $(`
                        <div class="product-card" data-product-id="${product.id}" data-price="${product.amount}" data-sizes='${JSON.stringify(sizes)}'>
                            <span class="product-card-added" hidden><i class="fas fa-check-circle" aria-hidden="true"></i> Added</span>
                            <div class="product-card-thumb">${imageHtml}</div>
                            <p class="product-card-title">${product.name || 'Product'}</p>
                            <p class="product-card-price">₹ ${parseFloat(product.amount || 0).toFixed(2)}</p>
                            <p class="product-card-size">${sizes.length ? sizes[0] : 'Standard'}</p>
                            <div class="product-card-controls">
                                <button type="button" class="product-card-btn minus" aria-label="Decrease quantity">−</button>
                                <span class="product-card-qty">0</span>
                                <button type="button" class="product-card-btn plus" aria-label="Increase quantity">+</button>
                            </div>
                            <button type="button" class="product-card-add">Add to invoice</button>
                        </div>
                    `);

                    grid.append(card);
                });
            }

            var productAddedMessageTimeout;

            function syncAddedProductCards() {
                var productQuantities = {};
                $('#productTable tbody tr').each(function() {
                    var row = $(this);
                    var productId = row.find('.product-name').val();
                    if (productId) {
                        var quantity = parseInt(row.find('[name$="[quantity]"]').val(), 10) || 0;
                        var key = String(productId);
                        productQuantities[key] = (productQuantities[key] || 0) + quantity;
                    }
                });

                $('.product-card').each(function() {
                    var card = $(this);
                    var invoiceQuantity = productQuantities[String(card.data('product-id'))] || 0;
                    var pendingQuantity = parseInt(card.data('pending-quantity'), 10) || 0;
                    var isAdded = invoiceQuantity > 0;
                    card.data('invoice-quantity', invoiceQuantity);
                    card.toggleClass('is-added', isAdded);
                    card.find('.product-card-added').prop('hidden', !isAdded);
                    card.find('.product-card-qty').text(Math.max(0, invoiceQuantity + pendingQuantity));
                });
            }

            function removeInvoiceProductQuantity(productId) {
                var matchingRows = $('#productTable tbody tr').filter(function() {
                    return String($(this).find('.product-name').val()) === String(productId);
                });
                var row = matchingRows.last();

                if (!row.length) {
                    return;
                }

                var quantityInput = row.find('[name$="[quantity]"]');
                var currentQuantity = parseInt(quantityInput.val(), 10) || 0;
                if (currentQuantity <= 1) {
                    row.remove();
                } else {
                    var newQuantity = currentQuantity - 1;
                    var price = parseFloat(row.find('.product-price').val()) || 0;
                    quantityInput.val(newQuantity);
                    row.find('.product-total').val((newQuantity * price).toFixed(2));
                }

                if ($('#productTable tbody tr').length === 0) {
                    $('#invoiceLines').addClass('is-empty');
                }
            }

            function showProductAddedMessage(productName) {
                var message = $('#productAddedMessage');
                message.text(productName + ' added to invoice.').prop('hidden', false);
                window.clearTimeout(productAddedMessageTimeout);
                productAddedMessageTimeout = window.setTimeout(function() {
                    message.prop('hidden', true);
                }, 3500);
            }

            function updateSelectedCount() {
                var count = $('#productTable tbody tr').length;
                $('#selectedCount').text(count + ' item' + (count === 1 ? '' : 's') + ' selected');
            }

            function addProductRow(productId, productName, price, size, quantity) {
                var rowExists = false;
                $('#productTable tbody tr').each(function() {
                    var rowProductId = $(this).find('.product-name').val();
                    var rowSize = $(this).find('.product-size').val();
                    if (String(rowProductId) === String(productId) && String(rowSize) === String(size)) {
                        rowExists = true;
                        var currentQty = parseInt($(this).find('[name$="[quantity]"]').val()) || 0;
                        $(this).find('[name$="[quantity]"]').val(currentQty + quantity);
                        var newTotal = (currentQty + quantity) * price;
                        $(this).find('.product-total').val(newTotal.toFixed(2));
                    }
                });

                if (rowExists) {
                    calculateTotals();
                    return;
                }

                counter++;
                $('#invoiceLines').removeClass('is-empty');
                var selectOptions = productData.map(function(product) {
                    var selected = String(product.id) === String(productId) ? 'selected' : '';
                    return `<option value="${product.id}" data-price="${product.amount}" data-sizes="${(product.size || '').trim()}" ${selected}>${product.name}</option>`;
                }).join('');

                var row = `
                    <tr>
                        <td>
                            <select class="form-control product-name" name="products[${counter}][name]" required>
                                <option value="">Select Product</option>
                                ${selectOptions}
                            </select>
                        </td>
                        <td>
                            <select class="form-control product-size" name="products[${counter}][size]" required>
                                <option value="">Select Size</option>
                            </select>
                        </td>
                        <td><input type="number" class="form-control" name="products[${counter}][quantity]" min="1" value="${quantity}" required></td>
                        <td><input type="number" class="form-control product-price" name="products[${counter}][price]" value="${price}" readonly></td>
                        <td><input type="number" class="form-control product-total" name="products[${counter}][total]" value="${(price * quantity).toFixed(2)}" readonly></td>
                        <td><button type="button" class="btn btn-danger removeRow">Remove</button></td>
                    </tr>
                `;
                $('#productTable tbody').append(row);

                var currentRow = $('#productTable tbody tr').last();
                var sizeSelect = currentRow.find('.product-size');
                sizeSelect.empty();
                sizeSelect.append(`<option value="">Select Size</option>`);
                var sizes = (productData.find(function(product) { return String(product.id) === String(productId); }) || {}).size || '';
                sizes.split(',').forEach(function(item) {
                    var trimmed = item.trim();
                    if (trimmed) {
                        sizeSelect.append(`<option value="${trimmed}" ${trimmed === size ? 'selected' : ''}>${trimmed}</option>`);
                    }
                });
                currentRow.find('.product-name').trigger('change');
                currentRow.find('.product-size').val(size);
                currentRow.find('.product-total').val((price * quantity).toFixed(2));
                calculateTotals();
            }

            $('#toggleProductPicker').click(function() {
                $('#productPickerPanel').toggleClass('is-open');
            });

            buildProductPicker();
            syncAddedProductCards();

            $(document).on('click', '.product-card-btn.plus', function() {
                var card = $(this).closest('.product-card');
                var pendingQuantity = parseInt(card.data('pending-quantity'), 10) || 0;
                card.data('pending-quantity', pendingQuantity + 1);
                syncAddedProductCards();
            });

            $(document).on('click', '.product-card-btn.minus', function() {
                var card = $(this).closest('.product-card');
                var pendingQuantity = parseInt(card.data('pending-quantity'), 10) || 0;
                if (pendingQuantity > 0) {
                    card.data('pending-quantity', pendingQuantity - 1);
                } else if ((parseInt(card.data('invoice-quantity'), 10) || 0) > 0) {
                    removeInvoiceProductQuantity(card.data('product-id'));
                    updateSelectedCount();
                    calculateTotals();
                }
                syncAddedProductCards();
            });

            $(document).on('click', '.product-card-add', function() {
                var card = $(this).closest('.product-card');
                var productId = card.data('product-id');
                var price = parseFloat(card.data('price')) || 0;
                var sizes = card.data('sizes') || [];
                var quantity = parseInt(card.data('pending-quantity'), 10) || 0;
                quantity = quantity > 0 ? quantity : 1;
                var selectedSize = sizes[0] || 'Standard';
                var product = productData.find(function(item) { return String(item.id) === String(productId); });
                if (product) {
                    addProductRow(product.id, product.name, price, selectedSize, quantity);
                    card.data('pending-quantity', 0);
                    syncAddedProductCards();
                    showProductAddedMessage(product.name);
                }
                updateSelectedCount();
            });

            $('#addRow').click(function() {
                $('#invoiceLines').removeClass('is-empty');
            });

            $(document).on('click', '.removeRow', function() {
                $(this).closest('tr').remove();
                if ($('#productTable tbody tr').length === 0) {
                    $('#invoiceLines').addClass('is-empty');
                }
                syncAddedProductCards();
                updateSelectedCount();
                calculateTotals();
            });

            $(document).on('change', '.product-name', function() {
                var selectedProduct = $(this).find(':selected');
                var sizes = (selectedProduct.data('sizes') || '').toString().split(',');
                var price = selectedProduct.data('price');
                var sizeSelect = $(this).closest('tr').find('.product-size');
                sizeSelect.empty();
                sizeSelect.append('<option value="">Select Size</option>');
                sizes.forEach(function(size) {
                    var trimmed = (size || '').trim();
                    if (trimmed) {
                        sizeSelect.append(`<option value="${trimmed}">${trimmed}</option>`);
                    }
                });
                $(this).closest('tr').find('.product-price').val(price);
                $(this).closest('tr').find('.product-total').val('0.00');
                syncAddedProductCards();
                updateSelectedCount();
            });

            $(document).on('input', '[name$="[quantity]"]', function() {
                var quantity = parseInt($(this).val()) || 0;
                var price = parseFloat($(this).closest('tr').find('.product-price').val()) || 0;
                var total = quantity * price;
                $(this).closest('tr').find('.product-total').val(total.toFixed(2));
                syncAddedProductCards();
                calculateTotals();
            });

            function calculateTotals() {
                let subtotal = 0;

             
                $('#productTable tbody tr').each(function() {
                    let total = parseFloat($(this).find('input[name$="[total]"]').val()) || 0;
                    subtotal += total;
                });

        
                let discountPercentage = parseFloat($('#discount_percentage').val()) || 0;
                let percentageDiscount = subtotal * (discountPercentage / 100);
                let totalDiscount = percentageDiscount;

    
                let subtotalAfterDiscount = subtotal - totalDiscount;

             
                let totalAmount = subtotalAfterDiscount;

          
                $('#subtotal').val(subtotal.toFixed(2)); 
                $('#total_discount').val(totalDiscount.toFixed(2)); 
                $('#total_amount').val(totalAmount.toFixed(2)); 
            }

         
            $('#discount_percentage').on('input', function() {
                calculateTotals();
            });
        });
    </script>

<script>

document.getElementById('logoutButton').addEventListener('click', function () {
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of the session!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log out!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php'; 
        }
    });
});

</script>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($alertMessage !== ''): ?>
<script>
Swal.fire({
    icon: <?php echo json_encode($alertType); ?>,
    text: <?php echo json_encode($alertMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
});
</script>
<?php endif; ?>

</body>
</html>
