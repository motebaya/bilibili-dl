<?php

declare(strict_types=1);

namespace BiliDL\Extractor;

use \BiliDL\Init;
use \BiliDL\Downloader\Merger;
use \GuzzleHttp\Cookie\CookieJar;
use \Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Promise;

error_reporting(E_ALL);

/**
 * Init -> indent
 * Extractor -> Init (with parent)
 * BiliBili -> Extractor (with parent)
 */
class Extractor extends Init
{
    public array $method_list;
    public bool $hasLogin;
    public array $user_detail;
    public array $user_book_data;
    public array $bookmark_videos;

    private Merger $merger;
    private array $formats;
    private string $appkey = "iVGUTjsxvpLeuDCf";
    private string $bilibilikey = "aHRmhWMLkdeMuILqORnYZocwMBpMEOdt";
    private string $default_url = "https://www.bilibili.com/video/%s/";
    private string $regexInfo = "/(?<=window\.__initial_state__\=)([^>*].*?)(?=;\(function)/i";
    private string $regexMedia = "/(?<=window\.__playinfo__\=)([^>*]+?)(?=<\/script>)/";

    public function __construct()
    {
        $this->merger = new Merger();
        $this->hasLogin = false;
        $this->method_list = array();
        $this->user_detail = array();
        $this->formats = [120 => "超清 4K", 116 => "高清 1080P60", 80 => "高清 1080P", 64 => "高清 720P", 32 => "清晰 480P", 16 => "流畅 360P"];
        parent::__construct();
    }

    /**
     * get user bookmark list data
     * ther's have 2 type bookmark, create own & save from any user.
     * it's same as when you save youtube playlist from other channel to your account.
     * but this only for get own bookmark.
     * 
     * @param string $userid
     * @return PromiseInterface
     */
    protected function get_user_bookmark(string $userid): PromiseInterface
    {
        return $this->client->getAsync('https://api.bilibili.com/x/v3/fav/folder/created/list-all', [
            'query' => [
                'up_mid' => $userid // user id
            ],
            'cookies' => $this->cookiejar
        ])->then(
            function (ResponseInterface $response) {
                $data = json_decode($response->getBody()->getContents(), true);
                if (isset($data['data'])) {
                    $this->user_book_data = $data['data'];
                    return $data['data'];
                }
                return [];
            }
        );
    }

    /**
     * get video list from bookmark id
     * boomarkid example -> 1017517240
     * 
     * @param string $bookmarkid
     * 
     */
    protected function get_bookmark_video(string $bookmarkid, int $page = 1)
    {
        return $this->client->getAsync(
            "https://api.bilibili.com/x/v3/fav/resource/list",
            [
                'query' => [
                    "media_id" => $bookmarkid,
                    "pn" => (string)$page, // page id
                    "ps" => "20" // max page content 20
                ],
                'cookies' => $this->cookiejar
            ]
        )->then(
            function (ResponseInterface $response) use ($bookmarkid, $page) {
                $data = json_decode($response->getBody()->getContents(), true);
                if (isset($data['data'])) {
                    if (isset($this->bookmark_videos['medias'])) {
                        array_push(
                            $this->bookmark_videos['medias'],
                            ...$data['data']['medias']
                        );
                    } else {
                        $this->bookmark_videos = $data['data'];
                    }

                    // has next page, get next .
                    if ($data['data']['has_more']) {
                        $this->log(sprintf(
                            "%sGetting to next page -> %s[%s%s%s]...",
                            $this->col->g,
                            $this->col->r,
                            $this->col->y,
                            (string)$page,
                            $this->col->r
                        ));
                        $this->get_bookmark_video($bookmarkid, $page = $page + 1);
                    }
                    return $this->bookmark_videos;
                }
                return [];
            }
        )->wait();
    }

