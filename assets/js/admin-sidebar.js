(function(){'use strict';
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  ready(function(){
    const sb = document.querySelector('.admin-sidebar'); if(!sb) return;
    // create active indicator
    let indicator = sb.querySelector('.active-indicator'); if(!indicator){ indicator = document.createElement('div'); indicator.className = 'active-indicator'; sb.appendChild(indicator); }
    const links = Array.from(sb.querySelectorAll('.nav-link'));
    function updateIndicator(el){
      if(!el || sb.classList.contains('collapsed')){ indicator.style.display='none'; return; }
      indicator.style.display='block';
      const r = el.getBoundingClientRect();
      const parentR = sb.getBoundingClientRect();
      // position indicator flush with the link, matching its height
      indicator.style.top = (r.top - parentR.top) + 'px';
      indicator.style.height = r.height + 'px';
    }
    // determine active link: prefer server-side class, then match by href to current URL, else fallback to first link
    let active = links.find(l => l.classList.contains('active'));
    if(!active){
      const cur = (location.pathname || '').split('/').pop() || '';
      active = links.find(l => {
        try{
          const href = l.getAttribute('href') || l.href || '';
          return href.endsWith(cur) || (cur && href.indexOf(cur) !== -1);
        }catch(e){ return false; }
      }) || links[0];
      links.forEach(x=>x.classList.remove('active'));
      if(active) active.classList.add('active');
    }
    updateIndicator(active);
    // attach click handlers to animate
    links.forEach(l => {
      l.addEventListener('click', function(e){ links.forEach(x=>x.classList.remove('active')); this.classList.add('active'); active = this; updateIndicator(this); });
      // keyboard friendly
      l.setAttribute('tabindex', '0');
      l.addEventListener('keydown', function(ev){ if(ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); this.click(); } if(ev.key === 'ArrowDown'){ ev.preventDefault(); const next = links[(links.indexOf(this)+1)%links.length]; next.focus(); } if(ev.key === 'ArrowUp'){ ev.preventDefault(); const prev = links[(links.indexOf(this)-1+links.length)%links.length]; prev.focus(); } });
    });

    // responsive collapsed toggle when width small
    function applyCollapse(){ if(window.innerWidth < 768){ sb.classList.add('collapsed'); } else { sb.classList.remove('collapsed'); } updateIndicator(active); }
    applyCollapse(); window.addEventListener('resize', applyCollapse);

    // animate indicator on initial load after small delay to allow layout
    setTimeout(()=> updateIndicator(active), 220);
  });
})();
