# BILIBILI-DL

just for download video from [bilibili.com](bilibili.com)

## usage:
```
[Input] Batch/File ? url/single : https://www.bilibili.com/video/<id>/
[extractWebPage] ...
[checkUrl] https://www.bilibili.com/video/<id>/
[gettingInfo] 919225848
[Author] -L 枫-
[Title] 喝水就喝百岁珊 🤤
[Description] 音乐：Hurt
喝水就喝百岁珊 🤤_videoOnly.mp4: 867611/867611 [============================] 100% 6.0 MiB
喝水就喝百岁珊 🤤_audioOnly.mp3: 191440/191440 [============================] 100% 6.0 MiB
[Merger] To: 喝水就喝百岁珊 🤤.mp4
[done] saved!
```
 + input bilibili url or url list.
 + if use without api, that will be saving 3 file:
    + audio only
    + video only
    + and result from merger with ffmpeg
 + uncomment [this line](https://github.com/motebaya/bilibili-dl/blob/main/bilibili.php#L22) for use api method

###  Note:
+ can't get max quality, must login or subscribe to bilibili for get more high quality
+ Max is 480P and this not bad ,its better than when u downlad from mobile app will get full of <b>WATERMARK</b> and not all video posible to download

![quality](https://github.com/motebaya/bilibili-dl/blob/main/src/2022-12-26%2004-40-51_crrop.png?raw=true)

+ download batch is not recomended when using [ffmpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)

## Requirement:
+ PHP 7.4.33 version.
+ https://github.com/PHP-FFMpeg/PHP-FFMpeg
+ https://github.com/guzzle/guzzle
+ https://github.com/symfony/symfony
