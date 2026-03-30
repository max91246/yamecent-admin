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
        if (err.response?.status === 401) {
            clearAuth()
            window.location.href = window.location.pathname + '#/login'
        }
        return Promise.reject(err)
    }
)

export default http
