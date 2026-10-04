# Product and design examples

`products.csv` is a standard WooCommerce CSV example: one draft variable T-shirt with two sizes and real editable price/stock fields. It uses new `EXAMPLE-IMPORT-*` SKUs to avoid the installed demo products. Prices are SEK decimal amounts; the template defaults to Sweden and prices entered including tax. Verify tax configuration before future sales.

In admin, open **Products → Import**, choose the CSV, leave **Update existing products** unchecked and review the column mapping. Import into a disposable demo first. Inspect the draft, its two variations, price, inventory and attributes before publishing. Duplicate SKUs must be resolved deliberately. This example does not prove the requested preview-and-ZIP import feature; that feature remains outstanding.

Global attribute names are `Storlek` and `Färg` with the global flag set to `1`. Their existing internal slugs are `size` and `color`, which provide `pa_size` and `pa_color` storefront filters. Do not create duplicate `pa_storlek` attributes.

The example ZIP contains the CSV and original schematic PNGs, licensed GPL-2.0-or-later with the included license. Extract it locally. Upload the PNGs through the WordPress media library and select them on the imported product. The ZIP itself cannot be uploaded as a product-import job in this release. CSV image cells are intentionally empty; for a standard WooCommerce CSV image import, replace them with real media URLs reachable by your server. Enter descriptive alternative text in the media library. The illustrations are placeholders, not product photographs or actual supplier information.

Design settings are in **Appearance → Customize**: name, logo, favicon, colors, light/dark profile and homepage copy. WordPress menus and Pages manage navigation and page text. Product information fields include material, care, sizing and safety information. Content changes persist in MariaDB and uploads without rebuilding the image. Code changes require an image update.
