import { ref } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

const getPrinterTypeLabel = (type) => {
  const labels = { cash: 'Caisse', kitchen: 'Cuisine', bar: 'Bar', invoice: 'Facture' }
  return labels[type] || type
}

export function usePrinterTypes() {
  const printerTypes = ref([])

  const fetchPrinterTypes = async () => {
    try {
      const token = localStorage.getItem('token')
      const response = await axios.get(`${API_BASE_URL}/printer-types`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })
      const data = response.data.data ? response.data.data : response.data
      if (Array.isArray(data)) {
        printerTypes.value = data
      }
    } catch (error) {
      console.error('Impossible de récupérer les types d\'imprimantes :', error)
      printerTypes.value = []
    }
  }

  return {
    printerTypes,
    fetchPrinterTypes,
    getPrinterTypeLabel,
  }
}
