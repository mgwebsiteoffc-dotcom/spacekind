(() => {
  const tour = document.querySelector('#studio-tour');
  if (!tour || typeof tour.showModal !== 'function') return;
  const launcher = document.querySelector('#studio-tour-launch');
  const panels = [...tour.querySelectorAll('[data-tour-step]')];
  const progress = [...tour.querySelectorAll('.tour-progress i')];
  const back = tour.querySelector('[data-tour-back]');
  const next = tour.querySelector('[data-tour-next]');
  let step = 0;
  let destination = null;

  function show(index) {
    step = index;
    panels.forEach((panel, i) => { panel.hidden = i !== step; });
    progress.forEach((mark, i) => mark.classList.toggle('visited', i <= step));
    back.disabled = step === 0;
    next.textContent = step === panels.length - 1 ? 'Finish tour' : 'Next';
    panels[step].querySelector('h2').focus();
  }
  function start() {
    destination = null;
    tour.showModal();
    show(0);
  }
  launcher.hidden = false;
  launcher.addEventListener('click', start);
  back.addEventListener('click', () => show(step - 1));
  next.addEventListener('click', () => {
    if (step < panels.length - 1) return show(step + 1);
    destination = document.querySelector('#studio-introduction');
    tour.close();
  });
  tour.querySelector('[data-tour-skip]').addEventListener('click', () => tour.close());
  tour.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;
    event.preventDefault();
    destination = document.querySelector(link.hash);
    tour.close();
  });
  tour.addEventListener('close', () => {
    const target = destination || launcher;
    target.focus({preventScroll: true});
    target.scrollIntoView({block: 'nearest'});
  });
  if (tour.hasAttribute('data-start-tour')) start();
})();
