@extends('base.base')
@section('base')
<div class="main-panel">
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-danger text-white mr-2">
                    <i class="mdi mdi-account-multiple"></i>
                </span>
                AV 女優管理
            </h3>
        </div>

        {{-- 統計卡 --}}
        <div class="row">
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-danger card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">本月新增 <i class="mdi mdi-calendar-today mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ $stats['month'] }}</h3><small>位女優</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-warning card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">本季新增 <i class="mdi mdi-calendar mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ $stats['quarter'] }}</h3><small>位女優</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-info card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">{{ now()->year }} 出道 <i class="mdi mdi-star mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ $stats['year'] }}</h3><small>位女優</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-primary card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">資料庫總數 <i class="mdi mdi-database mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ number_format($stats['total']) }}</h3><small>位女優</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- 篩選 --}}
        <div class="row">
            <div class="col-lg-12 grid-margin">
                <div class="card">
                    <div class="card-body py-3">
                        {{-- 期間頁籤 --}}
                        <div class="d-flex flex-wrap mb-3" style="gap:6px;">
                            @foreach(['' => '📋 全部', 'month' => '📅 本月', 'quarter' => '📊 本季', 'year' => '🗓 ' . now()->year . ' 年'] as $key => $label)
                                <a href="{{ url('admin/av/actresses') }}?period={{ $key }}"
                                   class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                            @endforeach
                            <span class="text-muted small align-self-center ml-auto">共 {{ $list->total() }} 筆</span>
                        </div>

                        {{-- 搜尋列 --}}
                        <form method="GET" action="">
                            <input type="hidden" name="period" value="{{ $period }}">
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <input type="text" name="name" class="form-control form-control-sm"
                                           placeholder="搜尋姓名" value="{{ request('name') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <select name="debut_year" class="form-control form-control-sm">
                                        <option value="">出道年份</option>
                                        @for($y = 2026; $y >= 2000; $y--)
                                            <option value="{{ $y }}" {{ request('debut_year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <select name="has_image" class="form-control form-control-sm">
                                        <option value="">圖片：全部</option>
                                        <option value="1" {{ request('has_image') === '1' ? 'selected' : '' }}>✅ 有圖片</option>
                                        <option value="0" {{ request('has_image') === '0' ? 'selected' : '' }}>❌ 無圖片</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <select name="cup" class="form-control form-control-sm">
                                        <option value="">罩杯</option>
                                        @foreach(['A','B','C','D','E','F','G','H','I','J','K'] as $c)
                                            <option value="{{ $c }}" {{ request('cup') === $c ? 'selected' : '' }}>{{ $c }} 罩</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <div class="d-flex align-items-center" style="gap:6px;">
                                        <span class="text-muted small" style="white-space:nowrap;">身高</span>
                                        <select name="height_min" class="form-control form-control-sm">
                                            <option value="">最低</option>
                                            @for($h = 145; $h <= 175; $h += 5)
                                                <option value="{{ $h }}" {{ request('height_min') == $h ? 'selected' : '' }}>{{ $h }}cm</option>
                                            @endfor
                                        </select>
                                        <span class="text-muted">~</span>
                                        <select name="height_max" class="form-control form-control-sm">
                                            <option value="">最高</option>
                                            @for($h = 145; $h <= 175; $h += 5)
                                                <option value="{{ $h }}" {{ request('height_max') == $h ? 'selected' : '' }}>{{ $h }}cm</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-auto mb-2">
                                    <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
                                    <a href="{{ url('admin/av/actresses') }}" class="btn btn-sm btn-secondary ml-1">重置</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- 女優卡片 --}}
        <div class="row">
            @forelse($list as $actress)
            @php $isNew = $actress->created_at->gt(now()->subDays(7)); @endphp
            <div class="col-6 col-md-3 col-lg-2 grid-margin">
                <div class="card h-100 text-center" style="border-color:rgba(100,160,255,0.15)!important;position:relative;">
                    @if($isNew)
                        <span class="badge badge-danger" style="position:absolute;top:6px;right:6px;font-size:0.6rem;">NEW</span>
                    @endif
                    <div class="card-body p-2">
                        <div class="mb-2">
                            @if($actress->image_url)
                                <img src="{{ $actress->image_url }}" alt="{{ $actress->name }}"
                                     referrerpolicy="no-referrer"
                                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;object-position:top;
                                            border:2px solid {{ $isNew ? '#fc8181' : 'rgba(100,160,255,0.3)' }};">
                            @else
                                <div style="width:80px;height:80px;border-radius:50%;background:#1a3a6e;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="mdi mdi-account" style="font-size:2rem;color:#63b3ed;"></i>
                                </div>
                            @endif
                        </div>
                        <p class="mb-1 font-weight-bold" style="color:#e2e8f0;font-size:0.85rem;line-height:1.2;">
                            {{ $actress->name }}
                        </p>
                        @if($actress->debut_year)
                            <p class="mb-0" style="color:#fbd38d;font-size:0.72rem;">🌟 {{ $actress->debut_year }} 出道</p>
                        @endif
                        @if($actress->birthday)
                            <p class="mb-0 text-muted" style="font-size:0.72rem;">{{ $actress->birthday->format('Y-m-d') }}</p>
                        @endif
                        @if($actress->height || $actress->bust)
                            <p class="mb-0 text-muted" style="font-size:0.72rem;">
                                @if($actress->height){{ $actress->height }}cm @endif
                                @if($actress->bust){{ $actress->bust }}-{{ $actress->waist }}-{{ $actress->hip }}@endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        尚無女優資料，請先執行：<br>
                        <code>php artisan scrape:av-actresses --pages=10</code>
                    </div>
                </div>
            </div>
            @endforelse
        </div>

        <div class="mt-2">{{ $list->appends(request()->query())->links() }}</div>
    </div>
</div>
@endsection
