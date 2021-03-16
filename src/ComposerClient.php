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
    const FG_GREEN = 32;
    const FG_RED = 31;

    /**
     * Database update launcher via console.
     * @param \Composer\Script\Event $event
     */
    public static function run(\Composer\Script\Event $event){

        $extra = $event->getComposer()->getPackage()->getExtra();
        $params = isset($extra[__METHOD__]) ? $extra[__METHOD__] : array();

        if(isset($params['dir'])){
            $params['dir'] = realpath(str_replace('@composer',realpath(dirname(\Composer\Factory::getComposerFile())),$params['dir']));
            $params['dir'] = str_replace(array('\\','/'),DIRECTORY_SEPARATOR,$params['dir']);
        }

        $client = new Client($params);
        $client->run();

        $infoArray = $client->updated();
        array_walk($infoArray,function ($info){
            ComposerClient::write_to_console($info,ComposerClient::FG_GREEN);
        });

        $errorsArray = $client->errors();
        array_walk($errorsArray,function ($error){
            ComposerClient::write_to_console($error,ComposerClient::FG_RED);
        });
    }

    /**
     * @param string $text
     * @param null|int $color
     */
    public static function write_to_console($text, $color = null){
        if(!is_null($color))
            fwrite(\STDOUT, "\033[0m" . "\033[" . $color . 'm' . $text . "\033[0m".PHP_EOL);
        else
            fwrite(\STDOUT, $text.PHP_EOL);
    }
}
