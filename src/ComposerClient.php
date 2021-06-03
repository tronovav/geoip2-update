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
 * These libraries are included in the Composer assembly
 * and do not need to be included as a dependency when updating databases through Composer.
 */
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Updates databases through a call from Composer.
 * Class ComposerClient
 * @package tronovav\GeoIP2Update
 */

class ComposerClient
{
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

        $client = new ComposerConsole($params);
        $client->run();

        $output = new ConsoleOutput();

        $infoArray = $client->updated();
        array_walk($infoArray,function ($info) use ($output){
            $output->writeln("<info>$info</info>");
        });
        $errorsArray = $client->errors();
        array_walk($errorsArray,function ($error) use ($output){
            $output->writeln("<fg=red>$error</>");
        });
    }
}
