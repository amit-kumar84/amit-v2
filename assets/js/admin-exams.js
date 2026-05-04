// Admin Exams UI interactions: entrance animations, card hover, quick filters
(function(){
  'use strict';
  function onReady(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }

  onReady(function(){
    // Stagger entrance animation
    const cards = Array.from(document.querySelectorAll('.exam-card'));
    cards.forEach((c, i) => { c.classList.add('will-enter'); c.style.animationDelay = (i*60)+'ms'; });

    // IntersectionObserver to reveal cards only when visible (for long lists)
    try {
      const obs = new IntersectionObserver((entries)=>{
        entries.forEach(ent => { if(ent.isIntersecting){ ent.target.classList.remove('will-enter'); ent.target.classList.add('entered'); obs.unobserve(ent.target); } });
      }, { threshold: 0.06 });
      document.querySelectorAll('.exam-card').forEach(c=>obs.observe(c));
    } catch(e) { /* noop */ }

    // Simple keyboard shortcut: focus search on '/'
    document.addEventListener('keydown', function(ev){
      if(ev.key === '/' && document.activeElement.tagName.toLowerCase() !== 'input' && document.activeElement.tagName.toLowerCase() !== 'textarea'){
        const s = document.querySelector('input[name="q"]'); if(s){ s.focus(); s.select(); ev.preventDefault(); }
      }
    });

    // Small hover tilt for pointer devices
    if(window.matchMedia('(hover: hover) and (pointer: fine)').matches){
      document.querySelectorAll('.exam-card').forEach(card => {
        card.addEventListener('mousemove', function(e){
          const r = card.getBoundingClientRect();
          const px = (e.clientX - r.left) / r.width - 0.5; const py = (e.clientY - r.top) / r.height - 0.5;
          card.style.transform = `translateY(-6px) scale(1.01) perspective(600px) rotateX(${(-py*4)}deg) rotateY(${(px*6)}deg)`;
        });
        card.addEventListener('mouseleave', function(){ card.style.transform = ''; });
      });
    }

    // Make codeExists and openCreateExamModal friendly to dynamic content
    window.adminExams = window.adminExams || {};
    window.adminExams.reloadCards = function(){ /* preserve API for future */ };
  });
  
  // Ripple effect for .btn-animated
  onReady(function(){
    document.body.addEventListener('click', function(e){
      const btn = e.target.closest('.btn-animated'); if(!btn) return;
      const rect = btn.getBoundingClientRect(); const d = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left; const y = e.clientY - rect.top;
      const r = document.createElement('span'); r.className = 'ripple'; r.style.width = r.style.height = d+'px'; r.style.left = (x - d/2) + 'px'; r.style.top = (y - d/2) + 'px'; btn.appendChild(r);
      setTimeout(()=> r.remove(), 650);
    });

    // Animate filter select changes
    const statusSelect = document.querySelector('select[name="status"]');
    if(statusSelect){ statusSelect.addEventListener('change', ()=>{
      // flash filter pills area if present
      const container = document.querySelector('.exams-grid') || document.querySelector('.row.g-3');
      if(container){ container.classList.add('filter-anim'); setTimeout(()=>container.classList.remove('filter-anim'), 900); }
      // highlight visible cards briefly
      document.querySelectorAll('.exam-card').forEach((c,i)=>{ setTimeout(()=>{ c.classList.add('card-highlight'); setTimeout(()=>c.classList.remove('card-highlight'),650); }, i*40); });
    }); }

    // Search input visual state
    const search = document.querySelector('.search-input'); if(search){ search.addEventListener('input', ()=>{
      const wrap = search.closest('.search-wrap'); if(!wrap) return; if(search.value.trim()) wrap.classList.add('search-active'); else wrap.classList.remove('search-active');
    }); }
  });
})();
