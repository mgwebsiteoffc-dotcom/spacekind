"""Local-only browser checks for the public directory and handbook (ChromeDriver)."""
import json
import sys
import urllib.request

DRIVER = 'http://127.0.0.1:9515'
SITE = 'http://127.0.0.1:8160'


def call(path, data=None, method=None):
    body = None if data is None else json.dumps(data).encode()
    req = urllib.request.Request(DRIVER + path, data=body, method=method,
                                 headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=30) as res:
        return json.load(res)['value']


sid = call('/session', {'capabilities': {'alwaysMatch': {'browserName': 'chrome',
    'goog:chromeOptions': {'args': ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage']}}}})['sessionId']
base = '/session/' + sid
def js(code, *args):
    return call(base + '/execute/sync', {'args': list(args), 'script': code})


def capture_checks():
    call(base + '/url', {'url': SITE + '/'})
    assert js("return !!document.querySelector('[data-open-project]')?.getBoundingClientRect().width"), 'floating button missing'
    opened = call(base + '/execute/async', {'args': [], 'script': """
        const done = arguments[0], d = document.querySelector('.project-dialog');
        if (d.open) return done(true);
        const observer = new MutationObserver(() => {if (d.open) {observer.disconnect(); clearTimeout(timer); done(true);}});
        const timer = setTimeout(() => {observer.disconnect(); done(false);}, 8000);
        observer.observe(d, {attributes:true, attributeFilter:['open']});"""})
    assert opened, 'delayed entry prompt missing'
    js("document.querySelector('.project-dialog [data-close]').click()")
    assert not js("return document.querySelector('.project-dialog').open"), 'dismissal failed'
    js("document.querySelector('[data-open-project]').click()")
    assert js("return document.querySelector('.project-dialog').open"), 'floating button failed to reopen'
    # Fresh visitor tab state; test the desktop exit before the entry delay.
    js('sessionStorage.clear()')
    call(base + '/url', {'url': SITE + '/'})
    assert not js("document.documentElement.dispatchEvent(new PointerEvent('pointerout', {pointerType:'touch', clientY:0})); return document.querySelector('.project-dialog').open"), 'exit fired for touch'
    assert js("document.documentElement.dispatchEvent(new PointerEvent('pointerout', {pointerType:'mouse', clientY:0})); return document.querySelector('.project-dialog').open"), 'desktop exit prompt missing'
    js("document.querySelector('.project-dialog').close(); document.documentElement.dispatchEvent(new PointerEvent('pointerout', {pointerType:'mouse', clientY:0}))")
    assert not js("return document.querySelector('.project-dialog').open"), 'exit prompt repeated in same session'
    print('Signed-out entry, exit, dismissal and floating reopen passed')


try:
    if '--capture' in sys.argv:
        capture_checks()
    for width in (() if '--capture' in sys.argv else (1440, 768, 390, 320)):
        call(base + '/goog/cdp/execute', {'cmd': 'Emulation.setDeviceMetricsOverride', 'params':
            {'width': width, 'height': 900, 'deviceScaleFactor': 1, 'mobile': False}})
        for path, marker in [('/', '#guides'), ('/for-businesses', '#compare'), ('/guides/compare-interior-quotes', '#checklist'), ('/studios/studio-aangan', '.profile-meta'), ('/inspiration', '#motion'), ('/reels', '.reel-card-player'), ('first-published-film', '.reel-watch-screen')]:
            if path == 'first-published-film':
                path = call(base + '/execute/sync', {'args': [], 'script': "return document.querySelector('.reel-card h3 a').pathname"})
            call(base + '/url', {'url': SITE + path})
            result = call(base + '/execute/sync', {'args': [marker], 'script': '''
              const doc = document.documentElement;
              const heading = document.querySelector('h1');
              const marker = document.querySelector(arguments[0]);
              if (!heading || !marker || marker.getBoundingClientRect().width === 0) return 'missing content';
              if (doc.scrollWidth > doc.clientWidth + 1) return 'horizontal overflow: ' + doc.scrollWidth + ' at ' + [...document.querySelectorAll('main *')].filter(el=>el.getBoundingClientRect().right > doc.clientWidth + 1 && !el.closest('.reel-collection, .categories')).slice(0,5).map(el=>el.className).join(', ');
              if (location.pathname === '/for-businesses') {
                const detail = document.querySelectorAll('#why-spacekind details')[1];
                detail.querySelector('summary').click();
                if (!detail.open || !detail.querySelector('p').getBoundingClientRect().height) return 'studio decision guide does not expand';
                if (document.querySelector('#compare .compare-next a')?.pathname !== '/register') return 'missing comparison signup';
              }
              if (location.pathname === '/inspiration') {
                const items = document.querySelectorAll('.inspiration-library [data-lightbox]');
                if (items.length < 2) return 'missing image navigation fixtures';
                items[0].click();
                const viewer = document.querySelector('#imageLightbox');
                viewer.querySelector('[data-gallery-step="1"]').click();
                if (!viewer.open || viewer.querySelector('img').getAttribute('src') !== items[1].dataset.lightbox) return 'next image failed';
                viewer.dispatchEvent(new KeyboardEvent('keydown', {key:'ArrowLeft'}));
                if (viewer.querySelector('img').getAttribute('src') !== items[0].dataset.lightbox) return 'previous image failed';
                viewer.close();
              }
              if (['/reels','/inspiration'].includes(location.pathname)) {
                const cards = [...document.querySelectorAll('.reel-collection .reel-card')];
                if (cards.length < 2 || Math.abs(cards[0].offsetHeight - cards[1].offsetHeight) > 1 || getComputedStyle(cards[0].querySelector('h3')).fontSize !== '14px') return 'uneven reel cards or oversized titles';
              }
              const film = document.querySelector('.reel-card-player video, .reel-watch-screen video');
              if (location.pathname === '/' || location.pathname.startsWith('/reels')) {
                if (!film) return 'missing public film';
                const r = film.getBoundingClientRect();
                if (Math.abs(r.width/r.height - 9/16) > .005 || getComputedStyle(film).objectFit !== 'cover') return `reel frame/crop mismatch: ${r.width}x${r.height} ${getComputedStyle(film).objectFit}`;
                film.controls = true;
                if (getComputedStyle(film).objectFit !== 'cover') return 'letterbox with controls';
              }
              if (location.pathname === '/' && document.querySelector('#city')?.value !== 'Lucknow') return 'unexpected finder default';
              if (location.pathname === '/' || location.pathname.startsWith('/guides/')) {
                const link = document.querySelector('a.project-float');
                if (!link || link.pathname !== '/project-brief') return 'missing project link';
                const r = link.getBoundingClientRect();
                if (!r.width || !r.height || r.top < 0 || r.bottom > innerHeight || r.left < 0 || r.right > innerWidth || !link.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))) return 'floating link hidden or covered';
              }
              const rail = document.querySelector('#home-reels');
              if (rail) {
                const heading = document.querySelector('.home-films .section-top').getBoundingClientRect();
                if (heading.bottom > rail.getBoundingClientRect().top + 1) return 'reels should sit below the heading';
                if (doc.clientWidth < 400 && rail.scrollWidth <= rail.clientWidth) return 'mobile reel shelf cannot scroll';
              }
              const box = document.querySelector('#checklist input');
              if (box) { box.click(); if (!box.checked || document.querySelector('[data-download-guide]')?.hidden !== false) return 'checklist failed'; }
              return 'ok';'''})
            if result != 'ok':
                raise RuntimeError(f'{path} at {width}px: {result}')
        print('Public directory and planning guide:', width, 'px passed')
except Exception as exc:
    print(f'::error::Public browser check: {exc}', flush=True)
    raise
finally:
    call(base, method='DELETE')
