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
 * Class Client
 * @package tronovav\GeoIP2Update
 */
class Client
{
    const TYPE_MMDB = 'mmdb';
    const TYPE_CSV = 'csv';

    const ARCHIVE_GZ = 'tar.gz';
    const ARCHIVE_ZIP = 'zip';

    /**
     * @var string Your account’s actual license key in www.maxmind.com.
     * @link https://support.maxmind.com/account-faq/license-keys/where-do-i-find-my-license-key/
     */
    public $license_key;

    /**
     * @var string[] Database editions list to update.
     */
    public $editions = array(
        'GeoLite2-ASN',
        'GeoLite2-City',
        'GeoLite2-Country',
    );

    /**
     * @var string Destination directory. Directory where your copies of databases are stored. Your old databases will be updated there.
     */
    public $dir;

    private $urlApi = 'https://download.maxmind.com/app/geoip_download';
    private $updated = array();
    private $errors = array();
    private $errorUpdateEditions = array();

    private $remoteEditions = array(
        'GeoLite2-ASN' => self::TYPE_MMDB,
        'GeoLite2-City' => self::TYPE_MMDB,
        'GeoLite2-Country' => self::TYPE_MMDB,

        'GeoIP2-ASN' => self::TYPE_MMDB,
        'GeoIP2-City' => self::TYPE_MMDB,
        'GeoIP2-Country' => self::TYPE_MMDB,

        'GeoLite2-ASN-CSV' => self::TYPE_CSV,
        'GeoLite2-City-CSV' => self::TYPE_CSV,
        'GeoLite2-Country-CSV' => self::TYPE_CSV,

        'GeoIP2-ASN-CSV' => self::TYPE_CSV,
        'GeoIP2-City-CSV' => self::TYPE_CSV,
        'GeoIP2-Country-CSV' => self::TYPE_CSV,
    );

    private $remoteTypes = array(
        self::TYPE_MMDB => self::ARCHIVE_GZ,
        self::TYPE_CSV => self::ARCHIVE_ZIP,
    );
    private $lastModifiedStorageFileName = 'last-modified.txt';

    public function __construct(array $params)
    {
        $thisClass = new \ReflectionClass($this);
        foreach ($params as $key => $value)
            if ($thisClass->hasProperty($key) && $thisClass->getProperty($key)->isPublic())
                $this->$key = $value;
            else
                $this->errors[] = "The \"{$key}\" parameter does not exist. Just remove it from the options. See https://github.com/tronovav/geoip2-update";

        $this->editions = array_unique((array)$this->editions);
    }

    /**
     * @return array
     */
    public function updated()
    {
        return $this->updated;
    }

    /**
     * Critical update errors.
     * @return array
     */
    public function errors()
    {
        return array_merge($this->errors, array_values($this->errorUpdateEditions));
    }

    /**
     * Database update launcher.
     */
    public function run()
    {
        if (!$this->validate())
            return;

        foreach ($this->editions as $editionId)
            $this->updateEdition($editionId);
    }

    private function validate()
    {
        if (!empty($this->errors))
            return false;

        if (!is_dir($this->dir) || !is_writable($this->dir))
            $this->errors[] = sprintf("Destination directory %s.", (empty($this->dir) ? "not specified" : "$this->dir is not writable"));

        if (empty($this->license_key))
            $this->errors[] = "You must specify your license_key https://support.maxmind.com/account-faq/license-keys/where-do-i-find-my-license-key/";

        if (empty($this->editions))
            $this->errors[] = "No GeoIP revision names are specified for the update. Specify the \"editions\" parameter in the config. See https://github.com/tronovav/geoip2-update";

        if (!empty($this->errors))
            return false;

        return true;
    }

