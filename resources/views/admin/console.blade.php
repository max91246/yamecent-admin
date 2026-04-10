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
                                持股中 {{ $stats['holding_users'] }} 人
                            </h6>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 第二排：內容統計 --}}
            <div class="row">
                <div class="col-md-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">內容統計</h4>
                            <div class="row mt-3">
                                <div class="col-md-3 border-right text-center">
                                    <i class="mdi mdi-file-document-outline text-primary mdi-36px"></i>
                                    <h4 class="mt-2">{{ $stats['article_total'] }}</h4>
                                    <p class="text-muted small">文章總數</p>
                                </div>
                                <div class="col-md-3 border-right text-center">
                                    <i class="mdi mdi-comment-outline text-info mdi-36px"></i>
                                    <h4 class="mt-2">{{ $stats['comment_today'] }}</h4>
                                    <p class="text-muted small">今日新增留言</p>
                                </div>
                                <div class="col-md-3 border-right text-center">
                                    <i class="mdi mdi-account-outline text-success mdi-36px"></i>
                                    <h4 class="mt-2">{{ $stats['member_active'] }}</h4>
                                    <p class="text-muted small">活躍會員</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <i class="mdi mdi-star-outline text-warning mdi-36px"></i>
                                    <h4 class="mt-2">{{ $stats['member_paid'] }}</h4>
                                    <p class="text-muted small">付費會員</p>
                                </div>
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
