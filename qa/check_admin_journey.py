"""Continue the synthetic QA journey's registered-studio/admin flow."""
import asyncio,json,os
from pathlib import Path
from password_login import password_login
from playwright.async_api import async_playwright
ROOT=Path(__file__).resolve().parent
BASE=os.getenv('SPACEKIND_BASE_URL','http://127.0.0.1:8000')

async def main():
    results=json.loads((ROOT/'journey-progress.json').read_text())
    assert results and all(r['passed'] for r in results), 'Resolve earlier journey failures before continuing.'
    errors=[]
    def record(name,passed,**data):
        results.append({'check':name,'passed':passed,**data});(ROOT/'journey-progress.json').write_text(json.dumps(results,indent=2));print(name,passed,flush=True)
        assert passed,name
    async with async_playwright() as p:
        browser=await p.chromium.launch(headless=True,args=['--no-sandbox'])
        ctx=await browser.new_context(viewport={'width':1440,'height':1000});admin=await ctx.new_page();admin.on('pageerror',lambda e:errors.append(str(e)));admin.on('dialog',lambda d:d.accept())
        await admin.goto(BASE+'/login',wait_until='domcontentloaded');await password_login(admin, 'admin');await admin.wait_for_url('**/admin',wait_until='domcontentloaded')
        record('Super-admin console is authenticated',await admin.locator('h1').inner_text()=='Good studios.\nClear boundaries.')
        row=admin.locator('tr').filter(has=admin.locator('strong',has_text='QA Synthetic Studio'));await row.locator('[name="decision"]').select_option('approved');await row.get_by_role('button',name='Save',exact=True).click();await admin.wait_for_load_state('domcontentloaded')
        response=await ctx.request.get(BASE+'/studios/qa-synthetic-studio');record('Admin approval publishes the studio profile',response.status==200)
        owner_ctx=await browser.new_context();owner=await owner_ctx.new_page();await owner.goto(BASE+'/login',wait_until='domcontentloaded');await owner.locator('[name="email"]').fill('qa-owner@example.test');await owner.locator('[name="password"]').fill('SyntheticPassword123');await owner.get_by_role('button',name='Sign in to your studio').click();await owner.wait_for_url('**/workspace',wait_until='domcontentloaded');record('Newly registered owner can sign back in',True)
        row=admin.locator('tr').filter(has=admin.locator('strong',has_text='QA Synthetic Studio'));await row.get_by_role('button',name='Suspend workspace').click();await admin.wait_for_load_state('domcontentloaded')
        suspended=await owner.goto(BASE+'/workspace',wait_until='domcontentloaded');hidden=await ctx.request.get(BASE+'/studios/qa-synthetic-studio');record('Suspension blocks workspace and public listing',suspended.status==403 and hidden.status==404)
        row=admin.locator('tr').filter(has=admin.locator('strong',has_text='QA Synthetic Studio'));await row.get_by_role('button',name='Restore workspace').click();await admin.wait_for_load_state('domcontentloaded');response=await owner.goto(BASE+'/workspace',wait_until='domcontentloaded');record('Restoring the workspace re-enables access',response.status==200)
        await admin.screenshot(path=str(ROOT/'admin-desktop.png'),full_page=True)
        record('No admin JavaScript runtime errors',not errors,errors=errors)
        await browser.close()
    final={'checks':len(results),'passed':all(r['passed'] for r in results),'results':results,'note':'The browser CRM journey and its continuation admin flow were run in separate processes; no production data was used.'}
    (ROOT/'journey-results.json').write_text(json.dumps(final,indent=2));print(json.dumps({'checks':len(results),'passed':final['passed']}))

if __name__=='__main__':asyncio.run(main())
