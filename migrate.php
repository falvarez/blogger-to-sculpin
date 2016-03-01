#!/usr/bin/php
<?php
require 'vendor/autoload.php';

use Falvarez\Sculpin\Utils\MigrateScript;

date_default_timezone_set('Europe/Madrid');
setlocale(LC_ALL, 'es_ES');

$logger = new \Monolog\Logger('default');

$configuration = include('config.php');
$migrateScript = new MigrateScript($argv[1], $configuration, $logger);
$migrateScript->run();
