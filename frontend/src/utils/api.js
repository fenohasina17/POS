// const raw = import.meta.env?.VITE_API_URL ?? 'http://127.0.0.1:8000'
const raw = import.meta.env?.VITE_API_URL ?? 'http://pos-b-main.test'
export const API_URL = raw.replace(/\/+$/, '')
export const API_BASE_URL = `${API_URL}/api`
