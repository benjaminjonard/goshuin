<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if (!(bool) $_SERVER['APP_DEBUG']) {
    (new Filesystem())->remove(dirname(__DIR__).'/var/cache/test');
}
