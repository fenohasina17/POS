// src/composables/usePrinterTypes.js
import { ref } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

export function usePrinterTypes() {
  const printerTypes = ref([])
  const loading = ref(false)
  const error = ref(null)

  const fetchPrinterTypes = async () => {
    loading.value = true
    error.value = null
    try {
      const token = localStorage.getItem('token')
      const response = await axios.get(`${API_BASE_URL}/printer-types`, {
        headers: { Authorization: `Bearer ${token}` }
      })
      const data = response.data.data || response.data
      printerTypes.value = Array.isArray(data) ? data : []
      console.log('✅ Types d\'imprimantes chargés:', printerTypes.value)
    } catch (err) {
      console.error('❌ Erreur chargement types imprimantes:', err)
      error.value = err.response?.data?.message || 'Erreur de chargement'
      printerTypes.value = []
    } finally {
      loading.value = false
    }
  }

  const getPrinterTypeName = (id) => {
    const type = printerTypes.value.find(t => t.id === id)
    return type?.name || 'Inconnu'
  }

  const getPrinterTypeId = (name) => {
    const type = printerTypes.value.find(t => t.name === name)
    return type?.id || null
  }

  return {
    printerTypes,
    loading,
    error,
    fetchPrinterTypes,
    getPrinterTypeName,
    getPrinterTypeId
  }
}