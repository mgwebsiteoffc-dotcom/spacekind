'use strict';
(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => Array.from(root.querySelectorAll(s));
  let toastTimer;
  const notify = (text) => {
    const toast = $('#toast');
    if (!toast) return;
    toast.textContent = text;
    toast.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('visible'), 3800);
  };
  const store = {
    get(key, fallback) { try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch { /* Private browsing may restrict storage. */ } },
    remove(key) { try { localStorage.removeItem(key); } catch {} }
  };
  let saved = new Set();
  try {
    const value = JSON.parse(store.get('spacekind:saved', '[]'));
    if (Array.isArray(value)) saved = new Set(value.filter(s => typeof s === 'string' && /^[a-z0-9-]{1,140}$/.test(s)));
  } catch {}
  const grid = $('#studioGrid');
  let savedOnly = !!grid && store.get('spacekind:showSaved', '') === 'yes';
  const renderSaved = () => {
    $$('[data-saved-count]').forEach(el => { el.textContent = saved.size; });
    $$('[data-save]').forEach(button => {
      const active = saved.has(button.dataset.save);
      button.classList.toggle('saved', active);
      button.setAttribute('aria-pressed', String(active));
      const label = $('[data-save-label]', button);
      if (label) label.textContent = active ? 'Remove from saved studios' : 'Save this studio';
      else button.setAttribute('aria-label', (active ? 'Unsave ' : 'Save ') + button.dataset.save.replace(/-/g, ' '));
    });
    if (!grid) return;
    let visible = 0;
    $$('[data-studio]', grid).forEach(card => {
      card.hidden = savedOnly && !saved.has(card.dataset.studio);
      if (!card.hidden) visible++;
    });
    $$('.saved-filter-label').forEach(label => { label.hidden = !savedOnly; });
    const empty = $('#savedEmpty');
    if (empty) empty.hidden = !savedOnly || visible > 0;
    $$('.empty-state', grid).forEach(el => { el.hidden = savedOnly; });
    $$('[data-show-saved]').forEach(link => link.setAttribute('aria-pressed', String(savedOnly)));
  };
  const clearSavedFilter = () => {
    savedOnly = false;
    store.remove('spacekind:showSaved');
    renderSaved();
  };
  const viewer = $('#imageLightbox');
  let gallery = [], imageIndex = 0;
  const showImage = index => {
    imageIndex = (index + gallery.length) % gallery.length;
    const item = gallery[imageIndex];
    const caption = item.dataset.caption || 'A closer look at the space.';
    $('#lightboxImage').src = item.dataset.lightbox;
    $('#lightboxImage').alt = item.querySelector('img')?.alt || caption;
    $('#lightboxTitle').textContent = caption;
    $('#lightboxNav').hidden = gallery.length < 2;
    $('#lightboxCount').textContent = gallery.length > 1 ? `Image ${imageIndex + 1} of ${gallery.length}` : '';
  };
  viewer?.addEventListener('keydown', event => {
    if (['ArrowLeft','ArrowRight'].includes(event.key)) {
      event.preventDefault();
      showImage(imageIndex + (event.key === 'ArrowLeft' ? -1 : 1));
    }
  });
  document.addEventListener('click', async event => {
    const save = event.target.closest('[data-save]');
    if (save) {
      event.preventDefault();
      const key = save.dataset.save;
      saved.has(key) ? saved.delete(key) : saved.add(key);
      store.set('spacekind:saved', JSON.stringify([...saved]));
      renderSaved();
      notify(saved.has(key) ? 'Saved on this device. A little inspiration for later.' : 'Removed from your saved studios.');
    }
    if (event.target.closest('[data-show-saved]')) {
      if (grid) {
        event.preventDefault();
        savedOnly = !savedOnly;
        savedOnly ? store.set('spacekind:showSaved', 'yes') : store.remove('spacekind:showSaved');
        renderSaved();
        $('#studios')?.scrollIntoView({ behavior: 'smooth' });
      } else store.set('spacekind:showSaved', 'yes');
    }
    if (event.target.closest('[data-clear-saved-filter]')) clearSavedFilter();
    if (event.target.closest('[data-reset-saved]')) clearSavedFilter();
    const image = event.target.closest('[data-lightbox]');
    if (image && viewer) {
      const collection = image.closest('.inspiration-library');
      gallery = collection ? $$('[data-lightbox]', collection) : [image];
      showImage(gallery.indexOf(image));
      viewer.showModal();
      document.body.style.overflow = 'hidden';
    }
    const step = event.target.closest('[data-gallery-step]');
    if (step && viewer?.open) showImage(imageIndex + Number(step.dataset.galleryStep));
    const close = event.target.closest('[data-close]');
    if (close) close.closest('dialog')?.close();
    const copy = event.target.closest('[data-copy]');
    if (copy) {
      const text = copy.dataset.copy;
      try {
        if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(text);
        else {
          const input = document.createElement('textarea');
          input.value = text;
          input.style.cssText = 'position:fixed;left:-9999px;top:0';
          document.body.append(input); input.select();
          const ok = document.execCommand('copy'); input.remove();
          if (!ok) throw new Error('Clipboard unavailable');
        }
        notify('Copied. Nothing has been sent.');
      } catch { window.prompt('Copy this text. Nothing is sent automatically.', text); }
    }
  });
  $$('dialog').forEach(dialog => {
    dialog.addEventListener('close', () => { if (!$('dialog[open]')) document.body.style.overflow = ''; });
    dialog.addEventListener('click', event => {
      if (event.target !== dialog) return;
      const box = dialog.getBoundingClientRect();
      if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
    });
  });
  $$('[data-autosubmit]').forEach(select => select.addEventListener('change', () => select.form.requestSubmit()));
  $$('[data-wizard]').forEach(form => {
    let step = Number(form.dataset.startStep) === 2 ? 2 : 1;
    const card = form.closest('.enquiry-form-card');
    card?.classList.add('has-wizard-js');
    form.noValidate = true;
    const show = (number, focus = false) => {
      step = number;
      $$('.wizard-step', form).forEach(pane => { pane.hidden = Number(pane.dataset.step) !== number; });
      $$('[data-step-dot]', card).forEach(dot => {
        dot.classList.toggle('active', Number(dot.dataset.stepDot) <= number);
        dot.setAttribute('aria-current', Number(dot.dataset.stepDot) === number ? 'step' : 'false');
      });
      if (focus) {
        const heading = $(`[data-step="${number}"] h2`, form);
        if (heading) { heading.tabIndex = -1; heading.focus({ preventScroll: true }); }
        card?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };
    const firstInvalid = number => $$(`[data-step="${number}"] input, [data-step="${number}"] select, [data-step="${number}"] textarea`, form).find(input => !input.checkValidity());
    const next = () => {
      const invalid = firstInvalid(1);
      if (invalid) { invalid.reportValidity(); return; }
      show(2, true);
    };
    $('[data-next-step]', form)?.addEventListener('click', next);
    $('[data-previous-step]', form)?.addEventListener('click', () => show(1, true));
    form.addEventListener('keydown', event => {
      if (step === 1 && event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && !['button', 'submit', 'file'].includes(event.target.type)) {
        event.preventDefault(); next();
      }
    });
    form.addEventListener('submit', event => {
      for (const number of [1, 2]) {
        const invalid = firstInvalid(number);
        if (invalid) {
          event.preventDefault(); show(number);
          invalid.reportValidity(); return;
        }
      }
    });
    show(step);
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented) return;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    const button = event.submitter;
    if (button?.dataset.pendingLabel) {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.textContent = button.dataset.pendingLabel;
    }
  });
  window.addEventListener('pageshow', () => $$('[aria-busy="true"]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); }));
  renderSaved();
})();
