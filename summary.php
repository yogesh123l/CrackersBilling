<?php

$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$search = '';
$from_date = '';
$to_date = '';
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = isset($_POST['search']) ? $_POST['search'] : '';
    $from_date = isset($_POST['from_date']) ? $_POST['from_date'] : '';
    $to_date = isset($_POST['to_date']) ? $_POST['to_date'] : '';
}

$sql = "
    SELECT 
        i.invoice_id,
        i.customer_name,
        i.customer_address,
        i.invoice_date,
        i.subtotal,
        i.total_amount,
        i.discount_percentage,
        GROUP_CONCAT(CONCAT_WS(' ', it.product_name, '(Size:', it.product_size, ', Qty:', it.quantity, ')') SEPARATOR '<br>') AS product_details
    FROM invoices i
    LEFT JOIN invoice_items it ON i.invoice_id = it.invoice_id
";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " WHERE i.customer_name LIKE ? OR it.product_name LIKE ?";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam; 
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($from_date) && !empty($to_date)) {
    $sql .= (empty($search) ? " WHERE " : " AND ") . "i.invoice_date BETWEEN ? AND ?";
    $params[] = $from_date; 
    $params[] = $to_date;   
    $types .= 'ss'; 
}

$sql .= " GROUP BY i.invoice_id ORDER BY i.invoice_id DESC LIMIT ? OFFSET ?";
$params[] = $limit;  
$params[] = $offset; 
$types .= 'ii'; 

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

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

if ($stmt) {
    $stmt->close();
}

$count_sql = "SELECT COUNT(DISTINCT i.invoice_id) AS total FROM invoices i LEFT JOIN invoice_items it ON i.invoice_id = it.invoice_id";
if (!empty($search)) {
    $count_sql .= " WHERE i.customer_name LIKE ? OR it.product_name LIKE ?";
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}

$searchTerm = '%' . $search . '%'; 

