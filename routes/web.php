<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use Illuminate\Support\Facades\Redis;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router)
{
    $count = Redis::GET('count');
    if (!$count)
    {
        $count = 1;
    }
    Redis::SET('count', $count + 1);

    $user = \App\Models\User::find(1);

    return response([
        'app_version' => $router->app->version(),
        'swoole_version' => swoole_version(),
        'php_version' => phpversion(),
        'counter' => $count,
        'user' => $user
    ]);
});
