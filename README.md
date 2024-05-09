INSTALLATION
------------

```bash
 composer require tomazov/geoip2-update
```

FEATURES
--------

### 1. Updating GeoIP2 databases via Composer.

To update Geoip2 databases via Composer, you can set up an update call in your `composer.json`.
Each time the `composer update` command is invoked, the library will check for updates on the "maxmind.com" server and update the Geoip2 databases if necessary.
You can also update only `GeoIP2` databases without updating all project dependencies:
`composer update tronovav/geoip2-update`.

### 2. Updating GeoIP2 databases from your PHP application.

You can use this option to update `GeoIP2` databases from your PHP project, or use `Cron` on Linux, or use `Task Scheduler` on Windows.

### 3. Simple, cross-platform and reliable.

Does not depend on the operating system and can be used on hosting services and production servers.
