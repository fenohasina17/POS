import apiClient from './apiClient'

export const saleService = {
  getAll(params) {
    return apiClient.get('/sales', { params })
  },
  getById(id) {
    return apiClient.get(`/sales/${id}`)
  },
  create(saleData) {
    return apiClient.post('/sales', saleData)
  },
  createPending(orderData) {
    return apiClient.post('/sales/pending-order', orderData)
  },
  addToPending(saleId, products) {
    return apiClient.post(`/sales/${saleId}/add-products`, { products })
  },
  validatePending(saleId, paymentData) {
    return apiClient.post(`/sales/${saleId}/validate`, paymentData)
  },
  cancel(saleId, reason) {
    return apiClient.post(`/sales/${saleId}/cancel`, { reason })
  },
  getFormatted(saleId) {
    return apiClient.get(`/sales/${saleId}/formatted`)
  }
}
