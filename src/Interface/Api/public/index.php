<?php

declare(strict_types=1);

use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Kernel;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$kernel = new Kernel(new Container());
$kernel->handle(Request::fromGlobals())->send();
