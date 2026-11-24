# Laravel Wizard Installer patch

`sagar/laravel-wizard-installer` is installed under `vendor/`, and its current
source distribution contains an `APPSECRET` prerequisite that redirects into
the application-owned purchase-code screen. Composer can replace files under
`vendor/` during install or update, so the installer changes are reapplied by
[`scripts/apply-installer-patch.php`](../scripts/apply-installer-patch.php).

The script runs after Composer install and update. It is idempotent, changes
only the installer database, migrations, keys, finish, and database-settings
controllers, and fails loudly if a future package version no longer matches
the expected source blocks. Review and update its replacements when upgrading
the installer package; do not remove the vendor's unrelated checks or
installation lock.

Run it manually after a package reinstall with:

```sh
php scripts/apply-installer-patch.php
```
