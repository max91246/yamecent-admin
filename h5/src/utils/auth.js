const TOKEN_KEY  = 'h5_token'
const MEMBER_KEY = 'h5_member'

export const getToken    = () => localStorage.getItem(TOKEN_KEY)
export const setToken    = (t) => localStorage.setItem(TOKEN_KEY, t)
export const clearToken  = () => localStorage.removeItem(TOKEN_KEY)

export const getMember   = () => JSON.parse(localStorage.getItem(MEMBER_KEY) || 'null')
export const setMember   = (m) => localStorage.setItem(MEMBER_KEY, JSON.stringify(m))
export const clearMember = () => localStorage.removeItem(MEMBER_KEY)

export const clearAuth   = () => { clearToken(); clearMember() }

export function isMemberActive() {
  const m = getMember()
  if (!m || !m.is_member) return false
  if (!m.member_expired_at) return false
  return new Date(m.member_expired_at) > new Date()
}

export function hasPendingApplication() {
  const m = getMember()
  return m && !m.is_member && !!m.member_applied_at
}
