# Interior-business SaaS — Consolidated Master Plan

**Single current scope reference · Development approved · First foundation / small-CRM build implemented**  
**Confirmed brand:** Spacekind. The user explicitly retained the name when authorising development.  
**Design reference:** `interior-platform-concept.html`. The existing visual concept is a starting point, not a finished application.

This consolidates the original business idea, GTM direction, HTML concept, Laravel/MySQL requirements and every subsequent addition. It is the current scope reference; earlier experimental pricing and narrower positioning are not the approved commercial model. The user has approved starting development under the Spacekind brand. Live transactions and external-provider activation still require separate configuration, testing and authorisation.

**Current status:** the first Laravel/MySQL development application now exists at `spacekind/`: public discovery and studio profiles, two-step qualified enquiries, tenant-scoped owner/staff CRM, notes/assignments/in-app follow-ups, clients/lightweight projects, gallery management, super-admin approval/access controls and a Sanctum API foundation. It preserves the ivory/green/editorial HTML aesthetic. It is a fictional-data development preview, not a production launch. Financial/labour modules, Phase 1B content/measurement tools and live external integrations remain upcoming. See `spacekind/README.md` for tested implementation details and setup.

## Contents

1. Positioning, pricing and confirmed scope
2. Product vision, customer and launch focus
3. Four connected product areas
4. Roles, navigation and complete user journeys
5. Detailed modules M01–M17
6. Mobile-first UX
7. Laravel/MySQL architecture and future app
8. WhatsApp and attribution boundaries
9. Phased implementation
10. GTM, validation, metrics and commercial sustainability
11. Quality and launch acceptance
12. Scope boundaries
13. Approval defaults and open decisions
14. Existing deliverables and next action


## 1. Confirmed positioning, pricing and scope

### Positioning — use verbatim

> We help interior-design businesses generate qualified enquiries on WhatsApp, follow up consistently, and track which ads produce customers.

The platform also gives interior businesses a public profile and gallery, with category/location discovery and direct organic marketplace enquiries. Organic discovery complements paid advertising; it does not guarantee traffic, rankings, enquiries or sales.

### Confirmed commercial model

| Offering | Price | Scope |
|---|---:|---|
| Annual software subscription | **₹4,999/year** | All five standard modules: CRM, Instant Quotation, Invoice Software, Receipt Management and Labour Management. The planned business tools also include the profile/gallery, simple Reel-style uploads, blog tools, qualified enquiries and UTM functionality. Delivered in phases, not separately charged as standard-module add-ons. |
| Optional ad-management service | **₹2,999/month** | Campaign-management service. Not advertising credit and not an all-inclusive media budget. |
| Advertising budget | **Separate** | The business pays Meta from its own ad account. Never silently deducted from the management fee. |

**Recommended v1 packaging, subject to plan approval:** include one approved business profile and gallery with the annual subscription, without a separate listing fee or per-lead commission. Do not add a free-listing tier, paid rankings or lead resale to the first release unless separately approved.

Whether the stated subscription/service amounts include GST must be agreed before production checkout and invoicing. Do not silently add taxes or invent setup fees. Optional messaging-provider or AI usage charges must be disclosed before enabling those services; basic CRM and document functionality must remain usable without them.

**Availability rule:** the small CRM is built first. During a private pilot, unfinished modules are clearly marked as upcoming. A landing page must not present an unfinished module or integration as currently available.

## 2. Product vision, customer and launch focus

### What we are building

An India-first, interior-focused business platform combining:

- **Discovery:** public studio listings, useful project galleries, short videos and articles.
- **Enquiries:** paid-ad and organic entry points with a qualified contact form and a WhatsApp path.
- **Conversion:** a simple CRM, ownership, follow-ups and source-to-customer records.
- **Operations:** clients/projects, quotations, invoices, payments/receipts and labour records.
- **Platform operations:** one super-admin console controlling businesses, billing, public content, tracking and the ad-management service.

The complete journey is:

**Paid ads / organic discovery / shared campaign link → studio profile, gallery, article or video → qualified enquiry → CRM follow-up → client/project → quotation → invoice → payment/receipt → work and labour tracking → handover.**

This retains both demand generation and conversion in the product direction. It does not redefine the product as follow-up-only, and it does not require every future integration to ship before the first CRM pilot.

### Primary customer

Independent interior-design studios, turnkey/residential fit-out businesses, renovation specialists and modular kitchen/wardrobe providers. The buyer is usually an owner or sales/operations manager; daily users may be the owner, sales staff, an accounts user or a site supervisor.

Start in one reachable metro and a small set of service categories. **Delhi-NCR is a candidate, not a confirmed geographic restriction.** Prefer customers willing to show their real enquiry and project workflow, use the system weekly and provide practical feedback.

The software can serve firms using referrals and organic enquiries as well as paid advertising. The ₹2,999 management service is for firms with a separate advertising budget; buying that service is optional.

### Differentiation to test

- Interior-specific qualification and a journey from first enquiry to final handover.
- Direct studio enquiries, not leads sold to multiple competing firms.
- Useful portfolios that a business can share through its own channels before the marketplace has traffic.
- Mobile-first, uncomplicated operational tools at the agreed annual price.
- One connected record across lead source, project, documents and recorded payments.

Do not assume the feature bundle itself is unique or a durable moat. Customer adoption, reliable workflows, service quality and acquisition economics still need validation.

### Two acquisition engines

**Paid:** business-owned Meta campaigns, initially managed externally by an operator under the optional service; later permissioned reporting, AI-assisted creative tools and native launch capabilities where approved.

**Organic/direct:** marketplace browsing, eligible search-engine discovery, public portfolios/articles, Reel-style videos, referrals and shared UTM links. Organic demand requires useful content and distribution; a listing alone does not guarantee visitors or leads.

### Interpretation of Reels — settled

This is a simple uploaded-video feature: **video file + caption/cover + associated URL → Reel-style playback on our platform**. It is not Instagram account management, synchronisation, automatic posting or a publishing-API requirement.

## 3. Four connected product areas

| Area | Purpose | Proposed routes |
|---|---|---|
| Public marketplace | Help homeowners discover studios and view project portfolios. | `/`, `/studios`, `/studios/{slug}` |
| Business conversion website | Explain the offer, demonstrate the workflow and convert studio owners. | `/for-businesses`, `/pricing`, `/register` |
| Business workspace | Run enquiries, clients, projects and the five standard modules. | `/app/...` |
| Super-admin console | Operate the platform, businesses, subscriptions, directory and ad service. | `/admin/...` |

A versioned API lives under `/api/v1`. These can initially run in one Laravel application and deployment; separate microservices are unnecessary.

