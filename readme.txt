=== NextXen Pre-Order Manager for WooCommerce ===
Contributors: nextxentech
Tags: pre-order, woocommerce, pre-order products, reserve products, release date
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 7.0
WC tested up to: 10.6

Accept pre-orders in WooCommerce. Set release dates, automate order completion, send email notifications, and reserve products before launch.

== Description ==

**NextXen Pre-Order Manager for WooCommerce** lets you turn any WooCommerce product into a pre-orderable item — with a release date, automated order completion, customer emails, and flexible payment options. Start capturing revenue before your product is ready to ship.

Whether you're launching a new product line, running a made-to-order business, or building hype around a limited release, this plugin gives your store a complete, professional pre-order system.

🔗 **[Plugin Website](https://nextxentech.com/plugins/nextxen-pre-order-manager-for-woocommerce/)** | **[Documentation](https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/)** | **[Support](https://nextxentech.com/support/)**

---

= Free Features =

**Pre-Order Any Product**
Enable pre-orders on simple, variable, composite, bundle, and booking products with a single checkbox. Customers see a customizable "Pre-order now" button and an availability message.

**Release Date & Countdown**
Set an optional availability date and time. When the release date arrives, WP-Cron automatically completes all pre-orders for that product — no manual work needed.

Use the `[npom_countdown]` shortcode to embed a live countdown timer anywhere on your site.

**Two Payment Models**
- **Upfront (pay now):** Full payment collected at checkout. Order stays "Pre-ordered" until the release date.
- **Upon release (pay later):** No charge at checkout. On release, customers with a saved payment method are charged automatically; others receive a payment link by email.

**Optional Pre-Order Fee**
Add an extra charge on top of the product price (e.g., a reservation fee). It appears as a separate line item in the cart.

**Six Email Notifications**
All emails are configurable from WooCommerce → Settings → Emails:
- New Pre-Order (admin)
- Pre-Ordered Confirmation (customer)
- Pre-Order Available / Released (customer)
- Pre-Order Cancelled (customer + admin)
- Pre-Order Date Changed (customer)

**My Account — Pre-Orders Tab**
Customers can view and cancel their pre-orders from the standard WooCommerce My Account area.

**WooCommerce Blocks Compatible**
Full support for the Cart Block, Checkout Block, and Product Block Editor.

**HPOS (High-Performance Order Storage) Compatible**
Fully declared and tested with WooCommerce's custom order tables.

**Translation Ready**
All strings use the `nextxen-pre-order-manager` text domain. Compatible with WPML, Polylang, and Loco Translate.

---

= Premium Features =

Upgrade to **NextXen Pre-Order Manager for WooCommerce Premium** for advanced tools that serious store owners need:

🔒 **Deposit / Partial Payments**
Collect a fixed-amount or percentage deposit at checkout. The remaining balance is automatically charged (via a new order) when the product releases. Deposit amounts appear in the order screen, emails, and CSV export.

🔒 **Quantity Limit Per Product**
Cap the total number of pre-orders accepted. Shows a "Only X slots remaining!" urgency notice when running low. Automatically closes pre-orders when the cap is hit — even under concurrent traffic.

🔒 **Admin Dashboard Widget**
Live stats on your WordPress dashboard: active pre-order count, revenue, new/completed/cancelled this month, and a list of upcoming releases in the next 30 days.

🔒 **CSV Export**
One-click export of all pre-order data (Order ID, Status, Customer, Product, Release Date, Payment Type, Deposit, Balance, Total, Date Created). UTF-8 BOM included for Excel compatibility.

🔒 **WooCommerce Subscriptions Compatibility**
Pre-order subscription products. No charge at checkout — the subscription starts and first payment is collected on the release date. Requires WooCommerce Subscriptions 6.2.0+.

🔒 **Priority Support**
Skip the queue and get direct help from the NextXen Technology team.

👉 [View Premium Plans](https://nextxentech.com/plugins/nextxen-pre-order-manager-for-woocommerce/)

---

= Supported Product Types =

- Simple
- Variable
- Composite *(WooCommerce Composite Products required)*
- Bundle *(WooCommerce Product Bundles required)*
- Booking *(WooCommerce Bookings required)*
- Mix and Match *(WooCommerce Mix and Match required)*
- Subscription / Variable Subscription *(Premium — WooCommerce Subscriptions 6.2.0+ required)*

---

= System Requirements =

- **WordPress:** 6.0 or higher
- **WooCommerce:** 7.0 or higher
- **PHP:** 7.4 or higher

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel.
2. Go to **Plugins → Add New Plugin**.
3. Search for **NextXen Pre-Order Manager for WooCommerce**.
4. Click **Install Now**, then **Activate Plugin**.

= Manual Installation =

1. Download the plugin zip file.
2. Go to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the zip file and click **Install Now**.
4. Click **Activate Plugin**.

= After Activation =

1. Go to **WooCommerce → Settings → Pre-Orders** to configure global settings.
2. Open any product, go to the **Pre-Orders** tab, and enable pre-orders.
3. Set a release date, choose the payment model, and save the product.
4. Your product is now available for pre-order.

Full setup guide: [nextxentech.com/docs/woocommerce-pre-order-manager/](https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/)

== Frequently Asked Questions ==

= Does it work with the WooCommerce Cart/Checkout Blocks? =

Yes. The Cart Block, Checkout Block, and Product Block Editor are all fully supported.

= Does it work with HPOS (High-Performance Order Storage)? =

Yes. The plugin is declared compatible with HPOS and works with both classic postmeta and the WooCommerce custom order tables.

= Can I collect a deposit instead of the full price upfront? =

Yes, but this is a **Premium** feature. You can configure a fixed-dollar or percentage-based deposit. The remaining balance is collected automatically on the release date.

= Can I limit the number of pre-orders per product? =

Yes, this is a **Premium** feature. Set a maximum pre-order quantity per product and the plugin handles the rest — including urgency notices and automatic closure.

= What happens if a customer doesn't have a saved payment method on release (upon release mode)? =

The order moves to "Pending Payment" and the customer is sent a payment link email automatically.

= Can I change the release date after orders have been placed? =

Yes. Update the product's release date and save. All customers with active pre-orders for that product automatically receive a **Pre-Order Date Changed** email.

= Can customers cancel their own pre-orders? =

Yes. From the **My Account → Pre-Orders** tab they can cancel an active pre-order. Refunds must be processed manually from the order screen.

= Is this compatible with WooCommerce Subscriptions? =

Yes, but only in the **Premium** version. Requires WooCommerce Subscriptions 6.2.0 or later.

= Is it translation ready? =

Yes. All strings use the `nextxen-pre-order-manager` text domain and a `.pot` file is included.

= Where can I find the documentation? =

Full documentation is at [nextxentech.com/docs/woocommerce-pre-order-manager/](https://nextxentech.com/docs/nextxen-pre-order-manager-for-woocommerce/)

= Where can I get support? =

Free support is available via the [WordPress.org plugin forum](https://wordpress.org/support/plugin/nextxen-pre-order-manager/). Premium customers get priority support at [nextxentech.com/support/](https://nextxentech.com/support/).

== Screenshots ==

1. **Pre-Orders Product Tab** — Enable pre-orders, set a release date, choose payment timing, and add an optional fee — all from the standard WooCommerce product editor.
2. **Product Page (front-end)** — Customizable availability message and "Pre-order now" button replace the standard add-to-cart experience.
3. **Cart & Checkout** — Clear payment timing label shown in the cart line item; checkout button text is customizable.
4. **Manage Pre-Orders** — Full list table under WooCommerce → Pre-Orders with filtering, searching, and bulk actions.
5. **Global Settings** — Customizable button text, product messages, cart text, and more at WooCommerce → Settings → Pre-Orders.
6. **Dashboard Widget (Premium)** — Live stats and upcoming releases on your WordPress admin dashboard.
7. **My Account — Pre-Orders** — Customers can view and cancel their pre-orders from My Account.

== Source Code & Build Tools ==

The plugin's compiled JavaScript assets (in the `build/` directory) are generated from source using **webpack** and **@wordpress/scripts**.

The full source code — including all `src/` files, `package.json`, and `webpack.config.js` — is publicly available at:

**https://github.com/NextXen-Technology/nextxen-pre-order-manager-for-woocommerce**

To regenerate the compiled assets:

1. `npm install`
2. `npm run build`

== Changelog ==

= 2.0.0 — 2026-04-12 =
* New: Freemium model — core pre-order features are now free; advanced features require a Premium license.
* New: Premium — Deposit / Partial Payments (fixed amount or percentage; balance order created on release).
* New: Premium — Per-product Quantity Limit with low-stock urgency notice and automatic closure.
* New: Premium — Admin Dashboard Widget with live stats and upcoming releases.
* New: Premium — CSV Export (nonce-secured, UTF-8 BOM, Excel-compatible).
* New: Premium — WooCommerce Subscriptions compatibility (requires Subscriptions 6.2.0+).
* New: Freemius SDK integrated for license management, automatic updates, and upgrade flow.
* Improvement: Plugin action links updated — free installs show an "Upgrade to Premium" link.
* Improvement: Product tab upgrade notice shown to free users listing locked premium features.
* Fix: Subscriptions compat class always loaded (static helpers available) but only instantiated for premium.
* Fix: All premium classes safely skip loading when no license is present — no fatal errors.

= 1.0.1 — 2026-03-25 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Major update introducing a freemium model. All existing features remain free. Three new premium features added: Deposits, Quantity Limits, Dashboard Widget, and CSV Export. Update is safe — no database changes required.
