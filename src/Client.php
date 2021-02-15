<?php


namespace tronovav\GeoIP2Update;

class Client
{

    /**
     * @var string Your account’s actual license key in www.maxmind.com.
     * @link https://support.maxmind.com/account-faq/license-keys/where-do-i-find-my-license-key/
     */
    public $license_key;

    /**
     * @var string Destination directory. Directory where your copies of databases are stored. Your old databases will be updated there.
     */
    public $dir;
    /**
     * The type of databases being updated. May be "mmdb" or "csv". If not specified, the mmdb type is the default.
     * @var string
     */
    public $type = 'mmdb';
    public $tmpDir;

    private $updated =[];
    private $errors =[];

    public function __construct($params = [])
    {
        foreach ($params as $key => $value)
            if(property_exists($this, $key))
                $this->$key = $value;
    }

    /**
     * @return array
     */
    public function updated(){
        return $this->updated;
    }

    /**
     * Critical update errors.
     * @return array
     */
    public function errors(){
        return $this->errors;
    }

    private $remoteEditions = [
        'GeoLite2-ASN',
        'GeoIP2-ASN',

        'GeoLite2-City',
        'GeoIP2-City',

        'GeoLite2-Country',
        'GeoIP2-Country',
    ];

    private $remoteTypes = [
        'mmdb' => 'tar.gz',
        // TODO implement csv extension
        //'csv' => 'zip',
    ];

    public function run(){

        $this->tmpDir = $this->tmpDir ?: sys_get_temp_dir();

        if(!is_dir($this->tmpDir) || !is_writable($this->tmpDir))
            $this->errors[] = sprintf("Temporary directory %s.",(empty($this->tmpDir) ? "not specified" : "{$this->tmpDir} is not writable"));

        if(!is_dir($this->dir) || !is_writable($this->dir))
            $this->errors[] = sprintf("Destination directory %s.",(empty($this->dir) ? "not specified" : "$this->dir is not writable"));

        if(empty($this->type) || !array_key_exists($this->type,$this->remoteTypes))
            $this->errors[] = sprintf("Database type %s.",(empty($this->type) ? "not specified" : "$this->type does not exist"));

        if(!empty($this->errors))
            return false;

        foreach ($this->remoteEditions as $remoteEdition){
            $this->updateEdition($remoteEdition);
        }
        return true;
    }

    private function updateEdition($editionId){

        $query = [
            'edition_id'=>$editionId,
            'suffix'=>$this->remoteTypes[$this->type],
            'license_key' => $this->license_key,
        ];

        $client = new \GuzzleHttp\Client();
        $newFileHeaders = $client->head('https://download.maxmind.com/app/geoip_download', [
            'query' => $query
        ]);

        preg_match('/filename=(?<attachment>[\w.\d-]+)$/' ,$newFileHeaders->getHeader('Content-Disposition')[0],$matches);
        $newFileName = $this->tmpDir.DIRECTORY_SEPARATOR.$matches['attachment'];

        $remoteFileLastModified = (new \DateTime($newFileHeaders->getHeader('Last-Modified')[0]))->getTimestamp();
        $oldFileName = $this->dir.DIRECTORY_SEPARATOR.$editionId.'.'.$this->type;

        if(!is_file($oldFileName) || $remoteFileLastModified !== filemtime($oldFileName)){

            if(is_file($newFileName))
                unlink($newFileName);

            $client->get('https://download.maxmind.com/app/geoip_download', [
                'query' => $query,
                'sink' => $newFileName,
            ]);

            if(is_file($oldFileName))
                unlink($oldFileName);

            $this->gz_unpack($newFileName,$oldFileName);
            touch($oldFileName,$remoteFileLastModified);
            unlink($newFileName);
            $this->updated[] = "The $editionId.{$this->type} file has been updated.";
        }
        else
            $this->updated[] = "The $editionId.{$this->type} file does not need to be updated.";
    }

    private function gz_unpack($inPath, $outPath)
    {
        // Raising this value may increase performance
        $buffer_size = 1048576; // read 4kb at a time
        // Open our files (in binary mode)
        $file = gzopen($inPath, 'rb');
        $out_file = fopen($outPath, 'wb');
        // Keep repeating until the end of the input file
        while (!gzeof($file)) {
            // Read buffer-size bytes
            // Both fwrite and gzread and binary-safe
            fwrite($out_file, gzread($file, $buffer_size));
        }
        fclose($out_file);
        gzclose($file);
    }
}