## 4. Roles, navigation and complete user journeys

### Roles and ownership

| Role | Responsibilities | Restrictions |
|---|---|---|
| Homeowner/visitor | Browse public profiles/content and submit an enquiry. | No access to private CRM, financial records or drafts. A homeowner account is not required initially. |
| Business owner | Manage their business profile, media, articles, leads, projects, documents, workers, team and subscription. | Their own business only; no global scripts, platform pricing or other tenants' private data. |
| Business staff | Perform tasks granted by the owner. | Permission-scoped; not automatically entitled to financial/labour/content access. |
| Super admin | Manage platform operations, approved content, businesses, plans, subscriptions, tracking and ad-service work. | Strong authentication and audit logs; private support access is deliberate, permissioned and recorded. |

Start with owner/staff permissions instead of a large role hierarchy. Sales, accounts, site-supervisor and content presets can be introduced as the respective modules become usable. An internal ad operator can receive restricted permissions when needed, rather than receiving unrestricted super-admin access.

### Public website inventory

- Marketplace home/discovery page.
- Studio search with city, service and indicative-budget filters.
- Individual studio profile.
- Project/gallery views and uploaded Reel-style video views.
- Qualified enquiry form and submission confirmation/WhatsApp continuation.
- Platform blog and article pages.
- Business article pages on the appropriate studio profile.
- Business conversion landing page, pricing, registration/login and demo links.
- Contact, privacy, terms and subscription/service policy pages before commercial launch.

Public URLs follow the route families in Section 3; only published, authorised content is exposed. City/category pages are created for genuine useful content, not empty SEO permutations.

### Business workspace inventory

**Today** — new enquiries, overdue/upcoming follow-ups and quick actions.  
**Leads** — list/pipeline, qualification details, source, ownership and activity.  
**Projects** — client/project records through handover, with linked documents and labour.  
**More** — Clients, Quotations, Invoices, Payments & Receipts, Labour, Gallery & Reels, Blog, Campaign Links, Ad Service, Team, Billing and Settings, shown as the modules become available.

Financial document lists and project-specific records are two views of the same underlying records, not separate copies requiring manual reconciliation.

### Journey A — homeowner to business enquiry

1. A visitor arrives through an ad, search, directory, article, referral or tagged link.
2. They inspect the selected studio, projects and media.
3. The main enquiry action opens the short qualification form.
4. The server validates inputs, destination ownership, source context and contact permission.
5. The enquiry is saved to the selected business only, with the available campaign/project/reel context.
6. The visitor sees confirmation and can choose a user-initiated WhatsApp continuation.
7. The owner/staff assigns and follows up in the CRM.

An optional direct WhatsApp action can be recorded as a click, but must not create a fictional confirmed chat/customer. If someone bypasses the form, full qualification may not be available until entered manually or received through a later official messaging integration.

### Journey B — business onboarding

1. Register owner and verify account.
2. Create business identity, address, contact and optional GST details.
3. Activate the annual plan through the agreed pilot/payment flow.
4. Add services, service areas, indicative project range, photos and short videos.
5. Submit public content for the applicable approval workflow.
6. Set up authorised team members and start the small CRM.
7. Use quotation, invoice, receipt and labour modules as released.
8. Optionally request the monthly ad-management service, with a separately agreed media budget.

### Journey C — enquiry through final handover

**Sales:** New → Contacted → Qualified → Site Visit → Quote Sent → Won/Lost.  
**Project:** Planned → Design → Execution → Handover → Closed.

A won lead converts to a client/project while preserving history. A quote is an estimate/offer, an invoice is an issued billing document, a recorded payment changes balances, and a receipt acknowledges that recorded payment. These events are not interchangeable. Project notes/stages provide a lightweight handover journey, not a full ERP.

### Journey D — business content and links

- Gallery: upload → label/caption/group → choose cover/order → preview → approve/publish as applicable.
- Reel-style video: upload file + associated URL → preview → publish on platform → visitor plays or opens link.
- Blog: draft → author/business attribution → review → approved publish/schedule.
- UTM: select owned public destination → campaign parameters → copy/share → preserve available context in an enquiry → report recorded outcomes.

## 5. Module-wise scope

### M01 — Accounts, businesses and access

**Functions**
- Owner registration, login, password reset and email verification.
- One business workspace with basic business information, contact details, logo, address and optional GST details.
- Business-owner and staff memberships.
- Permission checks for assigned leads, projects, financial documents and labour records.
- Clear subscription/access status.

**Initial roles:** Super Admin, Business Owner and Staff. Staff receive simple permission presets; more granular sales/accounts/site-supervisor presets can follow as those modules ship.

**Done when:** users can access their own workspace, cannot retrieve another business's private data, and owners can invite/manage authorised staff.

### M02 — Plans, subscriptions and platform billing

**Functions**
- Annual ₹4,999 software plan and separate optional ₹2,999/month management service.
- Subscription states: pending, active, expiring, expired and suspended.
- Renewal history, billing records and admin-controlled activation.
- Separate records for platform subscription payments versus a studio's customer payments.
- Platform invoice register for software subscriptions and ad-management fees: invoice number, business, service period, issue/due dates, taxes where applicable, total, payments, balance and status. Businesses can view/download their own platform invoices; only authorised platform staff manage them.
- Server-controlled prices and entitlements, not values trusted from the browser.

**Recommended first implementation:** manual activation against a recorded payment reference for the private pilot. Add an approved Indian payment-gateway checkout in test mode before enabling live online payments. Gateway credentials, settlement account, tax treatment and refund/renewal terms require confirmation first.

**Expiry recommendation:** a configurable grace period, then read-only access to existing records and permitted exports. Never silently delete financial records because a subscription expires.

**Done when:** activation, renewal and expiry behave predictably, payment processing is idempotent, and enabling/disabling an entitlement does not expose other tenants' data.

### M03 — Public marketplace, categories and galleries

**Functions**
- Search/filter studios by city, service category and indicative project range.
- Business profiles: introduction, location, service areas, services, contact action and gallery.
- Project-based gallery entries with title, scope, images, short videos and optional approved budget range.
- Business-side Gallery & Reels manager: add/edit/remove own media, organise by project/category/room/style, set captions/alt text, choose covers, reorder and feature selected items.
- Separate draft/public visibility, image previews and replace/delete controls; public changes follow the listing moderation policy. Super admin can review/unpublish with a reason.
- Businesses cannot manage another studio's media. Private enquiry attachments are never automatically added to a public gallery.
- Labels distinguishing completed projects, renders and AI concepts.
- Owner submits a profile; admin approves, rejects with a reason or unpublishes it.
- Qualified enquiry forms and direct WhatsApp actions, with explicit source tracking; see M05 for qualification fields.
- Links to the business's published articles without exposing drafts or private CRM data.
- Server-rendered SEO/AEO foundations, with public-content requirements defined in M14.

