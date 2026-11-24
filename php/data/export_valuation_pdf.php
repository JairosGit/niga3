<?php
// php/data/export_valuation_pdf.php
session_start();

// Protect route so only logged-in users can export PDFs
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Location: ../../login.html');
    exit;
}

require_once __DIR__ . '/../core/connection.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// =====================================================================
// TODO: Replace with your friend's SELECT query from the products table
// =====================================================================
/*
$stmt = $pdo->query("SELECT sku, name, category, unit_price, stock, min_stock FROM YOUR_PRODUCTS_TABLE ORDER BY sku ASC");
$products = $stmt->fetchAll();
*/

// Temporary fallback data so the PDF downloads immediately while testing
$products = [
    ['sku' => 'ELEC-101', 'name' => 'Wireless Barcode Scanner', 'category' => 'Electronics', 'unit_price' => 65.00, 'stock' => 3, 'min_stock' => 10],
    ['sku' => 'OFF-204', 'name' => 'Thermal Shipping Labels (4x6)', 'category' => 'Office Supplies', 'unit_price' => 22.50, 'stock' => 64, 'min_stock' => 20],
    ['sku' => 'HRD-309', 'name' => 'Heavy-Duty Pallet Jack Wheel Kit', 'category' => 'Hardware', 'unit_price' => 85.00, 'stock' => 3, 'min_stock' => 6],
    ['sku' => 'ELEC-118', 'name' => 'Industrial Label Printer', 'category' => 'Electronics', 'unit_price' => 260.00, 'stock' => 5, 'min_stock' => 3],
];
// =====================================================================

// Calculate totals
$totalProducts = count($products);
$totalValuation = 0;
$lowStockCount = 0;
$rowsHtml = '';

foreach ($products as $item) {
    $subtotal = $item['unit_price'] * $item['stock'];
    $totalValuation += $subtotal;
    $isLow = $item['stock'] <= $item['min_stock'];
    if ($isLow) {
        $lowStockCount++;
    }

    $rowClass = $isLow ? ' class="low-stock"' : '';
    $statusBadge = $isLow
        ? '<span class="badge-danger">Low Stock</span>'
        : '<span class="badge-ok">In Stock</span>';

    $rowsHtml .= "
        <tr{$rowClass}>
            <td class='mono'>{$item['sku']}</td>
            <td>{$item['name']}</td>
            <td>{$item['category']}</td>
            <td class='right'>$" . number_format($item['unit_price'], 2) . "</td>
            <td class='center'>{$item['stock']} (Min: {$item['min_stock']})</td>
            <td class='right bold'>$" . number_format($subtotal, 2) . "</td>
            <td class='center'>{$statusBadge}</td>
        </tr>
    ";
}

$generatedBy = htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$generatedAt = date('Y-m-d H:i:s');

// Build PDF HTML Template
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #2c3e50; }
        .header { background-color: #2B4C7E; color: #ffffff; padding: 18px 22px; border-bottom: 4px solid #F5A623; }
        .header h1 { margin: 0; font-size: 20px; letter-spacing: 0.5px; }
        .header p { margin: 4px 0 0; font-size: 11px; color: #dce3f0; }
        .summary { width: 100%; margin: 18px 0; border-collapse: collapse; }
        .summary td { width: 33.33%; background: #F6F8FC; border: 1px solid #DCE3F0; padding: 12px; }
        .summary .label { font-size: 10px; text-transform: uppercase; color: #6C7A89; }
        .summary .value { font-size: 16px; font-weight: bold; color: #2B4C7E; margin-top: 4px; }
        table.catalog { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.catalog th { background-color: #14A2B8; color: #ffffff; text-align: left; padding: 8px; font-size: 11px; }
        table.catalog td { border-bottom: 1px solid #DCE3F0; padding: 8px; font-size: 11px; }
        tr.low-stock { background-color: #FFF5F5; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .mono { font-family: monospace; font-weight: bold; color: #2B4C7E; }
        .badge-danger { color: #b02a37; font-weight: bold; }
        .badge-ok { color: #146c43; font-weight: bold; }
        .footer { margin-top: 24px; font-size: 10px; color: #6C7A89; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>WAREHOUSE MANAGEMENT — INVENTORY VALUATION REPORT</h1>
        <p>Generated on ' . $generatedAt . ' by ' . $generatedBy . '</p>
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Total Products</div>
                <div class="value">' . $totalProducts . '</div>
            </td>
            <td>
                <div class="label">Total Inventory Valuation</div>
                <div class="value" style="color: #D98E14;">$' . number_format($totalValuation, 2) . '</div>
            </td>
            <td>
                <div class="label">Low-Stock Alerts</div>
                <div class="value" style="color: #DC3545;">' . $lowStockCount . '</div>
            </td>
        </tr>
    </table>

    <table class="catalog">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product Name</th>
                <th>Category</th>
                <th class="right">Unit Price</th>
                <th class="center">Stock</th>
                <th class="right">Subtotal</th>
                <th class="center">Status</th>
            </tr>
        </thead>
        <tbody>' . $rowsHtml . '</tbody>
    </table>

    <div class="footer">Warehouse Management System • Official Valuation Summary</div>
</body>
</html>
';

// Initialize and render Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Stream PDF directly as a download
$filename = 'Inventory_Valuation_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;