document.addEventListener('DOMContentLoaded', function () {
  const branchSelect = document.getElementById('branch_id');
  const staffSelect = document.getElementById('staff_id');

  if (branchSelect && staffSelect) {
    branchSelect.addEventListener('change', function () {
      const branchId = this.value;
      Array.from(staffSelect.options).forEach(option => {
        const matches = option.dataset && option.dataset.branch == branchId;
        option.style.display = matches || option.value === '' ? '' : 'none';
      });
      staffSelect.value = '';
    });
  }

  // Tab state persistence using Bootstrap events
  var tillTabs = document.getElementById('tillTabs');
  if (tillTabs) {
    var tabButtons = tillTabs.querySelectorAll('button[data-bs-toggle="tab"]');
    tabButtons.forEach(function (btn) {
      btn.addEventListener('shown.bs.tab', function (e) {
        var tabId = e.target.getAttribute('data-bs-target').replace('#', '');
        var url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        history.replaceState(null, '', url);
      });
    });
  }
});
