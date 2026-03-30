@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-currency-usd"></i>
                    </span>
                    資產管理
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">會員管理</a></li>
                        <li class="breadcrumb-item active" aria-current="page">資產管理</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">流水記錄</h4>

                            {{-- 篩選列 --}}
                            <div class="d-flex justify-content-between align-items-end mb-3">
                                <form method="GET" action="" class="d-flex align-items-center" id="filter-form">
                                    <div class="position-relative mr-2" style="width:250px;">
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
                                    <button type="submit" class="btn btn-sm btn-gradient-primary mr-1">篩選</button>
                                    <a href="{{ url()->current() }}" class="btn btn-sm btn-gradient-secondary">重置</a>
                                </form>
                                <button type="button" class="btn btn-sm btn-gradient-success btn-icon-text" onclick="addBalance()">
                                    <i class="mdi mdi-plus btn-icon-prepend"></i>
                                    調整餘額
                                </button>
                            </div>

                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>會員帳號</th>
                                    <th>類型</th>
                                    <th>金額</th>
                                    <th>變動前餘額</th>
                                    <th>變動後餘額</th>
                                    <th>備注</th>
                                    <th>建立時間</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ $item->member ? $item->member->account : '-' }}</td>
                                        <td>
                                            @if($item->type == 1)
                                                <span class="badge badge-success">增加</span>
                                            @else
                                                <span class="badge badge-danger">減少</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($item->amount, 2) }}</td>
                                        <td>{{ number_format($item->before_balance, 2) }}</td>
                                        <td>{{ number_format($item->after_balance, 2) }}</td>
                                        <td>{{ $item->remark ?: '-' }}</td>
                                        <td>{{ $item->created_at }}</td>
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

        function addBalance() {
            layer.open({
                type: 2,
                title: '調整餘額',
                shadeClose: true,
                shade: 0.8,
                area: ['50%', '65%'],
                content: '/admin/member/balance/add'
            });
        }
    </script>
@endsection
