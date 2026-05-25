# Changelog
All notable changes to this project will be documented in this file.

## [2.1.0]
### Added
- Add support for MarketPay payment methods.

## [2.0.10]
### Fixed
-  Fix: Payment not captured when order status is changed to done for digital products.
-  Fix: Payment handler skipped when AltaPay Terminal ID field is empty but Sales Channel dropdown is set.
-  Fix: PHP warnings from missing custom field values.

## [2.0.9]
### Added
-  Support for sales channel–specific terminals for payment methods.

## [2.0.8]
### Fixed
-  Payment status was shown as Failed even though the order was placed successfully.
-  Add a new configuration option to enable or disable Known IP Protection for callbacks.

## [2.0.7]
### Fixed
- Fixed a Twig rendering issue in the storefront controller to restore compatibility with Shopware 6.6.9.0 and newer.

## [2.0.6]
### Added
- Provide a new configuration option to enable or disable changing the order status to `In Progress` after successful payment.
- Improve error handling for capture and refund operations, ensuring clear and accurate error messages in all cases.

## [2.0.5]
### Added
- Add option to change the `Checkout Form Style`.

## [2.0.4]
### Added
- Change Shopware order status to In Progress on successful transaction.
- Add a back button, on the payment form page.

## [2.0.3]
### Added
- Support applying surcharge fee to order payments.

## [2.0.2]
### Added
- Add support for multi/partial refund.

## [2.0.1]
### Added
- Add support for multi/partial capture.

## [2.0.0]
### Added
- Add compatibility for Shopware 6.7.x

## [1.3.2]
### Added
- Simplify order line calculations to support the new PayPal integration.
### Fixed
- Fix: Order line price mismatch caused by incorrect discount amount.
- Handle exception when the order payment fails.

## [1.3.1]
### Added
- Add compatibility for Shopware 6.6.x

## [1.3.0]
### Added
-  Automatically capture payment on Order status `Done` and refund/release on Delivery status `Returned`.
### Fixed
- Fail callback crashing issue after introduction of auto capture functionality.
  
##   [1.2.9]
### Added
- Add `transaction_info` meta data as part of gateway payment request.

## [1.2.8]
### Added
- Implement `callback notification`, `callback redirect`.
- Implement auto capture functionality.
### Fixed
- Update order transaction status as paid upon receiving captured transaction status from gateway in callbacks.
- Save correct AltaPay Transaction Payment ID upon receiving callbacks instead of upon createPaymentRequest.
- Add compensation orderline to fix mismatch between order total & orderlines.
- Round order amount to 2 decimal places & unit price to 3 decimal places.
- Enable Capture & Cancel buttons in order details AltaPay tab when payment status is authorized.
- Respond appropriately in callback notification.

## [1.2.7]
- Initial codebase from Wexo's AltaPay plugin version 1.2.7
 