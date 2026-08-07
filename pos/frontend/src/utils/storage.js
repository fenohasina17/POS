export const storage = {
  setAuth(token, user, roles = [], permissions = []) {
    // Le token est stocké dans sessionStorage (scope onglet uniquement, effacé à la fermeture)
    // → inaccessible depuis d'autres onglets, non persisté entre sessions navigateur
    sessionStorage.setItem('token', token)
    // Les données utilisateur (non sensibles) restent dans localStorage pour la persistance
    const userData = { ...user, roles, permissions }
    localStorage.setItem('user', JSON.stringify(userData))
  },

  getAuth() {
    try {
      const token = sessionStorage.getItem('token')
      const userStr = localStorage.getItem('user')
      const user = userStr && userStr !== 'undefined' ? JSON.parse(userStr) : null
      return { token, user }
    } catch (e) {
      return { token: null, user: null }
    }
  },

  removeAuth() {
    sessionStorage.removeItem('token')
    localStorage.removeItem('user')
    // Nettoyage rétrocompat (token stocké dans localStorage avant cette migration)
    localStorage.removeItem('token')
  },

  clearAuth() {
    this.removeAuth()
  },
  
  setSession(session) {
    localStorage.setItem('cash_register_session', JSON.stringify(session))
    localStorage.setItem('cashRegisterSession', JSON.stringify(session)) // Double stockage pour compatibilité
  },
  
  getSession() {
    try {
      const sessionStr = localStorage.getItem('cash_register_session') || localStorage.getItem('cashRegisterSession')
      return sessionStr && sessionStr !== 'undefined' ? JSON.parse(sessionStr) : null
    } catch (e) {
      console.error('Error reading session from storage:', e)
      return null
    }
  },
  
  removeSession() {
    localStorage.removeItem('cash_register_session')
    localStorage.removeItem('cashRegisterSession')
  },
  
  // Alias pour rétrocompatibilité
  clearSession() {
    this.removeSession()
  },

  setActivePos(pos) {
    localStorage.setItem('active_pos', JSON.stringify(pos))
  },

  getActivePos() {
    try {
      const posStr = localStorage.getItem('active_pos')
      return posStr && posStr !== 'undefined' ? JSON.parse(posStr) : null
    } catch (e) {
      console.error('Error reading active pos from storage:', e)
      return null
    }
  },

  removeActivePos() {
    localStorage.removeItem('active_pos')
  }
}
