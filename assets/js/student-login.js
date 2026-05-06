(function(){'use strict';
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  ready(function(){
    const form = document.querySelector('.login-split .right form');
    const left = document.querySelector('.login-split .left');
    if(form) setTimeout(()=> form.classList.add('visible'), 120);

    if(left){
      const shapesWrap = document.createElement('div'); shapesWrap.className = 'floating-shapes';
      const s1 = document.createElement('div'); s1.className = 'shape';
      const s2 = document.createElement('div'); s2.className = 'shape alt';
      const s3 = document.createElement('div'); s3.className = 'shape small';
      shapesWrap.appendChild(s1); shapesWrap.appendChild(s2); shapesWrap.appendChild(s3);
      left.appendChild(shapesWrap);
    }

    const pwd = document.querySelector('input[type="password"][name="password"]');
    if(pwd){
      const wrap = document.createElement('div'); wrap.className = 'pwd-wrap';
      pwd.parentNode.insertBefore(wrap, pwd);
      wrap.appendChild(pwd);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pwd-toggle';
      btn.setAttribute('aria-label','Show password');
      btn.innerHTML = '<i class="fas fa-eye"></i>';
      wrap.appendChild(btn);
      btn.addEventListener('click', function(){
        if(pwd.type==='password'){
          pwd.type='text';
          this.innerHTML = '<i class="fas fa-eye-slash"></i>';
          this.setAttribute('aria-label','Hide password');
        } else {
          pwd.type='password';
          this.innerHTML = '<i class="fas fa-eye"></i>';
          this.setAttribute('aria-label','Show password');
        }
      });
    }

    const submit = form && form.querySelector('button[type="submit"], .btn-navy');
    if(submit && form){
      form.addEventListener('submit', function(){
        try{
          submit.disabled = true;
          submit.classList.add('submitting');
          const spinner = document.createElement('span');
          spinner.className = 'spinner-border spinner-border-sm ms-2';
          spinner.setAttribute('role','status');
          spinner.setAttribute('aria-hidden','true');
          submit.appendChild(spinner);
        }catch(err){/* ignore */}
      });
    }

    document.querySelectorAll('.login-split .right .form-control').forEach(inp=>{
      inp.addEventListener('focus', ()=> inp.style.boxShadow = '0 14px 38px rgba(0,169,224,0.06)');
      inp.addEventListener('blur', ()=> inp.style.boxShadow = '');
    });
  });
})();