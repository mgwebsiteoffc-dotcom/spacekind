# Phase 1B — Content, videos and campaign context

**Working development slice, extending the Phase 0/1A CRM.** No external provider was activated. Use the fictional demo only. The existing Spacekind visual language, five-module annual package and optional-service pricing are unchanged.

## Public experience

- **The journal — `/blog`:** platform editorial and approved studio perspectives; category/publisher filters, visible bylines and useful short introductions.
- **Article pages:** safe Markdown, cover/alt text, attribution and author/publisher structured data. Studio articles lead back to their owning studio/enquiry; drafts and revisions are private.
- **In motion — `/reels`:** vertical video cards; dedicated player with ordinary playback/mute/full-screen controls, previous/next links, classification/caption, owning studio and enquiry action. The associated HTTPS URL is a separate outbound link.
- Studio profiles now show their approved articles and public videos alongside galleries. No Instagram credentials, integration, posting, fetching or scheduling exists.

## Owner workflow

### Studio journal

1. Open **Studio journal → Write an article**.
2. Choose the business byline or an active authorised member of this same studio. A named byline is not an independent professional verification and never exposes a login email.
3. Write a title, introduction and Markdown body. Optionally add a cover/alt text, category/tags and search details. Confirm rights before saving.
4. **Save draft** stays private; **Submit for review** creates a submitted revision. An admin can request changes or approve now/at a future date.
5. A later edit creates another private revision. It does not overwrite the approved public version. Concurrent stale editors must reload before saving.
6. Preview the saved revision. Archive hides the studio article while preserving history. The permanent slug/canonical stays stable; a slug-renaming/redirect manager is not provided.

Platform administrators use **Platform journal** to publish their own editorial/named-admin articles and review business submissions. Unpublication records a reason; time passing does not republish an explicitly hidden article.

### Videos & links

Upload **video + cover + title/caption + classification/category + associated URL**. Save as draft, preview, then select public visibility. The studio must be approved/active. Replace/edit, order or delete your own files. An admin moderation hold overrides the owner’s Public selection until an administrator explicitly lifts it.

Current technical guardrails, not final subscription entitlements:

| Item | Limit |
|---|---|
| MP4 | H.264 video, optional AAC audio |
| WebM | VP8/VP9 video, optional Opus/Vorbis audio |
| Each video | 20 MB / 90 seconds; dimensions 240–3840 px |
| Studio | 10 videos / 100 MB of video bytes |
| Cover | JPG/PNG/WebP ≤3 MB; at least 240 × 200 px |

FFprobe inspects real uploaded media with a timeout and restricted input protocols. Missing/failed inspection rejects the upload. Covers and videos are outside the public web root behind visibility/tenant checks. Native byte-range responses support seeking. There is no automatic transcoding, CDN, virus scanner or promise of universal device support.

The demo’s two four-second motion studies are generated from reference stills with visible development/reference labels. They are not completed studio projects. The associated Wikipedia page is general reference, not evidence of publication or image ownership.

### Campaign links

Select **your own** eligible profile, enquiry form, published article or video. Add source, medium and campaign; content/term are optional. Presets cover Instagram organic, Meta paid-social and Google search. Save/copy the URL into a location that actually supports clickable links.

No contact details, addresses or financial data belong in labels. Validation blocks obvious email/phone/URL-shaped values, but human review is still required: validation cannot identify every kind of personal data.

Available labels and validated article/video entry context travel within that public journey. **Separate optional campaign-retention permission at submission** determines whether the CRM stores them. They survive client conversion; they do not require an analytics or Meta tag to run.

Reports mean:
- **Matching-label enquiries:** submitted records with the same retained labels; article/video links additionally match the entry content.
- **Recorded Won:** those enquiries that the studio has marked Won, including conversion to a client/project.
- Reused labels can overlap. These are not unique visits, ad-platform conversion reports, verified revenue or proof of causation.

No persistent first/last-touch history, click counter, automatic WhatsApp attribution, shortened redirect service or cross-device identity is implemented. The source can remain unknown. Read-only owner campaign links are available through `/api/v1/campaign-links`; creation/deletion currently use the web panel.

## Admin discovery and tracking

### Discovery

Edit permitted global titles/descriptions and request public indexation. Owners can edit their own profile/article search fields. Canonical/social metadata and truthful schema render server-side. Indexation needs both deployment permission and the admin setting; demo/private/search/filter pages remain noindex. Sitemaps exclude drafts, unpublished/suspended studios and private/enquiry routes. Tools cannot guarantee rankings, rich results or AI citations. A redirect manager, mass location pages and auto-generated FAQ claims are not included.

### Measure with permission

- Configure typed direct GA4/Meta IDs **or** GTM, not direct duplicates plus GTM.
- Optional custom integration: reviewed HTTPS `.js` URL on an explicit deployment allowlist, name/purpose, consent category and eligible public-page groups. No arbitrary pasted HTML or inline scripts.
- Save draft → inspect stored values → explicitly publish. Disable and rollback append new published versions; history/audit records remain. Disable applies to subsequent page loads, not already-collected provider data.
- Production environment + deployment flag + enabled published configuration + permitted page group + visitor category consent must all permit delivery. These cannot be bypassed from the admin panel alone.
- Never delivered to signed-in visitors or auth/CRM/admin/API/enquiry/private media pages. Query strings, fragments and sensitive referrers are suppressed. This deliberately means a UTM URL is not itself an analytics page view; permitted CRM label retention is independent.
- Optional consent defaults off, separates analytics/marketing, expires after 180 days, respects GPC/DNT and offers rejection/withdrawal. Withdrawal reloads loaded-tag pages, clears known accessible first-party vendor cookies and propagates to other loaded tabs. It cannot delete vendor-held data or arbitrary unknown third-party storage.
- GA4 receives a canonical page context/stripped referrer with Google signals/ad-personalisation disabled. Meta automatic configuration is disabled; no advanced matching or lead fields are queued. This must still be checked against actual authorised SDK/network behaviour before any live deployment.

**Preview delivery remains locked, with no live IDs/providers configured.** The offline tests intercept every request and use inert provider stubs; they verify loader decisions, not real SDK payloads. GTM/custom scripts remain privileged, and a domain allowlist is not a safety certificate. This is not a certified CMP.

Custom enquiry/Reel-play/WhatsApp/signup/payment business-event instrumentation and first/last-touch attribution remain later work. No form/CRM values are deliberately sent in this slice.

## Validation and remaining gates

Current evidence: **56 PHP tests / 485 assertions; 144 route/width checks; 29 browser journey assertions; 28 offline consent/network assertions**. The journey includes a genuine 13 MiB file upload, Chromium decoding and byte-range streaming. The actual preview emitted no third-party requests in the checked flows. Recovery format 2 was round-tripped with all 15 tables populated and foreign keys on in the isolated test database.

See `README.md` for commands/reports and `launch-gates.md` for production requirements. Remaining gates include broad browser/mobile device QA, actual provider SDK/payload validation after authorisation, secure deployment/authentication/admin MFA, malware/metadata/retention review, asset rights, quotas, monitoring and encrypted off-site backup/restore. No production financial, labour or external-integration capability is claimed.
