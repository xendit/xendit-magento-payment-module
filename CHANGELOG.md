# CHANGELOG

## 8.0.0 (2022-10-26)
Features:
- Add new IDR payment: LBC

## 7.0.0 (2022-10-12)
Features:
- Add new PH payment: ECPay School

## 6.0.0 (2022-09-06)
Features:
- Add new PH payment: Direct debit RCBC
- Change payment icon for PayMaya
- Reduce QRIS limit to 1 IDR

## 5.0.0 (2022-08-19)
Features:
- Add new IDR payment Akulaku

Improvements:
- Webhook update order status improvements 

## 4.0.1 (2022-08-01)
Improvements:
- Compatible with PHP 8.1.0

## 4.0.0 (2022-07-26)
Improvements:
- Add new IDR payment: AstraPay
- Published repo to Packagist

## 3.9.1 (2022-07-12)
Improvements:
- Multishipping checkout with Xendit payment improvements

## 3.9.0 (2022-06-28)
Improvements:
- Remove the CC subscription

## 3.8.0 (2022-05-09)
Features:
- Add new IDR payment: Uangme

## 3.7.0 (2022-04-06)
Features:
- Add new PH payment: ShopeePay

## 3.6.0 (2022-03-22)
Features:
- Add new PH payment: Cashalo

## 3.5.0 (2022-03-08)
Improvements:
- Code refactoring
- Update extension compatible with Magento 2.4.3

## 3.4.0 (2022-02-18)
Features:

- Add new PH payment methods:
  - 7Eleven
  - BillEase
  - Cebuana
  - Direct Debit (BPI)
  - Direct Debit (UBP)
  - ECPay Loans
  - GCash
  - GrabPay
  - M Lhuillier
  - Palawan
  - Paymaya

- Add new ID payment methods:
  - Bank Transfer - BJB
  - Bank Transfer - BSI

- Add payment icons

Improvements:
- Fix truncate decimal amount on refunding for Credit card
- Hide/Show the payment setting based on the currency

## 3.3.1 (2021-11-25)
Improvements:
- Fix rounding amount on create invoice
- Hide payment if Xendit setting disabled

## 3.3.0 (2021-11-11)
Improvements:
- Migrate QRIS (QR Codes) to XenInvoice
- Add category on create invoice

## 3.2.0 (2021-11-02)
Improvements:
- Migrate Kredivo to XenInvoice
- Data housekeeping

## 3.1.0 (2021-10-03)
Improvements:
- Add Shopeepay payment method
- Add WA and SMS notification

## 3.0.1 (2021-08-09)

Bugfix:

- Improve Order ID retrieval

## 3.0.0 (2021-03-30)

Improvements:

- Add compatibility for Magento Enterprise (Require Magento 2.3 & PHP 7.3 or above)

## 2.7.0 (2021-02-01)

Improvements:

- Move Cards Payment Method to Invoice

## 2.6.0 (2021-01-19)

Improvements:

- Standardize Error Message

## 2.5.0 (2020-12-16)

Improvements:

- Migrate OVO to using XenInvoice

## 2.4.3 (2020-12-10)

Improvements:

- Improve callback endpoint security to check order number from source of truth

## 2.4.2 (2020-11-23)

Improvements:

- Check order status before cancelling through failure endpoint

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
