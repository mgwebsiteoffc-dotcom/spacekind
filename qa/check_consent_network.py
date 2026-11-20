"""Offline browser tests of the real consent.js + rendered preferences component.
Every HTTP request is fulfilled or aborted by Playwright. Provider SDKs are inert
stubs: no live provider, DNS request, API call or tracking activation is performed.
PHP deployment/auth/route/scope/CSP gates are covered separately by feature tests.
"""
import asyncio
import html
import json
import os
import re
import time
import urllib.request
from pathlib import Path
from playwright.async_api import async_playwright

ROOT = Path(__file__).resolve().parent
ORIGIN = 'https://spacekind-qa.invalid'
KEY = 'spacekind:public-consent'
VERSION = 'offline-consent-test-v1'
SOURCE = (ROOT.parent/'public/assets/consent.js').read_text()
with urllib.request.urlopen(os.getenv('SPACEKIND_BASE_URL','http://127.0.0.1:8000') + '/', timeout=15) as response:
    live_html = response.read().decode()
component = re.search(r'<div id="privacyPreferencesRoot".*?</dialog></div><script src="/assets/consent.js" defer></script>',live_html,re.S).group(0)
CSP = "default-src 'self'; script-src 'self' https://www.googletagmanager.com https://connect.facebook.net https://reviewed.invalid; connect-src 'self'; object-src 'none'; base-uri 'self'"
DIRECT = [
    {'provider':'ga4','id':'G-TEST12345','category':'analytics','debug':True},
    {'provider':'meta','id':'123456789012345','category':'marketing'},
    {'provider':'custom','url':'https://reviewed.invalid/inert-test.js','category':'marketing'},
]

