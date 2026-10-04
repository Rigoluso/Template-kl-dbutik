# NGINX och containerdrift

Deploymentlagret gäller den dynamiska WordPress/WooCommerce-applikationen i samma paket. Det ersätter inte de handelsfunktioner som fortfarande återstår. NGINX 1.30.5, MariaDB 11.4.13 och Certbot 5.8.0 använder officiella versioner med låsta digests i `compose.yaml`. Registry-manifesten kontrollerades 2026-10-04/05; samtliga tre innehåller både linux/amd64 och linux/arm64. NGINX-versionen valdes efter kontroll av [officiella säkerhetsmeddelanden](https://nginx.org/en/security_advisories.html) och [stabil release](https://nginx.org/en/download.html). MariaDB följer den underhållna [11.4-seriens releaseinformation](https://mariadb.com/docs/release-notes/community-server/11.4/11.4.13). Applikationsimagen måste publiceras separat innan produktionskommandot `docker compose pull && docker compose up -d` kan verifieras på en ny server.

## Lokal HTTP-demo

Kör från paketets rot på en Linuxhost med Docker Engine och Compose-plugin:

```sh
sh scripts/setup.sh --demo
docker compose -f compose.yaml -f compose.dev.yaml up --build -d --wait
```

Butiken finns på `http://localhost:18080`. Demons port binds till hostens loopback. `compose.dev.yaml` bygger den verkliga appimagen och använder lokal tagg `shop-template:0.1.0`. `.env.demo.example` ger denna tagg åt produktionsfilens obligatoriska imagevariabel innan Compose läser overridefilen. Uppladdningar, privata dokument och databas ligger i separata volymer; kodkatalogen ligger i imagen.

## Produktion och HTTPS

`scripts/setup.sh --production` skapar saknade hemlighetsfiler och kopierar `.env.example`. Ange faktisk publicerad `SHOP_APP_IMAGE`, huvuddomän, HTTPS-URL, admin-e-post och ACME-e-post i `.env`. `SHOP_WWW_DOMAIN` är ett valfritt explicit alias. DNS för båda namnen och fungerande A/AAAA måste peka på servern. Port 80 och 443 behöver vara öppna. Använd inga demo-overridefiler i produktion.

NGINX startar utan certifikat. Huvuddomänens HTTP svarar då 503 och endast ACME-utmaningens webroot kan lämna filer. Admin och kassa exponeras först genom HTTPS när ett certifikat finns. Certbot utfärdar med webroot och kontrollerar förnyelse var tolfte timme; NGINX kontrollerar certifikathash var trettionde sekund, validerar konfigurationen och reloadar. Ingen container får Docker-socketen. Certifikat och ACME-kontot överlever omstarter i certifikatvolymen.

Efter HTTPS-aktivering omdirigeras huvuddomänens HTTP och aliasets HTTPS med 308 till `SHOP_DOMAIN`. Okända HTTP-Host-värden ger 421, och okänd TLS-SNI nekas vid handskakning. HSTS är begränsad till ett dygn och inkluderar inte underdomäner/preload. `ACME_STAGING=1` används enbart för prov; stagingcertifikat är inte webbläsarbetrodda och certifikatvolymen måste separeras från den efterföljande liveinstallationen.

NGINX ersätter klientens proxyheaders. WordPress litar endast på `SHOP_PROXY_IP`, som motsvarar NGINX adress i det privata frontnätet. Ändra `SHOP_PROXY_SUBNET`, `SHOP_PROXY_DYNAMIC_RANGE` och `SHOP_PROXY_IP` tillsammans vid nätkonflikt; proxyadressen måste ligga inom subnätet och utanför det dynamiska intervallet. MariaDB och Apache publicerar inga portar; app och worker har ett separat nät för utgående Stripe-/SMTP-anslutningar. Uppladdade PHP/SVG/HTML-filer, konfigurationsfiler, Apache-status och webbkron nekas. Den separata workern kör schemalagt arbete genom CLI.

Privata sidor cachas inte av NGINX. Accessloggen utelämnar klient-IP, querystrings, cookies och referrer. Rutinmässiga NGINX-felloggar hålls avstängda eftersom de kan innehålla fulla privata request-URL:er; konfigurations-/certifikatfel och healthchecks används för driftkontroll. CSP tillåter WordPress nödvändiga inline-skript/stilar och angivna Stripe-origin; den måste verifieras igen när betalningsintegrationen är klar. Bevaka certifikatets giltighet och Certbot-loggar externt: automatisk förnyelse är inte ett larm om server/DNS slutar fungera.

## Krypterad backup och återställning

Linuxhosten behöver Bash, Python 3, GnuPG, tar och SHA-256-verktyg utöver Docker/Compose. Importera mottagarens publika GPG-nyckel och behåll den privata återställningsnyckeln på en separat säker plats. För demo används samma två Compose-filer som vid starten; för produktion används endast `compose.yaml`.

```sh
export COMPOSE_FILE=compose.yaml:compose.dev.yaml  # bara lokal demo
export BACKUP_GPG_RECIPIENT=<GPG-nyckelns-fingeravtryck>
bash scripts/backup.sh ./backups
```

Backup stoppar inkommande trafik, app, worker och certifikatjobb, väntar på deras avslut, tar databasdump och arkiv av uppladdningar, privata filer, certifikat, `.env`, Compose-konfiguration och de faktiska secretfilerna. Filhashar och tillåtna Certbot-symlänkar registreras i manifestet. Arkivet krypteras till GPG-mottagaren och får en separat kontrollsumma; klartextens temporära filer tas bort efter körningen. Ursprungligen körande tjänster startas även efter ett backupfel. Standardretention är 14 dagar i målkatalogen; `BACKUP_RETENTION_DAYS` kan ändras. `BACKUP_COPY_DIR` kan kopiera det krypterade resultatet till en separat monterad lagringsplats. Hantera kopians retention separat.

En restore kräver samma appimage-referens och matchande `.env`/hemligheter. På en ny host behöver dessa återställas från backupens `config/` före starten. Scriptet skriver inte över hostens konfiguration automatiskt: fel databaslösenord eller en annan domän ska inte införas tyst. Använd endast betrodda backuper; mottagarkryptering ersätter inte en signatur från backupskaparen.

```sh
bash scripts/restore.sh ./backups/shop-backup-YYYYMMDDTHHMMSSZ-NNNN.tar.gz.gpg --confirm-replace-data
```

Restore validerar krypterat arkiv och innehåll, stoppar skrivande tjänster, ersätter databas och de tre filvolymerna, jämför varje återställd fil mot manifestet och kräver godkända Compose-healthchecks. Om en åtgärd efter stoppet misslyckas lämnas skrivande tjänster stoppade för felsökning. Databasen kontrolleras genom lyckad import och applikationsstart; granska dessutom order-/betalningsavstämning innan verkliga köp aktiveras. Återställning till en äldre backup förlorar senare data. Använd samma release för återställningsövningen; genomför därefter uppgradering som en separat åtgärd.

För en manuell konfigurationsåterhämtning, dekryptera först till en privat arbetskatalog och använd `python3 deploy/backup_archive.py extract ARBETSKATALOG DEKRYPTERAT.tar.gz`. Funktionen validerar hela arkivet och manifestet innan resultatet används. Kopiera sedan `.env` och secrets från `config/` med begränsade filrättigheter och justera eventuella hostspecifika secretsökvägar.

## Verifiering

```sh
python3 -m unittest discover -s deploy/tests -p 'test_*.py'
```

Tester för domänvalidering och backupskydd kör verkliga scripts/arkivoperationer. `test_nginx.py` startar officiella NGINX-containrar och en isolerad testupstream och provar HTTP, säkerhetsheaders, proxyheaders, hostkontroll, inloggningsbegränsning, privata querylogs, första start utan certifikat samt installation och förnyelseladdning av ett självsignerat testcertifikat. Det verifierar NGINX-mekaniken. Verklig Let's Encrypt-utfärdning kräver extern domän/DNS och har inte verifierats av det självsignerade testet.

Testerna lämnar Docker-projektet för den vanliga butiken orört och rensar sitt eget tillfälliga nät och sina containrar. `DOCKER_COMMAND` kan ange en alternativ Docker-wrapper och `SHOP_TEST_WORK_DIR` kan ange en privat testarbetskatalog. Verifieringsrapporten i paketets `docs/` anger vilka testkörningar som faktiskt genomförts.
