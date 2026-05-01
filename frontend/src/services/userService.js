import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { storage } from '@/utils/storage'

const API_URL = `${API_BASE_URL}/users`

const getAuthHeaders = () => {
  const auth = storage.getAuth()
  return { Authorization: `Bearer ${auth?.token}` }
}

export default {
  getAll() {
    return axios.get(API_URL, {
      headers: getAuthHeaders(),
    })
  },

  getRoles(userId) {
    return axios.get(`${API_URL}/${userId}/roles`, {
      headers: getAuthHeaders(),
    })
  },

  assignRole(userId, role) {
    console.log(role)
    return axios.post(
      `${API_URL}/${userId}/roles`,
      { role },
      {
        headers: getAuthHeaders(),
      },
    )
  },

  removeRole(userId, roleId) {
    return axios.delete(`${API_URL}/${userId}/roles/${roleId}`, {
      headers: getAuthHeaders(),
    })
  },

  getPermissions(userId) {
    return axios.get(`${API_URL}/${userId}/permissions`, {
      headers: getAuthHeaders(),
    })
  },

  hasRole(userId, _roleName) {
    return axios.get(`${API_URL}/${userId}/roles`, {
      headers: getAuthHeaders(),
    })
  },

  create(userData) {
    return axios.post(API_URL, userData, {
      headers: getAuthHeaders(),
    })
  },

  delete(userId) {
    return axios.delete(`${API_URL}/${userId}`, {
      headers: getAuthHeaders(),
    })
  },

  getUser(userId) {
    return axios.get(`${API_URL}/${userId}`, {
      headers: getAuthHeaders(),
    })
  },

  update(userData) {
    console.log(userData)
    return axios.put(`${API_URL}/${userData.id}`, userData, {
      headers: getAuthHeaders(),
    })
  },
}
