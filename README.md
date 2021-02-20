# Geoip2 Update
Update Maxmind  GeoLite2 and GeoIP2 databases from your php script, program or via Composer.

[![Latest Stable Version](https://img.shields.io/packagist/v/tronovav/geoip2-update.svg)](https://packagist.org/packages/tronovav/geoip2-update)
[![GitHub downloads](https://img.shields.io/packagist/dt/tronovav/geoip2-update)](https://packagist.org/packages/tronovav/geoip2-update)

REQUIREMENTS
------------

The minimum requirement by this library that your Web server supports PHP >= 5.3.0 with curl and zlib libraries.

INSTALLATION
------------

If you do not have [Composer](http://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](https://getcomposer.org/doc/00-intro.md).

You can then install this library using the following command:

~~~
composer require tronovav/geoip2-update
~~~

CONFIGURATION
-------------

### 1. Updating databases via Composer

To update Geoip2 databases via Composer, you can set up an update call in your `composer.json`.
Each time the `composer update` command is invoked, the library will check for updates on the "maxmind.com" server and update the Geoip2 databases if necessary.


#### Basic configuration:

```
# composer.json

"scripts": {
        "post-update-cmd": "tronovav\\GeoIP2Update\\ComposerClient::run"
},
"extra": {
    "tronovav\\GeoIP2Update\\ComposerClient::run": {
        "license_key": "MAXMIND_LICENSE_KEY",
        "dir": "DESTINATION_DIRECTORY_PATH"
    }
}
```

Parameters in the `extra` section:

- `MAXMIND_LICENSE_KEY` (required) - You can see your license key information on [your account License Keys page](https://support.maxmind.com/account-faq/license-keys/where-do-i-find-my-license-key/) at maxmind.com.
- `DESTINATION_DIRECTORY_PATH` (required) - Path to the Geoip2 databases local storage directory.

#### Extended configuration:

```
# composer.json

"scripts": {
        "post-update-cmd": "tronovav\\GeoIP2Update\\ComposerClient::run"
},
"extra": {
    "tronovav\\GeoIP2Update\\ComposerClient::run": {
        "license_key": "MAXMIND_LICENSE_KEY",
        "dir": "DESTINATION_DIRECTORY_PATH",

        "editions": ["GeoLite2-ASN", "GeoLite2-City", "GeoLite2-Country"],
        "type": "mmdb"
    }
}
```

Additional parameters in the `extra` section:

- `editions` - List of database editions that you want to update. Maxmind.com offers databases for free download: `GeoLite2-ASN`, `GeoLite2-City`, `GeoLite2-Country`. These editions will be updated by default if you do not fill in the `editions` parameter. Otherwise, only the editions that you specified will be updated. See available editions in your maxmind.com account.
- `type` - Geoip2 database editions type. Currently, only the binary type `mmdb` is available for updating. Therefore, this parameter can be omitted.

### 2. Updating databases from your application

```php
$client = new \tronovav\GeoIP2Update\Client(array(
    'license_key' => 'MAXMIND_LICENSE_KEY',
    'dir' => 'DESTINATION_DIRECTORY_PATH',
    'editions' => array('GeoLite2-ASN', 'GeoLite2-City', 'GeoLite2-Country'),
    'type' => 'mmdb',
));
$client->run();
```
The description of the constructor parameters can be seen above.

After updating, you can get information about the result:

Update result:
```php
print_r($client->updated());
```
Update errors:
```php
print_r($client->errors());
```
If there were no update errors, then calling `print_r($client->errors());` will return an empty array.
