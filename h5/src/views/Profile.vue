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
      <span class="title">個人資料</span>
      <button class="profile-edit-btn" @click="$router.push('/profile/edit')">編輯</button>
    </div>

    <!-- 載入中 -->
    <div v-if="loading" class="loading-wrap">
      <div class="spinner-border text-primary"></div>
    </div>

    <div v-else class="profile-wrap">
      <!-- 頭像區塊 -->
      <div class="profile-hero">
        <div class="profile-avatar-wrap">
          <img
            v-if="member.avatar"
            :src="avatarUrl(member.avatar)"
            class="profile-avatar"
            :alt="member.nickname"
          >
          <div v-else class="profile-avatar profile-avatar-placeholder">
            {{ (member.nickname || member.account || '?')[0].toUpperCase() }}
          </div>
        </div>
        <h5 class="profile-nickname">{{ member.nickname || member.account }}</h5>
        <p class="profile-account">@{{ member.account }}</p>
      </div>

      <!-- 資料列表 -->
      <div class="profile-section">
        <div class="profile-item">
          <span class="profile-item-label">暱稱</span>
          <span class="profile-item-value">{{ member.nickname || '未設定' }}</span>
        </div>
        <div class="profile-item">
          <span class="profile-item-label">帳號</span>
          <span class="profile-item-value">{{ member.account }}</span>
        </div>
        <div class="profile-item">
          <span class="profile-item-label">Email</span>
          <span class="profile-item-value">{{ member.email || '未設定' }}</span>
        </div>
        <div class="profile-item">
          <span class="profile-item-label">手機號碼</span>
          <span class="profile-item-value">{{ member.phone || '未設定' }}</span>
        </div>
        <!-- 會員資格 -->
        <div class="profile-item">
          <span class="profile-item-label">會員資格</span>
          <span class="profile-item-value">
            <template v-if="memberStatus === 'active'">
              ⭐ VIP 會員
              <span class="d-block small text-muted">到期：{{ formatExpiry(member.member_expired_at) }}</span>
            </template>
            <template v-else-if="memberStatus === 'pending'">
              ⏳ 審核中
            </template>
            <template v-else>
              <span class="text-muted">無會員資格</span>
              <router-link to="/membership" class="d-block small text-primary">前往申請</router-link>
            </template>
          </span>
        </div>
      </div>

      <!-- 登出 -->
      <div class="px-4 mt-4">
        <button
          class="btn btn-outline-danger w-100"
          @click="handleLogout"
          :disabled="logoutLoading"
        >
          {{ logoutLoading ? '登出中...' : '登出帳號' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import http from '../utils/request'
import { getMember, setMember, clearAuth } from '../utils/auth'

const router        = useRouter()
const loading       = ref(true)
const logoutLoading = ref(false)
const apiBase       = import.meta.env.VITE_API_BASE_URL || ''
const avatarUrl     = (path) => path ? apiBase + path : ''

const member = ref({ ...getMember() })

const memberStatus = computed(() => {
  const m = member.value
  if (m.is_member && m.member_expired_at && new Date(m.member_expired_at) > new Date()) return 'active'
  if (!m.is_member && m.member_applied_at) return 'pending'
  return 'none'
})

function formatExpiry(dt) {
  if (!dt) return '-'
  return dt.slice(0, 10)
}

// 從 API 拉最新資料並同步快取
async function refreshMember() {
  const cached = getMember()
  if (!cached?.id) return
  try {
    const { data } = await http.get(`/members/${cached.id}`)
    if (data.code === 200) {
      const fresh = { ...cached, ...data.data }
      member.value = fresh
      setMember(fresh)
    }
  } catch {
    // 靜默失敗，保留本地快取
  }
}

onMounted(async () => {
  loading.value = false
  await refreshMember()
})

async function handleLogout() {
  logoutLoading.value = true
  try {
    await http.post('/auth/logout')
  } catch {
    // 即使 API 失敗也清除本地 token
  } finally {
    clearAuth()
    router.replace('/login')
  }
}
</script>
