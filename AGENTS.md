# Customer Tagger — Agent Guide

WordPress/WooCommerce plugin for **rule-based customer, order, and product tagging** via workflows. Local path: `wp-content/plugins/customer tagger/` (folder name has a space—quote paths in shell commands).

## Requirements

- WordPress 6.0+, PHP 7.4+
- WooCommerce required at runtime (`CT\Plugin` shows an admin notice if missing)
- Admin capability: `manage_woocommerce`
- GPLv2-or-later; follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) for PHP

## Bootstrap

| Path | Role |
|------|------|
| `customer-tagger.php` | Constants (`CUSTOMER_TAGGER_*`), autoload + `functions-api.php`, `customer_tagger_run()` |
| `includes/class-customer-tagger.php` | Legacy shell: activation hooks, text domain, `plugins_loaded` → `CT\Plugin::instance()->init()` |
| `includes/Plugin.php` | Wires post types, triggers, scheduler, REST, admin, integrations |
| `includes/autoload.php` | PSR-4 autoload for `CT\` → `includes/{Path}.php` |
| `includes/functions-api.php` | Public helpers: `ct_user_has_tag()`, `ct_get_user_tags()` |

Activator/deactivator: `includes/class-customer-tagger-activator.php`, `includes/class-customer-tagger-deactivator.php` (schema, options, Action Scheduler cleanup).

## Layout (`includes/`)

| Area | Path | Notes |
|------|------|--------|
| Database | `Database/Schema.php` | `wp_ct_*` tables |
| Storage | `Storage/*Repository.php` | Tags, workflows, activity log |
| Engine | `Engine/*` | Evaluator, actions, workflow runner, fields, dynamic tags |
| Post types | `PostTypes/` | `ct_workflow` CPT, order tag taxonomy |
| Triggers | `Triggers/` | Order, user, product hooks |
| Scheduler | `Scheduler/ActionSchedulerBridge.php` | Delayed runs, backfill |
| Admin | `Admin/` | Menu, onboarding, user profile/list UI |
| REST | `Rest/` | `ct/v1` — workflows, tags, tools (admin JS uses `ctAdmin` + nonce) |
| CLI | `CLI/Commands.php` | `wp ct …` |
| Integrations | `Integrations/` | FluentCRM, EDD, Give |
| Install | `Install/PrebuiltWorkflows.php` | Default workflows on activate |
| License | `License/Limits.php` | Pro limits hook points |

Admin assets: `assets/css/admin.css`, `assets/js/admin.js` (enqueued on any `customer-tagger*` admin screen).

`public/class-customer-tagger-public.php` remains for storefront hooks if needed; most logic lives under `CT\`.

`index.php` silencers in subfolders; `uninstall.php` drops tables, workflows, and `ct_*` options.

## Conventions

**Every PHP file:**

```php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
```

**Naming:**

- New code: `CT\` namespace classes under `includes/` matching PSR-4 paths
- Legacy wrapper classes: `Customer_Tagger_*` (bootstrap only)
- Public template API: `ct_*` functions in `functions-api.php`
- Options/meta: prefer `ct_` prefix (e.g. `ct_settings`, `ct_onboarding_complete`)
- Constants: `CUSTOMER_TAGGER_*`; enqueue assets with `CUSTOMER_TAGGER_VERSION`
- Text domain: `customer-tagger` for all user-facing strings

Escape on output; sanitize/validate on input; nonces + capability checks for forms, REST, and AJAX.

## Where to implement features

| Feature | Location |
|---------|----------|
| Admin screens / menus | `includes/Admin/AdminMenu.php`, related `Admin/*` |
| Workflow editor UI (JS) | `assets/js/admin.js` |
| Workflow rules / execution | `includes/Engine/`, `includes/Storage/WorkflowRepository.php` |
| New trigger types | `includes/Triggers/` + register in `Plugin.php` |
| REST endpoints | `includes/Rest/*` |
| Schema / migrations | `includes/Database/Schema.php`, activator |
| Third-party sync | `includes/Integrations/` |

Menu slug: `customer-tagger`; workflow editor: `customer-tagger-workflow`.

## What to avoid

- Unprefixed global functions, options, script handles, or REST namespaces
- Putting business logic in `customer-tagger.php` or `class-customer-tagger.php`—use `CT\Plugin` and services
- Committing secrets, `.env`, or local MAMP paths
- Large unrelated refactors when fixing one bug
- New Composer/npm dependencies unless the user asks
