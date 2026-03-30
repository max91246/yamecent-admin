@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">新增會員</h4>
                            <form class="forms-sample" id="form">

                                <div class="form-group">
                                    <label>*帳號</label>
                                    <input type="text" class="form-control required" name="account" placeholder="請輸入帳號">
                                </div>

                                <div class="form-group">
                                    <label>*密碼</label>
                                    <input type="password" class="form-control required" name="password" placeholder="請輸入密碼">
                                </div>

                                <div class="form-group">
                                    <label>頭像</label>
                                    <input type="file" class="file-upload-default img-file" data-path="avatar">
                                    <input type="hidden" name="avatar" class="image-path">
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" disabled="">
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-gradient-primary" onclick="upload($(this))" type="button">上傳</button>
                                        </span>
                                    </div>
                                    <div class="img-yl"></div>
                                </div>

                                <div class="form-group">
                                    <label>*暱稱</label>
                                    <input type="text" class="form-control required" name="nickname" placeholder="請輸入暱稱">
                                </div>

                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="text" class="form-control" name="email" placeholder="請輸入 Email">
                                </div>

                                <div class="form-group">
                                    <label>手機號</label>
                                    <input type="text" class="form-control" name="phone" placeholder="請輸入手機號">
                                </div>

                                <div class="form-group">
                                    <label>狀態</label>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input" name="is_active" value="1" checked>
                                            啟用
                                            <i class="input-helper"></i>
                                        </label>
                                    </div>
                                </div>

                                <button type="button" onclick="commit()" class="btn btn-sm btn-gradient-primary btn-icon-text">
                                    <i class="mdi mdi-file-check btn-icon-prepend"></i>
                                    提交
                                </button>
                                <button type="button" onclick="cancel()" class="btn btn-sm btn-gradient-warning btn-icon-text">
                                    <i class="mdi mdi-reload btn-icon-prepend"></i>
                                    取消
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function commit() {
            if (!checkForm()) return false;
            var data = $('#form').serializeObject();
            data.is_active = $('input[name=is_active]').is(':checked') ? 1 : 0;
            myRequest('/admin/member/add', 'post', data, function (res) {
                layer.msg(res.msg);
                if (res.code == 200) {
                    setTimeout(function () {
                        parent.location.reload();
                    }, 1500);
                }
            });
        }

        function cancel() {
            parent.location.reload();
        }
    </script>
@endsection
