import apiClient from './apiClient'

/**
 * DataCacheService - Service d'accès direct aux données
 * Utilise apiClient pour profiter des intercepteurs (auth + POS header)
 */
class DataCacheService {
  async getCategories(posId, token, forceRefresh = false) {
    console.log(`📡 Appel API: GET /categories`)
    
    const response = await apiClient.get(`/categories`, {
      params: { 'with_products': 1, 'with_pricing': 1 },
      headers: {
        'Cache-Control': 'no-cache'
      },
      timeout: 10000
    })
    
    return Array.isArray(response.data) ? response.data : (response.data?.data || [])
  }

  async getTables(posId, token) {
    try {
      console.log('📡 Appel API: GET /tables')
      
      const response = await apiClient.get(`/tables`, {
        headers: {
          'Cache-Control': 'no-cache'
        },
        timeout: 10000 // 10 secondes timeout
      })
      
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
      
      const response = await apiClient.get(`/${apiPath}`, {
        headers: {
          'Cache-Control': 'no-cache'
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
