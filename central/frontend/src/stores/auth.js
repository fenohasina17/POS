import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('central_token') ?? null)
  const user  = ref(JSON.parse(localStorage.getItem('central_user') ?? 'null'))

  const isAuthenticated = computed(() => !!token.value)

  api.interceptors.request.use(cfg => {
    if (token.value) cfg.headers.Authorization = `Bearer ${token.value}`
    return cfg
  })

  async function login(email, password) {
    const res = await api.post('/auth/login', { email, password })
    token.value = res.data.token
    user.value  = res.data.user
    localStorage.setItem('central_token', token.value)
    localStorage.setItem('central_user',  JSON.stringify(user.value))
  }

  async function logout() {
    try { await api.post('/auth/logout') } catch {}
    token.value = null
    user.value  = null
    localStorage.removeItem('central_token')
    localStorage.removeItem('central_user')
  }

  async function fetchMe() {
    try {
      const res = await api.get('/auth/me')
      user.value = res.data
      localStorage.setItem('central_user', JSON.stringify(user.value))
    } catch {
      token.value = null
      user.value  = null
      localStorage.removeItem('central_token')
      localStorage.removeItem('central_user')
    }
  }

  return { token, user, isAuthenticated, login, logout, fetchMe }
})
