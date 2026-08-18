import { ref, computed } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'

export function useCashRegisterSession() {
  const { activePos } = useAuth()
  const activeSession = ref(null)
  const isSessionBilleted = ref(false)
  const isLoading = ref(false)

  const checkActiveSession = async () => {
    if (!activePos.value?.id) return

    isLoading.value = true
    try {
      const { data } = await axios.get(`${API_BASE_URL}/sales/current-session`, {
        // Le header est injecté par apiClient, mais il faut s'assurer d'utiliser l'instance apiClient
      })
      activeSession.value = data.data
      isSessionBilleted.value = !!(data.data && data.data.is_bill_checked)
    } catch (error) {
      activeSession.value = null
      isSessionBilleted.value = false
      console.error('Erreur lors de la vérification de la session active:', error)
    } finally {
      isLoading.value = false
    }
  }

  const canSell = computed(() => {
    // Logique à adapter selon les permissions Admin
    return !isSessionBilleted.value
  })

  return {
    activeSession,
    isSessionBilleted,
    isLoading,
    checkActiveSession,
    canSell
  }
}
