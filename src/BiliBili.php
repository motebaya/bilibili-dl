<?php

declare(strict_types=1);

namespace BiliDL;

use \ReflectionClass;
use \ReflectionMethod;
use \Exception;
use BiliDL\Extractor\Extractor;

error_reporting(E_ALL);

class BiliBili extends Extractor
{
    private string $regex = "/http[s]?:\/\/(?:(?:www|m(?:obile)?)\.bilibili\.(?:tv|com)\/video\/([\w]*))/i";
    public function __construct()
    {
        parent::__construct();
        if (empty($this->method_list)) {
            $this->_setMethods();
        }
    }

    public function execute(
        string $urlOrUSer,
        string $methodType,
        bool $bookmark = false
    ) {
        if (array_key_exists($methodType, $this->method_list)) {
            if (!$bookmark) {
                if (preg_match($this->regex, $urlOrUSer, $bvid)) {
                    call_user_func(
                        [$this, $this->method_list[$methodType]],
                        $bvid[1]
                    );
                }
            } else {
                $this->_fromBookmark(
                    $urlOrUSer,
                    $this->method_list[$methodType]
                );
            }
        } else {
            throw new \Exception(sprintf(
                "%sInvalid method name, method doesn't exist in -> %s%s%s!%s",
                $this->col->rd,
                $this->col->r,
                $this->col->y,
                implode(",", array_keys(
                    $this->method_list
                )),
                $this->col->r
            ));
        }
    }

    private function _fromBookmark(string $userid, string $methodType)
    {
        if (preg_match('/^[0-9]+$/', $userid)) {
            $this->log(sprintf("Getting bookmark list .. [%s%s%s]", $this->col->g, $userid, $this->col->r));
            $this->get_user_bookmark((string)$userid)->then(
                function ($bookmark_result) use ($userid, $methodType) {
                    if (!empty($bookmark_result)) {
                        $bookmark = $bookmark_result['list'];
                        $this->log(sprintf("Found Total [%s%s%s] favorite list", $this->col->g, $bookmark_result['count'], $this->col->r));
                        // show bookmark info
                        foreach ($bookmark as $i => $v) {
                            echo sprintf(
                                " %s%02d%s. id: %s%s%s, name: %s%s%s, total: %s%s%s\n",
                                $this->col->w,
                                $i + 1,
                                $this->col->r,
                                $this->col->g,
                                $v['id'],
                                $this->col->r,
                                $this->col->g,
                                $v['title'],
                                $this->col->r,
                                $this->col->y,
                                $v['media_count'],
                                $this->col->r
                            );
                        }

                        askbookmarkid:
                        $book = (int)$this->gets(sprintf("Choose: %s", $this->col->g));
                        if ($book <= count($bookmark)) {
                            $s_bookmark = $bookmark[$book - 1];

                            // fetch video list from bookmark ID
                            $bookmark_items = $this->get_bookmark_video((string)$s_bookmark['id']);
                            if (!empty($bookmark_items)) {
                                $media_count = $bookmark_items['info']['media_count'];
                                $this->log(sprintf("Found total (%s%s%s) videos list from %s%s%s", $this->col->y, $media_count, $this->col->r, $this->col->g, $s_bookmark['title'], $this->col->r));
                                $media_lists = $bookmark_items['medias'];
                                $bvid = [];

                                // show video list info
                                download_check:
                                foreach ($media_lists as $i => $v) {
                                    echo sprintf(
                                        " %s%02d%s. Title: %s%s%s, thumbnail: %s%s%s, id: %s%s%s, uploaded: %s%s%s\n",
                                        $this->col->w,
                                        $i + 1,
                                        $this->col->r,
                                        $this->col->g,
                                        $v['title'],
                                        $this->col->r,
                                        $this->col->g,
                                        $v['cover'],
                                        $this->col->r,
                                        $this->col->g,
                                        $v['bvid'],
                                        $this->col->r,
                                        $this->col->g,
                                        date("Y-m-d H:i:s", $v['pubtime']),
                                        $this->col->r
                                    );
                                    $bvid[] = $v['bvid'];
                                }

                                askconfirm:
                                $confirm = strtolower($this->gets(sprintf("Download all %s%s%s videos? [y/n]: %s", $this->col->y, $media_count, $this->col->r, $this->col->g)));
                                if (in_array($confirm, ['y', 'n'])) {
                                    switch ($confirm) {
                                        case "y":
                                            $i = 0;
                                            while ($i <= count($bvid)) {
                                                $this->log(sprintf("Process.. :: %s%s%s", $this->col->g, $bvid[$i], $this->col->r));
                                                call_user_func(
                                                    [$this, $methodType],
                                                    $bvid[$i]
                                                );
                                                sleep(1);
                                            }
                                            break;
                                        case 'n':
                                            askmedia_id:
                                            $select = (int)$this->gets(sprintf("Choose [1-%s]:%s ", (string)count($bvid), $this->col->g));
                                            if ($select <= count($bvid)) {
                                                call_user_func(
                                                    [$this, $methodType],
                                                    $bvid[$select - 1]
                                                );
                                                sleep(1);
                                                goto askmedia_id;
                                            } else {
                                                $this->log(sprintf("%sMust < %s%s", $this->col->rd, (string)count($bvid), $this->col->r));
                                                sleep(1);
                                                goto askmedia_id;
                                            }
                                    }
                                } else {
                                    goto askconfirm;
                                }
                            } else {
                                $this->log(sprintf("%sFailed get videos list from bookmark %s(%s%s%s)", $this->col->rd, $this->col->r, $this->col->y, $s_bookmark['id'], $this->col->r));
                            }
                        } else {
                            goto askbookmarkid;
                        }
                    } else {
                        $this->log(sprintf(
                            "%sCannot get bookmark %s[%s%s%s] list, user doesn't have any bookmark%s",
                            $this->col->rd,
                            $this->col->r,
                            $this->col->y,
                            $userid,
                            $this->col->r,
                            $this->col->r
                        ));
                        die;
                    }
                }
            )->wait();
        }
    }

    /**
     * method set to array from aliases method name.
     * @see https://stackoverflow.com/questions/2531085/are-there-any-php-docblock-parser-tools-available
     * 
     * @return void
     */
    private function _setMethods(): void
    {
        $reflectionClass = new \ReflectionClass($this);
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PROTECTED) as $method) {
            $methodNames = $method->getName();
            if (preg_match('/^_extract/', $methodNames)) {
                if (preg_match('/(?<=@alias\s)([\w]*)/', $method->getDocComment(), $match)) {
                    $this->method_list[$match[1]] = $methodNames;
                } else {
                    throw new \Exception(
                        "Error when getting method list!"
                    );
                }
            }
        }
    }
}