**Suggested initial categories:** full-home interiors; kitchens and wardrobes; renovation; commercial interiors. Style tags are separate from service categories.

**Direct-enquiry rule:** an enquiry belongs to the selected studio. Do not resell or distribute it to competing studios. A dedicated campaign version of the profile should avoid competitor recommendations.

**Important:** a WhatsApp-button click is not proof that a message was sent. A saved enquiry form, outgoing click and confirmed inbound message are different events.

**Done when:** approved profiles are searchable, unapproved/private assets stay hidden, and a submitted profile enquiry appears only in the correct business workspace.

### M04 — Business conversion landing page

Use the positioning in Section 1 exactly, with a separate explanation of the organic marketplace benefit.

**Page sections**
1. Hero: exact positioning, supporting explanation, primary “Get started — ₹4,999/year” CTA and secondary “See the demo” CTA.
2. Two discovery channels: paid ads that open a WhatsApp conversation, and organic discovery through the marketplace/profile/gallery.
3. Connected journey: discover/ad → enquiry → follow-up → quotation → invoice/payment/receipt → project handover.
4. Five standard modules, with honest release availability.
5. Mobile-first workspace screenshots and a sample workflow.
6. Annual software pricing versus optional monthly ad management, with ad spend clearly separate.
7. How onboarding works: create account, set up business, activate subscription, submit profile and add team members.
8. FAQs: direct leads, software/service distinction, WhatsApp limits, taxes, ownership of ad accounts and support.
9. Final CTA and transparent contact/legal information.
10. Useful guides/articles with honest authorship and answer-led FAQs. Content visibility and structured data follow M13–M14.

**Suggested marketplace supporting copy:**

> Showcase your work in an interior-focused marketplace. Let homeowners explore your profile and gallery, then enquire directly with your studio—alongside your paid-ad workflow.

No fake testimonials, fabricated customer counts, guaranteed rankings, guaranteed organic leads or “verified quality” badges without a defined verification process.

**Done when:** the offer, pricing and calls to action are clear on a phone, and registration/demo flows work without misleading availability claims.

### M05 — Small CRM: first operational module

**Functions**
- Today dashboard: overdue and upcoming follow-ups, new enquiries and quick actions.
- Create enquiries manually and capture enquiries from platform forms.
- Lead fields: name, phone, city/locality, property type/configuration, approximate floor area and unit, project scope/plan, budget range, readiness/possession date, source, owner, status and notes.
- Optional private floor-plan/reference attachment; this is not a public gallery upload.
- Optional email; collect only information needed for the business workflow.
- Sales stages: New → Contacted → Qualified → Site Visit → Quote Sent → Won/Lost. A “Quote Sent” stage can be manually recorded before the quotation module exists.
- Search, filters, list view and a lightweight pipeline view.
- Follow-up date/time, task status, completion and activity history.
- Click-to-call and user-initiated WhatsApp sharing.
- Convert a won lead into a client/project while preserving history.
- Source summaries and recorded customer outcomes, with unknown attribution shown honestly.

#### Qualified enquiry form — mobile-first, two short steps

**Step 1: project fit**
- Location: city, locality/area and optional PIN code.
- Property: apartment, villa/independent house, office/retail or another supported type.
- Configuration where relevant: 1/2/3/4+ BHK; avoid residential questions for commercial work.
- Approximate floor area in sq ft, with a “not sure” option. “Area” covers both location and property size.
- Project plan/scope: full-home interiors, kitchen, wardrobes, renovation, commercial fit-out or consultation.
- Indicative budget range, including “need guidance”; use business-approved ranges.
- Possession/readiness and intended start window.
- Optional brief and optional floor-plan/reference PDF/image.

**Step 2: contact and permission**
- Name and validated phone number; email optional.
- Preferred contact method.
- Clearly named destination studio and permission to contact the person about this project, including WhatsApp where selected.
- Separate, optional marketing opt-in; do not bundle ongoing promotions into project-contact permission.

Use progressive questions and keep nonessential inputs optional. Store answers in the destination business's CRM and API response with appropriate staff permissions. Show rule-based service-area/budget/scope/readiness fit flags. An incomplete budget is “unknown,” not automatically disqualified; a fit flag is not proof of purchase intent or a guaranteed qualified sale.

Validate attachments by type, size and access policy; keep floor plans/private addresses out of public galleries, analytics payloads and search indexes. Apply rate limits, anti-spam measures and duplicate-submission protection. Routing comes from the selected listing/server context, not a client-supplied arbitrary tenant ID.

**Not in small CRM v1:** full WhatsApp chat synchronisation, AI autonomous replies, automatic WhatsApp follow-ups, native Meta campaign creation, advanced marketing automation or a full contact-centre inbox.

**Follow-up meaning in the first release:** in-app reminders/tasks; optional email notifications once configured. Automated WhatsApp follow-ups require a later official integration and consent/template handling.

**Done when:** an owner can capture an enquiry, assign it, schedule/complete a follow-up, update its status and record a customer outcome from a phone.

### M06 — Clients and lightweight projects

**Functions**
- Client records connected to the original enquiry and communications history.
- Project name, site address, assigned staff, budget and key dates.
- Simple project stages: Planned → Design → Execution → Handover → Closed.
- Timeline/notes and a next-action field.
- Links to quotations, invoices, payments, receipts and labour entries as those modules become available.

This extends the journey to final handover without adding procurement, inventory, CAD, complex scheduling or a full construction ERP.

**Done when:** a won lead becomes a client/project without duplicate data entry, and a project can be tracked to handover.

### M07 — Instant Quotation

**Functions**
- A reusable material/service catalogue and labour line items.
- Quantity, unit, rate, discount, applicable tax and totals.
- Project/client selection, validity date, notes, terms and payment milestones as text.
- One polished branded PDF template using business logo/details.
- Draft, sent, accepted, rejected and expired states; preserve the sent version.
- Duplicate/revise a quotation without overwriting the previously issued version.
- Download PDF and share through WhatsApp using an appropriate document link or supported device share flow.
- Create a draft invoice from an accepted quotation, with owner review.

**WhatsApp boundary:** a normal WhatsApp link cannot silently attach and send a PDF. Initially provide a secure share link and/or download/device sharing. Server-side document messages require the official API.

**Done when:** a studio can prepare a branded material-and-labour quote, verify the totals, generate a PDF and share it without exposing a predictable public document URL.

### M08 — Invoice Software

