# Spacekind — Phase 2 incremental patch

**For the already installed, working latest Phase 1B build. This is NOT a complete project or a fresh installation.**

Release: `phase-2-finance-pilot.1`. Keep the current `.env`, `APP_KEY`, database, accounts, uploaded files and storage. There is **no reset, seeding, checkout activation or external integration activation** in this update.

## What is in the ZIP

- `upload/`: **only new/changed application files** compared with the confirmed Phase 1B source archive. Copy the *contents* into the existing Laravel project root, not into `public/` and not into a new application folder.
- `manifest.json`: per-file action, baseline SHA-256 and patched SHA-256; baseline identity, unchanged identity anchors and exact migration list.
- `preflight.php`: CLI-only, read-only file/build/runtime/database checks. It does not copy files or run migrations. Default checks can bootstrap the existing app; do not expose this file on the web.
- `SHA256SUMS`: integrity checks for the unpacked patch payload. `PATCH-README.md` is this guide.

**Not included:** `.env` or `.env.example`, `APP_KEY`, credentials, database dumps/private snapshots, customer records, uploaded media/logos, vendor dependencies, caches, logs, sessions, a new Laravel scaffold, unchanged images/videos/fonts, or automatic demo fixtures. Composer installs the locked PDF dependency during the update.

Baseline archive: `Spacekind-phase-1B-build.zip`  
Baseline SHA-256: `8033055d296c215c92ff90ed7e5a92617ebd030ed6a41df9f6b69d194f3fa198`

## 1. Stage and check before uploading

First use a **private staging copy of the current deployed site/database/uploads** if available. Use only synthetic financial information during acceptance checks. Live GST/accounting use still requires accountant and deployment review.

Unzip the patch into a private directory **outside the website and outside the application**, e.g. `/home/account/spacekind-phase2-patch`. Do not upload the archive/preflight/manifests/backups into `public_html`/`public`.

Examples below assume Bash/SSH. Replace paths with your actual directories; `APP` is the directory containing `artisan`, **not** the document root. The correct public document root remains `APP/public`.

```bash
APP=/absolute/path/to/existing/spacekind
PATCH=/absolute/path/to/extracted/spacekind-phase2-patch
cd "$PATCH"
sha256sum -c SHA256SUMS
php preflight.php --target="$APP"
```

PHP 8.3+, actual MySQL 8+ (not MariaDB), the working Phase 1B migrations and required extensions are checked. The build was tested on PHP 8.4 / MySQL 8.4. `dom`, `mbstring`, `fileinfo`, `pdo_mysql`, XML and normal Laravel extensions must be available to both CLI and the web PHP worker. JPG-only PDF logos do not require GD in the tested renderer.

**If a hash conflict or wrong-build warning appears, STOP.** A deployed customization may need a three-way merge. Do not force-overwrite it, use an earlier CRM baseline or delete financial tables to make the check pass. The manifest identifies exactly which files would be replaced. New files with conflicting existing names also stop the check. Already-patched identical files are recognised, allowing an interrupted file copy to be inspected/resumed.

`--files-only` exists for offline package QA; **it is not deployment preflight** and does not check dependencies, the database or environment. Do not use it to bypass a real readiness failure.

## 2. Back up and stop writes

Arrange a maintenance window, ask owners/staff to finish their work, and pause any application queue/scheduler/integration writers at the host. Do not allow old/new code to serve writes simultaneously. Record the deployment, keep these backups outside any web-accessible directory and protect/encrypt them appropriately.

```bash
BACKUP=/private/backups/spacekind-before-phase2-$(date +%Y%m%d-%H%M%S)
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"
cd "$APP"
php artisan down --retry=60
# Full source/.env/upload backup for YOUR existing installation (not a replacement build):
tar --exclude='./vendor' --exclude='./node_modules' --exclude='./.git' \
    -czf "$BACKUP/files-before.tgz" .
sha256sum .env > "$BACKUP/env-before.sha256"
```

Export the **existing MySQL database** using your hosting backup tool/phpMyAdmin or `mysqldump`. For example, substituting your own DB host/user/name (the `-p` option prompts rather than putting the password in shell history):

```bash
mysqldump -h YOUR_DB_HOST -u YOUR_DB_USER -p \
  --single-transaction --no-tablespaces --set-gtid-purged=OFF \
  YOUR_EXISTING_DB_NAME > "$BACKUP/database-before.sql"
# Include routines/triggers if your installation uses them and the backup user has those permissions.
test -s "$BACKUP/database-before.sql"
tar -tzf "$BACKUP/files-before.tgz" > "$BACKUP/files-list.txt"
```

Check command exit codes and verify the backup with a restore rehearsal into a **different** private database/location. File size/listing alone is not a successful restore test. Include private and public uploads/billing logos, and any externally stored media, in your backup plan. The local `spacekind:snapshot` helper is **not** a substitute for this MySQL + files backup.

If any backup fails, keep the old application unchanged, restore service on the old build as appropriate, and fix the backup process first.

## 3. Copy just the patch files

With maintenance still on:

```bash
rsync -av --checksum "$PATCH/upload/" "$APP/"
# No --delete. Do not copy PATCH itself into the application or change .env.
cd "$APP"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan optimize:clear
```

