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

    /**
     * Temporary directory for updating. By default the directory obtained by the sys_get_temp_dir() function.
     * @var string
     */
    public $tmpDir;

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
    private $lastModifiedStorageFileName = 'geoip2.last-modified';

    public function __construct(array $params)
    {
        $this->tmpDir = sys_get_temp_dir();

        $thisClass = new \ReflectionClass($this);
        foreach ($params as $key => $value)
            if ($thisClass->hasProperty($key) && $thisClass->getProperty($key)->isPublic())
                $this->$key = $value;
            else
                $this->errors[] = "The \"{$key}\" parameter does not exist. Just remove it from the options. See https://github.com/tronovav/geoip2-update";
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

        if (!is_dir($this->tmpDir) || !is_writable($this->tmpDir))
            $this->errors[] = sprintf("Temporary directory %s.", (empty($this->tmpDir) ? "not specified" : "{$this->tmpDir} is not writable"));

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

        $newFileRequestHeaders = $this->request(array(
            'edition_id' => $editionId
        ));

        if (!empty($this->errorUpdateEditions[$editionId]))
            return;

        if (empty($newFileRequestHeaders['content-disposition'])) {
            $this->errorUpdateEditions[$editionId] = "Edition ID: \"{$editionId}\" not found in maxmind.com";
            return;
        }
        preg_match('/filename=(?<attachment>[\w.\d-]+)$/', $newFileRequestHeaders['content-disposition'][0], $matches);

        $newFileName = $this->tmpDir . DIRECTORY_SEPARATOR . $matches['attachment'];
        $remoteFileLastModified = date_create($newFileRequestHeaders['last-modified'][0])->getTimestamp();

        if ($remoteFileLastModified !== $this->getLocalLastModified($editionId)) {

            $this->request(array(
                'edition_id' => $editionId,
                'save_to' => $newFileName,
            ));

            $this->extract($newFileName, $editionId);

            if (!empty($this->errorUpdateEditions[$editionId]))
                return;

            $this->setLocalLastModified($editionId, $remoteFileLastModified);
            $this->updated[] = "$editionId has been updated.";
        } else
            $this->updated[] = "$editionId does not need to be updated.";
    }

    /**
     * @param array $params
     * @return array|void
     */
    private function request($params = array())
    {
        $url = $this->urlApi . '?' . http_build_query(array(
                'edition_id' => $params['edition_id'],
                'suffix' => $this->remoteTypes[$this->remoteEditions[$params['edition_id']]],
                'license_key' => $this->license_key,
            ));

        $ch = curl_init($url);
        if (!empty($params['save_to'])) {
            $fh = fopen($params['save_to'], 'w');
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
                $this->errorUpdateEditions[$params['edition_id']] = "Error update \"{$params['edition_id']}\": " . curl_error($ch);
        } else {
            curl_setopt_array($ch, array(
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
            ));
            $header = curl_exec($ch);
            curl_close($ch);
            return $this->parseHeaders($header);
        }
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
     * @return int
     */
    private function getLocalLastModified($editionId)
    {
        $lastModified = 0;

        // TODO: Start delete block in next minor release.
        $olgLastModifiedFile = $this->dir . DIRECTORY_SEPARATOR . $editionId . '.' . $this->remoteEditions[$editionId] . '.last-modified';
        if (is_file($olgLastModifiedFile)) {
            $lastModified = (int)file_get_contents($olgLastModifiedFile);
            $this->setLocalLastModified($editionId, (int)$lastModified);
            unlink($olgLastModifiedFile);
        }
        // TODO: end delete block in next minor release.

        foreach ($this->getLastModifiedArray() as $lastModifiedEdition) {
            preg_match('/^' . $editionId . ':(?P<last_modified>[\d]{10})$/i', $lastModifiedEdition, $matches);
            if (!empty($matches) && ($lastModified = $matches['last_modified'] ?: 0))
                break;
        }
        return (int)$lastModified;
    }

    /**
     * @param string $editionId
     * @param int $time
     */
    private function setLocalLastModified($editionId, $time)
    {
        $outArray = array("$editionId:$time");

        foreach ($this->getLastModifiedArray() as $lastModifiedOldRecord)
            if (preg_match('/' . $editionId . '/i', $lastModifiedOldRecord) === 0)
                $outArray[] = $lastModifiedOldRecord;

        file_put_contents($this->dir . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName, implode(PHP_EOL, $outArray));
    }

    private function getLastModifiedArray()
    {
        return
            is_file($this->dir . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName) ?
                file($this->dir . DIRECTORY_SEPARATOR . $this->lastModifiedStorageFileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
    }

    /**
     * @param string $archiveFile
     * @param string $editionId
     */
    private function extract($archiveFile, $editionId)
    {
        preg_match('/\.(?P<extension>' . str_replace('.', '\.', implode('|', array_values($this->remoteTypes))) . ')$/i', $archiveFile, $matches);

        $archiveType = (!empty($matches) ? ($matches['extension'] ?: null) : null);

        if (empty($archiveType)) {
            $this->errorUpdateEditions[$editionId] = "Error extract \"$archiveFile\" archive. Unknown archive type.";
            unlink($archiveFile);
            return;
        }

        if ($archiveType === self::ARCHIVE_GZ) {
            $phar = new \PharData($archiveFile);
            $phar->extractTo($this->tmpDir, null, true);
            $iterator = new \FilesystemIterator(substr($archiveFile, 0, -7));
        } elseif ($archiveType === self::ARCHIVE_ZIP) {

            if (!class_exists('\ZipArchive')) {
                $this->errorUpdateEditions[$editionId] = "PHP zip extension is required to update csv databases. See https://www.php.net/manual/en/zip.installation.php to install zip php extension.";
                return;
            }

            $zip = new \ZipArchive;
            $zip->open($archiveFile);
            $zip->extractTo($this->tmpDir);
            $zip->close();
            $iterator = new \FilesystemIterator(substr($archiveFile, 0, -4));
        } else
            return;

        foreach ($iterator as $fileIterator)
            if ($fileIterator->isFile() && $fileIterator->getExtension() === array_search($archiveType, $this->remoteTypes))
                rename($fileIterator->getPathname(), $this->dir . DIRECTORY_SEPARATOR . $fileIterator->getFilename());
            else
                unlink($fileIterator->getPathname());

        rmdir($iterator->getPath());
        unlink($archiveFile);
    }
}
