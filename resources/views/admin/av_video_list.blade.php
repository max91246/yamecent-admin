@extends('base.base')
@section('base')
<div class="main-panel">
    <div class="content-wrapper">

        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-danger text-white mr-2">
                    <i class="mdi mdi-filmstrip"></i>
                </span>
                新片速報
                <small class="text-muted ml-2" style="font-size:0.85rem;font-weight:normal;">AV 新片第一手</small>
            </h3>
        </div>

        {{-- 統計卡 --}}
        <div class="row">
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-danger card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">今日新片 <i class="mdi mdi-fire mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ $stats['today'] }}</h3>
                        <small>部</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-warning card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">本週新片 <i class="mdi mdi-calendar-week mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ $stats['week'] }}</h3>
                        <small>部</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-info card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">本月新片 <i class="mdi mdi-calendar mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ $stats['month'] }}</h3>
                        <small>部</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 stretch-card grid-margin">
                <div class="card bg-gradient-primary card-img-holder text-white">
                    <div class="card-body">
                        <img src="/assets/images/dashboard/circle.svg" class="card-img-absolute" alt=""/>
                        <h5 class="font-weight-normal mb-2">資料庫總數 <i class="mdi mdi-database mdi-24px float-right"></i></h5>
                        <h3 class="mb-1">{{ number_format($stats['total']) }}</h3>
                        <small>部</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- 篩選 --}}
        <div class="row">
            <div class="col-lg-12 grid-margin">
                <div class="card">
                    <div class="card-body py-3">
                        <form method="GET" action="" id="filterForm">
                            {{-- 期間頁籤 --}}
                            <div class="d-flex flex-wrap align-items-center mb-2" style="gap:6px;">
                                @foreach(['today' => '🔥 今日', 'week' => '📅 本週', 'month' => '📊 本月', 'all' => '📋 全部'] as $k => $v)
                                    <a href="{{ url('admin/av/videos') }}?period={{ $k }}"
                                       class="btn btn-sm {{ $period === $k ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $v }}</a>
                                @endforeach
                                <span class="text-muted small ml-auto">共 {{ $list->total() }} 部</span>
                            </div>

                            @php
                            $quickTags = [
                                '巨乳','美乳','中出','潮吹','人妻','美少女','OL','制服',
                                '素人','無碼','高清','4K','企劃','單體','系列','SM',
                                '女同','3P','口交','肛交','泳裝','護士','教師',
                            ];
                            $activeTags = (array) request('tags', []);
                            @endphp
                            <input type="hidden" name="period" value="{{ $period }}">

                            {{-- 搜尋列 --}}
                            <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                                <input type="text" name="code" class="form-control form-control-sm" style="width:120px;"
                                       placeholder="番號" value="{{ request('code') }}">
                                <input type="text" name="actress" class="form-control form-control-sm" style="width:120px;"
                                       placeholder="女優姓名" value="{{ request('actress') }}">
                                <input type="text" name="studio" class="form-control form-control-sm" style="width:120px;"
                                       placeholder="片商" value="{{ request('studio') }}">

                                {{-- Select2 多選標籤 --}}
                                <select name="tags[]" id="tagSelect" multiple="multiple" style="width:220px;">
                                    @foreach($quickTags as $t)
                                        <option value="{{ $t }}" {{ in_array($t, $activeTags) ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                </select>

                                <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
                                <a href="{{ url('admin/av/videos') }}?period={{ $period }}" class="btn btn-sm btn-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Select2 CDN --}}
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <style>
        .select2-container--default .select2-selection--multiple {
            background-color: #0d1224 !important;
            border-color: rgba(100,160,255,0.2) !important;
            border-radius: 4px;
            min-height: 31px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #2563a8 !important;
            border-color: #2563a8 !important;
            color: #fff !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: #fff !important; }
        .select2-dropdown {
            background-color: #111628 !important;
            border-color: rgba(100,160,255,0.3) !important;
        }
        .select2-container--default .select2-results__option { color: #a0aec0; }
        .select2-container--default .select2-results__option--highlighted { background:#1a3a6e !important; color:#fff !important; }
        .select2-container--default .select2-results__option[aria-selected=true] { background:rgba(37,99,168,0.3) !important; color:#63b3ed !important; }
        .select2-search__field { background:#0d1224 !important; color:#e2e8f0 !important; border-color:rgba(100,160,255,0.2) !important; }
        </style>
        <script>
        $(document).ready(function() {
            $('#tagSelect').select2({
                placeholder: '選擇標籤（可多選）',
                allowClear: true,
                closeOnSelect: false,
            });
        });
        </script>

        {{-- 影片卡片 --}}
        <div class="row">
            @forelse($list as $v)
            <div class="col-6 col-md-4 col-lg-3 grid-margin">
                <div class="card h-100" style="border-color:rgba(100,160,255,0.15)!important;">
                    {{-- 封面 --}}
                    <div style="position:relative;overflow:hidden;padding-top:66.67%;background:#0d1224;">
                        @if($v->cover_url)
                            <img src="{{ $v->cover_url }}" alt="{{ $v->code }}"
                                 referrerpolicy="no-referrer"
                                 style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
                        @endif
                        <span class="badge badge-dark" style="position:absolute;top:6px;left:6px;font-size:0.7rem;">{{ $v->code }}</span>
                        @if($v->is_uncensored)
                            <span class="badge badge-danger" style="position:absolute;top:6px;right:6px;font-size:0.65rem;">無碼</span>
                        @endif
                    </div>
                    <div class="card-body p-2">
                        <p class="mb-1" style="color:#e2e8f0;font-size:0.8rem;line-height:1.3;
                           display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            {{ $v->title }}
                        </p>
                        @if($v->actresses)
                        <p class="mb-0" style="color:#fbd38d;font-size:0.75rem;">
                            ⭐ {{ implode(', ', $v->actresses) }}
                        </p>
                        @endif
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted" style="font-size:0.7rem;">
                                @if($v->release_date){{ $v->release_date->format('Y-m-d') }}@endif
                            </small>
                            @if($v->studio)
                            <small class="text-muted" style="font-size:0.7rem;">{{ $v->studio }}</small>
                            @endif
                        </div>
                        @if($v->source_url)
                        <a href="{{ $v->source_url }}" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-info btn-block mt-2" style="font-size:0.7rem;padding:2px;">
                            <i class="mdi mdi-open-in-new"></i> 查看來源
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        尚無影片，請先執行：<br>
                        <code>php artisan scrape:av-videos --pages=5</code>
                    </div>
                </div>
            </div>
            @endforelse
        </div>

        <div class="mt-2">{{ $list->appends(request()->query())->links() }}</div>

    </div>
</div>
@endsection
