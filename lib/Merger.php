<?php

use FFMpeg\FFMpeg;
use GuzzleHttp\Client;
use FFMpeg\Format\Video\X264;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class Merger
{
    /*
    for merger and downloader
    */
    private $ffmpeg;
    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create();
    }

    public function merger($audio, $video, $output)
    {
        print " [Merger] To: {$output}\n";
        $advanceMedia = $this->ffmpeg->openAdvanced(
            [$video, $audio]
        )->map(
            [],
            new X264('aac', 'libx264'),
            "./Media/" . $output
        )->save();
        return true;
    }

    public function download($url, $output)
    {
        /*
Here: https://symfony.com/doc/current/components/console/helpers/progressbar.html
*/
        $fullpath = "./Media";
        if (!file_exists($fullpath)) {
            mkdir($fullpath);
        }
        $fullpath = !strpos($output, "_audioOnly") || !strpos($output, "_videoOnly") ? $output : $fullpath . "/" . $output;

        if (!file_exists($fullpath)) {
            $progress = new ProgressBar(new ConsoleOutput(), 50);
            $progress->setFormat('%message%: %current%/%max% [%bar%] %percent:3s%% %memory:6s%');
            $progress->setMessage($output);
            $response = (new Client())->request("GET", $url, [
                'sink' => $fullpath,
                'progress' => function (int $download_size, int $downloaded, int $upload_size, int $uploaded) use ($progress) {
                    $progress->setMaxSteps($download_size);
                    $progress->setProgress($downloaded);
                }
            ]);
            print "\n";
        } else {
            print " [skipped] File Exists: {$output}\n";
        }
    }
    /*
@param get_high for auto choice best quality
*/
    public function process($data, $get_high = true)
    {
        printf(
            " [Author] %s\n [Title] %s\n [Description] %s\n",
            $data["author"],
            $data["title"],
            $data["description"]
        );

        $audio = $data["media"]["audio"];
        $video = $data["media"]["video"];
        $accept_quality = $data["accept_quality"];
        rsort($accept_quality, SORT_NUMERIC);
        if ($get_high) {
            foreach ($accept_quality as $quality) {
                if (in_array($quality, array_keys($video))) {
                    $video = $video[$quality];
                    printf(
                        " [video] %s / %s",
                        $video["quality"],
                        $video["format"]
                    );
                    $video_file = $data["title"] . "_videoOnly.mp4";
                    $this->download(
                        $video["url"],
                        $video_file
                    );
                    break;
                }
            }
        } else {
            $i = 1;
            $keys = array_keys($video);
            foreach ($video as $key => $value) {
                printf(
                    "%s. [%s] %s/%s",
                    $i,
                    $key,
                    $value["format"],
                    $value["quality"]
                );
                $i++;
            }
            echo " [quality] Set quality: ";
            $quality = trim(fgets(STDIN));
            $video = $video[$keys[$quality]];
            printf(
                " [video] %s / %s",
                $video["quality"],
                $video["format"]
            );
            $video_file = $data["title"] . "_videoOnly." . $video["format"];
            $this->download(
                $video["url"],
                $video_file
            );
        }

        $audio = $audio[array_keys($audio)[0]];
        $audio_file = $data["title"] . "_audioOnly." . $audio["format"];
        $this->download(
            $audio["url"],
            $audio_file
        );

        if ($this->merger($audio_file, $video_file, $data["title"] . "." . $video["format"])) {
            print " [done] saved!\n\n";
        };
    }

    public function processApi($data)
    {
        printf(
            " [Author] %s\n [Title] %s\n [Description] %s\n",
            $data["author"],
            $data["title"],
            $data["description"]
        );
        $video = $data["video_list"][0]; // get only best quality
        $this->download(
            $video["url"],
            $data["title"] . "." . $video["type"]
        );
    }
}
