<?php

declare(strict_types=1);

namespace BiliDL\Downloader;

use \BiliDL\Downloader\Download;
use \BiliDL\Downloader\Progress;
use \FFMpeg\Format\Video\X264;
use \FFMpeg\FFMpeg;

class Merger extends Download
{

    /**
     * by default php-ffmpeg can autodetect binary path, but i want to set it manually.
     * https://github.com/PHP-FFMpeg/PHP-FFMpeg#documentation
     */
    private $ffmpeg;
    public function __construct()
    {
        parent::__construct();
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => "/usr/bin/ffmpeg",
            'ffprobe.binaries' => "/usr/bin/ffprobe"
        ]);
    }

    private function setFormat()
    {
        $this->progress = (new Progress())->setup('%message%: %current%/%max% %bar% %percent:3s%%');
        $this->progress->setMaxSteps(100); // default 100%
        $this->progress->start();
    }

    /**
     * check mimetype from downloaded filename
     *
     * @param string $stream
     * @return bool
     */
    public function _isStreamData(string $stream): bool
    {
        if (!empty($stream)) {
            if (preg_match('/(text|html)/', mime_content_type($stream))) {
                $this->log(sprintf("%sHtml/Text data detected! in %s%s%s", $this->col->rd, $this->col->g, basename(realpath($stream)), $this->col->r));
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * TODO: download audio and video. then merger it with ffmpeg..
     * @see https://stackoverflow.com/questions/61818186/ffmpeg-merge-audio-and-video-files
     *      https://github.com/PHP-FFMpeg/PHP-FFMpeg/issues/346
     * 
     * @param string $title
     * @param string $audiourl
     * @param string $videourl
     * @return array
     * 
     */
    public function merger(
        string $title,
        string $audiourl,
        string $videourl,
        bool $shorten = false
    ): array {
        $videofile = $this->download($videourl, [
            'type' => 'video',
            'formerger' => true
        ]);
        if (!$this->_isStreamData($videofile)) {
            return [
                'status' => false,
                'type' => 'video'
            ];
        }

        $audiofile = $this->download($audiourl, [
            'type' => 'audio',
            'formerger' => true
        ]);
        if (!$this->_isStreamData($audiofile)) {
            return [
                'status' => false,
                'type' => 'audio'
            ];
        }

        $this->log(sprintf(
            "Please wait, still merger %s%s%s and %s%s%s to %s%s.mp4%s",
            $this->col->g,
            basename($audiofile),
            $this->col->r,
            $this->col->g,
            basename($videofile),
            $this->col->r,
            $this->col->g,
            $title,
            $this->col->r
        ));

        // change progress formatter after download.
        $this->setFormat();
        $filename = sprintf("%s.mp4", $title);
        $this->progress->setMessage("Rendering..", "message");

        // video format
        $format = new X264('aac', 'libx264');
        $format->setAudioKiloBitrate(320);
        $format->on('progress', function ($video, $format, $percentage) {
            $this->progress->setProgress((int)$percentage);
        });

        if (!$shorten) {
            $advancedMedia = $this->ffmpeg->openAdvanced([$videofile, $audiofile]);
            $advancedMedia->map(
                [],
                $format,
                sprintf("%s/%s", $this->defaultdir, $filename)
            )->save();
            echo PHP_EOL;
            $this->log(sprintf("%sdone!%s", $this->col->g, $this->col->r));
            return [
                'status' => true
            ];
        }

        /**
         * shorten the duration of the output file based on the duration of the shortest input stream.
         * 
         * @see https://superuser.com/questions/1045410/combine-video-and-audio-and-make-the-final-duration-the-same-as-first-input-with
         * @see https://github.com/PHP-FFMpeg/PHP-FFMpeg/issues/346#issuecomment-292701054
         * 
         */
        $video = $this->ffmpeg->open($videofile);
        $video->addFilter(new \FFMpeg\Filters\Audio\SimpleFilter([
            '-i', $audiofile,
            '-shortest'
        ]));
        $video->save($format, sprintf("%s/%s", $this->defaultdir, $filename));
        echo PHP_EOL;
        $this->log(sprintf("%sdone!%s", $this->col->g, $this->col->r));
        return [
            'status' => true
        ];
    }
}
