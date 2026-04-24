@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">修改 TG 機器人</h4>
                            <form class="forms-sample" id="form">

                                <div class="form-group">
                                    <label>*機器人名稱</label>
                                    <input type="text" class="form-control required" name="name" value="{{ $bot->name }}" placeholder="請輸入機器人名稱">
                                </div>

                                <div class="form-group">
                                    <label>*Bot Token</label>
                                    <input type="text" class="form-control required" name="token" value="{{ $bot->token }}" placeholder="請貼入 Bot Token">
                                    <small class="text-muted">修改 Token 後將自動重新設定 Webhook</small>
                                </div>

                                <div class="form-group">
                                    <label>類型</label>
                                    <select class="form-control" name="type">
                                        <option value="stock" {{ in_array($bot->type, ['stock', '1', 1]) ? 'selected' : '' }}>📈 股票指數</option>
                                        <option value="av"    {{ $bot->type === 'av' ? 'selected' : '' }}>🔞 AV 查詢</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>狀態</label>
                                    <div>
                                        <label class="mr-3">
                                            <input type="radio" name="is_active" value="1" {{ $bot->is_active ? 'checked' : '' }}> 啟用
                                        </label>
                                        <label>
                                            <input type="radio" name="is_active" value="0" {{ !$bot->is_active ? 'checked' : '' }}> 停用
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>備註</label>
                                    <textarea class="form-control" name="remark" rows="3" placeholder="選填備註">{{ $bot->remark }}</textarea>
                                </div>

                                <div class="form-group">
                                    <label class="text-muted">Webhook 最後設定時間：</label>
                                    <span>{{ $bot->webhook_set_at ? $bot->webhook_set_at->format('Y-m-d H:i:s') : '尚未設定' }}</span>
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
            myRequest('/admin/tg-bot/update/{{ $bot->id }}', 'post', data, function (res) {
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
