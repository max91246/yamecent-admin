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
      <span class="title">加入會員</span>
      <span style="width:52px"></span>
    </div>

    <div class="membership-wrap px-4 py-4">

      <!-- 已是有效會員 -->
      <div v-if="isActive" class="membership-status-card active">
        <div class="status-icon">⭐</div>
        <h5>您已是 VIP 會員</h5>
        <p class="text-muted small">到期時間：{{ formatExpiry(member.member_expired_at) }}</p>
        <router-link to="/" class="btn btn-outline-primary btn-sm mt-2">瀏覽文章</router-link>
      </div>

      <!-- 申請審核中 -->
      <div v-else-if="isPending" class="membership-status-card pending">
        <div class="status-icon">⏳</div>
        <h5>申請審核中</h5>
        <p class="text-muted small">您的申請已提交，客服將於 1-3 個工作日內與您確認費用並為您開通。</p>
        <router-link to="/" class="btn btn-outline-secondary btn-sm mt-2">返回首頁</router-link>
      </div>

      <!-- 申請成功提示 -->
      <div v-else-if="applied" class="membership-status-card pending">
        <div class="status-icon">✅</div>
        <h5>申請成功！</h5>
        <p class="text-muted small">您的申請已提交，客服將於 1-3 個工作日內與您確認費用並為您開通。</p>
        <router-link to="/" class="btn btn-outline-secondary btn-sm mt-2">返回首頁</router-link>
      </div>

      <!-- 申請表單 -->
      <template v-else>
        <!-- 會員權益說明 -->
        <div class="membership-hero text-center mb-4">
          <div class="hero-icon">⭐</div>
          <h4>加入 VIP 會員</h4>
          <p class="text-muted small">解鎖所有精選文章，獲取最新市場資訊</p>
        </div>

        <div class="membership-benefits mb-4">
          <div class="benefit-item">
            <span class="benefit-icon">✅</span>
            <span>免費閱讀玩股網精選文章</span>
          </div>
          <div class="benefit-item">
            <span class="benefit-icon">✅</span>
            <span>持續更新每日精選好文</span>
          </div>
          <div class="benefit-item">
            <span class="benefit-icon">✅</span>
            <span>優先獲取市場分析資訊</span>
          </div>
        </div>

        <div class="membership-notice p-3 rounded mb-4">
          <h6 class="mb-2">📋 加入方式</h6>
          <p class="small text-muted mb-0">
            填寫申請後，客服將於 1-3 個工作日內與您確認費用並為您開通會員資格。
          </p>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small mb-3">{{ error }}</div>

        <!-- 未登入 -->
        <template v-if="!isLoggedIn">
          <button class="btn btn-primary w-100 btn-lg" @click="goLogin">立即申請（請先登入）</button>
        </template>

        <!-- 已登入，可申請 -->
        <template v-else>
          <button
            class="btn btn-primary w-100 btn-lg"
            @click="handleApply"
            :disabled="submitting"
          >
            {{ submitting ? '提交中...' : '立即申請' }}
          </button>
        </template>
      </template>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import http from '../utils/request'
import { getMember, setMember, getToken, isMemberActive, hasPendingApplication } from '../utils/auth'

const router     = useRouter()
const route      = useRoute()
const isLoggedIn = !!getToken()
const member     = ref(getMember() || {})
const submitting = ref(false)
const error      = ref('')
const applied    = ref(false)

const isActive  = computed(() => isMemberActive())
const isPending = computed(() => hasPendingApplication())

function formatExpiry(dt) {
  if (!dt) return '-'
  return dt.slice(0, 10)
}

function goLogin() {
  router.push('/login?redirect=/membership')
}

async function handleApply() {
  submitting.value = true
  error.value = ''
  try {
    const id = member.value.id
    const { data } = await http.post(`/members/${id}/membership/apply`)
    if (data.code === 200) {
      if (data.msg === 'already_member') {
        // refresh and show active state
        const fresh = { ...member.value, is_member: 1 }
        setMember(fresh)
        member.value = fresh
      } else if (data.msg === 'pending') {
        // already pending
      } else {
        // 'applied'
        const fresh = { ...member.value, member_applied_at: new Date().toISOString() }
        setMember(fresh)
        member.value = fresh
        applied.value = true
      }
    } else {
      error.value = data.msg || '申請失敗，請重試'
    }
  } catch {
    error.value = '申請失敗，請稍後重試'
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  // 若已登入，刷新會員資料確保狀態最新
  if (isLoggedIn && member.value?.id) {
    try {
      const { data } = await http.get(`/members/${member.value.id}`)
      if (data.code === 200) {
        const fresh = { ...member.value, ...data.data }
        setMember(fresh)
        member.value = fresh
      }
    } catch {
      // 靜默失敗
    }
  }
})
</script>

<style scoped>
.membership-wrap {
  max-width: 480px;
  margin: 0 auto;
}
.membership-hero {
  padding-top: 8px;
}
.hero-icon {
  font-size: 3rem;
  margin-bottom: 8px;
}
.membership-benefits {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 16px;
}
.benefit-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  font-size: 0.95rem;
  border-bottom: 1px solid #eee;
}
.benefit-item:last-child {
  border-bottom: none;
}
.benefit-icon {
  font-size: 1rem;
}
.membership-notice {
  background: #fff8e1;
  border: 1px solid #ffe082;
}
.membership-status-card {
  text-align: center;
  padding: 40px 24px;
  border-radius: 16px;
  margin-top: 20px;
}
.membership-status-card.active {
  background: linear-gradient(135deg, #fff9e6, #fffde7);
  border: 1px solid #ffd740;
}
.membership-status-card.pending {
  background: #f0f4ff;
  border: 1px solid #90caf9;
}
.status-icon {
  font-size: 3rem;
  margin-bottom: 12px;
}
</style>
