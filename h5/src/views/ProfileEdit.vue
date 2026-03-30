<template>
  <div class="h5-page">
    <!-- 頂部導航 -->
    <div class="h5-navbar">
      <button class="back-btn" @click="$router.back()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        返回
      </button>
      <span class="title">編輯資料</span>
      <span style="width:52px"></span>
    </div>

    <form class="profile-edit-wrap" @submit.prevent="handleSubmit">

      <!-- 頭像選取 -->
      <div class="edit-avatar-section">
        <div class="edit-avatar-wrap" @click="$refs.fileInput.click()">
          <img
            v-if="avatarPreview || form.avatar"
            :src="avatarPreview || avatarUrl(form.avatar)"
            class="edit-avatar"
            alt="頭像"
          >
          <div v-else class="edit-avatar edit-avatar-placeholder">
            {{ (form.nickname || form.account || '?')[0].toUpperCase() }}
          </div>
          <div class="edit-avatar-overlay">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
          </div>
        </div>
        <p class="edit-avatar-hint">點擊更換頭像</p>
        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/gif"
          style="display:none"
          @change="onAvatarChange"
        >
      </div>

      <!-- 欄位 -->
      <div class="profile-section">
        <!-- 帳號（唯讀） -->
        <div class="edit-field">
          <label class="edit-label">帳號</label>
          <div class="edit-readonly">{{ form.account }}</div>
          <span class="edit-readonly-hint">帳號不可修改</span>
        </div>

        <!-- 暱稱 -->
        <div class="edit-field">
          <label class="edit-label">暱稱 <span class="text-danger">*</span></label>
          <input
            v-model="form.nickname"
            type="text"
            class="edit-input"
            placeholder="請輸入暱稱"
            maxlength="30"
          >
        </div>

        <!-- Email -->
        <div class="edit-field">
          <label class="edit-label">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="edit-input"
            placeholder="請輸入 Email（選填）"
          >
        </div>

        <!-- 手機 -->
        <div class="edit-field">
          <label class="edit-label">手機號碼</label>
          <input
            v-model="form.phone"
            type="tel"
            class="edit-input"
            placeholder="請輸入手機號碼（選填）"
            maxlength="20"
          >
        </div>

        <!-- 分隔線 -->
        <div class="edit-divider">修改密碼（不填則不更新）</div>

        <!-- 新密碼 -->
        <div class="edit-field">
          <label class="edit-label">新密碼</label>
          <input
            v-model="form.password"
            type="password"
            class="edit-input"
            placeholder="至少 6 位（不填則不更新）"
            maxlength="50"
          >
        </div>

        <!-- 確認密碼 -->
        <div class="edit-field" v-if="form.password">
          <label class="edit-label">確認密碼</label>
          <input
            v-model="form.confirmPassword"
            type="password"
            class="edit-input"
            placeholder="再次輸入新密碼"
            maxlength="50"
          >
        </div>
      </div>

      <!-- 錯誤訊息 -->
      <div v-if="errorMsg" class="px-4 mt-3">
        <div class="alert alert-warning py-2 small">{{ errorMsg }}</div>
      </div>

      <!-- 送出 -->
      <div class="px-4 mt-4">
        <button
          type="submit"
          class="btn btn-primary w-100"
          :disabled="submitting"
        >
          {{ submitting ? '儲存中...' : '儲存變更' }}
        </button>
      </div>

    </form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import http from '../utils/request'
import { getMember, setMember } from '../utils/auth'

const router    = useRouter()
const apiBase   = import.meta.env.VITE_API_BASE_URL || ''
const avatarUrl = (path) => path ? apiBase + path : ''

const cached = getMember() || {}

const form = reactive({
  account:         cached.account  || '',
  nickname:        cached.nickname || '',
  email:           cached.email    || '',
  phone:           cached.phone    || '',
  avatar:          cached.avatar   || '',
  password:        '',
  confirmPassword: '',
})

const avatarFile    = ref(null)
const avatarPreview = ref('')
const submitting    = ref(false)
const errorMsg      = ref('')

function onAvatarChange(e) {
  const file = e.target.files[0]
  if (!file) return
  avatarFile.value    = file
  avatarPreview.value = URL.createObjectURL(file)
}

async function handleSubmit() {
  errorMsg.value = ''

  if (!form.nickname.trim()) {
    errorMsg.value = '暱稱不能為空'
    return
  }
  if (form.password && form.password.length < 6) {
    errorMsg.value = '密碼至少需要 6 位'
    return
  }
  if (form.password && form.password !== form.confirmPassword) {
    errorMsg.value = '兩次密碼輸入不一致'
    return
  }

  submitting.value = true

  try {
    const fd = new FormData()
    fd.append('nickname', form.nickname)
    fd.append('email',    form.email    || '')
    fd.append('phone',    form.phone    || '')
    if (form.password) {
      fd.append('password', form.password)
    }
    if (avatarFile.value) {
      fd.append('avatar', avatarFile.value)
    }

    const { data } = await http.post(`/members/${cached.id}/profile`, fd)

    if (data.code === 200) {
      // 更新本地快取
      setMember({ ...cached, ...data.data })
      router.replace('/profile')
    } else {
      errorMsg.value = data.msg || '更新失敗'
    }
  } catch (err) {
    errorMsg.value = err.response?.data?.msg || '更新失敗，請重試'
  } finally {
    submitting.value = false
  }
}
</script>
