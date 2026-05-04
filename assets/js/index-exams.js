(function(){'use strict';
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  ready(function(){
    const card = document.querySelector('.exam-card');
    if(card){
      // subtle entrance for the card
      card.style.opacity = 0; card.style.transform = 'translateY(8px)';
      setTimeout(()=>{ card.style.transition = 'opacity .6s ease, transform .6s cubic-bezier(.2,.9,.2,1)'; card.style.opacity = 1; card.style.transform = 'translateY(0)'; }, 80);
    }

    // animate palette buttons with stagger
    const buttons = Array.from(document.querySelectorAll('.palette-btn'));
    buttons.forEach((b,i)=>{ b.classList.remove('enter'); b.style.opacity=0; setTimeout(()=>{ b.classList.add('enter'); b.style.opacity=1; }, 80 + i*28);
      // click to toggle selected state
      b.addEventListener('click', function(){ buttons.forEach(x=>x.classList.remove('selected')); this.classList.add('selected');
        // small tactile pulse
        this.animate([{ transform: 'scale(1.06)' },{ transform: 'scale(1)' }], { duration:260, easing:'cubic-bezier(.2,.9,.2,1)' });
      });
    });

    // make timer pulse when less than 10 minutes (if data-ends present)
    const demoTimer = document.getElementById('demo-countdown');
    if(demoTimer){
      const valueEl = demoTimer.querySelector('span:last-child');
      // if original inline timer present, just add a gentle breathing effect
      setInterval(()=>{
        // pulse every 6 seconds
        demoTimer.classList.add('pulse');
        setTimeout(()=> demoTimer.classList.remove('pulse'), 1200);
      }, 6000);
    }

    // small hover sparkle: briefly change shadow color on hover for marked items
    buttons.forEach(b=>{
      b.addEventListener('mouseenter', ()=>{ if(b.classList.contains('marked')) b.style.boxShadow = '0 20px 48px rgba(250,200,60,0.12)'; });
      b.addEventListener('mouseleave', ()=>{ b.style.boxShadow = ''; });
    });

    // accessibility: allow keyboard selection
    buttons.forEach(b=>{ b.setAttribute('tabindex', '0'); b.addEventListener('keydown', function(e){ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); this.click(); } }); });
  });
})();
