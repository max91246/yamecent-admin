@extends('base.base')
@section('base')
<div class="main-panel">
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-primary text-white mr-2">
                    <i class="mdi mdi-chart-bar"></i>
                </span>
                台股查詢
            </h3>
        </div>

        {{-- 搜尋框 --}}
        <div class="row">
            <div class="col-lg-12 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" id="stockCode" class="form-control form-control-lg"
                                placeholder="輸入股票代號（例：2330）" maxlength="6"
                                style="font-size:1.1rem; letter-spacing:0.05em;">
                            <div class="input-group-append">
                                <button class="btn btn-primary px-4" id="queryBtn" onclick="doQuery()">
                                    <i class="mdi mdi-magnify mr-1"></i> 查詢
                                </button>
                            </div>
                        </div>
                        <div id="errorMsg" class="mt-2 text-danger" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 查詢結果 --}}
        <div id="resultArea" style="display:none;">

            {{-- 股價卡片 --}}
            <div class="row">
                <div class="col-lg-12 grid-margin">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div>
                                    <h3 class="mb-1" id="stockName" style="color:#e2e8f0;"></h3>
                                    <span class="text-muted" id="stockCode2"></span>
                                    <span id="disposalBadge" class="badge badge-danger ml-2" style="display:none;">⚠️ 處置股</span>
                                </div>
                                <div class="text-right">
                                    <h2 class="mb-0" id="stockPrice" style="color:#63b3ed; font-weight:700;"></h2>
                                    <div id="stockChange" class="mt-1"></div>
                                    <div id="stockVolume" class="text-muted small mt-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 處置股警示 --}}
            <div class="row" id="disposalRow" style="display:none;">
                <div class="col-lg-12 grid-margin">
                    <div class="card" style="border-color: rgba(252,129,129,0.4) !important; background: rgba(252,129,129,0.05) !important;">
                        <div class="card-body py-3">
                            <h5 class="mb-2" style="color:#fc8181;">⚠️ 處置股警示</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <span class="text-muted small">市場</span>
                                    <div id="disposalMarket" style="color:#e2e8f0;"></div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted small">處置期間</span>
                                    <div id="disposalPeriod" style="color:#e2e8f0;"></div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted small">原因</span>
                                    <div id="disposalReason" style="color:#e2e8f0;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 近10日股價走勢 --}}
            <div class="row" id="historyRow" style="display:none;">
                <div class="col-lg-12 grid-margin">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">近10個交易日股價走勢</h4>
                            <canvas id="priceChart" height="80"></canvas>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>日期</th>
                                            <th class="text-right">開盤</th>
                                            <th class="text-right">最高</th>
                                            <th class="text-right">最低</th>
                                            <th class="text-right">收盤</th>
                                            <th class="text-right">漲跌</th>
                                            <th class="text-right">成交量（張）</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 三大法人 + 營收 --}}
            <div class="row">
                {{-- 三大法人 --}}
                <div class="col-lg-6 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">近10日三大法人買賣超（張）</h4>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="instTable">
                                    <thead>
                                        <tr>
                                            <th>日期</th>
                                            <th class="text-right">外資</th>
                                            <th class="text-right">投信</th>
                                            <th class="text-right">自營</th>
                                        </tr>
                                    </thead>
                                    <tbody id="instBody"></tbody>
                                    <tfoot>
                                        <tr id="instSum" style="font-weight:600; border-top:1px solid rgba(100,160,255,0.2);">
                                            <td>合計</td>
                                            <td class="text-right" id="sumForeign"></td>
                                            <td class="text-right" id="sumTrust"></td>
                                            <td class="text-right" id="sumDealer"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div id="instNoData" class="text-muted text-center py-3" style="display:none;">暫無三大法人資料</div>
                        </div>
                    </div>
                </div>

                {{-- 月營收 --}}
                <div class="col-lg-6 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">月營收（千元）</h4>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>月份</th>
                                            <th class="text-right">營收</th>
                                            <th class="text-right">月增</th>
                                            <th class="text-right">年增</th>
                                        </tr>
                                    </thead>
                                    <tbody id="revBody"></tbody>
                                </table>
                            </div>
                            <div id="revNoData" class="text-muted text-center py-3" style="display:none;">暫無月營收資料</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 相關新聞 --}}
            <div class="row">
                <div class="col-lg-12 grid-margin">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">相關新聞</h4>
                            <ul id="newsList" class="list-unstyled mb-0"></ul>
                            <div id="newsNoData" class="text-muted text-center py-3" style="display:none;">暫無相關新聞</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- end resultArea --}}

    </div>
</div>

