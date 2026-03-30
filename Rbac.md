### Rbac 鉴权时序图

* 图中提到的中间件名称空间都是 `App\Http\Middleware`
* `checkLogin` 和 `login` 方法在 `App\Http\Controllers\Admin\AdministratorController` 中定义
* `index` 方法在 `App\Http\Controllers\Admin\IndexController` 中定义
* AdminUser 的名称空间是 App

```sequence
Title: 首次登入鉴权过程

浏览器->Router: GET /
Router->CheckSession 中间件: 交由 CheckSession中间件
Note over CheckSession 中间件: 检查 Session 是否存在 admin 字段
Note Over CheckSession 中间件: 首次访问, Session 不存在 admin 字段
CheckSession 中间件->Router:  返回 redirect(/login)
Router->浏览器: 重定向到 GET /login
浏览器->Router: GET /login
Router->login 方法: GET /login 直接分发到 loginView 方法
login 方法->Router: 返回视图(View 实例)
Router->浏览器: 返回 HTML 代码
Note over 浏览器: 渲染 /login 页面
Note Over 浏览器: 用户输入账号密码，提交
浏览器->Router: POST /login
Router->checkLogin 方法: POST /login 直接分发到 checkLogin 方法
Note Over checkLogin 方法: 检查账户密码
Note Over checkLogin 方法: 存入 AdminUser 实例到 Session admin 字段
checkLogin 方法->Router: 鉴权成功,返回 Json Response 
Router->浏览器: 输出 { code: 200, msg: '登录成功'}
Note over 浏览器: (Javascript) 自行跳转到 /
浏览器->Router: GET /
Router->CheckSession 中间件: 检查 Session 是否存在 admin
CheckSession 中间件->Rbac 中间件: 检查通过, 交由 Rbac 中间件
Note over Rbac 中间件: 从 Session 的 admin 字段获取 AdminUser 实例
Note over Rbac 中间件: 获得 AdminUser 实例的授权路由集合，比对当前路由 (/)
Note over Rbac 中间件: 检查通过
Rbac 中间件->index 方法: 发起 index 方法调用
Note over index 方法: 从 Session 的 admin 字段获取 AdminUser 实例
Note over index 方法: 从 AdminUser 实例获取当前登录用户的菜单集合
Note over index 方法: 传递菜单集合到 View 实例
index 方法->Router: 返回 View 实例
Router->浏览器: 返回 HTML 代码
Note over 浏览器: 渲染 / 页面
```
