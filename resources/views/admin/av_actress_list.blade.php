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
                        <form method="GET" action="" class="d-flex align-items-center flex-wrap" style="gap:8px;">
                            <input type="text" name="name" class="form-control form-control-sm" style="width:200px;"
                                   placeholder="搜尋姓名" value="{{ request('name') }}">
                            <select name="is_active" class="form-control form-control-sm" style="width:120px;">
                                <option value="">全部狀態</option>
                                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>在役</option>
                                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>引退</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
                            <a href="{{ url()->current() }}" class="btn btn-sm btn-secondary">重置</a>
                            <span class="text-muted small ml-2">共 {{ $list->total() }} 筆</span>
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
