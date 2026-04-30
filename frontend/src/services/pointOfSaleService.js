// src/services/pointOfSale.js
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

const getHeaders = () => ({
  Authorization: `Bearer ${localStorage.getItem('token')}`,
})

const getCandidates = (id = null) => {
  const suffix = id ? `/${id}` : ''
  return [
    `${API_BASE_URL}/point-of-sales${suffix}`
    // Tu peux ajouter d'autres URLs de fallback ici si besoin
  ]
}

const fetchWithFallback = async (id = null) => {
  let lastError = null

  for (const url of getCandidates(id)) {
    try {
      return await axios.get(url, {
        headers: getHeaders(),
      })
    } catch (error) {
      lastError = error
      if (error?.response?.status !== 404) {
        throw error
      }
    }
  }

  throw lastError
}

export default {
  async getAll() {
    try {
      const response = await fetchWithFallback()
      return response.data
    } catch (error) {
      console.error('Erreur lors de la récupération des points de vente:', error)
      throw error
    }
  },

  async getById(id) {
    try {
      const response = await fetchWithFallback(id)
      return response.data
    } catch (error) {
      console.error('Erreur lors de la récupération du point de vente:', error)
      throw error
    }
  },

  // Ajoute les méthodes create, update, delete si nécessaires
  async create(name) {
    try {
      const response = await axios.post(`${API_BASE_URL}/point-of-sales`, { name }, {
        headers: getHeaders(),
      })
      return response.data
    } catch (error) {
      console.error('Erreur création:', error)
      throw error
    }
  },

  async update(id, name) {
    try {
      const response = await axios.put(`${API_BASE_URL}/point-of-sales/${id}`, { name }, {
        headers: getHeaders(),
      })
      return response.data
    } catch (error) {
      console.error('Erreur mise à jour:', error)
      throw error
    }
  },

  async delete(id) {
    try {
      await axios.delete(`${API_BASE_URL}/point-of-sales/${id}`, {
        headers: getHeaders(),
      })
    } catch (error) {
      console.error('Erreur suppression:', error)
      throw error
    }
  },
}