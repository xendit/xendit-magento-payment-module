# CHANGELOG

## 1.6.0 (2019-11-01)

Features:

- Add bank promo feature for credit card
- Promo will be counted on Xendit side based on Magento sales rule

## 1.5.0 (2019-10-15)

Features:

- Enable merchant to select specific payment method they want to use

## 1.4.0 (2019-10-11)

Features:

- Add new pop up payment mode for cards payment
- Add new settings to choose between form or pop up on cards payment
- Redesign settings page

## 1.3.1 (2019-09-19)

Improvements:

- Add support for Magento 2.1 version

Dependencies:

- Copied Magento's `Serialize` lib interface. This is because we use this lib and it doesn't exist on Magento 2.1

## 1.3.0 (2019-09-05)

Features:

- Add refund functionality for payment made by cards payment

## 1.2.1 (2019-08-22)

Improvements:

- Map system's failure reason to an actionable explanation for better next step for end user

## 1.2.0 (2019-08-19)

Features:

- Remove callback URL abstraction on setting page, so merchant won't need to input it on their Xendit dashboard anymore
- Change API request data interface when creating invoice

## 1.1.0 (2019-07-29)

Features:

- New payment methods:
  - OVO
  - Alfamart
  - BCA Bank Transfer
- New cron to automatically cancel expired order. Please check readme on how to activate this.

## 1.0.0 (2019-07-19)

Initial version

Features:
- Standard Xendit payment method:
  - Cards payment
  - Mandiri Bank Transfer
  - BNI Bank Transfer
  - BRI Bank Transfer
  - Permata Bank Transfer
  - Alfamart