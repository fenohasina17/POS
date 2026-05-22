import { ref, computed } from 'vue'
import userService from '@/services/userService'
import { storage } from '@/utils/storage'

export function useAuth() {
  const authState = ref(storage.getAuth())

  const activePos = ref(storage.getActivePos())

  const user = computed(() => authState.value?.user || null)
  const roles = computed(() => authState.value?.user?.roles || [])
  const permissions = computed(() => authState.value?.user?.permissions || [])
  const pointsOfSale = computed(() => authState.value?.user?.points_of_sale || [])

  const setActivePos = (pos) => {
    activePos.value = pos
    storage.setActivePos(pos)
  }

  const isAuthenticated = computed(() => {
    return !!authState.value?.token
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
    const currentAuth = storage.getAuth()
    if (!currentAuth?.user?.id || !currentAuth?.token) return

    try {
      // Recharger les rôles et permissions depuis l'API pour être à jour
      const [rolesRes, permsRes] = await Promise.all([
        userService.getRoles(currentAuth.user.id),
        userService.getPermissions(currentAuth.user.id)
      ])

      const newRoles = (Array.isArray(rolesRes.data) ? rolesRes.data : (rolesRes.data?.data || [])).map(r => r.name || r)
      const newPerms = (Array.isArray(permsRes.data) ? permsRes.data : (permsRes.data?.data || [])).map(p => p.name || p)

      // Mettre à jour le stockage
      storage.setAuth(
        currentAuth.token,
        currentAuth.user,
        newRoles,
        newPerms
      )

      // Mettre à jour l'état réactif
      authState.value = storage.getAuth()
    } catch (error) {
      console.error('Erreur lors du rechargement des données utilisateur :', error)
    }
  }

  const logout = () => {
    storage.clearAuth()
    storage.clearSession()
    authState.value = null
  }

  return {
    user,
    roles,
    permissions,
    pointsOfSale,
    activePos,
    setActivePos,
    isAuthenticated,
    hasRole,
    hasPermission,
    isAdmin,
    loadUserData,
    logout
  }
}
