# Systemdesign: återanvändbar klädbutik för egen server

Datum: 2026-10-04. Status: designförslag för granskning, före implementation.

Detta dokument beskriver den föreslagna leveransen. Det är inte ett installationspaket eller en rapport om fungerande programvara. Ursprungliga krav finns i användarens bifogade text och gäller även när de sammanfattas här. Ingen funktion eller verifiering får räknas som färdig enbart därför att den beskrivs i designen.

## 1. Mål och avgränsning

En person utan programmeringskunskaper ska kunna administrera produkter, varianter, bilder, designers, innehåll, design, priser, frakt, ordrar och efterköpsärenden. Engångsinstallationen gäller egen Linuxserver med Docker Engine och Compose-plugin. Normal start och uppdatering ska därefter ske med exakt:

```sh
sudo docker compose pull && sudo docker compose up -d
```

Svenska, SEK och svenska konsumenter är första marknaden. Andra marknader ska vara avstängda tills villkor, skatt och frakt har konfigurerats. Demoläge ska vara tydligt märkt och aldrig kunna använda riktiga betalningsnycklar. Produktionsköp ska blockeras på servern tills lanseringskontrollen är godkänd.

Första versionen ska leverera samtliga funktioner i kravbilden. Utvecklingen delas i handelsgrund, butik/admin, import/efterköp samt drift/verifiering. Dessa är arbetsdelar av samma leverans, inte en begränsning till en prototyp.

## 2. Teknikval och alternativ

**Rekommendation: WordPress/WooCommerce, eget tema och ett avgränsat butikstillägg.** WooCommerce används för produktmodell, variationer, kundvagn, serverberäknade skatter, frakt, rabattkoder, orderrader och administrativa handelsflöden. WordPress används för innehåll, media, användare och designredigering. Detta ger en etablerad handelsgrund och omfattande administration utan att varje innehållsfunktion behöver utvecklas från början.

Ett eget tillägg implementerar Stripe Checkout, webhookjournal, lanseringsspärr, produktinformation, säker ZIP-import, verklig prishistorik, digital ångerfunktion, gäståtkomst och tillförlitliga bakgrundsjobb. Ett eget tillgängligt tema utformar klädbutiken. Stripe Checkout får inte förväxlas med ett standardtillägg som använder en annan Stripe-kassaintegration. Den föreslagna Checkout-adaptern är egen kod ovanpå Stripes officiella SDK och kräver egna integrations- och feltester.

Alternativ:

| Alternativ | Fördel | Avvägning |
| --- | --- | --- |
| WooCommerce med eget tillägg/tema | Omfattande handels- och innehållsadmin | WordPress måste härdas; egna flöden ska följa WooCommerces lager- och orderlivscykel |
| Medusa med separat serverrenderad butik | Modulär handelsgrund och stor frihet | Mer innehållsadministration och fler integrationer måste byggas för målgruppen |
| Helt egen handelsplattform | Full kontroll över modell och gränssnitt | Betydligt större ansvar för kritiska order-, lager- och betalningsfunktioner |

WooCommerce distribueras under GPL-2.0-or-later. Det egna WordPress-tillägget och temat föreslås använda samma licens. Kommersiell användning är möjlig; distributionsskyldigheter ska beskrivas och fullständiga licenser bifogas. Tredjepartspaket granskas separat i en komponentförteckning. Typsnitt levereras lokalt med verifierad licens, alternativt används systemtypsnitt. Exempelmaterial ska vara egenproducerat eller tydligt licensierat och får inte framstå som faktiska varor, recensioner eller leverantörscertifieringar.

Exakta underhållna versioner, kompatibilitet och säkerhetsstatus ska kontrolleras vid byggandet, sedan låsas i release-manifestet. Inga beroenden ska laddas ned dynamiskt vid normal start. WordPress-, tema- och pluginuppdateringar genom admin ska vara avstängda; koduppdateringar sker via testade images.

