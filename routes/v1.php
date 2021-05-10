<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->group(['prefix' => 'sign'], function () use ($router)
{
    $router->post('detect', 'SignController@detectAccess');

    $router->post('message', 'SignController@sendMessage');

    $router->post('email', 'SignController@sendEmail');

    $router->post('register', 'SignController@register');

    $router->post('login', 'SignController@login');

    $router->get('captcha', 'SignController@captcha');

    $router->group(['middleware' => 'auth'], function () use ($router)
    {
        $router->get('get_user_info', 'SignController@getUserInfo');

        $router->post('get_user_info', 'SignController@getUserInfo');

        $router->post('logout', 'SignController@logout');

        $router->post('oauth_channel', 'SignController@OauthChannelVerify');

        $router->post('bind_phone', 'SignController@bindPhone');

        $router->post('bind_weapp_user', 'SignController@bindWechatUser');

        $router->post('bind_qq_user', 'SignController@bindQQUser');
    });

    $router->post('get_wechat_phone', 'SignController@getWechatPhone');

    $router->post('wechat_mini_app_login', 'SignController@wechatMiniAppLogin');

    $router->post('wechat_mini_app_get_token', 'SignController@wechatMiniAppToken');

    $router->post('weapp_mini_app_login', 'SignController@wechatMiniAppLogin');

    $router->post('weapp_mini_app_get_token', 'SignController@wechatMiniAppToken');

    $router->post('qq_mini_app_login', 'SignController@qqMiniAppLogin');

    $router->post('qq_mini_app_get_token', 'SignController@qqMiniAppToken');

    $router->post('reset_password', 'SignController@resetPassword');

    $router->group(['prefix' => 'oauth2'], function () use ($router)
    {
        $router->get('ticket', 'SignController@shareTicket');

        $router->post('qq', 'SignController@qqAuthRedirect');

        $router->post('wechat', 'SignController@wechatAuthRedirect');
    });
});

$router->group(['prefix' => 'search'], function () use ($router)
{
    $router->get('mixin', 'SearchController@mixin');

    $router->get('bangumi', 'SearchController@bangumi');
});

$router->group(['prefix' => 'bangumi'], function () use ($router)
{

});

$router->group(['prefix' => 'desk', 'middleware' => 'auth'], function () use ($router)
{
    $router->get('upload_token', 'DeskController@token');

    $router->post('preload', 'DeskController@preload');

    $router->group(['prefix' => 'folder'], function () use ($router)
    {
        $router->get('list', 'DeskController@folders');

        $router->post('create', 'DeskController@createFolder');

        $router->post('update', 'DeskController@updateFolder');

        $router->post('delete', 'DeskController@deleteFolder');
    });

    $router->group(['prefix' => 'file'], function () use ($router)
    {
        $router->get('list', 'DeskController@files');

        $router->post('move', 'DeskController@moveFile');

        $router->post('delete', 'DeskController@deleteFile');
    });
});

$router->group(['prefix' => 'cm'], function () use ($router)
{
    $router->get('image_looper', 'CMController@imageLooper');

    $router->get('index_banner', 'CMController@showBanners');

    $router->post('report_banner', 'CMController@reportBannerStat');
});

$router->group(['prefix' => 'console', 'middleware' => 'auth'], function () use ($router)
{
    $router->group(['prefix' => 'cm'], function () use ($router)
    {
        $router->get('show_all_banner', 'CMController@allBanners');

        $router->post('create_banner', 'CMController@createBanner');

        $router->post('update_banner', 'CMController@updateBanner');

        $router->post('toggle_banner', 'CMController@toggleBanner');
    });

    $router->group(['prefix' => 'role'], function () use ($router)
    {
        $router->get('show_all_roles', 'RoleController@showAllRoles');

        $router->get('show_all_users', 'RoleController@getUsersByCondition');

        $router->post('create_role', 'RoleController@createRole');

        $router->post('create_permission', 'RoleController@createPermission');

        $router->post('toggle_permission_to_role', 'RoleController@togglePermissionToRole');

        $router->post('toggle_role_to_user', 'RoleController@toggleRoleToUser');
    });
});
