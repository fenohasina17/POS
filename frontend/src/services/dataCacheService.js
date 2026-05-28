import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

/**
 * DataCacheService - Sans cache, appels API directs uniquement
 */
class DataCacheService {
  constructor() {
    this.CACHE_KEY = 'pos_app_cache'
    this.TTL = 0 // Cache désactivé
  }

  async getCategories(posId, token, forceRefresh = false) {
    console.log(`DataCacheService: Fetching categories from API`)
    const response = await axios.get(`${API_BASE_URL}/categories`, {
      params: { 'with_products': 1, 'with_pricing': 1 },
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        ...(posId ? { 'X-Active-POS-ID': posId } : {})
      }
    })
    return Array.isArray(response.data) ? response.data : (response.data?.data || [])
  }

  async getTables(posId, token, forceRefresh = false) {
    console.log(`DataCacheService: Fetching tables from API`)
    const response = await axios.get(`${API_BASE_URL}/tables`, {
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        ...(posId ? { 'X-Active-POS-ID': posId } : {})
      }
    })
    return Array.isArray(response.data) ? response.data : (response.data?.data || [])
  }

  async getPendingOrders(tableId, posId, token, forceRefresh = false) {
    console.log(`DataCacheService: Fetching pending orders from API. tableId: ${tableId}`)
    const isAll = tableId === 'all'
    const apiPath = isAll ? 'sales/pending' : `tables/${tableId}/pending-orders`

    const response = await axios.get(`${API_BASE_URL}/${apiPath}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        ...(posId ? { 'X-Active-POS-ID': posId } : {})
      }
    })

    return Array.isArray(response.data) ? response.data : (response.data?.data || [])
  }

  invalidatePendingOrders(id) {
    // Plus nécessaire mais gardé pour compatibilité
    console.log(`invalidatePendingOrders called for ${id}`)
  }
}

export const dataCacheService = new DataCacheService()
