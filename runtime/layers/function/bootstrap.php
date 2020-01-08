<?php declare(strict_types=1);

use Bref\Runtime\LambdaRuntime;

ini_set('display_errors', '1');
error_reporting(E_ALL);

$appRoot = getenv('LAMBDA_TASK_ROOT');

/** @noinspection PhpIncludeInspection */
require $appRoot . '/vendor/autoload.php';

$lambdaRuntime = LambdaRuntime::fromEnvironmentVariable();

$handlerFile = $appRoot . '/' . getenv('_HANDLER');
if (! is_file($handlerFile)) {
    $lambdaRuntime->failInitialization("Handler `$handlerFile` doesn't exist");
}

/** @noinspection PhpIncludeInspection */
$handler = require $handlerFile;

if (! $handler) {
    $lambdaRuntime->failInitialization("Handler `$handlerFile` must return a function or Handler object");
}

$loopMax = getenv('BREF_LOOP_MAX') ?: 1;
$loops = 0;
while (true) {
    if (++$loops > $loopMax) {
        exit(0);
    }
    $lambdaRuntime->processNextEvent($handler);
}
