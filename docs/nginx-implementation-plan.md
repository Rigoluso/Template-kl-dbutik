# Körbar Docker- och NGINX-grund

Aktivt delmål 2026-10-04: säkerställ faktisk dynamisk körning bakom NGINX i Docker. Systemdesignen och den ursprungliga kompletta butiksleveransen finns kvar som krav; detta delmål ersätter inte de återstående handelsfunktionerna.

1. Kontrollera faktisk arbetsmapp och Linuxruntime. Kör en isolerad Docker Engine i WSL, utan att ersätta systemets Podman eller påverka befintliga containrar.
2. Lås tillgängliga WordPress/WooCommerce/NGINX/MariaDB-paket och kontrollera registry-manifest för amd64/arm64. Bifoga nedladdade låsta applikationsberoenden och kontrollsummor.
3. Skriv integrationstest för NGINX, dynamisk produkt, kundvagn, adminåtkomst, spärrad kassa, säker filåtkomst och persistens. Se testet misslyckas före körbar implementation.
4. Bygg appimage med kod i image och uppladdningar separat. Generera wp-config från Docker secrets, automatisera låst installation/migration, kör worker oberoende av HTTP-trafik.
5. Leverera NGINX-container, Compose, lokalt demoflöde och produktionsflöde med obligatorisk riktig imageadress. Automatisera första certifikatstart och förnyelse. Ingen påhittad publicering.
6. Kör riktig build/start, testa genom NGINX, återskapa containrar och verifiera sparad produkt/data/bild. Kontrollera NGINX syntax och relevanta säkerhetsgränser.
7. Granska ändringar separat, rätta fel och paketera källkod med svensk driftguide och verifieringsrapport. Redovisa allt som inte är testat eller återstår i den fullständiga leveransen.

Arbetet är uppdelat mellan runtimeundersökning, deployment och applikation med separata filägarskap. Rotagenten ansvarar för integration, tester och rapportering. Inget Git-repository finns i denna arbetsmapp; inget befintligt användarprojekt ändras.
