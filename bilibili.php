<?php

/*
Here CLI Main, 
and bruh, here just simple code that make it with basic knowledge.
credit: https://github.com/motebaya
*/
error_reporting(E_ALL);

include "../vendor/autoload.php";

include __DIR__ . "/lib/BiliBiliAPI.php";
include __DIR__ . "/lib/BiliBiliWeb.php";
include __DIR__ . "/lib/Merger.php";

echo "
[Author] @github/motebaya
[Input] Batch/File ? url/single : ";
echo "\n";
$ext = trim(fgets(STDIN));
if (preg_match("/(^https?\:\/\/)/", $ext, $match)) {
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

// $url = "https://www.bilibili.com/video/BV1784y1t7MR/";
// $home = BiliBili::extract($url);
// print_r($home);
// $info = BiliBili::get_video_info($home);
// print_r($info);
// (new Merger())->processApi($info);
// $mer = new Merger();
// $extra = BiliWeb::extract("");
// print_r($extra);
// $info = BiliWeb::get_video_info($extra);
// print_r($info);
// $mer->process($info);

// sample call
// $mobile = "https://b23.tv/SjAttex";
// $desktop = "https://www.bilibili.com/video/BV1784y1t7MR";
// $web = BiliWeb::extract($desktop);
// $play = BiliWeb::get_video_info($web);
// print_r($play);
