import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/utils/api'

export const useDashboardStore = defineStore('dashboard', () => {
  const data    = ref(null)
  const loading = ref(false)
  const error   = ref(null)

  async function fetch(restaurantId = null) {
    loading.value = true
    error.value   = null
    try {
      const params = restaurantId ? { restaurant_id: restaurantId } : {}
      const res    = await api.get('/dashboard', { params })
      data.value   = res.data
    } catch (e) {
      error.value = e.response?.data?.message ?? 'Erreur lors du chargement du dashboard'
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, fetch }
})