    /**
     * @param string $editionId
     */
    private function updateEdition($editionId)
    {
        if (!array_key_exists($editionId, $this->remoteEditions)) {
            $this->errorUpdateEditions[$editionId] = "The Edition ID: \"{$editionId}\" does not exist or is not currently supported for updating.";
            return;
        }

        if ($this->getArchiveType($editionId) === self::ARCHIVE_ZIP && !class_exists('\ZipArchive')) {
            $this->errorUpdateEditions[$editionId] = "PHP zip extension is required to update csv databases. See https://www.php.net/manual/en/zip.installation.php to install zip php extension.";
            return;
        }

        $newFileRequestHeaders = $this->headers($editionId);
        if (!empty($this->errorUpdateEditions[$editionId]))
            return;

        if (empty($newFileRequestHeaders['content-disposition'])) {
            $this->errorUpdateEditions[$editionId] = "Edition ID: \"{$editionId}\" not found in maxmind.com";
            return;
        }

        $remoteFileLastModified = date_create($newFileRequestHeaders['last-modified'][0])->getTimestamp();
        $localFileLastModified = is_file($this->dir . DIRECTORY_SEPARATOR . $editionId . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName) ?
            (int)file_get_contents($this->dir . DIRECTORY_SEPARATOR . $editionId . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName) : 0;

        if ($remoteFileLastModified !== $localFileLastModified) {

            $this->download($editionId);
            if (!empty($this->errorUpdateEditions[$editionId]))
                return;

            $this->extract($editionId);
            if (!empty($this->errorUpdateEditions[$editionId]))
                return;

            file_put_contents($this->dir . DIRECTORY_SEPARATOR . $editionId . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName, $remoteFileLastModified);
            $this->updated[] = "$editionId has been updated.";
        } else
            $this->updated[] = "$editionId does not need to be updated.";
    }

    /**
     * @param string $editionId
     * @return array
     */
    private function headers($editionId)
    {
        $ch = curl_init($this->getRequestUrl($editionId));
        curl_setopt_array($ch, array(
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
        ));
        $header = curl_exec($ch);
        curl_close($ch);
        return $this->parseHeaders($header);
    }

    /**
     * @param string $editionId
     */
    private function download($editionId)
    {
        $ch = curl_init($this->getRequestUrl($editionId));
        $fh = fopen($this->dir . DIRECTORY_SEPARATOR . $editionId . '.' . $this->remoteTypes[$this->remoteEditions[$editionId]], 'wb');
        curl_setopt_array($ch, array(
            CURLOPT_HTTPGET => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FILE => $fh,
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        fclose($fh);
        if ($response === false)
            $this->errorUpdateEditions[$editionId] = "Error download \"{$editionId}\": " . curl_error($ch);
    }

    private function getRequestUrl($edition_id)
    {
        return $this->urlApi . '?' . http_build_query(array(
                'edition_id' => $edition_id,
                'suffix' => $this->remoteTypes[$this->remoteEditions[$edition_id]],
                'license_key' => $this->license_key,
            ));
    }

    /**
     * @param string $header
     * @return array
     */
    private function parseHeaders($header)
    {
        $lines = explode("\n", $header);
        $headers = array();
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            $headers[strtolower(trim($parts[0]))][] = isset($parts[1]) ? trim($parts[1]) : null;
        }
        return $headers;
    }

    /**
     * @param string $editionId
     */
    private function extract($editionId)
    {

        switch (true) {
            case $this->getArchiveType($editionId) === self::ARCHIVE_GZ:

                $phar = new \PharData($this->getArchiveFile($editionId));
                $phar->extractTo($this->dir, null, true);
                break;
            case $this->getArchiveType($editionId) === self::ARCHIVE_ZIP:

                $zip = new \ZipArchive;
                $zip->open($this->getArchiveFile($editionId));
                $zip->extractTo($this->dir);
                $zip->close();
                break;
        }

        unlink($this->getArchiveFile($editionId));

        $this->deleteDirectory($this->dir . DIRECTORY_SEPARATOR . $editionId);

        $directories = new \DirectoryIterator($this->dir);
        foreach ($directories as $directory)
            if ($directory->isDir() && preg_match('/^' . $editionId . '[_\d]+$/i', $directory->getFilename()))
                rename($directory->getPathname(), $this->dir . DIRECTORY_SEPARATOR . $editionId);
    }

    private function deleteDirectory($directoryPath)
    {

        if (is_dir($directoryPath)) {
            $directory = new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS);
            $children = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($children as $child)
                $child->isDir() ? rmdir($child) : unlink($child);
            rmdir($directoryPath);
        }
    }

    private function getArchiveType($editionId)
    {
        return $this->remoteTypes[$this->remoteEditions[$editionId]];
    }

    private function getArchiveFile($editionId)
    {
        return $this->dir . DIRECTORY_SEPARATOR . $editionId . '.' . $this->getArchiveType($editionId);
    }
}
