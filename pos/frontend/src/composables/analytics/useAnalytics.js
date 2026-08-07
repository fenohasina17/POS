import { ref, computed } from 'vue'
import apiClient from '@/services/apiClient'

export function useAnalytics() {
  const salesData = ref([])
  const isLoading = ref(false)
  const error = ref(null)

  const loadSales = async (params = {}) => {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get('/sales', { params })
      salesData.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
      error.value = e
      console.error('Analytics error:', e)
    } finally {
      isLoading.value = false
    }
  }

  const totals = computed(() => {
    return salesData.value
      .filter(s => s.status !== 'pending')
      .reduce((sum, s) => sum + Number(s.final_amount ?? s.total_amount ?? 0), 0)
  })

  return { salesData, isLoading, error, loadSales, totals }
}
