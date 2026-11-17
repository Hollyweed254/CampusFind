// Styled confirmation modal for destructive actions: <button data-confirm="..." data-confirm-title="...">
(function () {
  var modalEl = document.getElementById('confirmModal');
  if (!modalEl || !window.bootstrap) return;
  var modal = new bootstrap.Modal(modalEl), pending = null;
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    e.preventDefault();
    pending = el;
    document.getElementById('confirmTitle').textContent = el.getAttribute('data-confirm-title') || 'Are you sure?';
    document.getElementById('confirmText').textContent = el.getAttribute('data-confirm');
    modal.show();
  });
  document.getElementById('confirmOk').addEventListener('click', function () {
    if (!pending) return;
    var form = pending.form, btn = pending;
    modal.hide();
    if (form) form.requestSubmit(btn); else if (btn.href) location.href = btn.href;
  });
})();

// Reason modal for admin reject/remove: <button data-reason data-id data-action data-title data-back>
(function () {
  var el = document.getElementById('reasonModal');
  if (!el || !window.bootstrap) return;
  var modal = new bootstrap.Modal(el);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-reason]');
    if (!b) return;
    e.preventDefault();
    var remove = b.dataset.action === 'remove';
    document.getElementById('reasonId').value = b.dataset.id;
    document.getElementById('reasonAction').value = b.dataset.action;
    document.getElementById('reasonBack').value = b.dataset.back || '';
    document.getElementById('reasonNotes').value = '';
    document.getElementById('reasonTitle').textContent = remove ? 'Remove this listing?' : 'Reject this listing?';
    document.getElementById('reasonText').textContent = b.dataset.title + ' will ' + (remove ? 'no longer appear in the marketplace.' : 'not be published until it is edited and approved.');
    document.getElementById('reasonForm').action = b.dataset.url;
    modal.show();
  });
})();

// Listing form: show condition only for products; adjust price hint
(function () {
  var cat = document.getElementById('category');
  if (!cat) return;
  var cond = document.getElementById('condBox'), help = document.getElementById('priceHelp');
  function sync() {
    var t = cat.options[cat.selectedIndex] ? cat.options[cat.selectedIndex].dataset.type : '';
    cond.style.display = (t === 'service') ? 'none' : '';
    help.textContent = t === 'product' ? 'Required for products.' : 'Optional for services. Leave blank to show "Contact for price".';
  }
  cat.addEventListener('change', sync); sync();
})();

// Auto-dismiss success/info alerts after a few seconds; keep errors until closed.
document.querySelectorAll('.alert-dismissible.alert-success, .alert-dismissible.alert-info').forEach(function (el) {
  setTimeout(function () {
    if (!el.isConnected) return;
    if (window.bootstrap) bootstrap.Alert.getOrCreateInstance(el).close();
    else el.remove();
  }, 4000);
});