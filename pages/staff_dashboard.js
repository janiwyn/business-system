document.addEventListener('DOMContentLoaded', function() {
    // Cart logic
    let cart = [];

    // expose product/customer data (productData may already be defined elsewhere)
    const productData = window.productData || {};
    const customers = window.customers || [];

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

    // --- Modified Sell Button Logic ---
    document.getElementById('sellBtn').onclick = function() {
        const paymentMethod = document.getElementById('payment_method').value;
        const amountPaid = parseFloat(document.getElementById('amount_paid').value || 0);

        // Calculate total cart value
        let total = 0;
        cart.forEach(item => {
            total += item.price * item.quantity;
        });

        // If cart is empty, do nothing
        if (cart.length === 0) {
            alert('Cart is empty.');
            return;
        }

        // Helper to submit sale and print receipt
        function submitAndMaybePrint(printReceipt) {
            if (paymentMethod === 'Customer File') {
                const custId = document.getElementById('customer_select').value;
                if (!custId) { alert('Please select a customer for Customer File payment.'); return; }
                document.getElementById('cart_data').value = JSON.stringify(cart);
                document.getElementById('cart_amount_paid').value = 0;
                document.getElementById('hidden_payment_method').value = paymentMethod;
                document.getElementById('hidden_customer_id').value = custId;
                hiddenSaleForm.submit();
                if (printReceipt) printCartReceipt(cart, total, paymentMethod, 0);
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
                if (printReceipt) printCartReceipt(cart, total, paymentMethod, amountPaid);
            } else {
                // Underpayment: Show debtor form
                const debtorForm = document.getElementById('debtorsFormCard');
                document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
                document.getElementById('debtor_amount_paid').value = amountPaid;
                debtorForm.style.display = 'block';
                window.scrollTo({ top: debtorForm.offsetTop, behavior: 'smooth' });
            }
        }

        // Only show receipt modal for non-debtor sales
        if (
            (paymentMethod === 'Customer File') ||
            (amountPaid >= total)
        ) {
            showReceiptConfirmModal(function(action) {
                if (action === 'ok') {
                    submitAndMaybePrint(true); // submit and print
                } else if (action === 'record') {
                    submitAndMaybePrint(false); // submit only, no print
                }
                // cancel does nothing
            });
        } else {
            // For debtors, proceed as before (no receipt)
            submitAndMaybePrint(false);
        }
    };

    function updateCartUI() {
        const cartSection = document.getElementById('cartSection');
        const cartItems = document.getElementById('cartItems');
        const cartTotal = document.getElementById('cartTotal');
        if (cart.length === 0) {
            cartSection.style.display = 'none';
            return;
        }
        cartSection.style.display = '';
        let total = 0;
        cartItems.innerHTML = cart.map((item, idx) => {
            const subtotal = item.quantity * item.price;
            total += subtotal;
            return `<tr>
                <td>${item.name}</td>
                <td>${item.quantity}</td>
                <td>UGX ${item.price.toLocaleString()}</td>
                <td>UGX ${subtotal.toLocaleString()}</td>
                <td><button class='btn btn-sm btn-danger' onclick='removeCartItem(${idx})'>Remove</button></td>
            </tr>`;
        }).join('');
        cartTotal.textContent = 'UGX ' + total.toLocaleString();
    }
    window.removeCartItem = function(idx) {
        cart.splice(idx, 1);
        updateCartUI();
    };
    document.getElementById('addToCartBtn').onclick = function() {
        const productId = document.getElementById('product_id').value;
        const quantity = parseInt(document.getElementById('quantity').value, 10);
        if (!productId || !quantity || quantity < 1) return;
        const prod = productData[productId];
        if (!prod) return;
        // Check if already in cart
        const existing = cart.find(item => item.id == productId);
        if (existing) {
            existing.quantity += quantity;
        } else {
            cart.push({ id: productId, name: prod.name, price: parseInt(prod['selling-price'],10), quantity });
        }
        updateCartUI();
        document.getElementById('addSaleForm').reset();
    };

    // Debtor Pay Modal logic (moved from staff_dashboard.php)
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

        pdConfirmBtn.addEventListener('click', async () => {
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
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    console.error('Invalid JSON response from server:', text);
                    pdMsg.innerHTML = '<div class="alert alert-danger">Server returned an invalid response. See console for details.</div>';
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

    ensureBootstrap(initPayModal);

    // Welcome balls animation (IIFE can stay outside DOMContentLoaded)
    (function() {
      const banner = document.querySelector('.welcome-banner');
      const ballsContainer = document.querySelector('.welcome-balls');
      if (!banner || !ballsContainer) return;

      function getColors() {
        if (document.body.classList.contains('dark-mode')) {
          return ['#ffd200', '#1abc9c', '#56ccf2', '#23243a', '#fff'];
        } else {
          return ['#1abc9c', '#56ccf2', '#ffd200', '#3498db', '#fff'];
        }
      }

      ballsContainer.innerHTML = '';
      ballsContainer.style.position = 'absolute';
      ballsContainer.style.top = 0;
      ballsContainer.style.left = 0;
      ballsContainer.style.width = '100%';
      ballsContainer.style.height = '100%';
      ballsContainer.style.zIndex = 1;
      ballsContainer.style.pointerEvents = 'none';

      const balls = [];
      const colors = getColors();
      const numBalls = 7;
      for (let i = 0; i < numBalls; i++) {
        const ball = document.createElement('div');
        ball.className = 'welcome-ball';
        ball.style.position = 'absolute';
        ball.style.borderRadius = '50%';
        ball.style.opacity = '0.18';
        ball.style.background = colors[i % colors.length];
        ball.style.width = ball.style.height = (32 + Math.random() * 32) + 'px';
        ball.style.top = (10 + Math.random() * 60) + '%';
        ball.style.left = (5 + Math.random() * 85) + '%';
        ballsContainer.appendChild(ball);
        balls.push({
          el: ball,
          x: parseFloat(ball.style.left),
          y: parseFloat(ball.style.top),
          r: Math.random() * 0.5 + 0.2,
          dx: (Math.random() - 0.5) * 0.2,
          dy: (Math.random() - 0.5) * 0.2
        });
      }

      function animateBalls() {
        balls.forEach(ball => {
          ball.x += ball.dx;
          ball.y += ball.dy;
          if (ball.x < 0 || ball.x > 95) ball.dx *= -1;
          if (ball.y < 5 || ball.y > 80) ball.dy *= -1;
          ball.el.style.left = ball.x + '%';
          ball.el.style.top = ball.y + '%';
        });
        requestAnimationFrame(animateBalls);
      }
      animateBalls();

      window.addEventListener('storage', () => {
        const newColors = getColors();
        balls.forEach((ball, i) => {
          ball.el.style.background = newColors[i % newColors.length];
        });
      });
      document.getElementById('themeToggle')?.addEventListener('change', () => {
        const newColors = getColors();
        balls.forEach((ball, i) => {
          ball.el.style.background = newColors[i % newColors.length];
        });
      });
    })();

    // Barcode scanning logic
    (function() {
        // Elements
        const scanBtn = document.getElementById('scanBarcodeBtn');
        const scanModal = document.getElementById('barcodeScanModal');
        const closeScanBtn = document.getElementById('closeBarcodeScan');
        const scanVideo = document.getElementById('barcodeScanVideo');
        const scanCanvas = document.getElementById('barcodeScanCanvas');
        const rotateBtn = document.getElementById('rotateCameraBtn');
        const scanModeSel = document.getElementById('barcodeScanMode');
        const scanStatus = document.getElementById('barcodeScanStatus');
        let currentStream = null;
        let currentFacing = 'environment'; // or 'user'
        let scanActive = false;
        let audioCtx = null;

        // Open modal
        scanBtn?.addEventListener('click', () => {
            // Unlock AudioContext on first user gesture
            if (!audioCtx) {
                try {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    // Immediately suspend so it can be resumed later
                    audioCtx.suspend();
                } catch (e) { audioCtx = null; }
            }
            scanModal.style.display = 'flex';
            scanStatus.textContent = '';
            startCameraScan();
        });

        // Close modal
        closeScanBtn?.addEventListener('click', () => {
            scanModal.style.display = 'none';
            stopCameraScan();
        });

        // Rotate camera
        rotateBtn?.addEventListener('click', () => {
            currentFacing = (currentFacing === 'environment') ? 'user' : 'environment';
            startCameraScan();
        });

        // Scan mode change
        scanModeSel?.addEventListener('change', () => {
            if (scanModeSel.value === 'hardware') {
                stopCameraScan();
                scanVideo.style.display = 'none';
                scanCanvas.style.display = 'none';
                scanStatus.textContent = 'Focus barcode input field and scan using hardware scanner.';
                // Listen for hardware barcode input (simulate with a hidden input)
                ensureHardwareInput();
            } else {
                scanVideo.style.display = '';
                scanStatus.textContent = '';
                startCameraScan();
            }
        });

        // Camera scan logic (simple, using BarcodeDetector API if available, fallback to QuaggaJS if needed)
        function startCameraScan() {
            stopCameraScan();
            scanActive = true;
            scanVideo.style.display = '';
            scanCanvas.style.display = 'none';
            scanStatus.textContent = 'Initializing camera...';
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({
                    video: { facingMode: currentFacing }
                }).then(stream => {
                    currentStream = stream;
                    scanVideo.srcObject = stream;
                    scanVideo.play();
                    scanStatus.textContent = 'Point camera at barcode.';
                    if ('BarcodeDetector' in window) {
                        const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'upc_a', 'upc_e'] });
                        const scanFrame = () => {
                            if (!scanActive) return;
                            detector.detect(scanVideo).then(barcodes => {
                                if (barcodes.length > 0) {
                                    handleBarcode(barcodes[0].rawValue);
                            } else {
                                requestAnimationFrame(scanFrame);
                            }
                        }).catch(() => requestAnimationFrame(scanFrame));
                    };
                    scanFrame();
                } else {
                    // Fallback: try QuaggaJS (must be loaded externally if needed)
                    scanStatus.textContent = 'BarcodeDetector not supported. Please use Chrome/Edge or hardware scanner.';
                }
            }).catch(err => {
                scanStatus.textContent = 'Camera error: ' + err.message;
            });
        } else {
            scanStatus.textContent = 'Camera not supported.';
        }
    }

    function stopCameraScan() {
        scanActive = false;
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
        scanVideo.srcObject = null;
    }

    // Hardware barcode input (simulate with hidden input)
    function ensureHardwareInput() {
        let hwInput = document.getElementById('hardwareBarcodeInput');
        if (!hwInput) {
            hwInput = document.createElement('input');
            hwInput.type = 'text';
            hwInput.id = 'hardwareBarcodeInput';
            hwInput.style.position = 'absolute';
            hwInput.style.opacity = 0;
            hwInput.style.pointerEvents = 'none';
            scanModal.appendChild(hwInput);
        }
        hwInput.value = '';
        hwInput.focus();
        hwInput.oninput = function() {
            if (hwInput.value.length >= 6) { // basic length check
                handleBarcode(hwInput.value.trim());
                hwInput.value = '';
            }
        };
    }

    // Handle barcode: auto-select product in dropdown
    function handleBarcode(barcode) {
        scanStatus.textContent = 'Barcode detected: ' + barcode;
        let foundId = null;
        const scanned = String(barcode).trim();
        for (const pid in productData) {
            const prodBarcode = String(productData[pid].barcode || '').trim();
            if (prodBarcode && prodBarcode === scanned) {
                foundId = pid;
                break;
            }
        }
        if (foundId) {
            document.getElementById('product_id').value = foundId;
            scanStatus.textContent = 'Product selected: ' + productData[foundId].name;
            playBeep();
            setTimeout(() => {
                scanModal.style.display = 'none';
                stopCameraScan();
                document.getElementById('quantity').focus();
            }, 350);
        } else {
            scanStatus.textContent = 'No matching product found for barcode: ' + barcode;
            playFailBeep();
        }
    }

    // Add beep sound function (success)
    function playBeep() {
        try {
            let ctx = audioCtx;
            if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
            if (ctx.state === 'suspended') ctx.resume();
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.type = 'triangle';
            oscillator.frequency.setValueAtTime(1600, ctx.currentTime);
            gain.gain.value = 0.08;
            oscillator.connect(gain).connect(ctx.destination);
            oscillator.start();
            setTimeout(() => {
                oscillator.stop();
                oscillator.disconnect();
                gain.disconnect();
            }, 80);
        } catch (e) {}
    }

    // Add fail beep sound function (failure)
    function playFailBeep() {
        try {
            let ctx = audioCtx;
            if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
            if (ctx.state === 'suspended') ctx.resume();
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();
            oscillator.type = 'sawtooth'; // harsher sound
            oscillator.frequency.setValueAtTime(400, ctx.currentTime); // lower pitch
            gain.gain.value = 0.12; // slightly louder
            oscillator.connect(gain).connect(ctx.destination);
            oscillator.start();
            setTimeout(() => {
                oscillator.stop();
                oscillator.disconnect();
                gain.disconnect();
            }, 180); // longer duration for fail
        } catch (e) {}
    }

    // If modal is closed by clicking outside
    scanModal?.addEventListener('click', function(e) {
        if (e.target === scanModal) {
            scanModal.style.display = 'none';
            stopCameraScan();
        }
    });
    })();
});
