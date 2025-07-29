# Changelog
All notable changes to this project will be documented in this file.

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
 