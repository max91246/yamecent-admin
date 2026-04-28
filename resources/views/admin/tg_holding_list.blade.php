@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-briefcase"></i>
                    </span>
                    TG 用戶持股管理
                </h3>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">用戶列表</h4>

                            <form method="GET" action="" class="mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <select name="bot_id" class="form-control form-control-sm">
                                            <option value="">全部機器人</option>
                                            @foreach($bots as $bot)
                                                <option value="{{ $bot->id }}" {{ request('bot_id') == $bot->id ? 'selected' : '' }}>{{ $bot->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="tg_chat_id" class="form-control form-control-sm" placeholder="Chat ID" value="{{ request('tg_chat_id') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                                        <a href="{{ request()->url() }}" class="btn btn-secondary btn-sm">重置</a>
                                    </div>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Chat ID</th>
                                            <th>機器人</th>
                                            <th>帳戶資金</th>
                                            <th>持股筆數</th>
                                            <th>持股總成本</th>
                                            <th>歷史損益</th>
                                            <th>勝率</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($wallets as $wallet)
                                            @php
                                                $hc = $holdingCounts[$wallet->tg_chat_id] ?? null;
                                                $tp = $tradeProfits[$wallet->tg_chat_id] ?? null;
                                                $profit  = $tp ? (float)$tp->profit : 0;
                                                $total   = $tp ? (int)$tp->total : 0;
                                                $win     = $tp ? (int)$tp->win : 0;
                                                $winPct  = $total > 0 ? round($win / $total * 100) : 0;
                                                $botName = optional(collect($bots)->firstWhere('id', $wallet->bot_id))->name ?? $wallet->bot_id;
                                            @endphp
                                            <tr>
                                                <td><strong>{{ $wallet->tg_chat_id }}</strong></td>
                                                <td>{{ $botName }}</td>
                                                <td>NT${{ number_format($wallet->capital, 0) }}</td>
                                                <td>{{ $hc ? $hc->cnt : 0 }} 筆</td>
                                                <td>{{ $hc ? 'NT$' . number_format($hc->total_cost, 0) : '-' }}</td>
                                                <td class="{{ $profit > 0 ? 'tw-up' : ($profit < 0 ? 'tw-dn' : 'tw-flat') }} font-weight-bold">
                                                    {{ $total > 0 ? ($profit >= 0 ? '+' : '') . 'NT$' . number_format($profit, 0) : '-' }}
                                                </td>
                                                <td>
                                                    @if($total > 0)
                                                        <span class="badge {{ $winPct >= 50 ? 'badge-success' : 'badge-danger' }}">{{ $winPct }}%</span>
                                                        <small class="text-muted">{{ $win }}/{{ $total }}</small>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ url('admin/tg-holding/user/' . $wallet->tg_chat_id) }}?bot_id={{ $wallet->bot_id }}"
                                                       class="btn btn-primary btn-sm">
                                                        查看詳情
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="text-center">無資料</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $wallets->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
