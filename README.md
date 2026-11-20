# Spacekind

## Current branch: public films, site attendance and worker statements

**Studio owners:** open **More → Worker register** to add a worker, their trade, daily wage and the date it takes effect. Add later rates without editing earlier ones. Select **Mark attendance**, open the work date, then choose the project worked in each half of the day. One half earns half the daily rate (rounded to the nearest paise, half up); two halves earn the full rate. Two projects can share one day, not claim two wages. An explicit absence earns zero; an untouched worker is not absent. The first worked entry freezes the applicable rate. Save each page before navigating; corrections retain a before/after audit and stale edits are rejected.

Open **Payments & balance** beside a worker to enter actual wage payments or advances. Project attribution is optional. Outstanding = attendance earnings − recorded advances − recorded wages (across all dates and projects); a negative result appears as **Paid ahead**. Correct a mistake by reversing with a reason and, if needed, recording a replacement. Reversals are retained, not refunds. Repeating the same form submission does not create another payment; always check the history before recording a separately entered transaction. These entries never move funds or create customer receipts.

Use **View statement & export** in that ledger to select an inclusive date range (up to 366 days) and all projects, a single project, or unassigned payments. Opening balance includes activity before the selected dates; daily earnings minus advances and wage payments carry forward into closing balance. Reports use current corrected attendance and non-reversed payments, not an immutable historical snapshot. A split day contributes its first half rounded up to the first site and the remainder to the second; an unsplit day or single half belongs entirely to its site. Unassigned payments are never silently charged to a project. **Download this statement (CSV)** exports those same daily figures in INR. The statement update needs no new migration or seed on installations already using worker payments.

**Already on the worker-register update?** Back up MySQL and uploads, update the files and run `php artisan migrate`. New labour records start empty; no seeder is needed for this step.

**Updating from Phase 2:** back up MySQL and uploads, then run the following against the intended installation after updating the files. Keep `.env`, `APP_KEY`, accounts and uploads. Do **not** run `migrate:fresh` or the general demo seeder.

```sh
php artisan migrate
php artisan db:seed --class=InspirationSeeder
```

The dedicated seeder inserts three missing editorial inspiration records; rerunning it preserves existing entries. It does not create studios, leads, accounts or financial records. The directory, journal and film cards use eligible published records, not invented activity. A site with no published videos will not show a fabricated film on its homepage. Replace the bundled design references with licensed material before commercial use (see [asset notes](docs/assets.md)). Local recovery now writes format 6 and accepts formats 1–5; when restoring an old snapshot into an empty migrated database, restore **before** running the editorial seeder.

**Next acceptance check:** run the worker register, attendance, ledger, statement and recovery feature tests on an isolated MySQL 8 test database before deploying the labour module.

The release, ZIP and validation counts below describe the **previous Phase 2 handoff**, not validation of this branch.

**Laravel + actual MySQL · Foundation / Phase 1B + Phase 2 financial workflows · Development pilot**

> We help interior-design businesses generate qualified enquiries on WhatsApp, follow up consistently, and track which ads produce customers.

An interior-design-business SaaS, retaining Spacekind’s warm ivory / forest-green / editorial design. Marketplace and direct enquiries work alongside paid acquisition. It is not merely a founder-operated agency or a static mock dashboard.

**This handoff is an incremental patch for the already installed latest Phase 1B build—not the complete project again.** Keep the current `.env`, `APP_KEY`, accounts, database and uploads. Follow **[`docs/update-phase-2.md`](docs/update-phase-2.md)**; do not run a fresh setup, reset, seeder or key generation. Release: `phase-2-finance-pilot.1` (`spacekind-release.json`).

## What to check now

Owner: **Invoices & receipts / More → Billing setup → Catalogue → Quotations → Invoices → Payments & advances → Receipts**. Existing same-studio clients/projects are used; none are automatically seeded by the patch. Super-admin: **Platform billing**, separate from studios’ customer invoices. Owners read their issued platform bills under **Spacekind bills**.

Financial walkthrough, exact rules, limitations and checks: **[`docs/phase-2.md`](docs/phase-2.md)**. First use a private staging clone with synthetic data. Accountant/deployment acceptance is required before real fiscal records.

