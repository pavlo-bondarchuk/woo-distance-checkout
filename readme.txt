=== Woo Distance Checkout ===
Contributors: Pavlo Bondarchuk
Stable tag: 1.0.0
Requires at least: 5.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Tested up to: 6.4
WC requires at least: 5.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Distance-based checkout for WooCommerce with delivery and pickup options.

== Description ==

Woo Distance Checkout is a WooCommerce plugin that provides distance-based shipping calculation, delivery/pickup selection, and address-based tax handling for online stores.

## Features

* Delivery and self-pickup checkout options
* Distance-based shipping cost calculation
* Address-based tax source management
* Separate sales tax and shipping tax handling
* Admin settings interface for configuration
* Order meta persistence for tracking
* Support for future AJAX checkout recalculation
* Admin settings for API keys and formula parameters

== Installation ==

1. Upload the `woo-distance-checkout` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Distance Checkout to configure settings

== Configuration ==

### Required Settings

* Store Address: Enter your primary store address
* Google Maps API Key: Add your Google Maps Distance Matrix API key
* Tax API Key: Add your tax calculation API key

### Shipping Parameters

* Rate per Mile: Cost per mile for distance-based calculation
* Base Shipping Fee: Fixed fee applied to all deliveries
* Minimum Shipping Cost: Minimum threshold for shipping costs
* Maximum Delivery Distance: Maximum distance for delivery availability

== Development ==

This is a production-ready skeleton built with a modular architecture. Each component is separated into dedicated classes for maintainability and extensibility.

### File Structure

* `includes/` - Core plugin classes
* `admin/` - Admin interface and settings
* `assets/` - CSS and JavaScript files
* `templates/` - Checkout UI templates
* `languages/` - Translation files

=== Changelog ===

= 1.0.0 =
* Initial public release
* Added delivery and self-pickup checkout modes
* Added distance-based shipping calculation
* Added configurable shipping formula settings
* Added maximum delivery distance validation
* Added address validation with Google Maps API
* Added tax calculation support for delivery and pickup
* Added fallback modes: Block Checkout and Manual Review
* Added separate Sales Tax and Shipping Tax handling
* Added checkout notices for blocked tax state and out-of-zone delivery
* Added order meta persistence for calculation details
* Added admin order panel with Woo Distance Checkout details
* Added thank-you page warning for manual review tax fallback
* Added translation-ready notice strings and updated POT file
* Fixed duplicate out-of-zone notice on initial checkout render
* Improved checkout UX for blocked totals and tax failure states

== Support ==

For support, contact your company website.
