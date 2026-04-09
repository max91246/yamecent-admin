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
      <span class="title">文章詳情</span>
      <span style="width:52px"></span>
    </div>

    <!-- 載入中 -->
    <div v-if="loading" class="loading-wrap">
      <div class="spinner-border text-primary"></div>
    </div>

    <!-- 錯誤提示 -->
    <div v-else-if="error" class="p-4 text-center text-danger small">{{ error }}</div>

    <!-- 文章內容 -->
    <div v-else-if="article" class="article-content">
      <span class="badge bg-primary mb-2">{{ typeLabel(article.type) }}</span>
      <h1>{{ article.title }}</h1>
      <p class="text-muted small mb-3">{{ formatDate(article.created_at) }}</p>
      <img v-if="article.image" :src="imgUrl(article.image)" class="cover-full" :alt="article.title">

      <!-- 非會員顯示模糊遮罩 -->
      <div v-if="!memberActive" class="content-gate-wrap">
        <div class="body content-blurred" v-html="article.content"></div>
        <div class="content-gate-overlay">
          <div class="gate-icon">🔒</div>
          <h6>加入會員解鎖全文</h6>
          <p>VIP 會員專享精選文章內容</p>
          <router-link to="/membership" class="btn btn-primary btn-sm px-4 mt-2">立即加入</router-link>
        </div>
      </div>
      <div v-else class="body" v-html="article.content"></div>

      <!-- 留言區 -->
      <div class="comment-section mt-4">
        <h5 class="comment-title mb-3">留言 ({{ commentMeta.total || 0 }})</h5>

        <!-- 留言表單 -->
        <div class="comment-form mb-3">
          <template v-if="isLoggedIn">
            <textarea
              v-model="commentContent"
              class="form-control mb-2"
              rows="3"
              placeholder="分享您的想法..."
              maxlength="500"
            ></textarea>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">{{ commentContent.length }}/500</small>
              <button
                class="btn btn-sm btn-primary"
                @click="submitComment"
                :disabled="submitting || !commentContent.trim()"
              >
                {{ submitting ? '送出中...' : '送出留言' }}
              </button>
            </div>
            <div v-if="submitError" class="alert alert-warning py-2 small mt-2">{{ submitError }}</div>
          </template>
          <template v-else>
            <div class="text-center py-3 text-muted small border rounded">
              請先 <router-link to="/login">登入</router-link> 才能留言
            </div>
          </template>
        </div>

        <!-- 留言列表 -->
        <div v-if="commentsLoading" class="text-center py-3">
          <div class="spinner-border spinner-border-sm text-secondary"></div>
        </div>
        <div v-else-if="comments.length === 0" class="text-center py-3 text-muted small">
          暫無留言，成為第一個留言的人吧！
        </div>
        <div v-else>
          <div v-for="c in comments" :key="c.id" class="comment-item d-flex mb-3">
            <img
              v-if="c.member.avatar"
              :src="imgUrl(c.member.avatar)"
              class="comment-avatar rounded-circle me-2"
              style="width:36px;height:36px;object-fit:cover;flex-shrink:0;"
            >
            <div
              v-else
              class="comment-avatar-placeholder rounded-circle me-2 bg-secondary d-flex align-items-center justify-content-center"
              style="width:36px;height:36px;flex-shrink:0;"
            >
              <span class="text-white small">{{ (c.member.nickname || '?')[0] }}</span>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-center">
                <strong class="small">{{ c.member.nickname }}</strong>
                <span class="text-muted" style="font-size:0.75rem;">{{ formatDate(c.created_at) }}</span>
              </div>
              <p class="mb-0 small mt-1" style="white-space:pre-wrap;">{{ c.content }}</p>
              <!-- 管理員回復 -->
              <div v-if="c.admin_reply" class="admin-reply mt-2 p-2 rounded">
                <div class="d-flex align-items-center mb-1">
                  <span class="badge bg-primary me-1" style="font-size:0.65rem;">官方回復</span>
                  <span class="text-muted" style="font-size:0.7rem;">{{ formatDate(c.admin_replied_at) }}</span>
                </div>
                <p class="mb-0 small" style="white-space:pre-wrap;">{{ c.admin_reply }}</p>
              </div>
            </div>
          </div>

          <!-- 分頁 -->
          <div v-if="commentMeta.last_page > 1" class="comment-pagination mt-3">
            <div class="d-flex justify-content-center align-items-center gap-2">
              <button
                class="btn btn-sm btn-outline-secondary"
                :disabled="commentMeta.current_page <= 1"
                @click="loadComments(commentMeta.current_page - 1)"
              >上一頁</button>
              <span class="small text-muted">
                {{ commentMeta.current_page }} / {{ commentMeta.last_page }}
              </span>
              <button
                class="btn btn-sm btn-outline-secondary"
                :disabled="commentMeta.current_page >= commentMeta.last_page"
                @click="loadComments(commentMeta.current_page + 1)"
              >下一頁</button>
            </div>
            <div class="d-flex justify-content-center align-items-center gap-1 mt-2">
              <span class="small text-muted">跳至</span>
              <input
                type="number"
                class="form-control form-control-sm text-center"
                style="width:60px;"
                :min="1"
                :max="commentMeta.last_page"
                v-model.number="jumpPage"
                @keyup.enter="doJump"
              >
              <span class="small text-muted">頁</span>
              <button class="btn btn-sm btn-outline-primary" @click="doJump">GO</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import http from '../utils/request'
