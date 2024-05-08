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

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class ComposerConsole extends Client
{
    /**
     * @param string $editionId
     */
    protected function download($editionId)
    {
        $progressBar = new ProgressBar((new ConsoleOutput()), 100);
        $progressBar->setFormat("  - Upgrading $editionId: [%bar%] %percent:3s%%");
        $progressBar->setRedrawFrequency(1);
        $progressBar->start();
        $progressBarFinish = false;

        $ch = curl_init($this->getRequestUrl($editionId));
        $fh = fopen($this->getArchiveFile($editionId), 'wb');
        curl_setopt_array($ch, array(
            CURLOPT_HTTPGET => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FILE => $fh,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($resource, $download_size = 0, $downloaded = 0, $upload_size = 0, $uploaded = 0) use ($progressBar, &$progressBarFinish) {
                /**
                 * $resource parameter was added in version 5.5.0 breaking backwards compatibility;
                 * if we are using PHP version lower than 5.5.0, we need to shift the arguments
                 * @see http://php.net/manual/en/function.curl-setopt.php#refsect1-function.curl-setopt-changelog
                 */
                if (version_compare(PHP_VERSION, '5.5.0') < 0) {
                    $uploaded = $upload_size;
                    $upload_size = $downloaded;
                    $downloaded = $download_size;
                    $download_size = $resource;
                }

                if ($download_size && !$progressBarFinish)
                    if ($downloaded < $download_size)
                        $progressBar->setProgress(round(($downloaded / $download_size) * 100,0,PHP_ROUND_HALF_DOWN));
                    else {
                        $progressBar->finish();
                        $progressBarFinish = true;
                        echo PHP_EOL;
                    }
            }
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        fclose($fh);
        if ($response === false)
            $this->errorUpdateEditions[$editionId] = "Error download \"{$editionId}\": " . curl_error($ch);
    }
}
