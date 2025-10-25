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

    // REMOVE this entire block if present (do not just comment it):
    /*
    document.querySelectorAll('.btn-pay-debtor').forEach(function(btn) {
        btn.onclick = function() {
            const row = btn.closest('tr');
            const debtorId = btn.getAttribute('data-id');
            const debtorName = row.querySelector('td:nth-child(2)').textContent;
            const balance = parseFloat(row.querySelector('td:nth-child(7)').textContent.replace(/[^\d.]/g, '')) || 0;

            // Show prompt for amount
            let amount = prompt(`Enter amount paid for ${debtorName} (Balance: UGX ${balance.toLocaleString()}):`, balance);
            if (amount === null) return; // Cancelled
            amount = parseFloat(amount);
            if (isNaN(amount) || amount <= 0) {
                alert('Please enter a valid amount.');
                return;
            }
            if (amount > balance) {
                alert('Amount cannot be greater than the balance.');
                return;
            }

            // AJAX to process payment
            fetch('staff_dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `pay_debtor=1&id=${debtorId}&amount=${amount}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.reload) window.location.reload();
            });
        };
    });
    */

    // If you have any other code that needs to run after DOM is ready, put it here.
});

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
