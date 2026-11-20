"""Read-only Phase 1B + foundation route/width/network smoke checks. Local demo only."""
import asyncio
import json
import os
from pathlib import Path
from playwright.async_api import async_playwright
from check_responsive import WORKSPACE

ROOT = Path(__file__).resolve().parent
BASE = os.getenv('SPACEKIND_BASE_URL', 'http://127.0.0.1:8000')
WIDTHS = [int(v) for v in os.getenv('SPACEKIND_WIDTHS', '1440,768,390,320').split(',')]
PUBLIC = ['/', '/for-businesses', '/studios/studio-aangan', '/studios/studio-aangan/enquire', '/blog', '/blog/before-the-moodboard', '/studios/studio-aangan/articles/warm-materials-everyday-life', '/reels', '/reels/1', '/privacy', '/login', '/register']
OWNER = WORKSPACE + ['/workspace/tools', '/workspace/blog', '/workspace/blog/new', '/workspace/blog/3/edit', '/workspace/reels', '/workspace/campaign-links']
ADMIN = ['/admin', '/admin/blog', '/admin/blog/new', '/admin/blog/1/edit', '/admin/reels', '/admin/discovery', '/admin/tracking']
SHOTS = {'/blog': 'journal', '/blog/before-the-moodboard': 'article', '/reels': 'reels', '/reels/1': 'watch', '/workspace/campaign-links': 'links', '/workspace/blog/new': 'editor', '/admin/tracking': 'tracking'}

async def main():
    results, external = [], []
    complete = False
    report_path = ROOT / ('phase1b-responsive-' + '-'.join(map(str, WIDTHS)) + '.json')
    def save():
        failures = [r for r in results if r.get('status') != 200 or r.get('overflow') or r.get('errors')]
        report_path.write_text(json.dumps({'complete': complete, 'checks': len(results), 'failures': failures, 'external_requests': external, 'results': results}, indent=2))
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, args=['--no-sandbox'])
        try:
            for width in WIDTHS:
                for role, paths in [('public', PUBLIC), ('owner', OWNER), ('admin', ADMIN)]:
                    context = await browser.new_context(viewport={'width': width, 'height': 900}, extra_http_headers={'X-Forwarded-For': '127.0.10.' + str(WIDTHS.index(width)*3 + ['public','owner','admin'].index(role)+1)})  # Distinct synthetic local clients, not repeated logins in one rate bucket.
                    page = await context.new_page()
                    page.set_default_timeout(12000)
                    errors = []
                    page.on('pageerror', lambda err: errors.append(str(err)))
                    page.on('request', lambda req: external.append(req.url) if not req.url.startswith(BASE) and not req.url.startswith(('data:', 'blob:')) else None)
                    try:
                        if role != 'public':
                            await page.goto(BASE + '/login', wait_until='domcontentloaded')
                            await page.locator(f'form:has(input[name="role"][value="{role}"]) button').click()
                            await page.wait_for_url('**/admin' if role == 'admin' else '**/workspace')
                        for path in paths:
                            errors.clear()
                            try:
                                response = await page.goto(BASE + path, wait_until='domcontentloaded', timeout=15000)
                                await page.locator('h1').first.wait_for()
                                await page.evaluate('document.fonts.ready')
                                await page.wait_for_timeout(90)
                                state = await page.evaluate('''() => ({width:innerWidth,page:document.documentElement.scrollWidth,bad:[...document.querySelectorAll('body *')].filter(el=>{const r=el.getBoundingClientRect();return r.width>0&&(r.right>innerWidth+1||r.left < -1)&&getComputedStyle(el).position!=='absolute'}).slice(0,6).map(el=>({tag:el.tagName,cls:String(el.className),width:el.getBoundingClientRect().width}))})''')
                                item = {'width': width, 'role': role, 'path': path, 'status': response.status, 'overflow': state['page'] > width, 'errors': list(errors)}
                                if item['overflow']: item['details'] = state['bad']
                                results.append(item)
                                if width in [1440, 390] and path in SHOTS:
                                    await page.screenshot(path=str(ROOT / f'phase1b-{SHOTS[path]}-{width}.png'), full_page=True)
                            except Exception as exc:
                                results.append({'width': width, 'role': role, 'path': path, 'status': None, 'errors': [str(exc)]})
                            save()
                    finally:
                        await context.close()
                    print(f'{width}px {role}: {len(results)} accumulated checks', flush=True)
        finally:
            await browser.close()
    complete = True
    save()
    report = json.loads(report_path.read_text())
    print(json.dumps({'checks': report['checks'], 'failures': report['failures'], 'external_requests': external}, indent=2))
    if report['failures'] or external: raise SystemExit(1)

if __name__ == '__main__': asyncio.run(main())