**Functions**
- Branded invoice numbering/financial-year configuration.
- Business/client addresses, optional GSTIN, place of supply, HSN/SAC where applicable and tax breakdowns.
- CGST/SGST or IGST as applicable, with explicit business inputs and server-side validation.
- Draft, issued, partially paid, paid, overdue and void/correction workflows.
- Convert a quote to an invoice without treating the quote as a payment.
- Due dates, outstanding balances and reminder tasks.
- Download/share PDFs and connect invoices to client/project records.
- Searchable invoice register: invoice number/date, client, project, taxable amount, tax, total, due date, paid amount, balance and status.
- Filters for date/financial year, client/project and paid/unpaid/overdue status; permission-controlled CSV export.
- Each invoice opens its line items, issued PDF/version, linked payments, receipts, reminders and correction audit trail. Preserve issued document details instead of changing historical invoices when branding/settings change.

Do not charge GST for a business that is not entitled to do so. Verify calculation/rounding and document requirements with an accountant before production use. “GST-ready” does not mean GST-return filing, e-invoice IRN generation, e-way bills or complete statutory accounting; those are outside this first scope.

Issued financial records must not be quietly overwritten or hard-deleted. Design an auditable correction/void process and confirm any credit-note requirements before production billing.

**Done when:** invoices calculate correctly, partial payments reconcile, numbering is tenant-safe and issued history remains traceable.

### M09 — Receipt Management and customer payments

**Functions**
- Record a payment with date, amount, method, reference and client/project; link to an invoice where applicable.
- Methods initially include cash, UPI and bank transfer as recorded by an authorised user.
- Generate a numbered branded receipt/PDF from the recorded payment.
- Update invoice paid/outstanding amounts from the payment record, not by separately editing duplicate totals.
- Support partial payments, appropriately identified advances and auditable corrections.
- Payment history and receipts visible on the client/project timeline.

**Important:** manual payment entry confirms what the business records. It does not mean the platform has checked the bank or received the money. Automatic “receipt when payment arrives” requires a verified payment event/webhook integration later.

**Done when:** recording one payment creates one receipt, updates the correct balance and cannot be accidentally duplicated by repeated taps or retries.

### M10 — Labour Management

**Functions**
- Worker records and effective-dated daily wage rates.
- Site/project allocation and mobile attendance: present, half-day, absent.
- Bulk attendance entry with per-worker corrections.
- Work notes, advances and recorded wage payments.
- Earned wages, paid/advance amounts and remaining balance by worker/project/date range.
- Simple export/statement and an edit audit trail.

Prevent accidental double-counting when a worker is allocated across sites. Define allocation fractions clearly. Wage-rate changes must not silently rewrite historical earnings.

**Not initially:** biometric/GPS attendance, payroll compliance, PF/ESI/TDS calculation, automated bank payouts or worker-facing native apps.

**Done when:** a site supervisor/owner can mark a day's attendance from a phone and obtain a reproducible wage balance.

### M11 — Ad-management service operations

The ₹2,999/month item is a managed service, not a claim that native ad buying is already built.

**First implementation**
- Business requests the service and supplies goals, service area, indicative budget and authorised portfolio assets.
- Super admin assigns the request to an operator.
- States: Requested → Awaiting Payment/Assets → Setup → Awaiting Business Approval → Running → Paused/Completed.
- Track fee status and actual advertising budget separately.
- Record campaign/ad identifiers and performance data manually during the initial service phase.
- Approval history for creative/campaign changes.
- Campaigns operate in the customer's ad account; do not pool client funds or spend without explicit authorisation.

**Later integration**
- Official Meta ad-account connections and permissioned reporting.
- Native campaign creation only after access, approvals, budget controls and error handling are proven.
- AI-assisted image/video production with approved assets, clear usage allowances and human review.
- Qualified/outcome event reporting where supported and lawful, without promising perfect attribution or improved results.

Campaign counts, included creative work, reporting frequency, service capacity and management-service terms must be set before selling the service. Do not promise unlimited creative production at this fee.

### M12 — Super-admin console

**Platform overview**
- Businesses, active/expiring subscriptions, pending listings, platform payments and ad-service requests.
- Basic operational health and failed-job visibility.

**Business and user management**
- Search/view businesses, owners and staff memberships.
- Approve/suspend/reactivate with a recorded reason.
- Manage entitlements and subscription corrections with audit history.
- No irreversible one-click deletion of a business's financial records.

**Marketplace management**
- Categories, cities, service areas and controlled style tags.
- Review profiles, gallery images and public descriptions.
- Approve/reject/unpublish, with owner notifications.
- Separate completed-work claims from renders/AI concepts.

**Commercial operations**
- Configure plan prices for future purchases without silently rewriting existing agreements.
- Record/reconcile subscription/service payments and renewals.
- Manage ad-service onboarding, assignments, approvals and status.
- Manage landing-page content through controlled fields rather than building a large CMS.
- Manage platform blogs and moderate business articles, including author/byline settings, through M13.
- Control public SEO/AEO settings and tracking/script publication through M14–M15.
- Keep platform subscription/service invoice records separate from tenant customer invoice registers.

**Security and support**
- Require strong admin authentication and 2FA.
- Platform audit trail for critical actions.
- Audited support access/impersonation with a reason, visible support mode and permissions; no invisible, unlogged access to private workspaces.
- Secure integration configuration; credentials are never exposed in public pages or client JavaScript.
- Backup/restore procedures and retention controls.

Super-admin billing concerns the platform's subscriptions/services. It does not turn the platform into a marketplace escrow or payment-settlement service for interior projects.

### M13 — Blog management: super admin and business workspace

**Super-admin publishing**
- Manage platform posts, categories, tags and author records.
- Draft, preview, schedule, publish, unpublish and archive.
- Title, slug, excerpt, sanitised rich-text content, cover image/alt text and SEO/social metadata.
- Review business submissions, request changes, approve/reject and unpublish with a recorded reason.
- Public platform articles at `/blog` and `/blog/{slug}`.

**Business publishing**
- Owners or staff with content permission manage only their own business's articles.
- Draft, edit, upload authorised images, preview and submit for publication.
- Show published posts on the studio profile and at `/studios/{studio-slug}/articles/{post-slug}`.
- Initial default: admin approval before publication. Edits to published content create reviewable revisions rather than bypassing moderation.
- Scheduled publication requires approval. Drafts stay out of public feeds, sitemaps and APIs.

