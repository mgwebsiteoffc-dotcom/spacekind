'use strict';
(() => {
  const videos = [...document.querySelectorAll('[data-reel-video]')];
  videos.forEach(video => video.addEventListener('play', () => {
    videos.forEach(other => { if (other !== video) other.pause(); });
  }));
  document.addEventListener('visibilitychange', () => { if (document.hidden) videos.forEach(video => video.pause()); });
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (!entry.isIntersecting) entry.target.pause();
    }), { threshold: 0.15 });
    videos.forEach(video => observer.observe(video));
  }
  document.querySelectorAll('[data-campaign-form]').forEach(form => {
    form.querySelectorAll('[data-utm-source]').forEach(button => button.addEventListener('click', () => {
      form.elements.utm_source.value = button.dataset.utmSource;
      form.elements.utm_medium.value = button.dataset.utmMedium;
      form.elements.utm_campaign.focus();
    }));
  });
})();
