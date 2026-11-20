"""Local-only browser checks for the public directory and handbook (ChromeDriver)."""
import json
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
try:
    for width in (1440, 768, 390, 320):
        call(base + '/goog/cdp/execute', {'cmd': 'Emulation.setDeviceMetricsOverride', 'params':
            {'width': width, 'height': 900, 'deviceScaleFactor': 1, 'mobile': False}})
        for path, marker in [('/', '#guides'), ('/guides/compare-interior-quotes', '#checklist')]:
            call(base + '/url', {'url': SITE + path})
            result = call(base + '/execute/sync', {'args': [marker], 'script': '''
              const doc = document.documentElement;
              const heading = document.querySelector('h1');
              const marker = document.querySelector(arguments[0]);
              if (!heading || !marker || marker.getBoundingClientRect().width === 0) return 'missing content';
              if (doc.scrollWidth > doc.clientWidth + 1) return 'horizontal overflow: ' + doc.scrollWidth;
              const box = document.querySelector('#checklist input');
              if (box) { box.click(); if (!box.checked) return 'checklist failed'; }
              return 'ok';'''})
            assert result == 'ok', (path, width, result)
        print('Public directory and planning guide:', width, 'px passed')
finally:
    call(base, method='DELETE')
