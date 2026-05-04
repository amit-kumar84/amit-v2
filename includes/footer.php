<?php $PUBLIC = $PUBLIC ?? false; ?>
<?php if ($PUBLIC): ?>
<footer class="site-footer">
  <div class="tricolor"><span></span><span></span><span></span></div>
  <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" class="footer-logo" alt="BEL">
      <div>
        <div class="fw-bold text-white"><?= t('brand') ?></div>
        <div class="small text-secondary">© <?= date('Y') ?> · <?= t('footer_enterprise') ?></div>
      </div>
    </div>
    <small class="text-secondary"><?= t('footer_auth') ?></small>
  </div>
</footer>
<?php endif; ?>
<!-- Bootstrap 5.3.2 JS - Local Offline Copy -->
<script src="<?= url('assets/lib/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script>
  (() => {
    const defaultDelay = 5000;
    document.querySelectorAll('.flash-message').forEach((message) => {
      const delay = parseInt(message.dataset.autoDismiss || defaultDelay, 10);
      window.setTimeout(() => {
        message.classList.remove('show');
        window.setTimeout(() => message.remove(), 350);
      }, delay);
    });
  })();
</script>
  <script>
    (function(){
      try {
        var _user = <?= json_encode($_SESSION['user'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
        if (_user && _user.role === 'admin') {
          var _perms = <?= json_encode(load_admin_perms((int)($_SESSION['user']['id'] ?? 0))['perms'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
          window.currentAdminPerms = _perms || {};
          window.hasPerm = function(section, action) {
            if (!_user) return false;
            if (!!_user.is_super) return true;
            try { return !!(window.currentAdminPerms && window.currentAdminPerms[section] && window.currentAdminPerms[section][action]); } catch(e) { return false; }
          };
          document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('[data-perm-section]').forEach(function(el){
              var sec = el.getAttribute('data-perm-section');
              var act = el.getAttribute('data-perm-action') || 'view';
              if (!window.hasPerm(sec, act)) el.style.display = 'none';
            });
          });
        }
      } catch(e) {}
    })();
  </script>
<!-- Global in-app confirmation modal and helper -->
<div class="modal fade app-confirm-fade" id="appConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg app-confirm-card">
      <div class="modal-body p-4 text-center">
        <div class="app-confirm-icon mb-3" id="appConfirmIcon"> 
          <svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#e9edf5" stroke-width="2" fill="#f8fafc"/><path d="M9 12l2 2 4-4" stroke="#0b74de" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h5 class="modal-title mb-2" id="appConfirmTitle">Please confirm</h5>
        <p id="appConfirmMessage" class="mb-3 text-muted small"></p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal" id="appConfirmCancel">Cancel</button>
          <button type="button" class="btn btn-primary btn-sm px-3" id="appConfirmOk">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>
<style>
/* Enhanced confirmation modal styles */
.app-confirm-fade .modal-dialog { transform: translateY(-10px); transition: transform .28s ease; }
.app-confirm-fade.show .modal-dialog { transform: translateY(0); }
.app-confirm-card { border-radius: 12px; overflow: hidden; }
.app-confirm-icon { width: 64px; height: 64px; display:flex; align-items:center; justify-content:center; margin:0 auto; background: linear-gradient(135deg, rgba(11,116,222,0.06), rgba(11,116,222,0.02)); border-radius: 50%; transition: transform .28s cubic-bezier(.2,.9,.2,1); }
.app-confirm-fade.show .app-confirm-icon { transform: scale(1.06); }
.app-confirm-card .modal-title { font-weight:700; color:#0f172a; }
.app-confirm-card .modal-body { background: linear-gradient(180deg, #ffffff, #fbfdff); }
.app-confirm-card .btn-primary { box-shadow: 0 6px 18px rgba(11,116,222,0.14); }
.app-confirm-card { animation: appConfirmIn .28s cubic-bezier(.2,.9,.2,1); }
@keyframes appConfirmIn { from { opacity: 0; transform: translateY(-8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
</style>
<script>
  function ensureAppConfirmDom() {
    const modalEl = document.getElementById('appConfirmModal');
    if (!modalEl) return null;

    let msgEl = document.getElementById('appConfirmMessage');
    let titleEl = document.getElementById('appConfirmTitle');
    let iconEl = document.getElementById('appConfirmIcon');
    let okBtn = document.getElementById('appConfirmOk');
    let cancelBtn = document.getElementById('appConfirmCancel');

    if (!msgEl || !titleEl || !iconEl || !okBtn || !cancelBtn) {
      const content = modalEl.querySelector('.modal-content');
      if (!content) return null;
      content.innerHTML = '' +
        '<div class="modal-body p-4 text-center">' +
          '<div class="app-confirm-icon mb-3" id="appConfirmIcon"></div>' +
          '<h5 class="modal-title mb-2" id="appConfirmTitle">Please confirm</h5>' +
          '<p id="appConfirmMessage" class="mb-3 text-muted small"></p>' +
          '<div class="d-flex justify-content-center gap-2">' +
            '<button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal" id="appConfirmCancel">Cancel</button>' +
            '<button type="button" class="btn btn-primary btn-sm px-3" id="appConfirmOk">OK</button>' +
          '</div>' +
        '</div>';

      msgEl = document.getElementById('appConfirmMessage');
      titleEl = document.getElementById('appConfirmTitle');
      iconEl = document.getElementById('appConfirmIcon');
      okBtn = document.getElementById('appConfirmOk');
      cancelBtn = document.getElementById('appConfirmCancel');
    }

    if (!msgEl || !titleEl || !iconEl || !okBtn || !cancelBtn) return null;
    return { modalEl, msgEl, titleEl, iconEl, okBtn, cancelBtn };
  }

  // appConfirm: returns Promise<boolean>
  window.appConfirm = function(message, opts = {}) {
    return new Promise((resolve) => {
      const title = opts.title || 'Please confirm';
      const icon = opts.icon || 'info'; // 'info'|'warn'|'danger'|'success'
      const okText = opts.okText || 'OK';
      const cancelText = opts.cancelText || 'Cancel';
      const refs = ensureAppConfirmDom();
      if (!refs) { resolve(false); return; }
      const { modalEl, msgEl, titleEl, iconEl, okBtn, cancelBtn } = refs;
      titleEl.textContent = title;
      msgEl.textContent = message || '';
      okBtn.textContent = okText; cancelBtn.textContent = cancelText;

      // set icon color/state
      iconEl.innerHTML = '';
      if (icon === 'danger' || icon === 'warn') {
        iconEl.innerHTML = '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#ffe9e9" stroke-width="2" fill="#fff8f8"/><path d="M12 8v4" stroke="#e11d48" stroke-width="1.8" stroke-linecap="round"/><path d="M12 16h.01" stroke="#e11d48" stroke-width="1.8" stroke-linecap="round"/></svg>';
        okBtn.classList.remove('btn-primary'); okBtn.classList.add('btn-danger');
      } else if (icon === 'success') {
        iconEl.innerHTML = '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#e9f9ee" stroke-width="2" fill="#f7fffb"/><path d="M9 12l2 2 4-4" stroke="#059669" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        okBtn.classList.remove('btn-primary'); okBtn.classList.add('btn-success');
      } else {
        iconEl.innerHTML = '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#e9edf5" stroke-width="2" fill="#f8fafc"/><path d="M9 12l2 2 4-4" stroke="#0b74de" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        okBtn.classList.remove('btn-danger','btn-success'); okBtn.classList.add('btn-primary');
      }

      const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });

      function cleanup(result) {
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        modal.hide();
        // reset ok button styles
        okBtn.classList.remove('btn-danger','btn-success'); okBtn.classList.add('btn-primary');
        resolve(result);
      }
      function onOk(e) { e && e.preventDefault(); cleanup(true); }
      function onCancel(e) { e && e.preventDefault(); cleanup(false); }
      okBtn.addEventListener('click', onOk);
      cancelBtn.addEventListener('click', onCancel);
      modal.show();
    });
  };

  // appAlert: simple informational modal (returns Promise<void>)
  window.appAlert = function(message, opts = {}) {
    return new Promise((resolve) => {
      const title = opts.title || '';
      const okText = opts.okText || 'OK';
      const refs = ensureAppConfirmDom();
      if (!refs) { resolve(); return; }
      const { modalEl, msgEl, titleEl, iconEl, okBtn, cancelBtn } = refs;

      titleEl.textContent = title;
      msgEl.textContent = message || '';
      okBtn.textContent = okText;
      // hide cancel for alert
      cancelBtn.style.display = 'none';

      // default info icon
      iconEl.innerHTML = '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#e9edf5" stroke-width="2" fill="#f8fafc"/><path d="M11 10h2v6h-2zM11 7h2v2h-2z" stroke="#0b74de" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      okBtn.classList.remove('btn-danger','btn-success'); okBtn.classList.add('btn-primary');

      const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });

      function cleanup() {
        okBtn.removeEventListener('click', onOk);
        // restore cancel visibility
        cancelBtn.style.display = '';
        modal.hide();
        resolve();
      }
      function onOk(e) { e && e.preventDefault(); cleanup(); }
      okBtn.addEventListener('click', onOk);
      modal.show();
    });
  };
</script>
</body></html>
