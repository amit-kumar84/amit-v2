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
  <div class="modal-dialog modal-dialog-centered app-confirm-dialog">
    <div class="modal-content border-0 shadow app-confirm-card">
      <div class="app-confirm-header"></div>
      <div class="modal-body p-5 text-center">
        <div class="app-confirm-icon-wrapper mb-4" id="appConfirmIconWrapper">
          <div class="app-confirm-icon" id="appConfirmIcon"></div>
        </div>
        <h5 class="modal-title mb-3 app-confirm-title" id="appConfirmTitle">Please confirm</h5>
        <p id="appConfirmMessage" class="mb-4 app-confirm-message"></p>
        <div class="d-flex justify-content-center gap-3 app-confirm-buttons">
          <button type="button" class="btn btn-outline-secondary app-confirm-cancel px-5" data-bs-dismiss="modal" id="appConfirmCancel">Cancel</button>
          <button type="button" class="btn btn-primary app-confirm-ok px-5" id="appConfirmOk">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>
<style>
/* ===== ENHANCED CONFIRMATION MODAL - STYLISH & ANIMATED ===== */

.app-confirm-fade {
  --bs-backdrop-bg: rgba(15, 23, 42, 0.85);
}

.app-confirm-dialog {
  max-width: 420px;
  transform: scale(0.92) translateY(-20px);
  opacity: 0;
  transition: all 0.35s cubic-bezier(0.2, 0.9, 0.2, 1);
}

.app-confirm-fade.show .app-confirm-dialog {
  transform: scale(1) translateY(0);
  opacity: 1;
}

.app-confirm-card {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25), 0 0 1px rgba(15, 23, 42, 0.1);
  background: #ffffff;
  position: relative;
}

/* Gradient header bar */
.app-confirm-header {
  height: 4px;
  background: linear-gradient(90deg, #FF9933, #3B6BA8, #FF9933);
  background-size: 200% 100%;
  animation: headerFlow 3s ease infinite;
}

@keyframes headerFlow {
  0% { background-position: 0% 0; }
  50% { background-position: 100% 0; }
  100% { background-position: 0% 0; }
}

.app-confirm-card .modal-body {
  background: linear-gradient(135deg, #ffffff 0%, #fbfdff 50%, #f7f9ff 100%);
  padding: 2.5rem !important;
}

/* Icon wrapper with animated pulse */
.app-confirm-icon-wrapper {
  position: relative;
  width: 88px;
  height: 88px;
  margin-left: auto;
  margin-right: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: iconBounce 0.6s cubic-bezier(0.2, 0.9, 0.2, 1);
}

@keyframes iconBounce {
  0% {
    transform: scale(0) rotate(-20deg);
    opacity: 0;
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1) rotate(0);
    opacity: 1;
  }
}

/* Icon background with pulse animation */
.app-confirm-icon {
  width: 88px;
  height: 88px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(11, 116, 222, 0.12), rgba(11, 116, 222, 0.05));
  position: relative;
  overflow: hidden;
}

.app-confirm-icon::before {
  content: '';
  position: absolute;
  inset: -50%;
  background: conic-gradient(from 0deg, transparent 0deg, rgba(11, 116, 222, 0.2) 90deg, transparent 360deg);
  animation: iconRotate 4s linear infinite;
  opacity: 0;
}

.app-confirm-fade.show .app-confirm-icon::before {
  opacity: 1;
}

@keyframes iconRotate {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.app-confirm-icon svg {
  position: relative;
  z-index: 1;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

/* Icon animations based on type */
.app-confirm-icon.danger::after,
.app-confirm-icon.warn::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: radial-gradient(circle, transparent 60%, rgba(225, 29, 72, 0.15) 100%);
  animation: dangerPulse 2s ease-in-out infinite;
}

@keyframes dangerPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.15); }
}

/* Title styling */
.app-confirm-title {
  font-weight: 700;
  color: #0f172a;
  font-size: 1.3rem;
  letter-spacing: -0.3px;
  text-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

/* Message styling */
.app-confirm-message {
  color: #475569;
  font-size: 0.95rem;
  line-height: 1.6;
  margin-bottom: 0 !important;
}

/* Button group styling */
.app-confirm-buttons {
  gap: 1rem !important;
}

/* Cancel button */
.app-confirm-cancel {
  border: 1.5px solid #cbd5e1;
  color: #64748b;
  background: transparent;
  font-weight: 600;
  padding: 0.6rem 1.5rem !important;
  border-radius: 8px;
  transition: all 0.25s ease;
  position: relative;
  overflow: hidden;
}

.app-confirm-cancel::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
  opacity: 0;
  transition: opacity 0.25s ease;
  z-index: -1;
}

