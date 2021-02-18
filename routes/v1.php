<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->group(['prefix' => 'sign'], function () use ($router)
{
    $router->post('/detect', 'SignController@detectAccess');

    $router->post('/message', 'SignController@sendMessage');

    $router->post('/register', 'SignController@register');

    $router->post('/login', 'SignController@login');

    $router->group(['middleware' => 'auth'], function () use ($router)
    {
        $router->post('/get_user_info', 'SignController@getUserInfo');

        $router->post('/logout', 'SignController@logout');

        $router->post('/oauth_channel', 'SignController@OauthChannelVerify');

        $router->post('/bind_phone', 'SignController@bindPhone');

        $router->post('/bind_weapp_user', 'SignController@bindWechatUser');

        $router->post('/bind_qq_user', 'SignController@bindQQUser');
    });

    $router->post('/get_wechat_phone', 'SignController@getWechatPhone');

    $router->post('/wechat_mini_app_login', 'SignController@wechatMiniAppLogin');

    $router->post('/wechat_mini_app_get_token', 'SignController@wechatMiniAppToken');

    $router->post('/weapp_mini_app_login', 'SignController@wechatMiniAppLogin');

    $router->post('/weapp_mini_app_get_token', 'SignController@wechatMiniAppToken');

    $router->post('/qq_mini_app_login', 'SignController@qqMiniAppLogin');

    $router->post('/qq_mini_app_get_token', 'SignController@qqMiniAppToken');

    $router->post('/reset_password', 'SignController@resetPassword');

    $router->group(['prefix' => '/oauth2'], function () use ($router)
    {
        $router->get('/ticket', 'SignController@shareTicket');

        $router->post('/qq', 'SignController@qqAuthRedirect');

        $router->post('/wechat', 'SignController@wechatAuthRedirect');
    });
});

$router->group(['prefix' => '/upload'], function () use ($router)
{
    $router->group(['middleware' => 'auth'], function () use ($router)
    {
        $router->get('/token', 'UploadController@token');
    });
});
