<?php

require __DIR__ . '/../../vendor/autoload.php';

use Luminus\App;

$config = require __DIR__ . '/../../config/app.php';
$config['views_path'] = __DIR__ . '/views';

$app = new App();
$app->loadConfig($config);
$app->loadRoutes(__DIR__ . '/routes.php');
$app->run();
