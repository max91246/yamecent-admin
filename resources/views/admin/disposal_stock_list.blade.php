@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="page-header">
                <h3 class="page-title">
                    <span class="page-title-icon bg-gradient-danger text-white mr-2">
                        <i class="mdi mdi-alert-circle"></i>
                    </span>
                    處置股查詢
                </h3>
            </div>
            <div class="row">
                <div class="col-lg-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">處置股名單</h4>

                            <form method="GET" action="" class="mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <input type="text" name="stock_code" class="form-control form-control-sm"
                                               placeholder="股票代號 / 名稱" value="{{ request('stock_code') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <select name="market" class="form-control form-control-sm">
                                            <option value="">全部市場</option>
                                            <option value="tpex" {{ request('market') === 'tpex' ? 'selected' : '' }}>上櫃 TPEX</option>
                                            <option value="twse" {{ request('market') === 'twse' ? 'selected' : '' }}>上市 TWSE</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="status" class="form-control form-control-sm">
                                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>處置中</option>
                                            <option value="all"    {{ $status === 'all'    ? 'selected' : '' }}>全部記錄</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                                        <a href="{{ request()->url() }}" class="btn btn-secondary btn-sm">重置</a>
                                    </div>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>市場</th>
                                            <th>代號</th>
                                            <th>名稱</th>
                                            <th>公告日</th>
                                            <th>處置起始</th>
                                            <th>處置截止</th>
                                            <th>狀態</th>
                                            <th>原因</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rows as $row)
                                            @php $isActive = $row->end_date->gte(now()); @endphp
                                            <tr>
                                                <td>
                                                    @if($row->market === 'tpex')
                                                        <span class="badge badge-info">上櫃</span>
                                                    @else
                                                        <span class="badge badge-primary">上市</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ url('admin/stock-query') }}?code={{ $row->stock_code }}" style="color:#63b3ed; text-decoration:none; font-weight:600;">
                                                        {{ $row->stock_code }}
                                                    </a>
                                                </td>
                                                <td>{{ $row->stock_name }}</td>
                                                <td>{{ $row->announced_date->format('Y-m-d') }}</td>
                                                <td>{{ $row->start_date->format('Y-m-d') }}</td>
                                                <td>{{ $row->end_date->format('Y-m-d') }}</td>
                                                <td>
                                                    @if($isActive)
                                                        <span class="badge badge-danger">處置中</span>
                                                    @else
                                                        <span class="badge badge-secondary">已解除</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span title="{{ $row->reason }}" style="cursor:help;">
                                                        {{ mb_substr($row->reason, 0, 20) }}{{ mb_strlen($row->reason) > 20 ? '...' : '' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">目前無資料</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3">
                                {{ $rows->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
