const raw = import.meta.env?.VITE_API_URL ?? '/'
export const API_URL = raw.replace(/\/+$/, '')
export const API_BASE_URL = `${API_URL}/api`
