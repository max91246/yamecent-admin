@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-primary text-white mr-2">
                        <i class="mdi mdi-home"></i>
                    </span>
                    Dashboard
                </h3>
            </div>

            {{-- 第一排：會員 / Bot 用戶 / 交割淨額 --}}
            <div class="row">
                <div class="col-md-4 stretch-card grid-margin">
                    <div class="card bg-gradient-danger card-img-holder text-white">
                        <div class="card-body">
                            <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image"/>
                            <h4 class="font-weight-normal mb-3">會員總覽
                                <i class="mdi mdi-account-multiple mdi-24px float-right"></i>
                            </h4>
                            <h2 class="mb-2">{{ number_format($stats['member_total']) }}</h2>
                            <h6 class="card-text">
                                活躍 {{ $stats['member_active'] }} 人 ／ 付費會員 {{ $stats['member_paid'] }} 人
                            </h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 stretch-card grid-margin">
                    <div class="card bg-gradient-info card-img-holder text-white">
                        <div class="card-body">
                            <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image"/>
                            <h4 class="font-weight-normal mb-3">Bot 投資用戶
                                <i class="mdi mdi-robot mdi-24px float-right"></i>
                            </h4>
                            <h2 class="mb-2">{{ number_format($stats['bot_users']) }}</h2>
                            <h6 class="card-text">
                                持股中 {{ $stats['holding_users'] }} 人 ／ 融資佔比 {{ $stats['margin_pct'] }}%
                            </h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 stretch-card grid-margin">
                    @php
                        $netSettle = $stats['settle_sell_amt'] - $stats['settle_buy_amt'];
                        $settleClass = $netSettle >= 0 ? 'bg-gradient-success' : 'bg-gradient-warning';
                        $settleLabel = $netSettle >= 0 ? '淨收款' : '淨付款';
                    @endphp
                    <div class="card {{ $settleClass }} card-img-holder text-white">
                        <div class="card-body">
                            <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image"/>
                            <h4 class="font-weight-normal mb-3">待交割款項
                                <i class="mdi mdi-calendar-check mdi-24px float-right"></i>
                            </h4>
                            <h2 class="mb-2">NT$ {{ number_format(abs($netSettle)) }}</h2>
                            <h6 class="card-text">
                                {{ $settleLabel }} ／ 共 {{ $stats['settle_pending'] }} 筆待結算
                            </h6>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 第二排：內容統計 + 交割款明細 --}}
            <div class="row">
                <div class="col-md-4 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">內容統計</h4>
                            <ul class="list-unstyled mt-3">
                                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span><i class="mdi mdi-file-document-outline text-primary mr-2"></i>文章總數</span>
                                    <span class="badge badge-primary badge-pill">{{ $stats['article_total'] }}</span>
                                </li>
                                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span><i class="mdi mdi-comment-outline text-info mr-2"></i>今日新增留言</span>
                                    <span class="badge badge-info badge-pill">{{ $stats['comment_today'] }}</span>
                                </li>
                                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span><i class="mdi mdi-account-outline text-success mr-2"></i>活躍會員</span>
                                    <span class="badge badge-success badge-pill">{{ $stats['member_active'] }}</span>
                                </li>
                                <li class="d-flex justify-content-between align-items-center py-2">
                                    <span><i class="mdi mdi-star-outline text-warning mr-2"></i>付費會員</span>
                                    <span class="badge badge-warning badge-pill">{{ $stats['member_paid'] }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-8 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">交割款明細（待結算）</h4>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>類型</th>
                                            <th>金額</th>
                                            <th>說明</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge badge-gradient-danger">待付款（買進）</span></td>
                                            <td class="text-danger font-weight-bold">- NT$ {{ number_format($stats['settle_buy_amt']) }}</td>
                                            <td class="text-muted small">T+2 交割扣款</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-gradient-success">待收款（賣出）</span></td>
                                            <td class="text-success font-weight-bold">+ NT$ {{ number_format($stats['settle_sell_amt']) }}</td>
                                            <td class="text-muted small">T+2 交割收款</td>
                                        </tr>
                                        <tr class="font-weight-bold">
                                            <td>淨額</td>
                                            @php $net = $stats['settle_sell_amt'] - $stats['settle_buy_amt']; @endphp
                                            <td class="{{ $net >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $net >= 0 ? '+' : '' }}NT$ {{ number_format($net) }}
                                            </td>
                                            <td class="text-muted small">共 {{ $stats['settle_pending'] }} 筆</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 第四排：近期交易紀錄 --}}
            <div class="row">
                <div class="col-12 grid-margin">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">近期交易紀錄（最新 10 筆）</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>股票</th>
                                            <th>張數</th>
                                            <th>買進均價</th>
                                            <th>賣出價格</th>
                                            <th>類型</th>
                                            <th>損益</th>
                                            <th>時間</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentTrades as $trade)
                                        <tr>
                                            <td><strong>{{ $trade->stock_code }}</strong> {{ $trade->stock_name }}</td>
                                            <td>{{ $trade->sell_shares }} 張</td>
                                            <td>{{ number_format($trade->buy_price, 2) }}</td>
                                            <td>{{ number_format($trade->sell_price, 2) }}</td>
                                            <td>
                                                @if($trade->is_margin)
                                                    <label class="badge badge-gradient-warning">融資</label>
                                                @else
                                                    <label class="badge badge-gradient-info">現股</label>
                                                @endif
                                            </td>
                                            <td class="{{ $trade->profit >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                                {{ $trade->profit >= 0 ? '+' : '' }}{{ number_format($trade->profit) }}
                                            </td>
                                            <td class="text-muted small">{{ $trade->created_at->format('m/d H:i') }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">尚無交易紀錄</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <footer class="footer">
            <div class="d-sm-flex justify-content-center justify-content-sm-between">
                <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">
                    Copyright © 2017-2026 <a href="http://www.yamecent.com/" target="_blank">Yamecent</a>. All rights reserved.
                </span>
            </div>
        </footer>
    </div>
@endsection
