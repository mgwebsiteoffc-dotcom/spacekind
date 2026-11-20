# Spacekind — before a real launch

The Phase 0/1A + 1B + Phase 2 development build is **not launch approval**. Keep real customer information out of shared demo accounts. The agreed brand, positioning and commercial scope remain in the master plan.

## Foundation / operations

- Production domain/host, restricted trusted hosts/proxies, HTTPS, appropriate first-party cookie policy and a proper web server/PHP-FPM. The development server and permissive Arena proxy trust must not become production defaults.
- `APP_ENV=production`, `APP_DEBUG=false`, `SPACEKIND_DEMO=false`; remove demo credentials/records/reference portfolios. New application secrets and least-privilege, separate app/test database accounts.
- Authentication review: verified identity/email, password change/reset, invitations, account recovery/support, admin MFA and stronger critical-configuration authorisation. Owner/staff is a deliberately small role model; finer permissions are not yet implemented.
- Paid subscription activation, expiry/grace/read-only/export rules and disclosed entitlements. Current records describe an unpaid pilot, not an enforced paid-renewal lifecycle.
- Abuse/rate-limit/concurrency and tenant-boundary review; actual XSS/CSRF and security testing beyond the included regressions. Test embedded authentication/cookies on the target deployment rather than inferring it from local Chromium.
- Production migrations/rollback, capacity, logs/alerts, recovery procedures, and queue/scheduler supervision when introduced. Scheduled approved articles currently become visible through publication-time queries, not external scheduled posts.
- Encrypted off-site database **and media** backups, access control/retention and real restore rehearsals. The private JSON local helper covers 26 relational tables, not binaries, sessions/tokens or production retention/deletion/export. Synthetic format-3 tests do not certify production backups.
- Wider accessibility, colour/touch/keyboard/screen-reader and device/browser testing. The 144 Chromium route/width checks are not a full accessibility or compatibility certification.

## Content, videos and discovery

- Obtain portfolio/writing/video licences and model/client permissions. Replace unverified concept-reference imagery. Check truthful completed/render/concept/AI classifications and bylines. Listing/article approval does not independently certify project claims.
- Studio/content identity verification, moderation/escalation/takedown procedures and support access/audit policy. The current admin can hold a video or unpublish an article; operational review is still required.
- Harden uploads with malware scanning, image/EXIF metadata stripping/re-encoding where appropriate, sandboxed/patched FFprobe, resource quotas, retention/deletion and orphan-file handling. Current file/MIME/codec/duration checks are not malware screening.
- Configure web-server/proxy/PHP upload/POST limits for video + cover (application 20 MB video / 3 MB cover; development runtime 24/32 MB), request timeouts, storage/CDN/range/CORS policy and broad-device codecs. No automatic transcoding exists.
- Disclose final seats/storage/video allowances and any fair-use limits before selling; current 10-video/100-MB values are pilot guardrails, not final paid entitlements.
- Review metadata/schema/canonicals and sitemap on the real domain. `SPACEKIND_ALLOW_INDEXING` and the admin indexation setting both need deliberate approval. Demo/private/query pages remain noindex; no rankings, traffic or AEO outcomes are guaranteed.

## Legal, service and billing

- Approved privacy/terms, project-contact permission, independent campaign-retention and marketing choices, withdrawal/deletion/retention rights, support contact and incident response.
- Confirm launch geography/domain, GST-inclusive/exclusive treatment, refunds/renewals/grace periods and exact optional campaign-management deliverables/capacity.
- Preserve **₹4,999/year for all five standard modules, phased**, and **₹2,999/month optional campaign-management fee**, unless the user authorises a change. Meta media spend remains separately business-paid. CRM, quotation, invoice and receipt workflows are built for development; labour operations are next.
- Have an accountant validate the implemented integer rounding, numbering, explicit GST treatment/place of supply/HSN-SAC, immutable corrections and payments/receipts before real use. Validate dates/periods and invoice retention with the applicable rules; manual entries are not bank reconciliation. Studio customer invoices and platform subscription/service invoices must remain separate ledgers/documents.

## Financial deployment / data preservation

- Apply only the incremental patch after a matching-build preflight, maintenance window, verified private MySQL + application/upload backups and a staging restore rehearsal. Preserve the existing `.env`/`APP_KEY`; never use a reset, seed or destructive migration rollback as an update. MySQL DDL can fail partially.
- Keep private PDFs/logos/tokens outside the public filesystem. Scrub bearer share URLs from access/error logs, protect runtime temp/cache files, monitor PDF resource usage and review download/share expiry/revocation/suspension on the target host. Forwarded links and downloaded copies cannot be recalled.
- Test correct PHP-FPM/OPcache/assets and HTTPS/cookies on the deployed host. The supplied bounded concurrency and Chromium/PDF tests are not production load/security/device/accounting certification.
- Maintain data-preserving correction/retention and incident procedures. After new financial writes, prefer a reviewed forward fix rather than restoring an older database and losing transactions.

## Tracking and external integrations — separate authorisation

- Keep `SPACEKIND_TRACKING_ALLOWED=false` until an explicitly authorised production/consent review. Configuration publication in the admin is not itself deployment authorisation. No live payment, WhatsApp/Meta sending or ad spend is approved merely by coding/deployment.
- Validate real provider IDs and a dedicated test property/account; inspect actual request/event payloads, referrers, cookies, automatic collection and duplicate installations. **The 28 consent tests use inert intercepted SDK stubs, not actual vendor SDK validation.** Debug mode still sends data if activated.
- Do not load tags for signed-in visitors, auth/CRM/admin/API/enquiry/private media/financial or tokenised share routes. Preserve query/fragment/sensitive-referrer safeguards; never export form fields, private plans, contact details or financial objects into a data layer.
- Review each GTM container/custom external script and its destinations independently. An allowlist/CSP is not a trust certificate or certified consent-management service. No arbitrary pasted HTML is accepted in this slice. Recheck consent expiry/versioning, GPC/DNT, category choice, withdrawal, cookie/domain handling and target-region legal requirements.
- Current instrumentation queues permitted provider page views only; custom enquiry/Reel-play/signup/payment events, full first/last-touch history and provider attribution/reporting remain later work. Retained UTM labels/matching counts are not verified acquisition causality or revenue.
- Select official payment/messaging/ad providers; complete onboarding/app reviews and permissions. Test signed/idempotent/retried webhooks, opt-outs/templates, failure states, reconciliation and revocation before live authorisation. No unofficial WhatsApp Web/QR automation.
- Uploaded Reel-style media is simply **panel video + associated URL → playback on Spacekind**, never an Instagram connection, publishing, scraping or synchronisation workflow.

## Explicitly unfinished product areas

Statutory accounting/GST filing/IRNs/e-way bills/credit notes, bank reconciliation, Phase 3 labour operations, paid subscription lifecycle, full curated mobile API resources, richer permissions, first/last-touch attribution/custom business events and all live external providers. The current project/handover record remains lightweight, not a full interior ERP. Technical content/measurement controls exist, but real content rights, operational validation and live activation do not follow automatically.
