<?php

declare(strict_types=1);

include_once __DIR__ . "/vendor/autoload.php";

use \GetOpt\GetOpt;
use \GetOpt\Option;
use \BiliDL\BiliBili;

class CLI
{
    private $init;
    public function __construct()
    {
        $this->init = new BiliBili();
    }

    /**
     * main CLI
     *
     * @return void
     */
    public function main(): void
    {
        echo PHP_EOL . "\t    bilibili downloader\n       @github.com/motebaya/bilibili-dl" . PHP_EOL . PHP_EOL;
        $args = new GetOpt();
        $args->addOptions([
            Option::create(
                'u',
                'url',
                GetOpt::REQUIRED_ARGUMENT
            )->setDescription('spesific single url or user id to download'),
            Option::create(
                'b',
                'bookmark',
                GetOpt::OPTIONAL_ARGUMENT
            )->setDescription('download from user bookmark by user id'),
            Option::create(
                't',
                'type',
                GetOpt::OPTIONAL_ARGUMENT
            )->setDescription("method type set: [web, api, site(youtube4kdownloader.com)].read docs how to used!")
        ]);
        try {
            $args->process();
            $opts = $args->getOptions();
            if (!empty($opts)) {
                if (isset($opts['url']) && isset($opts['type'])) {
                    $bookmark = isset($opts['bookmark']) ? $opts['bookmark'] : false;
                    // do execute
                    $this->init->execute(
                        $opts['url'],
                        $opts['type'],
                        $bookmark = (bool)$bookmark
                    );
                    return;
                } else {
                    die($args->getHelpText());
                }
            } else {
                die($args->getHelpText());
            }
        } catch (\GetOpt\ArgumentException | \GetOpt\ArgumentException\Missing $e) {
            throw new \GetOpt\ArgumentException(
                "\033[91mException:: \033[33mOption value required!\033[0m"
            );
        }
    }
}

(new CLI())->main();