    /**
     * get user login information with bilibili api's.
     * @name -> username
     * @mid -> userid
     * 
     * @return PromiseInterface
     */
    protected function get_user_info(): PromiseInterface
    {
        $this->log(sprintf("%sTrying to login..%s", $this->col->y, $this->col->r));
        return $this->client->getAsync("https://api.bilibili.com/x/space/v2/myinfo", [
            'cookies' => $this->cookiejar
        ])->then(
            function (ResponseInterface $res) {
                $data = json_decode($res->getBody()->getContents(), true);
                if (strtolower($data['message']) == "ok") {
                    return [
                        "name" => $data['data']['profile']['name'],
                        "mid" => $data['data']['profile']['mid']
                    ];
                }
                $this->cookiejar = new CookieJar(); // failed login, set to empty cookie
                $this->log(
                    sprintf(
                        "%sLogin failed::Cookies Expired, see docs: %shttps://github.com/motebaya/bilibili-dl#README.md%s",
                        $this->col->rd,
                        $this->col->y,
                        $this->col->r
                    )
                );
                return [];
            }
        );
    }

    /**
     * get video data from webpage
     *
     * @param string $videoid
     * @return PromiseInterface
     */
    protected function get_video_data(string $videoid): PromiseInterface
    {
        return $this->client->getAsync(sprintf($this->default_url, $videoid), [
            "cookies" => $this->cookiejar
        ])->then(
            function (ResponseInterface $response) {
                $result = [];
                $body = $response->getBody()->getContents();

                // get audio and video data.
                if (preg_match($this->regexMedia, $body, $match)) {
                    $data = json_decode($match[1], true);
                    $result['video'] = $data['data']['dash']['video'];
                    $result['audio'] = $data['data']['dash']['audio'];
                } else {
                    // exit, bc nothing to do if url is nothing.
                    $this->log(sprintf(" %sFailed get video & audio url.. %s", $this->col->rd, $this->col->r));
                    die;
                }

                // get video info: e.g title .etc
                if (preg_match($this->regexInfo, html_entity_decode($body), $match)) {
                    $data = json_decode($match[1], true)['videoData'];
                    $this->log(sprintf(
                        "Title => %s%s%s",
                        $this->col->g,
                        $data['title'],
                        $this->col->r
                    ));

                    // asign, idk why array_merge doesn't work.
                    $result['cid'] = $data['cid'];
                    $result["bvid"] = $data['bvid'];
                    $result["title"] = $data['title'];
                    $result["thumbnail"] = $data['pic'];
                    $result["description"] = $data['desc'];
                    $result["uploaded"] = date("Y-m-d H:i:s", $data['pubdate']);
                    $result["owner"] = $data['owner'];
                } else {
                    $this->log(sprintf("%sFailed get video info%s", $this->col->rd, $this->col->r));
                }
                return $result;
            }
        );
    }

    /**
     * login check.
     * IF: empty that's mean, login is not successfully.
     * TODO: avoid login again for bulk/batch url, so set login status.
     * 
     * @return PromiseInterface
     * 
     */
    protected function login(): PromiseInterface
    {
        if (!$this->hasLogin) {
            return $this->get_user_info()->then(function ($user_detail) {
                if (!empty($user_detail)) {
                    $this->log(sprintf(
                        "Logged as => %s%s%s [%s%s%s]",
                        $this->col->g,
                        $user_detail['name'],
                        $this->col->r,
                        $this->col->b,
                        $user_detail['mid'],
                        $this->col->r
                    ));
                    $this->hasLogin = true;
                    $this->user_detail = $user_detail;
                } else {
                    $this->log(sprintf(
                        "%sYou extracting video without login, remember it!%s",
                        $this->col->rd,
                        $this->col->r
                    ));
                }
                return $user_detail;
            });
        }
        // empty or not just return it
        return $this->user_detail;
    }

