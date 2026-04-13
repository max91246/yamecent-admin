@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-account"></i>
                    </span>
                    用戶詳情 — Chat ID: {{ $chatId }}
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('admin/tg-holding/list') }}">持股管理</a></li>
                        <li class="breadcrumb-item active">用戶詳情</li>
                    </ol>
                </nav>
            </div>

            {{-- 統計卡片 --}}
            <div class="row">
                <div class="col-md-3 stretch-card grid-margin">
                    <div class="card bg-gradient-info card-img-holder text-white">
                        <div class="card-body">
                            <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                            <h5 class="font-weight-normal mb-2">帳戶資金
                                <i class="mdi mdi-wallet mdi-24px float-right"></i>
                            </h5>
                            <h3 class="mb-1">NT${{ number_format($wallet->capital ?? 0, 0) }}</h3>
                            <small>可用現金</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 stretch-card grid-margin">
                    <div class="card bg-gradient-primary card-img-holder text-white">
                        <div class="card-body">
                            <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                            <h5 class="font-weight-normal mb-2">持股總成本
                                <i class="mdi mdi-briefcase mdi-24px float-right"></i>
                            </h5>
                            <h3 class="mb-1">NT${{ number_format($holdingCost, 0) }}</h3>
                            <small>{{ $holdings->count() }} 筆持股</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 stretch-card grid-margin">
                    @php $profitClass = $tradeProfit >= 0 ? 'bg-gradient-success' : 'bg-gradient-danger'; @endphp
                    <div class="card {{ $profitClass }} card-img-holder text-white">
                        <div class="card-body">
                            <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                            <h5 class="font-weight-normal mb-2">歷史損益
                                <i class="mdi mdi-chart-line mdi-24px float-right"></i>
                            </h5>
                            <h3 class="mb-1">{{ $tradeProfit >= 0 ? '+' : '' }}NT${{ number_format($tradeProfit, 0) }}</h3>
                            <small>共 {{ $tradeTotal }} 筆交易</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 stretch-card grid-margin">
                    <div class="card bg-gradient-warning card-img-holder text-white">
                        <div class="card-body">
                            <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                            <h5 class="font-weight-normal mb-2">交易勝率
                                <i class="mdi mdi-trophy mdi-24px float-right"></i>
                            </h5>
                            <h3 class="mb-1">{{ $tradeWinPct }}%</h3>
                            <small>盈利 {{ $tradeWin }} ／ 虧損 {{ $tradeTotal - $tradeWin }} 筆</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 待交割款摘要 --}}
            @if($settlements->count() > 0)
            <div class="row">
                <div class="col-md-12 grid-margin">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">待交割款項</h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-arrow-down-circle text-danger mr-2 icon-lg"></i>
                                        <div>
                                            <p class="text-muted small mb-0">待付款（買進）</p>
                                            <h5 class="text-danger mb-0">-NT${{ number_format($settleBuy, 0) }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-arrow-up-circle text-success mr-2 icon-lg"></i>
                                        <div>
                                            <p class="text-muted small mb-0">待收款（賣出）</p>
                                            <h5 class="text-success mb-0">+NT${{ number_format($settleSell, 0) }}</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-equal-box {{ $settleNet >= 0 ? 'text-success' : 'text-danger' }} mr-2 icon-lg"></i>
                                        <div>
                                            <p class="text-muted small mb-0">淨額</p>
                                            <h5 class="{{ $settleNet >= 0 ? 'text-success' : 'text-danger' }} mb-0">
                                                {{ $settleNet >= 0 ? '+' : '' }}NT${{ number_format($settleNet, 0) }}
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>交割日</th>
                                            <th>股票</th>
                                            <th>張數</th>
                                            <th>方向</th>
                                            <th>金額</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($settlements as $s)
                                        @php $sLots = intdiv($s->shares, 1000); $sOdd = $s->shares % 1000; @endphp
                                        <tr>
                                            <td>{{ $s->settle_date }}</td>
                                            <td>{{ $s->stock_name }}（{{ $s->stock_code }}）</td>
                                            <td>{{ number_format($s->shares) }}股
                                                @if($sLots > 0)（{{ $sLots }}張@if($sOdd > 0) {{ $sOdd }}零股@endif）@endif
                                            </td>
                                            <td>
                                                @if(($s->direction ?? 'buy') === 'sell')
                                                    <span class="badge badge-success">賣出待收</span>
                                                @else
                                                    <span class="badge badge-danger">買進待付</span>
                                                @endif
                                            </td>
                                            <td class="{{ ($s->direction ?? 'buy') === 'sell' ? 'text-success' : 'text-danger' }}">
                                                {{ ($s->direction ?? 'buy') === 'sell' ? '+' : '-' }}NT${{ number_format($s->settlement_amount, 0) }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- 當前持股 --}}
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">當前持股</h4>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>股票</th>
                                            <th>股數</th>
                                            <th>類型</th>
                                            <th>買進均價</th>
                                            <th>持有成本</th>
                                            <th>新增時間</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($holdings as $row)
                                        @php $lots = intdiv($row->shares, 1000); $odd = $row->shares % 1000; @endphp
                                        <tr>
                                            <td>{{ $row->stock_name }}（{{ $row->stock_code }}）</td>
                                            <td>{{ number_format($row->shares) }}股
                                                @if($lots > 0)（{{ $lots }}張@if($odd > 0) {{ $odd }}零股@endif）@endif
                                            </td>
                                            <td>
                                                @if($row->is_margin)
                                                    <span class="badge badge-warning">融資</span>
                                                @else
                                                    <span class="badge badge-info">現股</span>
                                                @endif
                                            </td>
                                            <td>NT${{ $row->buy_price }}</td>
                                            <td>NT${{ number_format($row->total_cost, 0) }}</td>
                                            <td class="text-muted small">{{ $row->created_at->format('m/d H:i') }}</td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="6" class="text-center text-muted">目前無持股</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 歷史交易 --}}
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">歷史交易記錄</h4>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>股票</th>
                                            <th>張數</th>
                                            <th>類型</th>
                                            <th>買進均價</th>
                                            <th>賣出價格</th>
                                            <th>損益</th>
                                            <th>時間</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($trades as $row)
                                        @php
                                            $tLots = intdiv($row->sell_shares, 1000);
                                            $tOdd  = $row->sell_shares % 1000;
                                            $buyVal = $row->buy_price * $row->sell_shares;
                                            $profitPct = $buyVal > 0 ? round($row->profit / $buyVal * 100, 2) : null;
                                        @endphp
                                        <tr>
                                            <td>{{ $row->stock_name }}（{{ $row->stock_code }}）</td>
                                            <td>{{ number_format($row->sell_shares) }}股
                                                @if($tLots > 0)（{{ $tLots }}張@if($tOdd > 0) {{ $tOdd }}零股@endif）@endif
                                            </td>
                                            <td>
                                                @if($row->is_margin)
                                                    <span class="badge badge-warning">融資</span>
                                                @else
                                                    <span class="badge badge-info">現股</span>
                                                @endif
                                            </td>
                                            <td>NT${{ $row->buy_price }}</td>
                                            <td>NT${{ $row->sell_price }}</td>
                                            <td class="{{ $row->profit >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                                {{ $row->profit >= 0 ? '+' : '' }}NT${{ number_format($row->profit, 0) }}
                                                @if($profitPct !== null)
                                                <br><small class="font-weight-normal">{{ $row->profit >= 0 ? '+' : '' }}{{ $profitPct }}%</small>
                                                @endif
                                            </td>
                                            <td class="text-muted small">{{ $row->created_at->format('m/d H:i') }}</td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="7" class="text-center text-muted">尚無交易記錄</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
