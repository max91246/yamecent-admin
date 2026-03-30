<template>
  <div class="auth-page">
    <div class="auth-logo">
      <h2>Yamecent</h2>
      <p>建立您的新帳號</p>
    </div>

    <form @submit.prevent="handleRegister">
      <div class="mb-3">
        <input
          v-model="form.account"
          type="text"
          class="form-control"
          placeholder="帳號（必填）"
          autocomplete="username"
          required
        >
      </div>
      <div class="mb-3">
        <input
          v-model="form.password"
          type="password"
          class="form-control"
          placeholder="密碼（必填）"
          autocomplete="new-password"
          required
        >
      </div>
      <div class="mb-3">
        <input
          v-model="form.nickname"
          type="text"
          class="form-control"
          placeholder="暱稱（必填）"
          required
        >
      </div>
      <div class="mb-3">
        <input
          v-model="form.email"
          type="email"
          class="form-control"
          placeholder="Email（選填）"
          autocomplete="email"
        >
      </div>
      <div class="mb-3">
        <input
          v-model="form.phone"
          type="tel"
          class="form-control"
          placeholder="手機號（選填）"
          autocomplete="tel"
        >
      </div>
      <div class="mb-3">
        <label class="form-label small text-muted mb-1">頭像（選填）</label>
        <input
          ref="avatarInput"
          type="file"
          class="form-control"
          accept="image/jpeg,image/png,image/gif"
        >
      </div>

      <div v-if="error" class="alert alert-danger py-2 small mb-3">{{ error }}</div>
      <div v-if="success" class="alert alert-success py-2 small mb-3">{{ success }}</div>

      <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="loading">
        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
        {{ loading ? '註冊中...' : '立即註冊' }}
      </button>
    </form>

    <div class="text-center mt-4">
      <span class="text-muted small">已有帳號？</span>
      <router-link to="/login" class="small ms-1">去登入</router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import http from '../utils/request'

const router      = useRouter()
const loading     = ref(false)
const error       = ref('')
const success     = ref('')
const avatarInput = ref(null)
const form        = reactive({ account: '', password: '', nickname: '', email: '', phone: '' })

async function handleRegister() {
  error.value   = ''
  success.value = ''
  loading.value = true
  try {
    const fd = new FormData()
    fd.append('account',  form.account)
    fd.append('password', form.password)
    fd.append('nickname', form.nickname)
    if (form.email) fd.append('email', form.email)
    if (form.phone) fd.append('phone', form.phone)
    const file = avatarInput.value?.files?.[0]
    if (file) fd.append('avatar', file)

    await http.post('/members/register', fd)
    success.value = '註冊成功！即將跳轉至登入頁...'
    setTimeout(() => router.push('/login'), 1500)
  } catch (err) {
    const code = err.response?.data?.code
    const msg  = err.response?.data?.msg
    if (code === 409) error.value = '此帳號已被使用，請更換'
    else if (code === 422) error.value = msg || '請檢查填寫內容'
    else error.value = '註冊失敗，請稍後再試'
  } finally {
    loading.value = false
  }
}
</script>
