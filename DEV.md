# Development using Mac Silicon
**TLDR**
- use Docker setup to easily dev

## 1. Machine Setup
1. Use Mac with Silicon
2. Install `php@8.3`
   1. Run `brew install php@8.3`
   2. Bonus: `brew install brew-php-switcher` for easy php version switching
3. Use `orbstack` for running docker containers
   1. Why? because `webdevops/php-apache-dev:8.3` doesn't work on ARM64 because of its apached ssl failing. [see this issue](https://github.com/webdevops/Dockerfile/issues/433). Weirdly, testing in orbstack works.
   2. Run `brew install orbstack`
   3. Run `Orbstack` in Application
## 2. Local Magento2.4 Setup w/ Xendit Module
1. Clone `magento2` in `./docker-magento/`
   1. Run `git clone https://github.com/magento/magento2.git`
   2. `git checkout 2.4`
   3. This will take some time
2. Add local domain
   1. Why? this is needed to match the `base-url-secure` later on magento installation.
   2. Run `sudo -- sh -c "echo '127.0.0.1 magento24.local' >> /etc/hosts"`
3. Setup magento dependencies (e.g. elasticsearch, mysql, php)
   1. `docker-compose up -d --build`
   2. Notes
      1. PHPMyAdmin URL @ `http://localhost:8080`
      2. Visit ElasticSearch @ `http://localhost:9200` to check if healthy
         1. **Note**: ran into a situation it gets stuck and needs to be restarted
      3. `web` service has volume binded from `magento2` to its `app` directory, thus `magento2` used in the docker container is the cloned `magento2` and changes are synced over.
4. Install Magento2.4
   1. Go in the docker container `docker exec -it web bash`
   2. Go to the "magento folder" by `cd app`
   3. Run the command [here](#magento-installation-command) inside container
5. Install Xendit Module
   1. Run `./bin/sync-plugin.sh`
      1. This copies over Xendit M2Invoice module to desired location for Magento module and **syncs it one way when changes are made. Useful for development**
   2. Run `php bin/magento module:status`.
      1. You should see Xendit as a disabled module
   3. Run `php bin/magento module:enable Xendit_M2Invoice` to enable.
6. Compile and Build
   1. **Note**: These steps need to be repeated when adding new modules or packages etc.
   2. Automated Step
      1. Run the script `./bin/setup-compile.sh` inside docker
   3. Manual Steps
      1. `php bin/magento cache:clean`
      2. `php bin/magento setup:upgrade`
      3. `php bin/magento setup:di:compile`
         1. **Note**: you might need to run `rm -rf generated` if you get an error that cannot overwrite `/app/generated`
      4. `php bin/magento cache:flush`
      5. `php bin/magento setup:static-content:deploy -f`
   4. **To check**
      1. Open `https://magento.local` in your browser
         1. Important to use `https` as `http` might direct you to your Mac's local apache httpd
7. Add Sample Data
   1. **Note**: this is so your store have products. useful for dev
   2. Run `composer config repositories.0 composer https://repo.magento.com` inside docker container `web` in `app` directory
      1. This will ask for credentials.
      2. Sign up for an account [here](https://commercemarketplace.adobe.com/)
      3. Go to `My Profile > Access Keys` and do proper requirements [here](https://commercemarketplace.adobe.com/customer/accessKeys/)
         1. Use `public` as username and `secret` as password
      4. Here's an [image](./docs/imgs/adobe-marketplace-accesskey.png)
   3. Run `php bin/magento sampledata:deploy`
   4. Run `php bin/magento setup:upgrade`
   5. Run `rm -rf generated && php bin/magento setup:upgrade && php bin/magento cache:clean`
   6. Visit `https://magento.local` to see the products of sample data
8. Check if Magento us running and Have xendit
   1. Open `https://magento24.local/admin/`
   2. Login username: `admin` password: `abcd1234`
   3. Navigate to `Stores (sidebar) > Configuration > Sales > Payment Methods`
   4. In the `Other Payment Methods`, you should see `Xendit`
   5. See [image](./docs/imgs/magento-admin-xendit.png)

## 3. Testing Paying Locally
1. Generate Xendit Public and Private Key
   1. Have a xendit test account
   2. Go to `Xendit Dashboard > Settings` create Private API Key
   3. Copy both public and private key
2. Set it up in Magento
   1. Go to Magento Admin
   2. Navigate to `Stores (sidebar) > Configuration > Sales > Payment Methods`
   3. Go to `Xendit` and input the public and private key
3. Setup custom callback url to receive payment notification
   1. Modify `magento2/etc/env.php` to `'MAGE_MODE' => 'developer'` instead of `default`
   2. Install a tunneling tool like [ngrok](https://ngrok.com/): `brew install ngrok`
   3. Start ngrok to tunnel your local Magento: `ngrok http https://magento24.local`
   4. Copy the HTTPS ngrok URL (e.g., `https://abc123.ngrok.io`)
   5. In Magento Admin, go to `Stores > Configuration > Sales > Payment Methods > Xendit`
   6. Set the "Custom Callback URL" field to your ngrok URL (e.g., `https://abc123.ngrok.io`)
   7. Now xendit payment links webhooks will be sent to your local development environment
4. Do test payment
   1. Open `https://magento.local`
   2. Buy anything. Add to Cart. Fill up shipping.
   3. Pick `mandiri va` and you will get redirect to Xendit Payment Link
   4. Click `Simulate Payment in the banner`
   5. After you will get redirected back, and should see a success.

## Tips
1. Run `./bin/sync-plugin.sh` to sync changes when developing
2. When you have changes on config files (e.g. xml)
   1. Run `php bin/magento cache:flush && php bin/magento setup:upgrade` then refresh browser to see changes

## TroubleShoot


## Appendix

### Magento Installation Command
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
--search-engine=elasticsearch8 \
--elasticsearch-host=elasticsearch \
--elasticsearch-port=9200
```

### Seeding Data with no Auth
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

## Magento 2.3 Installation

Local Host setup
`sudo -- sh -c "echo '127.0.0.1 magento23.local' >> /etc/hosts"`


Installation Command
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

### Magento 2.3 Setup problems
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
