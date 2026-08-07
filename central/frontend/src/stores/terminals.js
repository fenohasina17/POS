import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/utils/api'

export const useTerminalsStore = defineStore('terminals', () => {
  const list    = ref([])
  const loading = ref(false)
  const error   = ref(null)

  async function fetchAll(restaurantId = null) {
    loading.value = true
    error.value   = null
    try {
      const params = restaurantId ? { restaurant_id: restaurantId } : {}
      const res    = await api.get('/terminals', { params })
      list.value   = res.data
    } catch (e) {
      error.value = e.response?.data?.message ?? 'Erreur terminaux'
    } finally {
      loading.value = false
    }
  }

  // Met à jour un terminal en temps réel (WebSocket heartbeat)
  function updateFromHeartbeat(payload) {
    const idx = list.value.findIndex(t => t.terminal_id === payload.terminal_id)
    if (idx !== -1) {
      list.value[idx] = { ...list.value[idx], ...payload }
    }
  }

  return { list, loading, error, fetchAll, updateFromHeartbeat }
})
