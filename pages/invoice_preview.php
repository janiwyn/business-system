<?php
// Invoice preview page - generates invoice number and displays invoice
$cart = json_decode($_POST['cart'] ?? '[]', true);
$total = floatval($_POST['total'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'Customer File';
$customer_id = intval($_POST['customer_id'] ?? 0);
$customer_name = $_POST['customer_name'] ?? 'Unknown Customer';

// Generate invoice number
try {
    $inv4 = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
} catch (Throwable $e) {
    $inv4 = str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
}
$invoice_no = 'INV-' . $inv4;

$date = date('M d, Y');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice <?= htmlspecialchars($invoice_no) ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, sans-serif; padding:2rem; background:#f5f5f5; }
        .invoice-container { max-width:800px; margin:0 auto; background:#fff; padding:2rem; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        .invoice-header { text-align:center; margin-bottom:2rem; padding-bottom:1rem; border-bottom:2px solid #1abc9c; }
        .invoice-header h1 { color:#1abc9c; font-size:2rem; }
        .invoice-info { display:flex; justify-content:space-between; margin-bottom:2rem; }
        .invoice-info div { flex:1; }
        .invoice-info strong { display:block; margin-bottom:0.5rem; color:#1abc9c; }
        table { width:100%; border-collapse:collapse; margin-bottom:2rem; }
        th, td { padding:0.75rem; text-align:left; border-bottom:1px solid #e0e0e0; }
        th { background:#1abc9c; color:#fff; font-weight:600; }
        .text-right { text-align:right; }
        .total-row td { font-weight:bold; font-size:1.1rem; padding-top:1rem; }
        .balance-due { color:#e74c3c; font-size:1.3rem; }
        .print-btn { display:block; margin:1rem auto; padding:0.75rem 2rem; background:#1abc9c; color:#fff; border:none; border-radius:6px; font-size:1rem; cursor:pointer; }
        @media print { .print-btn { display:none; } }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1>INVOICE</h1>
            <p><?= htmlspecialchars($invoice_no) ?></p>
        </div>

        <div class="invoice-info">
            <div>
                <strong>Bill To:</strong>
                <p><?= htmlspecialchars($customer_name) ?></p>
            </div>
            <div>
                <strong>Invoice Date:</strong>
                <p><?= $date ?></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item & Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                foreach ($cart as $item): 
                    $subtotal = $item['price'] * $item['quantity'];
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td class="text-right"><?= $item['quantity'] ?></td>
                    <td class="text-right">UGX <?= number_format($item['price'], 2) ?></td>
                    <td class="text-right">UGX <?= number_format($subtotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table>
            <tr class="total-row">
                <td colspan="4" class="text-right">Sub Total</td>
                <td class="text-right">UGX <?= number_format($total, 2) ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="text-right">Tax Rate</td>
                <td class="text-right">0.00%</td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="text-right">Total</td>
                <td class="text-right">UGX <?= number_format($total, 2) ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="text-right balance-due">Balance Due</td>
                <td class="text-right balance-due">UGX <?= number_format($total, 2) ?></td>
            </tr>
        </table>

        <div style="text-align:center; margin-top:2rem; padding-top:1rem; border-top:1px solid #e0e0e0;">
            <p><strong>Terms & Conditions</strong></p>
            <p style="color:#666;">Full payment is due upon receipt of this invoice. Late payments may incur additional charges as per the applicable laws.</p>
        </div>

        <button class="print-btn" onclick="window.print()">Print Invoice</button>
    </div>
</body>
</html>
