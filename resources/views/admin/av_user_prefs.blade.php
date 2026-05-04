@extends('base.base')
@section('base')
<div class="main-panel">
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-warning text-white mr-2">
                    <i class="mdi mdi-account-heart"></i>
                </span>
                AV 用戶偏好管理
            </h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">訂閱用戶列表</h4>
                            <span class="badge badge-primary">共 {{ $prefs->count() }} 位</span>
                        </div>

                        @if($prefs->isEmpty())
                            <div class="text-center text-muted py-5">目前尚無用戶設定偏好</div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>機器人</th>
                                        <th>Chat ID</th>
                                        <th>用戶名稱</th>
                                        <th>喜好標籤</th>
                                        <th class="text-center">每日推播</th>
                                        <th>更新時間</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($prefs as $pref)
                                    <tr>
                                        <td>
                                            <span class="text-muted small">{{ $bots[$pref->bot_id] ?? 'Bot #'.$pref->bot_id }}</span>
                                        </td>
                                        <td>
                                            <code style="color:#63b3ed;">{{ $pref->tg_chat_id }}</code>
                                        </td>
                                        <td>
                                            @if($pref->tg_username)
                                                <span class="text-muted small">{{ '@' . $pref->tg_username }}</span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($pref->fav_tags))
                                                @foreach($pref->fav_tags as $tag)
                                                    <span class="badge badge-secondary mr-1 mb-1"
                                                          style="background:rgba(100,160,255,0.15);color:#a0aec0;font-weight:400;">
                                                        {{ $tag }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-muted small">未設定</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($pref->push_enabled)
                                                <span class="badge badge-success">開啟</span>
                                            @else
                                                <span class="badge badge-secondary">關閉</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ $pref->updated_at->format('Y-m-d H:i') }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