Källor: [WooCommerce-licens](https://github.com/woocommerce/woocommerce/blob/trunk/license.txt), [WooCommerce orderlivscykel](https://woocommerce.com/document/managing-orders/order-statuses/), [Medusas handelsmoduler](https://docs.medusajs.com/resources/commerce-modules).

## 3. Containerarkitektur och persistens

```text
Internet :80/:443
       |
     NGINX ---- ACME-webroot och certifikatvolym ---- certifikatjobb
       |
  applikation ---- databas
       |              |
  uppladdningar    bakgrundsjobb / migrationslås / mejlkö
```

Containrar: NGINX som enda publik ingång, applikation, MariaDB, worker, ett avslutande migrationsjobb och certifikatförnyelse. Workers använder samma release som applikationen. Databasen har inga publicerade portar. Worker ska inte vara beroende av trafik till webbplatsen för att köra jobb. Intern databasåtkomst och utgående internetåtkomst utformas så att Stripe, SMTP och ACME fungerar utan att databasen blir publik.

Kod, WordPress-kärna, paket och tema finns i immutable images. Databas och uppladdningar finns i namngivna volymer. Företagsuppgifter, produkter, innehåll, tema-inställningar och språk finns i databasen. Certifikat och ACME-data har egna beständiga volymer. Privata importfiler och dokument ska inte lagras i offentligt åtkomliga media-mappar.

Ingen fullständig kodkatalog ska döljas av en beständig WordPress-volym: det skulle kunna hindra uppdaterade images från att uppdatera koden. Ingen automatisk demoinmatning får ske vid senare omstarter. Första installationens bootstrap använder databaslås och en beständig installationsmarkör.

Migrationsjobbet tar ett exklusivt databaslås och registrerar genomförda versioner. Applikation och worker börjar ta trafik först efter lyckad migration och kompatibilitetskontroll. Misslyckad migration stoppar starten. Eftersom alla schemaoperationer inte kan återställas transaktionellt ska migrationer vara återupptagbara och använda kompatibla tillägg före destruktiva förändringar. Rollback ska ange om imagebyte räcker eller om även databasbackup krävs.

Healthchecks ska kontrollera databas, schemakompatibilitet, applikation och worker-hjärtslag. Återstartspolicy och beroenden anges i Compose. Privata köpflöden ska inte cachelagras.

## 4. HTTPS, domän och första start

Första starten får inte kräva ett befintligt certifikat. NGINX startar med en HTTP-konfiguration som endast exponerar ACME-utmaning och installationsstatus. Ingen admininloggning eller betalning ska tillåtas över okrypterad publik HTTP. Certifikatjobbet utfärdar certifikatet, validerar den nya NGINX-konfigurationen och byter därefter atomiskt till HTTPS med HTTP-omdirigering. Förnyelse utlöser säker reload, med övervakning av certifikatets utgång.

Vald huvuddomän och optional www-alias kommer från dokumenterade miljövariabler. En domän kan inte aktiveras genom enbart designinställning i admin; DNS, certifikat, canonical-URL och betalningswebhook måste också stämma. Ett adminflöde visar de steg som behövs.

DNS: A och eventuell fungerande AAAA till servern. Port 80 och 443 öppna. Certifikatutgivaren måste kunna nå ACME-utmaningen. Felaktig IPv6-post ska upptäckas i installationskontrollen. Stripe-webhook ska använda rätt HTTPS-domän och separat hemlighet för test och live.

NGINX ska ersätta klientens inkommande proxyheaders och sätta klient-IP och HTTPS-status från den egna anslutningen. Applikationen litar enbart på den interna proxyn. Okända Host-värden avvisas. Uppladdade filer får aldrig exekveras. Storleksgränser, timeout, säkerhetsheaders och en Stripe-kompatibel innehållspolicy ska testas. HSTS aktiveras efter att HTTPS fungerar och dokumenteras inför domänflytt.

## 5. Butik och designredigering

Visuell riktning: lugn, redaktionell klädbutik med stora bilder, tydlig produktinformation och diskret rörelse. Ljus och mörk profil ska kunna väljas i admin. Färger, systemtypsnitt/lokala typsnitt, loggor, favicon, navigering, banners, sektioner, kollektioner, sidfot, kontaktinformation och sociala länkar ska lagras som innehåll och inställningar.

Butiken får serverrenderade produkt-, designer- och kollektionssidor. Katalogen får sökning, sortering och kombinerbara filter för storlek, färg, kategori, pris och designer/varumärke. Varianter ska visa rätt bild, pris, SKU och tillgänglighet. Produktsidan får bildgalleri och tangentbordsåtkomlig zoom, storleksguide, material, skötselråd, leveransinformation och säkerhetsuppgifter.

Varukorg och kassa ska stödja gästköp. Företagsuppgifter, villkor, retur, frakt, FAQ och kontakt ska vara tillgängliga utan konto. Icke-konfigurerade funktioner och externa betalningsloggor ska döljas. Tomma sökresultat, saknade varianter, laddning, nekad betalning och nätverksfel ska ha tydliga texter och tillgängliga statusmeddelanden. Inga falska lagerpåståenden, recensioner eller nedräkningar får finnas.

Metadata, canonical-URL, sitemap, robots.txt och produktdata ska avspegla faktiska priser och lager. Order- och adminsidor ska ha noindex, ingen delad cache och faktisk åtkomstkontroll. Structured data får inte innehålla påhittade betyg.

## 6. Administration och säkerhet

Administratör, butikschef och innehållsredaktör ska ha separata rättigheter. Ekonomiska åtgärder, exports och hemligheter kräver uttryckliga capabilities på serversidan. Inloggning använder WordPress säkra sessionsmekanism och lösenordshashning, rate limiting, CSRF-skydd och ett versionslåst granskat tvåfaktorstöd. TOTP och återställningskoder ska fungera; extra administratör ska kunna återställa åtkomst genom dokumenterad rutin.

Bootstrap skapar administratören från en engångshemlighet utan standardlösenord. Installationshemligheten ska förbrukas och inte hamna i frontend eller loggar. TLS krävs för publikt admin. Hemligheter ligger utanför images och Git med begränsade filrättigheter. Logs ska maskera nycklar, lösenord, ordertokens och personuppgifter; kortuppgifter hanteras endast av Stripe.

Admin ska hantera SKU, varianter, priser, lager, designers, kategorier, kollektioner, kampanjer, kuponger, fraktzoner och fri-fraktgräns. Flera bilder och drag-and-drop ska stödjas med alternativtext. Ordervyn kompletteras med betalningsstatus, leveransstatus, retur, reklamation och avstämda återbetalningar. Teknisk felinformation ska skiljas från köparens begripliga felmeddelande.

## 7. Import, bilder och export

Releasepaketet får en CSV-mall och ett fungerande exempel-ZIP. Identifierare: stabilt product_id för produktgrupp och unik sku för varje säljbar variant. CSV kopplar bilder genom relativa bildnamn i ZIP och valfri variant-SKU; filnamn får inte bli godtyckliga servervägar. Priser i CSV anges med dokumenterat decimalformat och omvandlas till ören. Historiska priser importeras inte som verifierad historik utan dokumenterat stöd.

Importflöde: ladda upp, validera, visa förhandsgranskning med fältspecifika fel och dubbletter, välj skapa eller uttrycklig uppdatering, bekräfta, kör bakgrundsjobb och visa resultat. Befintliga data skrivs inte över med standardvalet. Produkten förblir utkast tills alla obligatoriska uppgifter och bilder är giltiga. En misslyckad import får inte publicera en halvfärdig produkt; filer och databasändringar hanteras genom staging och kompensation där full transaktion inte är möjlig.

ZIP-validering avvisar traversal, absoluta sökvägar, Windows-enhetsvägar, symboliska länkar, nästlade arkiv, för många filer, orimlig kompression och överskriden total uppackad storlek. Förslag till startgränser: ZIP 100 MiB, 500 filer, 500 MiB uppackat och bild 15 MiB/40 megapixlar. Gränser ska kontrolleras även vid strömmande uppackning.

JPEG, PNG och WebP accepteras efter innehållsverifiering och full avkodning. SVG och exekverbara format avvisas som standard. Bilder kodas om, metadata tas bort, slumpmässiga lagringsnamn används och responsiva storlekar skapas. Dimension- och minnesgränser skyddar mot bildbomber. Originalfilnamn blir aldrig en exekverbar URL.

Produkter, relevanta inställningar, ordrar, betalningsunderlag och bilder ska kunna exporteras. Serverflytt ska även stödjas med databasdump och mediebackup. CSV-export skyddar mot kalkylbladsformler. Känsliga exports ska vara privata och tidsbegränsade.

## 8. Priser, betalning och lager

WooCommerce beräknar priser, kuponger, frakt och moms på servern med kontrollerad decimalhantering. Stripe får heltal i ören. Svensk momsprofil ska granskas och konfigureras; ingen generell momssats ska antas för varje framtida produkt eller marknad. Ordern lagrar snapshot av priser, skatt, frakt, produktinformation och villkor.

Orderstatus och betalningsstatus hålls separata. Betalningsförsök har egen identifierare, orderkoppling, Stripe-session, PaymentIntent, belopp, valuta och miljö. Webhookjournal har unikt event-ID och separat behandlingsstatus. Återbetalning har unik begäran, belopp och leverantörs-ID. Outbox har unika nycklar för mejl och efterföljande åtgärder.

Köpet reserverar lager atomiskt via WooCommerces reservationsmekanism, inom samma kontrollerade orderlivscykel. Tillägget får inte lägga ett oberoende parallellt lagersystem ovanpå kärnan. Reservationstiden föreslås vara 30 minuter och Stripe-sessionen får samma utgång. Samtidiga köp av den sista varianten måste verifieras mot riktig databas; dokumentation om stock hold är inte ett sådant test.

Checkout-session skapas från orderns serverberäknade snapshot med en beständig idempotensnyckel. En oklar nätverkstimeout får inte skapa en andra session utan avstämning. Den sista knappen ska tydligt ange betalningsskyldighet. Kunden anger aldrig kortuppgifter i WordPress.

Webhooks verifierar signaturen på rå request-body, tidsgräns, test/live-miljö, rätt Stripe-konto, orderkoppling, belopp och valuta. Händelser lagras hållbart innan lyckat HTTP-svar. Bearbetning kan återupptas efter processkrasch. Dubbletter och fel ordning ska inte orsaka dubbelt lageravdrag, dubbel order eller dubbel återbetalning. Betald status kräver verifierat betaltillstånd, aldrig enbart success-URL eller en avslutad session med obetald status.

Stripe Checkout visar bara metoder som Stripe faktiskt kan erbjuda för kontot, valuta och kund. Kort är initial integration. Klarna/Swish och fördröjda metoder aktiveras först efter fullständig verifiering av deras livscykel och handlarens behörighet. Ingen sådan metod ska marknadsföras som tillgänglig enbart på grund av en inställning.

Efter utgången reservation får en sen betalning inte automatiskt utlösa leverans. Betalningen registreras först, lagret kontrolleras under lås och ordern reserveras bara om varan fortfarande är tillgänglig. Annars skapas ett synligt undantagsärende och en idempotent återbetalningsåtgärd. Kunden informeras, och ordern får aldrig framstå som levererad eller betalningen som obefintlig. Fördröjda betalningar får en dokumenterad reservationspolicy per aktiverad metod.

Hel och partiell återbetalning valideras mot tidigare återbetalat belopp och Stripe. Status är väntande tills leverantören bekräftat resultatet. Lageråterföring sker bara vid vald och faktiskt mottagen retur, inte automatiskt för varje återbetalning. Misslyckade och oklara API-resultat avstäms av worker. Kontrollerade jobb frigör utgångna reservationer och stämmer av missade betalningshändelser.

Ordermejl skapas via outbox efter commit och får återförsök med backoff och synlig fellista. Exakt en SMTP-leverans kan inte garanteras vid ett avbrott efter att servern accepterat meddelandet; ett stabilt Message-ID och en leverantör med stöd för deduplicering används där det är möjligt. Denna gräns ska finnas i verifieringsrapporten och inga felaktiga garantier om exakt en mejlleverans ska lämnas.

Tekniska källor: [Stripe Checkout fulfillment](https://docs.stripe.com/checkout/fulfillment), [WooCommerce lager och order](https://woocommerce.com/document/managing-orders/order-statuses/).

## 9. Gäståtkomst och efterköp

Orderuppföljning använder en tillräckligt stark slumpmässig gästreferens, lagrad som hash, alternativt tidsbegränsad engångslänk skickad till orderns e-post. Svar och rate limiting ska motverka order- och e-postenumerering. Tokens får inte hamna i analytics, referer, URL-loggar eller delad cache. Gäståtkomst avslöjar endast den aktuella ordern.

Digital ångerfunktion finns tydligt i sidfoten och efterköpsinformationen och kräver inte ett skapat konto. Kunden kan ange namn, identifiera avtalet och ange/bekräfta mottagningsadress för varaktigt mottagningsbevis. En separat bekräftelseknapp skickar meddelandet. Mottagning lagras hållbart med innehåll och exakt tid, och mottagningsbevis läggs omedelbart i mejlkön. Ett nedladdningsbart bevis erbjuds efter inskickandet.

Ångermeddelanden får inte blockeras därför att gäståtkomsttoken saknas eller för att en automatisk matchning misslyckas. De ska kunna tas emot för manuell matchning utan att andra kunders orderinformation lämnas ut. Ångerfrist för kläder knyts normalt till mottagandet av varan, inte köpdatumet. Delade leveranser och bristande information kan förändra beräkningen och måste hanteras. Mottagning av ett meddelande är inte samma sak som ett beslut om återbetalning.

Returnummer, mottagna artiklar, skick och reklamation dokumenteras i admin. Reklamation och ånger är olika ärendetyper. Bokföringsexport anger order, skatt, frakt, leverantörsbetalning, partiella återbetalningar och valuta. Den är underlag för bokföring, inte ett komplett bokföringssystem.

## 10. Juridiskt kontrollunderlag

Kontrolldatum för nedanstående förstudie: 2026-10-04. Fullständig rättslig kontroll och koppling mellan varje bestämmelse och implementation återstår. Redigerbara villkor är underlag som måste anpassas till det faktiska företaget och arbetssättet. Ingen garanti om full juridisk efterlevnad ska lämnas.

| Område | Kontrollerad källa och slutsats för designen | Återstående kontroll |
| --- | --- | --- |
| Ånger och distansavtal | [Distansavtalslagen](https://www.riksdagen.se/sv/dokument-och-lagar/dokument/svensk-forfattningssamling/lag-200559-om-distansavtal-och-avtal-utanfor_sfs-2005-59/), 2 kap. 10–10 a §§: normalt 14 dagar; digital funktion och varaktigt mottagningsbevis. [Riksdagens beslut](https://www.riksdagen.se/sv/dokument-och-lagar/dokument/betankande/ett-starkt-konsumentskydd-vid-distansavtal_hd01cu11/) anger ikraftträdande 19 juni 2026. | Informationskrav, fristens start, återbetalningsfrister, villkorssnapshot och undantag ska mappas mot flöden. Vanliga kläder och standard-POD undantas inte generellt. |
| Prissänkningar | [Konsumentverket](https://www.konsumentverket.se/marknadsratt-foretag/prissankningar-regler-for-foretag/): tidigare lägsta pris under 30 dagar och relevant jämförelsegrund. Verklig historik måste registreras; historik får inte fabriceras. | Variantpriser, allmänna kampanjkoder, nya produkter och successiva sänkningar ska få separata testfall och regler. |
| Reklamation | [Konsumentköplagen, myndighetsvägledning](https://www.konsumentverket.se/marknadsratt-foretag/konsumentkoplagen-for-foretag/). Separat reklamationsflöde och aktuella uppgifter om ARN. | Exakta frister, ansvar och aktuella ARN-villkor/länkar ska kontrolleras vid implementation. Ingen gammal EU-ODR-länk läggs in. |
| Tillgänglighet | [Lag 2023:254](https://www.riksdagen.se/sv/dokument-och-lagar/dokument/svensk-forfattningssamling/lag-2023254-om-vissa-produkters-och-tjansters_sfs-2023-254/) omfattar e-handel och innehåller mikroföretagsundantag för tjänster. | Handlarens faktiska tillämplighet dokumenteras. WCAG 2.2 AA är tekniskt mål även när undantag gäller; rättslig standardbedömning hålls separat. |
| GDPR | [IMY:s grundläggande principer](https://www.imy.se/verksamhet/dataskydd/det-har-galler-enligt-gdpr/grundlaggande-principer/). Dataminimering, ändamål och lagringsbegränsning styr designen. | Avtal, rättslig förpliktelse och eventuellt berättigat intresse prövas per behandling. Biträdesavtal, leverantörsroller och tredjelandsöverföringar beror på valda konton. |
| Bokföringsbevarande | [BFN](https://www.bfn.se/fragor-och-svar/arkivering/): räkenskapsinformation bevaras sju år efter kalenderåret då räkenskapsåret avslutades. | Klassificera vilka uppgifter som verkligen är räkenskapsinformation; övriga uppgifter får kortare gallringstid. |
| GPSR och textil | Officiella hänvisningar: [EU 2023/988](https://eur-lex.europa.eu/eli/reg/2023/988/oj) och [EU 1007/2011](https://eur-lex.europa.eu/eli/reg/2011/1007/oj). Modell planeras för fibersammansättning, identifiering, tillverkare, EU-ansvarig aktör och varningar. | EUR-Lex fulltext blockerades av robotkontroll under denna läsning. Bestämmelserna har därför inte fullständigt verifierats här. Det ska lösas innan juridisk kontroll markeras färdig. |

Endast nödvändiga cookies ska finnas som standard. Statistik/marknadsföring är avstängd och eventuella framtida scripts ska blockeras före aktivt samtycke. Neka och acceptera ska vara lika lätta, med återkallelse. Nyhetsbrev får separat frivilligt val. Köp får inte kräva samtycke till integritetspolicyn.

Registerutdrag, rättelse och radering ska finnas som administrativa flöden med verifierad identitet. Radering ska skilja onödiga personuppgifter från lagstadgade bokföringsuppgifter. Gallring omfattar även jobb, logs och backuprotation. Juridiska spärrar ska kunna stoppa gallring för pågående ärenden. Integritetstexten ska beskriva behandling, ändamål, rättslig grund, bevarandetid, leverantörer och överföringar.

## 11. Lanseringskontroll

Obligatoriska kontroller: företagets namn, organisationsuppgifter, adress och kontaktvägar; tillämplig moms; frakt och returadress; granskade och publicerade villkor, integritetstext, ångerblankett och fungerande digital ånger; produkt- och säkerhetsinformation; säker admin med 2FA; live-Stripe med verifierad webhook; verifierad transaktionsmejl; HTTPS; backup och genomförd återställningsövning.

Status ska visas begripligt i admin med direktlänk till respektive inställning. Maskinellt kontrollerbara punkter kontrolleras maskinellt. Juridiska och verksamhetsberoende bedömningar kräver registrerad ansvarig bekräftelse och dokumentation; ett ifyllt textfält är inte bevis på rättslig efterlevnad. Produktionsspärren gäller både vanlig kassa, direkt-API och försök att betala gamla ordrar. Efterföljande betalningswebhooks och återbetalningar får fortfarande behandlas om nya köp spärras.

## 12. Release, CI och drift

Leveransstruktur: källkod för tema/tillägg, låsta beroenden, Dockerfiles, compose.yaml, .env.example, NGINX/HTTPS-konfiguration, importsamples, tester, svensk README, licenser, legal-underlag, driftmanual och verifieringsrapport.

CI kör kodanalys, enhets-, integrations- och webbläsartester. Därefter byggs och publiceras versionsmärkta linux/amd64- och linux/arm64-images med manifest, komponentförteckning och kontrollsummor. Varje release väljer kontrollerade imageversioner/digests. Applikationsimages ska inte ha påhittade registryadresser eller använda latest. Release-ZIP får versionsnummer och SHA-256.

Publicering kräver ett faktiskt repository/containerregistry med skrivrättigheter. Om det inte finns ska fungerande bygg- och publiceringsflöde levereras och publicering markeras kvarstående. Då är pull/up-kravet ännu inte uppfyllt. Detta är ett uttryckligen tillåtet undantag i användarens krav och får inte beskrivas som en verifierad installation.

Backup ska frysa skrivningar på ett kontrollerat sätt, dränera relevant arbete och ta konsistent databasdump tillsammans med bilder och konfiguration. Backuper ska krypteras, få retention och kunna kopieras till separat plats. Återställning verifieras genom faktisk start, orderavstämning och hashkontroll av bilder. Ett lyckat backupkommando räcker inte som återställningstest. Rollback inför schemabrytande release kräver databas och media från kompatibel backup; dokumentationen ska förklara risken för senare orderdata.

Engångsinstallation: installera Docker/Compose, packa upp release, skapa säkra hemligheter och admin, konfigurera DNS/domän, Stripe och e-post, kör det exakta kommandot, slutför lanseringskontrollen. Daglig produkt- och designadministration sker därefter i admin utan imagebygge.

## 13. Verifieringsstrategi och ärlig rapportering

| Testgrupp | Bevis som krävs |
| --- | --- |
| Import/publicering | CSV+ZIP till verklig databas; varianter och bilder; uttryckligt uppdateringsval; dubbletter; rollback vid fel |
| Pris/kassa | Variantpris, kupong, skatt, frakt, avrundning och manipulerade klientbelopp |
| Lager | Två samtidiga köp av sista artikeln; exakt en lyckad reservation; frigivning och sen betalning |
| Stripe | Testkonto: lyckad, nekad, avbruten och fördröjd betalning; fel signatur, miljö, valuta och belopp |
| Idempotens | Upprepade/felordnade events, processkrasch mellan mottagning och effekt, full/partiell återbetalning |
| Efterköp | Ordermejl, återförsök, varaktiga villkor; ånger utan konto, mottagningstid och innehåll i bevis |
| Säkerhet | Rollgränser, sessionsskydd, 2FA, traversal, symlänkar, ZIP/bildbomber och privata exports |
| Tillgänglighet/cookies | Tangentbord, fokus, variantval, fel, kontrast, skärmläsarprov; ingen otillåten tracking före samtycke |
| Drift | Ren Linuxinstallation med pull/up, första HTTPS utan certifikat, omstart, versionsuppdatering, backup och verklig restore |
| Persistens | Före/efter: produkter, ordrar, inställningar och bildhashar; ingen återinmatning av demo |

Rapporten ska ange exakt kommando, datum, miljö, release, exitstatus och relevant resultat. Statusvärden: körd/godkänd, körd/underkänd, inte körd och kräver externt konto/infrastruktur. Testfiler och planerade CI-jobb är inte körda tester. Simulerade Stripe-svar är inte verifiering med Stripe-konto.

Aktuell miljöundersökning: arbetsmappen är tom bortsett från work/outputs; inget Git-repository hittades. Node, Python och Git finns på PATH. Docker och PHP hittades inte på PATH. Ingen Linuxinstallation, imagepublicering, betalning eller återställning har körts.

## 14. Engångsuppgifter och kostnader

| Beroende | Krav | Kostnadsunderlag |
| --- | --- | --- |
| Linuxserver och domän | Serveråtkomst, DNS och öppna portar | Leverantör väljs vid installation; ingen exakt offert finns ännu |
| Containerregistry och CI | Repository samt publiceringsbehörighet | Kan bero på offentlig/privat lagring, trafik och CI-minuter; kontoplan behöver kontrolleras |
| Stripe | Verifierat företagskonto, test/live-hemligheter och webhook | [Svensk prislista](https://stripe.com/se/pricing): vid kontroll 1,5 % + 1,80 kr för standardkort från EES; andra kort/metoder kan kosta mer. Ursprungliga behandlingsavgifter återbetalas normalt inte vid återbetalning. |
| Transaktionsmejl | SMTP/API-konto, verifierad avsändardomän, SPF/DKIM/DMARC | Leverantör och volym måste väljas; templaten ska inte kräva en viss leverantör |
| Frakt | Fraktpriser och leveransrutiner | Manuell fraktbokning räcker initialt. Transportörs-API aktiveras bara med fungerande konto/integration; fraktkostnader tillkommer |
| Backup | Separat lagringsplats och nyckelhantering | Beror på datamängd, retention och vald lagring |

Inga live-konton, riktiga hemligheter eller imageadresser ska fabriceras. Saknad extern åtkomst ska inte stoppa källkod, lokala tester och dokumentation som kan färdigställas utan åtkomsten, men den påverkar vilka slutkrav som faktiskt kan godkännas.

## 15. Beslut före implementation

Designförslaget är WooCommerce med eget tema och tillägg enligt ovan, samtliga ursprungliga funktionskrav och tydligt redovisade externa verifieringssteg. Implementationsplan med exakta arbetsuppgifter ska skrivas efter godkänd systemdesign. Ingen produktkod har skrivits i denna fas.
