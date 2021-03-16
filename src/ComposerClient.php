<?php

/*
 * This file is part of tronovav\GeoIP2Update.
 *
 * (c) Andrey Tronov
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tronovav\GeoIP2Update;

/**
 * Updates databases through a call from Composer.
 * Class ComposerClient
 * @package tronovav\GeoIP2Update
 */
class ComposerClient
{
    /**
     * Database update launcher.
     * @param \Composer\Script\Event $event
     */
    public static function run(\Composer\Script\Event $event){
        $extra = $event->getComposer()->getPackage()->getExtra();
        $params = isset($extra[__METHOD__]) ? $extra[__METHOD__] : array();
        if(isset($params['dir']))
            $params['dir'] = realpath(str_replace('@composer',realpath(dirname(\Composer\Factory::getComposerFile())),$params['dir']));
        $params['dir'] = str_replace(array('\\','/'),DIRECTORY_SEPARATOR,$params['dir']);
        $client = new Client($params);
        $client->run();
        fwrite(\STDOUT, implode(PHP_EOL,array_merge($client->updated(),$client->errors())).PHP_EOL);
    }
}
