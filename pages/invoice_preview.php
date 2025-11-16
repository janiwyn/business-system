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
$invoice_no = 'INV-000' . $inv4;

$date = date('M d, Y');

// Company details (customize as needed)
$company_name = "Zylisor Thread & Weave";
$company_tagline = "Life Redefining Sales";
$company_address = "Kinton Town Fabricae";
$company_city = "New York Sales 1207";
$company_tin = "1234";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice <?= htmlspecialchars($invoice_no) ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            padding:2rem; 
            background:#f5f5f5; 
            color:#333;
        }
        .invoice-container { 
            max-width:800px; 
            margin:0 auto; 
            background:#fff; 
            border-radius:12px; 
            box-shadow:0 4px 20px rgba(0,0,0,0.1);
            overflow:hidden;
        }
        .invoice-header { 
            background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:#fff;
            padding:2rem 2.5rem;
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
        }
        .company-info { flex:1; }
        .company-logo { 
            width:80px; 
            height:80px; 
            background:#fff; 
            border-radius:50%; 
            display:flex; 
            align-items:center; 
            justify-content:center;
            font-size:2.5rem;
            font-weight:bold;
            color:#667eea;
            margin-bottom:1rem;
        }
        .company-name { 
            font-size:1.8rem; 
            font-weight:700; 
            margin-bottom:0.3rem;
        }
        .company-tagline { 
            font-size:0.95rem; 
            opacity:0.9;
            margin-bottom:0.8rem;
        }
        .company-address { 
            font-size:0.9rem; 
            line-height:1.6;
            opacity:0.85;
        }
        .invoice-title-section { 
            text-align:right;
        }
        .invoice-title { 
            font-size:2.5rem; 
            font-weight:700; 
            margin-bottom:0.5rem;
        }
        .invoice-number { 
            font-size:1rem; 
            opacity:0.9;
        }
        .invoice-body { 
            padding:2.5rem;
        }
        .invoice-meta { 
            display:flex; 
            justify-content:space-between; 
            margin-bottom:2.5rem;
            padding-bottom:1.5rem;
            border-bottom:2px solid #f0f0f0;
        }
        .bill-to, .invoice-details { flex:1; }
        .bill-to strong, .invoice-details strong { 
            display:block; 
            font-size:0.85rem; 
            text-transform:uppercase; 
            letter-spacing:0.5px;
            color:#667eea; 
            margin-bottom:0.8rem;
        }
        .bill-to p, .invoice-details p { 
            margin-bottom:0.4rem; 
            font-size:0.95rem;
            color:#555;
        }
        table { 
            width:100%; 
            border-collapse:collapse; 
            margin-bottom:2rem;
        }
        thead { 
            background:#f8f9fa;
        }
        th { 
            padding:1rem; 
            text-align:left; 
            font-size:0.85rem; 
            text-transform:uppercase; 
            letter-spacing:0.5px;
            color:#667eea;
            font-weight:600;
            border-bottom:2px solid #e0e0e0;
        }
        th.text-right { text-align:right; }
        td { 
            padding:1rem; 
            border-bottom:1px solid #f0f0f0;
            font-size:0.95rem;
            color:#555;
        }
        td.text-right { text-align:right; }
        tbody tr:hover { background:#fafbfc; }
        .item-name { 
            font-weight:600; 
            color:#333;
        }
        .summary-table { 
            margin-left:auto; 
            width:350px;
            border:none;
        }
        .summary-table td { 
            border:none; 
            padding:0.5rem 0;
        }
        .summary-row { 
            font-size:0.95rem;
        }
        .total-row { 
            font-size:1.3rem; 
            font-weight:700; 
            color:#667eea;
            padding-top:1rem !important;
            border-top:2px solid #e0e0e0 !important;
        }
        .balance-due-row {
            font-size:1.4rem;
            font-weight:700;
            color:#e74c3c;
            padding-top:0.5rem !important;
        }
        .invoice-footer { 
            text-align:center; 
            padding:1.5rem 2.5rem;
            background:#f8f9fa;
            border-top:1px solid #e0e0e0;
        }
        .invoice-footer strong { 
            display:block; 
            margin-bottom:0.5rem; 
            color:#667eea;
            font-size:1rem;
        }
        .invoice-footer p { 
            font-size:0.9rem; 
            color:#777; 
            line-height:1.6;
        }
        .print-btn { 
            display:block; 
            margin:2rem auto; 
            padding:0.9rem 2.5rem; 
            background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:#fff; 
            border:none; 
            border-radius:8px; 
            font-size:1.05rem; 
            font-weight:600;
            cursor:pointer; 
            box-shadow:0 4px 15px rgba(102,126,234,0.3);
            transition:transform 0.2s, box-shadow 0.2s;
        }
        .print-btn:hover { 
            transform:translateY(-2px);
            box-shadow:0 6px 20px rgba(102,126,234,0.4);
        }
        @media print { 
            .print-btn { display:none; } 
            body { background:#fff; padding:0; }
            .invoice-container { box-shadow:none; border-radius:0; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="company-info">
                <div class="company-logo">Z</div>
                <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
                <div class="company-tagline"><?= htmlspecialchars($company_tagline) ?></div>
                <div class="company-address">
                    <?= htmlspecialchars($company_address) ?><br>
                    <?= htmlspecialchars($company_city) ?><br>
                    TIN: <?= htmlspecialchars($company_tin) ?>
                </div>
            </div>
            <div class="invoice-title-section">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number"><?= htmlspecialchars($invoice_no) ?></div>
            </div>
        </div>

        <!-- Body -->
        <div class="invoice-body">
            <!-- Meta Info -->
            <div class="invoice-meta">
                <div class="bill-to">
                    <strong>Bill To:</strong>
                    <p><?= htmlspecialchars($customer_name) ?></p>
                </div>
                <div class="invoice-details">
                    <strong>Invoice Date:</strong>
                    <p><?= $date ?></p>
                    <strong style="margin-top:1rem;">Due Date:</strong>
                    <p><?= date('M d, Y', strtotime('+30 days')) ?></p>
                </div>
            </div>

            <!-- Items Table -->
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
                        <td>
                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <small style="color:#999;">SKU: <?= $item['id'] ?></small>
                        </td>
                        <td class="text-right"><?= $item['quantity'] ?></td>
                        <td class="text-right">UGX <?= number_format($item['price'], 2) ?></td>
                        <td class="text-right">UGX <?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Summary -->
            <table class="summary-table">
                <tr class="summary-row">
                    <td>Sub Total</td>
                    <td class="text-right">UGX <?= number_format($total, 2) ?></td>
                </tr>
                <tr class="summary-row">
                    <td>Tax Rate</td>
                    <td class="text-right">0.00%</td>
                </tr>
                <tr class="total-row">
                    <td>Total</td>
                    <td class="text-right">UGX <?= number_format($total, 2) ?></td>
                </tr>
                <tr class="balance-due-row">
                    <td>Balance Due</td>
                    <td class="text-right">UGX <?= number_format($total, 2) ?></td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="invoice-footer">
            <strong>Terms & Conditions</strong>
            <p>Full payment is due upon receipt of this invoice. Late payments may incur additional charges as per the applicable laws. Thank you for your business!</p>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">
        <i class="fa fa-print"></i> Print Invoice
    </button>
</body>
</html>
