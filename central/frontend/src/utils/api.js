import axios from 'axios'

const env = window.__ENV__ ?? import.meta.env
const baseURL = (env.VITE_API_URL ?? 'http://localhost:9000').replace(/\/+$/, '')

const api = axios.create({
  baseURL: `${baseURL}/api`,
  headers: { 'Content-Type': 'application/json' },
})

// Injecte le token Sanctum si présent — même clé que le store auth (localStorage),
// qui est la source de vérité réelle (voir stores/auth.js). Un désaccord ici sur
// sessionStorage vs localStorage a longtemps cassé le 401-handler ci-dessous :
// il "nettoyait" un stockage que personne ne lisait, laissant le vrai token
// périmé dans localStorage — auth.isAuthenticated restait donc true après une
// expiration, le garde de route renvoyait aussitôt vers le dashboard, qui
// re-déclenchait le même 401, en boucle infinie de rechargement de page.
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('central_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Redirige vers /login si le token est expiré ou invalide
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('central_token')
      localStorage.removeItem('central_user')
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
