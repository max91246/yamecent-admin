<?php

// TG Webhook（TG 伺服器呼叫，無 JWT）
$router->post('/tg/webhook/{botId}', 'Api\TgWebhookController@handle');

// 後台管理員認證（pure-admin 使用）
$router->post('/admin/auth/login',         'Api\AdminAuthController@login');
$router->post('/admin/auth/refresh-token', 'Api\AdminAuthController@refreshToken');

// 公開路由（不需要 Token）
$router->post('/auth/login',       'Api\AuthController@login');
$router->post('/auth/tg-login',    'Api\TgAuthController@login');
$router->post('/members/register', 'Api\MemberController@register');

// 文章（公開）
$router->get('/articles',                   'Api\ArticleController@index');
$router->get('/articles/{id}',              'Api\ArticleController@show');

// 留言（公開讀取）
$router->get('/articles/{id}/comments',     'Api\CommentController@index');

// 首頁統計
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/dashboard'], function ($router) {
    $router->get('/stats', 'System\DashboardController@stats');
});

// AV 影片
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/av'], function ($router) {
    $router->post('/videos',           'System\AvController@videos');
    $router->put('/videos/{id}',       'System\AvController@updateVideo');
    $router->delete('/videos/{id}',    'System\AvController@destroyVideo');
    $router->post('/actresses',        'System\AvController@actresses');
    $router->put('/actresses/{id}',    'System\AvController@updateActress');
    $router->delete('/actresses/{id}', 'System\AvController@destroyActress');
});

// TG Bot
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/tg'], function ($router) {
    $router->get('/bots/all',          'System\TgController@allBots');
    $router->post('/bots',             'System\TgController@bots');
    $router->post('/bots/create',      'System\TgController@createBot');
    $router->put('/bots/{id}',         'System\TgController@updateBot');
    $router->delete('/bots/{id}',      'System\TgController@destroyBot');
    $router->post('/bots/{id}/webhook','System\TgController@setWebhookManual');
    $router->post('/messages',         'System\TgController@messages');
    $router->post('/holdings',         'System\TgController@holdings');
    $router->post('/trades',           'System\TgController@trades');
    $router->get('/holdings/{chatId}', 'System\TgController@userDetail');
});

// 股票工具
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/stock'], function ($router) {
    $router->post('/disposals',        'System\StockController@disposals');
    $router->post('/oil-prices',       'System\StockController@oilPrices');
});

// 系統設定
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/config'], function ($router) {
    $router->post('/',                 'System\ConfigController@index');
    $router->post('/create',           'System\ConfigController@store');
    $router->put('/{id}',             'System\ConfigController@update');
    $router->delete('/{id}',          'System\ConfigController@destroy');
});

// 會員管理
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/member'], function ($router) {
    $router->post('/',                    'System\MemberController@index');
    $router->post('/create',              'System\MemberController@store');
    $router->put('/{id}',                'System\MemberController@update');
    $router->delete('/{id}',             'System\MemberController@destroy');
    $router->post('/{id}/activate',      'System\MemberController@activateMembership');
    $router->post('/{id}/revoke',        'System\MemberController@revokeMembership');
    $router->post('/balance/logs',       'System\MemberController@balanceLogs');
    $router->post('/balance/adjust',     'System\MemberController@balanceAdjust');
});

// 文章管理
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/article'], function ($router) {
    $router->post('/',              'System\ArticleController@index');
    $router->post('/create',        'System\ArticleController@store');
    $router->put('/{id}',          'System\ArticleController@update');
    $router->delete('/{id}',       'System\ArticleController@destroy');
    $router->post('/comments',      'System\ArticleController@comments');
    $router->put('/comments/{id}',  'System\ArticleController@updateComment');
    $router->delete('/comments/{id}','System\ArticleController@destroyComment');
});

// 後台系統管理（需要 admin JWT）
$router->group(['middleware' => 'admin.auth', 'prefix' => 'admin/system'], function ($router) {
    // 動態路由（登入後取菜單）
    $router->get('/get-async-routes', 'System\MenuController@asyncRoutes');

    // 菜單
    $router->post('/menu',        'System\MenuController@index');
    $router->post('/menu/create', 'System\MenuController@store');
    $router->put('/menu/{id}',    'System\MenuController@update');
    $router->delete('/menu/{id}', 'System\MenuController@destroy');

    // 角色
    $router->post('/role',            'System\RoleController@index');
    $router->post('/role/create',     'System\RoleController@store');
    $router->put('/role/{id}',        'System\RoleController@update');
    $router->delete('/role/{id}',     'System\RoleController@destroy');
    $router->get('/list-all-role',    'System\RoleController@listAll');
    $router->post('/role-menu',       'System\RoleController@menuTree');
    $router->post('/role-menu-ids',   'System\RoleController@menuIds');
    $router->post('/role-menu/save',  'System\RoleController@saveMenus');

    // 用戶
    $router->post('/user',             'System\UserController@index');
    $router->post('/user/create',      'System\UserController@store');
    $router->put('/user/{id}',         'System\UserController@update');
    $router->delete('/user/{id}',      'System\UserController@destroy');
    $router->post('/list-role-ids',    'System\UserController@roleIds');
});

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
