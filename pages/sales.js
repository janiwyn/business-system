(function(){
  // Run when DOM ready
  function onReady(cb) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', cb);
    } else cb();
  }

  onReady(function() {
    // --- ensureBootstrap helper (same as original) ---
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

    // --- initPayModal (moved from inline) ---
    function initPayModal() {
      const payButtons = document.querySelectorAll('.btn-pay-debtor');
      if (!payButtons.length) return;

      const payModalEl = document.getElementById('payDebtorModal');
      const payModal = new bootstrap.Modal(payModalEl);
      const pdDebtorLabel = document.getElementById('pdDebtorLabel');
      const pdBalanceText = document.getElementById('pdBalanceText');
      const pdDebtorId = document.getElementById('pdDebtorId');
      const pdAmount = document.getElementById('pdAmount');
      const pdMethod = document.getElementById('pdMethod');
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

          // Reset payment method to Cash and enable it
          if (pdMethod) pdMethod.value = 'Cash';
          if (document.getElementById('pdMethodWrap')) document.getElementById('pdMethodWrap').style.display = '';

          payModal.show();
        });
      });

      pdConfirmBtn.addEventListener('click', async () => {
        const id = pdDebtorId.value;
        let amount = parseFloat(pdAmount.value || 0);
        const outstanding = parseFloat(payModalEl.dataset.outstanding || 0);
        const pm = (pdMethod?.value || 'Cash');

        pdMsg.innerHTML = '';
        if (!id) { pdMsg.innerHTML = '<div class="alert alert-warning">Invalid debtor selected.</div>'; return; }
        if (!amount || amount <= 0) { pdMsg.innerHTML = '<div class="alert alert-warning">Enter a valid amount.</div>'; return; }
        if (amount > outstanding) { pdMsg.innerHTML = '<div class="alert alert-warning">Amount cannot exceed outstanding balance.</div>'; return; }

        pdConfirmBtn.disabled = true;
        pdConfirmBtn.textContent = 'Processing...';
        try {
          const res = await fetch(location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `pay_debtor=1&id=${encodeURIComponent(id)}&amount=${encodeURIComponent(amount)}&pm=${encodeURIComponent(pm)}`
          });

          const text = await res.text();
          let data;
          try { data = JSON.parse(text); } catch (parseErr) {
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

    // ensure bootstrap then init pay modal
    ensureBootstrap(initPayModal);

    // Expose openReportGen globally (used by HTML buttons)
    window.openReportGen = function(type) {
      let title = 'Generate Report';
      if (type === 'payment_analysis') title = 'Generate Payment Analysis Report';
      else if (type === 'debtors') title = 'Generate Debtors Report';
      else if (type === 'product_summary') title = 'Generate Product Summary Report';
      const modalEl = document.getElementById('reportGenModal');
      if (!modalEl) return;
      document.getElementById('reportGenModalTitle').textContent = title;
      document.getElementById('reportGenForm').dataset.reportType = type;
      new bootstrap.Modal(modalEl).show();
    };

    // Report form submit
    const reportGenForm = document.getElementById('reportGenForm');
    if (reportGenForm) {
      reportGenForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const type = this.dataset.reportType || 'sales';
        const date_from = document.getElementById('report_date_from').value;
        const date_to = document.getElementById('report_date_to').value;
        const branch = document.getElementById('report_branch').value;
        const url = `reports_generator.php?type=${encodeURIComponent(type)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&branch=${encodeURIComponent(branch)}`;
        window.open(url, '_blank');
        const inst = bootstrap.Modal.getInstance(document.getElementById('reportGenModal'));
        if (inst) inst.hide();
      });
    }

    // Handle Customer Debtor payment button clicks (attach to existing buttons)
    document.querySelectorAll('.btn-pay-customer-debtor').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const balance = parseFloat(btn.getAttribute('data-balance') || 0);
        const name = btn.getAttribute('data-name') || 'Customer';

        const payModalEl = document.getElementById('payDebtorModal');
        const payModal = new bootstrap.Modal(payModalEl);

        document.getElementById('pdDebtorLabel').textContent = `Customer: ${name}`;
        document.getElementById('pdBalanceText').textContent = 'UGX ' + balance.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('pdDebtorId').value = id;
        document.getElementById('pdAmount').value = '';
        document.getElementById('pdMsg').innerHTML = '';
        payModalEl.dataset.outstanding = String(balance);
        payModalEl.dataset.isCustomerDebtor = '1';

        // Hide payment method for customer debtors (always 'Customer File')
        const wrap = document.getElementById('pdMethodWrap');
        if (wrap) wrap.style.display = 'none';

        payModal.show();
      });
    });

    // Pay confirm button (handles both shop and customer debtors)
    const pdConfirmBtnGlobal = document.getElementById('pdConfirmBtn');
    if (pdConfirmBtnGlobal) {
      pdConfirmBtnGlobal.addEventListener('click', async () => {
        const payModalEl = document.getElementById('payDebtorModal');
        const isCustomerDebtor = payModalEl.dataset.isCustomerDebtor === '1';
        const id = document.getElementById('pdDebtorId').value;
        let amount = parseFloat(document.getElementById('pdAmount').value || 0);
        const outstanding = parseFloat(payModalEl.dataset.outstanding || 0);
        const pm = document.getElementById('pdMethod')?.value || 'Cash';

        const pdMsg = document.getElementById('pdMsg');
        const pdConfirmBtn = pdConfirmBtnGlobal;

        pdMsg.innerHTML = '';
        if (!id) { pdMsg.innerHTML = '<div class="alert alert-warning">Invalid selection.</div>'; return; }
        if (!amount || amount <= 0) { pdMsg.innerHTML = '<div class="alert alert-warning">Enter a valid amount.</div>'; return; }
        if (amount > outstanding) { pdMsg.innerHTML = '<div class="alert alert-warning">Amount cannot exceed balance.</div>'; return; }

        pdConfirmBtn.disabled = true;
        pdConfirmBtn.textContent = 'Processing...';

        try {
          const endpoint = isCustomerDebtor ? 'pay_customer_debtor' : 'pay_debtor';
          const body = `${endpoint}=1&id=${encodeURIComponent(id)}&amount=${encodeURIComponent(amount)}${!isCustomerDebtor ? '&pm='+encodeURIComponent(pm) : ''}`;

          const res = await fetch(location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
          });

          const text = await res.text();
          let data;
          try { data = JSON.parse(text); } catch (parseErr) {
            console.error('Invalid JSON response:', text);
            pdMsg.innerHTML = '<div class="alert alert-danger">Server error. See console.</div>';
            pdConfirmBtn.disabled = false;
            pdConfirmBtn.textContent = 'OK';
            return;
          }

          pdConfirmBtn.disabled = false;
          pdConfirmBtn.textContent = 'OK';

          if (data && data.reload) {
            const inst = bootstrap.Modal.getInstance(payModalEl);
            if (inst) inst.hide();
            window.location.reload();
          } else {
            pdMsg.innerHTML = '<div class="alert alert-info">' + (data.message || 'Payment recorded') + '</div>';
          }
        } catch (err) {
          console.error('Request error:', err);
          pdConfirmBtn.disabled = false;
          pdConfirmBtn.textContent = 'OK';
          pdMsg.innerHTML = '<div class="alert alert-danger">Error processing payment.</div>';
        }

        // Reset modal state
        delete payModalEl.dataset.isCustomerDebtor;
        const wrap = document.getElementById('pdMethodWrap');
        if (wrap) wrap.style.display = '';
      });
    }

    // Set Due Date button handler (delegated)
    document.body.addEventListener('click', function(e) {
      const btn = e.target.closest('.set-due-date-btn');
      if (!btn) return;

      const type = btn.getAttribute('data-type'); // 'shop' or 'customer'
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name');

      const dueDateModal = new bootstrap.Modal(document.getElementById('setDueDateModal'));
      document.getElementById('ddDebtorLabel').textContent = `Debtor: ${name}`;
      document.getElementById('ddDebtorId').value = id;
      document.getElementById('ddDebtorType').value = type;
      document.getElementById('ddDueDate').value = '';
      document.getElementById('ddMsg').innerHTML = '';

      dueDateModal.show();
    });

    // Confirm due date setting
    const ddConfirmBtn = document.getElementById('ddConfirmBtn');
    if (ddConfirmBtn) {
      ddConfirmBtn.addEventListener('click', async function() {
        const id = document.getElementById('ddDebtorId').value;
        const type = document.getElementById('ddDebtorType').value;
        const dueDate = document.getElementById('ddDueDate').value;
        const ddMsg = document.getElementById('ddMsg');
        const ddConfirmBtnLocal = this;

        if (!dueDate) {
          ddMsg.innerHTML = '<div class="alert alert-warning">Please select a date.</div>';
          return;
        }

        ddConfirmBtnLocal.disabled = true;
        ddConfirmBtnLocal.textContent = 'Saving...';

        try {
          const formData = new FormData();
          let action = '';
          let id_field = '';

          if (type === 'shop') {
            action = 'set_due_date';
            id_field = 'debtor_id';
          } else { // customer
            action = 'set_customer_due_date';
            id_field = 'transaction_id';
          }

          formData.append(action, '1');
          formData.append(id_field, id);
          formData.append('due_date', dueDate);

          const res = await fetch(location.pathname, {
            method: 'POST',
            body: formData
          });

          const data = await res.json();

          ddConfirmBtnLocal.disabled = false;
          ddConfirmBtnLocal.textContent = 'OK';

          if (data.success) {
            ddMsg.innerHTML = '<div class="alert alert-success">Due date set successfully!</div>';
            setTimeout(() => {
              const inst = bootstrap.Modal.getInstance(document.getElementById('setDueDateModal'));
              if (inst) inst.hide();
              location.reload();
            }, 800);
          } else {
            ddMsg.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error setting due date') + '</div>';
          }
        } catch (err) {
          console.error('Error:', err);
          ddConfirmBtnLocal.disabled = false;
          ddConfirmBtnLocal.textContent = 'OK';
          ddMsg.innerHTML = '<div class="alert alert-danger">A client-side error occurred. Check console.</div>';
        }
      });
    }

    // --- Chart initialization (uses server-side data exposed on window.salesServerData) ---
    (function initCharts() {
      const dataObj = window.salesServerData || { chart_labels: [], series: {} };
      const rawLabels = dataObj.chart_labels || [];
      const chart_labels = rawLabels.map(m => {
        const parts = String(m).split('-');
        const y = parseInt(parts[0] || 0, 10);
        const mth = parseInt(parts[1] || 1, 10);
        const date = new Date(y, mth - 1, 1);
        return date.toLocaleString('en-US', { month: 'short', year: 'numeric' });
      });
      const dataByMethod = dataObj.series || {};
      const colors = {
        'Cash': '#1abc9c',
        'MTN MoMo': '#f1c40f',
        'Airtel Money': '#e74c3c',
        'Bank': '#3498db'
      };

      const getThemeColors = () => {
        const isDark = document.body.classList.contains('dark-mode');
        return {
          textColor: isDark ? '#ffffff' : '#000000',
          gridColor: isDark ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.1)'
        };
      };

      const makeOptions = (title) => {
        const { textColor, gridColor } = getThemeColors();
        return {
          type: 'bar',
          data: {
            labels: chart_labels,
            datasets: [{
              label: title,
              data: dataByMethod[title] || [],
              backgroundColor: (colors[title] || '#666') + '88',
              borderColor: colors[title] || '#666',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { ticks: { color: textColor }, grid: { color: gridColor } },
              y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }
            },
            plugins: {
              legend: { display: false, labels: { color: textColor } },
              tooltip: { titleColor: textColor, bodyColor: textColor }
            }
          }
        };
      };

      const charts = {};
      const makeChart = (id, title) => {
        const el = document.getElementById(id)?.getContext('2d');
        if (!el || typeof Chart === 'undefined') return;
        charts[id] = new Chart(el, makeOptions(title));
      };

      // Wait for Chart to be available if needed
      function tryInit() {
        if (typeof Chart === 'undefined') {
          setTimeout(tryInit, 120);
          return;
        }
        makeChart('chartCash', 'Cash');
        makeChart('chartMtn', 'MTN MoMo');
        makeChart('chartAirtel', 'Airtel Money');
        makeChart('chartBank', 'Bank');

        const applyThemeToCharts = () => {
          const { textColor, gridColor } = getThemeColors();
          Object.values(charts).forEach(ch => {
            ch.options.scales.x.ticks.color = textColor;
            ch.options.scales.y.ticks.color = textColor;
            ch.options.scales.x.grid.color = gridColor;
            ch.options.scales.y.grid.color = gridColor;
            if (ch.options.plugins && ch.options.plugins.legend && ch.options.plugins.legend.labels) {
              ch.options.plugins.legend.labels.color = textColor;
            }
            if (ch.options.plugins && ch.options.plugins.tooltip) {
              ch.options.plugins.tooltip.titleColor = textColor;
              ch.options.plugins.tooltip.bodyColor = textColor;
            }
            ch.update();
          });
        };

        const mo = new MutationObserver(applyThemeToCharts);
        mo.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        window.addEventListener('storage', applyThemeToCharts);
      }

      tryInit();
    })();

  }); // onReady
})(); 
