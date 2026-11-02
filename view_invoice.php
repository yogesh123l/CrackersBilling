<?php
require('fpdf186/fpdf.php');


$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";


$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$invoice_id || $invoice_id < 1) {
    http_response_code(400);
    exit('Invalid invoice ID.');
}

$invoice_sql = "SELECT * FROM invoices WHERE invoice_id = ?";
$stmt = $conn->prepare($invoice_sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice_result = $stmt->get_result();
$invoice = $invoice_result->fetch_assoc();

if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found.');
}

$items_sql = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$stmt_items = $conn->prepare($items_sql);
$stmt_items->bind_param("i", $invoice_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

$total_amount = 0;
$subtotal = 0;
$discount_percentage = $invoice['discount_percentage'] ?? 0;
$discount_amount = 0;


while ($item = $items_result->fetch_assoc()) {
    $item_total = $item['quantity'] * $item['price'];
    $subtotal += $item_total; 
}


$discount_amount = ($subtotal * $discount_percentage) / 100;


$subtotal_after_discount = $subtotal - $discount_amount;


$total_amount = $subtotal_after_discount;

if (isset($_GET['pdf'])) {
    
    $pdf = new FPDF();
    $pdf->AddPage();

    
    $pdf->SetFont('courier', 'B', 16);
    

    $pdf->Cell(0, 10, 'Yogesh Fire Crackers', 0, 1, 'C');
    $pdf->SetFont('courier', '', 12);
    $pdf->Cell(0, 10, '', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Phone: 9834394949', 0, 1, 'C');

    
    $pdf->Ln();
    $pdf->SetFont('courier', 'B', 12);
    $pdf->Cell(0, 10, 'Invoice Details:', 0, 1);
    $pdf->SetFont('courier', '', 12);
    $pdf->Cell(0, 10, 'Invoice Number: #' . $invoice['invoice_id'], 0, 1);
    $pdf->Cell(0, 10, 'Invoice Date: ' . date("d/m/Y", strtotime($invoice['invoice_date'])), 0, 1);
    
    $pdf->Ln();
    $pdf->Cell(0, 10, 'Invoice To:', 0, 1);
    $pdf->Cell(0, 10, $invoice['customer_name'], 0, 1);
    $pdf->MultiCell(0, 10, $invoice['customer_address'], 0, 'L');

    
    $pdf->Ln();
    $pdf->SetFont('courier', 'B', 12);
    $pdf->Cell(10, 10, 'S.No', 1, 0, 'C');
    $pdf->Cell(70, 10, 'Product Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Size', 1, 0, 'C');
    $pdf->Cell(20, 10, 'Quantity', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Price (₹)', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Total (₹)', 1, 1, 'C');
    
    
    $i = 1;
    $items_result->data_seek(0);
    while ($item = $items_result->fetch_assoc()) {
        $item_total = $item['quantity'] * $item['price'];
        $pdf->Cell(10, 10, $i++, 1, 0, 'C');
        $pdf->Cell(70, 10, $item['product_name'], 1, 0, 'C');
        $pdf->Cell(30, 10, $item['product_size'], 1, 0, 'C');
        $pdf->Cell(20, 10, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(30, 10, number_format($item['price'], 2), 1, 0, 'C');
        $pdf->Cell(30, 10, number_format($item_total, 2), 1, 1, 'C');
    }

    $pdf->Ln();
    $pdf->Cell(150, 10, 'Subtotal', 1);
    $pdf->Cell(30, 10, '₹' . number_format($subtotal, 2), 1);
    $pdf->Ln();
    $pdf->Cell(150, 10, 'Discount (' . number_format($discount_percentage, 2) . '%)', 1);
    $pdf->Cell(30, 10, '₹' . number_format($discount_amount, 2), 1);
    $pdf->Ln();
    $pdf->Cell(150, 10, 'Total Amount', 1);
    $pdf->Cell(30, 10, '₹' . number_format($total_amount, 2), 1);

    
    $pdf->Ln(10);
    $pdf->SetFont('courier', 'I', 10);
    $pdf->MultiCell(0, 10, 'Terms and Conditions Apply.', 0, 'C');
    
    
    $pdf->Output('I', 'invoice_' . $invoice['invoice_id'] . '.pdf');
    
    exit;
}


$invoice_message = "Invoice Details:\n";
$invoice_message .= "Invoice Number: #" . $invoice['invoice_id'] . "\n";
$invoice_message .= "Invoice Date: " . date("d/m/Y", strtotime($invoice['invoice_date'])) . "\n";
$invoice_message .= "Customer Name: " . $invoice['customer_name'] . "\n\n";
$invoice_message .= "Items:\n";
$invoice_message .= "---------------------------------------\n";
$invoice_message .= "S.No | Product | Qty | Price | Total\n";
$invoice_message .= "---------------------------------------\n";

$i = 1;
$items_result->data_seek(0);
while ($item = $items_result->fetch_assoc()) {
    $item_total = $item['quantity'] * $item['price'];
    $invoice_message .= sprintf(
        "%d. %s | %d | ₹%.2f | ₹%.2f\n",
        $i++,
        $item['product_name'],
        $item['quantity'],
        $item['price'],
        $item_total
    );
}

$invoice_message .= "\n";
$invoice_message .= "Subtotal: ₹" . number_format($subtotal, 2) . "\n";
$invoice_message .= "Discount (" . number_format($discount_percentage, 2) . "%): ₹" . number_format($discount_amount, 2) . "\n";
$invoice_message .= "Total Amount: ₹" . number_format($total_amount, 2) . "\n";

$whatsapp_url = 'https://wa.me/?text=' . rawurlencode($invoice_message);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice['invoice_id']; ?></title>
    <link rel="icon" href="Assets/images/logo1.png" type="image/jpg">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        .invoice-container {
            width: calc(100% - 32px);
            max-width: 960px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 6px;
        }

        .invoice-company-row,
        .invoice-party-row {
            margin-right: 0;
            margin-left: 0;
        }

        .company-logo {
            width: 100%;
            max-width: 150px;
            height: auto;
            object-fit: contain;
        }

        .invoice-items-table {
            width: 100%;
            table-layout: fixed;
        }

        .company-info, .customer-info, .invoice-details {
            margin-bottom: 20px;
        }
        .table th, .table td {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .invoice-total {
            font-weight: bold;
        }
        .invoice-action {
            border: 0;
            padding: 0;
            background: transparent;
            line-height: 1;
            cursor: pointer;
        }

        @media screen and (max-width: 575px) {
            .invoice-container {
                width: calc(100% - 24px);
                margin: 64px auto 16px;
                padding: 14px;
            }

            .invoice-company-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 58px;
                gap: 8px;
            }

            .invoice-company-row > [class*="col-"] {
                width: auto;
                max-width: none;
                padding: 0;
            }

            .company-info,
            .customer-info,
            .invoice-details {
                margin-bottom: 12px;
            }

            .company-info h3 {
                max-width: 100%;
                font-size: 18px;
                overflow-wrap: anywhere;
            }

            .company-info p {
                margin-bottom: 4px;
                font-size: 13px;
            }

            .company-logo {
                width: 58px;
                max-width: 58px;
                margin-top: 4px;
            }

            .invoice-party-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
                gap: 8px;
            }

            .invoice-party-row > [class*="col-"] {
                width: auto;
                max-width: none;
                padding: 0;
            }

            .invoice-party-row h4,
            .invoice-party-row h5 {
                font-size: 15px;
            }

            .invoice-party-row p {
                font-size: 13px;
                overflow-wrap: anywhere;
            }

            .invoice-items-table,
            .invoice-items-table tbody {
                display: block;
                width: 100%;
                margin-bottom: 12px;
            }

            .invoice-items-table thead {
                display: none;
            }

            .invoice-items-table tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0 10px;
                width: 100%;
                margin-bottom: 10px;
                padding: 7px 9px;
                border: 1px solid #dfe3e8;
                border-radius: 4px;
            }

            .invoice-items-table td {
                display: flex;
                min-width: 0;
                align-items: baseline;
                justify-content: space-between;
                gap: 4px;
                padding: 4px 0;
                border: 0;
                font-size: 12px;
                text-align: right;
                overflow-wrap: anywhere;
            }

            .invoice-items-table td::before {
                content: attr(data-label);
                color: #667085;
                font-weight: 600;
                text-align: left;
            }

            .invoice-items-table td:first-child {
                display: none;
            }

            .invoice-items-table td:nth-child(2) {
                display: block;
                grid-column: 1 / -1;
                padding-bottom: 6px;
                border-bottom: 1px solid #eceff2;
                font-size: 14px;
                font-weight: 700;
                text-align: left;
            }

            .invoice-items-table td:nth-child(2)::before {
                content: none;
            }

            .invoice-totals > .col-md-6:first-child {
                display: none;
            }

            .invoice-totals > .col-md-6:last-child {
                width: 100%;
                max-width: 100%;
                text-align: right;
            }

            .invoice-totals p {
                margin-bottom: 6px;
                font-size: 13px;
            }

            .invoice-container > .row.no-print {
                margin: 12px 0 0;
            }
        }

        @media print {
            @page {
                size: 80mm 200mm;
                margin: 0;
            }

            html, body {
                width: 80mm !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            body {
                margin-left: 0 !important;
            }

            body * {
                visibility: hidden;
            }

            .print-area, .print-area * {
                visibility: visible;
            }

            .sidebar, .no-print {
                display: none !important;
            }

            .print-area {
                position: absolute;
                top: 0;
                left: 0;
                width: 80mm !important;
                max-width: none !important;
                box-sizing: border-box;
                margin: 0 !important;
                padding: 2mm !important;
                background: #fff;
                color: #000;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 10pt;
                line-height: 1.35;
            }

            .print-area > .row {
                display: block;
                margin: 0;
            }

            .print-area > .row > [class*="col-"] {
                width: 100%;
                max-width: 100%;
                padding: 0;
            }

            .print-area > .row:first-child .col-md-6.text-right {
                display: none !important;
            }

            .company-info, .customer-info, .invoice-details {
                margin-bottom: 5px;
            }

            .company-info h3 {
                margin-bottom: 4px;
                font-size: 14pt;
            }

            .customer-info h4 {
                font-size: 12pt;
            }

            .invoice-details h5 {
                font-size: 11pt;
            }

            .invoice-details {
                text-align: left !important;
            }

            .print-area p {
                margin-bottom: 2px;
            }

            .print-area table,
            .print-area table tbody,
            .print-area table tr {
                display: block;
                width: 100%;
            }

            .print-area table {
                margin: 5px 0;
                table-layout: fixed;
            }

            .print-area table thead {
                display: none;
            }

            .print-area table tr {
                padding: 3px 0;
                border-bottom: 1px dashed #555;
                break-inside: avoid;
            }

            .print-area table td {
                display: inline-block;
                box-sizing: border-box;
                border: 0 !important;
                padding: 1px 0 !important;
                font-size: 9pt;
                line-height: 1.4;
                text-align: left !important;
                vertical-align: top;
                overflow-wrap: anywhere;
            }

            .print-area table td:nth-child(1) {
                display: none;
            }

            .print-area table td:nth-child(2) {
                display: block;
                width: 100%;
                font-weight: bold;
                font-size: 10pt;
            }

            .print-area table td:nth-child(3) {
                width: 13%;
            }

            .print-area table td:nth-child(3)::before {
                content: "Size: ";
            }

            .print-area table td:nth-child(4) {
                width: 18%;
            }

            .print-area table td:nth-child(4)::before {
                content: "Qty: ";
            }

            .print-area table td:nth-child(5) {
                width: 34%;
            }

            .print-area table td:nth-child(5)::before {
                content: "Price: ";
            }

            .print-area table td:nth-child(6) {
                width: 35%;
                text-align: right !important;
            }

            .print-area table td:nth-child(6)::before {
                content: "Total: ";
            }

            .invoice-totals > .col-md-6:first-child {
                display: none !important;
            }

            .invoice-totals > .col-md-6:last-child {
                text-align: left !important;
            }

            .invoice-totals p {
                margin: 2px 0;
                font-size: 9pt;
            }
        }
    </style>
</head>
<body class="invoice-view-body">
<?php include 'sidenav.php'; ?>

<div class="container invoice-container print-area">

    <div class="row invoice-company-row">
        <div class="col-md-6">
            <div class="company-info">
                <h3>Yogesh Fire Crackers</h3>
                <p>Amaleshwar Nagar,<br>Amalner,<br>Dist. Jalgaon,<br>Maharashtra 425201.</p>
               
               <strong> <p>Phone: 9834394949</p></strong>
            </div>
        </div>
        <div class="col-md-6 text-right">
            <img src="Assets/images/logo1.png" alt="Company Logo" class="company-logo">
        </div>
    </div>

    <div class="row invoice-party-row">
        <div class="col-md-6">
            <div class="customer-info">
                <h4>Invoice To:</h4>
                <p>
                    <strong><?php echo $invoice['customer_name']; ?></strong><br>
                    <?php echo nl2br($invoice['customer_address']); ?><br>
                </p>
            </div>
        </div>
        <div class="col-md-6 text-right">
            <div class="invoice-details">
                <h5>Invoice Details:</h5>
                <p>
                    Invoice Number: <strong>#<?php echo $invoice['invoice_id']; ?></strong><br>
                    Invoice Date: <strong><?php echo date("d/m/Y", strtotime($invoice['invoice_date'])); ?></strong>
                </p>
            </div>
        </div>
    </div>

    <table class="table table-bordered invoice-items-table">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Product Name</th>
                <th>Size</th>
                <th>Quantity</th>
                <th>Price (₹)</th>
                <th>Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            $items_result->data_seek(0);
            while ($item = $items_result->fetch_assoc()): 
                $item_total = $item['quantity'] * $item['price'];
            ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Size"><?php echo htmlspecialchars($item['product_size'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Qty"><?php echo (int) $item['quantity']; ?></td>
                    <td data-label="Price"><?php echo number_format($item['price'], 2); ?></td>
                    <td data-label="Total"><?php echo number_format($item_total, 2); ?></td>
                    
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="row invoice-totals">
        <div class="col-md-6"></div> 
        <div class="col-md-6 text-right">
            <p><strong>Subtotal:</strong> ₹<?php echo number_format($subtotal, 2); ?></p>
            <p><strong>Discount (<?php echo number_format($discount_percentage, 2); ?>%):</strong> ₹<?php echo number_format($discount_amount, 2); ?></p>
            <p class="invoice-total"><strong>Total Amount:</strong> ₹<?php echo number_format($total_amount, 2); ?></p>
            <p class="text-center"> Terms & conditions are Applicable</p>
        </div>
    </div>
   
    <div class="row mt-3 no-print">
    <div class="col-md-12 d-flex justify-content-around text-right">
        
        <button type="button" class="invoice-action" onclick="window.print()" title="Print invoice" aria-label="Print invoice">
            <i class="fa fa-print" aria-hidden="true" style="font-size:42px; color:gray;"></i>
        </button>
        
        <a href="invoice.php?id=<?php echo (int) $invoice['invoice_id']; ?>&pdf=true" target="_blank" rel="noopener" title="Open invoice PDF" aria-label="Open invoice PDF">
            <i class="fas fa-file-pdf" aria-hidden="true" style="font-size:42px;color:red;"></i>
        </a>

        <a href="<?php echo htmlspecialchars($whatsapp_url, ENT_QUOTES, 'UTF-8'); ?>" 
           target="_blank" rel="noopener" title="Share invoice on WhatsApp" aria-label="Share invoice on WhatsApp" style="text-decoration: none; color: green;">
           <i class="fab fa-whatsapp-square" aria-hidden="true" style="font-size:42px;color:green;"></i>
        </a>
    </div>
</div>



</div>


</body>
</html>
