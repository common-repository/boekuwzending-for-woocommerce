# Contributing
Helpbestand voor ontwikkelaars.

## Setup
Na checkout:

```bash
# Install Deployer and other dependencies
$ composer install --ignore-platform-reqs

# Start Docker
$ dep docker:up

# Bootstrap WooCommerce
$ dep configure
```

## Site
Front-end: http://wordpress.boekuwzending.com:8080/  
Admin: http://wordpress.boekuwzending.com:8080/wp-admin

```bash
cd /app/sites/wordpress.boekuwzending.com/wp-content/plugins/boekuwzending-for-woocommerce
```

## Translations
* Use POEdit for Mac.
* Update the `languages/boek...commerce.pot` file from source (for `__()`, `_x()`, `_e()` calls) (HOW?).
* Note: `Plugin::translateLabelStatus()` isn't detected, it uses a variable.
* Open .po, click "Translation --> Update from POT file".
* Translate new strings (marked in yellow if you're lucky).
* Click "File --> Compile to MO" to generate a binary file.
* Copy plugin translation files into WordPress to use new translations:
    ```bash
    dep docker:bash
    cp sites/wordpress.boekuwzending.com/wp-content/plugins/boekuwzending-for-woocommerce/languages/* \
    sites/wordpress.boekuwzending.com/wp-content/languages/plugins/
    ```
## Praten met Platform
In de docker-compose.yaml staat een env-variabele om tegen het lokale platform te testen (handig voor webhooks en Shipment/Order debugging).

Gebruik hiervoor:

    php:
      environment:
        BUZ_API_URL: https://dev.api.boekuwzending.com

Zet deze hostname dan in je hostfile, en verwijs naar je NIC-adres (`ifconfig`) zodat de containers met elkaar kunnen praten.

## Releasing

* Update readme changelog
* Update the version on three places
  * In the readme.txt (Stable tag on line 5)
  * boekuwzending-for-woocommerce.php on line 7
  * src/Plugin.php on line 43 
* Tag a release when you push the tag, the Github action will start the publish workflow (.github/workflows/publish.yml)
  * git tag vX.X.X and then push the tag 

