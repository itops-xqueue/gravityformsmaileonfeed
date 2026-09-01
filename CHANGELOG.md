# Changelog

All notable changes to the Maileon Add-On for Gravity Forms are documented here.

## 2.0.1

- Fixed the Double Opt-In (DOI) mailing logic so it correctly matches the selected permission: DOI settings now only apply when the *effective* permission (feed-specific, or the Add-On default if the feed doesn't set one) is **not** Double Opt-In or Double Opt-In+, since those levels already represent a completed double opt-in.
- On the main Add-On settings page, the **Enable Double Opt-In mailing** and **Double Opt-In mailing key** fields are now hidden automatically when **Permission** is set to Double Opt-In or Double Opt-In+, since they no longer apply in that case. (The equivalent fields inside a feed's "Override Double Opt-In settings" section remain visible regardless of permission, but are still ignored by the fix above when the effective permission is Double Opt-In or Double Opt-In+.)
- Contacts who already have Double Opt-In or Double Opt-In+ permission in Maileon no longer receive a duplicate double opt-in email on repeat form submissions.
- Fixed an issue where a contact's permission could be silently downgraded from Double Opt-In+ to Double Opt-In on a repeat submission. Contacts who already have Double Opt-In or Double Opt-In+ permission can no longer be moved to a lower permission level by a form submission.

## 2.0

- Added the option to override the Double Opt-In settings on a per-form (feed) basis, instead of always using the Add-On's general Double Opt-In settings.

## Earlier versions

Version history prior to 2.0 is not tracked in this changelog.
