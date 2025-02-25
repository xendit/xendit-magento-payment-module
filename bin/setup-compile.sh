#!/bin/bash

echo "Checking if module status"
disabled_modules=$(php bin/magento module:status | awk '/List of disabled modules:/,0' | tail -n +2)
if echo "$disabled_modules" | grep -q "Xendit_M2Invoice";
then
    echo "module is disabled, enabling module"
    php bin/magento module:enable Xendit_M2Invoice
else
    echo "module is enabled"
fi

php bin/magento setup:upgrade &&
php bin/magento setup:di:compile &&
php bin/magento cache:flush &&
php bin/magento setup:static-content:deploy -f