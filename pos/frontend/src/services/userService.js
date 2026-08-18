import apiClient from './apiClient'

const API_URL = '/users'

export default {
  getAll() {
    return apiClient.get(API_URL)
  },

  getRoles(userId) {
    return apiClient.get(`${API_URL}/${userId}/roles`)
  },

  assignRole(userId, role) {
    return apiClient.post(`${API_URL}/${userId}/roles`, { role })
  },

  removeRole(userId, roleId) {
    return apiClient.delete(`${API_URL}/${userId}/roles/${roleId}`)
  },

  getPermissions(userId) {
    return apiClient.get(`${API_URL}/${userId}/permissions`)
  },

  create(userData) {
    return apiClient.post(API_URL, userData)
  },

  delete(userId) {
    return apiClient.delete(`${API_URL}/${userId}`)
  },

  getUser(userId) {
    return apiClient.get(`${API_URL}/${userId}`)
  },

  update(userData) {
    return apiClient.put(`${API_URL}/${userData.id}`, userData)
  },
}
