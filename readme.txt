=== Customer Tagger ===
Contributors: customer-tagger
Tags: woocommerce, customers, tags, automation
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Automatically tag WooCommerce customers (and optionally orders/products) using rule-based workflows.

== Description ==

* Custom tables for tags (`wp_ct_tags`, `wp_ct_user_tags`) and activity log
* Workflows stored as `ct_workflow` posts with JSON rules (AND/OR conditions)
* Triggers: order placed, paid, status change, user register, daily schedule, product save/stock
* Dynamic tags, scheduled expiry, delayed execution, historical backfill
* FluentCRM sync, EDD/Give hooks, webhooks, WP-CLI

== Installation ==

1. Upload the plugin folder to `wp-content/plugins/`
2. Activate through the Plugins screen
3. Ensure WooCommerce is active
4. Open **Customer Tagger** in the admin menu

== Usage ==

Define workflows under **Customer Tagger → Add Workflow**. Tags appear on user profiles and the Users list.

Public API:

* `ct_user_has_tag( $user_id, 'vip' )`
* `ct_get_user_tags( $user_id )`

WP-CLI: `wp ct run-workflow --order_id=123`, `wp ct backfill`

== Changelog ==

= 1.0.0 =
* Initial release with full workflow engine and admin UI
