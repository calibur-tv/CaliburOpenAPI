<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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
    return 'hello guys~';
});

$router->group(['prefix' => '/callback'], function () use ($router)
{
    $router->group(['prefix' => '/auth'], function () use ($router)
    {
        $router->get('/qq', 'AuthController@qqAuthRedirect');

        $router->get('/wechat', 'AuthController@wechatAuthRedirect');

        $router->get('/weixin', 'AuthController@weixinAuthRedirect');
    });

    $router->group(['prefix' => '/oauth2'], function () use ($router)
    {
        $router->get('/qq', 'AuthController@qqAuthEntry');

        $router->get('/wechat', 'AuthController@wechatAuthEntry');

        $router->get('/weixin', 'AuthController@weixinAuthEntry');
    });
});
