import { ref } from 'vue'
import apiClient from '@/services/apiClient'

export function useSaleProcess() {
  const isProcessing = ref(false)
  const error = ref(null)

  const finalizeSale = async (saleData) => {
    isProcessing.value = true
    error.value = null
    try {
      // apiClient injecte automatiquement le header X-Active-POS-ID via l'intercepteur
      const { data } = await apiClient.post('/sales', saleData)
      return data
    } catch (err) {
      error.value = err.response?.data?.message || 'Erreur lors de la finalisation de la vente'
      throw err
    } finally {
      isProcessing.value = false
    }
  }

  return { isProcessing, error, finalizeSale }
}
