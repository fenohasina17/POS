import apiClient from './apiClient'

const API_URL = '/point-of-sales'

export default {
  getAll() {
    return apiClient.get(API_URL).then(res => res.data)
  },
  getById(id) {
    return apiClient.get(`${API_URL}/${id}`).then(res => res.data)
  },
  create(name) {
    return apiClient.post(API_URL, { name }).then(res => res.data)
  },
  update(id, name) {
    return apiClient.put(`${API_URL}/${id}`, { name }).then(res => res.data)
  },
  delete(id) {
    return apiClient.delete(`${API_URL}/${id}`).then(res => res.data)
  },
  attachUser(posId, userId) {
    return apiClient.post(`${API_URL}/${posId}/users/${userId}`).then(res => res.data)
  },
  detachUser(posId, userId) {
    return apiClient.delete(`${API_URL}/${posId}/users/${userId}`).then(res => res.data)
  }
}
