<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('/', function () use ($router)
{
    $count = Redis::GET('count');
    if (!$count)
    {
        $count = 1;
    }
    Redis::SET('count', $count + 1);

    return $router->app->version() . '；visit count：' . $count . '；swoole：' . swoole_version();
});
