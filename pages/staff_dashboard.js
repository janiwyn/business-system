document.addEventListener('DOMContentLoaded', function() {
    // FIX: define escapeHtml before any template rendering (invoice preview uses it)
    if (typeof window.escapeHtml !== 'function') {
        window.escapeHtml = function(s){
            return s ? String(s).replace(/[&<>"']/g, c => (
                {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]
            )) : '';
        };
    }

    // Cart logic
    let cart = [];

    // expose product/customer data (productData may already be defined elsewhere)
    const productData = window.productData || {};
    const customers = window.customers || [];
    // Helper: find customer by id
    function getCustomerById(id) {
        const cid = String(id);
        return customers.find(c => String(c.id) === cid);
    }

    // Hidden form for submitting cart to PHP (ensure customer_id & payment_method included)
    const hiddenSaleForm = document.createElement('form');
    hiddenSaleForm.method = 'POST';
    hiddenSaleForm.style.display = 'none';
    hiddenSaleForm.innerHTML = `
        <input type="hidden" name="cart_data" id="cart_data">
        <input type="hidden" name="amount_paid" id="cart_amount_paid">
        <input type="hidden" name="submit_cart" value="1">
        <input type="hidden" name="payment_method" id="hidden_payment_method">
        <input type="hidden" name="customer_id" id="hidden_customer_id">
    `;
    document.body.appendChild(hiddenSaleForm);

    // Toggle customer select / amount_paid when payment method changes
    document.getElementById('payment_method').addEventListener('change', function() {
        const pm = this.value;
        const wrap = document.getElementById('customer_select_wrap');
        const amt = document.getElementById('amount_paid');
        if (pm === 'Customer File') {
            wrap.style.display = '';
            amt.value = '';
            amt.disabled = true;
            amt.closest('.col-md-4').style.opacity = 0.6;
        } else {
            wrap.style.display = 'none';
            amt.disabled = false;
            amt.closest('.col-md-4').style.opacity = 1;
        }
    });

    // Ensure initial state
    document.getElementById('payment_method').dispatchEvent(new Event('change'));

    // --- Receipt Confirmation Modal ---
    // Only create ONCE at the top
    const receiptConfirmModal = document.createElement('div');
    receiptConfirmModal.id = 'receiptConfirmModal';
    receiptConfirmModal.style.display = 'none';
    receiptConfirmModal.innerHTML = `
  <div style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);z-index:9999;display:flex;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:2rem 2.5rem;border-radius:10px;box-shadow:0 2px 16px #0002;max-width:95vw;">
      <div style="font-size:1.2rem;margin-bottom:1rem;">Print receipt for this sale?</div>
      <div class="d-flex flex-wrap gap-2 justify-content-end" style="flex-wrap:wrap;">
        <button id="receiptConfirmCancel" class="btn btn-secondary">Cancel</button>
        <button id="receiptConfirmRecord" class="btn btn-warning">Record</button>
        <button id="receiptConfirmOk" class="btn btn-primary">OK</button>
      </div>
    </div>
  </div>
`;
    document.body.appendChild(receiptConfirmModal);

    // Only attach event listeners before showing
    function showReceiptConfirmModal(cb) {
        receiptConfirmModal.style.display = '';
        document.getElementById('receiptConfirmOk').onclick = function() {
            receiptConfirmModal.style.display = 'none';
            cb('ok');
        };
        document.getElementById('receiptConfirmCancel').onclick = function() {
            receiptConfirmModal.style.display = 'none';
            cb('cancel');
        };
        document.getElementById('receiptConfirmRecord').onclick = function() {
            receiptConfirmModal.style.display = 'none';
            cb('record');
        };
    }

    // NEW: Invoice Confirmation Modal (Customer File insufficient funds)
    const invoiceConfirmModal = document.createElement('div');
    invoiceConfirmModal.id = 'invoiceConfirmModal';
    invoiceConfirmModal.style.display = 'none';
    invoiceConfirmModal.innerHTML = `
  <div style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);z-index:9999;display:flex;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:2rem 2.5rem;border-radius:10px;box-shadow:0 2px 16px #0002;max-width:95vw;">
      <div style="font-size:1.2rem;margin-bottom:1rem;">Generate invoice for this Customer File sale?</div>
      <div class="d-flex flex-wrap gap-2 justify-content-end" style="flex-wrap:wrap;">
        <button id="invoiceConfirmCancel" class="btn btn-secondary">Cancel</button>
        <button id="invoiceConfirmRecord" class="btn btn-warning">Record</button>
        <button id="invoiceConfirmOk" class="btn btn-primary">OK</button>
      </div>
    </div>
  </div>
`;
    document.body.appendChild(invoiceConfirmModal);

    function showInvoiceConfirmModal(cb) {
        invoiceConfirmModal.style.display = '';
        document.getElementById('invoiceConfirmOk').onclick = function() {
            invoiceConfirmModal.style.display = 'none';
            cb('ok');
        };
        document.getElementById('invoiceConfirmCancel').onclick = function() {
            invoiceConfirmModal.style.display = 'none';
            cb('cancel');
        };
        document.getElementById('invoiceConfirmRecord').onclick = function() {
            invoiceConfirmModal.style.display = 'none';
            cb('record');
        };
    }

    // --- Cart Receipt Print Function (Supermarket Style) ---
    function printCartReceipt(cart, total, paymentMethod, amountPaid) {
        // --- Supermarket style receipt ---
        // You can adjust the logo, company, and barcode as needed.
        const now = new Date();
        const dateStr = now.toLocaleString();
        // Company info
        const company = "CYINIBEL SUPERMARKET LIMITED";
        const till = "2";
        const tillSales = "050520250601106";
        const tin = "1017004561";
        // Items
        let itemsHtml = '';
        cart.forEach((item, idx) => {
            itemsHtml += `
            <tr>
                <td style="text-align:left;">${item.quantity}</td>
                <td style="text-align:left;">${item.name}</td>
                <td style="text-align:right;">UGX ${Number(item.price * item.quantity).toLocaleString()}</td>
            </tr>`;
        });
        // Barcode (dummy, you can generate real barcode if needed)
        const barcode = tillSales;
        // Receipt HTML
        const receiptHtml = `
<div id="receiptToPrint" style="width:320px;max-width:100vw;padding:0 0 0 0;font-family:'Courier New',monospace;">
    <div style="text-align:center;margin-top:10px;">
        <img src="https://i.ibb.co/6w1yQnQ/cyinibel-logo.png" alt="Logo" style="width:80px;height:80px;object-fit:contain;margin-bottom:8px;">
    </div>
    <div style="text-align:center;font-weight:bold;font-size:15px;margin-bottom:2px;">${company}</div>
    <div style="text-align:center;font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <div style="text-align:center;font-size:13px;margin-bottom:2px;">${dateStr}</div>
    <div style="font-size:12px;margin-bottom:2px;">TILL: ${till} &nbsp; Till Sales: ${tillSales}</div>
    <div style="font-size:12px;margin-bottom:2px;">TIN: ${tin}</div>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <table style="width:100%;font-size:13px;margin-bottom:2px;border-collapse:collapse;">
        <tbody>
            ${itemsHtml}
        </tbody>
    </table>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <table style="width:100%;font-size:13px;">
        <tr>
            <td style="text-align:left;">Subtotal</td>
            <td style="text-align:right;">UGX ${Number(total).toLocaleString()}</td>
        </tr>
        <tr>
            <td style="text-align:left;">Total</td>
            <td style="text-align:right;">UGX ${Number(total).toLocaleString()}</td>
        </tr>
    </table>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <table style="width:100%;font-size:13px;">
        <tr>
            <td style="text-align:left;">Cash</td>
            <td style="text-align:right;">UGX ${Number(amountPaid).toLocaleString()}</td>
        </tr>
        <tr>
            <td style="text-align:left;">Change</td>
            <td style="text-align:right;">UGX ${(Number(amountPaid)-Number(total)).toLocaleString()}</td>
        </tr>
    </table>
    <div style="font-size:12px;margin-bottom:2px;">----------------------------------------------------</div>
    <div style="text-align:center;font-size:13px;margin:10px 0 2px 0;">THANK YOU</div>
    <div style="text-align:center;font-size:13px;margin-bottom:8px;">HAVE A NICE DAY</div>
    <div style="text-align:center;margin-top:8px;">
        <svg id="barcodeSvg" style="width:180px;height:40px;"></svg>
    </div>
</div>
        `;
        // Print window
        const win = window.open('', '', 'width=400,height=600');
        win.document.write(`<html><head><title>Receipt</title>
<style>
@media print {
  body * { visibility: hidden !important; }
  #receiptToPrint, #receiptToPrint * {
    visibility: visible !important;
  }
  #receiptToPrint {
    position: absolute;
    left: 0; top: 0;
    width: 58mm;
    min-width: 0;
    max-width: 100vw;
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    background: #fff !important;
    color: #000 !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  #receiptToPrint table { width:100%; }
  #receiptToPrint tr, #receiptToPrint td { font-size:13px; }
}
</style>
</head><body>${receiptHtml}
<script>
(function() {
    // Simple barcode SVG generator (Code128, dummy bars for visual)
    var svg = document.getElementById('barcodeSvg');
    if (svg) {
        var code = "${barcode}";
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
    setTimeout(function() { window.print(); setTimeout(function(){window.close();}, 400); }, 200);
})();
<\/script>
</body></html>`);
        win.document.close();
        win.focus();
    }

    // NEW: Invoice Preview (simple template)
    function openInvoicePreview(cart, total, customer) {
        const now = new Date();
        const invNo = 'INV-' + now.getFullYear().toString().slice(-2) + (now.getMonth()+1).toString().padStart(2,'0') + now.getDate().toString().padStart(2,'0') + '-' + Math.floor(Math.random()*9000+1000);
        let itemsHtml = '';
        cart.forEach((it, idx) => {
            const amount = Number(it.price) * Number(it.quantity);
            itemsHtml += `
              <tr>
                <td>${idx+1}</td>
                <td>${escapeHtml(String(it.name||''))}</td>
                <td class="text-center">${Number(it.quantity)}</td>
                <td class="text-end">UGX ${Number(it.price).toLocaleString()}</td>
                <td class="text-end">UGX ${amount.toLocaleString()}</td>
              </tr>`;
        });

        const html = `
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice ${invNo}</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background:#f8f9fa; margin:0; padding:0; color:#222; }
  .wrap { max-width:900px; margin:2rem auto; background:#fff; border-radius:14px; box-shadow:0 4px 24px #0002; padding:2rem 2.5rem; }
  .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem; }
  .brand { font-weight:700; color:#1abc9c; font-size:1.4rem; }
  .invoice-title { text-align:center; font-size:2rem; font-weight:800; letter-spacing:2px; margin:1rem 0; }
  .meta { display:flex; justify-content:space-between; gap:1rem; margin-bottom:1.2rem; }
  .box { background:#f4f6f9; border-radius:10px; padding:1rem 1.2rem; flex:1; }
  .box h4 { margin:.2rem 0 .6rem; color:#1abc9c; }
  table { width:100%; border-collapse:collapse; margin-top:1rem; }
  th, td { padding:.7rem 1rem; border-bottom:1px solid #e0e0e0; }
  thead th { background:#1abc9c; color:#fff; text-align:left; }
  .text-end { text-align:right; } .text-center { text-align:center; }
  tfoot td { font-weight:700; }
  .print-btn { display:block; margin:2rem auto 0; padding:.6rem 2rem; background:#1abc9c; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:700; }
  @media print { .print-btn { display:none; } .wrap { box-shadow:none; border-radius:0; padding:.5rem; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div class="brand">INVOICE</div>
    <div>
      <div><strong>No:</strong> ${invNo}</div>
      <div><strong>Date:</strong> ${now.toLocaleDateString()} ${now.toLocaleTimeString()}</div>
    </div>
  </div>
  <div class="meta">
    <div class="box">
      <h4>Bill To</h4>
      <div>${escapeHtml(String(customer?.name||'Customer'))}</div>
      <div>${escapeHtml(String(customer?.contact||''))}</div>
      <div>${escapeHtml(String(customer?.email||''))}</div>
    </div>
    <div class="box">
      <h4>Terms</h4>
      <div>Due on Receipt</div>
      <div style="margin-top:.6rem;"><strong>Balance Due:</strong> UGX ${Number(total).toLocaleString()}</div>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Item & Description</th>
        <th class="text-center">Qty</th>
        <th class="text-end">Rate</th>
        <th class="text-end">Amount</th>
      </tr>
    </thead>
    <tbody>${itemsHtml}</tbody>
    <tfoot>
      <tr><td colspan="4" class="text-end">Sub Total</td><td class="text-end">UGX ${Number(total).toLocaleString()}</td></tr>
      <tr><td colspan="4" class="text-end">Tax</td><td class="text-end">UGX 0</td></tr>
      <tr><td colspan="4" class="text-end">Total</td><td class="text-end">UGX ${Number(total).toLocaleString()}</td></tr>
      <tr><td colspan="4" class="text-end">Balance Due</td><td class="text-end">UGX ${Number(total).toLocaleString()}</td></tr>
    </tfoot>
  </table>
  <div style="margin-top:1rem;color:#555;">Thank you for your business.</div>
  <button class="print-btn" onclick="window.print()">Print Invoice</button>
</div>
<\/body>
</html>`;
        const w = window.open('', '_blank');
        w.document.write(html);
        w.document.close();
    }

    // --- Modified Sell Button Logic ---
    document.getElementById('sellBtn').onclick = function() {
        const paymentMethod = document.getElementById('payment_method').value;
        const amountPaid = parseFloat(document.getElementById('amount_paid').value || 0);
        const customerId = document.getElementById('customer_select').value;

        // Calculate total cart value
        let total = 0;
        cart.forEach(item => { total += item.price * item.quantity; });

        if (cart.length === 0) { alert('Cart is empty.'); return; }

        // For Customer File, check selected customer and balance
        if (paymentMethod === 'Customer File') {
            if (!customerId) { alert('Please select a customer for Customer File payment.'); return; }
            const cust = getCustomerById(customerId);
            const balance = parseFloat(cust?.account_balance || 0);

            // If insufficient funds: show Invoice modal (Record/OK/Cancel)
            if (balance < total) {
                showInvoiceConfirmModal(function(action){
                    if (action === 'ok') {
                        autoRecordDebtorFromCustomerFile(cust, cart, total);
                        openInvoicePreview(cart, total, cust);
                    } else if (action === 'record') {
                        autoRecordDebtorFromCustomerFile(cust, cart, total);
                    }
                    // cancel => do nothing
                });
                return;
            }
        }

        // Helper to submit sale and print receipt (unchanged)
        function submitAndMaybePrint(showPreview) {
            if (paymentMethod === 'Customer File') {
                const custId = document.getElementById('customer_select').value;
                if (!custId) { alert('Please select a customer for Customer File payment.'); return; }
                document.getElementById('cart_data').value = JSON.stringify(cart);
                document.getElementById('cart_amount_paid').value = 0;
                document.getElementById('hidden_payment_method').value = paymentMethod;
                document.getElementById('hidden_customer_id').value = custId;
                hiddenSaleForm.submit();
                if (showPreview) openReceiptPreview(cart, total, paymentMethod, 0);
                return;
            }

            if (amountPaid >= total) {
                const balance = amountPaid - total;
                if (balance > 0) {
                    alert(`Balance is UGX ${balance.toLocaleString()}`);
                }
                document.getElementById('cart_data').value = JSON.stringify(cart);
                document.getElementById('cart_amount_paid').value = amountPaid;
                document.getElementById('hidden_payment_method').value = paymentMethod;
                document.getElementById('hidden_customer_id').value = '';
                hiddenSaleForm.submit();
                if (showPreview) openReceiptPreview(cart, total, paymentMethod, amountPaid);
            } else {
                // Underpayment: Show debtor form (manual path for non-Customer File)
                const debtorForm = document.getElementById('debtorsFormCard');
                document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
                document.getElementById('debtor_amount_paid').value = amountPaid;
                debtorForm.style.display = 'block';
                window.scrollTo({ top: debtorForm.offsetTop, behavior: 'smooth' });
            }
        }

        // Only show receipt modal when applicable (unchanged logic)
        const custSufficient = (paymentMethod === 'Customer File')
            ? (getCustomerById(customerId)?.account_balance || 0) >= total
            : false;
        if ((paymentMethod === 'Customer File' && custSufficient) || (paymentMethod !== 'Customer File' && amountPaid >= total)) {
            showReceiptConfirmModal(function(action) {
                if (action === 'ok') {
                    submitAndMaybePrint(true);
                } else if (action === 'record') {
                    submitAndMaybePrint(false);
                }
            });
        } else {
            if (paymentMethod !== 'Customer File') submitAndMaybePrint(false);
        }
    };

    // Auto-record debtor for Customer File (no popup) – reused by Invoice modal actions
    function autoRecordDebtorFromCustomerFile(customer, cart, total) {
        if (!customer) { alert('Invalid customer selected.'); return; }
        const debtorFormCard = document.getElementById('debtorsFormCard');
        const form = debtorFormCard.querySelector('form');
        document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
        document.getElementById('debtor_amount_paid').value = 0;
        document.getElementById('debtor_name').value = customer.name || 'Customer';
        document.getElementById('debtor_contact').value = customer.contact || '';
        document.getElementById('debtor_email').value = customer.email || '';
        let hid = form.querySelector('input[name="debtor_customer_id"]');
        if (!hid) {
            hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = 'debtor_customer_id';
            form.appendChild(hid);
        }
        hid.value = customer.id;
        let submitBtn = form.querySelector('button[name="record_debtor"]');
        if (!submitBtn) {
            submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.name = 'record_debtor';
            submitBtn.style.display = 'none';
            form.appendChild(submitBtn);
        }
        debtorFormCard.style.display = 'none';
        submitBtn.click();
    }

    // --- Add To Cart handler and helpers (missing before) ---
    function updateCartUI() {
        const cartSection = document.getElementById('cartSection');
        const cartItems = document.getElementById('cartItems');
        const cartTotal = document.getElementById('cartTotal');
        if (!cartSection || !cartItems || !cartTotal) return;

        if (cart.length === 0) {
            cartSection.style.display = 'none';
            cartItems.innerHTML = '';
            cartTotal.textContent = '0';
            return;
        }

        cartSection.style.display = '';
        let total = 0;
        cartItems.innerHTML = cart.map((item, idx) => {
            const subtotal = Number(item.quantity) * Number(item.price);
            total += subtotal;
            return `<tr>
                <td>${escapeHtml(String(item.name))}</td>
                <td>${item.quantity}</td>
                <td>UGX ${Number(item.price).toLocaleString()}</td>
                <td>UGX ${subtotal.toLocaleString()}</td>
                <td><button class="btn btn-sm btn-danger" onclick="removeCartItem(${idx})">Remove</button></td>
            </tr>`;
        }).join('');
        cartTotal.textContent = 'UGX ' + total.toLocaleString();
    }
    window.removeCartItem = function(idx) {
        cart.splice(idx, 1);
        updateCartUI();
    };

    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productSel = document.getElementById('product_id');
            const qtyInput = document.getElementById('quantity');
            const productId = productSel?.value;
            const quantity = parseInt(qtyInput?.value || '0', 10);

            if (!productId) { alert('Select a product.'); return; }
            if (!quantity || quantity < 1) { alert('Enter a valid quantity.'); return; }

            const prod = (window.productData || {})[productId];
            if (!prod) { alert('Product not found.'); return; }

            const price = Number(prod['selling-price'] || 0);
            if (!price) { alert('Invalid product price.'); return; }

            const existing = cart.find(it => String(it.id) === String(productId));
            if (existing) {
                existing.quantity += quantity;
            } else {
                cart.push({
                    id: productId,
                    name: prod.name,
                    price: price,
                    quantity: quantity
                });
            }

            updateCartUI();
            document.getElementById('addSaleForm')?.reset();
            // keep focus on product for fast entry
            productSel?.focus();
        });
    }

    // --- Debtor Pay Modal wiring (parity with sales.php) ---
    function ensureBootstrap(cb) {
        if (window.bootstrap) return cb();
        const src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
        if (document.querySelector('script[src="'+src+'"]')) {
            const t = setInterval(() => { if (window.bootstrap) { clearInterval(t); cb(); } }, 50);
            return;
        }
        const s = document.createElement('script');
        s.src = src;
        s.onload = cb;
        s.onerror = function() { console.error('Failed to load Bootstrap bundle.'); cb(); };
        document.head.appendChild(s);
    }

    function initPayModal() {
        const payButtons = document.querySelectorAll('.btn-pay-debtor');
        if (!payButtons.length) return;

        const payModalEl = document.getElementById('payDebtorModal');
        if (!payModalEl) return;
        const payModal = new bootstrap.Modal(payModalEl);
        const pdDebtorLabel = document.getElementById('pdDebtorLabel');
        const pdBalanceText = document.getElementById('pdBalanceText');
        const pdDebtorId = document.getElementById('pdDebtorId');
        const pdAmount = document.getElementById('pdAmount');
        const pdMsg = document.getElementById('pdMsg');
        const pdConfirmBtn = document.getElementById('pdConfirmBtn');

        payButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const balance = parseFloat(btn.getAttribute('data-balance') || 0);
                const name = btn.getAttribute('data-name') || 'Debtor';
                pdDebtorId.value = id;
                pdAmount.value = '';
                pdDebtorLabel.textContent = `Debtor: ${name}`;
                pdBalanceText.textContent = 'UGX ' + balance.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                pdMsg.innerHTML = '';
                payModalEl.dataset.outstanding = String(balance);
                payModal.show();
            });
        });

        pdConfirmBtn?.addEventListener('click', async () => {
            const id = pdDebtorId.value;
            let amount = parseFloat(pdAmount.value || 0);
            const outstanding = parseFloat(payModalEl.dataset.outstanding || 0);

            pdMsg.innerHTML = '';
            if (!id) { pdMsg.innerHTML = '<div class="alert alert-warning">Invalid debtor selected.</div>'; return; }
            if (!amount || amount <= 0) { pdMsg.innerHTML = '<div class="alert alert-warning">Enter a valid amount.</div>'; return; }
            if (amount > outstanding) { pdMsg.innerHTML = '<div class="alert alert-warning">Amount cannot exceed outstanding balance.</div>'; return; }

            pdConfirmBtn.disabled = true;
            pdConfirmBtn.textContent = 'Processing...';
            try {
                const res = await fetch('handle_debtor_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `pay_debtor=1&id=${encodeURIComponent(id)}&amount=${encodeURIComponent(amount)}`
                });
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch {
                    console.error('Invalid JSON response from server:', text);
                    pdMsg.innerHTML = '<div class="alert alert-danger">Server returned an invalid response. See console.</div>';
                    pdConfirmBtn.disabled = false;
                    pdConfirmBtn.textContent = 'OK';
                    return;
                }
                pdConfirmBtn.disabled = false;
                pdConfirmBtn.textContent = 'OK';
                if (data && data.reload) {
                    payModal.hide();
                    window.location.reload();
                } else {
                    pdMsg.innerHTML = '<div class="alert alert-info">' + (data.message || 'Payment recorded') + '</div>';
                }
            } catch (err) {
                console.error('Request error:', err);
                pdConfirmBtn.disabled = false;
                pdConfirmBtn.textContent = 'OK';
                pdMsg.innerHTML = '<div class="alert alert-danger">Error processing payment. Check console.</div>';
            }
        });
    }

    // Initialize modal handlers (works whether Bootstrap was preloaded or not)
    ensureBootstrap(initPayModal);

    // ...existing code (invoice/receipt modals, sellBtn, debtor auto-record, barcode, etc.)...
});