if (!empty($search)) {
    $count_stmt->bind_param('ss', $searchTerm, $searchTerm); 
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$image_stmt = $conn->prepare("SELECT profile_image FROM login WHERE id = ?");
$userId = 1;
$image_stmt->bind_param('i', $userId);
$image_stmt->execute();
$image_stmt->bind_result($profileImage);
$image_stmt->fetch();
$image_stmt->close(); 

if (!empty($profileImage)) {
    $profileImage = $profileImage;
} else {
    $profileImage = 'default.jpg';
}
if (isset($_POST['download_pdf'])) {
    require('fpdf186/fpdf.php');

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(190, 10, 'Invoice Report', 0, 1, 'C'); 

    

    $pdf->Ln();
    $pdf->SetFont('Arial', 'B', 10);

    $widths = [
        'invoice_id' => 20,
        'customer_name' => 30,
        'invoice_date' => 30,
        'products' => 50,
        'subtotal' => 20,
        'total_amount' => 20
    ];



   $pdf->Cell($widths['invoice_id'], 10, 'Invoice ID', 1);
    $pdf->Cell($widths['customer_name'], 10, 'Customer Name', 1);
    $pdf->Cell($widths['invoice_date'], 10, 'Date', 1);
    $pdf->Cell($widths['products'], 10, 'Products', 1);
    $pdf->Cell($widths['subtotal'], 10, 'Subtotal', 1);
    $pdf->Cell($widths['total_amount'], 10, 'Total', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 10);
    


    $query = "
        SELECT 
            i.invoice_id, i.customer_name, i.invoice_date, i.subtotal, i.total_amount, 
            GROUP_CONCAT(CONCAT_WS(' ', it.product_name, '(Size:', it.product_size, ', Qty:', it.quantity, ')') SEPARATOR '\n') AS product_details
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
    
    if (!empty($search)) {
        $query .= " AND i.invoice_date BETWEEN ? AND ?";
    } else {
        $query .= " WHERE i.invoice_date BETWEEN ? AND ?";
    }
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
    
    $lineHeight = 8; 
    $cellWidth = $widths['products'];


    $lineCount = ceil($pdf->GetStringWidth($row['product_details']) / $cellWidth);
    $cellHeight = max($lineCount, 1) * $lineHeight; 

        
    $pdf->Cell($widths['invoice_id'], $cellHeight, $row['invoice_id'], 1);
    $pdf->Cell($widths['customer_name'], $cellHeight, $row['customer_name'], 1);
    $pdf->Cell($widths['invoice_date'], $cellHeight, date("d/m/Y", strtotime($row['invoice_date'])), 1);


    $x = $pdf->GetX(); 
    $y = $pdf->GetY(); 


    $pdf->MultiCell($cellWidth, $lineHeight, $row['product_details'], 1);
    
    
    $multiCellHeight = $pdf->GetY() - $y; 

    
    $pdf->SetXY($x + $cellWidth, $y); 
    $pdf->Cell($widths['subtotal'], $multiCellHeight, 'Rs.' . number_format($row['subtotal'], 2), 1);
    $pdf->Cell($widths['total_amount'], $multiCellHeight, 'Rs.' . number_format($row['total_amount'], 2), 1);

    
    $pdf->Ln(max($cellHeight, $multiCellHeight)); 
}


$pdf->Output('D', 'Invoice_Report_' . date('Ymd') . '.pdf');
exit; 
}
$count_stmt->close();
$conn->close(); 
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="Assets/images/logo1.png" type="image/jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-faf9d9675135708740834769d559d90807309968716651d9e756d33277107337" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Crackers Billing</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    
   
    <style>
        .summary-body {
            background: #f4f6f9;
            color: #202638;
            font-family: Arial, sans-serif;
        }

        .summary-body header {
            min-height: 78px;
            padding: 14px 28px;
            background: #fff;
        }

        .summary-body header h1 {
            margin: 0;
            color: #202638;
            font-size: 24px;
        }

        .summary-page {
            max-width: 1500px;
            margin: 0 auto;
            padding: 28px 28px 40px;
        }

        .summary-title-row,
        .summary-section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .summary-title-row {
            margin-bottom: 22px;
        }

        .summary-eyebrow {
            margin: 0 0 5px;
            color: #167d8d;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary-title-row h2,
        .summary-section-heading h2 {
            margin: 0;
            color: #202638;
            font-size: 24px;
            font-weight: 700;
        }

        .summary-title-row p:last-child,
        .summary-section-heading p {
            margin: 5px 0 0;
            color: #667085;
            font-size: 13px;
        }

        .summary-count {
            flex: 0 0 auto;
            padding: 8px 12px;
            border: 1px solid #d8dee7;
            border-radius: 4px;
            background: #fff;
            color: #4b5565;
            font-size: 13px;
        }

        .summary-filters {
            display: grid;
            grid-template-columns: minmax(220px, 1.5fr) repeat(2, minmax(140px, 0.8fr)) auto auto;
            align-items: end;
            gap: 10px;
            margin: 0 0 24px;
            padding: 14px;
            border: 1px solid #e1e5eb;
            border-radius: 5px;
            background: #fff;
        }

        .filter-search {
            position: relative;
        }

        .summary-filters .form-control {
            min-width: 0;
            height: 42px;
            border-color: #d8dee7;
            font-size: 14px;
        }

        .summary-filters .btn {
            height: 42px;
            padding: 0 14px;
            white-space: nowrap;
        }

        .summary-search-button {
            border-color: #212545;
            background: #212545;
        }

        .summary-search-button:hover {
            border-color: #30385f;
            background: #30385f;
        }

        .summary-export-actions {
            display: flex;
            gap: 8px;
        }

        .summary-export-button {
            display: inline-flex;
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            border: 1px solid #d8dee7;
            border-radius: 4px;
            background: #fff;
            font-size: 17px;
            text-decoration: none;
        }

        .summary-export-button:hover {
            background: #f4f6f9;
            text-decoration: none;
        }

        .summary-export-pdf {
            color: #c93f4d;
        }

        .summary-export-excel {
            color: #24865a;
        }

        .suggestions-box {
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            z-index: 1200;
            display: none;
            width: 100%;
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #d8dee7;
            border-radius: 4px;
            background: #fff;
            box-shadow: 0 6px 16px rgba(32, 38, 56, 0.12);
        }

        .suggestion-item {
            padding: 9px 11px;
            border-bottom: 1px solid #eef0f3;
            color: #202638;
            cursor: pointer;
        }

        .suggestion-item:hover {
            background: #f4f6f9;
        }

        .summary-section-heading {
            margin-bottom: 12px;
        }

        .summary-section-heading h2 {
            font-size: 18px;
        }

        .invoice-table-wrap {
            overflow-x: auto;
            border: 1px solid #e1e5eb;
            border-radius: 5px;
            background: #fff;
        }

        .invoice-summary-table {
            min-width: 1180px;
            margin: 0;
            background: #fff;
            font-size: 13px;
        }

        .invoice-summary-table th {
            padding: 12px 10px;
            border-top: 0;
            border-bottom: 0;
            background: #212545;
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
        }

        .invoice-summary-table td {
            padding: 11px 10px;
            border-color: #e8ebf0;
            vertical-align: top;
        }

        .invoice-summary-table tbody tr:hover {
            background: #f8fafc;
        }

        .invoice-product-details {
            min-width: 220px;
            line-height: 1.6;
        }

        .invoice-amount {
            white-space: nowrap;
        }

        .invoice-summary-table .action-icons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }

        .action-icons a,
        .action-icons button {
            display: inline-flex;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            margin: 0;
            border-radius: 4px;
        }

        .action-icons a:hover,
        .action-icons button:hover {
            background: #eef1f6;
            text-decoration: none;
        }

        .summary-pagination {
            margin: 18px 0 0;
        }

        .summary-pagination .pagination {
            flex-wrap: wrap;
            justify-content: center;
            gap: 2px;
        }

        .summary-body #sidebarToggler {
            border: 0;
        }

        @media (max-width: 991.98px) {
            .summary-body header {
                min-height: 72px;
                padding: 10px 16px 10px 58px;
                flex-direction: row;
                align-items: center;
            }

            .summary-body header h1 {
                font-size: 21px;
            }

            .summary-body .profile-icons {
                width: auto;
            }

            .summary-body .profile-img {
                width: 42px;
                height: 42px;
            }

            .summary-body #sidebarToggler {
                top: 18px;
                left: 12px;
                display: flex;
                width: 36px;
                height: 36px;
                align-items: center;
                justify-content: center;
            }

            .summary-page {
                padding: 22px 18px 32px;
            }

            .summary-filters {
                grid-template-columns: minmax(0, 1fr) minmax(130px, 0.8fr) minmax(130px, 0.8fr) auto;
            }

            .summary-search-button {
                grid-column: 1;
            }

            .summary-export-actions {
                grid-column: 2 / -1;
            }
        }

        @media (max-width: 767.98px) {
            .summary-page {
                padding: 18px 12px 28px;
            }

            .summary-title-row {
                margin-bottom: 16px;
            }

            .summary-title-row h2 {
                font-size: 21px;
            }

            .summary-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 9px;
                padding: 11px;
            }

            .filter-search,
            .summary-search-button,
            .summary-export-actions {
                grid-column: 1 / -1;
            }

            .summary-export-actions {
                justify-content: flex-end;
            }

            .invoice-table-wrap {
                overflow: visible;
                border: 0;
                background: transparent;
            }

            .invoice-summary-table,
            .invoice-summary-table tbody {
                display: block;
                width: 100%;
                min-width: 0;
            }

            .invoice-summary-table thead {
                display: none;
            }

            .invoice-summary-table tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0 14px;
                margin-bottom: 12px;
                padding: 8px 12px;
                overflow: hidden;
                border: 1px solid #e1e5eb;
                border-radius: 5px;
                background: #fff;
                box-shadow: 0 2px 5px rgba(32, 38, 56, 0.04);
            }

            .invoice-summary-table tbody tr:hover {
                background: #fff;
            }

            .invoice-summary-table tbody td {
                display: block;
                min-width: 0;
                padding: 10px 0;
                border: 0;
                border-bottom: 1px solid #eef0f3;
                overflow-wrap: anywhere;
            }

            .invoice-summary-table tbody td::before {
                display: block;
                margin-bottom: 4px;
                color: #667085;
                content: attr(data-label);
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .invoice-summary-table tbody td.invoice-product-details,
            .invoice-summary-table tbody td.invoice-actions-cell {
                grid-column: 1 / -1;
            }

            .invoice-summary-table tbody td.invoice-product-details {
                max-height: 150px;
                overflow-y: auto;
                line-height: 1.55;
            }

            .invoice-summary-table tbody td.invoice-amount {
                white-space: normal;
            }

            .invoice-summary-table tbody td.invoice-actions-cell {
                border-bottom: 0;
            }

            .invoice-summary-table tbody td.invoice-actions-cell::before {
                content: 'Actions';
            }

            .invoice-summary-table .action-icons {
                justify-content: flex-start;
            }

            .invoice-summary-table tbody tr td[colspan] {
                grid-column: 1 / -1;
                border: 0;
            }
        }

        @media (max-width: 360px) {
            .invoice-summary-table tbody tr {
                grid-template-columns: 1fr;
            }

            .invoice-summary-table tbody td.invoice-product-details,
            .invoice-summary-table tbody td.invoice-actions-cell {
                grid-column: auto;
            }
        }
    </style>