import { getToken, getMember, setMember, isMemberActive } from '../utils/auth'

const route        = useRoute()
const article      = ref(null)
const loading      = ref(true)
const error        = ref('')
const memberActive = ref(isMemberActive())
import { imgUrl } from '../utils/image'

const TYPE_LABELS = { 1: '普通文章', 4: '玩股網' }
const typeLabel   = (t) => TYPE_LABELS[t] || '文章'

function formatDate(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const date = `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
  const time = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`
  return `${date} ${time}`
}

// 留言相關
const isLoggedIn      = !!getToken()
const comments        = ref([])
const commentsLoading = ref(false)
const commentMeta     = ref({ current_page: 1, last_page: 1, total: 0 })
const jumpPage        = ref(1)

function doJump() {
  const p = Math.min(Math.max(Math.floor(jumpPage.value) || 1, 1), commentMeta.value.last_page)
  loadComments(p)
}
const commentContent  = ref('')
const submitting      = ref(false)
const submitError     = ref('')

async function loadComments(page = 1) {
  commentsLoading.value = true
  try {
    const { data } = await http.get(`/articles/${route.params.id}/comments`, { params: { page } })
    if (data.code === 200) {
      comments.value    = data.data
      commentMeta.value = data.meta
      jumpPage.value    = data.meta.current_page
    }
  } catch {
    // 留言載入失敗靜默處理，不影響文章顯示
  } finally {
    commentsLoading.value = false
  }
}

async function submitComment() {
  if (!commentContent.value.trim()) return
  submitting.value  = true
  submitError.value = ''
  try {
    const fd = new FormData()
    fd.append('content', commentContent.value)
    const { data } = await http.post(`/articles/${route.params.id}/comments`, fd)
    if (data.code === 200) {
      commentContent.value = ''
      comments.value.unshift(data.data)
      commentMeta.value.total = (commentMeta.value.total || 0) + 1
    } else {
      submitError.value = data.msg || '留言失敗'
    }
  } catch (err) {
    const res = err.response?.data
    if (res?.code === 429) {
      submitError.value = res.msg || '請稍後再試'
    } else {
      submitError.value = '留言失敗，請重試'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  // 背景拉最新會員狀態（防止後台開通/撤銷後快取過期）
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

  try {
    const [articleRes] = await Promise.all([
      http.get(`/articles/${route.params.id}`),
      loadComments(1),
    ])
    if (articleRes.data.code === 200) {
      article.value = articleRes.data.data
    } else {
      error.value = articleRes.data.msg || '文章不存在'
    }
  } catch {
    error.value = '載入失敗，請重新整理'
  } finally {
    loading.value = false
  }
})
</script>
