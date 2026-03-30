<template>
  <div class="auth-page">
    <div class="auth-logo">
      <h2>Yamecent</h2>
      <p>歡迎回來，請登入您的帳號</p>
    </div>

    <form @submit.prevent="handleLogin">
      <div class="mb-3">
        <input
          v-model="form.account"
          type="text"
          class="form-control form-control-lg"
          placeholder="帳號"
          autocomplete="username"
          required
        >
      </div>
      <div class="mb-3">
        <input
          v-model="form.password"
          type="password"
          class="form-control form-control-lg"
          placeholder="密碼"
          autocomplete="current-password"
          required
        >
      </div>

      <div v-if="error" class="alert alert-danger py-2 small mb-3">{{ error }}</div>

      <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="loading">
        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
        {{ loading ? '登入中...' : '登入' }}
      </button>
    </form>

    <div class="text-center mt-4">
      <span class="text-muted small">還沒有帳號？</span>
      <router-link to="/register" class="small ms-1">立即註冊</router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import http from '../utils/request'
import { setToken, setMember } from '../utils/auth'

const router  = useRouter()
const route   = useRoute()
const loading = ref(false)
const error   = ref('')
const form    = reactive({ account: '', password: '' })

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    const fd = new FormData()
    fd.append('account',  form.account)
    fd.append('password', form.password)
    const { data } = await http.post('/auth/login', fd)
    setToken(data.token)
    setMember(data.member)
    const redirect = route.query.redirect
    router.push(redirect && redirect.startsWith('/') ? redirect : '/')
  } catch (err) {
    const code = err.response?.data?.code
    if (code === 401) error.value = '帳號或密碼錯誤'
    else if (code === 403) error.value = '帳號已停用，請聯繫客服'
    else error.value = '登入失敗，請稍後再試'
  } finally {
    loading.value = false
  }
}
</script>