async def main():
    results, unexpected, runtime_errors = [], [], []
    def check(name, passed, **details):
        results.append({'check':name,'passed':bool(passed),**details})
        (ROOT/'phase1b-consent-network-results.json').write_text(json.dumps({'checks':len(results),'complete':False,'mode':'offline request interception; inert provider SDK stubs','unhandled_requests':unexpected,'runtime_errors':runtime_errors,'results':results},indent=2))
        print(('PASS ' if passed else 'FAIL ')+name,flush=True)
        if not passed: raise AssertionError(name+' '+str(details))
    async with async_playwright() as p:
        browser=await p.chromium.launch(headless=True,args=['--no-sandbox'])
        async def fixture(tags=None, stored=None, signal=None, active=True, referrer=None, path='/synthetic-studio', storage_blocked=False):
            tags=DIRECT if tags is None else tags
            cfg={'version':VERSION,'active':active,'tags':tags,'canonical':ORIGIN+'/synthetic-studio','group':'studio'}
            body='<!doctype html><meta charset="utf-8"><title>Offline consent fixture</title><h1>Offline consent QA</h1><button data-open-privacy>Preferences</button>'+re.sub(r'data-consent-config="[^"]*"','data-consent-config="'+html.escape(json.dumps(cfg),quote=True)+'"',component,count=1)
            ctx=await browser.new_context(viewport={'width':1000,'height':900},service_workers='block')
            requests=[]
            if stored is not None:
                await ctx.add_init_script("if(!localStorage.getItem("+json.dumps(KEY)+"))localStorage.setItem("+json.dumps(KEY)+","+json.dumps(json.dumps(stored))+");")
            if signal:
                prop='globalPrivacyControl' if signal=='gpc' else 'doNotTrack'
                value='true' if signal=='gpc' else "'1'"
                await ctx.add_init_script("Object.defineProperty(navigator,"+json.dumps(prop)+",{get:()=>"+value+"});")
            if storage_blocked:
                await ctx.add_init_script("Storage.prototype.getItem=()=>{throw new DOMException('Blocked','SecurityError')};Storage.prototype.setItem=()=>{throw new DOMException('Blocked','SecurityError')};")
            async def route_handler(route):
                url=route.request.url
                if url==ORIGIN+'/assets/consent.js':
                    await route.fulfill(status=200,content_type='application/javascript',body=SOURCE)
                elif url.startswith(ORIGIN+'/'):
                    await route.fulfill(status=200,content_type='text/html',headers={'Content-Security-Policy':CSP,'Referrer-Policy':'strict-origin-when-cross-origin'},body=body)
                elif url.startswith(('https://www.googletagmanager.com/','https://connect.facebook.net/','https://reviewed.invalid/')):
                    requests.append({'url':url,'referrer':route.request.headers.get('referer','')})
                    await route.fulfill(status=200,content_type='application/javascript',body='window.__qaSdkLoads=(window.__qaSdkLoads||0)+1;')
                else:
                    unexpected.append(url);await route.abort()
            await ctx.route('**/*',route_handler)
            page=await ctx.new_page();page.set_default_timeout(8000)
            page.on('pageerror',lambda err:runtime_errors.append(str(err)))
            await page.goto(ORIGIN+path,referer=referrer,wait_until='load')
            await page.wait_for_timeout(60)
            return ctx,page,requests
        async def choose(page, analytics, marketing, reload=False):
            await page.locator('[data-open-privacy]').first.click()
            form=page.locator('#privacyPreferencesForm')
            await form.locator('[name="analytics"]').set_checked(analytics)
            await form.locator('[name="marketing"]').set_checked(marketing)
            if reload:
                async with page.expect_navigation(wait_until='load'):
                    await form.get_by_role('button',name='Save my choices',exact=True).click()
            else:
                await form.get_by_role('button',name='Save my choices',exact=True).click()
            await page.wait_for_timeout(70)
        async def pref(page):return await page.evaluate('(key)=>JSON.parse(localStorage.getItem(key))',KEY)
        all_yes={'version':VERSION,'analytics':True,'marketing':True,'at':int(time.time()*1000)}
        try:
            ctx,page,requests=await fixture()
            check('No optional provider request precedes a choice',len(requests)==0 and await page.locator('#optionalConsentBanner').is_visible())
            await choose(page,True,False)
            await page.wait_for_function('() => window.__qaSdkLoads===1')
            check('Analytics-only consent injects GA4, not Meta or a marketing script',len(requests)==1 and '/gtag/js?id=G-TEST12345' in requests[0]['url'])
            commands=await page.evaluate('() => dataLayer.map(item=>Array.from(item))')
            config=next(item[2] for item in commands if item[0]=='config')
            default=next(item[2] for item in commands if item[:2]==['consent','default'])
            update=next(item[2] for item in commands if item[:2]==['consent','update'])
            check('Google starts denied; analytics choice does not grant ad data',all(v=='denied' for v in default.values()) and update['analytics_storage']=='granted' and update['ad_storage']=='denied' and update['ad_user_data']=='denied')
            check('GA4 configuration uses canonical context and disables signals',config['page_location']==ORIGIN+'/synthetic-studio' and config['page_referrer']=='' and config['allow_google_signals'] is False and config['allow_ad_personalization_signals'] is False)
            check('Provider script request exposes only origin as referrer',requests[0]['referrer']==ORIGIN+'/')
            await choose(page,True,False)
            check('Saving unchanged choices does not duplicate a loaded provider',len(requests)==1)
            await page.reload(wait_until='load');await page.wait_for_timeout(70)
            check('Saved valid consent persists without a repeated banner',len(requests)==2 and not await page.locator('#optionalConsentBanner').is_visible())
            await choose(page,True,True,reload=True)
            await page.wait_for_function('() => window.__qaSdkLoads===3')
            queue=await page.evaluate('() => window.fbq.queue.map(item=>Array.from(item))')
            check('Marketing choice permits only the configured Meta/custom requests',len(requests)==5 and any('fbevents.js' in r['url'] for r in requests) and any('/inert-test.js' in r['url'] for r in requests))
            check('Meta automatic configuration is disabled; no advanced-matching fields are queued',queue[0]==['set','autoConfig',False,'123456789012345'] and ['init','123456789012345'] in queue and ['track','PageView'] in queue)
            await page.evaluate("() => {document.cookie='_ga=synthetic;path=/';document.cookie='_fbp=synthetic;path=/';document.cookie='qa_necessary=keep;path=/'}")
            await page.locator('[data-open-privacy]').first.click()
            async with page.expect_navigation(wait_until='load'):await page.locator('#privacyPreferences [data-consent-reject]').click()
            await page.wait_for_timeout(70);cookies={c['name'] for c in await ctx.cookies()}
            check('Withdrawal reloads without reinjecting any optional provider',len(requests)==5 and (await pref(page))['analytics'] is False and await page.evaluate('typeof window.__qaSdkLoads')=='undefined')
            check('Withdrawal clears known vendor cookies, not necessary app cookies','_ga' not in cookies and '_fbp' not in cookies and 'qa_necessary' in cookies)
            await ctx.close()
            for label,stored in [('expired',{**all_yes,'at':int(time.time()*1000)-181*86400000}),('changed-version',{**all_yes,'version':'old-consent'}),('invalid-future',{**all_yes,'at':int(time.time()*1000)+86400000})]:
                ctx,page,requests=await fixture(stored=stored)
                check('Invalid '+label+' preference cannot enable providers',not requests and await page.locator('#optionalConsentBanner').is_visible());await ctx.close()
            for signal in ['gpc','dnt']:
                ctx,page,requests=await fixture(stored=all_yes,signal=signal)
                await page.locator('[data-open-privacy]').first.click()
                check(signal.upper()+' overrides stored consent and disables category controls',not requests and await page.locator('[name="analytics"]').is_disabled() and not await page.locator('[name="analytics"]').is_checked() and await page.locator('#browserPrivacyNotice').is_visible());await ctx.close()
            for label,path,ref in [
                ('query','/synthetic-studio?client=synthetic',None),
                ('fragment','/synthetic-studio#preferences',None),
                ('query-referrer','/synthetic-studio',ORIGIN+'/article?private=synthetic'),
                ('private-workspace-referrer','/synthetic-studio',ORIGIN+'/workspace/enquiries/1'),
                ('enquiry-referrer','/synthetic-studio',ORIGIN+'/studios/demo/enquire/thanks'),
            ]:
                ctx,page,requests=await fixture(stored=all_yes,path=path,referrer=ref)
                check('Sensitive '+label+' context suppresses all optional requests',not requests);await ctx.close()
            ctx,page,requests=await fixture(stored=all_yes,active=False)
            check('A server-inactive payload cannot be bypassed by stored consent',not requests);await ctx.close()
            ctx,page,requests=await fixture(stored=all_yes,referrer=ORIGIN+'/safe-article')
            commands=await page.evaluate('() => dataLayer.map(item=>Array.from(item))')
            config=next(item[2] for item in commands if item[0]=='config')
            check('Permitted referrer is stripped to origin in GA4 configuration',len(requests)==3 and config['page_referrer']==ORIGIN+'/');await ctx.close()
            ctx,page,requests=await fixture(storage_blocked=True)
            await page.locator('[data-consent-reject]').first.click()
            check('Blocked browser storage degrades safely without provider requests',not requests and not await page.locator('#optionalConsentBanner').is_visible());await ctx.close()
            ctx,page,requests=await fixture(tags=[{'provider':'gtm','id':'GTM-TEST123','category':'marketing'}])
            await choose(page,True,False)
            check('GTM does not load on analytics-only consent',not requests)
            await choose(page,True,True)
            await page.wait_for_function('() => window.__qaSdkLoads===1')
            check('Marketing consent permits the configured GTM container once',len(requests)==1 and requests[0]['url']=='https://www.googletagmanager.com/gtm.js?id=GTM-TEST123')
            await ctx.close()
            ctx,page,requests=await fixture(stored=all_yes)
            other=await ctx.new_page();other.on('pageerror',lambda err:runtime_errors.append(str(err)))
            await other.goto(ORIGIN+'/synthetic-studio',wait_until='load');await other.wait_for_timeout(70)
            count_before=len(requests)
            await other.locator('[data-open-privacy]').first.click()
            async with page.expect_navigation(wait_until='load'):
                async with other.expect_navigation(wait_until='load'):
                    await other.locator('#privacyPreferences [data-consent-reject]').click()
            await page.wait_for_timeout(70)
            check('Cross-tab withdrawal reloads both tabs without provider reinjection',count_before==6 and len(requests)==6 and await page.evaluate('typeof window.__qaSdkLoads')=='undefined' and await other.evaluate('typeof window.__qaSdkLoads')=='undefined')
            await ctx.close()
            check('No unhandled request or JavaScript runtime error in offline fixtures',not unexpected and not runtime_errors,unhandled=unexpected,errors=runtime_errors)
        finally:await browser.close()
    report={'complete':True,'checks':len(results),'mode':'offline request interception; inert provider SDK stubs; no live SDK/payload verification','unhandled_requests':unexpected,'runtime_errors':runtime_errors,'results':results}
    (ROOT/'phase1b-consent-network-results.json').write_text(json.dumps(report,indent=2))
    print(json.dumps({'checks':len(results),'passed':all(r['passed'] for r in results),'live_provider_requests':0},indent=2))

if __name__=='__main__':asyncio.run(main())
