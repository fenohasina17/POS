import apiClient from '@/services/apiClient'

const API_URL = '/printers'

export default {
  getAll() {
    return apiClient.get(API_URL)
  },
  getById(id) {
    return apiClient.get(`${API_URL}/${id}`)
  },
  create(printer) {
    return apiClient.post(API_URL, printer)
  },
  update(id, printer) {
    return apiClient.put(`${API_URL}/${id}`, printer)
  },
  delete(id) {
    return apiClient.delete(`${API_URL}/${id}`)
  },
}
