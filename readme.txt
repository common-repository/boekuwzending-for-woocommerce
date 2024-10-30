=== Boekuwzending for Woocommerce ===
Tags: shipping, woocommerce, boekuwzending, buz, postnl, dpd, parcel
Requires at least: 5.0
Tested up to: 6.5.4
Stable tag: 2.3.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ship your orders with PostNL or DPD with your Boekuwzending.com account.

== Description ==

Quickly integrate the activated shipping methods within your Boekuwzending.com account for WooCommerce.
You can easily create (additional) shipping labels for your order with the shipping method chosen by your customer or shop manager.

Features

*   Let your customers choose if the order is delivered at home or at a parcel shop
*   Quickly send the order from the WooCommerce admin to your Boekuwzending.com environment
*   Easily add additional shipping labels from the WooCommerce admin
*   Check the status of your shipments from the WooCommerce admin

== Changelog ==
= 2.3.3 (2024-10-09) =
* Several bug fixes

= 2.3.2 (2024-10-09) =
* Several bug fixes

= 2.3.1 (2024-10-09) =
* Several bug fixes

= 2.3.0 (2024-10-02) =
* Removed test mode
* Added SKu support

= 2.2.0 (2022-12-21) =
* Fix issue with manually updating statuses not being persisted
* Added the "boekuwzending_parse_address_lines" filter
* Added the "boekuwzending_create_order" filter
* Added the "boekuwzending_create_shipment" filter

= 2.1.1 (2022-12-21) =
* Fix issue with all orders being marked as private address.

= 2.1.0 (2022-10-19) =
* Retrieve and display tracking status per label.
* When the customer chooses a non-Boekuwzending shipping method and you select a Boekuwzending one in the admin (matrix mode), don't charge for shipping again.
* Fix some errors when a matrix could not be retrieved.

= 2.0.5 (2022-09-23) =
* Update boekuwzending/php-sdk for API compatibility.

= 2.0.4 =
* Fix PHP 8.0+ compatibility issues.

= 2.0.3 =
* Fix HttpClient PHP 8.0 polyfill causing runtime error.

= 2.0.0 =
* Added webhook support, so orders will be marked as completed when you request a shipping label.
* Allow new (additional) labels to be created for orders.
* Prevent exporting orders which contain only virtual products.
* Fix issue when applying a refund on an order.
* Fix minimum weight restrictions, fallback to 0.1 and calculate total weight based on all order items.
* Send admin mail when syncing an order fails (optional, enabled by default).
* Added additional logging of events to order notes.
* Added additional logging of critical events to debug log.
* Don't crash on invalid credentials and more error handling.
* Updated dependencies, updated minimum PHP, WP and WC versions.

= 1.2.4 =
* Fixed issues with label creation button
* Fixed serialization errors in track&trace

= 1.2.3 =
* Fixed issue with wrong order being shipped
* Fixed issue with parsing strings to float

= 1.2.2 =
* Fixed issues with "create shipment" button not working in admin order view

= 1.2.1 =
* Fixed issues with matrices and dimension errors

= 1.2.0 =
* Bumped minimum required PHP version to 7.1

= 1.1.9 =
* Added fix for use of external library when PHP intl extension is missing

= 1.1.8 =
* Automated deploy

= 1.1.7 =
* Removed polyfill package due to error with WooCommerce intl fallback

= 1.1.6 =
* Improved authorization error handling in checkout
* Added polyfill packages for PHP Intl extension
* When installing the plugin for the first time, sync orders will be enabled by default

= 1.1.5 =
* Removed single order sync restriction

= 1.1.4 =
* Address parsing

= 1.1.3 =
* API Connection improvements

= 1.1.2 =
* Improved address parsing

= 1.1.1 =
* Added support for automated order sync

= 1.1.0 =
* Added automatic dispatch for matrix shipments

= 1.0.10 =
* Bug fixes

= 1.0.9 =
* Bug fixes

= 1.0.8 =
* Bug fixes

= 1.0.7 =
* Minor improvements

= 1.0.6 =
* fix: Missing translations

= 1.0.5 =
* feat: Added option to dispatch shipments without using matrices

= 1.0.4 =
* Minor improvements

= 1.0.3 =
* fix: Minor improvements

= 1.0.2 =
* fix: Added missing dependencies

= 1.0.1 =
* fix: Improved handling of missing or incorrect API credentials.
* feat: Added option to download multiple labels for the same shipment at once
* fix: Improved the process for linking your account

= 1.0.0 =
* First version of the Boekuwzending.com WooCommerce plugin.
