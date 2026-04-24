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

        {{-- 搜尋 --}}
        <div class="row">
            <div class="col-lg-12 grid-margin">
                <div class="card">
                    <div class="card-body py-3">
                        <form method="GET" action="">
                            <div class="row" style="gap:0;">
                                {{-- 第一行 --}}
                                <div class="col-md-2 mb-2">
                                    <input type="text" name="name" class="form-control form-control-sm"
                                           placeholder="姓名搜尋" value="{{ request('name') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <select name="is_active" class="form-control form-control-sm">
                                        <option value="">全部狀態</option>
                                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>在役</option>
                                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>引退</option>
                                    </select>
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
                                    <select name="cup" class="form-control form-control-sm">
                                        <option value="">罩杯</option>
                                        @foreach(['A','B','C','D','E','F','G','H','I','J','K'] as $c)
                                            <option value="{{ $c }}" {{ request('cup') === $c ? 'selected' : '' }}>{{ $c }} 罩</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-1">
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
                                <div class="col-md-3 mb-2">
                                    <div class="d-flex align-items-center" style="gap:6px;">
                                        <span class="text-muted small" style="white-space:nowrap;">腰圍</span>
                                        <select name="waist_min" class="form-control form-control-sm">
                                            <option value="">最小</option>
                                            @for($w = 48; $w <= 70; $w += 2)
                                                <option value="{{ $w }}" {{ request('waist_min') == $w ? 'selected' : '' }}>{{ $w }}</option>
                                            @endfor
                                        </select>
                                        <span class="text-muted">~</span>
                                        <select name="waist_max" class="form-control form-control-sm">
                                            <option value="">最大</option>
                                            @for($w = 48; $w <= 70; $w += 2)
                                                <option value="{{ $w }}" {{ request('waist_max') == $w ? 'selected' : '' }}>{{ $w }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-auto mb-2">
                                    <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
                                    <a href="{{ url()->current() }}" class="btn btn-sm btn-secondary ml-1">重置</a>
                                    <span class="text-muted small ml-2">共 {{ $list->total() }} 筆</span>
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
            <div class="col-6 col-md-3 col-lg-2 grid-margin">
                <div class="card h-100 text-center" style="border-color:rgba(100,160,255,0.15)!important;">
                    <div class="card-body p-2">

                        {{-- 頭像 --}}
                        <div class="mb-2">
                            @if($actress->image_url)
                                <img src="{{ $actress->image_url }}" alt="{{ $actress->name }}"
                                     referrerpolicy="no-referrer"
                                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;object-position:top;">
                            @else
                                <div style="width:80px;height:80px;border-radius:50%;background:#1a3a6e;display:inline-flex;align-items:center;justify-content:center;">
                                    <i class="mdi mdi-account" style="font-size:2rem;color:#63b3ed;"></i>
                                </div>
                            @endif
                        </div>

                        {{-- 姓名 --}}
                        <p class="mb-1 font-weight-bold" style="color:#e2e8f0;font-size:0.85rem;line-height:1.2;">
                            {{ $actress->name }}
                        </p>

                        {{-- 生日 --}}
                        @if($actress->birthday)
                        <p class="mb-0 text-muted" style="font-size:0.75rem;">
                            {{ $actress->birthday->format('Y-m-d') }}
                        </p>
                        @endif

                        {{-- 三圍 --}}
                        @if($actress->height || $actress->bust)
                        <p class="mb-0 text-muted" style="font-size:0.75rem;">
                            @if($actress->height){{ $actress->height }}cm @endif
                            @if($actress->bust){{ $actress->bust }}-{{ $actress->waist }}-{{ $actress->hip }}@endif
                        </p>
                        @endif

                        {{-- 狀態 --}}
                        <div class="mt-1">
                            @if($actress->is_active)
                                <span class="badge badge-success" style="font-size:0.65rem;">在役</span>
                            @else
                                <span class="badge badge-secondary" style="font-size:0.65rem;">引退</span>
                            @endif
                        </div>

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

        {{-- 分頁 --}}
        <div class="mt-2">
            {{ $list->appends(request()->query())->links() }}
        </div>

    </div>
</div>
@endsection
