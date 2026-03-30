@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-account-multiple"></i>
                    </span>
                    會員管理
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">會員管理</a></li>
                        <li class="breadcrumb-item active" aria-current="page">會員列表</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">會員列表</h4>

                            {{-- 搜尋篩選列 --}}
                            <form method="GET" action="" class="mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="text" name="keyword" class="form-control form-control-sm"
                                               placeholder="搜尋暱稱 / 帳號 / Email / 手機號"
                                               value="{{ request('keyword') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <select name="balance_sort" class="form-control form-control-sm">
                                            <option value="">存款預設排序</option>
                                            <option value="desc" {{ request('balance_sort') == 'desc' ? 'selected' : '' }}>存款由高至低</option>
                                            <option value="asc"  {{ request('balance_sort') == 'asc'  ? 'selected' : '' }}>存款由低至高</option>
                                        </select>
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
                                    <th>頭像</th>
                                    <th>帳號</th>
                                    <th>暱稱</th>
                                    <th>Email</th>
                                    <th>手機號</th>
                                    <th>存款</th>
                                    <th>狀態</th>
                                    <th>會員資格</th>
                                    <th>建立時間</th>
                                    <th>操作</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>
                                            @if($item->avatar)
                                                <img src="{{ $item->avatar }}" style="height:40px;width:40px;border-radius:50%;object-fit:cover;">
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $item->account }}</td>
                                        <td>{{ $item->nickname }}</td>
                                        <td>{{ $item->email ?: '-' }}</td>
                                        <td>{{ $item->phone ?: '-' }}</td>
                                        <td>{{ number_format($item->balance, 2) }}</td>
                                        <td>
                                            @if($item->is_active)
                                                <span class="badge badge-success">啟用</span>
                                            @else
                                                <span class="badge badge-secondary">停用</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->created_at }}</td>
                                        <td>
                                            @php
                                                $isActive = $item->is_member == 1 && $item->member_expired_at && $item->member_expired_at->gt(now());
                                                $isPending = !$item->is_member && $item->member_applied_at;
                                                $isExpired = $item->is_member == 1 && $item->member_expired_at && $item->member_expired_at->lte(now());
                                            @endphp
                                            @if($isActive)
                                                <span class="badge badge-success">有效</span>
                                                <div class="small text-muted">到 {{ $item->member_expired_at->format('Y-m-d') }}</div>
                                            @elseif($isPending)
                                                <span class="badge badge-warning">待審核</span>
                                                <div class="small text-muted">申請 {{ $item->member_applied_at->format('m-d H:i') }}</div>
                                            @elseif($isExpired)
                                                <span class="badge badge-danger">已到期</span>
                                                <div class="small text-muted">{{ $item->member_expired_at->format('Y-m-d') }}</div>
                                            @else
                                                <span class="badge badge-secondary">非會員</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-gradient-dark btn-icon-text" onclick="update({{ $item->id }})">
                                                修改
                                                <i class="mdi mdi-file-check btn-icon-append"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-gradient-info btn-icon-text" onclick="openMembershipModal({{ $item->id }}, '{{ $item->nickname }}')">
                                                <i class="mdi mdi-crown btn-icon-prepend"></i>
                                                開通
                                            </button>
                                            @if($isActive || $isExpired)
                                            <button type="button" class="btn btn-sm btn-gradient-warning btn-icon-text" onclick="revokeMembership({{ $item->id }})">
                                                撤銷
                                            </button>
                                            @endif
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

    {{-- 開通會員 Modal --}}
    <div id="membershipModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;padding:30px;width:380px;max-width:90%;">
            <h5 style="margin-bottom:16px;">開通會員資格 — <span id="modalNickname"></span></h5>
            <input type="hidden" id="modalMemberId">
            <div class="form-group">
                <label>開通天數</label>
                <div class="d-flex flex-wrap" style="gap:8px;margin-bottom:10px;">
                    <button class="btn btn-sm btn-outline-primary" onclick="setDays(30)">30 天</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="setDays(90)">90 天</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="setDays(180)">180 天</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="setDays(365)">365 天</button>
                </div>
                <input type="number" id="daysInput" class="form-control" placeholder="自訂天數（1~3650）" min="1" max="3650">
                <small class="text-muted">若已是有效會員，天數將從現有到期時間延長</small>
            </div>
            <div class="d-flex justify-content-end" style="gap:8px;margin-top:16px;">
                <button class="btn btn-secondary" onclick="closeMembershipModal()">取消</button>
                <button class="btn btn-primary" onclick="submitMembership()">確認開通</button>
            </div>
        </div>
    </div>

    <script>
        function openMembershipModal(id, nickname) {
            document.getElementById('modalMemberId').value = id;
            document.getElementById('modalNickname').textContent = nickname;
            document.getElementById('daysInput').value = '';
            document.getElementById('membershipModal').style.display = 'flex';
        }
        function closeMembershipModal() {
            document.getElementById('membershipModal').style.display = 'none';
        }
        function setDays(d) {
            document.getElementById('daysInput').value = d;
        }
        function submitMembership() {
            var id   = document.getElementById('modalMemberId').value;
            var days = parseInt(document.getElementById('daysInput').value);
            if (!days || days < 1 || days > 3650) {
                layer.msg('請輸入有效天數（1~3650）');
                return;
            }
            myRequest('/admin/member/membership/' + id + '/activate', 'post', { days: days }, function (res) {
                if (res.success) {
                    closeMembershipModal();
                    layer.msg('開通成功！到期時間：' + res.member_expired_at, function () {
                        window.location.reload();
                    });
                } else {
                    layer.msg('操作失敗');
                }
            });
        }
        function revokeMembership(id) {
            myConfirm('確定撤銷該會員的會員資格？', function () {
                myRequest('/admin/member/membership/' + id + '/revoke', 'post', {}, function (res) {
                    if (res.success) {
                        layer.msg('已撤銷', function () { window.location.reload(); });
                    } else {
                        layer.msg('操作失敗');
                    }
                });
            });
        }

        function update(id) {
            layer.open({
                type: 2,
                title: '修改會員',
                shadeClose: true,
                shade: 0.8,
                area: ['70%', '85%'],
                content: '/admin/member/update/' + id
            });
        }

        function del(id) {
            myConfirm("刪除操作不可逆，是否繼續?", function () {
                myRequest("/admin/member/del/" + id, "post", {}, function (res) {
                    layer.msg(res.msg);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                });
            });
        }
    </script>
@endsection
