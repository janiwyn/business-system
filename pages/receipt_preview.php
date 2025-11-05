<?php
// Accept POST data
$cart = json_decode($_POST['cart'] ?? '[]', true);
$total = floatval($_POST['total'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'Cash';
$amount_paid = floatval($_POST['amount_paid'] ?? 0);

// Company info (same as printCartReceipt)
$company = "CYINIBEL SUPERMARKET LIMITED";
$till = "2";
$tillSales = "050520250601106";
$tin = "1017004561";
$dateStr = date('Y-m-d H:i:s');

// Build items HTML
$itemsHtml = '';
foreach ($cart as $item) {
    $qty = intval($item['quantity']);
    $name = htmlspecialchars($item['name']);
    $subtotal = number_format($item['price'] * $qty, 0);
    $itemsHtml .= "<tr>
        <td style='text-align:left;'>$qty</td>
        <td style='text-align:left;'>$name</td>
        <td style='text-align:right;'>UGX $subtotal</td>
    </tr>";
}
$change = $amount_paid - $total;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt Preview</title>
    <style>
        body { background: #f8f9fa; font-family: 'Courier New', monospace; }
        #receiptToPrint { width:320px; max-width:100vw; margin:2rem auto; background:#fff; padding:1rem 1.5rem; border-radius:12px; box-shadow:0 4px 24px #0002; }
        .print-btn { display:block; margin:2rem auto 0 auto; padding:0.7rem 2.5rem; font-size:1.1rem; background:#1abc9c; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer; box-shadow:0 2px 8px #0002; }
        @media print { .print-btn { display:none; } #receiptToPrint { box-shadow:none; border-radius:0; padding:0.5rem; } body { background:#fff; } }
    </style>
</head>
<body>
    <div id="receiptToPrint">
        <div style="text-align:center;margin-top:10px;">
            <img src="../uploads/1 (1).png" alt="Logo" style="width:80px;height:80px;object-fit:contain;margin-bottom:8px;">
        </div>
        <div style="text-align:center;font-weight:bold;font-size:15px;margin-bottom:2px;"><?= $company ?></div>
        <div style="text-align:center;font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
        <div style="text-align:center;font-size:13px;margin-bottom:2px;"><?= $dateStr ?></div>
        <div style="font-size:12px;margin-bottom:2px;">TILL: <?= $till ?> &nbsp; Till Sales: <?= $tillSales ?></div>
        <div style="font-size:12px;margin-bottom:2px;">TIN: <?= $tin ?></div>
        <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
        <table style="width:100%;font-size:13px;margin-bottom:2px;border-collapse:collapse;">
            <tbody>
                <?= $itemsHtml ?>
            </tbody>
        </table>
        <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
        <table style="width:100%;font-size:13px;">
            <tr>
                <td style="text-align:left;">Subtotal</td>
                <td style="text-align:right;">UGX <?= number_format($total, 0) ?></td>
            </tr>
            <tr>
                <td style="text-align:left;">Total</td>
                <td style="text-align:right;">UGX <?= number_format($total, 0) ?></td>
            </tr>
        </table>
        <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
        <table style="width:100%;font-size:13px;">
            <tr>
                <td style="text-align:left;">Cash</td>
                <td style="text-align:right;">UGX <?= number_format($amount_paid, 0) ?></td>
            </tr>
            <tr>
                <td style="text-align:left;">Change</td>
                <td style="text-align:right;">UGX <?= number_format($change, 0) ?></td>
            </tr>
        </table>
        <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
        <div style="text-align:center;font-size:13px;margin:10px 0 2px 0;">THANK YOU</div>
        <div style="text-align:center;font-size:13px;margin-bottom:8px;">HAVE A NICE DAY</div>
        <div style="text-align:center;margin-top:8px;">
            <svg id="barcodeSvg" style="width:180px;height:40px;"></svg>
        </div>
    </div>
    <button class="print-btn" onclick="window.print()">Print Receipt</button>
    <script>
    // Simple barcode SVG generator (Code128, dummy bars for visual)
    (function() {
        var svg = document.getElementById('barcodeSvg');
        if (svg) {
            var code = "<?= $tillSales ?>";
            var bars = '';
            var x = 0;
            for (var i = 0; i < code.length; i++) {
                var val = code.charCodeAt(i) % 7 + 1;
                for (var j = 0; j < val; j++) {
                    bars += '<rect x="'+x+'" y="0" width="2" height="40" fill="#000"/>';
                    x += 3;
                }
                x += 2;
            }
            svg.innerHTML = bars;
        }
    })();
    </script>
</body>
</html>