.app-confirm-cancel:hover {
  border-color: #94a3b8;
  color: #334155;
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(100, 116, 139, 0.15);
}

.app-confirm-cancel:hover::before {
  opacity: 1;
}

.app-confirm-cancel:active {
  transform: translateY(0);
  box-shadow: 0 4px 8px rgba(100, 116, 139, 0.1);
}

/* OK/Confirm button */
.app-confirm-ok {
  background: linear-gradient(135deg, #0b74de 0%, #0860d0 100%);
  border: 0;
  color: white;
  font-weight: 600;
  padding: 0.6rem 1.5rem !important;
  border-radius: 8px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 20px rgba(11, 116, 222, 0.3);
  transition: all 0.25s ease;
}

.app-confirm-ok::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), transparent);
  opacity: 0;
  transition: opacity 0.25s ease;
}

.app-confirm-ok:hover {
  background: linear-gradient(135deg, #0860d0 0%, #074bb8 100%);
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(11, 116, 222, 0.4);
}

.app-confirm-ok:hover::before {
  opacity: 1;
}

.app-confirm-ok:active {
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(11, 116, 222, 0.25);
}

/* Danger button styling */
.app-confirm-ok.btn-danger {
  background: linear-gradient(135deg, #e11d48 0%, #dc1d45 100%);
  box-shadow: 0 8px 20px rgba(225, 29, 72, 0.3);
}

.app-confirm-ok.btn-danger:hover {
  background: linear-gradient(135deg, #dc1d45 0%, #c71531 100%);
  box-shadow: 0 12px 28px rgba(225, 29, 72, 0.4);
}

/* Success button styling */
.app-confirm-ok.btn-success {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
}

.app-confirm-ok.btn-success:hover {
  background: linear-gradient(135deg, #047857 0%, #046043 100%);
  box-shadow: 0 12px 28px rgba(5, 150, 105, 0.4);
}

/* Responsive adjustments */
@media (max-width: 576px) {
  .app-confirm-dialog {
    margin: 1rem;
    max-width: calc(100% - 2rem);
  }
  
  .app-confirm-card .modal-body {
    padding: 1.75rem !important;
  }
  
  .app-confirm-title {
    font-size: 1.15rem;
  }
  
  .app-confirm-buttons {
    flex-direction: column-reverse;
    gap: 0.75rem !important;
  }
  
  .app-confirm-cancel,
  .app-confirm-ok {
    width: 100%;
  }
}

/* Icon specific color backgrounds */
.app-confirm-icon.info {
  background: linear-gradient(135deg, rgba(11, 116, 222, 0.12), rgba(11, 116, 222, 0.05));
}

.app-confirm-icon.danger,
.app-confirm-icon.warn {
  background: linear-gradient(135deg, rgba(225, 29, 72, 0.12), rgba(225, 29, 72, 0.05));
}

.app-confirm-icon.success {
  background: linear-gradient(135deg, rgba(5, 150, 105, 0.12), rgba(5, 150, 105, 0.05));
}

/* Shake animation for critical actions */
.app-confirm-icon.danger::after {
  animation: dangerShake 0.5s cubic-bezier(0.36, 0, 0.66, 1);
}

@keyframes dangerShake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-2px); }
  75% { transform: translateX(2px); }
}

/* Success checkmark animation */
.app-confirm-icon.success svg {
  animation: successCheckmark 0.6s cubic-bezier(0.2, 0.9, 0.2, 1) forwards;
}

@keyframes successCheckmark {
  0% {
    opacity: 0;
    transform: scale(0.5) rotate(-45deg);
  }
  50% {
    transform: scale(1.2) rotate(10deg);
  }
  100% {
    opacity: 1;
    transform: scale(1) rotate(0deg);
  }
}
</style>
<script>
  function ensureAppConfirmDom() {
    const modalEl = document.getElementById('appConfirmModal');
    if (!modalEl) return null;

    let msgEl = document.getElementById('appConfirmMessage');
    let titleEl = document.getElementById('appConfirmTitle');
    let iconEl = document.getElementById('appConfirmIcon');
    let iconWrapperEl = document.getElementById('appConfirmIconWrapper');
    let okBtn = document.getElementById('appConfirmOk');
    let cancelBtn = document.getElementById('appConfirmCancel');

    if (!msgEl || !titleEl || !iconEl || !okBtn || !cancelBtn || !iconWrapperEl) {
      const content = modalEl.querySelector('.modal-content');
      if (!content) return null;
      content.innerHTML = '' +
        '<div class="app-confirm-header"></div>' +
        '<div class="modal-body p-5 text-center">' +
          '<div class="app-confirm-icon-wrapper mb-4" id="appConfirmIconWrapper">' +
            '<div class="app-confirm-icon" id="appConfirmIcon"></div>' +
          '</div>' +
          '<h5 class="modal-title mb-3 app-confirm-title" id="appConfirmTitle">Please confirm</h5>' +
          '<p id="appConfirmMessage" class="mb-4 app-confirm-message"></p>' +
          '<div class="d-flex justify-content-center gap-3 app-confirm-buttons">' +
            '<button type="button" class="btn btn-outline-secondary app-confirm-cancel px-5" data-bs-dismiss="modal" id="appConfirmCancel">Cancel</button>' +
            '<button type="button" class="btn btn-primary app-confirm-ok px-5" id="appConfirmOk">OK</button>' +
          '</div>' +
        '</div>';

      msgEl = document.getElementById('appConfirmMessage');
      titleEl = document.getElementById('appConfirmTitle');
      iconEl = document.getElementById('appConfirmIcon');
      iconWrapperEl = document.getElementById('appConfirmIconWrapper');
      okBtn = document.getElementById('appConfirmOk');
      cancelBtn = document.getElementById('appConfirmCancel');
    }

    if (!msgEl || !titleEl || !iconEl || !okBtn || !cancelBtn || !iconWrapperEl) return null;
    return { modalEl, msgEl, titleEl, iconEl, iconWrapperEl, okBtn, cancelBtn };
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
      const { modalEl, msgEl, titleEl, iconEl, iconWrapperEl, okBtn, cancelBtn } = refs;
      titleEl.textContent = title;
      msgEl.textContent = message || '';
      okBtn.textContent = okText; cancelBtn.textContent = cancelText;

      // Remove previous icon classes and set new ones
      iconEl.className = 'app-confirm-icon';
      
      // set icon color/state with better animations
      iconEl.innerHTML = '';
      if (icon === 'danger' || icon === 'warn') {
        iconEl.classList.add('danger');
        iconEl.innerHTML = '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#e11d48" stroke-width="2" fill="#ffe9e9" opacity="0.8"/><path d="M12 8v4" stroke="#e11d48" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17" r="0.8" fill="#e11d48"/></svg>';
        okBtn.classList.remove('btn-primary', 'btn-success'); 
        okBtn.classList.add('btn-danger');
      } else if (icon === 'success') {
        iconEl.classList.add('success');
        iconEl.innerHTML = '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#059669" stroke-width="2" fill="#e9f9ee" opacity="0.8"/><path d="M8 12l3 3 5-5" stroke="#059669" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        okBtn.classList.remove('btn-primary', 'btn-danger'); 
        okBtn.classList.add('btn-success');
      } else {
        iconEl.classList.add('info');
        iconEl.innerHTML = '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#0b74de" stroke-width="2" fill="#e9edf5" opacity="0.8"/><path d="M12 8v4" stroke="#0b74de" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17" r="0.8" fill="#0b74de"/></svg>';
        okBtn.classList.remove('btn-danger', 'btn-success'); 
        okBtn.classList.add('btn-primary');
      }

      const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });

      function cleanup(result) {
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        modal.hide();
        // reset ok button styles
        okBtn.classList.remove('btn-danger', 'btn-success'); 
        okBtn.classList.add('btn-primary');
        iconEl.className = 'app-confirm-icon';
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
      const { modalEl, msgEl, titleEl, iconEl, iconWrapperEl, okBtn, cancelBtn } = refs;

      titleEl.textContent = title;
      msgEl.textContent = message || '';
      okBtn.textContent = okText;
      // hide cancel for alert
      cancelBtn.style.display = 'none';

      // Set info icon
      iconEl.className = 'app-confirm-icon info';
      iconEl.innerHTML = '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#0b74de" stroke-width="2" fill="#e9edf5" opacity="0.8"/><path d="M12 8v4" stroke="#0b74de" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17" r="0.8" fill="#0b74de"/></svg>';
      okBtn.classList.remove('btn-danger', 'btn-success'); 
      okBtn.classList.add('btn-primary');

      const modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });

      function cleanup() {
        okBtn.removeEventListener('click', onOk);
        // restore cancel visibility
        cancelBtn.style.display = '';
        modal.hide();
        iconEl.className = 'app-confirm-icon';
        resolve();
      }
      function onOk(e) { e && e.preventDefault(); cleanup(); }
      okBtn.addEventListener('click', onOk);
      modal.show();
    });
  };
</script>
</body></html>