| Area | Working development slice |
|---|---|
| Marketplace / business website | Approved/active studios; discovery/search/filter; profiles, saved studios, galleries, articles/videos; paid **and organic/direct** acquisition; honest availability/pricing |
| Accounts / CRM | Studio owner/staff, tenant-before-binding, fail-closed scopes, fresh suspension checks; qualified two-step enquiry, private plans and separate permissions; stages, assignments, notes, dated in-app follow-ups |
| Clients / projects | Idempotent Won conversion; source/UTM/consented context retained; lightweight project stages, target/notes/first handover; owner-only linked financial history |
| Journals | Platform/studio safe-Markdown revisions, authorised business/member/editorial bylines, private drafts/covers, review/scheduling, archive/unpublish |
| Uploaded videos | Panel MP4/WebM + cover + HTTPS associated URL, classification/rights controls, draft/private/public ACL, quotas and admin hold; native Reel-style Spacekind playback, **no Instagram connection/publishing** |
| UTM / discovery / tracking | Owned-destination campaign links, retained-label enquiry/Won summaries, SEO/schema and indexation gates; typed versioned GA4/Meta **or** GTM, approved external script URL and visitor consent; **live delivery locked** |
| Quotations | Material/labour/service catalogue, quantity/unit/rate/line discount/HSN-SAC/GST, draft/version protection, issue/frozen snapshot, explicit manually recorded sent/accepted/rejected/expired, duplicate/revise, accepted quote → one invoice draft |
| Customer invoices | Studio/FY numbering, full billing identity, explicit CGST/SGST or IGST/inclusive/non-GST basis, immutable issued details, derived unpaid/partial/paid/overdue, reasoned void/copy, in-app reminders, filtered register and permissioned CSV |
| Manual payments / receipts | Client/project/invoice cash/UPI/bank/cheque/other entries; one receipt per payment; UUID repeat-submit protection; partial payments, identified advances/allocations and reversals with history |
| Private financial PDFs / shares | Branded on-demand A4 PDFs, private JPG logo, draft/void/demo markings; hashed expiring/revocable bearer links, no-store/no-referrer/noindex, suspension denial; no automatic sending |
| Separate platform billing | Admin issuer/settings, fixed annual software / optional monthly management fees, reviewed GST totals, separate invoices/sequence/manual payments, own-business read-only owner access; **no checkout or entitlement activation** |
| API / admin / local recovery | Sanctum foundation API plus curated owner invoice read endpoint; moderation/audits; format-3 local development recovery, 26 application tables and legacy format-1/2 compatibility |

### Commercial terms — unchanged

- **₹4,999/year covers all five standard modules, phased:** CRM, Instant Quotation, Invoice Software, Receipt Management and Labour Management. The first four now have development workflows; **labour operations are next**, not a separately priced standard add-on.
- **₹2,999/month optional campaign management is a service fee only.** The business pays Meta spend separately; it is neither included nor deducted.
- Explicit platform GST basis is configurable for review; final commercial renewals/refunds/entitlements/service terms and accountant approval remain launch decisions. New accounts remain **unpaid pilots**, with pending public listings.

## Important boundaries

This is a **development application, not commercial-launch approval**. Shared fictional demo data/accounts, reference images/videos and locally tested workflows are not verified studio work, statutory accounting, a production security audit or revenue/attribution proof. Use synthetic data in the shared demo.

- Recording a payment/receipt is **not collecting or bank-verifying money**. Reversal is not a bank refund. A CRM Won result is not payment. Retry UUIDs protect the same submission, not separately keyed entries with a duplicated real-world reference.
- Manual sent/acceptance decisions are not actual message delivery or digital signatures. Private bearer links can be forwarded; already downloaded PDFs cannot be recalled.
- No GST filing/IRN/e-way bills/statutory credit notes, bank reconciliation or universal GST classification validation. Issued records are retained; corrections are explicit and auditable. Have an accountant review tax/rounding/numbering/corrections.
- No checkout, renewal enforcement, WhatsApp/Meta sending, ads/spend transfer, real tags, emails/OTP or automatic follow-up delivery is activated. Each external connection requires separate configuration, consent, testing and authorisation.
- UTM summaries are consent-retained labels/context, not causal ad attribution, visits, cross-device first/last-touch history or verified revenue. No custom enquiry/payment event telemetry is emitted.
- Tracking gates still exclude signed-in/auth/CRM/admin/API/enquiry/private media/financial/share contexts. Custom scripts are deployment-allowlisted HTTPS `.js` URLs, not pasted arbitrary HTML. Offline consent tests are not real vendor SDK/payload certification.
- No Instagram login/API/publishing/scraping/scheduling workflow. Users upload videos on the panel and add a URL; Spacekind displays them in a Reel-style experience.

