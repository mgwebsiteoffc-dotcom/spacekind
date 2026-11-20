# Phase 1B implementation checkpoint

The user authorised the next milestone. The foundation/small CRM is preserved, with Spacekind’s editorial ivory/green design and unchanged package/service pricing.

## Implemented development slice

- Tenant-owned article revisions; business/active-member/editorial bylines; safe Markdown, private cover/preview, review/changes, approved scheduling and stable canonicals; no silent overwriting of live content.
- Owner MP4/WebM uploads, covers/captions/classification and public HTTPS associated links; private/public ACLs, inspection/quotas/replacement/deletion, native Reel-style playback and admin hold. No Instagram account, scraping or publishing workflow.
- Owned destination UTM presets/copy/saved links; matching-label enquiries and recorded-Won summaries. Separate submission permission retains available current-journey labels/validated article-video context through client conversion. Owner read API.
- Global/profile/article metadata and truthful schema; robots/sitemap with deployment/admin indexation gates.
- Versioned typed tracking settings, explicit draft/publication, disable/rollback; approved external `.js` URL instead of arbitrary HTML; production/demo/auth/route/query/scope/CSP gates; independent category consent, GPC/DNT, 180-day expiry, withdrawal and cross-tab refresh.
- Guarded fictional content seeder, format-2 private recovery for 15 tables/legacy compatibility, inherited PHP upload limits via the preview launch script, documentation/master-plan updates and a separate source package.

## Executed checks

- 56 PHP tests / 485 assertions against actual MySQL; all pass. Includes strict format-2 restore of all 15 populated tables with foreign keys on, legacy format-1, populated-table refusal and invalid-format rejection. Test snapshots use isolated temporary paths, never the real preview snapshot.
- 144 public/owner/admin route/width checks across 1440, 768, 390 and 320 px, zero recorded overflow/JS exceptions/external requests.
- 29 complete browser journey assertions, including a genuine 13 MiB panel upload, H.264 decoding/playback, 206 byte-range streaming, publication/moderation, UTM enquiry/client retention and tracking history/demo lock. Zero third-party requests. Exact QA fixtures removed afterwards.
- 28 offline consent/provider-request assertions using the real consent JavaScript/rendered preferences, with every request fulfilled/aborted locally and SDKs replaced by inert stubs. No live provider request. These are not real vendor payload validation.
- 136 PHP/template syntax checks, Pint, Blade compilation, Composer validation, shell syntax and existing-workspace preparation. The latter is not a fresh-machine setup rehearsal.

Browser testing exposed/fixed nondeterministic same-second tracking history ordering; the suite now has a regression. Malformed login limiter identities no longer trigger type/array errors. PHP `-d ... artisan serve` does not affect its child server: the inherited scan-directory configuration now sets 24/32 MB and was exercised by the 13 MiB upload.

## Deliberately not claimed

Live tracking/providers, actual SDK payload compliance, a certified CMP, custom enquiry/Reel/signup/payment events, persistent first/last-touch history, visit/click/causal ad/revenue attribution, curated production/mobile API resources, financial/labour modules, enforced paid subscription lifecycle, production asset rights or complete security/accessibility/device/deployment approval.

See `README.md`, `docs/phase-1b.md`, `docs/api.md`, `docs/assets.md` and `docs/launch-gates.md`. Current source package is `../Spacekind-phase-1B-build.zip`; the earlier foundation archive is kept separate. No payments, messages, ads or tags were activated.
