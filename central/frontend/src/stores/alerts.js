import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'

export const useAlertsStore = defineStore('alerts', () => {
  const list    = ref([])
  const counts  = ref({ critical: 0, warning: 0, total: 0 })
  const loading = ref(false)

  const hasAlerts = computed(() => counts.value.total > 0)

  async function fetchCounts() {
    try {
      const res    = await api.get('/alerts/counts')
      counts.value = res.data
    } catch {}
  }

  async function fetchActive() {
    loading.value = true
    try {
      const res = await api.get('/alerts', { params: { active: 1 } })
      list.value  = res.data
    } finally {
      loading.value = false
    }
  }

  async function resolve(alertId) {
    await api.post(`/alerts/${alertId}/resolve`)
    list.value    = list.value.filter(a => a.id !== alertId)
    counts.value.total = Math.max(0, counts.value.total - 1)
  }

  return { list, counts, loading, hasAlerts, fetchCounts, fetchActive, resolve }
})
