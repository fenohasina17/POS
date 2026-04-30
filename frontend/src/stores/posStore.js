import { defineStore } from 'pinia'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

export const usePosStore = defineStore('pos', {
  state: () => ({
    pointOfSales: [],
    selectedPOS: null,
    loading: false,
  }),
  actions: {
    async fetchPointOfSales() {
      try {
        this.loading = true
        const token = localStorage.getItem('token')
        const response = await axios.get(`${API_BASE_URL}/point-of-sales`, {
          headers: { Authorization: `Bearer ${token}` },
        })
        this.pointOfSales = response.data.data ? response.data.data : response.data
      } catch (error) {
        console.error('Erreur lors du chargement des points de vente:', error)
      } finally {
        this.loading = false
      }
    },
    setSelectedPOS(pos) {
      this.selectedPOS = pos
      // Persist in localStorage
      if (pos) {
        localStorage.setItem('selectedPOS', JSON.stringify(pos))
      } else {
        localStorage.removeItem('selectedPOS')
      }
    },
    loadSelectedPOS() {
      const stored = localStorage.getItem('selectedPOS')
      if (stored) {
        this.selectedPOS = JSON.parse(stored)
      }
    },
  },
})
