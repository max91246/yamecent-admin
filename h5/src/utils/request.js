import axios from 'axios'
import { getToken, clearAuth } from './auth'

const http = axios.create({
    baseURL: '/api',
})

http.interceptors.request.use(config => {
    const token = getToken()
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

http.interceptors.response.use(
    res => res,
    err => {
        // tg-login 驗證失敗不強制跳轉，讓用戶以訪客身份繼續使用
        const url = err.config?.url || ''
        if (err.response?.status === 401 && !url.includes('/auth/tg-login')) {
            clearAuth()
            window.location.href = window.location.pathname + '#/login'
        }
        return Promise.reject(err)
    }
)

export default http
