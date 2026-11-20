(() => {
  const root = document.querySelector('#project-capture');
  if (!root) return;
  const dialog = root.querySelector('dialog');
  if (typeof dialog.showModal !== 'function') return;
  const key = 'spacekind-project-prompt-seen-v1';
  let seen = false;
  try { seen = sessionStorage.getItem(key) === '1'; } catch (_) {}
  function open(automatic) {
    if (dialog.open || document.hidden || document.querySelector('dialog[open]')) return;
    if (automatic && (seen || document.activeElement?.matches('input,textarea,select') || [...document.querySelectorAll('video')].some(v => !v.paused))) return;
    seen = true;
    try { sessionStorage.setItem(key, '1'); } catch (_) {}
    dialog.showModal();
  }
  root.querySelector('[data-open-project]')?.addEventListener('click', event => { event.preventDefault(); open(false); });
  if (root.dataset.entry === '1') setTimeout(() => open(true), Number(root.dataset.delay) * 1000);
  if (root.dataset.exit === '1' && matchMedia('(hover:hover) and (pointer:fine)').matches) {
    document.documentElement.addEventListener('mouseleave', event => { if (event.clientY <= 0 && !event.relatedTarget) open(true); });
  }
})();
