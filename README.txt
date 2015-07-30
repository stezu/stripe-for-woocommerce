=== Stripe for WooCommerce ===
Contributors: stephen.zuniga001
Tags: woocommerce, stripe, payment gateway, subscriptions, credit card, ecommerce, e-commerce, commerce, cart, checkout
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=3489V46ZRBQMC&lc=US&item_name=Thank%20You%20Coffee%20for%20Stripe%20for%20WooCommerce&currency_code=USD
Requires at least: 3.8.0
Tested up to: 4.2.2
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

A Payment Gateway for WooCommerce allowing you to take credit card payments using Stripe.


== Description ==

[Stripe](https://stripe.com/) allows you to take credit card payments on your site without having sensitive credit card information hit your servers. The problem is, it's marketed towards developers so many people don't believe they can use it, or even know how. This plugin aims to show anyone that they can use Stripe to take credit card payments in their WooCommerce store without having to write a single line of code. All you have to do is copy 2 API keys to a settings page and you're done.

= Why Stripe? =
Without getting too technical, Stripe allows you to take credit card payments without having to put a lot of effort into securing your site. Normally you would have to save a customers sensitive credit card information on a seperate server than your site, using different usernames, passwords and limiting access to the point that it's nearly impossible to hack from the outside. It's a process that helps ensure security, but is not easy to do, and if done improperly leaves you open to fines and possibly lawsuits.

If you use this plugin, all you have to do is include an SSL certificate on your site and the hard work is done for you. Credit card breaches are serious, and with this plugin and an SSL certificate, you're protected. Your customers credit card information never hits your servers, it goes from your customers computer straight to Stripes servers keeping their information safe.

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

= What now? =

Once the plugin is installed on your server, notices at the top of the screen should tell you where to go next. But just in case that doesn't work, the process is really simple to enable credit card payments on your WooCommerce store. Go to the WooCommerce settings, click the checkout tab, then click the Stripe for WooCommerce link at the top of the page. Once there, you should make sure the enable checkbox is checked, and then you just need to fill in your API key settings which can be found on your [Stripe account page](https://dashboard.stripe.com/account/apikeys). That's it.

Once you're ready to take live payments, make sure the test mode checkbox is unchecked. You'll also need to force SSL on checkout in the WooCommerce settings and of course have an SSL certificate. As long as the Live API Keys are saved, your store will be ready to process credit card payments.

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

= 1.38 =
* Fix: Saved card behaves better now
* Fix: No more console errors when submitting the checkout form
* Dev: Switched to a gulp build so it's easier for people to contribute

= 1.37 =
* Fix: First saved card was not working properly (Fix by HarriBellThomas)

= 1.36 =
* Fix: JavaScript should be more compatible on checkout pages

= 1.35 =
* Fix: WooCommerce Bookings plugin now works again
* Fix: Partial captures now allowed, don't go over the authorized amount though
* Fix: Stripe Fee wasn't showing up on captured charges
* Tweak: 'Capture' field removed from custom fields

= 1.34 =
* Feature: Stripe fee is added to order details
* Tweak: Payment gateway disabled if cart is less than 50 cents
* Tested up to 4.1

= 1.33 =
* Fix: No payments processing
* Fix: New customer payments not processing

= 1.32 =
* Fix: Fatal error string offset bug
* Tweak: User id is pulled from the order instead of the logged in user now

= 1.31 =
* Fix: Subscriptions were charging the wrong card
* Fix: Saved cards were misbehaving
* Fix: Charge errors were not giving the right messages

= 1.30 =
* Feature: Refunds! With WC 2.2, refunds were introduced and rclai on GitHub added them to this plugin
* Feature: This plugin is now a few hundred lines lighter, and will hopefully break less often
* Tweak: The transaction id is now implemented using the built-in WC 2.2 field instead of a custom field
* Tweak: Templates were reworked completely, if you were using a template before, you should revert and tweak the new one (sorry)
* Tweak: The built-in WC credit card form is being used now instead of the custom one
* Tweak: Form validation should now be better
* Dev: New filters for charge data to allow for metadata and other Stripe features
* Dev: Documentation added here: https://github.com/stezu/stripe-for-woocommerce/wiki

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
