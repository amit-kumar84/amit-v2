(function(){'use strict';
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  ready(function(){
    // Count-up animation for stat values
    document.querySelectorAll('.stat-count').forEach(el=>{
      const to = Number(el.dataset.to||0);
      const dur = 900; const start = performance.now();
      function step(ts){ const t = Math.min(1,(ts-start)/dur); const v = Math.floor(t*to); el.textContent = v.toLocaleString(); if(t<1) requestAnimationFrame(step); else el.textContent = to.toLocaleString(); }
      requestAnimationFrame(step);
    });

    // Countdown timers for active exams
    function tickCountdown(el, endTs){ const now = Date.now(); const diff = endTs - now; if(diff <= 0){ el.textContent = '00:00'; el.classList.remove('text-danger'); el.classList.add('text-muted'); return; } const s = Math.floor(diff/1000); const mm = Math.floor(s/60); const ss = s%60; el.textContent = String(mm).padStart(2,'0')+ ':' + String(ss).padStart(2,'0'); if(s<=300) el.classList.add('text-danger'); else el.classList.remove('text-danger'); }
    document.querySelectorAll('.exam-countdown').forEach(el=>{
      const ends = Number(el.dataset.ends) || 0; if(!ends) return; tickCountdown(el, ends);
      const id = setInterval(()=>{ tickCountdown(el, ends); if(Date.now()>=ends) clearInterval(id); }, 1000);
    });

    // Simple sparkline renderer (canvas)
    document.querySelectorAll('.sparkline').forEach(can=>{
      try{
        const data = (can.dataset.points||'').split(',').map(n=>Number(n)||0); if(!data.length) return; const ctx = can.getContext('2d'); const w = can.width = can.offsetWidth*devicePixelRatio; const h = can.height = can.offsetHeight*devicePixelRatio; ctx.scale(devicePixelRatio, devicePixelRatio);
        const pad = 4; const sw = can.offsetWidth; const sh = can.offsetHeight;
        const min = Math.min(...data); const max = Math.max(...data); const range = Math.max(1, max-min);
        ctx.clearRect(0,0,sw,sh);
        // area gradient
        const grad = ctx.createLinearGradient(0,0,0,sh); grad.addColorStop(0,'rgba(0,169,224,0.18)'); grad.addColorStop(1,'rgba(0,169,224,0.02)');
        ctx.beginPath(); data.forEach((v,i)=>{ const x = pad + (i/(data.length-1))*(sw-pad*2); const y = pad + (1 - (v-min)/range)*(sh-pad*2); if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y); }); ctx.lineTo(sw-pad, sh-pad); ctx.lineTo(pad, sh-pad); ctx.closePath(); ctx.fillStyle = grad; ctx.fill();
        // line
        ctx.beginPath(); data.forEach((v,i)=>{ const x = pad + (i/(data.length-1))*(sw-pad*2); const y = pad + (1 - (v-min)/range)*(sh-pad*2); if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y); }); ctx.strokeStyle = '#007ea7'; ctx.lineWidth = 2; ctx.stroke();
      }catch(e){ console.error('sparkline',e); }
    });

    // Small entry animation for panels with stagger
    document.querySelectorAll('.panel').forEach((p,i)=>{ p.style.opacity=0; p.style.transform='translateY(16px)'; setTimeout(()=>{ p.style.transition='opacity .45s ease, transform .45s cubic-bezier(.2,.9,.2,1)'; p.style.opacity=1; p.style.transform='translateY(0)'; }, i*60); });

    // Dashboard hero entrance animation
    const hero = document.querySelector('.dashboard-hero');
    if(hero){ hero.style.opacity=0; hero.style.transform='translateY(12px)'; setTimeout(()=>{ hero.style.transition='opacity .6s ease, transform .6s cubic-bezier(.2,.9,.2,1)'; hero.style.opacity=1; hero.style.transform='translateY(0)'; }, 120); }

    // Scroll reveal for stat cards with IntersectionObserver
    const observer = new IntersectionObserver((entries)=>{ entries.forEach(entry=>{ if(entry.isIntersecting){ entry.target.style.transition='all .45s cubic-bezier(.2,.9,.2,1)'; entry.target.style.opacity=1; entry.target.style.transform='translateY(0) scale(1)'; observer.unobserve(entry.target); } }); }, { threshold:0.2 });
    document.querySelectorAll('.stat-card').forEach(card=>{ card.style.opacity='0'; card.style.transform='translateY(20px) scale(.95)'; observer.observe(card); });

    // completed initial animations with enhanced scroll effects
  });
})();
