"""Synthetic financial browser journey + responsive/network QA, local demo only.
Prepare/clean exact fixtures using finance_browser_fixture.php; no real financial data.
"""
import asyncio
import io
import json
import os
import re
from datetime import date,timedelta
from pathlib import Path
from urllib.parse import urlencode
from playwright.async_api import async_playwright
from pypdf import PdfReader

ROOT=Path(__file__).resolve().parent.parent
BASE=os.getenv('SPACEKIND_BASE_URL','http://127.0.0.1:8000')
CASE=json.loads((ROOT/'storage/framework/testing/finance-browser.json').read_text())
REPORT=ROOT/'qa/phase2-browser-results.json'
TODAY=date.today();LATER=TODAY+timedelta(days=20)

async def main():
    checks=[];widths=[];errors=[];external=[];fixtures={};complete=False
    def save():REPORT.write_text(json.dumps({'complete':complete,'journey_checks':checks,'responsive_checks':widths,'runtime_errors':errors,'external_requests':external,'fixture_document_ids':{k:v for k,v in fixtures.items() if k!='active_share_path'}},indent=2))
    def check(name,value=True):
        checks.append({'check':name,'passed':bool(value)});save();print(('PASS ' if value else 'FAIL ')+name,flush=True)
        if not value:raise AssertionError(name)
    async with async_playwright() as pw:
        browser=await pw.chromium.launch(headless=True,args=['--no-sandbox'])
        contexts=[]
        async def ctx(ip):
            c=await browser.new_context(viewport={'width':1440,'height':960},extra_http_headers={'X-Forwarded-For':ip});contexts.append(c)
            c.on('request',lambda r:external.append(r.url) if not r.url.startswith(BASE) and not r.url.startswith(('data:','blob:')) else None)
            c.on('page',lambda p:p.on('pageerror',lambda e:errors.append(str(e))))
            return c
        async def goto(p,path):
            r=await p.goto(BASE+path,wait_until='domcontentloaded');await p.locator('h1').first.wait_for();return r
        async def submit(button):
            async with button.page.expect_navigation(wait_until='domcontentloaded'):await button.click()
        async def login(c,role):
            p=await c.new_page();p.set_default_timeout(15000);p.on('dialog',lambda d:d.accept());await goto(p,'/login');f=p.locator('form[action$="/login"]');await f.locator('[name="email"]').fill(CASE['users'][role]['email']);await f.locator('[name="password"]').fill(CASE['password']);await submit(f.locator('button'));return p
        async def fields(form,values):
            for k,v in values.items():
                el=form.locator('[name="'+k+'"]')
                if await el.evaluate('(e)=>e.tagName')=='SELECT':await el.select_option(str(v))
                else:await el.fill(str(v))
        try:
            oc=await ctx('127.0.51.1');ac=await ctx('127.0.51.2');gc=await ctx('127.0.51.3');xc=await ctx('127.0.51.4')
            owner=await login(oc,'owner');admin=await login(ac,'admin');guest=await gc.new_page();other=await login(xc,'other')
            await goto(owner,'/workspace/finance/settings');form=owner.locator('form[action="/workspace/finance/settings"]')
            await fields(form,{'name':'QA Finance Studio','address':'8 Synthetic Billing Street, Noida','state':'09','gstin':'09ABCDE1234F1Z5','email':'qa-finance@example.test','phone':'+919000000000','tax_basis':'exclusive','quotation_prefix':'QTN','invoice_prefix':'INV','receipt_prefix':'RCT','terms':'Synthetic test terms and milestones only. Not commercial.','bank_details':'Synthetic instructions, not a bank account.'});await form.locator('[name="logo"]').set_input_files(str(ROOT/'tests/Fixtures/billing-logo.jpg'));await form.locator('[name="reviewed"]').check();await submit(form.locator('button'))
            check('Owner saves explicit billing/GST setup and private JPG logo','Billing setup saved' in await owner.locator('main').inner_text())
            await goto(owner,'/workspace/finance/catalogue');form=owner.locator('form').filter(has=owner.locator('[name="name"]')).first
            await fields(form,{'name':'QA Finance Material','category':'material','unit':'sq ft','rate':'1000.00','tax_bps':'1800','hsn_sac':'9983'});await form.locator('[name="is_active"]').check();await submit(form.locator('button'));check('Reusable catalogue material saved','QA Finance Material' in await owner.locator('main').inner_text())
            await goto(owner,'/workspace/finance/documents/new?kind=quotation&project_id='+str(CASE['project_id']));form=owner.locator('[data-finance-editor]');await form.locator('[data-catalogue-choice]').select_option(label='QA Finance Material · ₹1,000.00/sq ft');await form.locator('[data-add-catalogue]').click();await form.locator('[data-finance-line]').first.locator('[data-remove-line]').click();line=form.locator('[data-finance-line]');check('Catalogue adds a dynamic line without JavaScript errors',await line.count()==1)
            await line.locator('[data-line-field="quantity"]').fill('2.500');await line.locator('[data-line-field="discount"]').fill('100.00');await fields(form,{'customer[address]':'Apartment 8, Synthetic Site, Noida','customer[state]':'09','place_of_supply':'09'});await submit(form.get_by_role('button',name='Save draft & calculate'))
            qid=int(re.search(r'/documents/(\d+)',owner.url).group(1));fixtures['quotation']=qid;check('Server-calculated quotation draft retains exact CGST/SGST total','₹2,832.00' in await owner.locator('main').inner_text())
            async def issue(p):
                form=p.locator('form[action$="/issue"]');await form.locator('[name="confirm"]').check();await submit(form.locator('button'))
            await issue(owner);check('Issue allocates a permanent quotation number and removes edit action',await owner.locator('h1').inner_text()!='Draft #'+str(qid) and await owner.get_by_role('link',name='Edit draft',exact=False).count()==0)
            form=owner.locator('form[action$="/sent"]');await form.locator('[name="note"]').fill('Synthetic PDF handed to the client for QA');await submit(form.locator('button'));check('Quotation sent status is explicitly studio-recorded','Marked sent' in await owner.locator('main').inner_text())
            form=owner.locator('form[action$="/decision"]');await fields(form,{'decision':'accepted','note':'Synthetic client accepted test scope by phone'});await submit(form.locator('button'));await submit(owner.locator('form[action$="/convert"] button'))
            iid=int(re.search(r'/documents/(\d+)',owner.url).group(1));fixtures['invoice']=iid;check('Accepted quotation converts to reviewable draft invoice',iid!=qid and 'Draft #' in await owner.locator('h1').inner_text());await issue(owner)
            async def create_share():
                form=owner.locator('form[action$="/share"]');await form.locator('[name="days"]').select_option('7');await form.locator('[name="confirm"]').check();await submit(form.locator('button'));return await owner.locator('[data-copy]').get_attribute('data-copy')
            share=await create_share();r=await goto(guest,share.replace(BASE,''));check('Guest private link has no-store and no-referrer',r.status==200 and 'no-store' in r.headers['cache-control'] and r.headers.get('referrer-policy')=='no-referrer')
            r=await gc.request.get(share+'/pdf');pdf=PdfReader(io.BytesIO(await r.body()));text='\n'.join(p.extract_text() for p in pdf.pages);check('Private branded PDF contains frozen identity, scope and tax','QA Finance Studio' in text and 'QA Finance Material' in text and 'CGST' in text and '2,832.00' in text and '09ABCDE1234F1Z5' in text)
            check('Other studio cannot read or download this invoice',(await xc.request.get(BASE+f'/workspace/finance/documents/{iid}')).status==404 and (await xc.request.get(BASE+f'/workspace/finance/documents/{iid}/pdf')).status==404)
            form=owner.locator('form[action="/workspace/finance/payments"]');await fields(form,{'amount':'1000.00','method':'upi','reference':'QA-PHASE2-REF-001','note':'Synthetic partial payment, no real funds.'})
            async with owner.expect_request(lambda r:r.method=='POST' and r.url.endswith('/workspace/finance/payments')) as ri:await submit(form.locator('button'))
            original=await ri.value;rid=int(re.search(r'/documents/(\d+)',owner.url).group(1));fixtures['receipt']=rid;check('Manual partial payment creates a separate numbered receipt','RCT/' in await owner.locator('h1').inner_text() and '₹1,000.00' in await owner.locator('main').inner_text())
            retry=await oc.request.post(original.url,data=original.post_data,headers={'Content-Type':'application/x-www-form-urlencoded'},max_redirects=0);check('Repeating the exact payment POST returns the original receipt',retry.status==302 and retry.headers.get('location','').endswith('/documents/'+str(rid)))
            await goto(owner,f'/workspace/finance/documents/{iid}');check('Partial invoice balance is derived as ₹1,832.00','₹1,832.00' in await owner.locator('.finance-balance').inner_text())
            await goto(owner,'/workspace/finance/payments');form=owner.locator('#record form');await fields(form,{'client_id':CASE['client_id'],'project_id':CASE['project_id'],'amount':'500.00','method':'bank','reference':'QA-PHASE2-ADVANCE-001','note':'Synthetic identified advance.'});await submit(form.locator('button'));arid=int(re.search(r'/documents/(\d+)',owner.url).group(1));fixtures['advance_receipt']=arid;check('Identified client advance receives its own receipt','Recorded as a client advance' in await owner.locator('main').inner_text())
            await goto(owner,f'/workspace/finance/documents/{iid}');form=owner.locator('form[action$="/allocate"]');await form.locator('[name="amount"]').fill('500.00');await submit(form.locator('button'));check('Advance allocation reduces outstanding without a new receipt','₹1,332.00' in await owner.locator('.finance-balance').inner_text())
            await goto(owner,f'/workspace/finance/documents/{rid}');form=owner.locator('form[action$="/reverse"]');await form.locator('[name="reason"]').fill('Synthetic correction: original offline reference was erroneous');await submit(form.locator('button'));check('Reversal retains and visibly voids the original receipt','Voided / retained for history' in await owner.locator('main').inner_text())
            await goto(owner,f'/workspace/finance/documents/{iid}');check('Reversal restores balance while keeping the advance allocation','₹2,332.00' in await owner.locator('.finance-balance').inner_text())
            await submit(owner.locator('form[action$="/revoke"] button').first);check('Revoked token immediately denies guest access',(await gc.request.get(share)).status==404);share=await create_share();fixtures['active_share_path']=share.replace(BASE,'')
            csv=await oc.request.get(BASE+'/workspace/finance/export?kind=invoice&project_id='+str(CASE['project_id']));check('Permissioned filtered CSV contains the customer invoice',csv.status==200 and 'INV/' in await csv.text())
            await goto(admin,'/admin/billing');form=admin.locator('form[action="/admin/billing/settings"]');await fields(form,{'name':'QA Spacekind Platform','address':'Suite 1, Synthetic Platform Street, Noida','state':'09','tax_basis':'none','default_tax_bps':0,'terms':'Synthetic platform service terms. Not a real service or charge.'});await form.locator('[name="reviewed"]').check();await submit(form.locator('button'))
            form=admin.locator('form[action="/admin/billing"]');await fields(form,{'business_id':CASE['owner_business_id'],'service':'software_yearly','quantity':1,'document_date':TODAY.isoformat(),'due_date':LATER.isoformat(),'period_start':TODAY.isoformat(),'period_end':(TODAY+timedelta(days=364)).isoformat(),'customer[name]':'QA Finance Studio','customer[address]':'8 Synthetic Billing Street, Noida','customer[state]':'09','place_of_supply':'09'});await submit(form.locator('button'));pid=int(re.search(r'/billing/(\d+)',admin.url).group(1));fixtures['platform_invoice']=pid;check('Platform bill draft shows fixed ₹4,999 fee and explicit total','₹4,999.00' in await admin.locator('main').inner_text());await issue(admin)
            form=admin.locator('form[action$="/payments"]');await fields(form,{'amount':'4999.00','reference':'QA-PHASE2-PLATFORM-001'});await submit(form.locator('button'));check('Admin records platform payment separately','₹0.00' in await admin.locator('.finance-balance').inner_text())
            await goto(owner,f'/workspace/billing/{pid}');check('Owner can read own Spacekind bill but not edit it','SK/' in await owner.locator('h1').inner_text() and await owner.locator('form[action$="/payments"]').count()==0);check('Other business cannot read platform bill',(await xc.request.get(BASE+f'/workspace/billing/{pid}')).status==404)
            owner_paths=['/workspace/finance','/workspace/finance?kind=quotation','/workspace/finance?kind=receipt','/workspace/finance/settings','/workspace/finance/catalogue','/workspace/finance/payments','/workspace/finance/documents/new?kind=invoice&project_id='+str(CASE['project_id']),f'/workspace/finance/documents/{qid}',f'/workspace/finance/documents/{iid}',f'/workspace/finance/documents/{rid}',f'/workspace/projects/{CASE["project_id"]}','/workspace/billing',f'/workspace/billing/{pid}','/workspace/tools']
            admin_paths=['/admin/billing',f'/admin/billing/{pid}']
            for width in [1440,768,390,320]:
                for role,page,paths in [('owner',owner,owner_paths),('admin',admin,admin_paths),('guest',guest,[fixtures['active_share_path']])]:
                    await page.set_viewport_size({'width':width,'height':940})
                    for path in paths:
                        r=await goto(page,path);await page.evaluate('document.fonts.ready');await page.wait_for_timeout(65)
                        state=await page.evaluate('''()=>({page:document.documentElement.scrollWidth,width:innerWidth,bad:[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.right>innerWidth+1&&getComputedStyle(e).position!=='absolute'}).slice(0,5).map(e=>({tag:e.tagName,cls:String(e.className)}))})''')
                        item={'width':width,'role':role,'path':path,'status':r.status,'overflow':state['page']>width}
                        if item['overflow']:item['details']=state['bad']
                        widths.append(item);save()
                        if width in [1440,390] and role=='owner' and path in ['/workspace/finance',f'/workspace/finance/documents/{iid}']:
                            await page.screenshot(path=str(ROOT/f'qa/phase2-{ "invoice" if "/documents/" in path else "register"}-{width}.png'),full_page=True)
                print(f'{width}px responsive checks complete',flush=True)
            check('All financial routes fit desktop, tablet and small phones',all(x['status']==200 and not x['overflow'] for x in widths))
            check('No runtime JavaScript errors',not errors);check('No third-party requests, payment gateways or messaging calls',not external)
            complete=True;save()
        finally:
            for c in contexts:await c.close()
            await browser.close()

if __name__=='__main__':asyncio.run(main())
