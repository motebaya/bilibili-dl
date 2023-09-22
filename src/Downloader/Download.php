<?php

declare(strict_types=1);

namespace BiliDL\Downloader;

use \BiliDL\Init;
use \BiliDL\Downloader\Progress;
use \GuzzleHttp\Exception\ClientException;

/**
 * simply downloader class
 */
class Download extends Init
{
    protected $progress;
    protected $defaultdir = __DIR__ . "/media";
    public function __construct()
    {
        parent::__construct();
        $this->progress = (new Progress())->setup('%message%: %downloaded%/%total% %bar% %percent:3s%% eta %estimate%');
        if (!file_exists($this->defaultdir)) {
            $this->log(sprintf("%s Creating default folder for saved media%s", $this->col->g, $this->col->r));
            mkdir($this->defaultdir);
        }
    }

    /**
     * filename clean.
     *
     * @param string $title
     * @return string
     */
    private function cleanFilename(string $title): string
    {
        return preg_replace(['/[\/:*?"<>|]+/', '/\s+/'], '_', $title);
    }

    /**
     * start download file
     *
     * @param string $url
     * @param string $filename
     * docs: https://symfony.com/doc/current/components/console/helpers/progressbar.html
     * 
     * ISSUE:
     *   read documentation!!
     * @return string
     */
    public function download(
        string $url,
        array $conf = []
    ): string {
        if (isset($conf['type'])) {
            if (isset($conf['formerger']) && $conf['formerger']) {
                // idk, why ffmpeg doesn't work if used from /tmp dir.
                $cachedir = __DIR__ . '/cache';
                if (!is_dir($cachedir)) {
                    $this->log(sprintf("creating cache dir: %s%s%s", $this->col->y, $cachedir, $this->col->r));
                    mkdir($cachedir);
                }

                if (!isset($conf['filename'])) {
                    $filename = sprintf(
                        "%s/.cache_%s.%s",
                        $cachedir,
                        $conf['type'],
                        $conf['type'] == "audio" ? "mp3" : "mp4"
                    );
                }
            } else {
                if (isset($conf['filename'])) {
                    $filename = sprintf("%s/%s", $this->defaultdir, $this->cleanFilename($conf['filename']));
                } else {
                    throw new \Exception(
                        sprintf(
                            "%sFilename required for non merger%s",
                            $this->col->rd,
                            $this->col->r
                        )
                    );
                }
            }
        } else {
            throw new \Exception(
                sprintf("%sunknow type!%s", $this->col->rd, $this->col->r)
            );
        }

        $this->log(sprintf(
            "downloading %s%s%s file to -> %s%s%s",
            $this->col->g,
            $conf['type'],
            $this->col->r,
            $this->col->y,
            basename($filename),
            $this->col->r
        ));
        $this->progress->setMessage(sprintf("Fetch:%s", $conf['type']), 'message');
        try {
            $this->client->get($url, [
                'sink' => $filename,
                'progress' => function (int $download_size, int $downloaded) {
                    $this->progress->setMaxSteps($download_size);
                    $this->progress->setProgress($downloaded);
                    $this->progress->setMessage(
                        $this->human_size($downloaded),
                        'downloaded'
                    );
                    $this->progress->setMessage(
                        $this->human_size($download_size),
                        'total'
                    );
                    $this->progress->setMessage(
                        gmdate(
                            "H:i:s",
                            (int)$this->progress->getEstimated()
                        ),
                        "estimate"
                    );
                },
                'cookies' => $this->cookiejar
            ]);
            $this->progress->finish();
            echo PHP_EOL;
            $this->log(sprintf('%ssuccess, saved as: %s%s%s', $this->col->g, $this->col->w, $filename, $this->col->r));
            return $filename;
        } catch (ClientException $e) {
            echo PHP_EOL;
            $this->log(sprintf(
                "%sFailed Download %s%s%s file, Exception Client with %s%s%s Code!%s",
                $this->col->rd,
                $this->col->y,
                $conf['type'],
                $this->col->r,
                $this->col->y,
                $e->getResponse()->getStatusCode(),
                $this->col->rd,
                $this->col->r
            ));
            return '';
        }
    }
}
