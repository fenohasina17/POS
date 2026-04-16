import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

const getHeaders = () => ({
  Authorization: `Bearer ${localStorage.getItem('token')}`,
})

const getCandidates = (id = null) => {
  const suffix = id ? `/${id}` : ''
  return [
    `${API_BASE_URL}/point-of-sales${suffix}`

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
}
