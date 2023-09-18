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

use Composer\Factory;
use Composer\Script\Event;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class ComposerClient
 * @package tronovav\GeoIP2Update
 */
class ComposerClient
{
    /**
     * Database update launcher via console.
     * @param Event $event
     */
    public static function run(Event $event)
    {

        $extra = $event->getComposer()->getPackage()->getExtra();
        $params = isset($extra[__METHOD__]) ? $extra[__METHOD__] : array();

        if (isset($params['dir'])) {
            $params['dir'] = realpath(str_replace('@composer', realpath(dirname(Factory::getComposerFile())), $params['dir']));
            $params['dir'] = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $params['dir']);
        }

        if (isset($params['geoipConfFile'])) {
            $params['geoipConfFile'] = realpath(str_replace('@composer', realpath(dirname(Factory::getComposerFile())), $params['geoipConfFile']));
            $params['geoipConfFile'] = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $params['geoipConfFile']);
        }

        $client = new ComposerConsole($params);
        $client->run();

        $output = new ConsoleOutput();

        $infoArray = $client->updated();
        array_walk($infoArray, function ($info) use ($output) {
            $output->writeln("<fg=green>$info</>");
        });

        $errorsArray = $client->errors();

        if(!empty($errorsArray))
            $output->writeln("<fg=red>GeoIP2 database update errors:</>");

        array_walk($errorsArray, function ($error) use ($output) {
            $output->writeln("<fg=red>  - $error</>");
        });
    }
}
