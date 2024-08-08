## Setup Magento 2
Clone magento 2 in docker folder
```
git clone https://github.com/magento/magento2.git
cd magento2
git checkout 2.4
```

## Setup local domain
Magento 2.3
`sudo -- sh -c "echo '127.0.0.1 magento23.local' >> /etc/hosts"`

Magento 2.4
`sudo -- sh -c "echo '127.0.0.1 magento24.local' >> /etc/hosts"`

## Run
```
docker-compose up -d --build
```

App: http://magento23.local OR http://magento24.local

PHPMyAdmin: http://127.0.0.1:8080

## Access docker container
```
docker exec -it web bash
```

Go to magento folder inside docker container

```
cd app
```

Then run composer install in the root directory of Magento
```
composer install
```

## Install Magento2

Magento 2.3
```
php bin/magento setup:install \
--admin-firstname=Admin \
--admin-lastname=TPI \
--admin-email=tpi@admin.com \
--admin-user=admin \
--admin-password='abcd1234' \
--base-url=https://magento23.local \
--base-url-secure=https://magento23.local \
--backend-frontname=admin \
--db-host=mysql \
--db-name=magento \
--db-user=root \
--db-password=root \
--use-rewrites=1 \
--language=en_US \
--currency=IDR \
--timezone=Asia/Jakarta \
--use-secure-admin=1 \
--admin-use-security-key=1 \
--session-save=files \
--use-sample-data
```

Magento 2.4
```
php bin/magento setup:install \
--cleanup-database \
--admin-firstname=Admin \
--admin-lastname=TPI \
--admin-email=tpi@admin.com \
--admin-user=admin \
--admin-password=abcd1234 \
--base-url=https://magento24.local \
--base-url-secure=https://magento24.local \
--backend-frontname=admin \
--db-host=mysql \
--db-name=magento \
--db-user=root \
--db-password=root \
--use-rewrites=1 \
--language=en_US \
--currency=IDR \
--timezone=Asia/Jakarta \
--use-secure-admin=1 \
--admin-use-security-key=1 \
--session-save=files \
--use-sample-data \
--elasticsearch-host=elasticsearch
```

To include Xendit_M2Invoice module without composer, copy the inner `Xendit` folder into your `MAGENTO_DIR/app/code`
Then if you run: `php bin/magento module:status`, you should see Xendit_M2Invoice on list of disabled modules

## Setup & compile

```
1. php bin/magento setup:upgrade
2. php bin/magento setup:di:compile
3. php bin/magento cache:flush
```

## Seeding sample data (Optional)

Add reference to repo.magento.com in composer.json, this is needed if you clone repo from github.

```
composer config repositories.0 composer https://repo.magento.com
```
Note: you need repo.magento.com credentials

Install sample data:
```
php bin/magento sampledata:deploy
```

### Alternative (Non auth)

You can also get the sample data by adding several package from composer, add this package into your `composer.json` installation

```
"magento/module-bundle-sample-data": "100.4.*",
"magento/module-catalog-rule-sample-data": "100.4.*",
"magento/module-catalog-sample-data": "100.4.*",
"magento/module-cms-sample-data": "100.4.*",
"magento/module-configurable-sample-data": "100.4.*",
"magento/module-customer-sample-data": "100.4.*",
"magento/module-downloadable-sample-data": "100.4.*",
"magento/module-grouped-product-sample-data": "100.4.*",
"magento/module-msrp-sample-data": "100.4.*",
"magento/module-offline-shipping-sample-data": "100.4.*",
"magento/module-product-links-sample-data": "100.4.*",
"magento/module-review-sample-data": "100.4.*",
"magento/module-sales-rule-sample-data": "100.4.*",
"magento/module-sales-sample-data": "100.4.*",
"magento/module-swatches-sample-data": "100.4.*",
"magento/module-tax-sample-data": "100.4.*",
"magento/module-theme-sample-data": "100.4.*",
"magento/module-widget-sample-data": "100.4.*",
"magento/module-wishlist-sample-data": "100.4.*",
"magento/sample-data-media": "100.4.*"
```

and run

```
composer update
```

## Installation Troubleshoot
### Magento 2.3
If you encounter this problem when trying to install Magento inside docker
```
Fatal error: Uncaught Error: Call to undefined function xdebug_disable()
```

Go to this directory (after finished installing dependencies)
```
vendor/magento/magento2-functional-testing-framework/src/Magento/FunctionalTestingFramework/_bootstrap.php
```

Replace this line
`xdebug_disable();`

Into
```
if (function_exists('xdebug_disable')) {
        xdebug_disable();
}
```


## TODO
- [x] Create docker for magento
- [ ] Create our own image
- [x] Integrate with our plugin
- [X] Support other version
- [ ] Seeding product
- [ ] Add Xendit API Key
- [ ] Integrate with E2E testing
