[![Geoip2 Update](https://user-images.githubusercontent.com/25905384/111375423-4631ce00-86af-11eb-81a9-2bc4dab89068.png)](https://www.geodbase-update.com/?utm_source=github&utm_medium=organic&utm_campaign=github_project_page&utm_content=main_banner)

Geoip2 Update is a PHP tool for updating Maxmind GeoLite2 and GeoIP2 databases from your script, application or via Composer.

[![Latest Stable Version](https://img.shields.io/packagist/v/tronovav/geoip2-update)](https://packagist.org/packages/tronovav/geoip2-update)
[![GitHub downloads](https://img.shields.io/packagist/dt/tronovav/geoip2-update)](https://packagist.org/packages/tronovav/geoip2-update)

DOCUMENTATION
-------------

You can read the documentation on setting up and using the library, as well as learn about new features on the official website.

**Go to documentation -> [GeoIP2 Update Documentation](https://www.geodbase-update.com/?utm_source=github&utm_medium=organic&utm_campaign=github_project_page&utm_content=documentation_link)**

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

COPYRIGHT AND LICENSE
---------------------

This software is Copyright (c) 2021 by Andrey Tronov.

This is free software, licensed under the MIT License.
