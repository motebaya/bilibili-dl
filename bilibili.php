<?php

/*
Here CLI Main, 
and bruh, here just simple code that make it with basic knowledge.
credit: https://github.com/motebaya
*/
error_reporting(E_ALL);

include "vendor/autoload.php";

include __DIR__ . "/lib/BiliBiliAPI.php";
include __DIR__ . "/lib/BiliBiliWeb.php";
include __DIR__ . "/lib/Merger.php";

echo "
[Author] @github/motebaya
[Input] Batch/File ? url/single : ";
$ext = trim(fgets(STDIN));
echo "\n";
if (preg_match("/(^https?\:\/\/)/", $ext, $match)) {
    /* remove this comment if want to change method to API */
    // (new Merger())->processApi(
    //     BiliBili::get_video_info(
    //         BiliBili::extract(
    //             $ext
    //         )
    //     )
    // );

    (new Merger())->process(
        BiliWeb::get_video_info(
            BiliWeb::extract(
                $ext
            )
        )
    );
} else {
    $fullpath = __DIR__ . "/" . $ext;
    if (file_exists($fullpath)) {
        $files = explode("\n", trim(file_get_contents($fullpath)));
        foreach ($files as $key => $urls) {
            printf(" [run] proc: %s Of %s\n\n", $key + 1, count($files));
            // (new Merger())->processApi(
            //     BiliBili::get_video_info(
            //         BiliBili::extract(
            //             $urls
            //         )
            //     )
            // );
            (new Merger())->process(
                BiliWeb::get_video_info(
                    BiliWeb::extract(
                        $urls
                    )
                )
            );
        }
    }
}
