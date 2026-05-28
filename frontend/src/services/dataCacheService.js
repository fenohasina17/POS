import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

/**
 * DataCacheService - Service d'accès direct aux données
 * Utilise Cache-Control: no-cache pour garantir des données fraîches
 */
class DataCacheService {
  async getCategories(posId, token, forceRefresh = false) {
    console.log(`📡 Appel API: GET /categories`)

    const response = await axios.get(`${API_BASE_URL}/categories`, {
      params: { 'with_products': 1, 'with_pricing': 1 },
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        'Cache-Control': 'no-cache',
        ...(posId ? { 'X-Active-POS-ID': posId } : {})
      },
      timeout: 10000
    })

    return Array.isArray(response.data) ? response.data : (response.data?.data || [])
  }

  async getTables(posId, token) {
    try {
      console.log('📡 Appel API: GET /tables')

      const response = await axios.get(`${API_BASE_URL}/tables`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          ...(posId ? { 'X-Active-POS-ID': posId } : {})
        },
          })
          console.log('📡 API response received for /tables:', response)

      const tables = response.data?.data || response.data || []
      console.log(`✅ ${tables.length} tables récupérées`)
      return tables

    } catch (error) {
      console.error('❌ Erreur getTables:', error.message)
      throw error
    }
  }

  async getPendingOrders(tableId, posId, token, forceRefresh = false) {
    try {
      const isAll = tableId === 'all'
      const apiPath = isAll ? 'sales/pending' : `tables/${tableId}/pending-orders`

      console.log(`📡 Appel API: GET /${apiPath}`)

      const response = await axios.get(`${API_BASE_URL}/${apiPath}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache',
          ...(posId ? { 'X-Active-POS-ID': posId } : {})
        },
        timeout: 10000
      })

      const orders = response.data?.data || response.data || []
      console.log(`✅ ${orders.length} commandes en attente récupérées`)
      return orders

    } catch (error) {
      console.error('❌ Erreur getPendingOrders:', error.message)
      return []
    }
  }

  invalidatePendingOrders(id) {
    // Cache désactivé - rien à faire
  }
}

export const dataCacheService = new DataCacheService()
