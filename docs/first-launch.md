# First launch and external services

For a local demonstration, follow the two commands in the README. A commercial launch remains blocked; this release cannot accept payments.

| One-time task | Account or information needed | Status |
| --- | --- | --- |
| Install Docker Engine and Compose on Linux | Server administration | Local Linux container runtime tested |
| Publish the application image | GitHub repository and GHCR publishing rights, or another registry | Workflow supplied; publication not run |
| Set domain and HTTPS | Domain, DNS and inbound ports 80/443 | Local certificate lifecycle tested; public ACME outstanding |
| Create and secure administration | Generated password, owner email, later 2FA | Password installation and permissions tested; 2FA outstanding |
| Enter real products and business information | Supplier data, stock, company identity, policies | Admin fields available; information supplied by owner |
| Enable payments | Verified Stripe account and test/live credentials | Stripe Checkout implementation and integration tests outstanding |
| Deliver transactional email | SMTP/email account, verified sender/domain | Reliable queue and delivery integration outstanding |
| Configure shipping and tax | Carrier arrangement, service areas, tax treatment | WooCommerce configuration available; business validation outstanding |
| Protect and recover data | Backup storage and GPG recovery key | Scripts supplied; executed status in verification report |
| Complete launch review | Legal applicability, withdrawal flow, privacy, accessibility and commerce tests | Outstanding; full list in `aterstaende-krav.md` |

The software license does not charge a store fee. Hosting, domain renewal, registry/CI usage, offsite backup, email delivery and shipping may cost money according to the chosen providers and volumes. Stripe charges payment fees according to the merchant's account and methods; consult its [current Swedish pricing](https://stripe.com/se/pricing) when implementing the integration. No Stripe, email, shipping or registry account has been connected by this delivery. SMTP variables currently reserve configuration only and do not establish verified delivery.

Only Sweden is enabled initially. Before adding another market, configure and validate its terms, shipping and tax. Do not display Swish or Klarna without a functioning verified integration and account eligibility. The launch checklist cannot override this release's server-side purchase block.
