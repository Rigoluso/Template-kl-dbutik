# Klädbutik — Docker/NGINX-grund 0.1.0

En dynamisk svensk WordPress/WooCommerce-butik med eget tema, beständig databas och uppladdningar, worker samt NGINX som publik container. Detta paket är en verifieringsbar grund för den begärda fullständiga butiken. Produktionsköp är avstängda: Stripe Checkout och flera efterköps-/lanseringsfunktioner i den ursprungliga kravbilden återstår. Se [återstående krav](docs/aterstaende-krav.md) och [verifieringsrapport](docs/verifiering.md). Paketet ska inte användas för verklig försäljning ännu.

English guides: [operation and startup](docs/drift.md), [first-launch tasks and costs](docs/first-launch.md), [product CSV and design examples](examples/README.md). The storefront remains Swedish with SEK.

## Starta fungerande lokal demo på Linux

Docker Engine och Docker Compose-plugin måste vara installerade. Packa upp paketet och kör i installationsmappen:

```sh
sh scripts/setup.sh --demo
sudo docker compose -f compose.yaml -f compose.dev.yaml up --build -d
```

Öppna http://localhost:18080. Admin finns på http://localhost:18080/wp-admin/. Användarnamn: värdet SHOP_ADMIN_USER i .env (standard shopadmin). Lösenord finns i secrets/admin_password. Läs det lokalt; skicka det inte till support eller loggar. Demons portar binds bara till loopback. De två exempelplaggen och bilderna är märkta som demo. Katalog, variantval, varukorg, media och admin använder riktig databas; kassa blockerar köp på servern.

Secrets skapas bara om de saknas. Startskriptet skriver inte över befintlig .env eller lösenord. Koden finns i image; produkter, sidtexter och designinställningar ligger i databasen. Bilder finns i separat uppladdningsvolym. Normal omstart och uppdatering matar inte in exempelprodukter igen.

## Administration och design

- Produkter, variationer, lager, priser och bilder: **Produkter**. Standardimport/export i WooCommerce hanterar CSV; den begärda säkra ZIP-importen med förhandsgranskning är ännu inte implementerad.
- Designers och kollektioner: taxonomier under Produkter. Storlek och färg: WooCommerces produktattribut och variationer.
- Material, fibersammansättning, skötselråd, storleksguide och produktsäkerhetsuppgifter: produktens extra fält. Dessa uppgifter måste komma från verklig leverantör.
- Namn, logotyp, favicon, färger, ljus/mörk profil och startsidans texter: **Utseende → Anpassa**. Navigation: WordPress menyredigering. Sidinnehåll: **Sidor**.
- Lanseringsstatus och företagsinställningar: **WooCommerce → Lanseringskontroll**. Att kryssa i alla punkter aktiverar inte betalning; nödvändiga integrationer saknas fortfarande.
- Ordrar, kuponger, skatter och frakt: WooCommerces ordinarie admin. Testa inte verkliga köp i denna release.

Innehållsändringar kräver inget imagebygge. WordPress-plugininstallation och koduppdatering genom admin är avstängda eftersom kod ska uppdateras genom versionskontrollerade images.

## Produktion, imagepublicering och det exakta startkommandot

Produktionsfilen compose.yaml kräver en **faktiskt publicerad** versionsmärkt appimage i SHOP_APP_IMAGE. Någon sådan appimage har inte publicerats i samband med denna leverans. NGINX, MariaDB och Certbot använder kontrollerade officiella registry-images; deras faktiska adresser och digests finns i compose.yaml. Appens kontrollerade WordPress-bas finns i dependencies.lock.json.

Publiceringsflödet `.github/workflows/containers.yml` testar först den riktiga containermiljön och bygger sedan linux/amd64 + linux/arm64 till det GitHub-repository där paketets källkod lagts in. Publicering startas genom rätt versionstag eller uttryckligt workflow-val. Ett lyckat workflow visar den faktiska imageadressen och digest efter manifestkontroll. GitHub-konto, repository och GHCR-publiceringsrättighet behövs. Workflow-filen har inte körts i GitHub i denna leverans.

Efter publicering och färdig engångskonfiguration:

```sh
sh scripts/setup.sh --production
# Redigera .env: faktisk SHOP_APP_IMAGE, HTTPS-URL, domän, admin-e-post, ACME-e-post.
sudo docker compose pull && sudo docker compose up -d
```

Använd inte compose.dev.yaml i produktion. Pull/up-kommandot är **inte verifierat för en publicerad appimage ännu**. En lokalt byggd image är inte bevis för att en extern server kan pulla den.

Domänens A-post och eventuell fungerande AAAA-post ska peka på servern; 80/443 ska vara öppna. NGINX startar utan certifikat och lämnar bara ACME-utmaning/HTTPS-status över HTTP tills certifikat finns. Certbot utfärdar/förnyar och NGINX verifierar/reloadar ändrade certifikat automatiskt. Felaktig DNS och HTTPS-utfärdning kan kontrolleras med `docker compose logs nginx certbot`. Verklig ACME-utfärdning kräver din domän och har ännu inte testats; certifikatövergången har separat lokalt integrationstest.

Domänbyte kräver ändrad SHOP_DOMAIN/SHOP_URL, DNS, certifikat och framtida betalningswebhook. SHOP_WWW_DOMAIN kan sättas som ett särskilt alias som omdirigeras till huvuddomänen. Byt inte domän genom enbart sidans designinställningar.

## Tester och felsökning

```sh
sh tests/run-containers.sh
python3 deploy/tests/test_nginx.py
sudo docker compose -f compose.yaml -f compose.dev.yaml ps
sudo docker compose -f compose.yaml -f compose.dev.yaml logs --tail 100 migrate app nginx worker
```

Testsviten använder ett separat demo-projektnamn shopverify, skapar verkliga databasprodukter, provar kundvagn och återskapar containrar för persistenskontroll. Python 3 krävs bara för testerna. Använd inte dessa testscripts mot en livebutik. Diagnostik ska aldrig innehålla lösenord eller hemligheter.

Migrate måste avslutas med status 0 innan appen startar. Appens healthcheck kräver databas och genomförd bootstrap. Databas, Apache och worker har inga publicerade portar. `/healthz` via NGINX kontrollerar applikationens beredskap; NGINX har också intern processhealthcheck. Fel i migreringen får inte kringgås genom att manuellt markera butiken installerad.

## Backup, återställning och uppdatering

Se [driftmanualen](docs/drift.md). Ta alltid en kontrollerad backup före uppdatering. `docker compose down` behåller normalt volymer; `docker compose down --volumes` raderar dem och ska inte användas för en butik du vill bevara.

## Licenser och externa kostnader

Egen kod och tema: GPL-2.0-or-later. Exempelbilder: egna schematiska illustrationer, samma licens, inga modell-/produktfotografier. Systemtypsnitt används; inga externa typsnittsbeställningar görs. WordPress/WooCommerce/WP CLI och officiella containerbaser har egna licenser; se [komponenter](docs/licenser.md). Källkod för temat/tillägget och låsta WooCommerce/WP CLI-paket följer med.

Server, domän, registry/CI, transaktionsmejl, frakt och backup kan medföra kostnader beroende på leverantör. Den planerade betalningsleverantören är Stripe; konto och avgifter behövs före integration/lansering. Se [systemdesignen](docs/systemdesign.md) och [engångsuppgifter](docs/first-launch.md) för underlag. Denna release skickar inte tillförlitliga produktionsmejl och tar inte emot betalningar.
