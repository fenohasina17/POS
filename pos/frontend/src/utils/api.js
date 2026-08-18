// window.__ENV__ est injecté au démarrage du container via env-config.js
// import.meta.env sert de fallback pour le développement local (npm run dev)
const env = window.__ENV__ ?? import.meta.env
const raw = env?.VITE_API_URL ?? '/'
export const API_URL = raw.replace(/\/+$/, '')
export const API_BASE_URL = `${API_URL}/api`
