=== webGefaehrte Membership Suite ===
Contributors: locke85
Tags: membership, checkout, lms, login, 2fa, multisite
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Membership, checkout, login, 2FA, LMS and frontend integrations for webGefaehrte sites.

== Description ==

The webGefaehrte Membership Suite packages the migrated membership, checkout, login, 2FA, LMS, admin and frontend integrations from the former GeneratePress child theme into a reusable WordPress plugin. It preserves the existing `wg_*` compatibility layer, checkout flow, CPT slugs, shortcodes, meta keys and offer-role behavior while keeping optional integrations defensive.

= Included functionality =

* Membership and checkout flow without WooCommerce.
* CF7-based multi-step checkout integration.
* Login, password reset and email 2FA helpers.
* LMS quiz, progress, points and activity sync helpers.
* GenerateBlocks, MediaElement and frontend integration layer.
* Reusable compatibility wrappers for legacy theme calls.
* Multisite-aware site-local runtime enablement.

= Important notes =

* The plugin must work without GeneratePress, GenerateBlocks or Contact Form 7 being active.
* Site-specific nonce injection such as `window.wpRestNonce` remains outside the plugin.
* Existing CPT slugs, shortcode names, meta keys and offer-role behavior are preserved for compatibility.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP package via the WordPress plugin uploader.
2. Activate the plugin in WordPress.
3. Open the Membership Suite settings and configure the site-local page IDs and CF7 form IDs.
4. Save permalinks once after activation if your site relies on custom rewrites.

== Frequently Asked Questions ==

= Does the plugin support multisite? =

Yes. It supports single-site activation, multisite activation and network activation with a site-local runtime switch.

= Which CPTs are included? =

The suite provides `wg_angebot`, `wg_bestellung` and `wg_rechnung`.

= Does the plugin delete data on deactivation? =

No. Deactivation does not delete posts, roles, options or user data.

== Changelog ==

= 1.10.0 =

* Add historical finance archive, protected analytics and reproducible dry-run imports.
* Preserve offer context across checkout steps and add controlled order-on-behalf workflows.
* Add site-local editor support for Etappenstopps, responsive frontend assets and quiz status indicators.
* Add SMTP transport settings, password-reset flow coverage and cross-site parity review support.

= 1.9.5 =

* Route configured login submissions to WordPress core without replacing the styled login page.
* Keep logout, interim login, protected posts, reauthentication and password-reset routing on their intended paths.
* Remove legacy login rewrite rules once per site so the login page and its form target remain compatible.

= 1.9.4 =

* Make the configured login fields visibly distinct from the surrounding form area.
* Apply the GeneratePress Disable Elements behaviour only on the configured login page.

= 1.9.3 =

* Prevent custom-login URL generation from recursively entering WordPress' site URL filter on PHP 8.3.

= 1.9.2 =

* When Membership 2FA is enabled, direct core login requests now receive a neutral response and direct credential posts are rejected before password authentication. Configured login URLs retain WordPress login, logout, reauthentication and password-protected-post behavior.

= 1.9.1 =

* Centralized the established Spickzettel REST, overlay-event and popup flow in the shared Etappenstopp runtime.
* Added an atomic lock for concurrent finance schema upgrades.

= 1.9.0 =
* Promote the validated Etappen and Etappenstopp meta boxes, responsive assets,
  toggle states and multi-quiz completion indicator into the shared standard.
* Promote mapped-domain checkout state, offer preselection and separated
  buyer/offer-owner order mail handling.
* Bundle the authenticated SMTP adapter and administrator-only parity review
  panel in one coherent release.

= 1.8.0 =
* Add authenticated SMTP with administrator-only one-time password capture.
* Encrypt the site-bound SMTP secret separately and never render it back.
* Add an SMTP-backed test-mail action and secret-free last-test status.

= 1.7.0 =
* Add the administrator-only Membership Suite parity review panel.
* Persist only review decisions and export a canonical, HMAC-signed decision payload.
* Keep manifests read-only and require site-specific path and site-ID configuration.

= 1.6.0 =
* Format finance amounts using the WordPress locale and the euro symbol.
* Round summary cards to whole euros while retaining cents in tables and JSON exports.
* Include three operator-confirmed 2026 Finom-era customer starts as aggregate monthly evidence without creating a parallel customer register.

= 1.5.0 =
* Replace source-heavy top-level reporting with a unified History/YTD overview.
* Reduce summary cards and move source, customer, product and methodology details into accessible accordions.

