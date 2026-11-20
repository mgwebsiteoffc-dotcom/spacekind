(() => {
  const shelf = document.querySelector('[data-film-shelf]');
  if (!shelf) return;
  const rail = shelf.querySelector('.reel-collection'), controls = shelf.querySelector('.shelf-controls');
  function sync() {
    controls.hidden = rail.scrollWidth <= rail.clientWidth + 2;
    controls.firstElementChild.disabled = rail.scrollLeft <= 2;
    controls.lastElementChild.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2;
  }
  controls.addEventListener('click', e => {
    const button = e.target.closest('[data-shift]');
    if (button) rail.scrollBy({left: Number(button.dataset.shift) * (rail.firstElementChild.offsetWidth + parseFloat(getComputedStyle(rail).columnGap)), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
  });
  rail.addEventListener('scroll', sync, {passive:true});
  window.addEventListener('resize', sync); sync();
})();
