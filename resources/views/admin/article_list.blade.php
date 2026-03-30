@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-newspaper"></i>
                    </span>
                    文章管理
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">內容管理</a></li>
                        <li class="breadcrumb-item active" aria-current="page">文章列表</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">文章列表</h4>
                            <p class="card-description">
                                <button type="button" class="btn btn-sm btn-gradient-success btn-icon-text" onclick="add()">
                                    <i class="mdi mdi-plus btn-icon-prepend"></i>
                                    新增文章
                                </button>
                            </p>
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>標題</th>
                                    <th>封面圖</th>
                                    <th>類型</th>
                                    <th>狀態</th>
                                    <th>建立時間</th>
                                    <th>操作</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ $item->title }}</td>
                                        <td>
                                            @if($item->image)
                                                <img src="{{ $item->image }}" style="height:50px;">
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $item->getTypeLabel() }}</td>
                                        <td>
                                            @if($item->is_active)
                                                <span class="badge badge-success">上架</span>
                                            @else
                                                <span class="badge badge-secondary">下架</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->created_at }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-gradient-dark btn-icon-text" onclick="update({{ $item->id }})">
                                                修改
                                                <i class="mdi mdi-file-check btn-icon-append"></i>
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
                                {{ $list->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function add() {
            layer.open({
                type: 2,
                title: '新增文章',
                shadeClose: true,
                shade: 0.8,
                area: ['80%', '90%'],
                content: '/admin/article/add'
            });
        }

        function update(id) {
            layer.open({
                type: 2,
                title: '修改文章',
                shadeClose: true,
                shade: 0.8,
                area: ['80%', '90%'],
                content: '/admin/article/update/' + id
            });
        }

        function del(id) {
            myConfirm("刪除操作不可逆，是否繼續?", function () {
                myRequest("/admin/article/del/" + id, "post", {}, function (res) {
                    layer.msg(res.msg);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                });
            });
        }
    </script>
@endsection
