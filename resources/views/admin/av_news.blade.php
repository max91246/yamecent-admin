@extends('base.base')
@section('base')
<div class="main-panel">
    <div class="content-wrapper">

        {{-- 頁面標題 --}}
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-danger text-white mr-2">
                    <i class="mdi mdi-newspaper"></i>
                </span>
                AV 速報
                <small class="text-muted ml-2" style="font-size:0.85rem;font-weight:normal;">新人出道第一手</small>
            </h3>
        </div>

        {{-- 統計卡 --}}
        <div class="row">
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-danger card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">本月新增
                            <i class="mdi mdi-calendar-today mdi-24px float-right"></i>
                        </h5>
                        <h3 class="mb-1">{{ $stats['month'] }}</h3>
                        <small>位女優</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-warning card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">本季新增
                            <i class="mdi mdi-calendar mdi-24px float-right"></i>
                        </h5>
                        <h3 class="mb-1">{{ $stats['quarter'] }}</h3>
                        <small>位女優</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-info card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">{{ now()->year }} 出道
                            <i class="mdi mdi-star mdi-24px float-right"></i>
                        </h5>
                        <h3 class="mb-1">{{ $stats['year'] }}</h3>
                        <small>位女優</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-primary card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">資料庫總數
                            <i class="mdi mdi-database mdi-24px float-right"></i>
                        </h5>
                        <h3 class="mb-1">{{ number_format($stats['total']) }}</h3>
                        <small>位女優</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- 篩選頁籤 --}}
        <div class="row">
            <div class="col-lg-12 grid-margin">
                <div class="card">
                    <div class="card-body py-2">
                        <ul class="nav nav-pills" style="gap:6px;">
                            @foreach(['month' => '📅 本月', 'quarter' => '📊 本季', 'year' => '🗓 ' . now()->year . ' 年', 'all' => '📋 全部'] as $key => $label)
                            <li class="nav-item">
                                <a class="nav-link {{ $period === $key ? 'active' : '' }}"
                                   href="{{ url('admin/av/news') }}?period={{ $key }}"
                                   style="padding:6px 14px;font-size:0.9rem;
                                          {{ $period === $key ? 'background:#2563a8;color:#fff;' : 'color:#a0aec0;' }}">
                                    {{ $label }}
                                </a>
                            </li>
                            @endforeach
                            <li class="ml-auto align-self-center">
                                <span class="text-muted small">共 {{ $list->total() }} 筆</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- 女優速報卡片 --}}
        <div class="row">
            @forelse($list as $actress)
            @php
                $isNew = $actress->created_at->gt(now()->subDays(7));
            @endphp
            <div class="col-6 col-md-3 col-lg-2 grid-margin">
                <div class="card h-100" style="border-color:rgba(100,160,255,0.15)!important;position:relative;">
                    @if($isNew)
                    <span class="badge badge-danger" style="position:absolute;top:8px;right:8px;z-index:1;font-size:0.65rem;">🔥 NEW</span>
                    @endif
                    <div class="card-body p-2 text-center">

                        {{-- 頭像 --}}
                        <div class="mb-2">
                            @if($actress->image_url)
                                <img src="{{ $actress->image_url }}" alt="{{ $actress->name }}"
                                     referrerpolicy="no-referrer"
                                     style="width:90px;height:90px;border-radius:50%;object-fit:cover;object-position:top;
                                            border:2px solid {{ $isNew ? '#fc8181' : 'rgba(100,160,255,0.3)' }};">
                            @else
                                <div style="width:90px;height:90px;border-radius:50%;background:#1a3a6e;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="mdi mdi-account" style="font-size:2.5rem;color:#63b3ed;"></i>
                                </div>
                            @endif
                        </div>

                        {{-- 姓名 --}}
                        <p class="mb-1 font-weight-bold" style="color:#e2e8f0;font-size:0.9rem;line-height:1.2;">
                            {{ $actress->name }}
                        </p>

                        {{-- 出道年 --}}
                        @if($actress->debut_year)
                        <p class="mb-0" style="color:#fbd38d;font-size:0.75rem;font-weight:600;">
                            🌟 {{ $actress->debut_year }} 出道
                        </p>
                        @endif

                        {{-- 生日 --}}
                        @if($actress->birthday)
                        <p class="mb-0 text-muted" style="font-size:0.7rem;">
                            {{ $actress->birthday->format('Y-m-d') }}
                        </p>
                        @endif

                        {{-- 身材 --}}
                        @if($actress->height || $actress->bust)
                        <p class="mb-0 text-muted" style="font-size:0.7rem;">
                            @if($actress->height){{ $actress->height }}cm @endif
                            @if($actress->bust){{ $actress->bust }}-{{ $actress->waist }}-{{ $actress->hip }}@endif
                        </p>
                        @endif

                        {{-- 加入時間 --}}
                        <p class="mb-0" style="color:#4a5568;font-size:0.65rem;margin-top:4px;">
                            加入 {{ $actress->created_at->diffForHumans() }}
                        </p>

                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        尚無該區間的新人資料
                    </div>
                </div>
            </div>
            @endforelse
        </div>

        <div class="mt-2">
            {{ $list->appends(request()->query())->links() }}
        </div>

    </div>
</div>
@endsection
