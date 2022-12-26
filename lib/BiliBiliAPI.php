<?php

use GuzzleHttp\Client;
use GuzzleHttp\RedirectMiddleware;

class BiliBili
{
    /*
    converted from youtube_dl, but dunno why cant't get high quality 
    */
    private static $app_key = "iVGUTjsxvpLeuDCf";
    private static $bilibili_key = "aHRmhWMLkdeMuILqORnYZocwMBpMEOdt";
    public static $regex = "/http[s]?:\/\/(?:(?:www|m(?:obile)?)\.bilibili\.(?:tv|com)\/video\/([\w]*))/i";
    public static $headers = [
        "user-agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36"
    ];

    public static function check_url($url, $videoid = true)
    {
        print " [checkUrl] {$url} \n";
        if (preg_match(self::$regex, $url, $match)) {
            return !$videoid ? $url : $match[1];
        } else {
            if (preg_match("/http[s]:\/\/(b[0-9]*\.tv\/\w+)/i", $url, $match)) {
                $redirect_url = (new Client([
                    "allow_redirects" => [
                        "track_redirects" => true
                    ]
                ]))->get($url, self::$headers)->getHeader(
                    RedirectMiddleware::HISTORY_HEADER
                )[0];
                printf(" [Redirect] To: %s\n", $redirect_url);
                return self::check_url($redirect_url, $videoid);
            } else {
                die("invalid url for: {$url}");
            }
        }
    }

    public static function extract($url)
    {
        print " [fetchingAPi] .....\n";
        $url = self::check_url($url);
        $response = (new Client())->get(
            "https://api.bilibili.com/x/web-interface/view",
            [
                "query" => [
                    "bvid" => $url
                ], "headers" => self::$headers
            ]
        );
        if ($response->getStatusCode() == 200) {
            $json = json_decode($response->getBody(), true)["data"];
            return array(
                "status" => 200,
                "aid" => $json["aid"],
                "cid" => $json["cid"],
                "title" => $json["title"],
                "thumbnail" => $json["pic"],
                "author" => $json["owner"]["name"],
                "description" => $json["desc"]
            );
        } else {
            print("Exited with status code: " . $response->getStatusCode());
            return false;
        }
    }

    public static function get_video_info($data)
    {
        $formats = array();
        printf(" [extractInfo] Getting info: %s\n", $data["cid"]);
        $supported_formats = json_decode((new Client())->get(
            "https://api.bilibili.com/x/player/playurl",
            [
                "query" => [
                    "avid" => $data["aid"],
                    "cid" => $data["cid"],
                    "otype" => "json"
                ], "headers" => self::$headers
            ]
        )->getBody(), true);
        if ($supported_formats["code"] != -400) {
            foreach ($supported_formats["data"]["support_formats"] as $forms) {
                $payload = sprintf(
                    "appkey=%s&cid=%s&otype=json&qn=%s&quality=%s&type=%s",
                    self::$app_key,
                    $data["cid"],
                    $forms["quality"],
                    $forms["quality"],
                    $forms["format"]
                );
                $signature = md5($payload . self::$bilibili_key);
                $playJson = json_decode((new Client())->get(
                    "http://interface.bilibili.com/v2/playurl?{$payload}&sign={$signature}",
                    [
                        "headers" => self::$headers
                    ]
                )->getBody(), true);
                if (array_key_exists("durl", $playJson)) {
                    array_push(
                        $formats,
                        [
                            "quality" => $forms["display_desc"],
                            "type" => $forms["format"],
                            "url" => $playJson["durl"][0]["url"]
                        ]
                    );
                } else {
                    echo "Failed get format: " . $forms["format"];
                }
            }
            $data["video_list"] = $formats;
            return $data;
        } else {
            print $supported_formats;
            die;
        }
    }
}
