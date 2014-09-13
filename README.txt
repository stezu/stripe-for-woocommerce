=== Stripe for WooCommerce ===
Contributors: stephen.zuniga001
Tags: woocommerce, stripe, payment gateway, subscriptions, credit card, ecommerce, e-commerce, commerce, cart, checkout
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=3489V46ZRBQMC&lc=US&item_name=Thank%20You%20Coffee%20for%20Stripe%20for%20WooCommerce&currency_code=USD
Requires at least: 3.8.0
Tested up to: 4.0.0
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A Stripe Payment Gateway for WooCommerce featuring subscriptions, saved cards, and a simple interface.


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


== Screenshots ==

1. The standard credit card form on the checkout page.
2. The form with saved cards and an icon for card identification.
3. Saved cards displayed on the account page.
4. Changing payment method for a subscription.


== Frequently Asked Questions ==

= Does I need to have an SSL Certificate? =

Yes you do. For any transaction involving sensitive information, you should take security seriously, and credit card information is incredibly sensitive. This plugin disables itself if you try to process live transactions without an SSL certificate. You can read [Stripe's reasaoning for using SSL here](https://stripe.com/help/ssl).

= Does this plugin work with Subscriptions? =

Yes. In order to process subscription payments, you'll need to have the [WooCommerce Subscriptions plugin](http://www.woothemes.com/products/woocommerce-subscriptions/). I'm not planning on adding the subscription funcionality to this plugin because the aforementioned plugin is well worth the money.

= How do I get support or request a feature? =

If you're having trouble with this plugin in particular, the [Stripe for WooCommerce support forum](http://wordpress.org/support/plugin/stripe-for-woocommerce) is a good place to start, or you can also visit the [Stripe For WooCommerce GitHub repository](https://github.com/stezu/stripe-for-woocommerce). Please don't post your issues in both because that's annoying.

= I love this plugin, how can I help improve it? =

You're the best. The [Stripe for WooCommerce GitHub repository](https://github.com/stezu/stripe-for-woocommerce) is a great place to start. Feel free to look through the issues already reported, or add your own. If you feel like you can fix something or improve the code, feel free to send a pull request and explain what's going on and I'll be glad to merge it into the plugin.

= I have money burning a hole in my pocket, where can I send it? =

I'll take it off your hands! This plugin is completely open-source and completely free and will remain that way for it's entire life. With that said, this plugin is free and you're not required to pay a penny, but if this plugin helped you and your business and you feel it's worth some spare change, [send it here](https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=3489V46ZRBQMC&lc=US&item_name=Thank%20You%20Coffee%20for%20Stripe%20for%20WooCommerce&currency_code=USD). Thank you.

= My Question isn't on this list, what do I do now? =

Holler at the [Stripe For WooCommerce support forum](http://wordpress.org/support/plugin/stripe-for-woocommerce). Hopefully you can find your question has already been answered there, but if not open up a new thread and you'll probably get a wothwhile response.

= Is there good documentation somewhere? =

Not yet. I've been meaning to go through the code and develop some solid documentation on how to not only set it up, but also tweak and customize it to your liking. There are features that most people probably don't know about and they should, so I'm going to try and get that set up ASAP, but I'm a one-man-band so I don't necessarily have a lot of free time to devote to mind-numbing documentation.


== Changelog ==

= 1.25 =
* Feature: Error messages are now localized for translations
* Feature: Ability to delete stripe account data per individual customer
* Tweak: Deleting data now opens a confirmation to prevent accidental data deletion
* Fix: Stripe customer didn't have an email attached so Stripe couldn't send receipts
* Fix: Some templates broke the Javascript, that should be fixed now
* Dev: Move templates to /s4wc from /woocommerce-stripe
* Dev: Include filters for charge and customer descriptions

= 1.24 =
* Tweak: Saved cards are now optional
* Tweak: CC Data is saved when changing address
* Fix: Invalid card with account creation
* Fix: Switching payment methods gave validation errors
* Fix: Removed double error messages, it was obnoxious

= 1.23 =
* Feature: French translation courtesy of Malo Jaffre
* Fix: Checkout with account creation was failing
* Fix: Error with setting default cards

= 1.22 =
* Fix: New customers were returning an invalid string error
* Fix: Backend error messages were bad

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
