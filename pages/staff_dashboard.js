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

    // Sell button logic (adjusted to include customer file flow)
    document.getElementById('sellBtn').onclick = function() {
        const paymentMethod = document.getElementById('payment_method').value;
        const amountPaid = parseFloat(document.getElementById('amount_paid').value || 0);

        // Calculate total cart value
        let total = 0;
        cart.forEach(item => {
            total += item.price * item.quantity;
        });

        if (paymentMethod === 'Customer File') {
            const custId = document.getElementById('customer_select').value;
            if (!custId) { alert('Please select a customer for Customer File payment.'); return; }
            // submit with customer_id; amount_paid left as 0
            document.getElementById('cart_data').value = JSON.stringify(cart);
            document.getElementById('cart_amount_paid').value = 0;
            document.getElementById('hidden_payment_method').value = paymentMethod;
            document.getElementById('hidden_customer_id').value = custId;
            hiddenSaleForm.submit();
            return;
        }

        // existing flow for other payment methods
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
        } else {
            // Underpayment: Show debtor form
            const debtorForm = document.getElementById('debtorsFormCard');
            document.getElementById('debtor_cart_data').value = JSON.stringify(cart);
            document.getElementById('debtor_amount_paid').value = amountPaid;
            debtorForm.style.display = 'block';
            window.scrollTo({ top: debtorForm.offsetTop, behavior: 'smooth' });
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
});
