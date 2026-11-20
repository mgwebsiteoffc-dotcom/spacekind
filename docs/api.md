# Spacekind API v1 — development foundation + campaign links + invoice read API

The API and Blade controllers share `LeadWorkflow` and tenant rules. There is no native mobile app yet. Treat this as a reusable development backend, **not a frozen production/mobile API contract**; curated resources, field/versioning/permission review and broader client/error UX remain required.

## Authenticate a studio user

```http
POST /api/v1/auth/token
Content-Type: application/json

{"email":"owner@aangan.example","password":"Spacekind@2026","device_name":"development-device"}
```

That fictional account exists only in a seeded local demo. A successful response returns a one-time bearer token, `token_type: Bearer`, and `expires_in_days: 30`. Store it securely; MySQL stores a token hash. The token route is rate limited. Inactive users, suspended studios and super-admin accounts cannot obtain studio device tokens here.

Use `Authorization: Bearer <token>` and `Accept: application/json`. The token determines the current user/business; **never submit a tenant ID to choose a workspace**. Tenant middleware precedes route-model binding, and foreign studio record URLs return 404. Active-user/business and owner/staff restrictions still apply to existing tokens. Revocation does not require exposing the token again in logs/UI.

| Method | Endpoint | Action |
|---|---|---|
| GET | `/api/v1/me` | Current studio/user/role |
| DELETE | `/api/v1/auth/token` | Revoke the current device token |
| GET | `/api/v1/leads` | Paginated studio enquiries; optional `stage` |
| POST | `/api/v1/leads` | Create a manual studio enquiry |
| GET | `/api/v1/leads/{id}` | Scoped enquiry, activities and assignee |
| PATCH | `/api/v1/leads/{id}` | Stage, assignee and follow-up fields |
| POST | `/api/v1/leads/{id}/notes` | Private note, `body` 2–3000 characters |
| POST | `/api/v1/leads/{id}/convert` | Idempotently mark Won and create client/project |
| GET | `/api/v1/projects` | Paginated studio project/client records |
| GET | `/api/v1/campaign-links` | **Owner-only**, own studio’s saved link summaries; new in Phase 1B |

## Financial read resource — new in Phase 2

`GET /api/v1/invoices` is **owner-only** and tenant-scoped with the same fresh active-user/business checks. It returns `data` containing a paginator (records in `data.data`) of up to 30 newest own-studio customer invoices:

```json
{"data":{"data":[{"id":1,"number":"INV/2627/00001","client_id":1,"project_id":1,"date":"2026-11-17","due_date":"2026-12-17","status":"part-paid","currency":"INR","total_minor":118000,"paid_minor":50000,"balance_minor":68000}],"current_page":1}}
```

The example is illustrative, with pagination metadata omitted. Totals are **integer minor units (paise)**, not decimal rupees. Drafts/voids have no collectible balance; issue/paid/overdue state is derived as documented in `phase-2.md`. No customer PII, token hash, private logo path, entire snapshot or other-business platform bill is included. Super-admin/staff do not gain studio financial API access by guessing IDs.

This slice supplies a curated read endpoint, not a full mobile financial API. Financial writes/PDFs/shares/CSV use owner-authenticated CSRF-protected web workflows backed by shared `FinancialWorkflow`/`DocumentCalculator`. Do not treat those session form routes as a frozen token/mobile write contract. Future writes require curated schemas, conflict/idempotency semantics and explicit permissions. Platform billing remains a separate admin/owner-read web workflow.

## Manual enquiry create/update

Create requires `name`, `phone`, `city`, `locality`, `property_type`, `configuration`, `scope`, `budget`, `readiness`, `brief`, `source`. Optional: `email`, `floor_area` in sq ft, `assigned_to`, `contact_consent`, private multipart `plan` upload.

Allowed values are in `config/spacekind.php`; plans accept PDF/JPG/PNG ≤8 MB. The public form requires area and explicit contact permission. A manual studio entry records permission only if the studio affirms it. No contact or original-source edits are accepted through the pipeline update endpoint.

Update fields:
- `stage`: New, Contacted, Qualified, Site Visit, Quote Sent, Won, Lost.
- `assigned_to`: null or an active **same-studio** user ID.
- `next_follow_up_at`: null or a timestamp; use an explicit offset. Web forms display Asia/Kolkata time.
- `follow_up_task`: required with a follow-up time, maximum 180 characters.
- `lost_reason`: required for Lost, maximum 240 characters.

Create does not trust submitted stage/business IDs. `plan_path` and public submission UUIDs are hidden from enquiry serialization. Source/UTM and the permitted `entry_context` are retained when a public qualified enquiry is captured, and copied to the client on conversion; the manual API does not grant permission to invent public attribution or update those fields. Existing lead/project responses still use foundation model-oriented serialization and require an explicit allowlisted-resource audit before expansion.

## Owner campaign links

```http
GET /api/v1/campaign-links
Authorization: Bearer <owner-token>
Accept: application/json
```

Response shape:

```json
{
  "data": [
    {
      "id": 42,
      "name": "Illustrative campaign",
      "destination_type": "profile",
      "url": "https://your-authorised-domain.example/studios/your-studio?utm_source=instagram&utm_medium=organic_social&utm_campaign=illustrative",
      "utm": {"source":"instagram","medium":"organic_social","campaign":"illustrative"}
    }
  ]
}
```

IDs/domain above illustrate the schema; they are not usable credentials or records. Values come only from the current studio’s saved links. Types are `profile`, `enquiry`, `article` or `reel`. The endpoint is read-only and does **not** expose visit/click/Won counts. Web-panel summaries independently show matching-label enquiries and studio-recorded Won outcomes, not causal or verified revenue attribution.

Link creation/deletion, article/video management and global tracking/discovery administration currently use CSRF-protected, role-restricted web routes. No new public write, arbitrary-destination redirect, article-draft API or script-configuration API was added.

## Privacy and integration boundaries

Public labels/context are retained only with separate optional campaign permission, independent of contact permission and optional tag consent. No persistent first/last-touch history or cross-device tracking is implemented. No API action sends a WhatsApp message, charges a payment or reports an ad-platform conversion. No private CRM data is deliberately exported to GA4/Meta/GTM. Third-party tags remain deployment-locked in the demo; production/live providers require their own configuration, network/consent validation and authorisation.
