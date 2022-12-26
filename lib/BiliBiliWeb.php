<?php

use GuzzleHttp\Client;

class BiliWeb
{
    /*
    get json data from web source / without api
    */
    public static function extract($url)
    {
        print " [extractWebPage] ...\n";
        $url = BiliBili::check_url($url, false);
        $page = (new Client())->get(
            $url,
            [
                "headers" => BiliBili::$headers
            ]
        )->getBody();
        if (preg_match("/window\.__INITIAL_STATE__\=(.*?);/i", (string)$page, $match)) {
            $json = json_decode($match[1])->videoData;
            return [
                "status" => 200,
                "aid" => $json->aid,
                "cid" => $json->cid,
                "title" => $json->title,
                "thumbnail" => $json->pic,
                "author" => $json->owner->name,
                "description" => $json->desc,
                "page" => (string)$page
            ];
        } else {
            print "Failed get data : {$url}";
            die;
        }
    }

    public static function get_video_info($dats)
    {
        $formats = [
            "audio" => array(),
            "video" => array()
        ];
        print " [gettingInfo] " . $dats["cid"] . "\n";
        if (preg_match("/window\.__playinfo__\=(.*?)<\/script/i", $dats["page"], $match)) {
            $data = json_decode($match[1])->data;
            foreach ($data->dash->video as $video) {
                if (!in_array($video->id, array_keys($formats["video"]))) {
                    $formats["video"][$video->id] = array(
                        "format" => explode("/", $video->mime_type)[1],
                        "quality" => $video->width . "P",
                        "url" => $video->base_url
                    );
                }
            }

            foreach ($data->dash->audio as $audio) {
                if (!in_array($audio->id, array_keys($formats["audio"]))) {
                    $formats["audio"][$audio->id] = array(
                        "format" => "mp3",
                        "url" => $audio->base_url
                    );
                }
            }
            $dats["media"] = $formats;
            $dats["accept_quality"] = $data->accept_quality;
            return $dats;
        } else {
            die("Exited !");
        }
    }
}
