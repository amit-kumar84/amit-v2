<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Button Test</title>
  <link rel="stylesheet" href="../assets/lib/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/lib/fontawesome/css/all.min.css">
    <style>
        /* Enhanced confirmation modal styles */
        .app-confirm-fade .modal-dialog { transform: translateY(-10px); transition: transform .28s ease; }
        .app-confirm-fade.show .modal-dialog { transform: translateY(0); }
        .app-confirm-card { animation: appConfirmIn .28s cubic-bezier(.2,.9,.2,1); }
        @keyframes appConfirmIn { from { opacity: 0; transform: translateY(-8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    </style>
</head>
<body class="p-5">
<div class="container">
    <h1>Submit Button Test Page</h1>
    <p>This page tests the submit button functionality in isolation.</p>
    
    <hr>
    
    <h3>Test 1: Basic submitExam() Call</h3>
    <button class="btn btn-danger mb-3" onclick="testSubmitExam()">
        <i class="fas fa-paper-plane me-2"></i>Test Submit Button
    </button>
    
    <hr>
    
    <h3>Console Output:</h3>
    <div id="console" class="bg-dark text-light p-3" style="height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; border-radius: 4px;">
        Waiting for output...
    </div>
    
    <hr>
    
    <h3>Test Status:</h3>
    <div id="status" class="alert alert-info">
        Checking functions...
    </div>
</div>

<!-- Global in-app confirmation modal (copied from footer.php) -->
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

<script src="../assets/lib/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
// Mock console to capture logs
const logBuffer = [];
const origLog = console.log;
const origWarn = console.warn;
const origError = console.error;

console.log = function(...args) {
    origLog(...args);
    logBuffer.push('[LOG] ' + args.join(' '));
    updateConsole();
};

console.warn = function(...args) {
    origWarn(...args);
    logBuffer.push('[WARN] ' + args.join(' '));
    updateConsole();
};

console.error = function(...args) {
    origError(...args);
    logBuffer.push('[ERROR] ' + args.join(' '));
    updateConsole();
};

function updateConsole() {
    const el = document.getElementById('console');
    el.textContent = logBuffer.join('\n');
    el.scrollTop = el.scrollHeight;
}

// Mock footer.php appConfirm function - FULL VERSION with icon support
window.appConfirm = function(message, opts = {}) {
    return new Promise((resolve) => {
      const title = opts.title || 'Please confirm';
      const icon = opts.icon || 'info'; // 'info'|'warn'|'danger'|'success'
      const okText = opts.okText || 'OK';
      const cancelText = opts.cancelText || 'Cancel';
      console.log('[appConfirm] Showing modal with icon:', icon);
      
      const msgEl = document.getElementById('appConfirmMessage');
      const titleEl = document.getElementById('appConfirmTitle');
      const iconEl = document.getElementById('appConfirmIcon');
      const okBtn = document.getElementById('appConfirmOk');
      const cancelBtn = document.getElementById('appConfirmCancel');
      
      titleEl.textContent = title;
      msgEl.textContent = message || '';
      okBtn.textContent = okText; 
      cancelBtn.textContent = cancelText;
      cancelBtn.style.display = 'block'; // ensure cancel is visible

      // set icon color/state
      iconEl.innerHTML = '';
      if (icon === 'danger' || icon === 'warn') {
        console.log('[appConfirm] Using DANGER icon (red warning)');
        iconEl.innerHTML = '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#ffe9e9" stroke-width="2" fill="#fff8f8"/><path d="M12 8v4" stroke="#e11d48" stroke-width="1.8" stroke-linecap="round"/><path d="M12 16h.01" stroke="#e11d48" stroke-width="1.8" stroke-linecap="round"/></svg>';
        okBtn.classList.remove('btn-primary', 'btn-success'); 
        okBtn.classList.add('btn-danger');
      } else if (icon === 'success') {
        console.log('[appConfirm] Using SUCCESS icon (green checkmark)');
        iconEl.innerHTML = '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#e9f9ee" stroke-width="2" fill="#f7fffb"/><path d="M9 12l2 2 4-4" stroke="#059669" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        okBtn.classList.remove('btn-primary', 'btn-danger'); 
        okBtn.classList.add('btn-success');
      } else {
        console.log('[appConfirm] Using INFO icon (blue checkmark)');
        iconEl.innerHTML = '<svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="11" stroke="#e9edf5" stroke-width="2" fill="#f8fafc"/><path d="M9 12l2 2 4-4" stroke="#0b74de" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        okBtn.classList.remove('btn-danger','btn-success'); 
        okBtn.classList.add('btn-primary');
      }

      const modalEl = document.getElementById('appConfirmModal');
      const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });

      function cleanup(result) {
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        modal.hide();
        // reset ok button styles
        okBtn.classList.remove('btn-danger','btn-success'); 
        okBtn.classList.add('btn-primary');
        resolve(result);
      }
      function onOk(e) { e && e.preventDefault(); cleanup(true); }
      function onCancel(e) { e && e.preventDefault(); cleanup(false); }
      okBtn.addEventListener('click', onOk);
      cancelBtn.addEventListener('click', onCancel);
      modal.show();
    });
};

// Mock submit URL
const SUBMIT_URL = '../student/submit.php?attempt=1';

// Mock variables from take-exam.php
let currentExamLang = 'en';
let submitted = false;

function _releaseKeyboardLock() {
    console.log('[_releaseKeyboardLock] Released keyboard lock');
}

// submitExam function from lockdown.js (should load but fallback here)
function submitExam() {
  console.log('[Submit] submitExam() called');
  const lang = (typeof currentExamLang !== 'undefined') ? currentExamLang : (document.body.getAttribute('data-lang') || 'en');
  const msg = (lang === 'hi')
    ? 'क्या आप वाकई परीक्षा जमा करना चाहते हैं? यह क्रिया वापस नहीं ली जा सकती।'
    : 'Submit exam now? This cannot be undone.';
  
  const title = (lang === 'hi') ? 'परीक्षा जमा करें' : 'Submit Exam';
  
  // Ensure appConfirm exists - retry if needed. Do not fall back to browser confirm.
  let retries = 5;
  const tryConfirm = () => {
    if (typeof appConfirm === 'function') {
      console.log('[Submit] appConfirm found, showing modal with danger icon');
      appConfirm(msg, { 
        title: title,
        icon: 'danger',
        okText: lang === 'hi' ? 'जमा करें' : 'Submit',
        cancelText: lang === 'hi' ? 'रद्द करें' : 'Cancel'
      }).then(ok => { 
        console.log('[Submit] Modal result:', ok);
        if (!ok) { 
          console.log('[Submit] User cancelled');
          return; 
        }
        console.log('[Submit] User confirmed, starting submission');
        autoSubmit(null); 
      }).catch(e => {
        console.error('[Submit] appConfirm error:', e);
        if (typeof appAlert === 'function') {
          appAlert('Confirmation dialog could not open. Please refresh the page and try again.', {
            title: lang === 'hi' ? 'त्रुटि' : 'Error',
            okText: lang === 'hi' ? 'ठीक है' : 'OK'
          });
        }
      });
    } else if (retries > 0) {
      retries--;
      console.warn('[Submit] appConfirm not ready yet, retrying... (' + retries + ' left)');
      setTimeout(tryConfirm, 100);
    } else {
      console.error('[Submit] appConfirm not found after retries');
      if (typeof appAlert === 'function') {
        appAlert('Confirmation dialog is not ready yet. Please refresh the page and try again.', {
          title: lang === 'hi' ? 'त्रुटि' : 'Error',
          okText: lang === 'hi' ? 'ठीक है' : 'OK'
        });
      }
    }
  };
  
  tryConfirm();
}

function autoSubmit(reason) {
  console.log('[Submit] autoSubmit() called, submitted =', submitted);
  if (submitted) {
    console.log('[Submit] Already submitted, ignoring');
    return;
  }
  submitted = true;
  _releaseKeyboardLock();
  if (reason) {
    try { if (typeof appAlert === 'function') appAlert(reason); else alert(reason); } catch(e) { alert(reason); }
  }
  try { document.getElementById('cam').srcObject?.getTracks().forEach(t => t.stop()); } catch(e){}
  console.log('[Submit] Would redirect to:', SUBMIT_URL);
  console.log('[Submit] SUCCESS - Submission flow complete!');
  document.getElementById('status').innerHTML = '<div class="alert alert-success"><strong>✅ Success!</strong> Submit flow completed successfully. Would redirect to submit.php.</div>';
}

function testSubmitExam() {
    console.log('========== TEST START ==========');
    console.log('Testing submitExam() function...');
    submitExam();
}

// Initial status check
window.addEventListener('load', function() {
    let status = '<div class="alert alert-info">';
    status += '<strong>Function Check:</strong><br>';
    status += '✓ submitExam: ' + (typeof submitExam === 'function' ? '✅ Loaded' : '❌ Missing') + '<br>';
    status += '✓ autoSubmit: ' + (typeof autoSubmit === 'function' ? '✅ Loaded' : '❌ Missing') + '<br>';
    status += '✓ appConfirm: ' + (typeof appConfirm === 'function' ? '✅ Loaded' : '❌ Missing') + '<br>';
    status += '<strong>Test Instructions:</strong><br>';
    status += '1. Click "Test Submit Button" above<br>';
    status += '2. A confirmation dialog will appear<br>';
    status += '3. Click OK to proceed with submission<br>';
    status += '4. Watch the console output below for status messages<br>';
    status += '</div>';
    document.getElementById('status').innerHTML = status;
    console.log('[Init] Test page loaded - ready to test');
});
</script>
</body>
</html>
