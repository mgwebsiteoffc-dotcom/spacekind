"""Synthetic Phase 1B browser journey. Never use real client details or provider IDs.
Run cleanup_phase1b.php before/after. Writes only explicitly named QA fixtures.
"""
import asyncio
import json
import os
import re
import struct
from datetime import datetime, timedelta
from pathlib import Path
from PIL import Image, ImageDraw
from playwright.async_api import async_playwright

ROOT = Path(__file__).resolve().parent
BASE = os.getenv('SPACEKIND_BASE_URL', 'http://127.0.0.1:8000')
STATE = ROOT / 'phase1b-journey-progress.json'
TITLE = 'QA Phase1B Article'
VIDEO = 'QA Phase1B Video'
CLIENT = 'QA Phase1B Client'

async def main():
    results, external, errors = [], [], []
    fixtures = {'article_slug': 'qa-phase1b-article', 'tracking_versions': []}
    complete = False
    def save():
        STATE.write_text(json.dumps({'complete': complete, 'checks': len(results), 'results': results, 'fixtures': fixtures, 'external_requests': external, 'runtime_errors': errors}, indent=2))
    def check(name, passed=True, **extra):
        results.append({'check': name, 'passed': bool(passed), **extra}); save()
        print(('PASS ' if passed else 'FAIL ') + name, flush=True)
        if not passed: raise AssertionError(name + ' ' + str(extra))
    image = Image.new('RGB', (640, 480), '#f4f1e6')
    draw = ImageDraw.Draw(image); draw.rectangle([70,70,570,370], outline='#426044', width=6); draw.line([320,70,320,370], fill='#426044', width=5); draw.text((75,410), 'SYNTHETIC QA COVER - NO CLIENT DATA', fill='#426044')
    image.save(ROOT / 'phase1b-synthetic-cover.png')
    # A valid ISO-BMFF free atom makes this a genuine 13 MiB upload. This catches
    # child-server PHP defaults that small demo videos would never exercise.
    source = (ROOT.parent/'public/assets/reels/warm-reference.mp4').read_bytes()
    padding = 13 * 1024 * 1024 - len(source)
    (ROOT/'phase1b-synthetic-upload.mp4').write_bytes(source + struct.pack('>I4s', padding, b'free') + b'\0' * (padding-8))
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, args=['--no-sandbox'])
        async def context(ip):
            ctx = await browser.new_context(viewport={'width':1360, 'height':940}, extra_http_headers={'X-Forwarded-For': ip})
            ctx.on('request', lambda req: external.append(req.url) if not req.url.startswith(BASE) and not req.url.startswith(('data:', 'blob:')) else None)
            ctx.on('page', lambda page: page.on('pageerror', lambda err: errors.append(str(err))))
            return ctx
        async def goto(page, path):
            response = await page.goto(BASE + path, wait_until='domcontentloaded'); await page.locator('h1').first.wait_for(); return response
        async def submit(button):
            async with button.page.expect_navigation(wait_until='domcontentloaded'): await button.click()
        async def login(ctx, role):
            page = await ctx.new_page(); page.set_default_timeout(15000); page.on('dialog', lambda d: d.accept())
            await goto(page, '/login'); await submit(page.locator(f'form:has(input[name="role"][value="{role}"]) button'))
            return page
        try:
            owner_ctx = await context('127.0.20.2'); admin_ctx = await context('127.0.20.3'); guest_ctx = await context('127.0.20.4')
            owner = await login(owner_ctx, 'owner'); guest = await guest_ctx.new_page(); guest.set_default_timeout(15000)
            await goto(owner, '/workspace/blog/new')
            for field, value in {'title':TITLE, 'slug':fixtures['article_slug'], 'excerpt':'A synthetic article for the browser publishing workflow. No real client, project or professional claim.', 'body':'## What does this synthetic article test?\n\nIt tests a private draft, an authorised named byline, moderation and scheduled revisions. No real project data is used.\n\n<script>window.__phase1bXss=1</script>\n\n- A useful first point.\n- A clear second point.', 'cover_alt':'Synthetic QA geometry, not a client plan.'}.items(): await owner.locator(f'[name="{field}"]').fill(value)
            await owner.locator('[name="byline"]').select_option(index=1)
            await owner.locator('[name="cover"]').set_input_files(str(ROOT/'phase1b-synthetic-cover.png'))
            await owner.locator('[name="rights"]').check(); await submit(owner.get_by_role('button', name='Save draft', exact=True))
            match = re.search(r'/workspace/blog/(\d+)/edit', owner.url); check('Owner saves a private article draft', bool(match))
            article_id = fixtures['article_id'] = int(match.group(1)); save()
            public_article = '/studios/studio-aangan/articles/' + fixtures['article_slug']
            check('Guest cannot retrieve the draft', (await guest_ctx.request.get(BASE+public_article)).status == 404)
            preview = await owner_ctx.new_page(); await goto(preview, f'/workspace/blog/{article_id}/preview')
            check('Owner preview renders safe Markdown', await preview.locator('h1').inner_text()==TITLE and await preview.evaluate('typeof window.__phase1bXss')=='undefined')
            cover_url = await preview.locator('.article-cover img').get_attribute('src'); check('Draft cover is not public', (await guest_ctx.request.get(BASE+cover_url)).status == 404); await preview.close()
            await owner.locator('[name="rights"]').check(); await submit(owner.get_by_role('button', name='Submit for review', exact=True))
            check('Studio submits a reviewable revision', 'a new revision was saved' in (await owner.locator('main').inner_text()).lower())
            admin = await login(admin_ctx,'admin'); await goto(admin,'/admin/blog')
            card = admin.locator(f'article[data-article-id="{article_id}"]'); await submit(card.get_by_role('button',name='Save review',exact=True))
            await goto(guest,public_article)
            check('Admin approval publishes the named-author article', await guest.locator('h1').inner_text()==TITLE and 'By ' in await guest.locator('.article-byline').inner_text())
            schemas = await guest.locator('script[type="application/ld+json"]').all_text_contents(); check('Published article contains parseable structured data', bool(schemas) and json.loads(schemas[0])['@graph'][0]['@type']=='BlogPosting')
            await goto(owner,f'/workspace/blog/{article_id}/edit'); await owner.locator('[name="title"]').fill('QA Phase1B Private Revision'); await owner.locator('[name="rights"]').check(); await submit(owner.get_by_role('button',name='Save draft',exact=True))
            await goto(guest,public_article); check('Editing a live article does not bypass review', await guest.locator('h1').inner_text()==TITLE)
            await owner.locator('[name="rights"]').check(); await submit(owner.get_by_role('button',name='Submit for review',exact=True))
            await goto(admin,'/admin/blog'); card=admin.locator(f'article[data-article-id="{article_id}"]'); await card.locator('[name="publish_at"]').fill((datetime.now()+timedelta(days=2)).strftime('%Y-%m-%dT12:00')); await submit(card.get_by_role('button',name='Save review',exact=True))
            await goto(guest,public_article); check('Future approved revision does not replace current content early',await guest.locator('h1').inner_text()==TITLE)
            card=admin.locator(f'article[data-article-id="{article_id}"]'); await card.locator('.article-unpublish summary').click(); await card.locator('[name="reason"]').fill('QA Phase1B publication withdrawal test'); await submit(card.get_by_role('button',name='Unpublish article',exact=True))
            check('Admin can unpublish an article', (await guest_ctx.request.get(BASE+public_article)).status==404)
            await goto(owner,'/workspace/reels'); form=owner.locator('#upload form')
            for field,value in {'title':VIDEO,'caption':'A synthetic upload and playback test. No client data and no claim of completed work.','associated_url':'https://en.wikipedia.org/wiki/Interior_design'}.items(): await form.locator(f'[name="{field}"]').fill(value)
            await form.locator('[name="video"]').set_input_files(str(ROOT/'phase1b-synthetic-upload.mp4')); await form.locator('[name="cover"]').set_input_files(str(ROOT/'phase1b-synthetic-cover.png')); await form.locator('[name="kind"]').select_option('concept'); await form.locator('[name="status"]').select_option('draft'); await form.locator('[name="rights"]').check(); await submit(form.get_by_role('button',name='Upload to Spacekind',exact=True))
            video_card=owner.locator('article.managed-reel').filter(has=owner.locator('h2',has_text=VIDEO)); edit_href=await video_card.get_by_role('link',name='Edit',exact=True).get_attribute('href'); reel_id=fixtures['reel_id']=int(re.search(r'/reels/(\d+)/edit',edit_href).group(1)); save()
            check('A 13 MiB video and cover upload as a private draft', (await guest_ctx.request.get(BASE+f'/reel-media/{reel_id}/video')).status==404 and (await guest_ctx.request.get(BASE+f'/reel-media/{reel_id}/cover')).status==404)
            await goto(owner,f'/workspace/reels/{reel_id}/edit'); form=owner.locator('#upload form'); await form.locator('[name="status"]').select_option('published'); await form.locator('[name="rights"]').check(); await submit(form.get_by_role('button',name='Save video changes',exact=True))
            await goto(guest,f'/reels/{reel_id}'); video=guest.locator('video'); await video.evaluate('(v)=>{v.muted=true;return v.play()}'); await guest.wait_for_function('() => document.querySelector("video").currentTime>0.25'); playback=await video.evaluate('(v)=>({time:v.currentTime,width:v.videoWidth,duration:v.duration})'); await video.evaluate('(v)=>v.pause()'); check('Uploaded video actually decodes and plays in Chromium',playback['time']>0 and playback['width']>0,playback=playback)
            ranged=await guest_ctx.request.get(BASE+f'/reel-media/{reel_id}/video',headers={'Range':'bytes=0-31'}); check('Video streaming supports byte ranges',ranged.status==206 and len(await ranged.body())==32)
            await goto(admin,'/admin/reels'); card=admin.locator('article.admin-reel-card').filter(has=admin.locator('h2',has_text=VIDEO)); await card.locator('[name="reason"]').fill('QA Phase1B rights hold'); await submit(card.get_by_role('button',name='Hide from public playback',exact=True)); check('Moderation hold blocks the public video', (await guest_ctx.request.get(BASE+f'/reel-media/{reel_id}/video')).status==404)
            card=admin.locator('article.admin-reel-card').filter(has=admin.locator('h2',has_text=VIDEO)); await card.locator('[name="reason"]').fill('QA Phase1B hold lifted'); await submit(card.get_by_role('button',name='Lift moderation hold',exact=True)); check('Admin can restore eligible playback', (await guest_ctx.request.get(BASE+f'/reels/{reel_id}')).status==200)
            await goto(owner,'/workspace/campaign-links'); form=owner.locator('[data-campaign-form]'); await form.locator('[name="name"]').fill('QA Phase1B Campaign'); await form.locator('[name="destination"]').select_option('reel:'+str(reel_id)); await form.get_by_role('button',name='Instagram organic',exact=True).click(); check('UTM preset fills source and medium',await form.locator('[name="utm_source"]').input_value()=='instagram' and await form.locator('[name="utm_medium"]').input_value()=='organic_social'); await form.locator('[name="utm_campaign"]').fill('qa_phase1b_journey'); await form.locator('[name="utm_content"]').fill('video_trial'); await submit(form.get_by_role('button',name='Save campaign link',exact=True))
            link_card=owner.locator('article.campaign-link-card').filter(has=owner.locator('h3',has_text='QA Phase1B Campaign')); share=await link_card.locator('[data-copy]').get_attribute('data-copy'); campaign_action=await link_card.locator('form').get_attribute('action');fixtures['campaign_link_id']=int(campaign_action.rsplit('/',1)[-1]);save();check('Saved UTM link targets the owned video',share.startswith(BASE+f'/reels/{reel_id}?') and 'utm_campaign=qa_phase1b_journey' in share)
            await guest.goto(share,wait_until='domcontentloaded'); await guest.get_by_role('link',name='Tell them about your space',exact=True).click(); await guest.wait_for_url('**/enquire?*'); form=guest.locator('[data-wizard]');check('UTM and video context reach the qualified form',await form.locator('[name="entry_context"]').input_value()=='reel:'+str(reel_id) and await form.locator('[name="utm_campaign"]').input_value()=='qa_phase1b_journey')
            for field,value in {'city':'Noida','locality':'QA synthetic locality','floor_area':'1320','brief':'QA Phase1B synthetic brief only, with no real client data.'}.items():await form.locator(f'[name="{field}"]').fill(value)
            for field,value in {'property_type':'Apartment','configuration':'2 BHK','scope':'Full-home interiors','budget':'₹10–20 lakh','readiness':'Within 1–3 months'}.items():await form.locator(f'[name="{field}"]').select_option(label=value)
            await form.locator('[data-next-step]').click(); await form.locator('[name="name"]').fill(CLIENT);await form.locator('[name="phone"]').fill('+91 9000000000');await form.locator('[name="contact_consent"]').check();await form.locator('[name="attribution_consent"]').check();await submit(form.get_by_role('button',name='Save to studio inbox',exact=True));check('Qualified submission succeeds without any tracking provider',(await guest.locator('h1').inner_text()).lower().find('good')>=0 or '/thanks' in guest.url)
            await goto(owner,'/workspace/enquiries?q=QA%20Phase1B%20Client'); link=owner.get_by_role('link',name='Open '+CLIENT,exact=True).first; href=await link.get_attribute('href');fixtures['lead_id']=int(href.rsplit('/',1)[-1]);save();await link.click();await owner.wait_for_load_state('domcontentloaded');text=await owner.locator('main').inner_text();check('CRM retains permitted UTM labels and the owning video context','qa_phase1b_journey' in text and VIDEO in text)
            await submit(owner.get_by_role('button',name='Convert to client',exact=True));fixtures['project_id']=int(owner.url.rsplit('/',1)[-1]);save();check('Enquiry converts into a client/project', '/workspace/projects/' in owner.url)
            await goto(owner,'/workspace/campaign-links');link_card=owner.locator('article.campaign-link-card').filter(has=owner.locator('h3',has_text='QA Phase1B Campaign'));counts=await link_card.locator('.campaign-link-metrics strong').all_text_contents();check('Saved campaign summary shows recorded enquiry and Won counts',counts==['1','1'],counts=counts)
            await goto(admin,'/admin/tracking');form=admin.locator('form[action="/admin/tracking"]');await form.locator('[name="enabled"]').check();await form.locator('[name="ga4_id"]').fill('G-QAPHASE1B1');await form.locator('[name="scopes[]"][value="marketplace"]').check();await form.locator('[name="note"]').fill('QA Phase1B Tracking draft, deployment stays locked');await submit(form.get_by_role('button',name='Save draft preview',exact=True))
            async def remember_version():
                text=await admin.locator('.tracking-versions>article').first.locator('strong').first.inner_text();vid=int(re.search(r'#(\d+)',text).group(1));fixtures['tracking_versions'].append(vid);save();return vid
            draft_id=await remember_version(); check('Admin saves a non-executing tracking draft','Draft' in await admin.locator('.tracking-versions>article').first.inner_text())
            await submit(admin.locator('.tracking-versions>article').first.get_by_role('button',name='Publish this saved draft',exact=True));await remember_version();await goto(guest,'/');payload=json.loads(await guest.locator('#privacyPreferencesRoot').get_attribute('data-consent-config'));check('Publishing configuration cannot override the preview delivery lock',payload['active'] is False and payload['tags']==[])
            await guest.locator('[data-open-privacy]').first.click();dialog=guest.locator('#privacyPreferences');await dialog.locator('[name="analytics"]').check();await dialog.locator('[name="marketing"]').uncheck();await dialog.get_by_role('button',name='Save my choices',exact=True).click();pref=await guest.evaluate('JSON.parse(localStorage.getItem("spacekind:public-consent"))');check('Visitor can save separate optional consent categories',pref['analytics'] is True and pref['marketing'] is False)
            await guest.locator('[data-open-privacy]').first.click();await guest.locator('#privacyPreferences [data-consent-reject]').click();pref=await guest.evaluate('JSON.parse(localStorage.getItem("spacekind:public-consent"))');check('Visitor can withdraw optional consent',not pref['analytics'] and not pref['marketing'])
            await submit(admin.get_by_role('button',name='Disable all delivery',exact=True));await remember_version();await submit(admin.locator(f'form[action="/admin/tracking/{draft_id}/restore"] button'));await remember_version();check('Admin disable and explicit rollback create versioned history',len(set(fixtures['tracking_versions']))==4)
            check('No application JavaScript runtime errors',not errors,errors=errors);check('No third-party network requests during the full journey',not external,requests=external)
            complete=True;save();(ROOT/'phase1b-journey-results.json').write_text(STATE.read_text())
        finally:
            await browser.close()
    print(json.dumps({'complete':complete,'checks':len(results),'passed':all(r['passed'] for r in results)},indent=2))

if __name__=='__main__':asyncio.run(main())
