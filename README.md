## xendit-magento-payment-module
Xendit PG integration plugin with Magento 2.x

## Setup
### System requirements
This module has been tested against the following tech stacks:

| Magento Version | OS | Database | PHP | Web Server |
| --- | --- | --- | --- | --- |
| 2.3.0 | Ubuntu 18.04.2 LTS | MariaDB 10.1.39 | 7.2.12 | Apache 2.4.37 |
| 2.2.5 | Debian GNU/Linux 9 | MariaDB 10.3 | 7.0.33 | Apache 2.2 |

### How to
To install this plugin, manually copy plugin files into your store's webserver and enable it. Here's the detailed steps:
1. Download and unzip plugin source code
2. Copy the inner `Xendit` folder into your `MAGENTO_DIR/app/code` directory on your store's webserver. You may not have the `code` folder by default, you can proceed to create it manually.
3. From `MAGENTO_DIR`, run these commands:
   1. `php bin/magento module:status`. You should see `Xendit_M2Invoice` on list of disabled modules.
   2. `php bin/magento module:enable Xendit_M2Invoice`
   3. `php bin/magento setup:upgrade`
   4. Run `php bin/magento module:status` again to ensure `Xendit_M2Invoice` is enabled already.
   5. You should flush Magento cache by using `php bin/magento cache:flush`
   6. Compile Magento with newly added module by using `php bin/magento setup:di:compile`
4. You can see Xendit's setting page by navigating to **Stores -> Configuration -> Sales -> Payment Method**
5. Once you enable Xendit on the setting page, you should see Xendit's payment methods (credit card and bank transfer) on payment section during checkout flow.

## Ownership

Team: [TPI Team](https://www.draw.io/?state=%7B%22ids%22:%5B%221Vk1zqYgX2YqjJYieQ6qDPh0PhB2yAd0j%22%5D,%22action%22:%22open%22,%22userId%22:%22104938211257040552218%22%7D)

Slack Channel: [#integration-product](https://xendit.slack.com/messages/integration-product)

Slack Mentions: `@troops-tpi`
