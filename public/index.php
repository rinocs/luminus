<?php

require __DIR__ . '/../vendor/autoload.php';

use Luminus\App;

$app = new App();
$app->loadEnv();
$app->loadConfig(require __DIR__ . '/../config/app.php');
$app->loadRoutes(__DIR__ . '/../routes/web.php');
$app->run();
