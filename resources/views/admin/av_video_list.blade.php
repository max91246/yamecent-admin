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

                                <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
                                <a href="{{ url('admin/av/videos') }}?period={{ $period }}" class="btn btn-sm btn-secondary">重置</a>
                            </div>

                            {{-- 標籤 Chip 多選 --}}
                            <div class="mt-2" id="tagChipArea">
                                @foreach($quickTags as $idx => $t)
                                    <span onclick="toggleChip(this,'{{ $t }}')"
                                          data-tag="{{ $t }}"
                                          class="tag-chip {{ in_array($t, $activeTags) ? 'active' : '' }}">{{ $t }}</span>
                                @endforeach
                            </div>
                            <div id="tagHiddenInputs">
                                @foreach($activeTags as $t)
                                    <input type="hidden" name="tags[]" value="{{ $t }}">
                                @endforeach
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .tag-chip {
            display: inline-block;
            padding: 3px 12px;
            margin: 3px 4px 3px 0;
            border-radius: 20px;
            font-size: 0.78rem;
            cursor: pointer;
            border: 1px solid rgba(100,160,255,0.25);
            color: #718096;
            background: transparent;
            transition: all .15s;
            user-select: none;
        }
        .tag-chip:hover {
            border-color: rgba(100,160,255,0.5);
            color: #a0aec0;
        }
        .tag-chip.active {
            background: #2563a8;
            border-color: #2563a8;
            color: #fff;
            font-weight: 500;
        }
        </style>
        <script>
        function toggleChip(el, tag) {
            el.classList.toggle('active');
            var container = document.getElementById('tagHiddenInputs');
            // 重建全部 hidden inputs
            var chips = document.querySelectorAll('.tag-chip.active');
            container.innerHTML = '';
            chips.forEach(function(c) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'tags[]';
                inp.value = c.dataset.tag;
                container.appendChild(inp);
            });
        }
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
