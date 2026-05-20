import apiClient from './apiClient'

export const tableService = {
  getAll() {
    return apiClient.get('/tables')
  },
  getAvailable() {
    return apiClient.get('/tables/available')
  },
  getOccupied() {
    return apiClient.get('/tables/occupied')
  },
  getById(id) {
    return apiClient.get(`/tables/${id}`)
  },
  updateStatus(id, status) {
    return apiClient.patch(`/tables/${id}/status`, { status })
  },
  getPendingOrders(tableId) {
    return apiClient.get(`/tables/${tableId}/pending-orders`)
  }
}
