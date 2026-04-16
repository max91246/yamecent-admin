<!DOCTYPE html>
<html lang="zh-TW">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>登錄</title>
  <link rel="stylesheet" href="/assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="/assets/vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="shortcut icon" href="/assets/images/favicon.png" />
  <style>
    /* 全螢幕背景，fallback 深色漸層（防止圖片未上傳時空白） */
    body {
      background-color: #0f1428;
      background-image: url('/assets/images/login-bg.jpg');
      background-size: cover;
      background-position: 20% center;
      background-attachment: fixed;
      min-height: 100vh;
    }

    /* 暗化遮罩 */
    .login-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.45);
      z-index: 0;
    }

    /* 確保內容在遮罩上層 */
    .container-scroller {
      position: relative;
      z-index: 1;
    }

    /* 玻璃霧化卡片 */
    .login-glass-card {
      background: rgba(15, 20, 40, 0.55);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 16px;
    }

    /* 標題 */
    .login-title {
      color: #fff;
      font-weight: 700;
      text-align: center;
      letter-spacing: 2px;
    }

    /* 輸入框 */
    .login-glass-card .form-control {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
      border-radius: 8px;
    }

    .login-glass-card .form-control::placeholder {
      color: rgba(255, 255, 255, 0.55);
    }

    .login-glass-card .form-control:focus {
      background: rgba(255, 255, 255, 0.2);
      border-color: rgba(100, 180, 255, 0.6);
      box-shadow: 0 0 0 0.2rem rgba(100, 180, 255, 0.2);
      color: #fff;
    }

    /* 登入按鈕圓角加大 */
    .login-glass-card .btn {
      border-radius: 8px;
      letter-spacing: 2px;
    }

    /* 覆蓋原有 auth 背景 */
    .content-wrapper.auth {
      background: transparent !important;
    }

    .auth-form-light {
      background: transparent !important;
      box-shadow: none !important;
    }
  </style>
</head>

<body>
  <!-- 暗化遮罩 -->
  <div class="login-overlay"></div>

  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-center auth">
        <div class="row w-100">
          <div class="col-lg-4 col-md-6 ml-auto mr-4 mt-5">
            <div class="login-glass-card p-5">
              <h4 class="login-title mb-4">後台管理系統</h4>
              <form class="pt-2">
                <div class="form-group">
                  <input type="text" class="form-control form-control-lg" id="account" placeholder="請輸入帳號">
                </div>
                <div class="form-group">
                  <input type="password" class="form-control form-control-lg" id="password" placeholder="請輸入密碼">
                </div>
                <div class="mb-2">
                  <button type="button" onclick="login()" class="btn btn-gradient-info btn-lg btn-block">
                    登錄
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="/assets/vendors/js/vendor.bundle.base.js"></script>
  <script src="/assets/vendors/js/vendor.bundle.addons.js"></script>
  <script src="/assets/js/off-canvas.js"></script>
  <script src="/assets/js/misc.js"></script>
  <script src="https://code.jquery.com/jquery-3.0.0.min.js"></script>
  <script src="/assets/layer/layer.js"></script>
  <script src="/assets/js/common.js"></script>
</body>
<script>
  document.onkeydown = keyListener;
  function keyListener(e) {
    if (e.keyCode == 13) {
      login();
    }
  }

  function login() {
    var account = $("#account").val();
    var password = $("#password").val();
    if (!account || !password) {
      layer.msg('账号和密码不能为空', function () {});
      return false;
    }
    var data = {
      'account': account,
      'password': password,
    };
    myRequest("/login", "post", data, function (res) {
      if (res.code == '200') {
        layer.msg(res.msg)
        setTimeout(function () {
          window.location.href = "/";
        }, 1500)
      } else {
        layer.msg(res.msg)
      }
    });
  }
</script>

</html>
