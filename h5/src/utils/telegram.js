// Telegram Mini App 工具

export const tg = window.Telegram?.WebApp ?? null

// 是否從 Telegram Mini App 開啟
export function isTelegramApp() {
    return !!tg && !!tg.initData
}

// 取得 Telegram 用戶資訊
export function getTgUser() {
    return tg?.initDataUnsafe?.user ?? null
}

// 取得 initData（送後端驗證用）
export function getInitData() {
    return tg?.initData ?? ''
}

// 通知 TG 頁面已就緒（展開、設定顏色）
export function initTgApp() {
    if (!tg) return
    tg.ready()
    tg.expand()
}