## Running local preview

Use Arena’s **Spacekind website & workspace** live preview. Public directory/journal/videos remain available. Studio login demo buttons are enabled only in a local fictional demo, blocked in production.

| Demo role | Email | Fictional demo password |
|---|---|---|
| Aangan owner | `owner@aangan.example` | `Spacekind@2026` |
| Aangan staff | `team@aangan.example` | `Spacekind@2026` |
| Other studio owner | `owner@form-grain.example` | `Spacekind@2026` |
| Super-admin | `admin@spacekind.example` | `Spacekind@2026` |

Never reuse these credentials or deploy shared demo records in production. Staff retain CRM/project permissions; content/configuration/finance/export are owner-only here. Finance settings start blank; the patch does not create fake invoices in an existing register.

Existing local preview runtime launch scripts are unchanged. This patch requires `composer install` from the patched lock (Dompdf) and the three additive migrations in the update guide; no front-end build is needed. PHP 8.4/MySQL 8.4 were tested; PHP 8.3+ and actual MySQL 8+ are checked. JPG logos were rendered without GD; private uploads still need production malware/metadata policy. Video upload limits remain 24 MB PHP upload / 32 MB POST for application 20 MB video + 3 MB cover.

`spacekind:snapshot` is **local-demo only**, format 3 / 26 tables; it includes private hashes/records, not media binaries, sessions/device tokens or encrypted/off-site backups. Restore refuses occupied tables. Never package or upload private recovery snapshots. Preserve/upload binaries separately and use real MySQL + files backups before deployment.

## Validation evidence

- **83 PHP tests / 750 assertions** on isolated actual MySQL: foundation, content, finance and recovery regressions.
- **26 complete financial browser checks**, **68 financial route/width checks** at 1440 / 768 / 390 / 320 px; no recorded overflow, JS exceptions or third-party requests.
- **12 concurrent retry/numbering/PDF checks**: six simultaneous invoice issues, six repeated payment submissions, four allocation retries; correct unique numbers/receipt counts/balances; all 50 lines + terms in a six-page PDF.
- PHP syntax/style, Blade/Composer/JS/Python checks, baseline hash, patch-only manifest/CRC/secrets/path verification and staged baseline-to-patch rehearsal.
- Historical Phase 1B evidence remains separately labelled: 144 responsive checks, 29 publishing/video/UTM journey assertions (including 13 MiB upload/playback/206), 28 offline inert-provider consent assertions. It is not new live integration evidence.

Financial reports are under `qa/`; new browser/concurrency write fixtures have explicit environment/database and identity guards and were cleaned afterwards. Do **not** run the test suite on a deployed database. `phpunit.xml` targets `spacekind_test`; use separate test credentials and isolation. Concurrency QA requires that test database to be empty. Browser QA is restricted to this local fictional demo.

## Delivery and next work

**Only `Spacekind-phase-2-finance-patch.zip` is the current handoff.** Its `upload/` folder is the new/changed-file delta; no full scaffold/dependencies/private state. `preflight.php`/`manifest.json` live beside it, outside the website. The delivered Phase 1B ZIP is immutable; the old full-source packager is retired. No full project is resent.

Next: **Phase 3 labour** (attendance, historical rates, worker advances/wages/balances), then separately authorised official payment/WhatsApp/Meta integrations, later native ads/AI/mobile expansion. Paid subscription lifecycle, curated financial mobile-write resources, richer permissions, target-host security/legal/accessibility/backup/asset-rights acceptance remain explicit gates.

References: [`docs/phase-2.md`](docs/phase-2.md), [`docs/update-phase-2.md`](docs/update-phase-2.md), [`docs/api.md`](docs/api.md), [`docs/launch-gates.md`](docs/launch-gates.md), historical [`docs/phase-1b.md`](docs/phase-1b.md), and the approved master plan.

The existing public/workspace/admin route smoke suite was also rerun after Phase 2: **144 checks passed** with no page overflow, runtime JS errors or third-party requests (`qa/phase2-foundation-responsive.json`). One initial oversubscribed parallel QA attempt timed out in FFprobe/browser screenshots/login; focused rechecks and the final suites ran sequentially without weakening validation or raising application timeouts.