= 1.4.0 =
* Add confirmed Finom cash-inflow screen totals for August-December 2025 and 2026 YTD.
* Keep Finom cashflow separate from invoice revenue, tax, customer LTV and Membership Suite order values.

= 1.3.0 =
* Protect historical membership analytics and JSON exports with administrator-only WordPress password reauthentication.
* Add separate 2026 carry-over metrics for active subscriptions and customers originating before 2026.

= 1.2.0 =
* Added current Membership Suite orders from 2026 to the membership analytics.
* Kept historical MemberPress revenue, current order value and Finom payment semantics separate.
* Added combined commercial lifetime value, current product usage and annual source-specific order reporting.

= 1.1.2 =
* Hardened the historical-order CSV export against spreadsheet formulas.
* Renamed the existing order submenu entry to Aktuelle Bestellungen.

= 1.1.1 =
* Added a read-only historical-order list directly below Bestellungen.
* Added filters for order, invoice, customer, product, year, status and date range.
* Added an audit-logged CSV export without duplicating archived orders into the operational checkout post type.

= 1.1.0 =
* Added the append-only MemberPress history archive and guarded idempotent importer.
* Added protected analytics for registrations, historical customer revenue, activity gaps, products and extension bundle candidates.
* Added reproducible annual revenue, tax, acquisition and observed-departure reporting.
* Added audit-logged document access, controlled invoice copies and anonymized dry-run reporting.

= 1.0.0 =
* Enablement for GitHub-based distribution and repository handoff.
* Prepared plugin package metadata and release history for public source control.

= 0.9.0 =
* Updated the GenerateBlocks `{{logout_url}}` placeholder to redirect users to `/abmelden-erfolgreich/` after logout.
* Published the logout placeholder fix as the primary functional change in this package revision.

= 0.8.0 =
* Packaging refresh with no functional source-code delta compared to `0.7.0`.
* Retained the migrated REST, multisite and runtime-toggle behavior from the previous release.

= 0.7.0 =
* Added the migrated Spickzettel REST endpoint `custom/v1/spickzettel-url` to the Membership Suite runtime.
* Added the new frontend Spickzettel component to plugin bootstrap and preserved legacy access through compatibility wrappers.
* Expanded README documentation for the migrated REST endpoint and site-specific nonce handling.

= 0.6.0 =
* Packaging refresh with no functional source-code delta compared to `0.5.0`.
* Kept the multisite activation and site-local runtime controls introduced in the previous release unchanged.

= 0.5.0 =
* Added multisite-aware activation, deactivation and new-site initialization support.
* Introduced the site-local `site_enabled` runtime switch while keeping CPTs, settings and core admin functions available.
* Extended settings and config handling for site-local page IDs, checkout page IDs and network activation bookkeeping.

= 0.4.0 =
* Hardened the user-data preview flow and documented the plugin more comprehensively in the bundled README.
* Expanded the Contact Form 7 integration wrapper so CF7-dependent hooks remain defensive when the plugin is missing.
* Improved MediaElement integration behavior and frontend asset scoping for the playback-speed enhancement.

= 0.3.0 =
* Aligned Step 3 order handling, prefill user data and 2FA redirect behavior more closely with the legacy theme flow.
* Expanded compatibility wrappers for legacy global helper calls.
* Improved user-data handling for checkout-related profile fields used during final review and redirect flows.

= 0.2.0 =
* Hardened avatar upload handling with stricter validation, nonce checks and safer form processing.
* Moved primary checkout, login and password-reset settings to integer-based IDs with legacy fallback support.
* Extended checkout, pricing, order finalization and 2FA handling to stabilize the migrated purchase flow.
* Expanded compatibility wrappers and admin settings coverage for the early plugin migration phase.

= 0.1.0 =
* Initial plugin release based on the migrated webGefaehrte membership child-theme logic.
* Added reusable CPT handling for `wg_angebot`, `wg_bestellung` and `wg_rechnung`.
* Migrated checkout, pricing, order finalization and duplicate-account resolution flows.
* Migrated login, password reset, REST-based 2FA and site-local settings handling.
* Added compatibility wrappers for legacy global functions and preserved shortcode names.
* Included MediaElement playback-speed assets, GenerateBlocks helpers and frontend hardening updates.

== Upgrade Notice ==

= 1.0.0 =
Initial packaged Membership Suite release with migrated checkout, membership, login, LMS and frontend integrations.
