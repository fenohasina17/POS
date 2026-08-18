import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/utils/api'

export const useDashboardStore = defineStore('dashboard', () => {
  const data    = ref(null)
  const loading = ref(false)
  const error   = ref(null)

  async function fetch(params = {}) {
    loading.value = true
    error.value   = null
    try {
      const res  = await api.get('/dashboard', { params })
      data.value = res.data
    } catch (e) {
      error.value = e.response?.data?.message ?? 'Erreur lors du chargement du dashboard'
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, fetch }
})
