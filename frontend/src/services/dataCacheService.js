import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

/**
 * DataCacheService implements the Proxy Pattern to handle data fetching.
 * It provides a cache layer using localStorage to avoid redundant API calls.
 */
class DataCacheService {
  constructor() {
    this.CACHE_KEY = 'pos_categories_data'
    this.TTL = 1000 * 60 * 15 // 15 minutes
  }

  /**
   * Gets categories from cache or fetches from API if cache is expired or missing.
   * @param {number} pointOfSaleId
   * @param {string} token
   * @param {boolean} forceRefresh
   */
  async getCategories(pointOfSaleId, token, forceRefresh = false) {
    if (!forceRefresh) {
      const cached = this._getCache(pointOfSaleId)
      if (cached) {
        console.log('DataCacheService: Returning data from cache')
        return cached
      }
    }

    console.log('DataCacheService: Fetching data from API')
    const response = await axios.get(`${API_BASE_URL}/categories`, {
      params: {
        'with_products': 1,
        'point_of_sale_id': pointOfSaleId,
        'with_pricing': 1,
      },
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`
      }
    })
    console.log('=== Réponse API complète ===', response);
console.log('Status:', response.status);
console.log('Headers:', response.headers);
console.log('Données brutes:', response.data);

    const data = Array.isArray(response.data) ? response.data : response.data.data || []
    this._setCache(pointOfSaleId, data)
    return data
  }

  _getCache(pointOfSaleId) {
    const raw = localStorage.getItem(this.CACHE_KEY)
    if (!raw) return null

    try {
      const cache = JSON.parse(raw)
      if (cache.pointOfSaleId !== pointOfSaleId) return null

      const now = new Date().getTime()
      if (now > cache.expiry) {
        localStorage.removeItem(this.CACHE_KEY)
        return null
      }

      return cache.data
    } catch (e) {
      console.error('DataCacheService: Error parsing cache', e)
      return null
    }
  }

  _setCache(pointOfSaleId, data) {
    const expiry = new Date().getTime() + this.TTL
    const cache = {
      pointOfSaleId,
      expiry,
      data
    }
    localStorage.setItem(this.CACHE_KEY, JSON.stringify(cache))
  }

  clearCache() {
    localStorage.removeItem(this.CACHE_KEY)
  }
}

// Singleton instance
export const dataCacheService = new DataCacheService()
