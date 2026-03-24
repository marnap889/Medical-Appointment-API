<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$appEnv = $_SERVER['APP_ENV'] ?? 'dev';
$appDebug = (bool) ($_SERVER['APP_DEBUG'] ?? false);

$kernel = new Kernel($appEnv, $appDebug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
