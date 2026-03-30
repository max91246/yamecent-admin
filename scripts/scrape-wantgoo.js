const puppeteer = require('puppeteer');

const MAIN_URL = 'https://www.wantgoo.com/blog/daily-featured';
const API_PATH = 'daily-featured-data';

(async () => {
    const executablePath = (() => {
        const fs = require('fs');
        return [
            '/usr/bin/google-chrome-stable', '/usr/bin/google-chrome',
            '/usr/bin/chromium', '/usr/bin/chromium-browser',
        ].find(p => fs.existsSync(p));
    })();

    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    });

    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    );
    await page.setExtraHTTPHeaders({ 'Accept-Language': 'zh-TW,zh;q=0.9' });

    // 攔截頁面自己發出的 API 回應
    let captured = null;
    page.on('response', async response => {
        if (response.url().includes(API_PATH) && captured === null) {
            try {
                const data = await response.json();
                if (Array.isArray(data) && data.length > 0) {
                    captured = data;
                    process.stderr.write(`[攔截] 取得 ${data.length} 筆資料\n`);
                }
            } catch (_) {}
        }
    });

    process.stderr.write('[啟動] 前往主頁，等待 Cloudflare 通過...\n');

    try {
        await page.goto(MAIN_URL, { waitUntil: 'networkidle2', timeout: 90000 });
    } catch (e) {
        process.stderr.write('[警告] goto timeout，繼續等候資料...\n');
    }

    // 若 networkidle2 結束時還沒攔到，再等 15 秒
    if (!captured) {
        process.stderr.write('[等待] 尚未攔到 API 回應，繼續等候...\n');
        await new Promise(resolve => {
            const timer = setTimeout(resolve, 15000);
            const check = setInterval(() => {
                if (captured !== null) { clearTimeout(timer); clearInterval(check); resolve(); }
            }, 500);
        });
    }

    await browser.close();

    if (!captured) {
        process.stdout.write(JSON.stringify({ success: false, error: 'CF 未通過，未攔截到 API 回應' }));
        process.exit(1);
    }

    process.stdout.write(JSON.stringify({ success: true, data: captured }));
})();
