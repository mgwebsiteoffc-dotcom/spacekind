# Spacekind

## Project concierge and public collections

After migrating, **Admin → Popups & project leads** independently enables delayed entry, desktop exit intent, or a floating button for signed-out visitors to the homepage, `/inspiration`, and `/reels`. The floating link is on by default for signed-out visitors (also on planning guides); saved admin choices take precedence. Entry and exit prompts are off by default. Without an embedded popup, the link opens the dedicated project-brief page, including without JavaScript. Consented briefs are stored in a separate private platform inbox, not sent to studios or WhatsApp. Admins can delete them. Platform briefs are excluded from local portable recovery snapshots. Optional tracking is suppressed on pages rendering the qualification form. The inspiration library includes editorial images and only published studio moodboards and reference films. Homepage client voices require separate homepage permission and a published studio; no reviews are generated automatically.

## Administrator access, article editing and email settings

There is no public role picker or passwordless sign-in. The historical shared administrator identity is retired; new and restored sessions for it cannot access admin routes. After backing up the installation, update the files and run `php artisan migrate`. Existing private administrators can continue signing in at `/login`. If you relied on the shared administrator, run `php artisan spacekind:create-admin <your-private-email>` **from a trusted server terminal** and enter a new password at the hidden prompts. The command refuses existing addresses rather than promoting a studio account. Never share private administrator credentials. Historical local browser QA requiring an administrator now uses `SPACEKIND_QA_ADMIN_EMAIL` and `SPACEKIND_QA_ADMIN_PASSWORD` for a dedicated disposable QA account.

**No-terminal alternative for the first admin (HTTPS, single-server hosting):** Deploy this revision, then visit `/admin/setup` on your own site. In hosting File Manager, create the empty approval file named by that page under `storage/framework` (not `public` or `storage/app/public`). Submit the email and a new password in the same browser within 30 minutes. The proof is specific to that browser, and the page removes it after success. The installer requires writable framework storage, refuses existing users, and closes once a private administrator exists (even if disabled) or the completion marker exists. This is not an account recovery or password-reset page. Do not remove the completion marker. If deployment caches routes, refresh the deployment's route cache when releasing the update.

**Non-interactive alternative — `SuperAdminSeeder`:** Deploy this revision, then run the following from the Laravel installation directory (the one containing `artisan`). First set `SPACEKIND_ADMIN_EMAIL` to your actual private email and `SPACEKIND_ADMIN_PASSWORD` to a unique 14–72 byte password as temporary **server/CLI environment secrets**. Do not paste a password into the command itself, commit it, or use literal angle brackets. With Laravel config cached, use actual process environment variables, not just a new `.env` entry.

```sh
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force
```

Remove the two secrets from the server environment after success, then sign in at `/login`. No reset or full demo seed is needed. Re-running leaves an existing active private administrator and their password unchanged. It refuses to promote an existing studio user, reactivate disabled users, or provision the retired shared address. If the class cannot be found, verify that `database/seeders/SuperAdminSeeder.php` is on the deployed release and rebuild the Composer autoloader (`composer dump-autoload -o`).

Under **Admin → Settings · Payments & email**, update Razorpay test/live keys or SMTP connection and sender details. Passwords and key secrets are write-only, encrypted with `APP_KEY`, and excluded from local recovery snapshots. Saving configuration neither connects to the SMTP host nor sends messages or processes payments. Credential changes remain blocked on shared installations.

The journal editor supports Markdown formatting buttons and up to 12 FAQ pairs per revision. Published pages show expandable answers and server-rendered `BlogPosting` and (when applicable) `FAQPage` JSON-LD. To schedule a draft, reopen it, choose a future date/time in the displayed time zone, and select **Schedule publication** (platform) or **Request scheduled publication** (studio; approval required). Saving a draft alone never publishes it.

## Platform integrations and studio content

Super admins: **Integrations & email** stores separate Razorpay test/live key pairs (write-only encrypted secrets) and plain-text welcome, enquiry and invoice email templates. Neither checkout nor mail delivery is activated by saving settings. Do not use real credentials in shared accounts. Preserve `APP_KEY` for encrypted records; gateway credentials are excluded from local recovery files and must be reconfigured after such a restore. Use provider-side rotation/revocation when needed.

**Tracking & consent** handles Google Analytics 4 (gtag.js), Meta Pixel and Microsoft Clarity, or a GTM container, using provider IDs rather than pasted HTML. Existing publication, deployment, visitor consent and page restrictions still apply. Configure Clarity masking and validate provider payloads before allowing production delivery.