**Author name or business name**
- Byline options: **“By [Business Name]”** or **“By [Author Name] · [Business Name]”**.
- Business-name attribution is the default. Named authors must be associated with/authorised by that business.
- Keep the actual creating/editing user internally, separate from the public byline; do not expose login email addresses.
- Platform articles use a real named author or accurately identified platform/editorial organisation.
- Separate publisher identity and appropriate Person/Organization authorship in structured data; no invented credentials or verification claims.

**Content rules**
- Sanitise rich text/uploads. No executable scripts, tracking tags or arbitrary iframe embeds inside posts.
- Check ownership of drafts, revisions, images and author selections.
- One canonical URL per article even if featured in another feed. Redirect changed slugs.
- Blog tools are included; writing services, mass AI posting and guaranteed SEO traffic are not implied.

**Done when:** admin can publish platform articles, businesses can submit their own correctly attributed posts, approval/scheduling works, and tenants cannot retrieve one another's private drafts.

### M14 — SEO and AEO foundations

AEO means answer-engine-friendly content and presentation, not a guarantee of AI citations, rankings, rich results or organic enquiries.

**Technical SEO**
- Server-rendered, mobile-first public pages with crawlable navigation, semantic headings and meaningful content.
- Unique editable titles/descriptions, clean slugs and social/Open Graph metadata.
- Canonicals, redirects, useful 404s, XML sitemaps and configurable robots directives.
- Include approved published profiles/articles; exclude drafts, private/financial pages and low-value internal search/filter variants from indexable discovery.
- Authentication protects private content; robots/noindex is not access control.
- Responsive compressed images, useful alt text, image dimensions and appropriately lazy/deferred assets.
- City/category pages only where real listings and useful content exist; no bulk thin or duplicate pages.

**Structured data and entity clarity**
- Appropriate Organization, applicable LocalBusiness, BreadcrumbList and Article/BlogPosting markup reflecting visible content.
- Accurate author/publisher identity, meaningful published/updated dates, contact details and service areas.
- FAQ markup, if used, must match visible content. Never fabricate ratings, reviews, prices, credentials or completed work to populate schema.
- Preserve a single canonical article identity across profile/platform feeds.

**AEO-friendly content**
- Clear question-led headings, concise factual answers, useful summaries and service definitions.
- Explicit scope/budget assumptions and location context instead of keyword repetition.
- Visible authorship and business identity; cite sources for externally sourced claims.
- Human factual review; no fabricated case studies or automatically mass-produced city articles.

**Permissions**
- Admin controls global defaults, redirects, indexation and sitemaps.
- Businesses edit permitted profile/article SEO fields, not global robots rules, scripts or other tenants' metadata.
- Technical tools do not imply ongoing content-writing or agency SEO services.

**Done when:** public pages render useful content and accurate metadata/schema without relying on client-side JavaScript, while drafts and private records stay out of public discovery.

### M15 — Google tags, analytics, Meta Pixel and script management

**Super-admin integrations**
- Configure Google Tag Manager container IDs, Google Analytics 4 measurement IDs, Meta Pixel IDs and other approved integrations.
- Enable/disable, production/test environment controls, public page scopes and preview before publication.
- Prefer typed provider configuration over pasted code. Prevent duplicate installations/events through simultaneous direct tags and GTM.
- Page groups: marketplace, studio profile, business landing page and public articles. Any future tenant-specific tag must be authorised and limited to that studio's own pages.

**Other scripts**
- Super-admin-only header/body/footer snippets with name, purpose, provider/domain, consent category, page scope and status.
- Version history, preview, explicit publish, audit log and immediate disable/rollback.
- Trusted platform administrator with 2FA only—not ordinary staff, business owners or blog authors.
- Custom JavaScript is privileged executable code; HTML sanitisation does not make it safe. Review it and apply an appropriate Content Security Policy.

**Privacy and placement**
- Do not run advertising/custom tags on authenticated business/admin workspaces, private enquiries, invoices, receipts, private PDFs or tokenised document-share pages.
- Gate nonessential analytics/marketing tags behind relevant consent with reject/withdraw controls. Rejecting tracking must not prevent an enquiry.
- No names, phones, emails, addresses, floor plans, free-text briefs, budgets or financial-document content in GA4/GTM/Meta payloads by default.
- Disable unintended automatic form-value/advanced-matching collection unless separately reviewed, permitted and deliberately configured. Inspect actual network/event payloads.
- Keep private provider secrets server-side; public measurement IDs are not private API credentials.

**Events**
- `view_studio`, `view_project`, platform `reel_play`, `reel_link_click`, `enquiry_started`, server-confirmed `enquiry_submitted`, `whatsapp_click`, `business_signup` and verified platform `subscription_success`, where appropriate and consented. Platform reel events are not Instagram analytics.
- A click is not an enquiry; an enquiry is not a confirmed conversation/customer. A browser thank-you page is not proof of payment.
- Deduplicate events; later browser/server conversions require deliberate pairing and permissioned data handling.
- Approved non-sensitive context only; never export the full CRM lead object to an analytics data layer.

This covers configuration and instrumentation, not a full embedded GA4/Meta reporting product. Deeper reporting remains in the integration phase.

**Done when:** authorised admins can preview/publish/disable tags, consent/scopes work, private pages remain untagged, and test events are not duplicated or leaking form/financial data.

### M16 — Simple Reel-style video gallery (not Instagram publishing)

**Confirmed interpretation:** the business uploads a video in its panel, adds an associated URL and displays it in a Reel-style layout on this platform. No Instagram login, connection, publishing API, scheduling or automatic upload is required for this feature.

**Business controls**
- Upload a supported video file and add a short title/caption.
- Add an associated source/destination URL, such as an existing Instagram Reel link. The video is hosted/displayed by this platform; the URL is an outbound link, not a publishing instruction.
- Choose/upload a cover, assign a project/category and arrange the display order.
- Preview, edit, replace, remove and manage draft/public visibility under the gallery moderation policy.
- An authorised media/content staff member can manage only their business's videos.

**Visitor experience**
- Mobile-first vertical Reel-style cards/viewer with next/previous or scroll/swipe navigation, play/pause, mute/unmute and a visible caption.
- Video playback on the platform; a separate “View original” / “Open link” action opens the supplied URL. Use an Instagram-specific label only for a validated Instagram URL.
- A direct “View studio” or “Enquire” action stays associated with the owning studio and can preserve the reel/source context.
- Lazy-load videos, avoid playing multiple videos at once, and do not autoplay with sound. Provide normal controls and accessible text.
- No likes, followers, comments, social-network clone or Instagram account-management features are implied.

