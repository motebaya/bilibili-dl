<?php

declare(strict_types=1);

namespace BiliDL;

use \GuzzleHttp\Client;
use \GuzzleHttp\Cookie\CookieJar;
use \GuzzleHttp\Promise\Promise;
use \GuzzleHttp\Cookie\SetCookie;

class Init
{
    public object $col;
    protected $client;
    protected $promise;
    public $cookiejar;
    protected string $cookiefile = __DIR__ . "/../cookies";

    public function __construct()
    {
        $this->cookiejar = new CookieJar();
        $this->promise = new Promise();
        $this->client = new Client([
            "headers" => [
                "User-Agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36"
            ]
        ]);

        /**
         * custom color
         */
        $this->col = (object)[
            "y" => "\033[33m", "rd" => "\033[31m",
            "g" => "\033[32m", "b" => "\033[34m",
            "w" => "\033[1;37m", "r" => "\033[0m",
            "gr" => "\033[90m", "bk" => "\033[30m"
        ];

        /**
         * cookie check and set.
         * IF: you have cookie and not checked yet valid or not,
         *  cookie will be set to instance Cookiejar
         */
        $cookie = $this->check_cookie();
        if (!empty($cookie)) {
            $this->setCookie($cookie);
        }
    }

    /**
     * convert cookie string to array key => value
     *
     * @param string $strcookie
     * @return array
     */
    protected function CookieToArray(string $strcookie): array
    {
        return array_reduce(
            explode(";", $strcookie),
            function ($cookies, $cok) {
                list($key, $val) = explode("=", $cok, 2);
                $cookies[trim($key)] = trim($val);
                return $cookies;
            },
            []
        );
    }

    /**
     * set cookie to client
     *
     * @param string $strcookie
     * @param string $domain
     * @return void
     */
    protected function setCookie(
        string $strcookie,
        string $domain = 'bilibili.com'
    ): void {
        $this->cookiejar = CookieJar::fromArray(
            $this->CookieToArray(
                $strcookie
            ),
            $domain
        );
    }

    /**
     * check cookie file and return string content
     *
     * @return string
     */
    public function check_cookie(): string
    {
        if (file_exists($this->cookiefile)) {
            if (filesize($this->cookiefile) > 0) {
                return file_get_contents($this->cookiefile);
            }
            return "";
        }
        return "";
    }

    /**
     * log message with current time
     *
     * @param string $msg
     * @return void
     */
    public function log(string $msg, bool $line = true): void
    {
        $message = sprintf(
            " [%s%s%s]%s:: %s%s",
            $this->col->b,
            date("H:i:s"),
            $this->col->r,
            $this->col->g,
            $this->col->r,
            $msg
        );
        if ($line) {
            echo $message . PHP_EOL;
        } else {
            echo $message;
        }
    }

    /**
     * console input
     *
     * @param string $msg
     * @return string
     */
    public function gets(string $msg): string
    {
        while (1) {
            $this->log($msg, false);
            $i = trim(fgets(STDIN));
            if (!empty($i)) {
                return $i;
            }
            continue;
        }
    }

    /**
     * hyperlink for cover
     * here: https://gist.github.com/egmontkob/eb114294efbcd5adb1944c9f3cb5feda
     * @param string $url
     * @param string $text
     * @return void
     */
    protected function create_hyperlink(string $url, string $text): void
    {
        echo sprintf(
            "\033]8;;%s\033\\%S\033]8;;\033\\",
            $url,
            $text
        );
    }


    /**
     * human readable fileszie
     * https://stackoverflow.com/questions/15188033/human-readable-file-size
     *
     * @param integer $length
     * @return string
     */
    protected function human_size(int $length): string
    {
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($length, 1024));
        return sprintf(
            "%s %s",
            round($length / (1024 ** $i), 2),
            $sizes[$i]
        );
    }

    public function gStrings(int $length = 15)
    {
        $string = sprintf(
            "%s%s%s",
            implode('', range('0', '9')),
            implode('', range('a', 'z')),
            implode('', range('Z', 'Z'))
        );
        return substr(
            str_shuffle(
                str_repeat(
                    $string,
                    (int)ceil(
                        $length / strlen($string)
                    )
                )
            ),
            1,
            $length
        );
    }
}
