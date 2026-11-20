"""Synthetic browser journey against the running demo. Never use real client details."""
import asyncio, json, os, re
from pathlib import Path
from datetime import datetime, timedelta
from PIL import Image, ImageDraw
from playwright.async_api import async_playwright

ROOT=Path(__file__).resolve().parent
BASE=os.getenv('SPACEKIND_BASE_URL','http://127.0.0.1:8000')

async def main():
    results=[]; errors=[]; external=[]
    def record(name, ok=True, **extra):
        results.append({'check':name,'passed':bool(ok),**extra})
        (ROOT/'journey-progress.json').write_text(json.dumps(results,indent=2))
        if not ok: raise AssertionError(name+' '+str(extra))
    name='QA Synthetic Journey'
    image=Image.new('RGB',(640,480),'#f5f2e8');d=ImageDraw.Draw(image);d.rectangle([50,60,580,390],outline='#526748',width=5);d.line([300,60,300,390],fill='#526748',width=4);d.text((70,410),'SYNTHETIC QA PLAN - NO REAL CLIENT DATA',fill='#526748');image.save(ROOT/'synthetic-plan.png')
    async with async_playwright() as p:
        browser=await p.chromium.launch(headless=True,args=['--no-sandbox'])
        context=await browser.new_context(viewport={'width':1360,'height':960})
        page=await context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        page.on('request',lambda r:external.append(r.url) if not r.url.startswith(BASE) and not r.url.startswith('data:') else None)
        page.on('dialog',lambda dialog: dialog.accept())
        r=await page.goto(BASE+'/studios/studio-aangan/enquire?origin=marketplace&utm_source=instagram&utm_medium=paid_social&utm_campaign=qa_synthetic')
        record('Qualified form is server rendered',r.status==200)
        form=page.locator('form[data-wizard]')
        for field,value in {'city':'Noida','locality':'QA synthetic locality','floor_area':'1640','brief':'Synthetic QA project only. Warm finishes and practical storage. No real personal data.'}.items():await form.locator('[name="'+field+'"]').fill(value)
        for field,value in {'property_type':'Apartment','configuration':'3 BHK','scope':'Full-home interiors','budget':'₹10–20 lakh','readiness':'Within 1–3 months'}.items():await form.locator('[name="'+field+'"]').select_option(label=value)
        await form.locator('[name="plan"]').set_input_files(str(ROOT/'synthetic-plan.png'))
        await form.locator('[data-next-step]').click()
        record('Two-step qualification advances',await form.locator('[data-step="2"]').is_visible())
        await form.locator('[name="name"]').fill(name);await form.locator('[name="phone"]').fill('+91 9000000000');await form.locator('[name="email"]').fill('qa-journey@example.test');await form.locator('[name="contact_consent"]').check();await form.locator('[name="attribution_consent"]').check()
        await form.locator('button[type="submit"],button:not([type])').last.click()
        await page.wait_for_url('**/enquire/thanks')
        body=await page.locator('main').inner_text();reference=re.search(r'SK-\d+',body)
        record('Brief is saved and a reference returned',reference is not None,reference=reference.group() if reference else None)
        await page.goto(BASE+'/login');await page.locator('form:has(input[name="role"][value="owner"]) button').click();await page.wait_for_url('**/workspace')
        await page.goto(BASE+'/workspace/enquiries?q=QA%20Synthetic%20Journey')
        link=page.get_by_role('link',name=re.compile('QA Synthetic Journey')).first;href=await link.get_attribute('href');lead_id=int(href.rsplit('/',1)[-1]);await link.click()
        record('Public brief reaches the selected studio inbox',await page.locator('h1').inner_text()==name,lead_id=lead_id)
        record('Permitted UTM source is retained','qa_synthetic' in await page.locator('main').inner_text())
        plan_response=await context.request.get(BASE+f'/workspace/enquiries/{lead_id}/plan')
        record('Private plan is downloadable by its studio',plan_response.status==200 and 'attachment' in plan_response.headers.get('content-disposition',''))
        pipeline_form=page.locator(f'form[action="/workspace/enquiries/{lead_id}"]')
        await pipeline_form.locator('[name="stage"]').select_option('Qualified')
        await pipeline_form.locator('[name="assigned_to"]').select_option(label='Meera · Demo team')
        await pipeline_form.locator('[name="next_follow_up_at"]').fill((datetime.now()+timedelta(days=1)).strftime('%Y-%m-%dT11:30'))
        await pipeline_form.locator('[name="follow_up_task"]').fill('QA: confirm synthetic site visit')
        await pipeline_form.get_by_role('button',name='Save next step').click();await page.wait_for_load_state('domcontentloaded')
        record('Stage assignment and follow-up persist',await page.locator('[name="stage"]').input_value()=='Qualified' and await page.locator('[name="follow_up_task"]').input_value()=='QA: confirm synthetic site visit')
        note='QA synthetic note: <script>window.__qaXss=1</script> is safely rendered as text.'
        await page.locator('[name="body"]').fill(note);await page.get_by_role('button',name='Add note').click();await page.wait_for_load_state('domcontentloaded')
        record('Notes persist without executing HTML',note in await page.locator('main').inner_text() and await page.evaluate('typeof window.__qaXss')=='undefined')
        await page.get_by_role('button',name='Mark current follow-up complete').click();await page.wait_for_load_state('domcontentloaded');record('Follow-up can be completed',await page.locator('[name="next_follow_up_at"]').input_value()=='')
        await page.get_by_role('button',name='Convert to client').click();await page.wait_for_url('**/workspace/projects/*');project_id=int(page.url.rsplit('/',1)[-1]);record('Confirmed enquiry converts to client/project',project_id>0,project_id=project_id)
        project_form=page.locator(f'form[action="/workspace/projects/{project_id}"]');await project_form.locator('[name="stage"]').select_option('Handover');await project_form.locator('[name="notes"]').fill('QA synthetic handover notes. No real site or client.');await project_form.get_by_role('button',name='Save project').click();await page.wait_for_load_state('domcontentloaded')
        record('Project reaches handover with a saved timestamp','First marked for handover:' in await page.locator('main').inner_text())
        await page.goto(BASE+'/workspace/studio');upload=page.locator('form[action="/workspace/gallery"]');await upload.locator('[name="image"]').set_input_files(str(ROOT/'synthetic-plan.png'));await upload.locator('[name="caption"]').fill('QA synthetic gallery concept');await upload.locator('[name="kind"]').select_option('concept');await upload.locator('[name="rights"]').check();await upload.get_by_role('button',name='Upload to gallery').click();await page.wait_for_load_state('domcontentloaded')
        card=page.locator('article.managed-image').filter(has=page.locator('img[alt="QA synthetic gallery concept"]'));record('Authorised gallery image uploads',await card.count()==1)
        image_src=await card.locator('img').get_attribute('src');await card.locator('[name="is_visible"]').uncheck();await card.get_by_role('button',name='Save',exact=True).click();await page.wait_for_load_state('domcontentloaded')
        guest=await browser.new_context();guest_response=await guest.request.get(BASE+image_src);record('Hidden gallery media is not public',guest_response.status==404)
        card=page.locator('article.managed-image').filter(has=page.locator('img[alt="QA synthetic gallery concept"]'));await card.get_by_role('button',name='Remove image').click();await page.wait_for_load_state('domcontentloaded');record('Gallery image can be removed',await page.locator('img[alt="QA synthetic gallery concept"]').count()==0)
        missing_csrf=await guest.request.post(BASE+'/demo-login',form={'role':'owner'});record('Real HTTP form without CSRF is rejected',missing_csrf.status==419,status=missing_csrf.status)
        token_a=await guest.request.post(BASE+'/api/v1/auth/token',data={'email':'owner@aangan.example','password':'Spacekind@2026','device_name':'qa-journey'});token_data=await token_a.json();record('Sanctum issues an expiring device token',token_a.status==201 and token_data.get('expires_in_days')==30)
        token_b=await guest.request.post(BASE+'/api/v1/auth/token',data={'email':'owner@form-grain.example','password':'Spacekind@2026','device_name':'qa-journey'});token_b_data=await token_b.json();headers={'Authorization':'Bearer '+token_b_data['token']}
        cross=await guest.request.get(BASE+f'/api/v1/leads/{lead_id}',headers=headers);cross_write=await guest.request.patch(BASE+f'/api/v1/leads/{lead_id}',headers=headers,data={'stage':'Won'})
        record('Another studio cannot read or update this enquiry',cross.status==404 and cross_write.status==404)
        revoke=await guest.request.delete(BASE+'/api/v1/auth/token',headers=headers);record('Device token can be revoked',revoke.status==204)
        # A separate guest registration + admin approval + suspension flow.
        reg=await guest.new_page();await reg.goto(BASE+'/register');studio_name='QA Synthetic Studio';email='qa-owner@example.test'
        for field,value in {'name':'QA Synthetic Owner','studio_name':studio_name,'email':email,'password':'SyntheticPassword123','password_confirmation':'SyntheticPassword123'}.items():await reg.locator('[name="'+field+'"]').fill(value)
        await reg.locator('[name="city"]').select_option('Noida');await reg.locator('[name="terms"]').check();await reg.get_by_role('button',name='Create my studio workspace').click();await reg.wait_for_url('**/workspace/studio')
        record('Registration creates an owner with a pending public listing','public listing is hidden' in (await reg.locator('main').inner_text()).lower())
        admin_context=await browser.new_context(viewport={'width':1440,'height':1000});admin=await admin_context.new_page();admin.on('dialog',lambda dialog:dialog.accept());await admin.goto(BASE+'/login');await admin.locator('form:has(input[name="role"][value="admin"]) button').click();await admin.wait_for_url('**/admin')
        row=admin.locator('tr').filter(has=admin.locator('strong',has_text=studio_name));await row.locator('[name="decision"]').select_option('approved');await row.get_by_role('button',name='Save',exact=True).click();await admin.wait_for_load_state('domcontentloaded')
        published=await context.request.get(BASE+'/studios/qa-synthetic-studio');record('Super-admin approval publishes the listing',published.status==200)
        row=admin.locator('tr').filter(has=admin.locator('strong',has_text=studio_name));await row.get_by_role('button',name='Suspend workspace').click();await admin.wait_for_load_state('domcontentloaded')
        suspended=await reg.goto(BASE+'/workspace');hidden=await context.request.get(BASE+'/studios/qa-synthetic-studio');record('Suspension blocks workspace and public listing',suspended.status==403 and hidden.status==404)
        row=admin.locator('tr').filter(has=admin.locator('strong',has_text=studio_name));await row.get_by_role('button',name='Restore workspace').click();await admin.wait_for_load_state('domcontentloaded');restored=await reg.goto(BASE+'/workspace');record('Admin can restore workspace access',restored.status==200)
        await admin.screenshot(path=str(ROOT/'admin-desktop.png'),full_page=True)
        await guest.request.delete(BASE+'/api/v1/auth/token',headers={'Authorization':'Bearer '+token_data['token']})
        record('No JavaScript runtime errors',not errors,errors=errors);record('No third-party network requests',not external,requests=external)
        await browser.close()
    final={'checks':len(results),'passed':all(r['passed'] for r in results),'results':results,'synthetic_records':{'lead_id':lead_id,'project_id':project_id,'business_slug':'qa-synthetic-studio','owner_email':'qa-owner@example.test'}}
    (ROOT/'journey-results.json').write_text(json.dumps(final,indent=2));print(json.dumps(final,indent=2))

if __name__=='__main__':asyncio.run(main())
