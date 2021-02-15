<?php


namespace tronovav\GeoIP2Update;


class ComposerClient
{
    public static function up($event){
        $extra = $event->getComposer()->getPackage()->getExtra();
        $params = isset($extra[__METHOD__]) ? $extra[__METHOD__] : [];
        $client = new Client($params);
        $client->run();
        fwrite(\STDOUT, implode("\n",array_merge($client->updated(),$client->errors())));
    }
}