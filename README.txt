=== Stripe for WooCommerce ===
Contributors: stephen.zuniga001
Tags: woocommerce, stripe, payment gateway, credit card, ecommerce, e-commerce, commerce, cart, checkout
Requires at least: 3.8.0
Tested up to: 3.9.2
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

= What is Stripe? =
[Stripe](https://stripe.com/) allows you to take credit card payments on your site without having sensitive credit card information hit your servers. This works by having your customers input their credit card information on your web page which is then sent to Stripe's servers via JavaScript and a unique "token" is created which you can then use to charge your customers one time. This process keeps you from having to be [PCI compliant](https://www.pcisecuritystandards.org/), and allows you to quickly process credit card payments that makes your store look more legitimate than one that only supports PayPal.

= Why does this plugin exist? =
In reality, there aren't a lot of methods you can use that provide PayPal-like convenience (and prices) for processing credit card payments directly on your site. Stripe is in the right neighborhood for developers and tech-savvy people but perhaps not for the layman, and this plugin hopes to fix that. In the Stripe interface, you have access to all kinds of information on charges, customers, and logs.

This plugin exists because the current solutions for Stripe on WooCommerce are incomplete or expensive. WooThemes made a [Stripe plugin](http://www.woothemes.com/products/stripe/) but it costs $79. [Striper](https://wordpress.org/plugins/striper/) is the plugin that I used to initally start this plugin process and while free, was lacking a few features that I thought were necessary. It also appears to have dropped out of development recently and I hope that this plugin will fill in where it left off.

= Contributing =
If you'd like to contribute, feel free to tackle a feature or fix a bug on [Github](https://github.com/stezu/stripe-for-woocommerce/) and when you're ready, send a pull request. If you'd like to get more involved than that, please e-mail me at [hello@stephenzuniga.com](mailto:hello@stephenzuniga.com).

== Installation ==

= Minimum Requirements =

* WooCommerce 2.1.0 or later

= Automatic installation =
Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of WooCommerce, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “Stripe for WooCommerce” and click Search Plugins. Once you've found our plugin you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our eCommerce plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

The plugin should automatically update with new features, but you could always download the new version of the plugin and manually update the same way you would manually install.

== Changelog ==

= 1.21 =
* Feature: Subscriptions!
* Feature: Filters for customer and charge descriptions sent to Stripe
* Tweak: Localization wasn't set up before, now if someone wants to translate, they can
* Tweak: Namespace changed from wc_stripe to s4wc
* Fix: The most recent card used is marked as default now
* Fix: Token was sometimes not created
* Fix: Paying from the pay page didn't send Stripe address details

= 1.11 =
* Tweak: Database handling is now in it's own class
* Fix: JS sometimes didn't create a token
* Fix: Users with wp_debug on fresh install had fatal errors
* Fix: Possible function redeclaration issue

= 1.1 =
* Feature: Templates! For credit card form and account page
* Feature: Button to delete all test data
* Tweak: Payment.js might not have worked consistently, should be better now
* Fix: Add an icon for the gateway
* Fix: Add description to Credit Card Page
* Fix: Inconsistencies on Customer creation

= 1.0 =
* Feature: Charge a guest using Stripe
* Feature: Create a customer in Stripe for logged in users
* Feature: Charge a Stripe customer with a saved card
* Feature: Add a card to a customer
* Feature: Delete cards from customers
* Feature: Authorize & Capture or Authorize only