Use **`composer install` against the patched lock**, not `composer update` and not `composer setup`. It adds Dompdf and its locked supporting packages. There is no Node/Vite build for these local assets. Restart PHP-FPM/clear OPcache through the host if it keeps old code; keep maintenance in place while doing so.

If Composer fails, do not reopen a mixed-dependency site. Check PHP/extension/hosting errors privately and fix them or use the pre-write recovery procedure below.

### File-manager / FTP users

After a successful preflight and backups, upload **the contents of `upload/`** over the current Laravel root while retaining paths. Do not delete old folders. `public/assets/finance.css` and `.js` go into the same existing public assets directory used by the rest of the app.

**File upload alone is not enough.** Your host/control-panel terminal or developer must run Composer and the migrations below, then post-update checks. If you have no terminal, ask the host to execute those commands; do not add a publicly accessible migration/Composer web script. No new `.env`, database, `APP_KEY` or website setup is needed.

## 4. Apply only the three financial migrations

```bash
cd "$APP"
php artisan migrate --force \
  --path=database/migrations/2026_11_17_000003_add_financial_workflows.php \
  --path=database/migrations/2026_11_17_000004_add_allocation_retry_keys.php \
  --path=database/migrations/2026_11_17_000005_add_quotation_delivery_record.php
php artisan migrate:status
php artisan view:cache
php "$PATCH/preflight.php" --target="$APP" --after
sha256sum -c "$BACKUP/env-before.sha256"
```

Changes are additive: eleven finance/platform tables; tenant/client/project identity constraints; allocation retry UUID; quotation sent-record columns. No existing business/client/project, invoice, uploaded file or configuration record is reset or seeded. Billing settings are deliberately blank until an authorised owner/admin enters and reviews them.

Migrations are one-time tracked; repeating the command after successful completion does not duplicate data. **MySQL DDL is not fully transactional.** If a migration fails halfway, keep maintenance on and inspect the exact migration/table state with a developer. Do not blindly rerun, remove migration entries, drop tables, reset auto-increments or run a fresh migration.

## 5. Smoke-test, then reopen

On a staging copy first, or with explicitly fictional test records on an approved development installation:

1. Existing owner/staff/admin logins, enquiries, blogs/videos, old uploads and projects still work. Existing records remain present. Check an old private plan/media file as well as the database.
2. Owner: **More / Invoices & receipts → Billing setup**. Save legal name/full address/state, optional GSTIN, explicit non-GST/inclusive/exclusive basis, distinct prefixes and reviewed terms. Optional logo: genuine JPG, ≤1 MB / 32–1600 px. Do not use fake GST information on a live commercial document.
3. Add a catalogue material/service/labour item. Prepare a quotation for a same-studio client/project; verify quantity/unit/rate/discount and CGST+SGST or IGST. Save/edit draft, issue, download PDF, manually mark sent and record acceptance. Convert to a draft invoice; review again before issuing.
4. Record a synthetic partial payment; repeat the **same** form submission to verify one receipt. Record/allocate an advance; verify outstanding. Reverse a test record with a reason, without implying a bank refund. Keep the audit trail rather than deleting issued records.
5. Verify PDF branding/text, private share and revocation, financial-year/client/project/date/status filters, CSV and owner-only access. Staff must not access finance/export; another studio must receive 404 for a private financial ID.
6. Super-admin: **Platform billing**. Configure the separate issuer/tax basis, draft/review/issue a software/service bill and record a manual platform payment. Owner sees only their issued Spacekind bills under **Spacekind bills**. Studio customer invoices/payments must not change. No subscription is activated.

Do not create fake issued tax invoices in a real production register merely for smoke testing. Use a staging clone and your accountant’s acceptance procedure instead. This patch has **no financial demo seeder** and no automatic fiscal setup.

Once post-update and acceptance checks pass:

```bash
cd "$APP"
php artisan up
```

Resume previously paused writers deliberately and verify HTTPS/login/downloads once more. The patch does not change `.env`, indexation/tracking locks, messages, gateways or advertising configuration. Keep all live integrations disabled unless separately authorised/configured/tested.

## Recovery / failed-update rule

- **Before any post-update writes:** keep maintenance on. A reviewed recovery can restore the verified pre-update **database and matching application/files** together, using the original `.env`/`APP_KEY` and original Composer lock, then reinstall original dependencies. Verify before reopening. This is a restoration of your own backup, not a new installation.
- **After any financial or other new writes:** do **not** restore an older database over them or roll back finance migrations. Preserve an incident backup and use a reviewed forward fix/data-preserving reconciliation. Get developer/accountant help where appropriate.
- Never use `migrate:fresh`, `migrate:reset`, `db:wipe`, `db:seed`, `key:generate`, a new `.env`, or deletion of invoices/payments/sequences as an update/recovery shortcut. Migration rollback guards refuse populated financial-history tables, but this is not a substitute for backups.

This patch supplies development financial workflows, not statutory accounting/GST filing, IRNs, e-way bills, bank reconciliation or production security approval. See `docs/phase-2.md` and `docs/launch-gates.md`.
