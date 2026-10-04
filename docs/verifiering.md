# Verification report — Docker/NGINX baseline 0.1.0

Executed 2026-10-05, Europe/Stockholm, on Linux/amd64 inside WSL Kali using an isolated actual Docker Engine 29.8.2, Compose 5.5.0 and Buildx 0.37.2. Windows had no installed Docker Engine. This was a real container stack, not a simulated storefront. Test resources and generated credentials were separated from personal services. No application image was published.

The final stack uses WordPress 7.1.2/PHP 8.3, WooCommerce 11.1.2, NGINX 1.30.5, MariaDB 11.4.13 and Certbot 5.8.0. Exact digests and archive checksums are recorded in `compose.yaml` and `dependencies.lock.json`.

## Verified

| Check | Actual result | Scope |
| --- | --- | --- |
| Application image build and Compose startup | PASS | Real dependency archive checksums, database health, locked migrations, app/proxy/worker startup |
| HTTP integration | 8/8 PASS before and 8/8 after recreation | Rendered demo, database products in SEK, guest cart with real variation and price 28900 öre, admin login, readiness, protected paths, proxy spoofing rejection, purchase block |
| Image/code separation | PASS before and after recreation | Read-only application filesystem, code outside inherited WordPress anonymous volume, matching runtime/source hashes, Apache-readable runtime config |
| Container recreation | PASS | Real product price/name, setting and uploaded file preserved; two demo parents remain unique |
| Fresh final-image installation | PASS | New isolated database/upload volumes; initial admin installation, 8/8 HTTP checks, recreation, image-layout and persistence checks, then 8/8 HTTP checks again |
| Task daemon restart | PASS | Retained fresh-demo data, current immutable code and 8/8 HTTP checks after the isolated Docker daemon was restarted |
| Private query logging | PASS | Actual requests checked against both Apache and NGINX access logs |
| NGINX HTTP/TLS container harness | 7/7 PASS | ACME-only first start, canonical redirects, self-signed certificate installation/replacement reload, Host/SNI rejection, security headers, proxy header replacement, login rate limiting and private-path/upload guards |
| Domain/script/archive behavior | 12/12 PASS | Invalid domain rejection, safe renderer behavior, unencrypted-backup refusal, explicit restore guard, traversal/special-file rejection and contained Certbot symlinks |
| Encrypted backup and real restore | PASS | GPG snapshot, deliberate product/setting/file changes and order deletion, database and volume recovery, matching file hashes and healthy services; actual product, pending order, upload and private file verified |
| Real WordPress application fixtures | PASS | Purchase guards, capabilities/nonces, settings sanitization, combined catalog filters, owner settings/price/stock/media preservation |
| Empty production bootstrap | PASS | No demo products, Sweden only, checkout closed, reusable size/color global attributes |
| Interrupted bootstrap recovery | PASS | Missing variants/attributes/media repaired; existing variant price/stock preserved |
| Atomic demo writes | PASS | Controlled failures during new parent ownership and new variation SKU writes roll back; retry succeeds without a completed marker or partial product |
| Syntax and launch-policy checks | PASS | 26 PHP files, JavaScript, declared POSIX/Bash shell syntax and hand-checked launch-policy cases |
| Release workflow | Local Actionlint PASS | Five active official action commit pins verified; workflow not executed on GitHub |
| CSV example through actual WooCommerce importer | PASS | Draft variable parent, two variants, price/stock, existing global attributes and repeated import preserving changed owner values; browser admin workflow and ZIP importer not executed |

The application fixture tests used fresh WordPress/WooCommerce/MariaDB containers; those isolated fixtures used MariaDB 11.4.10. The main final stack uses patched 11.4.13. Fixture outputs alone do not prove the final image; the real HTTP, mount/hash and persistence checks above provide that evidence.

The recovery exercise targeted only the task-created `shopverify` demo on the private Docker daemon. Preflight checked project/volume labels, demo mode, fixture-only products/orders and test accounts. No pre-existing user or production data was replaced. Binary archive commands use the existing image with `--pull never`; the real test proved this prevents build output from contaminating tar streams.

Certificate-volume recovery also checked fixture bytes and the relative Certbot symlink layout. That archive fixture was not a serving certificate; real TLS behavior was tested separately with self-signed certificates in the NGINX harness.

## Fixes proven by tests

The initial HTTP run exposed an unreadable runtime configuration link. The upstream image also declared `/var/www/html` as a volume, which preserved stale application code on image replacement. Code now lives at `/var/www/shop`, with only uploads mounted there; the tests inspect actual mounts and compare running file hashes. Apache and NGINX log tests exposed private query logging and verified the corrected format. Interrupted-install tests proved partial demo writes before the transaction/recovery fixes. The cart creation assertion now matches WooCommerce's actual HTTP 201 response and verifies the returned variation, quantity and price.

## Unverified or unavailable

- Public ACME issuance against a real domain/DNS; the TLS harness uses test certificates.
- Application image publication, GitHub workflow execution, local ARM64 build/execution and a clean external registry `pull/up` installation. Official infrastructure manifests include amd64/arm64; that does not prove the custom app's ARM64 build.
- A cross-version application/schema upgrade and rollback. Container recreation verifies preservation, not all future migrations.
- Actual Stripe payments, webhook signatures/idempotency, concurrent last-item purchases, delayed payments/refunds, reliable transactional email, digital withdrawal and guest order recovery.
- Secure preview-based CSV+ZIP product import, verified 30-day price history, 2FA, complete privacy/legal/accessibility and commerce E2E work.

Purchases remain blocked on the server. This report verifies the Docker/NGINX baseline and does not certify a complete commercial store or legal compliance. See `aterstaende-krav.md` for the preserved full delivery scope.

## Reproduction

On a disposable Linux demo installation with generated `.env` and secrets:

```sh
sh scripts/setup.sh --demo
sh tests/run-containers.sh
python3 deploy/tests/test_nginx.py
```

Use the synthetic recovery-key helper and guarded `deploy/tests/backup-recovery.sh` only in an isolated `shopverify` project, never against a live shop. Recovery deliberately changes data before restoring it. Exact commands and prerequisites are in the operations/deployment guides. Release evidence and source hashes are packaged under `docs/test-evidence/`.
