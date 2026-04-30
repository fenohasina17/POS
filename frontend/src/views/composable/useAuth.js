// src/composables/useAuth.js
import { ref, computed } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

export const useAuth = () => {
  const user = ref(null)
  const isAuthenticated = ref(false)
  const loading = ref(false)
  const error = ref(null)

  const isAdmin = computed(() => {
    const userData = user.value
    if (!userData) return false
    return userData.role === 'admin' || userData.is_admin === true || userData.id === 1
  })

  const login = async (email, password) => {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`${API_BASE_URL}/login`, { email, password })
      const { token, user: userData, permissions = [], roles = [] } = response.data
      
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(userData))
      localStorage.setItem('user_permissions', JSON.stringify(permissions))
      localStorage.setItem('user_roles', JSON.stringify(roles))
      
      user.value = userData
      isAuthenticated.value = true
      
      return { success: true, user: userData }
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur de connexion'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const logout = () => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('user_permissions')
    localStorage.removeItem('user_roles')
    localStorage.removeItem('cash_register_session')
    user.value = null
    isAuthenticated.value = false
  }

  const loadUserData = () => {
    const userData = localStorage.getItem('user')
    const token = localStorage.getItem('token')
    
    if (userData && token) {
      try {
        user.value = JSON.parse(userData)
        isAuthenticated.value = true
      } catch (e) {
        console.error('Erreur parsing user:', e)
        logout()
      }
    }
  }

  const hasPermission = (permission) => {
    const permissions = JSON.parse(localStorage.getItem('user_permissions') || '[]')
    return permissions.includes(permission)
  }

  const hasRole = (role) => {
    const roles = JSON.parse(localStorage.getItem('user_roles') || '[]')
    return roles.includes(role)
  }

  // Initialisation
  loadUserData()

  return {
    user,
    isAuthenticated,
    isAdmin,
    loading,
    error,
    login,
    logout,
    loadUserData,
    hasPermission,
    hasRole
  }
}