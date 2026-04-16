import { ref, computed } from 'vue'
import userService from '@/services/userService'

const readStoredArray = (key) => {
  try {
    const value = localStorage.getItem(key)
    if (!value) return []
    const parsed = JSON.parse(value)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

export function useAuth() {
  const user = ref(null)
  const roles = ref(readStoredArray('user_roles'))
  const permissions = ref(readStoredArray('user_permissions'))

  const isAuthenticated = computed(() => {
    const token = localStorage.getItem('token')
    return !!token
  })

  const currentUser = computed(() => {
    const userStr = localStorage.getItem('user')
    return userStr ? JSON.parse(userStr) : null
  })

  const hasRole = (roleName) => {
    return roles.value.includes(roleName)
  }

  const hasPermission = (permissionName) => {
    return permissions.value.includes(permissionName)
  }

  const isAdmin = computed(() => {
    return hasRole('admin')
  })

  const loadUserData = async () => {
    try {
      const userData = currentUser.value
      if (userData && userData.id) {
        user.value = userData

        // Load user roles
        const rolesResponse = await userService.getRoles(userData.id)
        roles.value = rolesResponse.data.map((role) => role.name)
        localStorage.setItem('user_roles', JSON.stringify(roles.value))

        // Load user permissions
        const permissionsResponse = await userService.getPermissions(userData.id)
        permissions.value = permissionsResponse.data.map((permission) => permission.name)
        localStorage.setItem('user_permissions', JSON.stringify(permissions.value))
      } else {
        roles.value = []
        permissions.value = []
        localStorage.removeItem('user_roles')
        localStorage.removeItem('user_permissions')
      }
    } catch (error) {
      console.error('Erreur lors du chargement des données utilisateur :', error)
    }
  }

  return {
    user,
    roles,
    permissions,
    isAuthenticated,
    currentUser,
    hasRole,
    hasPermission,
    isAdmin,
    loadUserData,
  }
}
