# Ursprunglig leverans: återstående krav

Det aktuella delmålet gäller faktisk dynamisk körning bakom en NGINX-container. Följande lista håller kvar den ursprungliga fullständiga butikens omfattning. En fungerande NGINX-installation bevisar inte att dessa funktioner är levererade. Produktionsköp är spärrade och paketet får inte beskrivas som en komplett kommersiell release ännu.

| Krav | Aktuell status |
| --- | --- |
| Dynamisk butik, produkter, varianter, vanlig kundvagn och admin | Implementerad grund; se verkliga körresultat i verifieringsrapporten |
| Kompletterande designers/kollektioner och produktinformation | Grundläggande taxonomier, vyer och fält finns; hela kravbildens filter och redaktionella sektioner ska fortsätta granskas |
| Stripe Checkout, signerade webhooks och betalningsstatus | Inte implementerat i denna release; inga riktiga nycklar eller mockade köp |
| Transaktionell sista-varan, sena/fördröjda betalningar och idempotens | WooCommerce har lagergrund, men de begärda konkurrens- och betalningsflödena återstår att implementera/integrationsverifiera |
| Hel/partiell Stripe-återbetalning med avstämning | Inte implementerat |
| Tillförlitlig mejlkö, återförsök och varaktiga villkorssnapshot | Inte implementerat; SMTP-fälten i Compose är endast reserverad konfiguration |
| Säker gäståtkomst, digital ångerfunktion, mottagningsbevis, returer/reklamationer | Inte implementerat enligt hela kravbilden; utkastssidor ersätter inte fungerande flöden |
| CSV+ZIP-import med förhandsgranskning, idempotens, bildoptimering och säkra filgränser | WooCommerces standard-CSV finns; den begärda kompletterande importen återstår |
| Verklig prishistorik och tidigare lägsta 30-dagarspris | Inte implementerat; inga påståenden om korrekt realogik lämnas |
| 2FA och komplett rate limiting/behörighetsgranskning | Grundläggande WordPress capabilities/nonces och NGINX-loginbegränsning finns; 2FA och hela hotmodellen återstår |
| Juridiska texter, cookieconsent för valfria scripts, personuppgiftsflöden/gallring och full legal-kontroll | Förstudie finns i systemdesignen; kompletta texter, tillämplighet, verifiering och rättighetsflöden återstår |
| WCAG 2.2 AA, manuell skärmläsargranskning, SEO/schema/sitemap | Tillgänglig grund och kärnfunktioner finns; full kravverifiering återstår |
| Automatiska låsta migreringar, persistens, NGINX och HTTPS-livscykel | Implementerad grund; skilj faktiska lokala testresultat från extern ACME-verifiering |
| Backup/återställning och rollback | Scripts levereras; exakt teststatus måste läsas i verifieringsrapporten |
| Publicerade appimages för amd64/arm64 och ren extern pull/up-installation | CI-flöde finns. Appimagepublicering och extern installationsverifiering återstår; inga registryadresser fabriceras |
| Fullständiga handels-E2E och verifierad komplett produktionsrelease | Inte uppfyllt. Gröna NGINX-/grundtester får inte användas som bevis för den ursprungliga kompletta leveransen |

Extern åtkomst som senare behövs: GitHub/repository eller annat registry, riktig domän och Linuxserver, verifierat Stripe-företagskonto, mejlleverantör och verksamhetens företags-/produktuppgifter. Kostnader bestäms av konton, volymer och leverantörer.