</head>
<body class="summary-body">
<?php include 'sidenav.php'; ?>
<?php include 'header.php'; ?>

    <main class="summary-page">
        <div class="summary-title-row">
            <div>
                <p class="summary-eyebrow">Sales</p>
                <h2>Invoice summary</h2>
                <p>Search, review, and export customer invoices.</p>
            </div>
            <span class="summary-count"><?php echo number_format((int) $total_records); ?> invoices</span>
        </div>

        <form method="POST" class="summary-filters">
            <div class="filter-search">
                <label class="sr-only" for="search">Search invoices</label>
                <input type="search" name="search" id="search" class="form-control" placeholder="Customer or product" value="<?php echo htmlspecialchars($search); ?>">
                <div id="suggestions" class="suggestions-box"></div>
            </div>
            <div>
                <label class="sr-only" for="from_date">From date</label>
                <input type="text" id="from_date" name="from_date" class="form-control" placeholder="From date" value="<?php echo htmlspecialchars($from_date); ?>">
            </div>
            <div>
                <label class="sr-only" for="to_date">To date</label>
                <input type="text" id="to_date" name="to_date" class="form-control" placeholder="To date" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
            <button type="submit" class="btn btn-primary summary-search-button"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button>
            <div class="summary-export-actions">
                <button type="submit" name="download_pdf" class="summary-export-button summary-export-pdf" title="Download PDF" aria-label="Download PDF"><i class="fas fa-file-pdf" aria-hidden="true"></i></button>
                <a href="export_excel.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" class="summary-export-button summary-export-excel" title="Export Excel" aria-label="Export Excel"><i class="fas fa-file-excel" aria-hidden="true"></i></a>
            </div>
        </form>

        <div class="summary-section-heading">
            <div>
                <h2>Invoices</h2>
                <p>Most recent invoices first</p>
            </div>
        </div>

        <div class="invoice-table-wrap">
        <table class="table table-bordered invoice-summary-table">
            
                <thead>
    <tr>
        <th>Invoice ID</th>
        <th>Customer Name</th>
        <th>Customer Address</th>
        <th>Invoice Date</th>
        <th>Product Details</th>
        <th>Subtotal</th>
        <th>Discount Percentage</th>
        <th>Total Amount</th>
        <th>Action</th>
    </tr>
