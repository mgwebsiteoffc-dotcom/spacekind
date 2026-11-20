'use strict';
(() => {
  const body = document.querySelector('[data-article-body]');
  if (!body) return;
  const toolbar = document.querySelector('[data-markdown-toolbar]');
  toolbar.hidden = false;
  const formats = { heading: ['\n## ', '', 'Heading'], bold: ['**', '**', 'Bold text'], italic: ['*', '*', 'Italic text'], list: ['\n- ', '', 'List item'], link: ['[', '](https://)', 'Link text'] };
  toolbar.addEventListener('click', e => {
    const f = formats[e.target.closest('[data-format]')?.dataset.format];
    if (!f) return;
    const start = body.selectionStart, end = body.selectionEnd;
    const text = body.value.slice(start, end) || f[2];
    body.setRangeText(f[0] + text + f[1], start, end);
    body.focus();
    body.setSelectionRange(start + f[0].length, start + f[0].length + text.length);
    body.dispatchEvent(new Event('input', { bubbles: true }));
  });
  const rows = document.querySelector('[data-faq-rows]'), add = document.querySelector('[data-add-faq]');
  let next = rows.children.length;
  function refresh() {
    rows.querySelectorAll('[data-remove-faq]').forEach(btn => btn.hidden = false);
    add.hidden = false; add.disabled = rows.children.length >= 12;
  }
  add.addEventListener('click', () => {
    if (rows.children.length >= 12) return;
    const fragment = document.querySelector('#faq-row-template').content.cloneNode(true);
    fragment.querySelectorAll('[data-faq-key]').forEach(input => input.name = `faqs[${next}][${input.dataset.faqKey}]`);
    next++; rows.append(fragment); refresh(); rows.lastElementChild.querySelector('input').focus();
  });
  rows.addEventListener('click', e => {
    const remove = e.target.closest('[data-remove-faq]');
    if (remove) { remove.closest('[data-faq-row]').remove(); refresh(); add.focus(); }
  });
  refresh();
})();
