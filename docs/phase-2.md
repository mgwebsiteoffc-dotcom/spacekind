# Phase 2 — quotations, invoices, manual payments and receipts

Release: `phase-2-finance-pilot.1`. **Incremental patch against the confirmed installed Phase 1B build.** Update guide: [`update-phase-2.md`](update-phase-2.md). No fresh setup, data reset or financial seeding; existing `.env`, `APP_KEY`, records and uploads stay in place.

## Working development workflows

### Studio billing setup / reusable catalogue

Owner-only **Workspace → Invoices & receipts**, also under **More** on phones. Each studio has separate legal identity, full address, billing state, optional syntax/state-checked GSTIN, explicit non-GST / tax-exclusive / tax-inclusive basis, distinct 1–5 character quotation/invoice/receipt prefixes, terms and payment instructions. Review confirmation is required. An optional JPG logo is stored privately; each issued document retains its exact path and branding snapshot. Changing setup affects future reviews, not previously issued records. Prefixes cannot change after that document series has issued a number.

Catalogue material, labour and service items have a name, unit, INR rate, optional HSN/SAC and supported GST rate. Adding an item copies its values into a line; later catalogue changes/archiving never reprice old documents. Labour *lines in a quotation* do not implement attendance or worker wages (Phase 3).

### Quotations

- Same-studio client and optional matching project; document/valid-until dates, reference/revision context, full customer billing details and explicit place of supply.
- Up to 50 material/labour/service lines: description, unit, positive quantity (3 decimals), rate/absolute line discount (2 decimals), optional HSN/SAC and GST. No document-wide percentage discount/milestone schedule engine.
- Editable **draft** with UUID repeat-submit guard and optimistic version check against stale browser tabs.
- **Issue** assigns a permanent number and freezes identity, items, pricing, tax, terms and branding. It does not send the document.
- Explicit studio-recorded **sent** context; **accepted / rejected** decision and note; **expired** derived for undecided past-validity records. `issued` is intentionally distinguishable from `sent`. A recorded decision is not a digital signature or independently verified delivery/acceptance.
- Accepted quotation → **one draft invoice**, even after repeated conversion taps. Owner reviews/edits/issues separately. Once referenced, the quotation decision is locked; original quoted values remain retained.
- Duplicate/revise makes another draft and retains the original reference/audit history; it never silently replaces an issued version. A linked invoice must be voided before voiding its source quote.

### Customer invoices

Studio-only invoice register, separate from platform billing. Draft → issued; **unpaid / part-paid / paid / overdue** are derived from active allocations, outstanding and due date. A part-paid overdue invoice displays overdue while still showing its exact paid/balance amounts. Drafts/voids have no collectible balance.

Numbers are tenant-safe and sequential by document type + Indian financial year (1 April–31 March). Example: `INV/2627/00001`. Prefix/year/sequence fits at most 16 characters. No statutory gap-free guarantee: voided numbers remain consumed; back-dated issues continue that financial year’s sequence, not chronological date order. The 99,999-per-type/FY pilot limit fails safely. Do not reset/reuse sequences.

Full business/customer addresses, optional GSTINs, explicit place of supply, HSN/SAC and CGST/SGST or IGST are retained. GSTIN **format/state** is checked, not checksum/registration status, tax classification, reverse charge, export/SEZ place-of-supply law or legal entitlement. The supported rates are a limited configured list, not a current statutory tax recommendation. Obtain accountant approval before use.

Issued models reject detail edits and hard deletion. UI corrections use reasoned void + copied/new draft and payment reversals; the original number/snapshot is retained. This is **not** a GST credit/debit-note, IRN/e-invoice, e-way-bill, GST-return or certified accounting system. No silent issue/delete/reissue or automatic statutory correction occurs.

Register search: number/billing name, client, project, date range, financial year and status. Owner CSV uses the same filters, limits each export to 5,000 rows and prefixes spreadsheet-formula-like strings. Narrow date filters for larger exports. Summary cards are all-studio financial totals, **not** totals of the current filtered page. Dated in-app reminder/note is visible in the register/document; no job, email or message is scheduled.

Project pages show the latest 30 financial documents to the owner; staff see no financial amounts/links. Invoice allocation history links to recorded payments/receipts, including general client advances allocated later.

### Manual payments, advances and receipts

Owner records an amount/date/method (`cash`, `upi`, `bank`, `cheque`, `other`), client, optional project, optional invoice, reference (required unless cash) and receipt-visible note. This records only the studio’s assertion of receipt: **not a payment collection, bank verification or gateway success**. Won in CRM remains independent of payment.

Each recorded payment creates exactly **one numbered receipt** atomically. Initial invoice allocations are partial/full up to outstanding; selecting an invoice infers its project if omitted. An unallocated identified client/project advance creates its own receipt and can later be allocated without new money or another receipt. Project-specific advances cannot be allocated to another project; general client advances can be allocated to that same client’s projects. A receipt retains its original acknowledgement/project and initial allocation context.

UUID retry protection is per studio. Exact retried payment payloads return the same receipt; reusing a key for a changed amount/client/project/invoice/date/method/reference/note is rejected. Allocation retries are likewise unique. This prevents duplicate taps/retries of **that submission**; two independently created forms with different UUIDs are separate entries and still need human reference/reconciliation review. References are not claimed to be bank-unique.

Auditable **reversal** retains the payment, voids the receipt, revokes its shares and reverses every linked allocation, restoring invoice balances. It is not a bank refund. Correct partial allocation mistakes by reversing/re-recording the original payment under a new submission; there is no standalone allocation edit/unallocation UI. Payment/allocation details are model-immutable; no hard-delete workflow exists.

### Private PDFs and controlled sharing

