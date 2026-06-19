export const storage = {
  setAuth(token, user, roles = [], permissions = []) {
    localStorage.setItem('token', token)
    // On ajoute les rôles et permissions directement dans l'objet user pour simplifier
    const userData = { ...user, roles, permissions }
    localStorage.setItem('user', JSON.stringify(userData))
  },
  
  getAuth() {
    try {
      const token = localStorage.getItem('token')
      const userStr = localStorage.getItem('user')
      const user = userStr && userStr !== 'undefined' ? JSON.parse(userStr) : null
      return { token, user }
    } catch (e) {
      console.error('Error reading auth from storage:', e)
      return { token: null, user: null }
    }
  },
  
  removeAuth() {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  },
  
  // Alias pour rétrocompatibilité
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
