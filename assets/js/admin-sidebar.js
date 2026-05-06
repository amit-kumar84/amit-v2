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
    function currentPath(){
      return (location.pathname || '').replace(/\/+$/, '').split('/').pop() || '';
    }
    function matchesPath(link, path){
      if (!path) return false;
      try{
        const href = link.getAttribute('href') || link.href || '';
        return href.endsWith('/' + path) || href.endsWith(path) || href.indexOf('/' + path + '?') !== -1 || href.indexOf('/' + path + '#') !== -1;
      }catch(e){ return false; }
    }
    function resolveActiveLink(){
      const path = currentPath();
      const routeGroups = {
        'dashboard.php': ['dashboard.php'],
        'students.php': ['students.php', 'admit-card.php'],
        'exams.php': ['exams.php', 'questions.php', 'preview-question.php'],
        'live-monitor.php': ['live-monitor.php', 'monitor-exam.php', 'export-classroom.php', 'export-classroom-pdf.php'],
        'results.php': ['results.php', 'exam-results-view.php', 'export-results.php'],
        'admins.php': ['admins.php'],
        'trash.php': ['trash.php'],
        'logs.php': ['logs.php']
      };

      const exact = links.find(l => matchesPath(l, path));
      if (exact) return exact;

      for (const [parent, children] of Object.entries(routeGroups)) {
        if (children.includes(path)) {
          const parentLink = links.find(l => matchesPath(l, parent));
          if (parentLink) return parentLink;
        }
      }

      return links.find(l => l.classList.contains('active')) || links[0] || null;
    }

    // determine active link: prefer server-side class, then match by href to current URL,
    // then map child pages to their parent tab so nested views do not fall back to Dashboard
    let active = links.find(l => l.classList.contains('active'));
    if(!active){
      active = resolveActiveLink();
      links.forEach(x=>x.classList.remove('active'));
      if(active) active.classList.add('active');
    }
    if (active) {
      links.forEach(x => x.classList.remove('active'));
      active.classList.add('active');
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

    window.addEventListener('popstate', function(){
      const resolved = resolveActiveLink();
      if (resolved) {
        links.forEach(x => x.classList.remove('active'));
        resolved.classList.add('active');
        active = resolved;
        updateIndicator(resolved);
      }
    });

    // animate indicator on initial load after small delay to allow layout
    setTimeout(()=> updateIndicator(active), 220);
  });
})();