**Storage and safety**
- Validate files, MIME/format and configurable file-size/storage limits; use a compatible playback format for the initial version. Publish only authorised portfolio media and label renders/AI concepts appropriately.
- Store uploaded media separately from private CRM attachments; drafts are private and only approved public assets are exposed.
- Validate the associated HTTPS URL and block executable URLs/arbitrary embed code. Do not scrape/download Instagram posts or fetch arbitrary remote URLs server-side.
- Set and disclose video/storage allowances before launch; the annual fee does not imply unlimited video hosting or transcoding.

**Done when:** a business uploads a video and associated URL, previews and publishes it under the gallery policy, and a visitor can play it in the Reel-style viewer or open the supplied link—without any Instagram integration.

### M17 — UTM link builder and CRM attribution

**Business controls**
- Create a campaign link to the business's own approved studio profile, gallery/project, article or enquiry page.
- Fields: destination, `utm_source`, `utm_medium`, `utm_campaign`, optional `utm_content` and `utm_term`.
- Provide reusable organic/paid-social presets, copy-link controls and a saved campaign-link list.
- Keep names/phones/emails and other private lead details out of URLs.

**Example**

`/studios/studio-aangan?utm_source=instagram&utm_medium=organic_social&utm_campaign=noida_interiors&utm_content=reel_01`

This is an example only. A Reel itself is not automatically a clickable link; the business must place the generated URL in a placement that supports links.

**Capture and reporting**
- Preserve permitted UTMs and the studio/project/reel context through the public enquiry journey and into the correct tenant's lead record.
- Retain first and last known campaign touches where available and permitted, with timestamps and an explicit unknown/direct state.
- Show known source, medium, campaign and content on the lead; keep historical attribution when the lead converts to a client/project.
- Campaign summaries can relate tracked visits/clicks, submitted enquiries and manually recorded customer outcomes. Compare like-for-like events; do not present clicks as enquiries.
- A click out to an Instagram/source URL is an outbound click only. It does not provide Instagram view counts, proof of a post, or confirmed sales on the destination service.
- Consent settings, blocked storage, direct WhatsApp journeys and cross-device visits can limit attribution. Do not promise perfect tracking.

**Safety and access**
- Businesses access only their own campaign links and attributed records. Admin has authorised platform oversight.
- Validate destination ownership and URL parameters; any shortened redirect route uses approved destinations, not an unrestricted open redirect.
- Keep the full lead object out of analytics. External event payload rules remain governed by M15.
- Include UTM/source fields in the documented API for future app/reporting reuse.

**Done when:** a generated campaign URL leads to the correct public business page, a test enquiry retains its permitted campaign/reel context in that studio's CRM, and another business cannot access the link's private reporting.

## 6. Mobile-first UX rules

- Continue the visual reference's warm neutral/green direction, simplified for daily work.
- Business app primary navigation: **Today, Leads, Projects, More**. Financial, labour, Blog, Gallery & Reels and Campaign Links tools are accessible through relevant screens and More; do not overcrowd bottom navigation.
- One prominent action per screen: add lead, set follow-up, create quote, record payment or mark attendance.
- Cards and stacked forms instead of desktop-wide tables on phones.
- Progressive disclosure, sensible defaults and short forms.
- Sticky save/action area, clear validation and protection against duplicate submissions.
- Reusable Blade/Livewire components: field, selector, money input, status chip, file picker, bottom sheet, document preview and confirmation dialog.
- No drag-only interactions; every pipeline stage change also works through a button/select.
- Accessible labels, readable contrast and touch-friendly controls.
- Published/private states and success/error messages are explicit.

A native app is future scope. A web installable/PWA shell may be added later, but do not cache private financial/customer data offline by default.

## 7. Laravel, MySQL and future-app architecture

### Recommended stack

- **Laravel:** a supported stable version, pinned when implementation begins.
- **MySQL:** supported 8.x release, with migrations and transactional financial updates.
- **Web UI:** server-rendered Blade + Livewire + Tailwind for simple, mobile-first screens and public-page SEO.
- **API:** REST `/api/v1`, documented request/response contracts, pagination, filtering and consistent validation errors.
- **Authentication:** Laravel Sanctum; appropriate session/CSRF handling for the web, revocable tokens for future mobile clients.
- **Business logic:** reusable domain actions/services and policies used by both web and API entry points.
- **Documents/files:** private storage for client/financial files; explicitly published, sanitised assets for public galleries.
- **Background jobs:** queues/scheduler for PDFs, notifications, approved scheduled posts, expiry tasks and later provider events. Start lean; add Redis when justified.
- **Deployment:** one application initially, with a worker, scheduler, TLS, backups and monitored logs.

### What is reusable for the future app

Reuse API contracts, authentication, tenant rules, validation/business rules, pricing, document generation and integrations. Flutter or React Native can consume the same backend later.

**Do not promise that Blade/Livewire screens become native mobile screens unchanged.** A native interface is a separate client, although design tokens and interaction patterns can be shared.

### Tenancy and access

Use a shared database with a business/tenant key on private business records. Resolve tenant access from authenticated membership, not an arbitrary business ID supplied by the client.

Enforce isolation with policies, scoped queries, foreign keys, tenant-aware unique constraints and tests. PDFs, exports, queued jobs, file URLs and webhook routing require the same isolation—not only visible pages.

Public endpoints return only approved profile/gallery fields. Public enquiry creation derives the destination business from the approved listing and applies rate limiting, validation and anti-spam controls.

### Main data groups

- Identity: users, businesses, memberships, permissions.
- Platform billing: plans, subscriptions, subscription payments, ad-service orders.
- Marketplace/media: profiles, categories, service areas, gallery projects, images, Reel-style videos, covers, captions, associated URLs and moderation/display order.
- CRM: leads, qualification answers/fit flags, private enquiry attachments, source events, activities, follow-up tasks, clients.
- Delivery: projects and project-stage history.
- Documents: catalog items, quotations/versions/items, invoices/items.
- Money: customer payments, allocations where needed, receipts and correction history.
- Labour: workers, rate history, attendance, advances and wage-payment entries.
- Content: platform/business posts, revisions, categories/tags, authorised author identities and publication states.
- Discovery: permitted SEO metadata, canonical/redirect records and sitemap inputs.
- Tracking: provider configurations, script versions, publication/page scopes, necessary consent records, tenant-owned campaign links and permitted attribution touches.
- Governance: audit logs, notification preferences and integration configuration.

Use decimal money fields and server-side totals, UTC timestamps with appropriate local display, idempotent payment/receipt operations and transaction boundaries. Do not use floating-point arithmetic for financial totals.

## 8. WhatsApp and attribution: phased, honest capabilities

### Small CRM phase

