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

export default api
