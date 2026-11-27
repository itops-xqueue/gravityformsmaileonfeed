# Maileon Add-On for Gravity Forms

Sends Gravity Forms submissions to [Maileon](https://maileon.com): creates or updates the contact and, optionally, sends a Maileon transaction.

Requires WordPress with Gravity Forms, and PHP 7.4 or newer.

## Installation

### Zip upload

1. Download `gravityformsmaileonfeed-<version>.zip` from the [latest release](https://github.com/itops-xqueue/gravityformsmaileonfeed/releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate **Maileon Add-On for Gravity Forms**.

### Composer

For sites managed with Composer (e.g. [Bedrock](https://roots.io/bedrock/)), with [`composer/installers`](https://github.com/composer/installers) placing `wordpress-plugin` packages in your plugins folder.

The package is installed from this GitHub repository. Add it to the site's `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/itops-xqueue/gravityformsmaileonfeed" }
  ]
}
```

Then require it:

```bash
composer require xqueue/gravityformsmaileonfeed:^2.0
```

It's installed as `wp-content/plugins/gravityformsmaileonfeed` (or your `installer-paths` location). Make sure the site loads Composer's `vendor/autoload.php`, as Bedrock does; the plugin then uses the Maileon API client from the site's Composer dependencies. Activate it in WordPress or with `wp plugin activate gravityformsmaileonfeed`.

## Configuration

1. **Forms → Settings → Maileon:** enter a Maileon API key (best: one created for this add-on, with access to contacts, custom fields and transactions), the default permission and the Double Opt-In settings.
2. **Form → Settings → Maileon → Add New:** select the email field, map form fields to Maileon standard and custom contact fields, and optionally pick a transaction type and map its attributes.

If something isn't sent, enable logging for the add-on under **Forms → Settings → Logging**; Maileon API errors are logged with their HTTP status and message.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