</thead>

<tbody>
    <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td data-label="Invoice ID">#<?php echo (int) $row['invoice_id']; ?></td>
                <td data-label="Customer"><?php echo htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td data-label="Address"><?php echo htmlspecialchars($row['customer_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td data-label="Invoice date"><?php echo date("d/m/Y", strtotime($row['invoice_date'])); ?></td>
                <td data-label="Products" class="invoice-product-details"><?php echo str_replace('&lt;br&gt;', '<br>', htmlspecialchars($row['product_details'] ?? 'No items', ENT_QUOTES, 'UTF-8')); ?></td>
                <td data-label="Subtotal" class="invoice-amount">₹<?php echo number_format($row['subtotal'], 2); ?></td>
                <td data-label="Discount"><?php echo number_format($row['discount_percentage'], 2); ?>%</td>
                <td data-label="Total" class="invoice-amount"><strong>₹<?php echo number_format($row['total_amount'], 2); ?></strong></td>
                <td class="action-icons invoice-actions-cell">
                    <a href="view_invoice.php?id=<?php echo $row['invoice_id']; ?>" title="View Invoice">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="edit_invoice.php?id=<?php echo $row['invoice_id']; ?>" title="Edit Invoice">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="delete_invoice.php" method="POST" style="display:inline;">
                        <input type="hidden" name="invoice_id" value="<?php echo $row['invoice_id']; ?>">
                        <button type="submit" class="btn btn-link text-danger" onclick="return confirm('Are you sure you want to delete this invoice?');" title="Delete Invoice" style="border: none; background: none; padding: 0;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
        
            <td colspan="10">No invoices found.</td>
        </tr>
    <?php endif; ?>
</tbody>
</table>
    </div>

<nav class="summary-pagination" aria-label="Invoice pages">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?><?php if (!empty($from_date)) echo '&from_date=' . urlencode($from_date); ?><?php if (!empty($to_date)) echo '&to_date=' . urlencode($to_date); ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?><?php if (!empty($from_date)) echo '&from_date=' . urlencode($from_date); ?><?php if (!empty($to_date)) echo '&to_date=' . urlencode($to_date); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php if (!empty($search)) echo '&search=' . urlencode($search); ?><?php if (!empty($from_date)) echo '&from_date=' . urlencode($from_date); ?><?php if (!empty($to_date)) echo '&to_date=' . urlencode($to_date); ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </main>

   

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(document).ready(function() {
    $('#search').on('keyup', function() {
        var searchTerm = $(this).val();
        
        if (searchTerm.length > 2) { 
            $.ajax({
                url: 'suggest.php',
                type: 'GET',
                data: { term: searchTerm },
                success: function(data) {
                    var suggestions = JSON.parse(data);
                    var suggestionBox = $('#suggestions');
                    
                    if (suggestions.length > 0) {
                        suggestionBox.empty().show();
                        
                        suggestions.forEach(function(suggestion) {
                            suggestionBox.append('<div class="suggestion-item">' + suggestion + '</div>');
                        });
                    } else {
                        suggestionBox.hide();
                    }
                }
            });
        } else {
            $('#suggestions').hide();
        }
    });
    

    $(document).on('click', '.suggestion-item', function() {
        $('#search').val($(this).text());
        $('#suggestions').hide();
    });
    
    
    $(document).click(function(e) {
        if (!$(e.target).closest('#search').length) {
            $('#suggestions').hide();
        }
    });
});
</script>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
        $(function() {
            $("#from_date").datepicker({ dateFormat: 'yy-mm-dd' });
            $("#to_date").datepicker({ dateFormat: 'yy-mm-dd' });
        });
    </script>
    <script src="Assets/js/sweetalert2.all.min.js"></script>

    
</body>
</html>
