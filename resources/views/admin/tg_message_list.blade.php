@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-message-text"></i>
                    </span>
                    TG 訊息記錄
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">TG 機器人</a></li>
                        <li class="breadcrumb-item active" aria-current="page">訊息記錄</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">訊息記錄</h4>

                            {{-- 搜尋篩選列 --}}
                            <form method="GET" action="" class="mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select name="bot_id" class="form-control form-control-sm">
                                            <option value="">全部機器人</option>
                                            @foreach($bots as $bot)
                                                <option value="{{ $bot->id }}" {{ request('bot_id') == $bot->id ? 'selected' : '' }}>
                                                    {{ $bot->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="tg_username" class="form-control form-control-sm"
                                               placeholder="搜尋 TG 用戶名"
                                               value="{{ request('tg_username') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <select name="direction" class="form-control form-control-sm">
                                            <option value="">全部方向</option>
                                            <option value="1" {{ request('direction') === '1' ? 'selected' : '' }}>收到用戶訊息</option>
                                            <option value="2" {{ request('direction') === '2' ? 'selected' : '' }}>Bot 回覆</option>
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
                                    <th>機器人</th>
                                    <th>TG User ID</th>
                                    <th>TG 用戶名</th>
                                    <th>Chat ID</th>
                                    <th>訊息內容</th>
                                    <th>方向</th>
                                    <th>類型</th>
                                    <th>時間</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $item)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ $item->bot ? $item->bot->name : '-' }}</td>
                                        <td>{{ $item->tg_user_id }}</td>
                                        <td>{{ $item->tg_username ?: '-' }}</td>
                                        <td>{{ $item->tg_chat_id }}</td>
                                        <td>
                                            <span title="{{ $item->content }}">
                                                {{ mb_substr($item->content, 0, 80) }}{{ mb_strlen($item->content) > 80 ? '...' : '' }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($item->direction == 1)
                                                <span class="badge badge-primary">收</span>
                                            @else
                                                <span class="badge badge-success">發</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->message_type }}</td>
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
@endsection