    /**
     * extract audio and video url from webpage.
     *
     * @param string $videoid
     * @return void
     * 
     * @alias webpage
     */
    protected function _extractWebpage(string $videoid)
    {
        $this->log(sprintf("Extracting... [%sWebPage%s:%s%s%s]", $this->col->y, $this->col->r, $this->col->g, $videoid, $this->col->r));
        $this->login()->then(
            function ($user_detail) use ($videoid) {
                $this->get_video_data($videoid)->then(
                    function ($result) use ($videoid) {
                        askvideolist:
                        if (!isset($result['videourl'])) {
                            $this->log(sprintf("%sSelect one from videos list..!%s", $this->col->g, $this->col->r));
                            foreach ($result['video'] as $k => $v) {
                                echo sprintf(
                                    " %s%02d%s. bandwidth:[%s%s%s], Quality:[%s%s%s], Res:[%s%sx%s%s], Fr:[%s%s%s],type:[%s%s%s]\n",
                                    $this->col->w,
                                    $k + 1,
                                    $this->col->r,
                                    $this->col->g,
                                    $v['bandwidth'],
                                    $this->col->r,
                                    $this->col->g,
                                    $this->formats[$v['id']],
                                    $this->col->r,
                                    $this->col->g,
                                    $v['width'],
                                    $v['height'],
                                    $this->col->r,
                                    $this->col->y,
                                    $v['frameRate'],
                                    $this->col->r,
                                    $this->col->g,
                                    explode(
                                        "/",
                                        $v['mime_type']
                                    )[1],
                                    $this->col->r
                                );
                            }

                            askvideo:
                            $select = (int)$this->gets("Choose Video: ");
                            if ($select <= count($result['video'])) {
                                $result['videourl'] = $result['video'][$select - 1]['base_url'];
                            } else {
                                goto askvideo;
                            }
                        }

                        // now, ask for audio quality.
                        askaudiolist:
                        if (!isset($result['audiourl'])) {
                            $this->log(sprintf("%sSelect one from audio list...!%s", $this->col->g, $this->col->r));
                            foreach ($result['audio'] as $k => $v) {
                                echo sprintf(
                                    " %s%02d%s. bandwith:[%s%s%s], Codecs:[%s%s%s], Type:[%s%s%s]\n",
                                    $this->col->w,
                                    $k + 1,
                                    $this->col->r,
                                    $this->col->g,
                                    $v['bandwidth'],
                                    $this->col->r,
                                    $this->col->y,
                                    $v['codecs'],
                                    $this->col->r,
                                    $this->col->g,
                                    explode(
                                        "/",
                                        $v['mime_type']
                                    )[0],
                                    $this->col->r
                                );
                            }

                            askaudio:
                            $select = (int)$this->gets('Choose Audio:: ');
                            if ($select <= count($result['audio'])) {
                                $result['audiourl'] = $result['audio'][$select - 1]['base_url'];
                            } else {
                                goto askaudio;
                            }
                        }

                        // trying download and merger.
                        trymerger:
                        $tryMerger = $this->merger->merger(
                            $result['title'],
                            $result['audiourl'],
                            $result['videourl']
                        );
                        if (!$tryMerger['status']) {
                            switch ($tryMerger['type']) {
                                case 'audio':
                                    unset($result['audiourl']);
                                    goto askaudiolist;
                                case 'video':
                                    unset($result['videourl']);
                                    goto askvideolist;
                            }
                            // try jump, merger again after change unavailable url
                            goto trymerger;
                        }
                    }
                );
            }
        )->wait();
    }

