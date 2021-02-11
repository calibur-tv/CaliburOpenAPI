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
        $router->get('/qq', 'CallbackController@qqAuthRedirect');

        $router->get('/wechat', 'CallbackController@wechatAuthRedirect');

        $router->get('/weixin', 'CallbackController@weixinAuthRedirect');
    });

    $router->group(['prefix' => '/oauth2'], function () use ($router)
    {
        $router->get('/qq', 'CallbackController@qqAuthEntry');

        $router->get('/wechat', 'CallbackController@wechatAuthEntry');

        $router->get('/weixin', 'CallbackController@weixinAuthEntry');
    });

    $router->group(['prefix' => '/oss'], function () use ($router)
    {
        $router->get('/upload', 'CallbackController@aliyunOSSupload');
    });
});
