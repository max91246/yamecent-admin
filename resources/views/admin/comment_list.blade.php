@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-comment-text-multiple"></i>
                    </span>
                    留言管理
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">文章管理</a></li>
                        <li class="breadcrumb-item active" aria-current="page">留言內容</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">留言列表</h4>

                            {{-- 篩選列 --}}
                            <form method="GET" action="" class="d-flex align-items-center flex-wrap mb-3" id="filter-form">
                                {{-- 留言人模糊搜尋 --}}
                                <div class="position-relative mr-2 mb-2" style="width:250px;">
                                    <input type="text" id="member_search_input"
                                           class="form-control form-control-sm"
                                           placeholder="搜尋帳號或暱稱" autocomplete="off"
                                           value="{{ $selectedMember ? $selectedMember->account . ' (' . $selectedMember->nickname . ')' : '' }}">
                                    <input type="hidden" name="member_id" id="member_id_hidden"
                                           value="{{ request('member_id') }}">
                                    <div id="member_dropdown"
                                         class="list-group position-absolute w-100"
                                         style="z-index:9999;display:none;max-height:200px;overflow-y:auto;box-shadow:0 4px 8px rgba(0,0,0,.15);">
                                    </div>
                                </div>

                                {{-- 文章下拉 --}}
                                <select name="article_id" class="form-control form-control-sm mr-2 mb-2" style="width:200px;">
                                    <option value="">全部文章</option>
                                    @foreach($articles as $a)
                                        <option value="{{ $a->id }}" {{ request('article_id') == $a->id ? 'selected' : '' }}>
                                            {{ \Illuminate\Support\Str::limit($a->title, 20) }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- 時間排序 --}}
                                <select name="sort" class="form-control form-control-sm mr-2 mb-2" style="width:120px;">
                                    <option value="desc" {{ request('sort', 'desc') === 'desc' ? 'selected' : '' }}>最新優先</option>
                                    <option value="asc"  {{ request('sort') === 'asc'  ? 'selected' : '' }}>最舊優先</option>
                                </select>

                                <button type="submit" class="btn btn-sm btn-gradient-primary mr-1 mb-2">篩選</button>
                                <a href="{{ url()->current() }}" class="btn btn-sm btn-gradient-secondary mb-2">重置</a>
                            </form>

                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>留言文章</th>
                                    <th>留言內容</th>
                                    <th>管理員回復</th>
                                    <th>留言人</th>
                                    <th>狀態</th>
                                    <th>留言時間</th>
                                    <th>操作</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $item)
                                    <tr>
                                        <td>{{ $item->article ? $item->article->title : '-' }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($item->content, 50) }}</td>
                                        <td>
                                            @if($item->admin_reply)
                                                <span class="text-primary small">{{ \Illuminate\Support\Str::limit($item->admin_reply, 30) }}</span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->member ? $item->member->nickname : '-' }}</td>
                                        <td>
                                            @if($item->is_visible)
                                                <span class="badge badge-success">顯示中</span>
                                            @else
                                                <span class="badge badge-secondary">已隱藏</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->created_at }}</td>
                                        <td>
                                            <button type="button"
                                                    class="btn btn-sm btn-gradient-warning btn-icon-text mb-1"
                                                    onclick="toggle({{ $item->id }}, {{ $item->is_visible }})">
                                                <i class="mdi mdi-eye{{ $item->is_visible ? '-off' : '' }} btn-icon-prepend"></i>
                                                {{ $item->is_visible ? '隱藏' : '顯示' }}
                                            </button>
                                            <button type="button"
                                                    class="btn btn-sm btn-gradient-info btn-icon-text mb-1"
                                                    onclick="reply({{ $item->id }})">
                                                <i class="mdi mdi-reply btn-icon-prepend"></i>
                                                回復
                                            </button>
                                            <button type="button"
                                                    class="btn btn-sm btn-gradient-danger btn-icon-text mb-1"
                                                    onclick="del({{ $item->id }})">
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
        var searchTimer = null;

        $('#member_search_input').on('input', function () {
            var keyword = $(this).val().trim();
            clearTimeout(searchTimer);
            if (keyword.length === 0) {
                $('#member_id_hidden').val('');
                $('#member_dropdown').hide();
                return;
            }
            searchTimer = setTimeout(function () {
                $.get('/admin/member/search', {keyword: keyword}, function (data) {
                    var $dropdown = $('#member_dropdown').empty();
                    if (data.length === 0) {
                        $dropdown.append('<div class="list-group-item text-muted small">無符合結果</div>');
                    } else {
                        $.each(data, function (i, m) {
                            $('<a href="#" class="list-group-item list-group-item-action py-1 small"></a>')
                                .text(m.account + ' (' + m.nickname + ')')
                                .on('click', function (e) {
                                    e.preventDefault();
                                    $('#member_search_input').val(m.account + ' (' + m.nickname + ')');
                                    $('#member_id_hidden').val(m.id);
                                    $dropdown.hide();
                                })
                                .appendTo($dropdown);
                        });
                    }
                    $dropdown.show();
                });
            }, 300);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#member_search_input, #member_dropdown').length) {
                $('#member_dropdown').hide();
            }
        });

        function del(id) {
            myConfirm('刪除操作不可逆，是否繼續？', function () {
                myRequest('/admin/comment/del/' + id, 'post', {}, function (res) {
                    layer.msg(res.msg);
                    setTimeout(function () { window.location.reload(); }, 1500);
                });
            });
        }

        function toggle(id, currentVisible) {
            var label = currentVisible ? '確定要隱藏這則留言？' : '確定要顯示這則留言？';
            myConfirm(label, function () {
                myRequest('/admin/comment/toggle/' + id, 'post', {}, function (res) {
                    layer.msg(res.msg);
                    setTimeout(function () { window.location.reload(); }, 1000);
                });
            });
        }

        function reply(id) {
            layer.prompt({
                formType: 2,
                title: '管理員回復',
                area: ['420px', '220px'],
                placeholder: '請輸入回復內容...'
            }, function (val, index) {
                if (!val.trim()) { layer.msg('回復內容不能為空'); return; }
                myRequest('/admin/comment/reply/' + id, 'post', {reply: val}, function (res) {
                    layer.msg(res.msg);
                    layer.close(index);
                    setTimeout(function () { window.location.reload(); }, 1000);
                });
            });
        }
    </script>
@endsection
