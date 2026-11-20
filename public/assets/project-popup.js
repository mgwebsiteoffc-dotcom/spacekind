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
  // A delayed prompt can become due in a background tab or mid-form. Retry when
  // the visitor is available instead of losing the one scheduled attempt.
  function entryWhenAvailable() {
    if (seen) return;
    open(true);
    if (!seen) setTimeout(entryWhenAvailable, 3000);
  }
  if (root.dataset.entry === '1') setTimeout(entryWhenAvailable, Number(root.dataset.delay) * 1000);
  if (root.dataset.exit === '1') {
    // Check the pointer actually leaving, not the device's primary-input media query.
    // A laptop can report touch as its primary input even while a mouse is in use.
    document.documentElement.addEventListener('pointerout', event => {
      if (event.pointerType === 'mouse' && event.clientY <= 0 && !event.relatedTarget) open(true);
    });
  }
})();
