## xendit-magento-payment-module
Xendit PG integration plugin with Magento 2.x
- [Setup on Magento](#setup-on-magento) = setup if you want to use this module
- [Development](./DEV.md) = see this file if you want to contribute

## Setup on Magento
### System requirements
This module has been tested against the following tech stacks:

| Magento Version | OS | Database | PHP | Web Server |
| --- | --- | --- | --- | --- |
| 2.4.x| Ubuntu 18.04.2 LTS | MariaDB 10.1.39 | 8.x | Apache 2.4.37 |
| 2.4.0 | Ubuntu 18.04.2 LTS | MariaDB 10.1.39 | 7.4.1 | Apache 2.4.37 |
| 2.3.2 | Ubuntu 18.04.2 LTS | MariaDB 10.1.39 | 7.2.12 | Apache 2.4.37 |
| 2.2.5 | Debian GNU/Linux 9 | MariaDB 10.3 | 7.0.33 | Apache 2.2 |
| 2.1.18 | Debian GNU/Linux 9 | MariaDB 10.3 | 7.0.33 | Apache 2.4 |

### How to
## A. Installation using Composer

Magento® 2 use the Composer to manage the module package and the library. Composer is a dependency manager for PHP. Composer declare the libraries your project depends on and it will manage (install/update) them for you.

### Check Composer Status

Check if your server has composer installed by running the following command:

``composer –v``

If your server doesn’t have the composer install, you can easily install it. https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx

### Install using Composer

1. Run your SSH Console to connect to your Magento® 2 store
2. Locate the root of your Magento® 2 store.
3. Enter the command line in your Root and wait as composer will download the extension for you:

`composer require xendit/m2invoice`

When it’s finished you can activate the extension, clean the caches and deploy the content in your Magento® environment using the following command line;
- `php bin/magento module:enable Xendit_M2Invoice`
- `php bin/magento setup:upgrade`
- `php bin/magento cache:clean`

If Magento® is running in production mode, deploy the static content:

`php bin/magento setup:static-content:deploy`

After the installation. Go to your Magento® admin portal, to `Stores > Configuration > Sales > Payment Methods > Xendit`.

### Update through Composer

1. Run your SSH Console to connect to your Magento® 2 store
2. Locate the root of your Magento® 2 store.
3. Enter the following command line and wait as composer will download the update:

`composer update xendit/m2invoice`

4. Enter following commands to start Magento® update process:
- `php bin/magento setup:upgrade`
- `php bin/magento cache:clean`

5. If Magento® is running in production mode, deploy the static content:

`php bin/magento setup:static-content:deploy`

## B. Install via CLI

To install this plugin, you can either manually copy plugin files into your store's webserver and enable it:
1. Download and unzip plugin source code
2. Copy the inner `Xendit` folder into your `MAGENTO_DIR/app/code` directory on your store's webserver. You may not have the `code` folder by default, you can proceed to create it manually.

Or, you can use get our [free plugin](https://marketplace.magento.com/xendit-m2invoice.html) from Magento marketplace, and install it via composer:
1. From the `MAGENTO_DIR`, execute `composer require xendit/m2invoice`
2. Enter your authentication keys. Public key is your Magento marketplace's username, your private key is your password.
3. Wait until Composer finished updating the dependencies.

After the code is inside the `MAGENTO_DIR`, proceed to run these commands:
1. From `MAGENTO_DIR`, run these commands:
   1. `php bin/magento module:status`. You should see `Xendit_M2Invoice` on list of disabled modules.
   2. `php bin/magento module:enable Xendit_M2Invoice`
   3. `php bin/magento setup:upgrade`
   4. Run `php bin/magento module:status` again to ensure `Xendit_M2Invoice` is enabled already.
   5. You should flush Magento cache by using `php bin/magento cache:flush`
   6. Compile Magento with newly added module by using `php bin/magento setup:di:compile`
   7. After finished compiling, run `php bin/magento setup:static-content:deploy -f`
   8. Then flush the cache again with `php bin/magento cache:flush`
2. You can see Xendit's setting page by navigating to **Stores -> Configuration -> Sales -> Payment Method**
3. Once you enable Xendit on the setting page, you should see Xendit's payment methods (credit card and bank transfer) on payment section during checkout flow.

## C. Install via marketplace

### Automatic Order Cancellation
If the Xendit payment links expired, it will auto cancel the order via the webhook.

## Supported Payment Method
- Credit and Debit Card
  - Installment
  - Subscription
- Virtual Accounts
  - BCA
  - BJB
  - BNI
  - BRI
  - BSI
  - Mandiri
  - Permata
  - CIMB
- Retail Outlets
  - Alfamart
  - Indomaret
  - 7-Eleven (PH)
  - ECPay Loan (PH)
  - ECPay School (PH)
  - Cebuana (PH)
  - M Lhuillier (PH)
  - Palawan Express Pera Padala (PH)
  - LBC (PH)
- eWallets
  - OVO
  - DANA
  - LinkAja
  - QRIS
  - ShopeePay
  - GrabPay (PH)
  - GCash (PH)
  - PayMaya (PH)
  - JeniusPay
- PayLater
  - Kredivo
  - BillEase (PH)
  - Cashalo (PH)
  - Uangme
  - AstraPay
  - Akulaku
- Direct Debit
  - BRI
  - BPI (PH)
  - UBP (PH)
  - ChinaBank (PH)
  - BDO (PH)

## Refund Support
Since v1.3.0, online refund (full and partial) is supported for payment through credit and debit card channel.

## Multishipping Support
Since v2.0.0, multishipping checkout is supported for all payment methods.

## Installment & Subscription
Since v2.2.0, merchant can setup installment & subscription payment methods via credit card.

## Unit Testing
To run unit test, run this command from you `MAGENTO_DIR`:

`php bin/magento dev:tests:run unit`

Currently, haven't found out a way to run tests outside Magento environment.

## Ownership

Team: [TPI Team](https://www.draw.io/?state=%7B%22ids%22:%5B%221Vk1zqYgX2YqjJYieQ6qDPh0PhB2yAd0j%22%5D,%22action%22:%22open%22,%22userId%22:%22104938211257040552218%22%7D)

Slack Channel: [#integration-product](https://xendit.slack.com/messages/integration-product)

Slack Mentions: `@troops-tpi`
