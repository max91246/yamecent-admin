<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import http from './utils/request'
import { setToken, setMember, getToken } from './utils/auth'
import { isTelegramApp, getInitData, initTgApp } from './utils/telegram'

const router = useRouter()

onMounted(async () => {
  // 若不是從 Telegram Mini App 開啟，或已有 token，略過自動登入
  if (!isTelegramApp() || getToken()) {
    if (isTelegramApp()) initTgApp()
    return
  }

  initTgApp()

  try {
    const { data } = await http.post('/auth/tg-login', {
      init_data: getInitData(),
    })

    if (data.code === 200) {
      setToken(data.token)
      setMember(data.member)
    }
  } catch {
    // 驗證失敗，讓用戶正常使用（不強制登入）
  }
})
</script>
