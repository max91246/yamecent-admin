@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-briefcase"></i>
                    </span>
                    TG 用戶持股
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">TG 機器人</a></li>
                        <li class="breadcrumb-item active">持股管理</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">當前持股列表</h4>

                            <form method="GET" action="" class="mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select name="bot_id" class="form-control form-control-sm">
                                            <option value="">全部機器人</option>
                                            @foreach($bots as $bot)
                                                <option value="{{ $bot->id }}" {{ request('bot_id') == $bot->id ? 'selected' : '' }}>{{ $bot->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="tg_chat_id" class="form-control form-control-sm" placeholder="Chat ID" value="{{ request('tg_chat_id') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="stock_code" class="form-control form-control-sm" placeholder="股票代號" value="{{ request('stock_code') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                                        <a href="{{ request()->url() }}" class="btn btn-secondary btn-sm">重置</a>
                                        <a href="{{ url('admin/tg-holding/trade-list') }}" class="btn btn-info btn-sm ml-2">交易記錄</a>
                                    </div>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>機器人</th>
                                            <th>Chat ID</th>
                                            <th>股票</th>
                                            <th>張數</th>
                                            <th>類型</th>
                                            <th>買進價</th>
                                            <th>持有成本</th>
                                            <th>新增時間</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($list as $row)
                                            <tr>
                                                <td>{{ $row->id }}</td>
                                                <td>{{ optional(collect($bots)->firstWhere('id', $row->bot_id))->name ?? $row->bot_id }}</td>
                                                <td>{{ $row->tg_chat_id }}</td>
                                                <td>{{ $row->stock_name }}（{{ $row->stock_code }}）</td>
                                                <td>{{ $row->shares }} 張</td>
                                                <td>
                                                    @if($row->is_margin)
                                                        <span class="badge badge-warning">融資</span>
                                                    @else
                                                        <span class="badge badge-info">現股</span>
                                                    @endif
                                                </td>
                                                <td>NT${{ $row->buy_price }}</td>
                                                <td>NT${{ number_format($row->total_cost, 0) }}</td>
                                                <td>{{ $row->created_at }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="9" class="text-center">無資料</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $list->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
