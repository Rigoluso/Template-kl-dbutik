# Operations guide — version 0.1.0

This release runs a real dynamic catalog and administration interface behind an NGINX container. Purchases remain disabled. Read `aterstaende-krav.md` before planning a commercial launch. See `verifiering.md` for executed tests and their limits.

## Local demonstration

On Linux, install Docker Engine and its Compose plugin, then run from the extracted package:

```sh
sh scripts/setup.sh --demo
sudo docker compose -f compose.yaml -f compose.dev.yaml up --build -d --wait
```

Open `http://localhost:18080` and `/wp-admin/`. The administrator name is in `.env`; the generated password is in `secrets/admin_password`. Keep that file private. The demo binds only to loopback. To access it remotely, use an SSH tunnel rather than exposing demo HTTP credentials.

`setup.sh` creates missing files and preserves existing configuration and passwords. MariaDB stores products, variations, orders, text and design settings. Separate named volumes store uploads, private documents and certificates. The application filesystem is read-only; code changes use a new image. The worker runs scheduled WordPress jobs without public web cron.

## Production preparation and image publication

No application image has been published as part of this delivery. The package includes a tested build definition and a GitHub Actions publishing workflow. Put the source in a GitHub repository, run its verification workflow and publish the matching version tag or use the workflow's explicit publish option. Use only the image reference and digest reported by a successful workflow. Do not invent a registry address.

After publication, run `sh scripts/setup.sh --production` in a fresh installation directory and edit `.env`. Required values include the real `SHOP_APP_IMAGE`, `SHOP_DOMAIN`, HTTPS `SHOP_URL`, `SHOP_ADMIN_EMAIL` and `ACME_EMAIL`. Never include `compose.dev.yaml` in production. The intended normal start/update command is:

```sh
sudo docker compose pull && sudo docker compose up -d
```

That exact registry-based installation is still unverified because the application image has not been published. Local builds do not prove it works on an external clean host. Commercial launch additionally requires the outstanding payment, email, legal and account-security work.

Point the main domain's A record, and any working AAAA record, to the server. Open inbound TCP ports 80 and 443. `SHOP_WWW_DOMAIN` optionally declares one alias with matching DNS. Keep MariaDB and Apache ports private. If the configured subnet conflicts with server networking, adjust `SHOP_PROXY_SUBNET`, `SHOP_PROXY_DYNAMIC_RANGE` and `SHOP_PROXY_IP` together: the static proxy address must be inside the subnet and outside the dynamic range.

## HTTPS and domain changes

Production NGINX starts without an existing certificate. It serves the ACME challenge webroot and returns 503 for the store until Certbot supplies a certificate. Once installed, HTTP redirects to the canonical HTTPS domain. NGINX checks certificate changes and reloads only after configuration validation. Certbot renews periodically. Local tests cover this transition and certificate replacement with test certificates; actual public ACME issuance still requires a domain and DNS.

Use `ACME_STAGING=1` only for a rehearsal. Staging certificates are untrusted by browsers; use a separate certificate volume for subsequent live issuance. Monitor expiry and Certbot errors externally. Renewal automation cannot notify you when the host or DNS is unavailable.

For a domain change, schedule maintenance, back up, update DNS and `.env` (`SHOP_DOMAIN`, `SHOP_WWW_DOMAIN`, `SHOP_URL`), obtain a certificate for the new names, and recreate services. Review content links and future payment-webhook URLs. Do not change only the visual store name. Changing WordPress URL settings alone will not update the proxy or certificate.

## Backup and restore

The Linux host needs Bash, Python 3, GnuPG, tar and SHA-256 tools for the supplied scripts. Import a backup recipient's public GPG key; keep its private recovery key separately. A backup includes database, uploads, private files, certificates, configuration and secrets, so even encrypted backups need controlled storage.

For a demo, set `COMPOSE_FILE=compose.yaml:compose.dev.yaml`. For production, leave it unset or use `compose.yaml`. Run:

```sh
export BACKUP_GPG_RECIPIENT=<public-key-fingerprint>
bash scripts/backup.sh ./backups
```

The script stops writers for a consistent snapshot, creates and encrypts the bundle, records checksums and restarts previously running services. `BACKUP_RETENTION_DAYS` defaults to 14. `BACKUP_COPY_DIR` can copy encrypted output to separate mounted storage; configure that copy's retention separately. Test recovery regularly and monitor failed jobs.

Restoring replaces current data. Use a trusted backup, the matching release/image and matching configuration and secrets. Restore host configuration from the encrypted bundle first when moving to a new host; the script does not silently overwrite `.env` or credentials.

```sh
bash scripts/restore.sh ./backups/shop-backup-<timestamp>.tar.gz.gpg --confirm-replace-data
```

The restore validates the archive, replaces database and file volumes, compares file hashes, then requires healthy services. A failure after writers stop keeps them stopped for investigation. Successful recovery still needs business reconciliation before enabling future purchases. See `deploy/README.md` for configuration extraction and archive details.

## Updating and rollback

1. Record the current image digest, configuration and release package. Make a backup and confirm a recent recovery exercise.
2. Read the new release's migration and compatibility notes. Select an actually published image in `SHOP_APP_IMAGE`.
3. Run the normal pull/up command. The one-shot migration service holds a database advisory lock; dependent services start only after it succeeds.
4. Inspect service health, catalog, admin, uploads and background jobs before reopening the store.

Rollback must consider database migrations. Reusing an old image is safe only when that release explicitly supports the current schema. Otherwise restore the pre-update backup with its matching release. That discards later changes, so reconcile any later orders/payments independently. This package verifies container recreation and data preservation; a future cross-version migration/rollback is not yet tested.

## Diagnostics

```sh
sudo docker compose ps
sudo docker compose logs --tail 100 migrate app nginx worker certbot
```

For the local demo, add `-f compose.yaml -f compose.dev.yaml` to each command. Migration failure: check required environment, readable secret files and database health. NGINX 503 during first production start: check DNS, ports and Certbot. Unhealthy application: verify configuration readability, database access and successful bootstrap. Never bypass a failed migration by manually setting its completion option.

Use `/healthz` through NGINX for application readiness. Database and worker have separate checks. Routine access logs omit private query values; avoid sharing raw diagnostics without checking them for confidential information. `docker compose down` preserves named volumes; `down --volumes` deletes them.
