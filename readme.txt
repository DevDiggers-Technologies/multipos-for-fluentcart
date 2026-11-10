=== MultiPOS for FluentCart ===
Contributors: DevDiggers
Plugin URI: https://devdiggers.com/product/fluentcart-pos/
Author: DevDiggers
Author URI: https://devdiggers.com/
Tags: fluentcart, point of sale, pos, barcode scanner, cash register
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GNU General Public License v3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Free FluentCart POS. Sell in person from any browser, scan barcodes, take cash, print receipts, and save every sale as a real FluentCart order.

== Description ==

MultiPOS is a free **[point of sale plugin for FluentCart](https://devdiggers.com/product/fluentcart-pos/)**. It turns any browser into a cash register at an address on your own site, such as `yoursite.com/pos`, so the shop counter and the online store share one product list, one stock count, and one order history. There is no app to download and no separate POS account.

A cashier opens the POS, logs in, and lands on the sales screen. They search a product or scan its barcode, pick the customer, apply a FluentCart coupon, take the payment, and print a receipt. The sale is saved as a paid FluentCart order, so it shows up in FluentCart's Orders screen, reports, the customer's order history, and every integration that listens to FluentCart order events.

The free version runs one counter from start to finish: one outlet, unlimited cashiers, FluentCart stock and tax, barcode scanning and label printing, cash payments with change, receipts, customers, coupons, held carts, and a POS dashboard. It suits retail counters, grocery and corner shops, pickup desks, market stalls, pop-up shops, and cafes that want in-person sales inside FluentCart instead of a separate till.

No monthly fee, no external POS account, and no store data leaving your site.

= Quick Links =

* [View Product Page](https://devdiggers.com/product/fluentcart-pos/)
* [Live Demo](https://demo.devdiggers.com/multipos-for-fluentcart/)
* [Full Documentation](https://docs.devdiggers.com/multipos-for-fluentcart/)
* [Contact Support](https://devdiggers.com/contact/)
* [Upgrade to Pro](https://devdiggers.com/product/fluentcart-pos/)

= What you get in the free version =

* A point of sale screen on your own site, at a URL path you choose
* Setup wizard for the first configuration
* One outlet in grocery/retail or restaurant/cafe mode
* Unlimited cashiers with a dedicated POS Cashier role
* Product search by name, ID, SKU, or barcode, plus category filters
* Barcode scanning to add products to the cart
* FluentCart stock checked before every sale and booked when the sale is saved
* FluentCart tax for the outlet address, with prices including or excluding tax
* FluentCart coupon codes at the till, checked again on the server
* Cash payment with tendered amount and change, plus your own payment method labels
* Every sale saved as a paid FluentCart order with its outlet, cashier, and payment
* Customer search, add, edit, and delete from the POS, and a walk-in customer for quick sales
* Hold a cart and pick it up later
* Receipt printing with paper width, height, and margin settings
* Barcode label printing for one product or many at once
* POS dashboard with sales, orders, cashiers, payment methods, and recent POS orders
* POS orders and transactions screens in the admin
* Login screen text, logo, and colour settings
* POS colours, fonts, font size, corner radius, and product card layout
* Translation ready, `.pot` file included

= How it works =

After activation, **MultiPOS for FluentCart** appears under the DevDiggers menu in your WordPress admin.

1. Run the setup wizard, or open **Configuration**
2. Enable the Point of Sale and choose the POS URL path, for example `pos`
3. Fill in your outlet name and address under **Outlet**
4. Add a cashier under **Cashiers**
5. Open the POS from **Visit POS** and take your first in-person sale

Cashiers never need WordPress admin access. They log in on the POS screen itself.

== FluentCart POS features in detail ==

=== Point of sale screen for your FluentCart store ===

The POS is one sales screen built for a busy counter.

* Open the register at a custom URL such as `/pos`
* Administrators, FluentCart managers, and POS cashiers can sign in
* Browse products by category or search by name, ID, SKU, or barcode
* Add a product by tapping its card or scanning its barcode
* Change quantities or remove lines in the cart
* Hold an unfinished cart and come back to it later
* Keyboard shortcuts for the main screens
* Responsive layout for tablets, laptops, and desktop screens

Products come straight from FluentCart with their images, prices, sale prices, and categories. The free POS sells simple products. Subscription plans stay with FluentCart checkout.

=== Every POS sale is a real FluentCart order ===

In-person sales land in FluentCart, not in a separate ledger.

* Each sale creates a paid FluentCart order with the status you choose (Completed by default)
* Physical items are marked Delivered, since the customer leaves with them
* Tax is worked out by FluentCart's own tax engine for the outlet address, with the per-rate breakdown saved on the order
* Coupons are saved as FluentCart applied coupons and their use count goes up
* The outlet, cashier, payment method, and tendered amount are saved with the order
* FluentCart's usual order events run, so receipts, admin emails, FluentCRM, and other integrations work for POS sales too
* A sale sent twice after a dropped connection does not create a second order

=== FluentCart stock at the till ===

* The POS checks FluentCart stock before the order is created
* When FluentCart's Stock Management module is on, units move from available to committed, the same way FluentCart books a delivered order
* Out of stock products are not offered at the till, and items that allow backorders can still be sold
* Optional stock count on each product card

=== Barcode scanner and barcode label printing ===

* Use the product ID or the SKU as the barcode
* Scan a barcode at the POS to drop the product into the cart, with a sound on each add
* Print barcode labels from **Assign Barcodes**, one product or many at once, with a quantity per product
* Set label page size, barcode height, margins, and orientation

Any keyboard-wedge USB or Bluetooth scanner works, because the scanner types the code into the POS search box. There is nothing extra to install.

=== Cash payments and payment method labels ===

* Cash with tendered amount and automatic change
* Add your own payment method labels such as Card, UPI, or Bank Transfer to record how the customer paid
* Turn each method on or off and choose which ones the outlet offers

=== Customers and coupons at the counter ===

* Search FluentCart customers and pick one for the sale
* Add or edit a customer in the middle of a sale
* A walk-in customer is used for quick sales, or choose your own default customer
* Store staff accounts can not be edited or deleted from the POS
* Apply FluentCart coupons. The server checks each one again (status, dates, usage limits, minimum spend), so a coupon can not be used after it was switched off

=== Receipt printing for thermal printers ===

* A clean receipt with store header, order details, items, tax, and total
* Preview the receipt at real size on 58 mm, 80 mm, or A4 paper and print a test page
* Set receipt width, height, and margin to match your printer
* Reprint the receipt of any POS order from the Orders tab

=== Outlet setup for a shop counter or cafe ===

* Outlet name, address, phone, and email (the address drives FluentCart tax)
* Grocery/retail or restaurant/cafe mode
* Restaurant mode adds a Tables tab, so an order can be attached to one of your dining tables
* Mark the outlet active or inactive to open or close the register

=== POS dashboard, orders, and transactions ===

* Dashboard with sales, orders, cashiers, and top payment method
* Revenue chart and payment method breakdown, next to the latest POS orders
* POS Orders screen filtered by cashier and date, each linked to the order in FluentCart
* Transactions log with cashier, method, amount, and date
* Cashiers can see today's sales and recent orders inside the POS

=== Branding the POS ===

* Change the login heading, subtitle, footer, and button text
* Upload your logo for the POS and receipts
* Set login, POS, and button colours, font family, font size, and corner radius
* Pick a ready made colour palette and see a live preview

== MultiPOS Pro for FluentCart ==

Pro is for stores that outgrow a single counter: more branches, more ways to pay, cash control at the end of the day, or a kitchen to feed.

=== More outlets and staff control ===

* Unlimited outlets, each with its own payment methods, receipt, and tables
* Assign cashiers to outlets, with shift supervisors
* Cashier permissions for refunds, price changes, cash in and out, closing the register, and editing customers
* Manager approval with a PIN at the till, and a maximum discount per cashier
* Separate stock count per outlet, and stock at other outlets on every product card

=== Cash control and refunds ===

* Close the register with blind close, X-reports, and Z-reports
* Registers screen with every closed shift, and the Z-report emailed to the owner
* Cash in and cash out with reasons
* Refunds at the till through FluentCart's refund service, with or without restocking

=== Advanced checkout ===

* Products with variations, as options or as their own cards
* Split one order across several payment methods
* Product and cart discounts, fees and surcharges, and order notes
* Custom items that are not in the catalog, and sell by weight
* Quick Keys for best sellers, and a customer profile with spend and visits
* Offline selling that syncs when the connection is back
* Receipt editor with a live preview and a receipt per outlet
* Barcode labels with store name, product name, barcode number, SKU, and price

=== Reports, kitchen, and app ===

* Full POS reports by period, outlet, and cashier, with CSV export
* Kitchen screen for restaurant outlets
* Install the POS as a Progressive Web App

[Upgrade to MultiPOS Pro](https://devdiggers.com/product/fluentcart-pos/)

== External services ==

This plugin connects to services run by DevDiggers (https://devdiggers.com) only inside the WordPress admin, as described below.

**1. DevDiggers extensions directory**

* What it is: A read-only API on devdiggers.com that returns the public list of DevDiggers plugins.
* What it is used for: To show available DevDiggers plugins on the plugin's "Extensions" admin page.
* When data is sent: Only when a logged-in administrator opens the "Extensions" admin page. The response is cached for 24 hours.
* What data is sent: A standard HTTP request only (your server's IP address and a user-agent string). No personal or store data is sent.
* Endpoint: https://devdiggers.com/wp-json/ddwcs/v1/plugins

**2. Newsletter subscription (optional)**

* What it is: A newsletter endpoint on devdiggers.com.
* What it is used for: To add your email address to the DevDiggers newsletter, only if you choose to subscribe.
* When data is sent: Only when an administrator submits the optional newsletter form in the plugin dashboard. Nothing is sent automatically.
* What data is sent: The email address you enter and your site URL.
* Endpoint: https://devdiggers.com/

These services are provided by DevDiggers. See the DevDiggers Terms and Conditions (https://devdiggers.com/terms-and-conditions/) and Privacy Policy (https://devdiggers.com/privacy-policy/).

== Source code and build tools ==

The POS app, admin and login JavaScript and CSS are compiled from the `src/` folder with webpack and Babel. The complete, human readable source code, including all build configuration, is published in our public GitHub repository:

https://github.com/DevDiggers-Technologies/multipos-for-fluentcart

To build the assets from source:

1. Run `npm install` to install the build dependencies listed in `package.json`.
2. Run `npm run build` to compile the production assets into `assets/js` and `assets/css`.

The build configuration is in `webpack.config.js` and `babel.config.js`.

The admin screens share the bundled DevDiggers Framework. The human readable source of its compiled files in `devdiggers-framework/assets/js` and `devdiggers-framework/assets/css` (framework, dashboard, dashboard analytics, setup wizard and review notice) is in the same GitHub repository under `devdiggers-framework/src`, with its own `package.json`, `webpack.config.js` and `babel.config.js`. To rebuild them, run `npm install` and then `npm run build` inside the `devdiggers-framework` folder.

Two third party libraries used by the admin screens ship unmodified under the MIT license inside `devdiggers-framework/assets/js/`: Chart.js (https://www.chartjs.org) and Select2 (https://select2.org).

== Installation ==

**Automatic installation**

1. Make sure FluentCart is installed and active.
2. Go to **Plugins > Add New**, search for **MultiPOS for FluentCart**, then click **Install Now** and **Activate**.

**Manual installation**

1. Download the plugin zip file.
2. Go to **Plugins > Add New > Upload Plugin**, upload the zip, then click **Install Now** and **Activate**.

You can also upload the `multipos-for-fluentcart` folder to `/wp-content/plugins/` over FTP and activate it from the Plugins menu.

**After activation**

1. Follow the setup wizard, or open **MultiPOS for FluentCart > Configuration > General**.
2. Enable the Point of Sale and set the POS URL path.
3. Fill in your outlet under **Outlet**.
4. Add a cashier under **Cashiers**.
5. Open the register with the **Visit POS** link.

If the POS address shows a "page not found" error after changing the path, save your permalinks once under **Settings > Permalinks**.

== Frequently Asked Questions ==

= Is MultiPOS for FluentCart really free? =

Yes. The free version runs a full counter: one outlet, unlimited cashiers, barcode scanning and labels, cash payments, receipts, customers, coupons, held carts, and the POS dashboard. There is no trial period and nothing stops working after a while.

= Do POS sales show up as normal FluentCart orders? =

Yes. Each sale is a paid FluentCart order. MultiPOS adds the outlet, cashier, payment method, and tendered amount, but the order lives in FluentCart and behaves like any other order, in its reports and integrations too.

= Does it work with FluentCart free, or do I need FluentCart Pro? =

It works with FluentCart free. With FluentCart Pro active, Pro features that act on paid orders, such as software licenses, run for POS sales too.

= Do I need WooCommerce? =

No. MultiPOS for FluentCart needs only WordPress and FluentCart. If you run WooCommerce, see MultiPOS for WooCommerce instead.

= Do I need a Square, Stripe, or PayPal account? =

No. MultiPOS runs on your WordPress site and records sales straight into FluentCart. There is no third-party POS account and no per-sale fee from the plugin. The free version does not connect to card readers. If you take cards on a separate terminal, add a payment method label such as Card so the order records how the customer paid.

= Does it use my FluentCart stock? =

Yes. The POS checks FluentCart stock before the sale and books the sold units the way FluentCart books a delivered order. A separate stock count per outlet is a Pro feature.

= Does it use FluentCart tax settings? =

Yes. Tax is worked out by FluentCart's tax engine for the outlet address, for prices that include or exclude tax.

= Do I need a barcode scanner? =

No, but it speeds things up. Any keyboard-wedge USB or Bluetooth scanner works. You can also type the barcode, product name, or SKU into the search box.

= Can I print barcode labels for my products? =

Yes. **Assign Barcodes** prints labels for one product or many at once. The barcode is the product ID or SKU. Printing the store name, product name, SKU, and price on the label, and setting your own barcode value, are Pro features.

= Can I print receipts on a thermal printer? =

Yes. Receipts print from the browser after each sale, and you can set the paper width, height, and margin to match a 58 mm or 80 mm roll. Designing your own receipt layouts is a Pro feature.

= Which payment methods can I take at the counter? =

Cash is included, with tendered amount and change. You can add your own labels such as Card or Bank Transfer under **Configuration > Payments**. Each sale is paid with one method. Splitting one order across several methods is a Pro feature.

= How many cashiers can I add? =

As many as you like. Cashiers are WordPress users with the POS Cashier role, and administrators and FluentCart managers can use the POS too.

= Can I run more than one shop? =

The free version runs one outlet. MultiPOS Pro adds unlimited outlets with their own stock, payment methods, receipts, and cashiers.

= Which products can I sell at the POS? =

The free version sells simple FluentCart products. Products with variations, custom items, and selling by weight are Pro features.

= Can I refund a POS sale? =

Refunds made in FluentCart's own order screen work as usual. Refunding at the till, line by line, is a Pro feature.

= Does the POS work offline? =

No. The free version needs a connection to your site. Offline selling with later sync is a Pro feature.

= Can I run MultiPOS for FluentCart and MultiPOS for WooCommerce on the same site? =

Yes. They keep separate settings and tables. Both open the POS at `/pos` by default, so give one of them another address under **Configuration > General**.

== Screenshots ==

1. POS sales screen with product grid, categories, and cart
2. Customer search and the add customer form at the till
3. Payment screen with tendered amount and change
4. Printed receipt preview
5. POS dashboard with sales, payment methods, and recent orders
6. Outlet settings
7. Cashiers screen
8. Assign Barcodes screen with bulk label printing
9. General configuration

== Changelog ==

= 1.0.0 =
* First release: a free point of sale for FluentCart. One outlet, unlimited cashiers, FluentCart stock, tax and coupons, barcode scanning and labels, cash payments, receipts, customers, held carts, and a POS dashboard.
