<?php
// Simple receipt preview (same contract as invoice_preview.php)

$cartJson      = $_POST['cart'] ?? '[]';
$total         = $_POST['total'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$amountPaid    = $_POST['amount_paid'] ?? '';
$balance       = $_POST['balance'] ?? '';
$invoiceNo     = $_POST['invoice_no'] ?? ($_POST['receipt_no'] ?? '');
$customerName  = $_POST['customer_name'] ?? '';
$customerEmail = $_POST['customer_email'] ?? '';
$customerContact = $_POST['customer_contact'] ?? '';
$dueDate       = $_POST['due_date'] ?? '';

$cart = json_decode(is_string($cartJson) ? $cartJson : json_encode($cartJson), true);
if (!is_array($cart)) $cart = [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt Preview <?= h($invoiceNo) ?></title>
<style>
  /* reuse minimal styles from invoice_preview */
  body{font-family:Arial;margin:18px;color:#222}
  .receipt{max-width:700px;margin:0 auto;border:1px solid #eaeaea;padding:14px;border-radius:8px}
  .header{display:flex;justify-content:space-between;align-items:center}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{padding:8px;border-bottom:1px solid #f2f2f2}
  .text-right{ text-align:right; }
  .btns{margin-top:12px;text-align:right}
  .btn{padding:8px 12px;border-radius:5px;border:1px solid #ccc;background:#fff;cursor:pointer}
  .btn-print{background:#0b6;color:#fff;border:none}
  @media print{ .btns{display:none} body{margin:0} }
</style>
</head>
<body>
  <div class="receipt">
    <div class="header">
      <div>
        <strong>Company Name</strong><br><small>Receipt</small>
      </div>
      <div>
        <small>Ref: <?= h($invoiceNo ?: '—') ?></small><br>
        <small><?= date('d M Y, H:i') ?></small>
      </div>
    </div>

    <div style="margin-top:10px">
      <strong>Customer:</strong> <?= h($customerName ?: 'Walk-in') ?><br>
      <?php if ($customerContact): ?><?= h($customerContact) ?><br><?php endif; ?>
      <?php if ($customerEmail): ?><?= h($customerEmail) ?><br><?php endif; ?>
    </div>

    <table>
      <thead><tr><th>Item</th><th>Qty</th><th class="text-right">Unit</th><th class="text-right">Subtotal</th></tr></thead>
      <tbody>
        <?php if (empty($cart)): ?>
          <tr><td colspan="4" class="text-right">No items</td></tr>
        <?php else: foreach($cart as $it):
            $name = $it['product_name'] ?? $it['name'] ?? 'Item';
            $qty = $it['quantity'] ?? ($it['qty'] ?? 1);
            $unit = isset($it['unit_price']) ? number_format((float)$it['unit_price'],2) : '-';
            $sub = isset($it['unit_price']) ? number_format(((float)$it['unit_price'])*((int)$qty),2) : '-';
        ?>
          <tr>
            <td><?= h($name) ?></td>
            <td><?= h($qty) ?></td>
            <td class="text-right">UGX <?= h($unit) ?></td>
            <td class="text-right">UGX <?= h($sub) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <div style="margin-top:8px">
      <table style="width:100%">
        <tr><td style="width:70%"></td><td>Subtotal:</td><td class="text-right">UGX <?= h(number_format((float)$total,2)) ?></td></tr>
        <tr><td></td><td>Paid:</td><td class="text-right">UGX <?= h(number_format((float)$amountPaid,2)) ?></td></tr>
        <tr><td></td><td><strong>Balance:</strong></td><td class="text-right"><strong>UGX <?= h(number_format((float)$balance,2)) ?></strong></td></tr>
      </table>
    </div>

    <div class="btns">
      <button class="btn" onclick="window.close()">Close</button>
      <button class="btn btn-print" onclick="window.print()">Print Receipt</button>
    </div>
  </div>
</body>
</html>
