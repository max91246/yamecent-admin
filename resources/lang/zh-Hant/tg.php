<?php

return [
    // ── 主選單按鈕 ────────────────────────────────────────────────
    'menu_oil'       => '🛢 布蘭特原油',
    'menu_wtx'       => '📈 台指期貨',
    'menu_vix'       => '😨 VIX恐慌指數',
    'menu_stock'     => '📊 台股查詢',
    'menu_portfolio' => '💼 我的持股',
    'menu_settings'  => '⚙️ 設置',

    // ── 設置選單 ──────────────────────────────────────────────────
    'settings_title'  => '⚙️ 設置',
    'settings_prompt' => '請選擇要設置的項目：',
    'settings_banner' => '🖼 我的 Banner',
    'settings_lang'   => '🌐 語系',

    // ── 語系選擇 ──────────────────────────────────────────────────
    'lang_title'    => "🌐 語系設定\n\n請選擇您的語言：",
    'lang_zh_hant'  => '繁體中文',
    'lang_zh_hans'  => '简体中文',
    'lang_en'       => 'English',
    'lang_updated'  => '✅ 語系已更新為 繁體中文',

    // ── 通用 ──────────────────────────────────────────────────────
    'cancel'         => '取消',
    'cancel_aliases' => ['取消', '❌ 取消', '/cancel'],
    'cancelled'      => '已取消，請選擇查詢項目：',
    'main_menu'      => '請選擇查詢項目：',
    'cancel_hint'    => '輸入「取消」可返回',
    'cancel_hint_menu' => '輸入「取消」可返回主選單',

    // ── 台股查詢 ──────────────────────────────────────────────────
    'stock_query_prompt'   => "📊 台股查詢\n請輸入股票代號（例如：2317）\n\n輸入「取消」可返回",
    'stock_not_found'      => '❌ 找不到股票代號「:code」，請重新輸入：',

    // ── 持股：添加流程 ────────────────────────────────────────────
    'holding_add'          => '➕ 添加持股',
    'holding_add_prompt'   => "➕ 請輸入要添加的股票代號\n（例如：2317）\n\n輸入「取消」可返回主選單",
    'holding_found'        => "✅ 找到：:name（:code）\n💰 當前價：:price\n\n請輸入持有【股數】（整股：5張請輸入 5000，零股：500股請輸入 500）：",
    'holding_invalid_shares' => '❌ 股數請輸入正整數（例如：5000；零股例如：500）：',
    'holding_margin_prompt'  => '是否融資購買？',
    'holding_margin_yes'     => '✅ 是（融資）',
    'holding_margin_no'      => '❌ 否（現股）',
    'holding_margin_wait'    => "請點選上方按鈕選擇是否融資：\n\n輸入「取消」可返回主選單",
    'holding_price_prompt'   => "請輸入當時買進的每股價格（元）：\n例如：:name 買 :shares，每股 53.5 就輸入 53.5\n\n輸入「取消」可返回",
    'holding_invalid_price'  => '❌ 請輸入有效的每股買進價格（例如：53.5）：',
    'holding_added'          => "✅ 已添加持股：\n📌 :name（:code）\n📦 :shares · :type\n💵 買進價：NT$:buy_price　市值：NT$:market_val\n💰 持有成本：NT$:cost",
    'holding_margin_tag'     => '融資',
    'holding_cash_tag'       => '現股',
    'holding_margin_note'    => '（自備 40%）',

    // ── 持股：賣出流程 ────────────────────────────────────────────
    'sell_prompt'          => "💰 賣出 :name（:code）:type\n持有：:shares\n\n請輸入賣出股數（最多 :max 股）：\n\n輸入「取消」可返回",
    'sell_exceed'          => '❌ 持有只有 :shares，請重新輸入：',
    'sell_price_prompt'    => "請輸入每股賣出價格（元）：\n例如：每股 55 就輸入 55\n\n輸入「取消」可返回",
    'sell_invalid_price'   => '❌ 請輸入有效的每股賣出價格（例如：55）：',
    'sell_invalid_shares'  => '❌ 請輸入有效的賣出股數（正整數）：',
    'sell_done'            => "📤 賣出完成：\n📌 :name（:code）:shares\n💵 買進均價：NT$:buy_price　賣出：NT$:sell_price\n💸 手續費：NT$:fee　交易稅：NT$:tax\n:profit_tag：:sign NT$:profit",
    'sell_profit'          => '✅ 獲利',
    'sell_loss'            => '❌ 虧損',
    'sell_btn'             => '💰 賣出 :code',
    'margin_tag'           => '（融資）',

    // ── 我的持股 ──────────────────────────────────────────────────
    'portfolio_title'      => '💼 我的持股',
    'portfolio_empty'      => "💼 我的持股\n\n目前沒有持股記錄。",
    'portfolio_btn_add'    => '➕ 添加持股',
    'portfolio_btn_capital'=> '⚙️ 設定資金',
    'portfolio_btn_settle' => '📅 交割款查詢',
    'portfolio_no_capital' => '💰 帳戶資金：未設定（點擊⚙️設定資金）',

    // ── 設定資金 ──────────────────────────────────────────────────
    'capital_mode_prompt'  => "⚙️ 設定資金模式\n\n📌 <b>總資金設置</b>：直接輸入您的總資金（含持股部位）\n📌 <b>剩餘資金設置</b>：輸入帳戶現金餘額，系統自動加上持股成本計算總資金\n",
    'capital_btn_total'    => '💼 總資金設置',
    'capital_btn_remain'   => '💵 剩餘資金設置',
    'capital_total_prompt' => "💼 總資金設置\n請輸入您的總資金（台幣整數，例如：2000000）：\n\n輸入「取消」可返回",
    'capital_remain_prompt'=> "💵 剩餘資金設置\n請輸入您目前的帳戶現金餘額（台幣整數，例如：500000）：\n系統將自動加上持股成本計算總資金\n\n輸入「取消」可返回",
    'capital_invalid'      => '❌ 請輸入有效金額（正整數，例如：1500000）：',
    'capital_set_remain'   => "✅ 剩餘可用資金設定為 NT$:capital\n   帳戶總資金 = NT$:total",
    'capital_set_total'    => "✅ 帳戶總資金 NT$:total\n   持股占用 NT$:cost\n   → 剩餘可用 NT$:remain",
    'capital_warning'      => ' ⚠️（持股成本已超過總資金）',

    // ── 交割款 ────────────────────────────────────────────────────
    'settle_title'         => '📅 交割款查詢',
    'settle_empty'         => "📅 交割款查詢\n\n目前無待交割款項。",

    // ── Banner ────────────────────────────────────────────────────
    'banner_prompt'        => "🖼 設置我的 Banner\n\n請直接發送一張圖片作為您的 Banner。\n支援格式：JPG、PNG、GIF\n\n圖片將顯示在「我的持股」資訊上方。\n\n輸入「取消」可返回",
    'banner_wait'          => "🖼 請直接發送一張圖片作為 Banner。\n支援格式：JPG、PNG、GIF\n\n輸入「取消」可返回",
    'banner_success'       => '✅ Banner 更新成功！',
    'banner_get_fail'      => '❌ 無法取得圖片，請重新發送。',
    'banner_download_fail' => '❌ 圖片下載失敗，請重試。',
    'banner_update_fail'   => '❌ Banner 更新失敗，請稍後重試。',
];
