# Changelog
All notable changes to this project will be documented in this file.

## 1.0.0
- Initial codebase from Wexo's AltaPay plugin version 1.2.7
 
## 1.0.1
### Added
- Implement `callback notification`.
### Fixed
- Update order transaction status as paid upon receiving captured transaction status from gateway in callbacks.
- Save correct AltaPay Transaction Payment ID upon receiving callbacks instead of upon createPaymentRequest.
- Add compensation orderline to fix mismatch between order total & orderlines.
- Round order amount to 2 decimal places & unit price to 3 decimal places.
- Enable Capture & Cancel buttons in order details AltaPay tab when payment status is authorized.
- Respond appropriately in callback notification.