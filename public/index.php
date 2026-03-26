<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('APP_ROOT', dirname(__DIR__));

$autoloader = new \DxEngine\Core\Autoloader();
$autoloader->register();

$dotenv = \Dotenv\Dotenv::createImmutable(APP_ROOT);
$dotenv->load();

\DxEngine\Core\Config\ConfigRegistry::getInstance()->set('app', require APP_ROOT . '/config/app.php');
\DxEngine\Core\Config\ConfigRegistry::getInstance()->set('database', require APP_ROOT . '/config/database.php');

$router = new \DxEngine\Core\Router();
$router->dispatch($_SERVER);
