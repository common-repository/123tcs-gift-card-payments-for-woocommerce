=== 123TCS Gift Card Payments for WooCommerce ===
Contributors: michelts
Tags: WooCommerce, 123TCS, Gift Cards, Payment
Requires at least: 5.7.1
Tested up to: 5.8.1
Stable tag: 1.6.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin connects your WooCommerce webshop with the 123TCS Cardbase Point Of Sale Webservice.

== Description ==

De 123TCS Gift Card payments plugin maakt het mogelijk om giftcards te accepteren in je WooCommerce webshop.

De consument kan de giftcard invullen tijdens het afrekenen in het speciaal ontwikkelde invoerveld, waar tevens het logo van de giftcard kan worden toegevoegd. Het tegoed op de giftcard zal automatisch in mindering worden gebracht.

123TCS het platform voor giftcards, (e)-vouchers- en loyalty kaarten.

TCS heeft slechts één focus en dat is de volledige automatisering van alle processen en de real-time transactieverwerking van giftcards, (e)-vouchers- en loyalty kaarten. TCS richt zich met deze dienstverlening op onder andere retailketens, branchekaarten en branche-overstijgende kaarten.

TCS streeft naar een optimale ICT dienstverlening met de beste service levels in de branche. Door de gerealiseerde koppeling met WooCommerce verzilver je nu gemakkelijk een eigen cadeaukaart in jouw webshop. 

Wil je graag met ons in contact komen voor alle mogelijkheden of heb je een vraag? Stuur dan een e-mail naar info@123tcs.com of bel naar +31(0)85-7441035

== Installation ==

Always take a backup of your db before doing the upgrade, just in case ...
It's highly recommended to test updates of WooCommerce and WooCommerce related plugins on a staging site first before applying the updates to a production site
1. Upload plugin to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure you have also installed and activated WooCommerce
4. The plugin will add the menu "TCS Gift Card Payments" in your admin menu. Here you should enter the credentials you have from 123TCS. Here you can also set the name and logo for your gift card.

== Frequently Asked Questions ==

== Screenshots ==

1. Order overview
2. Checkout page
3. Admin settings

== Changelog ==

= 1.6.0 (2021/11/02) =
* Feature: Added mechanism to retrieve future updates from self hosted server

= 1.5.0 (2021/06/16) =
* Bugfix: When using an 'open loop' gift card with a balance lower than the limit of €50,- the limit was used instead of the balance of the gift card.

= 1.4.3 (2021/05/31) =
* Updated description

= 1.4.2 (2021/05/19) =
* Bugfix: Updated variable name forgotten in 1.4.1.

= 1.4.1 (2021/05/19) =
* Bugfix: When switching between live and test mode the endpoint was not updated accordingly. Resulting in having to save twice.

= 1.4.0 (2021/05/18) =
* Feature: Diferentiate between open loop and closed loop gift cards
* Feature: Limit maximum amount to witdraw per gift card for open loop gift cards to €50,-
* Bugfix: load textdomain earlier to make translations show up in admin notices

= 1.3.0 (2021/04/28) =
* Changed CURL to WordPress HTTP API after review by WordPress Plugin Review Team

= 1.2.0 (2021/04/24) =
* Feature: Added Dutch translations

= 1.1.0 (2021/04/22) =
* Bugfix: Trying to upload a gift card resulted in a no rigths error.
* Bugfix: The amount for withdraw requests to the 123TCS server should always be larger than 0
* Feature: Added the ability to remove gift cards from the order overview on the WooCommerce checkout page

= 1.0.0 (2021/04/22) =
* Initial release for use in testing / staging environments.

== Upgrade Notice ==

= 1.5.0 (2021/06/16) =
* Bugfix: When using an 'open loop' gift card with a balance lower than the limit of €50,- the limit was used instead of the balance of the gift card.

= 1.4.2 (2021/05/19) =
* Bugfix: Updated variable name forgotten in 1.4.1.

= 1.4.1 (2021/05/19) =
* Bugfix: When switching between live and test mode the endpoint was not updated accordingly. Resulting in having to save twice.

= 1.4.0 (2021/05/18) =
* Feature: Diferentiate between open loop and closed loop gift cards
* Feature: Limit maximum amount to witdraw per gift card for open loop gift cards to €50,-
* Bugfix: load textdomain earlier to make translations show up in admin notices

= 1.3.0 (2021/04/28) =
* Changed CURL to WordPress HTTP API after review by WordPress Plugin Review Team

= 1.2.0 (2021/04/24) =
* Feature: Added Dutch translations

= 1.1.0 (2021/04/22) =
* Bugfix: Trying to upload a gift card resulted in a no rigths error.
* Bugfix: The amount for withdraw requests to the 123TCS server should always be larger than 0
* Feature: Added the ability to remove gift cards from the order overview on the WooCommerce checkout page
