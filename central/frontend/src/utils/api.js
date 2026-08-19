import axios from 'axios'

const env = window.__ENV__ ?? import.meta.env
const baseURL = (env.VITE_API_URL ?? 'http://localhost:9000').replace(/\/+$/, '')

const api = axios.create({
  baseURL: `${baseURL}/api`,
  headers: { 'Content-Type': 'application/json' },
})

// Injecte le token Sanctum si présent
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('central_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Redirige vers /login si le token est expiré ou invalide
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      sessionStorage.removeItem('central_token')
      sessionStorage.removeItem('central_user')
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