<style>
    #stockCode { background: #0d1224 !important; color: #e2e8f0 !important; border-color: rgba(100,160,255,0.2) !important; }
    #stockCode:focus { border-color: rgba(100,160,255,0.5) !important; box-shadow: 0 0 0 0.2rem rgba(100,160,255,0.15) !important; }
    .inst-pos { color: #68d391; }
    .inst-neg { color: #fc8181; }
    .rev-up   { color: #68d391; }
    .rev-dn   { color: #fc8181; }
    #newsList li { padding: 8px 0; border-bottom: 1px solid rgba(100,160,255,0.08); }
    #newsList li:last-child { border-bottom: none; }
    #newsList a { color: #63b3ed; text-decoration: none; }
    #newsList a:hover { color: #90cdf4; }
    .news-date { color: #4a5568; font-size: 0.8rem; margin-left: 8px; }
</style>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let priceChartInstance = null;

    document.getElementById('stockCode').addEventListener('keydown', e => {
        if (e.key === 'Enter') doQuery();
    });

    // 從 URL 參數自動帶入並查詢
    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const code = params.get('code');
        if (code) {
            document.getElementById('stockCode').value = code;
            doQuery();
        }
    });

    function doQuery() {
        const code = document.getElementById('stockCode').value.trim();
        if (!code) return;

        document.getElementById('errorMsg').style.display = 'none';
        document.getElementById('resultArea').style.display = 'none';
        document.getElementById('queryBtn').disabled = true;
        document.getElementById('queryBtn').innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> 查詢中…';

        fetch('{{ url("admin/stock-query/search") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ code })
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                showError(data.error);
                return;
            }
            renderResult(data);
        })
        .catch(() => showError('查詢失敗，請稍後再試'))
        .finally(() => {
            document.getElementById('queryBtn').disabled = false;
            document.getElementById('queryBtn').innerHTML = '<i class="mdi mdi-magnify mr-1"></i> 查詢';
        });
    }

    function showError(msg) {
        const el = document.getElementById('errorMsg');
        el.textContent = msg;
        el.style.display = 'block';
    }

    function fmtNum(n, dec = 0) {
        if (n === null || n === undefined) return '-';
        return Number(n).toLocaleString('zh-TW', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }

    function instColor(v) {
        if (v === null || v === undefined) return '';
        return v >= 0 ? 'inst-pos' : 'inst-neg';
    }

    function renderResult(data) {
        const q = data.quote;

        // 股價
        document.getElementById('stockName').textContent = q.name;
        document.getElementById('stockCode2').textContent = data.code + '.TW';
        document.getElementById('stockPrice').textContent = 'NT$' + fmtNum(q.price, 2);

        if (q.priceChange !== null) {
            const diff = parseFloat(q.priceChange);
            const pct  = parseFloat(q.priceChangePct);
            const sign = diff >= 0 ? '+' : '';
            const color = diff >= 0 ? '#68d391' : '#fc8181';
            const arrow = diff >= 0 ? '📈' : '📉';
            document.getElementById('stockChange').innerHTML =
                `<span style="color:${color}; font-size:1rem;">${arrow} ${sign}${fmtNum(diff,2)} (${sign}${fmtNum(pct,2)}%)</span>`;
        }

        document.getElementById('stockVolume').textContent =
            q.volume ? '成交量：' + fmtNum(Math.round(q.volume / 1000)) + ' 張' : '';

        // 處置股
        const disposalRow = document.getElementById('disposalRow');
        if (data.disposal) {
            const d = data.disposal;
            document.getElementById('disposalBadge').style.display = 'inline-block';
            document.getElementById('disposalMarket').textContent  = d.market === 'twse' ? '上市（TWSE）' : '上櫃（TPEX）';
            document.getElementById('disposalPeriod').textContent  = d.start_date + ' ～ ' + d.end_date;
            document.getElementById('disposalReason').textContent  = d.reason || '-';
            disposalRow.style.display = 'block';
        } else {
            document.getElementById('disposalBadge').style.display = 'none';
            disposalRow.style.display = 'none';
        }

        // 三大法人
        const instBody = document.getElementById('instBody');
        instBody.innerHTML = '';
        if (data.institutional && data.institutional.length) {
            document.getElementById('instTable').style.display = '';
            document.getElementById('instNoData').style.display = 'none';
            let sumF = 0, sumT = 0, sumD = 0;
            data.institutional.forEach(row => {
                sumF += row.foreign || 0;
                sumT += row.trust  || 0;
                sumD += row.dealer || 0;
                instBody.innerHTML += `<tr>
                    <td>${row.date}</td>
                    <td class="text-right ${instColor(row.foreign)}">${fmtNum(row.foreign)}</td>
                    <td class="text-right ${instColor(row.trust)}">${fmtNum(row.trust)}</td>
                    <td class="text-right ${instColor(row.dealer)}">${fmtNum(row.dealer)}</td>
                </tr>`;
            });
            document.getElementById('sumForeign').className = `text-right ${instColor(sumF)}`;
            document.getElementById('sumForeign').textContent = fmtNum(sumF);
            document.getElementById('sumTrust').className   = `text-right ${instColor(sumT)}`;
            document.getElementById('sumTrust').textContent  = fmtNum(sumT);
            document.getElementById('sumDealer').className  = `text-right ${instColor(sumD)}`;
            document.getElementById('sumDealer').textContent = fmtNum(sumD);
        } else {
            document.getElementById('instTable').style.display = 'none';
            document.getElementById('instNoData').style.display = 'block';
        }

        // 月營收
        const revBody = document.getElementById('revBody');
        revBody.innerHTML = '';
        if (data.revenues && data.revenues.length) {
            document.getElementById('revNoData').style.display = 'none';
            data.revenues.forEach(r => {
                const d   = r.date ? r.date.substring(0, 7) : '';
                const lbl = d ? d.substring(2, 4) + '/' + d.substring(5, 7) : '-';
                const rev = Math.round((r.revenue || 0) / 1000);
                const mom = parseFloat(r.revenueMoM || 0);
                const yoy = parseFloat(r.revenueYoY || 0);
                revBody.innerHTML += `<tr>
                    <td>${lbl}</td>
                    <td class="text-right">${fmtNum(rev)}</td>
                    <td class="text-right ${mom >= 0 ? 'rev-up' : 'rev-dn'}">${mom >= 0 ? '▲' : '▼'}${fmtNum(Math.abs(mom),1)}%</td>
                    <td class="text-right ${yoy >= 0 ? 'rev-up' : 'rev-dn'}">${yoy >= 0 ? '▲' : '▼'}${fmtNum(Math.abs(yoy),1)}%</td>
                </tr>`;
            });
        } else {
            document.getElementById('revNoData').style.display = 'block';
        }

        // 新聞
        const newsList = document.getElementById('newsList');
        newsList.innerHTML = '';
        if (data.news && data.news.length) {
            document.getElementById('newsNoData').style.display = 'none';
            data.news.forEach(n => {
                newsList.innerHTML += `<li>
                    <a href="${n.link}" target="_blank" rel="noopener">${n.title}</a>
                    <span class="news-date">${n.date}</span>
                </li>`;
            });
        } else {
            document.getElementById('newsNoData').style.display = 'block';
        }

        // 近10日走勢
        const historyRow  = document.getElementById('historyRow');
        const historyBody = document.getElementById('historyBody');
        historyBody.innerHTML = '';

        if (data.history && data.history.length) {
            historyRow.style.display = 'block';

            // 表格（由新到舊）
            data.history.forEach((d, i) => {
                const prev  = data.history[i + 1];
                const diff  = prev ? (d.close - prev.close) : 0;
                const sign  = diff >= 0 ? '+' : '';
                const cls   = diff > 0 ? 'inst-pos' : diff < 0 ? 'inst-neg' : '';
                historyBody.innerHTML += `<tr>
                    <td>${d.date}</td>
                    <td class="text-right">${fmtNum(d.open, 2)}</td>
                    <td class="text-right">${fmtNum(d.high, 2)}</td>
                    <td class="text-right">${fmtNum(d.low,  2)}</td>
                    <td class="text-right"><strong>${fmtNum(d.close, 2)}</strong></td>
                    <td class="text-right ${cls}">${prev ? sign + fmtNum(diff, 2) : '-'}</td>
                    <td class="text-right">${fmtNum(d.volume)}</td>
                </tr>`;
            });

            // Chart.js 折線圖（舊→新）
            const sorted = [...data.history].reverse();
            const labels = sorted.map(d => d.date.substring(5));
            const closes = sorted.map(d => d.close);

            if (priceChartInstance) priceChartInstance.destroy();
            priceChartInstance = new Chart(document.getElementById('priceChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: '收盤價',
                        data: closes,
                        borderColor: '#63b3ed',
                        backgroundColor: 'rgba(99,179,237,0.08)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: '#63b3ed',
                        fill: true,
                        tension: 0.3,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#a0aec0' } } },
                    scales: {
                        x: { ticks: { color: '#718096' }, grid: { color: 'rgba(100,160,255,0.08)' } },
                        y: { ticks: { color: '#718096' }, grid: { color: 'rgba(100,160,255,0.08)' } },
                    }
                }
            });
        } else {
            historyRow.style.display = 'none';
        }

        document.getElementById('resultArea').style.display = 'block';
    }
</script>
@endsection
