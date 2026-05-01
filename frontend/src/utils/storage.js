/**
 * Utilitaire pour centraliser la gestion du localStorage
 */

const KEYS = {
  AUTH: 'pos_auth',
  SESSION: 'pos_session',
  PRINTER: 'pos_printer_config'
};

export const storage = {
  // AUTH
  getAuth() {
    const data = localStorage.getItem(KEYS.AUTH);
    if (!data) return null;
    try {
      const parsed = JSON.parse(data);
      // Vérification expiration
      if (parsed.expires_at && new Date().getTime() > parsed.expires_at) {
        this.clearAuth();
        return null;
      }
      return parsed;
    } catch (e) {
      return null;
    }
  },

  setAuth(token, user, roles = [], permissions = [], expiresInHours = 24) {
    const expires_at = new Date().getTime() + expiresInHours * 60 * 60 * 1000;
    const authData = {
      token,
      expires_at,
      user: {
        ...user,
        roles,
        permissions
      }
    };
    localStorage.setItem(KEYS.AUTH, JSON.stringify(authData));

    // Compatibilité descendante : on définit aussi les anciennes clés individuelles
    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(user));
    localStorage.setItem('user_roles', JSON.stringify(roles));
    localStorage.setItem('user_permissions', JSON.stringify(permissions));
    localStorage.setItem('token_expiration', expires_at.toString());
  },

  clearAuth() {
    localStorage.removeItem(KEYS.AUTH);
    // On nettoie aussi les anciennes clés par sécurité pendant la transition
    localStorage.removeItem('token');
    localStorage.removeItem('token_expiration');
    localStorage.removeItem('user');
    localStorage.removeItem('user_expiration');
    localStorage.removeItem('user_roles');
    localStorage.removeItem('user_permissions');
  },

  // SESSION CAISSE
  getSession() {
    const data = localStorage.getItem(KEYS.SESSION) || localStorage.getItem('cashRegisterSession') || localStorage.getItem('cash_register_session');
    if (!data) return null;
    try {
      return JSON.parse(data);
    } catch (e) {
      return null;
    }
  },

  setSession(sessionData) {
    localStorage.setItem(KEYS.SESSION, JSON.stringify(sessionData));
    // Nettoyage des anciennes clés
    localStorage.removeItem('cashRegisterSession');
    localStorage.removeItem('cash_register_session');
  },

  clearSession() {
    localStorage.removeItem(KEYS.SESSION);
    localStorage.removeItem('cashRegisterSession');
    localStorage.removeItem('cash_register_session');
  }
};
