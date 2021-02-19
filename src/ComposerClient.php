<?php


namespace tronovav\GeoIP2Update;

/**
 * Updates databases through a call from Composer.
 * Class ComposerClient
 * @package tronovav\GeoIP2Update
 */
class ComposerClient
{
    /**
     * @param \Composer\Script\Event $event
     */
    public static function run(\Composer\Script\Event $event){
        $extra = $event->getComposer()->getPackage()->getExtra();
        $params = isset($extra[__METHOD__]) ? $extra[__METHOD__] : array();
        $client = new Client($params);
        $client->run();
        fwrite(\STDOUT, implode("\n",array_merge($client->updated(),$client->errors())));
    }
}