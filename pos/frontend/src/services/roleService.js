import apiClient from './apiClient'

const API_URL = '/roles'

export default {
  getById(id) {
    return apiClient.get(`${API_URL}/${id}`)
  },
  getAll() {
    return apiClient.get(API_URL)
  },
  create(role) {
    return apiClient.post(API_URL, role)
  },
  update(id, role) {
    return apiClient.put(`${API_URL}/${id}`, role)
  },
  delete(id) {
    return apiClient.delete(`${API_URL}/${id}`)
  },
  assignPermission(roleId, permission) {
    return apiClient.post(`${API_URL}/${roleId}/permissions`, { permission })
  },
  revokePermission(roleId, permissionId) {
    return apiClient.delete(`${API_URL}/${roleId}/permissions/${permissionId}`)
  },
}
