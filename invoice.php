<?php
require('fpdf186/fpdf.php');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "crackersbilling";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdf_requested = isset($_GET['pdf']) && $_GET['pdf'] === 'true';

if ($invoice_id > 0) {
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

    
    $subtotal = 0;
    $discount_percentage = $invoice['discount_percentage'] ?? 0; 
    $discount_amount = 0;
    $total_amount = 0;

    if ($pdf_requested) {
        $pdf = new FPDF('L', 'mm', 'A5');
        $pdf->SetMargins(8, 6, 8);
        $pdf->SetAutoPageBreak(true, 6);
        $pdf->AddPage();

        $pageWidth = $pdf->GetPageWidth();
        $contentWidth = $pageWidth - 16;
        $columnWidth = $contentWidth / 2;
        $logoPath = 'Assets/images/logo1.png';
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, $pageWidth - 29, 7, 20);
        }

        $pdf->SetFont('Courier', 'B', 13);
        $pdf->Cell(0, 7, 'Yogesh Fire Crackers', 0, 1, 'C');
        $pdf->SetFont('Courier', '', 8);
        $pdf->Cell(0, 5, 'Kamrajar 4th street, Chinna Chokkikulam', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Opposite to Greentrends, Madurai, Tamil Nadu 625001', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Phone: 90033 60242', 0, 1, 'C');
        $pdf->Ln(2);

        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Line(8, $pdf->GetY(), $pageWidth - 8, $pdf->GetY());
        $pdf->Ln(2);

        $pdf->SetFont('Courier', 'B', 9);
        $pdf->Cell($columnWidth, 5, 'Invoice Details', 0, 0);
        $pdf->Cell($columnWidth, 5, 'Customer Information', 0, 1);

        $detailsX = $pdf->GetX();
        $detailsY = $pdf->GetY();
        $pdf->SetFont('Courier', '', 8);
        $pdf->MultiCell(
            $columnWidth,
            4.5,
            'Invoice Number: #' . $invoice['invoice_id'] . "\nInvoice Date: " . date('d/m/Y', strtotime($invoice['invoice_date'])),
            0,
            'L'
        );
        $leftBottom = $pdf->GetY();

        $pdf->SetXY($detailsX + $columnWidth, $detailsY);
        $pdf->MultiCell(
            $columnWidth,
            4.5,
            'Customer: ' . pdfText($invoice['customer_name']) . "\nAddress: " . pdfText($invoice['customer_address']),
            0,
            'L'
        );
        $pdf->SetY(max($leftBottom, $pdf->GetY()) + 2);

        $widths = [10, 70, 18, 23, 35, 38];
        $headers = ['S.No', 'Product Name', 'Size', 'Quantity', 'Price (Rs.)', 'Total (Rs.)'];
        $pdf->SetFont('Courier', 'B', 8);
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 7, $header, 1, $index === count($headers) - 1 ? 1 : 0, 'C');
        }

        $i = 1;
        while ($item = $items_result->fetch_assoc()) {
            $item_total = $item['quantity'] * $item['price'];
            $subtotal += $item_total;

            $pdf->SetFont('Courier', '', 8);
            $pdf->Cell($widths[0], 7, $i++, 1, 0, 'C');
            $pdf->Cell($widths[1], 7, pdfText($item['product_name']), 1, 0, 'L');
            $pdf->Cell($widths[2], 7, pdfText($item['product_size']), 1, 0, 'C');
            $pdf->Cell($widths[3], 7, $item['quantity'], 1, 0, 'C');
            $pdf->Cell($widths[4], 7, number_format($item['price'], 2), 1, 0, 'R');
            $pdf->Cell($widths[5], 7, number_format($item_total, 2), 1, 1, 'R');
        }

     
        $discount_amount = ($subtotal * $discount_percentage / 100);
        $subtotal_after_discount = $subtotal - $discount_amount;
        $total_amount = $subtotal_after_discount;

        
        $pdf->Ln(2);
        $summaryWidth = $contentWidth - 45;
        $valueWidth = 45;
        $pdf->SetFont('Courier', '', 8);
        $pdf->Cell($summaryWidth, 6, 'Subtotal (Rs.):', 1);
        $pdf->Cell($valueWidth, 6, number_format($subtotal, 2), 1, 1, 'R');
        $pdf->Cell($summaryWidth, 6, 'Discount (' . number_format($discount_percentage, 2) . '%):', 1);
        $pdf->Cell($valueWidth, 6, number_format($discount_amount, 2), 1, 1, 'R');
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Cell($summaryWidth, 6, 'Total Amount (Rs.):', 1);
        $pdf->Cell($valueWidth, 6, number_format($total_amount, 2), 1, 1, 'R');

        $pdf->Ln(3);
        $pdf->SetFont('Courier', 'I', 7);
        $pdf->Cell(0, 5, 'Terms and conditions apply.', 0, 1, 'C');

        $pdf->Output('I', 'invoice_' . $invoice_id . '.pdf');
        exit; 
    }
} else {
    echo "Invalid Invoice ID.";
}

$conn->close();

function pdfText($value)
{
    $value = str_replace('₹', 'Rs.', (string) $value);
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            return $converted;
        }
    }

    return preg_replace('/[^\x20-\x7E]/', '', $value);
}
?>
