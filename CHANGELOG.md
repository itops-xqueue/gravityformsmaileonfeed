# Changelog

All notable changes to the Maileon Add-On for Gravity Forms are documented here.

## 2.0.2

- Fixed the feed settings page staying empty on PHP 8+ when Maileon rejects the request for transaction types or custom fields (for example, an API key without permission for transactions). This caused a fatal PHP error that didn't appear in the Gravity Forms logs. The page now loads, and the affected dropdowns are just empty.
- Fixed form submissions failing on PHP 8+ when a decimal (double) transaction field received a value with a decimal comma (e.g. "12,5") or non-numeric text. The contact was created but the transaction was never sent. Decimal commas are now accepted; non-numeric values are sent as 0, as on PHP 7.4.
- Fixed transactions being rejected by Maileon ("float expected" / "integer expected") when a **float**, double or integer attribute was mapped: numbers were sent as text. Numbers are now always sent as numbers, parsed in the site's currency format (e.g. "1.234,56" for EUR). Float attributes can now also be selected in the transaction mapping, and float custom contact fields are converted too.
- Fixed fields mapped as a whole being silently left out: **Name (Full)**, **Address (Full)**, **Checkboxes (Selected)** and single checkboxes are now sent, using Gravity Forms' own formatting. **List** fields are sent as a comma-separated list instead of serialized PHP data.
- Fixed boolean fields: "No", "false", "0" and "off" are now sent as false (they were sent as true).
- Date and timestamp fields are now sent in Maileon's format (Y-m-d / Y-m-d H:i:s). Values without a date (e.g. a Time field mapped to a timestamp) are skipped and logged instead of making Maileon reject the whole transaction.
- Fixed "Add Custom Key" rows mapped to entry data (entry ID, date, IP, source URL, form title) being sent under the name "gf_custom" instead of the custom key.
- Errors from Maileon API requests (contact, custom field, transaction type and transaction calls) now reach the add-on log with the HTTP status and Maileon's message. They were previously dropped.
- Removed debug output that printed the add-on settings, including the API key, during form submission.
- Custom contact fields are no longer auto-created when the list of existing fields can't be loaded from Maileon.
- The add-on can now be installed with Composer as `xqueue/gravityformsmaileonfeed` (type `wordpress-plugin`); see the README. On Composer-managed sites it uses the Maileon API client from the site's dependencies (tested with 1.9.12 and 1.25.7), otherwise the bundled one.
- Fixed a PHP 8.2+ deprecation notice ("Creation of dynamic property … Contact::$subscribtionPage") with newer Maileon API clients.
- Still compatible with PHP 7.4 and newer.

## 2.0.1

- Fixed the Double Opt-In (DOI) mailing logic so it correctly matches the selected permission: DOI settings now only apply when the *effective* permission (feed-specific, or the Add-On default if the feed doesn't set one) is **not** Double Opt-In or Double Opt-In+, since those levels already represent a completed double opt-in.
- On the main Add-On settings page, the **Enable Double Opt-In mailing** and **Double Opt-In mailing key** fields are now hidden automatically when **Permission** is set to Double Opt-In or Double Opt-In+, since they no longer apply in that case. (The equivalent fields inside a feed's "Override Double Opt-In settings" section remain visible regardless of permission, but are still ignored by the fix above when the effective permission is Double Opt-In or Double Opt-In+.)
- Contacts who already have Double Opt-In or Double Opt-In+ permission in Maileon no longer receive a duplicate double opt-in email on repeat form submissions.
- Fixed an issue where a contact's permission could be silently downgraded from Double Opt-In+ to Double Opt-In on a repeat submission. Contacts who already have Double Opt-In or Double Opt-In+ permission can no longer be moved to a lower permission level by a form submission.

## 2.0

- Added the option to override the Double Opt-In settings on a per-form (feed) basis, instead of always using the Add-On's general Double Opt-In settings.

## Earlier versions

Version history prior to 2.0 is not tracked in this changelog.
