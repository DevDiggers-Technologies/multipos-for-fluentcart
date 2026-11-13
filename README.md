# DevDiggers MultiPOS for FluentCart

A free point of sale for FluentCart. Sell in person from any browser, scan barcodes, take cash, print receipts, and save every sale as a real FluentCart order.

**Contributors:** DevDiggers
**Author:** [DevDiggers](https://devdiggers.com/)
**Plugin URI:** https://devdiggers.com/product/fluentcart-pos/
**Live Demo:** https://demo.devdiggers.com/multipos-for-fluentcart/
**Documentation:** https://docs.devdiggers.com/multipos-for-fluentcart/
**Version:** 1.0.0
**License:** GPL-3.0 (https://www.gnu.org/licenses/gpl-3.0.html)

| Requirement | Minimum | Tested up to |
| --- | --- | --- |
| WordPress | 6.7 | 7.1 |
| FluentCart | 2.0 | 2.2 |
| PHP | 7.4 | n/a |

WooCommerce is not needed.

---

## Building from source

The compiled assets in `assets/js/` and `assets/css/` are built from `src/` with webpack and Babel. The source lives in this repository and is left out of the plugin zip. The DevDiggers Framework source is in `devdiggers-framework/src`.

```bash
npm install
npm run build   # production assets, then text domain replacement and i18n
npm run watch   # development build with watch
npm run build:release   # plugin zip, honours .distignore
```

---

## Repository structure

```text
.
├── functions.php            # Bootstrap: headers, Free init, framework load, activation
├── readme.txt               # WordPress.org readme
├── src/                     # POS React app, admin and login source (JS + LESS)
├── assets/                  # Compiled JS/CSS, fonts, sounds, images
├── api/                     # REST controllers (ddfcpos/v1)
├── includes/                # Admin dashboard, settings, setup wizard, front end, install
├── helper/                  # FluentCart bridge, outlet, barcode, invoice, transactions, dashboard
├── templates/               # Admin screens and the POS page
├── devdiggers-framework/    # Bundled DevDiggers admin framework (free build)
├── bin/                     # Dev scripts: build, i18n, runtime test (not shipped)
└── i18n/                    # Translation template
```

---

## What the free version does

Cashiers log in at your POS URL and land on the sales screen for your outlet. They search or scan products, pick a customer, apply FluentCart coupons, take a cash or labelled payment, and print a receipt. Each sale is a paid FluentCart order with FluentCart's own tax, stock booking, coupons, and order events.

### POS screen
- Custom POS URL, for example `/pos`
- Product search by name, ID, SKU, or barcode, and category filters
- Cart with quantity changes, held carts, and FluentCart coupons
- Cash with tendered amount and change, or one of your own payment labels
- Receipt printing and reprint from the Orders tab
- Customers: search, add, edit, delete, and a walk-in default customer
- Tables tab for restaurant/cafe outlets
- Today's sales and transactions in the Statistics tab

### Admin
- Dashboard with sales, orders, cashiers, payment methods, and recent POS orders
- One outlet (address, mode, payment methods, tables, status)
- Cashiers with the `ddfcpos_cashier` role, unlimited
- Assign Barcodes: bulk label printing from product ID or SKU
- POS Orders and Transactions screens
- Receipt preview at real size
- Configuration: General, Payments, Login, Printer, Tables, Layout
- Setup wizard

---

## Free vs Pro

| Feature | Free | Pro |
| --- | --- | --- |
| POS screen, barcode scanning, held carts | Yes | Yes |
| Sales saved as FluentCart orders with FluentCart tax, stock, coupons | Yes | Yes |
| Outlets | One | Unlimited |
| Cashiers | Unlimited | Unlimited, assigned per outlet |
| Payment methods | Cash and custom labels, one per sale | Split payments |
| Simple products | Yes | Yes |
| Variable products, custom items, sell by weight | No | Yes |
| Product and cart discounts, fees, order notes | No | Yes |
| Barcode labels from ID or SKU | Yes | Yes |
| Custom barcode values, label details | No | Yes |
| Receipt printing | One template | Receipt editor, per outlet |
| Refunds at the till | No | Yes |
| Cash in/out, close register, X and Z reports | No | Yes |
| Cashier permissions and manager PIN | No | Yes |
| Stock per outlet | No | Yes |
| Offline selling | No | Yes |
| Quick Keys, customer profile | No | Yes |
| Full reports with CSV export | No | Yes |
| Kitchen screen | No | Yes |
| Progressive Web App | No | Yes |

---

## Setup

1. Install and activate FluentCart, then this plugin.
2. Follow the setup wizard, or open **MultiPOS for FluentCart > Configuration**.
3. Enable the POS and set the URL path.
4. Fill in the outlet under **Outlet**.
5. Add cashiers under **Cashiers**.
6. Open the POS from **Visit POS**.

If the POS address 404s after changing the path, save permalinks once.

---

## Developer notes

- REST routes live under `ddfcpos/v1` and need a logged-in user with `ddfcpos_manage` (administrators and FluentCart managers) or the `ddfcpos_cashier` role: `get-products`, `get-product-categories`, `get-customers`, `get-countries-states`, `check-coupon`, `check-centralized-stock`, `manage-customer`, `delete-customer`, `create-order`, `get-orders`, `save-cashier`, `get-transactions`.
- `create-order` sells at the catalogue price, accepts one payment method, and refuses custom items and non-simple products. A `sale_key` makes a retried submit return the first order.
- The outlet is the `_ddfcpos_outlet` option with id `1`. MultiPOS Pro moves it into its outlets table on activation, so orders keep the same outlet id.
- POS orders carry the `_ddfcpos_outlet_id` and `_ddfcpos_cashier_id` order meta, plus `_ddfcpos_payment_methods` and `_ddfcpos_tendered_amount`.
- `helper/fluentcart/` is the FluentCart bridge: money in cents, tax, countries, customers, and product and order adapters.
- PHP hooks use the `ddfcpos/{area}/{name}` style, for example `ddfcpos/order/created`.
- `bin/pos-free-test.php` is a runtime check of the order flow and the free boundaries: `wp eval-file wp-content/plugins/devdiggers-multipos-for-fluentcart/bin/pos-free-test.php`.

---

## Support

- Documentation: https://docs.devdiggers.com/multipos-for-fluentcart/
- Support: https://devdiggers.com/contact/

## Changelog

### 1.0.0
- First release: a free point of sale for FluentCart with one outlet, unlimited cashiers, FluentCart stock, tax and coupons, barcode scanning and labels, cash payments, receipts, customers, held carts, and a POS dashboard.