- Capture manual/platform-form enquiries.
- Preserve a known profile/project/reel source and permitted campaign/UTM context, including first/last known attribution where available; see M17. Never overwrite a known source with an empty value or invent a missing source.
- Provide a user-initiated WhatsApp action and secure document-sharing flow.
- Record follow-up tasks and manually confirmed outcomes.
- Keep submitted forms, WhatsApp clicks, actual conversations and paid customers as different measurements.

### Official integration phase

- Select a compliant WhatsApp Business Platform provider/direct route.
- Check business onboarding, number/coexistence eligibility and required permissions.
- Process inbound webhooks to capture actual chats and supported ad-referral identifiers.
- Use approved templates outside the applicable customer-service window and respect opt-ins/opt-outs.
- Provide a clear human escalation path.
- Use provider delivery status and failures honestly; never show “sent” merely because a job was queued.
- Disclose provider/message costs and secure credentials.

Never base the product on unofficial WhatsApp Web/QR automation. Source tracking supports informed reporting, not a guarantee of complete or causal attribution.

## 9. Proposed build sequence and approval gates

| Phase | Deliverables | Acceptance gate |
|---|---|---|
| **0 — Foundation** | Laravel/MySQL setup, mobile component system, authentication, tenant isolation, permissions, API conventions and admin foundation. | Demonstrate two isolated business accounts and authorised admin access. |
| **1A — Small CRM + acquisition surfaces** | Business landing page, marketplace/profile/gallery with business media controls, qualified forms, permitted UTM capture, follow-ups, clients, lightweight projects, annual-plan activation, technical SEO and basic ad-service/admin operations. | A qualified marketplace enquiry retains its known source, reaches the correct workspace, gets a follow-up and converts to a client/project on mobile. |
| **1B — Content + measurement** | Platform/business blogs, simple uploaded Reel-style videos with associated links, UTM campaign-link builder/reporting, SEO/AEO controls and consent-aware tracking/script management. | Tenant-safe content, working video/link gallery, correct bylines/canonicals, preserved campaign context and scoped/nonduplicated/no-PII test events. |
| **2 — Financial workflow** | Material/labour quotations, branded PDFs, business invoice register, customer payments and receipts. | Quote → invoice register → partial/final payments → receipts reconciles with tenant-safe documents and audit history. |
| **3 — Labour module** | Worker/rate records, attendance, site work notes, advances, wage payments and balances. | Multiple days/sites produce correct balances without double-counting or rewriting historical rates. |
| **4 — Integrations and growth** | Approved payment gateway, official WhatsApp automation, permissioned ad reporting and consent-aware attribution improvements. | Test-mode provider events, retries, duplicate handling, access isolation and opt-outs pass before live activation. |
| **5 — Expansion** | Native ad-launch workflow, AI creative tools with usage controls and a future mobile client using the API. | Separate approved scope after core usage, economics and provider feasibility are proven. |

API and tenant security start in Phase 0; they are not retrofitted when an app is requested. All five standard modules remain within the annual software plan as they are released.

Build one phase at a time and demonstrate it before proceeding. No production ads, live payment collection or messaging begins merely because the application is deployed; each requires separate provider configuration and authorisation.

## 10. GTM, validation, metrics and commercial sustainability

### Launch strategy

Start with a focused group of studios in one reachable city. Sell the connected workflow and the exact positioning—not an unsupported promise of leads, rankings or an all-powerful AI system.

**Direct acquisition**
- Build a manually qualified list of local studios using public business information and introductions.
- Speak with owners and daily users; review a limited set of real, permissioned enquiry/project examples.
- Demonstrate the mobile journey, branded documents and portfolio visibility using clearly labelled sample data.
- Use appropriate direct outreach, professional networks and in-person introductions. Do not scrape contacts for unsolicited WhatsApp blasts.

**Partners**
- After useful direct pilots, test referrals from agencies serving interior businesses.
- Explain that the platform helps the studio handle enquiries and show recorded outcomes; it does not automatically take ownership of an agency's client relationship.
- No large reseller/white-label platform or unapproved commission structure is added to the initial build.

**Homeowner-side distribution**
- Businesses share their own profiles, projects, articles and UTM links.
- Publish useful, factual local/service content and keep portfolios authentic.
- Build genuine local choice and a usable enquiry experience before claiming marketplace scale.
- A business's paid-ad landing experience should not divert its paid traffic to competitor recommendations.

### Proposed validation targets — experiments, not forecasts

1. About 15–20 owner/user conversations and several agency conversations.
2. Five permissioned process audits to discover recurring pain and missing information.
3. Three to five design-partner studios using the first CRM flow.
4. Evidence of repeated usage, complete follow-up records and practical benefit before widening acquisition.
5. Willingness to buy at the actual **₹4,999 annual** software price once offered capabilities are usable and clearly disclosed.

The private CRM pilot may precede completion of all modules. Disclose that explicitly; do not sell unfinished modules as live. Payment/refund and founder-pilot terms must be agreed before collecting money.

An illustrative 90-day commercial-learning cycle can cover interviews, pilot onboarding and outcome reviews, but it is **not a committed engineering schedule**. Delivery dates should be estimated only after approval and implementation feasibility checks.

### What to measure

**Acquisition:** qualified studio conversations, onboarding conversion, activation, actual acquisition cost and channel.  
**Product:** weekly active businesses, follow-ups completed, useful client/project records, quotes/invoices/receipts created and attendance usage.  
**Enquiry quality:** source completeness, qualification completion, service/budget fit, qualified consultations booked and attended.  
**Outcomes:** manually verified won/lost/customer/payment outcomes, time saved and support effort.  
**Commercial:** annual software sales, support/storage/delivery costs, management-service subscriptions and separately tracked service labour.

Define “qualified” for each studio before comparing results. Keep source, offer and time period reasonably comparable; small pre/post samples are directional rather than causal proof.

**Annual-plan correction:** monthly renewal is not a software-retention test because the CRM is billed annually. Measure early engagement/usage and later annual renewal; monthly ad-service renewal is a different metric and must not be presented as proof of software retention.

### Price-based scenarios — arithmetic, not market size or forecasts

| Scenario | Billing at the stated price |
|---|---:|
| 100 annual software customers | ₹4,99,900/year |
| 1,000 annual software customers | ₹49,99,000/year |
| 20 monthly ad-management customers | ₹59,980/month in management fees |

These are listed-price billing illustrations before tax treatment, refunds, discounts and operating costs. Annual receipts are not monthly cash receipts. Client advertising budgets are not platform revenue.

At this annual price, standardise onboarding and templates, automate routine operations, cap/disclose media allowances and avoid bundling unlimited video processing, AI generation or human services. Track true acquisition and support cost; do not reuse earlier economics based on a much higher monthly software price.

### Evidence for broadening the rollout

