import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { storage } from '@/utils/storage'

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Intercepteur pour ajouter le token et le POS actif à chaque requête
apiClient.interceptors.request.use(config => {
  const auth = storage.getAuth()
  if (auth?.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  
  const activePos = storage.getActivePos()
  if (activePos?.id) {
    config.headers['X-Active-POS-ID'] = activePos.id
  }
  
  return config
}, error => {
  return Promise.reject(error)
})

// Intercepteur pour gérer les erreurs globales (ex: 401 Unauthorized)
apiClient.interceptors.response.use(response => response, error => {
  if (error.response?.status === 401) {
    storage.removeAuth()
    // Optionnel: rediriger vers login si nécessaire
    // window.location.href = '/login'
  }
  return Promise.reject(error)
})

export default apiClient
