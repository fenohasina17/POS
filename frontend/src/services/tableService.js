import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

/**
 * TableService implements the Facade Pattern.
 * It provides a simplified interface to complex operations involving tables and orders.
 */
class TableService {
  getHeaders() {
    return {
      Authorization: `Bearer ${localStorage.getItem('token')}`,
    }
  }

  /**
   * Fetches all necessary data for a table in parallel.
   * @param {number|string} tableId 
   */
  async getTableFullContext(tableId) {
    const headers = this.getHeaders()
    
    // Parallel execution for better performance
    const [tableResponse, pendingOrdersResponse] = await Promise.all([
      axios.get(`${API_BASE_URL}/tables/${tableId}`, {
        params: { with_sales: 1, with_point_of_sale: 1 },
        headers
      }),
      axios.get(`${API_BASE_URL}/tables/${tableId}/pending-orders`, {
        headers
      })
    ])

    return {
      table: tableResponse.data.data || tableResponse.data,
      pendingOrders: pendingOrdersResponse.data.data || pendingOrdersResponse.data || []
    }
  }

  async getTable(tableId) {
    const response = await axios.get(`${API_BASE_URL}/tables/${tableId}`, {
      params: { with_sales: 1, with_point_of_sale: 1 },
      headers: this.getHeaders()
    })
    return response.data.data || response.data
  }

  async getPendingOrders(tableId) {
    const response = await axios.get(`${API_BASE_URL}/tables/${tableId}/pending-orders`, {
      headers: this.getHeaders()
    })
    return response.data.data || response.data || []
  }
}

export const tableService = new TableService()
