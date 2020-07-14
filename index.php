<?php

use LINE\LINEBot\RemindMe\Dependency;
use LINE\LINEBot\RemindMe\Route;
use LINE\LINEBot\RemindMe\Setting;

require_once __DIR__ . '/vendor/autoload.php';

$setting = Setting::getSetting();
$app = new \Slim\App($setting);

(new Route()) -> register($app);
(new Dependency()) -> register($app);

$app -> run();