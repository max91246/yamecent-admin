<template>
  <div class="h5-page">
    <!-- 頂部導航 -->
    <div class="h5-navbar">
      <span class="title">文章</span>
      <div class="d-flex align-items-center gap-2">
        <template v-if="member">
          <!-- 頭像 + 暱稱，點擊進入個人資料 -->
          <div class="navbar-user" @click="$router.push('/profile')">
            <img
              v-if="member.avatar"
              :src="imgUrl(member.avatar)"
              class="navbar-avatar"
              :alt="member.nickname"
            >
            <div v-else class="navbar-avatar navbar-avatar-placeholder">
              {{ (member.nickname || member.account || '?')[0].toUpperCase() }}
            </div>
            <span class="navbar-nickname">{{ member.nickname || member.account }}</span>
          </div>
        </template>
        <template v-else>
          <router-link to="/login" class="btn btn-sm btn-outline-primary">登入</router-link>
        </template>
      </div>
    </div>

    <!-- 類型篩選 Tabs -->
    <div class="type-tabs">
      <button
        v-for="t in typeOptions"
        :key="t.value"
        class="tab-btn"
        :class="{ active: activeType === t.value }"
        @click="setType(t.value)"
      >{{ t.label }}</button>
    </div>

    <!-- 首次載入中 -->
    <div v-if="loading" class="loading-wrap">
      <div class="spinner-border text-primary"></div>
    </div>

    <!-- 錯誤提示 -->
    <div v-else-if="error" class="p-4 text-center text-danger small">{{ error }}</div>

    <!-- 空狀態 -->
    <div v-else-if="articles.length === 0" class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <p>暫無文章</p>
    </div>

    <!-- 文章列表 -->
    <div v-else>
      <!-- 非會員：前兩篇正常顯示，其餘模糊 + 遮罩 -->
      <template v-for="(item, index) in articles" :key="item.id">
        <div
          class="article-card"
          :class="{ 'blurred-card': !memberActive && index >= 2 }"
          @click="memberActive || index < 2 ? $router.push('/articles/' + item.id) : null"
        >
          <img v-if="item.image" :src="imgUrl(item.image)" class="cover" :alt="item.title">
          <div v-else class="cover-placeholder">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
              <path d="M21 15l-5-5L5 21"/>
            </svg>
          </div>
          <div class="info">
            <h6>{{ item.title }}</h6>
            <div class="meta">
              <span class="badge bg-light text-secondary me-1">{{ typeLabel(item.type) }}</span>
              {{ formatDate(item.created_at) }}
              <span class="comment-count ms-2">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                {{ item.comments_count ?? 0 }}
              </span>
            </div>
          </div>
        </div>
        <!-- 在第 2 篇後插入會員提示（非會員且有更多文章時）-->
        <div v-if="!memberActive && index === 1 && articles.length > 2" class="membership-gate">
          <div class="gate-content">
            <div class="gate-icon">🔒</div>
            <p class="gate-title">加入會員即可閱讀全部文章</p>
            <p class="gate-desc text-muted small">VIP 會員專享精選文章，每日更新市場資訊</p>
            <router-link to="/membership" class="btn btn-primary btn-sm px-4">立即加入</router-link>
          </div>
        </div>
      </template>

      <!-- 懶加載哨兵 & 底部狀態 -->
      <div ref="sentinel" class="load-sentinel">
        <div v-if="loadingMore" class="py-3 text-center">
          <div class="spinner-border spinner-border-sm text-secondary"></div>
        </div>
        <div v-else-if="!hasMore" class="py-3 text-center text-muted small">
          已顯示全部文章
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import http from '../utils/request'
import { getMember, setMember, isMemberActive } from '../utils/auth'

const router       = useRouter()
const articles     = ref([])
const loading      = ref(true)
const loadingMore  = ref(false)
const error        = ref('')
const hasMore      = ref(true)
const page         = ref(1)
const sentinel     = ref(null)
const member       = getMember()
const memberActive = ref(isMemberActive())
const apiBase       = import.meta.env.VITE_API_BASE_URL || ''
const imgUrl        = (path) => path ? apiBase + path : ''

const TYPE_LABELS = { 1: '普通文章', 4: '玩股網' }
const typeLabel   = (t) => TYPE_LABELS[t] || '文章'

const typeOptions = [
  { value: 0, label: '全部' },
  { value: 1, label: '普通文章' },
  { value: 4, label: '玩股網' },
]
const activeType = ref(0)

function formatDate(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
}

// 載入指定頁（append=false 表示重置列表）
async function fetchPage(p, append = false) {
  if (append) {
    loadingMore.value = true
  } else {
    loading.value = true
    error.value   = ''
  }

  try {
    const params = { page: p }
    if (activeType.value) params.type = activeType.value

    const { data } = await http.get('/articles', { params })

    if (append) {
      articles.value.push(...(data.data || []))
    } else {
      articles.value = data.data || []
    }

    const meta = data.meta || {}
    hasMore.value = (meta.current_page || p) < (meta.last_page || 1)
    page.value    = p
  } catch {
    if (!append) error.value = '載入失敗，請重新整理'
  } finally {
    loading.value     = false
    loadingMore.value = false
  }
}

// 切換類型：重置列表從第 1 頁開始
function setType(type) {
  activeType.value = type
  fetchPage(1, false)
}

// IntersectionObserver：哨兵進入視窗時載入下一頁
let observer = null

function initObserver() {
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting && hasMore.value && !loadingMore.value && !loading.value) {
        fetchPage(page.value + 1, true)
      }
    },
    { rootMargin: '120px' }
  )
}

// 當 sentinel 元素出現在 DOM 後才 observe
watch(sentinel, (el) => {
  if (el && observer) observer.observe(el)
})

onMounted(async () => {
  initObserver()
  fetchPage(1, false)

  // 若已登入，背景拉最新會員資料更新會員狀態
  const cached = getMember()
  if (cached?.id) {
    try {
      const { data } = await http.get(`/members/${cached.id}`)
      if (data.code === 200) {
        const fresh = { ...cached, ...data.data }
        setMember(fresh)
        const expiry = fresh.member_expired_at
        memberActive.value = fresh.is_member == 1 && !!expiry && new Date(expiry) > new Date()
      }
    } catch {
      // 靜默失敗，保留快取狀態
    }
  }
})

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
})

</script>
