"""Read-only desktop/mobile smoke tests. Requires Python Playwright + Chromium."""
import asyncio, json, os
from pathlib import Path
from playwright.async_api import async_playwright

ROOT = Path(__file__).resolve().parent
BASE = os.getenv('SPACEKIND_BASE_URL', 'http://127.0.0.1:8000')
PUBLIC = ['/', '/for-businesses', '/studios/studio-aangan', '/studios/studio-aangan/enquire', '/login', '/register']
WORKSPACE = ['/workspace', '/workspace/enquiries', '/workspace/enquiries?view=board', '/workspace/enquiries/1', '/workspace/enquiries/new', '/workspace/projects', '/workspace/projects/1', '/workspace/clients', '/workspace/studio', '/workspace/team', '/workspace/modules']

async def main():
    results = []
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, args=['--no-sandbox'])
        for width in [int(v) for v in os.getenv('SPACEKIND_WIDTHS','1440,768,390,320').split(',')]:
            context = await browser.new_context(viewport={'width': width, 'height': 900})
            page = await context.new_page()
            errors = []
            page.on('pageerror', lambda error: errors.append(str(error)))
            async def check(path):
                errors.clear()
                response = await page.goto(BASE + path, wait_until='domcontentloaded', timeout=15000)
                await page.locator('h1').first.wait_for(timeout=10000)
                await page.wait_for_timeout(150)
                state = await page.evaluate('''() => ({width:innerWidth, page:document.documentElement.scrollWidth,
                    bad:[...document.querySelectorAll('body *')].filter(el=>{let r=el.getBoundingClientRect();return r.width>0&&(r.right>innerWidth+1||r.left < -1)&&getComputedStyle(el).position!=='absolute'}).slice(0,8).map(el=>({tag:el.tagName,cls:String(el.className),width:el.getBoundingClientRect().width}))})''')
                result = {'width': width, 'path': path, 'status': response.status, 'overflow': state['page'] > width, 'errors': list(errors)}
                if result['overflow']: result['details'] = state['bad']
                results.append(result)
                (ROOT / 'responsive-progress.json').write_text(json.dumps(results, indent=2, ensure_ascii=False))
                if width == 390 and path in ['/', '/for-businesses', '/studios/studio-aangan/enquire', '/workspace', '/workspace/enquiries/1', '/workspace/studio']:
                    name = {'/':'home','/for-businesses':'business','/studios/studio-aangan/enquire':'enquiry','/workspace':'workspace','/workspace/enquiries/1':'detail','/workspace/studio':'studio'}[path]
                    await page.screenshot(path=str(ROOT / (name + '-mobile.png')), full_page=True)
                if width == 1440 and path in ['/workspace', '/for-businesses']:
                    await page.screenshot(path=str(ROOT / ('workspace-desktop.png' if path == '/workspace' else 'business-desktop.png')), full_page=True)
            for path in PUBLIC: await check(path)
            await page.goto(BASE + '/login', wait_until='domcontentloaded')
            await page.locator('form:has(input[name="role"][value="owner"]) button').click()
            await page.wait_for_url('**/workspace')
            for path in WORKSPACE: await check(path)
            print(f'Completed {width}px: {len(results)} checks', flush=True)
            await context.close()
        await browser.close()
    failures = [r for r in results if r['status'] != 200 or r['overflow'] or r['errors']]
    (ROOT / ('responsive-' + os.getenv('SPACEKIND_WIDTHS','all').replace(',','-') + '.json')).write_text(json.dumps({'checks':len(results),'failures':failures,'results':results}, indent=2, ensure_ascii=False))
    print(json.dumps({'checks':len(results),'failures':failures}, indent=2))
    if failures: raise SystemExit(1)

if __name__ == '__main__': asyncio.run(main())