- A4 branded PDF, frozen values for issued records, draft/void/demo markings, optional genuine JPG logo, INR/GST totals, terms and payment instructions. Fifty-line/long-terms output was checked as a six-page PDF with all line sentinels present.
- PDFs are generated on demand, not stored in a public directory. Dompdf remote loads, PHP and JavaScript are disabled; file chroot is a private runtime directory. Text is escaped; uploaded logos are not arbitrary URLs/HTML. Apply server request/memory limits and monitor PDF load before high-volume use.
- Expiring **1 / 7 / 30 day bearer links**: 64-character opaque token, only SHA-256 retained in the database, immediate revocation/expiry/void/suspension denial, no-store/no-referrer/noindex, no tracking scripts. Anyone holding the forwarded token has document access. Scrub token URLs from hosting/access/error/analytics logs where possible.
- The original URL is shown once after creation; it cannot be recovered from a stored hash. Device share/copy or PDF download is deliberate. No automatic WhatsApp attachment, delivery/read receipt, public listing, digital signature, protected-password portal or email is implied. Downloaded copies cannot be recalled or marked void retroactively.

### Separate Spacekind platform billing

Super-admin **Platform billing** has its own issuer/tax setup, sequence, invoice and manual payment tables. Owner sees only issued/numbered own-business platform invoices; drafts/other studios’ bills are denied.

- Annual software base fee: **₹4,999/year**, one annual package covering **all five standard modules, phased**, not per seat and not separately charged standard modules.
- Optional campaign-management base fee: **₹2,999/month**, 1–12 months; **Meta media spend is separately business-paid**, neither included nor deducted.
- Tax-inclusive/exclusive/non-GST fee treatment is configured explicitly. The admin sees the draft’s calculated total and confirms it before issue; a settings change invalidates that review. HSN/SAC, issuer/GST and service period still need professional/commercial review.
- Server-controlled fees cannot be replaced with submitted browser rates. Issued platform snapshots are immutable, manual payment retries/reversals are recorded, balances are derived and void retains history. No standalone platform payment-receipt product is claimed.
- No checkout, charge, bank verification, service entitlement, subscription activation/renewal, arrears enforcement or Meta budget transfer occurs. Existing subscriptions/pilot status are unchanged.

## Implementation / integrity

Laravel + actual MySQL, private tenant scopes before bindings, owner middleware, fresh suspension reads and tenant/client/project compound foreign keys. Business-row locking serialises studio numbering/payment/allocation writes; sequences and retry keys also have database uniques. Platform issuing/payment locks are separate. All mutation writes are transactionally grouped where necessary. Integer-paise maths avoids binary floating-point for calculations; display formatting is not used for calculations.

Exclusive intra-state CGST/SGST components round separately per line. Inter-state GST rounds as IGST. Inclusive GST is extracted after line discount; an odd tax paise is assigned to SGST. A tiny taxable value can therefore produce a one-paise difference between inclusive/exclusive or intra/inter-state examples. This explicit policy is tested, not a claim that every accountant/jurisdiction requires it. Rounding/classification must be approved before commercial use.

Curated read-only owner endpoint: `GET /api/v1/invoices`, INR totals/paid/balance as integer minor units. Shared `FinancialWorkflow` / `DocumentCalculator` can support a future client, but full financial mobile write/upload/export APIs are **not** delivered here.

Local preview recovery is now **format 3 / 26 application tables**, with format-1 and format-2 compatibility and refusal to overwrite occupied tables. Finance round-trip includes source quotation self-reference, receipt, payment, allocation and token hash with foreign keys enabled. It is a local-demo recovery helper, not encrypted/off-site MySQL/media backup. Never deploy a private snapshot or use it instead of real backups.

## Executed evidence and limits

- **83 PHP tests / 750 assertions**, complete foundation/content/recovery + 25 new financial tests and two added recovery regressions, against isolated actual MySQL. No production reset/test database reuse.
- **26 complete financial browser assertions**, including uploaded JPG, catalogue/dynamic lines, quotation issue/sent/accept/convert, private PDF, retry, partial/advance/reversal, revoked access and separate platform billing.
- **68 financial route/width checks** across 1440, 768, 390 and 320 px. No recorded page overflow, runtime JS errors or external network requests.
- **12 multi-process / PDF checks**: six concurrent invoice issues, six exact simultaneous payment submissions returning one payment, four allocation retries, exact derived balances/receipt counts and 50 lines + long terms across six PDF pages. This is bounded local concurrency, not production throughput/chaos testing.
- PHP lint, style, Blade compilation, Composer/JS/Python syntax, patch manifest/CRC/secrets/path checks and a Phase 1B-to-Phase 2 rehearsal are reported in the handoff QA artifacts.

Reports: `qa/phpunit-phase2.txt`, `qa/phase2-browser-results.json`, `qa/phase2-concurrency-results.json`, `qa/phase2-package-verification.json` (if copied into the handoff). Named browser fixtures/test logo were removed afterwards. Historical Phase 1B browser/consent reports are separate evidence, not new live provider validation.

No production tax/legal/security certification, target-host/embedded-cookie proof, high-volume PDF/DB benchmark, automatic backups, complete accessibility/device matrix, IRNs/filing/statutory notes, online payments, WhatsApp/Meta integrations, Instagram publishing or labour-management implementation is claimed. See launch gates. **Development approval does not activate a live service.**

The existing public/workspace/admin route smoke suite was also rerun after Phase 2: **144 checks passed** with no page overflow, runtime JS errors or third-party requests (`qa/phase2-foundation-responsive.json`). One initial oversubscribed parallel QA attempt timed out in FFprobe/browser screenshots/login; focused rechecks and the final suites ran sequentially without weakening validation or raising application timeouts.