    /**
     * extract with bilibili api's, 
     * @see https://socialsisteryi.github.io/bilibili-API-collect/
     *
     * @param string $videoid
     * @return void
     * @alias api
     */
    protected function _extractApi(string $videoid)
    {
        $this->log(sprintf("Extracting... [%sBiliBiliApi%s:%s%s%s]", $this->col->y, $this->col->r, $this->col->g, $videoid, $this->col->r));
        $this->login()->then(function ($user_detail) use ($videoid) {
            $this->get_video_data($videoid)->then(
                function ($video_data) use ($videoid) {
                    $promises = [];
                    foreach (array_keys($this->formats) as $format) {
                        $this->log(sprintf(
                            "Fetching for quality: %s%s%s",
                            $this->col->g,
                            $this->formats[$format],
                            $this->col->r
                        ));
                        $payload = sprintf(
                            "appkey=%s&cid=%s&otype=json&qn=%s&quality=%s&type=",
                            $this->appkey,
                            $video_data['cid'],
                            $format,
                            $format
                        );
                        $promises[] = $this->client->getAsync(sprintf(
                            'http://interface.bilibili.com/v2/playurl?%s&sign=%s',
                            $payload,
                            md5($payload . $this->bilibilikey)
                        ), [
                            'headers' => [
                                'Accept' => "application/json", 'Referer' => sprintf($this->default_url, $videoid), 'User-Agent' => 'Mozilla/5.0 (Linux; Android 13; LM-Q710(FGN)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.5938.60 Mobile Safari/537.36'
                            ],
                            'cookies' => $this->cookiejar
                        ]);
                    }

                    // wait aysnc request
                    $video_data['media_data'] = array();
                    Promise\Utils::all($promises)->then(function (array $response) use (&$video_data) {
                        foreach ($response as $respon) {
                            $data = json_decode($respon->getBody()->getContents(), true);
                            $short_data = $data['durl'][0];
                            $short_data['quality'] = $this->formats[$data['quality']];
                            $video_data['media_data'][] = $short_data;
                        }
                    })->wait();

                    $this->log(sprintf('%sChoose one for quality list..%s', $this->col->g, $this->col->r));
                    askmedialist:
                    $i = 1;
                    foreach ($video_data['media_data'] as $media) {
                        echo sprintf(
                            " %s%02d%s. Quality: %s%s%s, Size:%s%s%s, type:flv\n",
                            $this->col->w,
                            $i,
                            $this->col->r,
                            $this->col->g,
                            $media['quality'],
                            $this->col->r,
                            $this->col->g,
                            $this->human_size($media['size']),
                            $this->col->r
                        );
                        $i++;
                    }
                    askselectmedia:
                    $select = (int)$this->gets(sprintf("Choose: %s", $this->col->g));
                    if ($select <= count($video_data['media_data'])) {
                        $hasDownloaded = $this->merger->download(
                            $video_data['media_data'][$select - 1]['url'],
                            [
                                'filename' => sprintf(
                                    '%s.flv',
                                    $video_data['title']
                                ),
                                'type' => 'media'
                            ]
                        );
                        /**
                         * download func give message if download failed,
                         * and this for jump ask then download again with other choice.
                         */
                        if (empty($hasDownloaded)) {
                            goto askmedialist;
                        }
                    } else {
                        goto askselectmedia;
                    }
                }
            )->wait();
        })->wait();
    }

