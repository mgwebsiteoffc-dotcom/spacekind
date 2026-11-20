'use strict';
(() => {
  const root = document.getElementById('privacyPreferencesRoot');
  if (!root) return;
  let cfg;
  try { cfg = JSON.parse(root.dataset.consentConfig); } catch { return; }
  const key = 'spacekind:public-consent';
  const maxAge = 180 * 24 * 60 * 60 * 1000;
  const signal = navigator.globalPrivacyControl === true || navigator.doNotTrack === '1' || window.doNotTrack === '1';
  const dialog = document.getElementById('privacyPreferences');
  const form = document.getElementById('privacyPreferencesForm');
  const banner = document.getElementById('optionalConsentBanner');
  let loaded = false;
  const installed = new Set();
  let choice = null;
  try {
    const value = JSON.parse(localStorage.getItem(key) || 'null');
    if (value && value.version === cfg.version && typeof value.analytics === 'boolean' && typeof value.marketing === 'boolean' && Number.isFinite(value.at) && Date.now() - value.at < maxAge && value.at <= Date.now() + 300000) choice = value;
  } catch { /* Optional storage failure must not affect the site or enquiry forms. */ }
  const allowed = () => ({ analytics: !signal && !!choice?.analytics, marketing: !signal && !!choice?.marketing });
  const safeContext = () => {
    if (!cfg.active || location.search || location.hash) return false;
    if (document.referrer) {
      try {
        const u = new URL(document.referrer);
        if (u.search || u.hash || (u.origin === location.origin && /^\/(workspace|admin|api|login|register|article-media|reel-media|media)(\/|$)/.test(u.pathname)) || /\/enquire(?:\/|$)/.test(u.pathname)) return false;
      } catch { return false; }
    }
    return true;
  };
  const script = src => {
    const el = document.createElement('script');
    el.async = true; el.src = src; el.referrerPolicy = 'origin';
    document.head.append(el); loaded = true;
  };
  const google = consent => {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
    window.gtag('consent', 'update', { analytics_storage: consent.analytics ? 'granted' : 'denied', ad_storage: consent.marketing ? 'granted' : 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
  };
  const apply = () => {
    if (!safeContext()) return;
    const consent = allowed();
    for (const tag of cfg.tags || []) {
      if (!consent[tag.category] || installed.has(tag.provider)) continue;
      installed.add(tag.provider);
      if (tag.provider === 'ga4') {
        google(consent);
        window.gtag('js', new Date());
        const referrer = document.referrer ? new URL(document.referrer).origin + '/' : '';
        window.gtag('config', tag.id, { page_location: cfg.canonical, page_referrer: referrer, send_page_view: true, allow_google_signals: false, allow_ad_personalization_signals: false, debug_mode: !!tag.debug });
        script('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(tag.id));
      } else if (tag.provider === 'gtm') {
        google(consent);
        window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js', spacekind_page_group: cfg.group, spacekind_canonical: cfg.canonical });
        script('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(tag.id));
      } else if (tag.provider === 'meta') {
        if (!window.fbq) {
          const fbq = function () { fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments); };
          fbq.queue = []; fbq.loaded = true; fbq.version = '2.0'; window.fbq = fbq; window._fbq = fbq;
        }
        window.fbq('set', 'autoConfig', false, tag.id);
        window.fbq('init', tag.id);
        window.fbq('consent', 'grant');
        window.fbq('track', 'PageView');
        script('https://connect.facebook.net/en_US/fbevents.js');
      } else if (tag.provider === 'custom') {
        script(tag.url);
      }
    }
  };
  const clearKnownCookies = () => {
    const domains = [null];
    const parts = location.hostname.split('.');
    for (let i = 0; i < parts.length - 1; i++) domains.push('.' + parts.slice(i).join('.'));
    document.cookie.split(';').forEach(pair => {
      const name = pair.split('=')[0].trim();
      if (!/^(_ga(?:_|$)|_gid$|_gat(?:_|$)|_fbp$|_fbc$)/.test(name)) return;
      for (const domain of domains) document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax' + (domain ? '; Domain=' + domain : '') + (location.protocol === 'https:' ? '; Secure' : '');
    });
  };
  const save = (analytics, marketing) => {
    const before = allowed();
    choice = { version: cfg.version, analytics: !signal && !!analytics, marketing: !signal && !!marketing, at: Date.now() };
    try { localStorage.setItem(key, JSON.stringify(choice)); } catch {}
    banner.hidden = true;
    if (dialog.open) dialog.close();
    if (!choice.analytics || !choice.marketing) clearKnownCookies();
    if (loaded && (before.analytics !== choice.analytics || before.marketing !== choice.marketing)) { location.reload(); return; }
    apply();
  };
  const open = () => {
    const consent = allowed();
    form.elements.analytics.checked = consent.analytics;
    form.elements.marketing.checked = consent.marketing;
    form.elements.analytics.disabled = signal;
    form.elements.marketing.disabled = signal;
    document.getElementById('browserPrivacyNotice').hidden = !signal;
    if (!dialog.open) dialog.showModal();
  };
  document.addEventListener('click', event => {
    if (event.target.closest('[data-open-privacy]')) { event.preventDefault(); open(); }
    if (event.target.closest('[data-consent-reject]')) save(false, false);
    if (event.target.closest('[data-consent-accept]')) save(true, true);
  });
  form.addEventListener('submit', event => { event.preventDefault(); save(form.elements.analytics.checked, form.elements.marketing.checked); });
  window.addEventListener('storage', event => { if (event.key === key && loaded) location.reload(); });
  if (signal) clearKnownCookies();
  if (!choice && safeContext() && !signal) banner.hidden = false;
  if (location.hash === '#preferences') open();
  apply();
})();
