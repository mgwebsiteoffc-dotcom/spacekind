(() => {
  const toggle = document.querySelector('.studio-menu-toggle');
  const nav = document.querySelector('#studio-navigation');
  const mobile = matchMedia('(max-width:700px)');
  function setExpanded(expanded) {
    nav.hidden = !expanded;
    toggle.setAttribute('aria-expanded', String(expanded));
  }
  function resize() { toggle.hidden = !mobile.matches; setExpanded(!mobile.matches); }
  toggle.addEventListener('click', () => setExpanded(nav.hidden));
  mobile.addEventListener('change', resize);
  resize();
  const menu = document.querySelector('.quick-create');
  if (!menu) return;
  document.addEventListener('click', e => { if (!menu.contains(e.target) || e.target.closest('a')) menu.open = false; });
  document.addEventListener('focusin', e => { if (!menu.contains(e.target)) menu.open = false; });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
  });
})();
