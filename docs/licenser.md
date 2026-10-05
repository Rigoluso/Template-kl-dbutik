# Licenser och komponenter

Egen kod (tema, MU-tillägg, Docker-/NGINX-/test-/driftscripts): GPL-2.0-or-later. Licenstext finns i LICENSE. Ändringar i dessa filer är en ny implementation, datum 2026-10-04.

De två medföljande PNG-demobilderna är egenproducerade schematiska plaggillustrationer, licensierade enligt GPL-2.0-or-later tillsammans med temat. De beskriver ingen faktisk vara och har inga externa bildrättigheter. Inga externa fotografier, recensioner eller certifieringar ingår. Systemtypsnitt används från köparens egen enhet; inga typsnittsfiler distribueras.

Byxikonen i SVG- och PNG-format är egenproducerad 2026-10-05 och licensierad enligt GPL-2.0-or-later tillsammans med temat.

| Komponent | Version/källa | Licens och åtkomst |
| --- | --- | --- |
| WordPress | dependencies.lock.json, officiell wordpress-image | GPL-2.0-or-later; kärnans fullständiga källkod finns i basimagen och från wordpress.org |
| WooCommerce | 11.1.2, låst vendor/woocommerce.zip | GPL-2.0-or-later; fullständig plugin-distribution med licenstext ingår i ZIP |
| WP CLI | 2.12.0, låst vendor/wp-cli.phar | MIT; officiell PHAR innehåller verktyget; källkod/notice hos github.com/wp-cli/wp-cli |
| NGINX | compose.yaml officiell nginx-image | BSD-2-Clause; notice i upstream image/source |
| MariaDB | compose.yaml officiell mariadb-image | GPLv2 och komponentberoende notices; källkod/notice från mariadb.org och imagen |
| Certbot | compose.yaml officiell certbot/certbot-image | Apache-2.0 och beroendens notices; github.com/certbot/certbot |
| PHP/Apache och imageberoenden | låst WordPress-bas | Respektive upstream-licenser; egen kod ändrar inte deras rättigheter |

Distribuera alltid denna källkod, lockfiler, notices och tillämpliga licenstexter tillsammans med egna byggen. Denna lista ersätter inte en komplett genererad SBOM av varje publicerad container; CI är förberett att generera SBOM/provenance vid publicering. Containerimages och deras systempaket följer sina respektive licenser.