    /**
     * extract from external website,
     * @see https://youtube4kdownloader.com/en70/download-bilibili-videos.html
     *
     * @param string $videoid
     * @return void
     * @alias site
     */
    protected function _extractSite(string $videoid)
    {
        $url = trim(sprintf($this->default_url, $videoid), "/");
        $this->log(sprintf("Extracting... [site%s:youtube4kdownloader:%s%s%s]", $this->col->y, $this->col->r, $this->col->g, $videoid, $this->col->r));
        $this->client->getAsync(sprintf(
            "https://s18.youtube4kdownloader.com/ajax/getLinks.php?video=%s&rand=%s",
            urlencode($url),
            $this->convert($url, "dec", 3)
        ), [
            'headers' => [
                "Accept" => "*/*",
                "Referer" => "https://youtube4kdownloader.com/",
                "Origin" => "https://youtube4kdownloader.com/"
            ]
        ])->then(
            function (ResponseInterface $response) {
                $data = json_decode($response->getBody()->getContents(), true);
                if (strtolower($data['status']) == 'success') {
                    $data = $data['data'];
                    $this->log(sprintf("Title: %s%s", $this->col->g, $data['title'], $this->col->r));
                    $this->log(sprintf("Thumbnail: %s%s", $this->col->g, $data['thumbnail'], $this->col->r));

                    $formated = ['a', 'v', 'av'];
                    foreach ($formated as $i => $tp) {
                        if (isset($data[$tp])) {
                            switch ($tp) {
                                case 'a':
                                    echo sprintf(" %s%02d%s. %sDownload audio only%s\n", $this->col->w, $i + 1, $this->col->r, $this->col->g, $this->col->r);
                                    break;
                                case 'v':
                                    echo sprintf(" %s%02d%s. %sDownload video only%s\n", $this->col->w, $i + 1, $this->col->r, $this->col->g, $this->col->r);
                                    break;
                                case 'av':
                                    echo sprintf(" %s%02d%s. %sDownload video with audio%s\n", $this->col->w, $i + 1, $this->col->r, $this->col->g, $this->col->r);
                                    break;
                            }
                        } else {
                            unset($formated[$tp]);
                        }
                    }
                    $select = (int)$this->gets(sprintf('Choose [1-%s]: %s', (string)count($formated), $this->col->g));
                    if ($select <= count($formated)) {
                        $formats = $data[$formated[$select - 1]];
                        askmedialist:
                        foreach ($formats as $k => $v) {
                            echo sprintf(
                                " %s%02d%s. Quality: %s%s%s, Format: %s%s%s\n",
                                $this->col->w,
                                $k + 1,
                                $this->col->r,
                                $this->col->g,
                                $v['quality'],
                                $this->col->r,
                                $this->col->g,
                                $v['ext'],
                                $this->col->r
                            );
                        }
                        askformats:
                        $select = (int)$this->gets(sprintf("Choose [1-%s]: %s", (string)count($formats), $this->col->g));
                        if ($select <= count($formats)) {
                            $hasDownloaded = $this->merger->download(
                                str_replace(
                                    "[[_index_]]",
                                    (string)($select - 1),
                                    $formats[$select - 1]['url']
                                ),
                                [
                                    'filename' => sprintf("%s.%s", $data['title'], $formats[$select - 1]['ext']),
                                    'type' => 'media'
                                ]
                            );
                            /**
                             * not all url working, some download url give html response with message 
                             * 'An error occurred from remote video site. Please try with other download link'.
                             */
                            if (empty($hasDownloaded) || !$this->merger->_isStreamData($hasDownloaded)) {
                                $this->log(sprintf("%sFailed downloading files, try choose other choice!%s", $this->col->y, $this->col->r));
                                goto askmedialist;
                            }
                        } else {
                            goto askformats;
                        }
                    }
                } else {
                    $this->log(sprintf("%sfailed getting video data%s", $this->col->rd, $this->col->r));
                    print_r($data) . PHP_EOL;
                }
            }
        )->wait();
    }

    /**
     * JS function from youtube4kdownloader.com
     * @see https://youtube4kdownloader.com/scripts/js.js?v=94
     * 
     * @param string $url
     * @return string
     * 
     * this actually only for recursive decode url
     */

    private function decode_max(string $url): string
    {
        $urlnew = $url;
        $lasturl = $url;
        for ($i = 0; 10 > $i && (($lasturl = urldecode($urlnew)) && $lasturl !== $urlnew);) {
            $urlnew = $lasturl;
            $i++;
        }
        return $lasturl;
    }

    /**
     * string enc, it's passed in query url &rand=...
     *
     * @param string $string
     * @param string $type
     * @param string $total
     * @return void
     */
    private function convert(string $string, string $type, int $total): string
    {
        $chars = sprintf(
            "%s%s%s",
            implode('', range('A', 'Z')),
            implode('', range('0', '9')),
            implode('', range('a', 'z'))
        );
        $string = strval($string);
        for ($c = 1; $c <= $total;) {
            $enc_str = "";
            for ($i = 0; $i < strlen($string); $i++) {
                $char = $string[$i];
                $pos = strpos($chars, $char);
                if (!$pos) {
                    $enc_str .= $char;
                } else {
                    $enc_pos = ($type === "enc")
                        ? (!isset($chars[$pos + 5]) ? 5 - (strlen($chars) - $pos) : $pos + 5)
                        : (!isset($chars[$pos - 5]) ? (strlen($chars) + $pos) - 5 : $pos - 5);
                    $enc_char = $chars[$enc_pos];
                    $enc_str .= $enc_char;
                }
            }
            $enc_str = strrev($enc_str);
            $string = $enc_str;
            $c++;
        }
        return substr(
            preg_replace('/[^0-9a-z]/i', '', $enc_str),
            0,
            15
        );
    }
}
