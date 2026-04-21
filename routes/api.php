<?php

// TG Webhook（TG 伺服器呼叫，無 JWT）
$router->post('/tg/webhook/{botId}', 'Api\TgWebhookController@handle');

// 公開路由（不需要 Token）
$router->post('/auth/login',       'Api\AuthController@login');
$router->post('/auth/tg-login',    'Api\TgAuthController@login');
$router->post('/members/register', 'Api\MemberController@register');

// 文章（公開）
$router->get('/articles',                   'Api\ArticleController@index');
$router->get('/articles/{id}',              'Api\ArticleController@show');

// 留言（公開讀取）
$router->get('/articles/{id}/comments',     'Api\CommentController@index');

// 需要 Token 的路由
$router->group(['middleware' => 'api.auth'], function ($router) {
    $router->post('/auth/logout',              'Api\AuthController@logout');
    $router->get('/members/{id}',              'Api\MemberController@show');
    $router->post('/members/{id}/profile',     'Api\MemberController@updateProfile');
    $router->get('/members/{id}/transactions',          'Api\MemberController@transactions');
    $router->post('/members/{id}/membership/apply',     'Api\MemberController@applyMembership');

    // 留言（需要登入才能發表）
    $router->post('/articles/{id}/comments',   'Api\CommentController@store');
});
