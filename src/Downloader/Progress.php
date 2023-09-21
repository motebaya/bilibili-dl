<?php

declare(strict_types=1);

namespace BiliDL\Downloader;

use \BiliDL\Init;
use \Symfony\Component\Console\Helper\ProgressBar;
use \Symfony\Component\Console\Output\ConsoleOutput;

/**
 * DRE: also used for merger video.
 */
class Progress extends Init
{
    public function setup(string $format)
    {
        $progress = new ProgressBar(
            new ConsoleOutput(),
            50
        );
        $progress->setFormat($format);
        $progress->setBarCharacter(
            sprintf(
                "%s━%s",
                $this->col->g,
                $this->col->r
            )
        );
        $progress->setEmptyBarCharacter(
            sprintf(
                "%s━%s",
                $this->col->gr,
                $this->col->r
            )
        );
        $progress->setProgressCharacter(
            sprintf(
                "%s╸%s",
                $this->col->bk,
                $this->col->r
            )
        );
        return $progress;
    }
}
