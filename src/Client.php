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
     * @link https://www.maxmind.com/en/accounts/current/geoip/downloads/
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

    protected $urlApi = 'https://download.maxmind.com/app/geoip_download';
    protected $updated = array();
    protected $errors = array();
    protected $errorUpdateEditions = array();
    protected $remoteEditions = array(
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
    protected $remoteTypes = array(
        self::TYPE_MMDB => self::ARCHIVE_GZ,
        self::TYPE_CSV => self::ARCHIVE_ZIP,
    );
    protected $lastModifiedStorageFileName = 'last-modified.txt';

    public function __construct(array $params)
    {
        $this->setConfParams($params);
        $thisClass = new \ReflectionClass($this);
        foreach ($params as $key => $value)
            if ($thisClass->hasProperty($key) && $thisClass->getProperty($key)->isPublic())
                $this->$key = $value;
            else
                $this->errors[] = "The \"{$key}\" parameter does not exist. Just remove it from the options. See https://github.com/tronovav/geoip2-update";

        $this->editions = array_unique((array)$this->editions);
    }

    /**
     * Update info.
     * @return array
     */
    public function updated()
    {
        return $this->updated;
    }

    /**
     * Update errors.
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

    protected function setConfParams(&$params)
    {
        if (array_key_exists('geoipConfFile', $params) && is_file($params['geoipConfFile']) && is_readable($params['geoipConfFile'])) {
            $confParams = array();
            foreach (file($params['geoipConfFile']) as $line) {
                $confString = trim($line);
                if (preg_match('/^\s*(?P<name>LicenseKey|EditionIDs)\s+(?P<value>([\w-]+\s*)+)$/', $confString, $matches)) {
                    $confParams[$matches['name']] = $matches['name'] === 'EditionIDs'
                        ? array_values(array_filter(explode(' ', $matches['value']), function ($val) {
                            return trim($val);
                        }))
                        : trim($matches['value']);
                }
            }
            $this->license_key = !empty($confParams['LicenseKey']) ? $confParams['LicenseKey'] : $this->license_key;
            $this->editions = !empty($confParams['EditionIDs']) ? $confParams['EditionIDs'] : $this->editions;
            unset($params['geoipConfFile']);
        }
    }

    /**
     * @return bool
     */
    protected function validate()
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
    protected function updateEdition($editionId)
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

        $remoteFileLastModified = date_create($newFileRequestHeaders['last-modified'][0])->getTimestamp();
        $localFileLastModified = is_file($this->getEditionDirectory($editionId) . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName) ?
            (int)file_get_contents($this->getEditionDirectory($editionId) . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName) : 0;

        if ($remoteFileLastModified !== $localFileLastModified) {

            $this->download($editionId);
            if (!empty($this->errorUpdateEditions[$editionId]))
                return;

            $this->extract($editionId);
            if (!empty($this->errorUpdateEditions[$editionId]))
                return;

            file_put_contents($this->getEditionDirectory($editionId) . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName, $remoteFileLastModified);
            $this->updated[] = "$editionId has been updated.";
        } else
            $this->updated[] = "$editionId does not need to be updated.";
    }

    /**
     * @param string $editionId
     * @return string
     */
    protected function getRequestUrl($editionId)
    {
        return $this->urlApi . '?' . http_build_query(array(
                'edition_id' => $editionId,
                'suffix' => $this->getArchiveType($editionId),
                'license_key' => $this->license_key,
            ));
    }

    /**
     * @param string $editionId
     * @return string
     */
    protected function getArchiveType($editionId)
    {
        return $this->remoteTypes[$this->remoteEditions[$editionId]];
    }

    /**
     * @param string $editionId
     * @return string
     */
    protected function getArchiveFile($editionId)
    {
        return $this->dir . DIRECTORY_SEPARATOR . $editionId . '.' . $this->getArchiveType($editionId);
    }

    /**
     * @param $editionId
     * @return string
     */
    protected function getEditionDirectory($editionId)
    {
        return $this->dir . DIRECTORY_SEPARATOR . $editionId;
    }

    /**
     * @param string $editionId
     * @return array
     */
    protected function headers($editionId)
    {
        $headers = array();
        $ch = curl_init($this->getRequestUrl($editionId));
        curl_setopt_array($ch, array(
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers) {
                $headerArray = explode(':', $header, 2);
                if (count($headerArray) >= 2) // ignore invalid headers
                    $headers[strtolower(trim($headerArray[0]))][] = trim($headerArray[1]);
                return strlen($header);
            }
        ));
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        switch ($httpCode) {
            case 200:
                break;
            case 401:
                $this->errorUpdateEditions[$editionId] = "Error downloading \"{$editionId}\". Invalid license key.";
                break;
            case 403:
                $this->errorUpdateEditions[$editionId] = "Error downloading \"{$editionId}\". Invalid product ID or subscription expired for \"{$editionId}\".";
                break;
            case 404:
                $this->errorUpdateEditions[$editionId] = "Edition ID: \"{$editionId}\" not found in maxmind.com. The remote server responded with a \"{$httpCode}\" error.";
                break;
            case 0:
                $this->errorUpdateEditions[$editionId] = "Error downloading \"{$editionId}\". The remote server is not available.";
                break;
            default:
                $this->errorUpdateEditions[$editionId] = "Error downloading \"{$editionId}\". The remote server responded with a \"{$httpCode}\" error.";
        }

        if (empty($headers['content-disposition']) && empty($this->errorUpdateEditions[$editionId]))
            $this->errorUpdateEditions[$editionId] = "Edition ID: \"{$editionId}\" not found in maxmind.com";

        if (!empty($this->errorUpdateEditions[$editionId]))
            return array();

        return $headers;
    }

    /**
     * @param string $editionId
     */
    protected function download($editionId)
    {
        $ch = curl_init($this->getRequestUrl($editionId));
        $fh = fopen($this->getArchiveFile($editionId), 'wb');
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

    /**
     * @param string $editionId
     */
    protected function extract($editionId)
    {
        switch ($this->getArchiveType($editionId)) {
            case self::ARCHIVE_GZ:

                $phar = new \PharData($this->getArchiveFile($editionId));
                $phar->extractTo($this->dir, null, true);
                break;
            case self::ARCHIVE_ZIP:

                $zip = new \ZipArchive;
                $zip->open($this->getArchiveFile($editionId));
                $zip->extractTo($this->dir);
                $zip->close();
                break;
        }

        unlink($this->getArchiveFile($editionId));

        if (!is_dir($this->getEditionDirectory($editionId)))
            mkdir($this->getEditionDirectory($editionId));

        $directories = new \DirectoryIterator($this->dir);
        foreach ($directories as $directory)
            /* @var \DirectoryIterator $directory */
            if ($directory->isDir() && preg_match('/^' . $editionId . '[_\d]+$/i', $directory->getFilename())) {
                $newEditionDirectory = new \DirectoryIterator($directory->getPathname());
                foreach ($newEditionDirectory as $item)
                    if ($item->isFile())
                        rename($item->getPathname(), $this->getEditionDirectory($editionId) . DIRECTORY_SEPARATOR . $item->getFilename());
                $this->deleteDirectory($directory->getPathname());
                break;
            }
    }

    /**
     * @param string $directoryPath
     */
    protected function deleteDirectory($directoryPath)
    {
        if (is_dir($directoryPath)) {
            $directory = new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS);
            $children = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($children as $child)
                /* @var \RecursiveDirectoryIterator $child */
                $child->isDir() ? rmdir($child) : unlink($child);
            rmdir($directoryPath);
        }
    }
}
