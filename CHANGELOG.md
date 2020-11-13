# CHANGELOG

## 2.4.1 (2020-11-06)

Improvements:

- Add OVO phone validation
- Set default order status for Invoice methods

## 2.4.0 (2020-11-02)

Features:

- Add DANA payment method
- Add Indomaret payment method

## 2.3.0 (2020-10-06)

Features:

- Enable custom external ID

## 2.2.2 (2020-10-01)

Bugfix:

- Handle invoice paid with OVO
- Remove auto cancellation feature for now

## 2.2.1 (2020-09-28)

Improvements:

- Add `Send Email Notification` setting
- Fix invoice email format
- Disable Xendit multishipping when plugin in not enabled
- Support Sprint multishipping in Xendit's ecosystem

## 2.2.0 (2020-09-15)

Features:

- Add Credit Card Subscription payment method

## 2.1.0 (2020-09-04)

Features:

- Add Credit Card Installment payment method

## 2.0.1 (2020-06-11)

Bugfix:

- Support order ID with prefix (single and multishipping)
- Fix OVO callback for single checkout

## 2.0.0 (2020-06-01)

Features:

- Support multishipping checkout

## 1.7.0 (2020-04-03)

Improvement:

- Change OVO flow to reduce timeout case

## 1.6.2 (2020-02-24)

Bugfix:

- Modify external_id format

## 1.6.1 (2019-11-26)

Bugfix:

- Move payment complete process for OVO to notification endpoint
- Modify notification endpoint to cater both invoice and ewallet callback

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