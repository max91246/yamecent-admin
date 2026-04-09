/**
 * 將圖片路徑轉為可用的 URL。
 * 若路徑是完整的 HTTP URL（例如開發環境寫入的 http://yamecent-admin.local/...），
 * 只取 pathname 部分，避免 HTTPS 頁面出現 Mixed Content。
 */
export function imgUrl(path) {
    if (!path) return ''
    try {
        const url = new URL(path)
        return url.pathname
    } catch {
        // 不是完整 URL，直接回傳（相對路徑）
        return path
    }
}
