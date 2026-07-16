<?php

declare(strict_types=1);

use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Interface\Api\Http\Request as ApiRequest;
use OrderHub\Interface\Api\Kernel as ApiKernel;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Kernel as WebKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH);
$path = \is_string($path) ? $path : '/';

$container = new Container();

if (str_starts_with($path, '/api/v1')) {
    (new ApiKernel($container))->handle(ApiRequest::fromGlobals())->send();

    return;
}

if (str_starts_with($path, '/app')) {
    (new WebKernel($container))->handle(WebRequest::fromGlobals())->send();

    return;
}

http_response_code(404);
echo 'Not Found';
