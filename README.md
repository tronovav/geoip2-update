![geoip2-update-logo](https://user-images.githubusercontent.com/25905384/111375423-4631ce00-86af-11eb-81a9-2bc4dab89068.png)

Geoip2 Update is a php tool for updating Maxmind GeoLite2 and GeoIP2 databases from your script, application or via Composer.

[![Latest Stable Version](https://img.shields.io/packagist/v/tronovav/geoip2-update)](https://packagist.org/packages/tronovav/geoip2-update)
[![GitHub downloads](https://img.shields.io/packagist/dt/tronovav/geoip2-update)](https://packagist.org/packages/tronovav/geoip2-update)

REQUIREMENTS
------------

The minimum requirement of this library is for your web server to support PHP >= 5.3.0 with `curl` extension and an optional `zip` extension if you want to update the `csv` databases.

INSTALLATION
------------

If you do not have [Composer](http://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](https://getcomposer.org/doc/00-intro.md).

You can then install this library using the following command:

```bash
composer require tronovav/geoip2-update
```

CONFIGURATION
-------------

### 1. Updating databases via Composer:

To update Geoip2 databases via Composer, you can set up an update call in your `composer.json`.
Each time the `composer update` command is invoked, the library will check for updates on the "maxmind.com" server and update the Geoip2 databases if necessary.

```json
# composer.json

"scripts": {
        "post-update-cmd": [
            "tronovav\\GeoIP2Update\\ComposerClient::run"
        ]
},
"extra": {
    "tronovav\\GeoIP2Update\\ComposerClient::run": {
        "license_key": "MAXMIND_LICENSE_KEY",
        "dir": "DESTINATION_DIRECTORY_PATH",
        "editions": ["GeoLite2-ASN", "GeoLite2-City", "GeoLite2-Country"]
    }
}
```

Parameters in the `scripts` section:

Just add the `"post-update-cmd": "tronovav\\GeoIP2Update\\ComposerClient::run"` line to update databases via Composer.

Parameters in the `extra` section:

- `license_key` **(required)** - You can see your license key information on [your account License Keys page](https://support.maxmind.com/account-faq/license-keys/where-do-i-find-my-license-key/) at maxmind.com.
- `dir` **(required)** - Absolute path to the Geoip2 local database storage directory. Or you can alias part of the path relative to your composer.json. For example `@composer/path-to-db-storage`.
  The script itself will find the absolute path to the `composer.json` file in your project and the `path-to-db-storage` directory for storing Geoip2 databases relative to `composer.json`.
  You can also specify `@composer/../path-to-db-storage`. The main thing is that you yourself understand which path to storing the database you specify.
- `editions` - List of database editions that you want to update. Maxmind.com offers databases for free download: `GeoLite2-ASN`, `GeoLite2-City`, `GeoLite2-Country`,` GeoLite2-ASN-CSV`, `GeoLite2-City-CSV`,` GeoLite2-Country-CSV`. If you do not specify the `editions` parameter, then the databases will be updated:` GeoLite2-ASN`, `GeoLite2-City`,` GeoLite2-Country`. Otherwise, only the editions that you specified will be updated. See available editions in [your maxmind.com account](https://www.maxmind.com/en/accounts/current/geoip/downloads/).

Instead of the parameters `license_key` and` editions`, you can specify the path to the configuration file.
The configuration file format fully complies with the recommendations of maxmind.com on the documentation page:
[Obtain GeoIP.conf with Account Information](https://dev.maxmind.com/geoip/updating-databases?lang=en#2-obtain-geoipconf-with-account-information)

Example using a config file:

```json
# composer.json

"scripts": {
        "post-update-cmd": [
            "tronovav\\GeoIP2Update\\ComposerClient::run"
        ]
},
"extra": {
    "tronovav\\GeoIP2Update\\ComposerClient::run": {
        "dir": "DESTINATION_DIRECTORY_PATH",
        "geoipConfFile": "DESTINATION_GEOIP_CONFIG_FILE"
    }
}
```

Parameters in the `extra` section:

- `dir` **(required)** - Absolute path to the Geoip2 local database storage directory. Or you can alias part of the path relative to your composer.json. For example `@composer/path-to-db-storage`.
  The script itself will find the absolute path to the `composer.json` file in your project and the `path-to-db-storage` directory for storing Geoip2 databases relative to `composer.json`.
  You can also specify `@composer/../path-to-db-storage` or another path relative to your file` composer.json`.
- `geoipConfFile` **(required)** - The absolute path to the configuration file. Or you can alias part of the path relative to your composer.json. For example `@composer/../GeoIP.conf`.

### 2. Updating databases from your php application:

```php
<?php

require 'vendor/autoload.php';

// configuration
$client = new \tronovav\GeoIP2Update\Client(array(
    'license_key' => 'MAXMIND_LICENSE_KEY',
    'dir' => 'DESTINATION_DIRECTORY_PATH',
    'editions' => array('GeoLite2-ASN', 'GeoLite2-City', 'GeoLite2-Country'),
));
// run update
$client->run();
```

Params:

- `license_key` **(required)** - You can see your license key information on [your account License Keys page](https://support.maxmind.com/account-faq/license-keys/where-do-i-find-my-license-key/) at maxmind.com.
- `dir` **(required)** - Absolute path to the local storage of Geoip2 databases.
- `editions` - List of database editions that you want to update. Maxmind.com offers databases for free download: `GeoLite2-ASN`, `GeoLite2-City`, `GeoLite2-Country`,` GeoLite2-ASN-CSV`, `GeoLite2-City-CSV`,` GeoLite2-Country-CSV`. If you do not specify the `editions` parameter, then the databases will be updated:` GeoLite2-ASN`, `GeoLite2-City`,` GeoLite2-Country`. Otherwise, only the editions that you specified will be updated. See available editions in [your maxmind.com account](https://www.maxmind.com/en/accounts/current/geoip/downloads/).

Instead of the parameters `license_key` and` editions`, you can specify the path to the configuration file.
The configuration file format fully complies with the recommendations of maxmind.com on the documentation page:
[Obtain GeoIP.conf with Account Information](https://dev.maxmind.com/geoip/updating-databases?lang=en#2-obtain-geoipconf-with-account-information)

Example using a config file:

```php
<?php

require 'vendor/autoload.php';

// configuration
$client = new \tronovav\GeoIP2Update\Client(array(
    'dir' => 'DESTINATION_DIRECTORY_PATH',
    "geoipConfFile" => "DESTINATION_GEOIP_CONFIG_FILE",
));
// run update
$client->run();
```

Params:

- `dir` **(required)** - Absolute path to the local storage of Geoip2 databases.
- `geoipConfFile` **(required)** - The absolute path to the configuration file.

After the update, you can get information about the result, if you need it:

```php
print_r($client->updated()); // update result

print_r($client->errors()); // update errors
```

AVAILABLE DATABASE TO UPDATE
----------------------------

Available `Edition ID` databases that you can specify in the `editions` parameter or in the configuration file to update them.

- `GeoLite2-ASN`
- `GeoLite2-City`
- `GeoLite2-Country`

- `GeoLite2-ASN-CSV`
- `GeoLite2-City-CSV`
- `GeoLite2-Country-CSV`

- `GeoIP2-ASN`
- `GeoIP2-City`
- `GeoIP2-Country`

- `GeoIP2-ASN-CSV`
- `GeoIP2-City-CSV`
- `GeoIP2-Country-CSV`

See available `Edition ID` databases for updates in [your maxmind.com account](https://www.maxmind.com/en/accounts/current/geoip/downloads/).

DATABASE UPDATE ATOMICITY
-------------------------

The atomicity of the update operation is implemented at the level of the database files.

The structure of the mmdb and csv databases is different. Mmdb databases consist of a single database file. Thus, when updating the mmdb databases, the operation is completely atomic and errors associated with the short-term absence of the mmdb file during the database update are excluded. Since the files are atomically replaced with new ones.

CSV databases consist of multiple files. When upgrading versions of the CSV database, each CSV file is also replaced atomically, and there is no chance of a file missing during the upgrade.

COPYRIGHT AND LICENSE
---------------------

This software is Copyright (c) 2021 by Andrey Tronov.

This is free software, licensed under the MIT License.
