@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-robot"></i>
                    </span>
                    TG 機器人管理
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">TG 機器人</a></li>
                        <li class="breadcrumb-item active" aria-current="page">機器人列表</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="card-title mb-0">機器人列表</h4>
                                <a href="/admin/tg-bot/add" class="btn btn-sm btn-gradient-primary btn-icon-text">
                                    <i class="mdi mdi-plus btn-icon-prepend"></i>
                                    新增機器人
                                </a>
                            </div>

                            {{-- 搜尋篩選列 --}}
                            <form method="GET" action="" class="mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="text" name="name" class="form-control form-control-sm"
                                               placeholder="搜尋名稱"
                                               value="{{ request('name') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <select name="is_active" class="form-control form-control-sm">
                                            <option value="">全部狀態</option>
                                            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>啟用</option>
                                            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>停用</option>
                                        </select>
                                    </div>
                                    <div class="col-md-auto">
                                        <button type="submit" class="btn btn-sm btn-gradient-primary">搜尋</button>
                                        <a href="{{ url()->current() }}" class="btn btn-sm btn-gradient-secondary ml-1">重置</a>
                                    </div>
                                </div>
                            </form>

                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名稱</th>
                                    <th>Bot Token</th>
                                    <th>類型</th>
                                    <th>狀態</th>
                                    <th>Webhook 設定時間</th>
                                    <th>建立時間</th>
                                    <th>操作</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td>
                                            <span class="text-muted small">{{ substr($item->token, 0, 20) }}...</span>
                                        </td>
                                        <td>
                                            @if($item->type == 1)
                                                <span class="badge badge-info">指數查詢</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->is_active)
                                                <span class="badge badge-success">啟用</span>
                                            @else
                                                <span class="badge badge-secondary">停用</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->webhook_set_at ? $item->webhook_set_at->format('Y-m-d H:i:s') : '-' }}</td>
                                        <td>{{ $item->created_at }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-gradient-dark btn-icon-text" onclick="update({{ $item->id }})">
                                                修改
                                                <i class="mdi mdi-file-check btn-icon-append"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-gradient-info btn-icon-text" onclick="setWebhook({{ $item->id }})">
                                                <i class="mdi mdi-link btn-icon-prepend"></i>
                                                設定 Webhook
                                            </button>
                                            <button type="button" class="btn btn-sm btn-gradient-danger btn-icon-text" onclick="del({{ $item->id }})">
                                                <i class="mdi mdi-delete btn-icon-prepend"></i>
                                                刪除
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="mt-3">
                                {{ $list->appends(request()->query())->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function update(id) {
            layer.open({
                type: 2,
                title: '修改機器人',
                shadeClose: true,
                shade: 0.8,
                area: ['60%', '80%'],
                content: '/admin/tg-bot/update/' + id
            });
        }

        function setWebhook(id) {
            layer.msg('設定中...', {icon: 16, shade: 0.3, time: 0});
            myRequest('/admin/tg-bot/set-webhook/' + id, 'post', {}, function (res) {
                layer.closeAll();
                if (res.success) {
                    layer.msg(res.msg, {icon: 1, time: 3000}, function () {
                        window.location.reload();
                    });
                } else {
                    layer.msg('設定失敗：' + res.msg, {icon: 2, time: 5000});
                }
            });
        }

        function del(id) {
            myConfirm("刪除操作不可逆，是否繼續？", function () {
                myRequest("/admin/tg-bot/del/" + id, "post", {}, function (res) {
                    layer.msg(res.msg, function () {
                        window.location.reload();
                    });
                });
            });
        }
    </script>
@endsection