- Several studios use the CRM repeatedly without constant founder intervention.
- Enquiries keep their known source and assigned next action.
- Financial totals and attendance balances reconcile.
- Owners find the public profile/gallery useful even before organic marketplace traffic is substantial.
- There is a credible low-touch support and hosting model at the agreed price.
- Integration access and service capacity are confirmed before selling corresponding capabilities.

## 11. Quality and operational acceptance

Before launch, test:
- Cross-tenant access through screens, API, documents, exports, jobs and file links.
- Business-owner/staff/admin permissions.
- Landing-page price consistency and subscription activation/expiry.
- Qualified-form fields, consent, sources, private attachments, anti-spam and duplicate-submission protection.
- Blog permissions, bylines, revisions, approval and scheduled publication.
- Business gallery upload/edit/delete, covers, ordering, private drafts and tenant-safe media access.
- Reel-style playback, safe associated URLs, video lazy-loading and no dependency on Instagram publishing.
- UTM generation/capture, known/unknown attribution states, campaign reporting and safe redirect ownership.
- Canonicals, accurate rendered schema, redirects, sitemaps and private/draft exclusion.
- Script publishing/rollback, consent withdrawal, page scopes, event deduplication and payload inspection for private-data leakage.
- Lead conversion and project history.
- Quote/invoice numbering, taxes, rounding, discounts, registers/exports, platform-vs-business invoice separation and corrections.
- Partial payments, advances, receipts and idempotency.
- Attendance allocations, historical wage rates and payment balances.
- Phone layouts and accessibility, including low-width screens.
- Backups and a real restore rehearsal.
- No production secrets, fake testimonials or demo customer records in public production data.

Public production galleries must use owned/authorised assets; the existing concept's reference imagery is not a production portfolio.

## 12. Boundaries that keep the first product small

**Included in the standard product:** the five requested modules, direct studio profile/gallery enquiries, business-managed galleries, simple Reel-style video uploads with associated URLs, UTM tools, qualified contact forms, platform/business blogs, technical SEO/AEO foundations, controlled admin tracking, lightweight client/project tracking, mobile web UI, API-ready architecture and super-admin operations. Agreed prices remain unchanged; media allowances must be disclosed before launch.

**Not initially included:** a full interior ERP, procurement/inventory, CAD/design tools, homeowner quote bidding, escrow, lead resale, review/rating systems, custom domains, complex workflow builders, statutory payroll, GST filing/e-invoicing or an already-built native app.

Advertising management is a separately priced service. AI generation/native ad management and automatic WhatsApp sending remain clearly phased roadmap capabilities, not hidden promises inside the ₹4,999 software plan.

At this price point, standardise onboarding/templates, automate routine operations and avoid unlimited storage, video generation or hands-on services. Before checkout goes live, approve and display any user/storage/gallery allowances explicitly; do not add undisclosed caps or extra fees.

## 13. Approval defaults and open decisions

Development has been approved and the Phase 0/1A foundation has been implemented. The following defaults describe the implementation direction; unresolved commercial/provider details below remain launch decisions, not blockers to the already-authorised foundation work:

1. Blade web UI, concept-matched self-hosted CSS and progressive JavaScript for the first build, with reusable Laravel services and Sanctum `/api/v1`. Livewire/Tailwind remain optional later enhancements, not prerequisites to preserving the approved design.
2. One approved public listing/gallery included with the annual plan; no separate free-listing or per-lead pricing in v1.
3. Manual subscription activation for the private pilot, then a verified gateway integration before online public sales.
4. Manual/user-initiated WhatsApp sharing first; official automatic messaging in the integration phase.
5. The phase order above, beginning with the foundation and small CRM (1A), then content/measurement (1B).
6. Business-name attribution by default, optionally an authorised named author, with admin approval before initial business-article publication.
7. Super-admin-controlled tracking/scripts restricted to permitted public pages with consent and private-data protections.
8. Simple uploaded videos displayed in a Reel-style gallery with associated URLs; no Instagram account connection/publishing is part of this requirement.
9. Business-owned gallery/media controls and campaign-link/UTM management, with tenant-safe attribution.

Before production billing/service launch, separately finalise GST-inclusive/exclusive price display, gateway/provider choice, documented refund/renewal terms, user/storage allowances and the exact monthly ad-management deliverables.



### Remaining decisions before public commercial launch

These are not reasons to change the agreed software/service prices without permission:

- Production domain and initial commercial launch city. The brand **Spacekind is confirmed**; the demo directory’s cities do not by themselves establish a commercial launch commitment.
- GST-inclusive/exclusive price presentation and legal/tax review.
- Payment gateway, account/settlement ownership, refund/renewal terms and expiry grace policy.
- User seats, gallery/video/document storage, upload sizes and any disclosed fair-use allowances.
- Exact ₹2,999 ad-management deliverables: campaign/creative scope, approval process, reporting and service capacity.
- Hosting, mail and file-storage providers, backups and retention.
- Official messaging/ad integration provider and approvals when that phase is reached.
- Actual tracking IDs, approved script providers, consent policy and authorised content assets.

The user has authorised coding and confirmed Spacekind. Configuration credentials and live-provider authorisations remain separate from development approval. No live billing, ad spend, messaging or production tracking is authorised merely by this milestone.

## 14. Existing deliverables and next action

### Already completed

- Business/GTM exploration and positioning discussion.
- A self-contained interactive HTML design concept: `interior-platform-concept.html`.
- Detailed module planning consolidated into this master document.
- Confirmed Spacekind branding and explicit approval to begin development.
- First working Laravel/MySQL foundation + small CRM application, with public/owner/staff/admin views and API services.
- Actual MySQL migrations and clearly labelled fictional demo records.
- 28 automated tests / 139 assertions, 68 responsive route checks and a 25-check synthetic browser/admin journey; targeted fixes and local recovery rehearsal completed. Reports and boundaries are in `spacekind/qa/` and `spacekind/README.md`.

### Not yet completed

- A working production business/admin system.
- Live payments, ad-account access, message sending or production analytics configuration.
- A native mobile app.

### Next development action

Review the running **Phase 0/1A development slice** and harden the account/CRM pilot as needed. Continue the agreed sequence with Phase 1B content/media/UTM/SEO-tracking work, then financial and labour modules; keep every not-yet-implemented capability explicitly upcoming. Production legal, operational and integration gates remain separate.

No live payments, advertisements, messages or production scripts are activated solely because coding has been approved. Those require appropriate configuration, testing and explicit authorisation.

**Implementation status: STARTED — first working foundation / small-CRM slice implemented and tested. Spacekind is confirmed. Production launch and live-provider activation have not occurred.**
