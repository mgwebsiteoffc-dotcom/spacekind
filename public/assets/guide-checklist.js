(() => {
  const button = document.querySelector('[data-download-guide]');
  if (!button || typeof URL.createObjectURL !== 'function') return;
  button.hidden = false;
  button.addEventListener('click', () => {
    const items = Array.from(document.querySelectorAll('#checklist label'), label =>
      `${label.querySelector('input').checked ? '[x]' : '[ ]'} ${label.textContent.trim()}`);
    const notes = document.querySelector('.guide-example');
    const text = [document.querySelector('.guide-hero h1').textContent, '', ...items, '',
      notes.querySelector('h2').textContent, ...Array.from(notes.querySelectorAll('p'), p => p.textContent), '',
      document.querySelector('link[rel="canonical"]').href].join('\n');
    const url = URL.createObjectURL(new Blob([text], {type:'text/plain;charset=utf-8'}));
    const link = document.createElement('a');
    link.href = url; link.download = `${location.pathname.split('/').pop()}-checklist.txt`;
    document.body.append(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
})();