Studio owners: **More → Works & inspiration** uploads rights-confirmed images and manages captions, visibility, ordering and removal. Inspiration is a separate collection on the approved studio profile, never a portfolio cover. **More → Client testimonials** manages permission-confirmed feedback and attribution; new entries are hidden unless explicitly made public. No testimonials or provider settings are seeded.

Back up the installation first, update the files, then run `php artisan migrate` to add the configuration and testimonial tables. No reset or general seeder is required.

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

The dedicated seeder inserts three missing editorial inspiration records; rerunning it preserves existing entries. It does not create studios, leads, accounts or financial records. The directory, journal and film cards use eligible published records, not invented activity. A site with no published videos will not show a fabricated film on its homepage. Replace the bundled design references with licensed material before commercial use (see [asset notes](docs/assets.md)). Local recovery now writes format 7 and accepts formats 1–6; when restoring an old snapshot into an empty migrated database, restore **before** running the editorial seeder.

**MySQL acceptance:** the 14 worker register, attendance, ledger, statement and recovery tests [passed on an isolated MySQL 8.4 service](https://github.com/mgwebsiteoffc-dotcom/spacekind/actions/runs/44694298591). The [CI workflow](.github/workflows/labour-mysql.yml) repeats the check on pushes. It creates a disposable `spacekind_test` database, checks that it is empty with foreign keys enabled, and runs the PHP regression suite. Never point the test suite at an installed database. This is not a production deployment or a full-platform regression run.

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
| Separate platform billing | Admin issuer/settings, fixed annual software / optional monthly management fees, reviewed GST totals, separate invoices/sequence/manual payments, own-business read-only owner access; optional hosted invoice checkout, no entitlement activation |
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
- Unless explicitly enabled as described below, no checkout or enquiry email is active. No renewal enforcement, WhatsApp/Meta sending, ads/spend transfer, real tags, marketing emails/OTP or automatic follow-up delivery is activated. Each external connection requires separate configuration, consent, testing and authorisation.
- UTM summaries are consent-retained labels/context, not causal ad attribution, visits, cross-device first/last-touch history or verified revenue. No custom enquiry/payment event telemetry is emitted.
- Tracking gates still exclude signed-in/auth/CRM/admin/API/enquiry/private media/financial/share contexts. Custom scripts are deployment-allowlisted HTTPS `.js` URLs, not pasted arbitrary HTML. Offline consent tests are not real vendor SDK/payload certification.
- No Instagram login/API/publishing/scraping/scheduling workflow. Users upload videos on the panel and add a URL; Spacekind displays them in a Reel-style experience.

## Running local preview

Use Arena’s **Spacekind website & workspace** live preview. Public directory/journal/videos remain available. All web logins use the password form. Only local fictional studio accounts use the fixture passwords below.

| Demo role | Email | Fictional demo password |
|---|---|---|
| Aangan owner | `owner@aangan.example` | `Spacekind@2026` |
| Aangan staff | `team@aangan.example` | `Spacekind@2026` |
| Other studio owner | `owner@form-grain.example` | `Spacekind@2026` |

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

### Activate platform checkout and enquiry alerts (non-demo installations)

Deploy the migrations (`php artisan migrate --force`). Under **Admin → Integrations & email**, enter your own credentials; never put secrets in source control. In the hosting environment set the desired flags below, then rebuild cached configuration (`php artisan config:cache`). Both integrations default off. Shared demo records cannot initiate external delivery or collection.

- **Razorpay:** Start with `SPACEKIND_RAZORPAY_MODE=test` and test keys. Under **Platform billing** issue a CRM/software or performance-marketing invoice. Its studio owner opens **Spacekind bills → invoice → Test checkout**, pays on Razorpay, returns and presses **Refresh payment status**. Test payments never reduce balances. After verifying your account, configure live keys and switch to `live`. The server checks the provider reference, currency, full outstanding amount and paid status before recording a live payment once. This is one-time, invoice-based collection, not recurring billing, automatic entitlement or a payment on a homeowner’s project. No webhook is needed: an admin can also refresh the invoice. Reconcile pending links before manual adjustments or key rotation; cancel an unused link at Razorpay or let it expire, then refresh. Resolve a missing provider link with Refresh after one hour. Use the merchant dashboard for refunds.
- **Email:** Save TLS SMTP details, optionally edit the **New project enquiry** template, then enable `SPACEKIND_ENQUIRY_EMAIL=true`. Submit a test brief to your own published non-demo studio and confirm its active owner receives the reference. No homeowner contact data or file is attached. Only new public studio enquiries trigger alerts; there is no automatic welcome, invoice, campaign email or retry. A mail failure does not discard the enquiry; check the studio inbox and Laravel log. Your host must allow outbound SMTP and HTTPS to `api.razorpay.com`.
