import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

const API_URL = `${API_BASE_URL}/users`
const token = localStorage.getItem('token')

export default {
  getAll() {
    return axios.get(API_URL, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  getRoles(userId) {
    return axios.get(`${API_URL}/${userId}/roles`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  assignRole(userId, role) {
    console.log(role)
    return axios.post(
      `${API_URL}/${userId}/roles`,
      { role },
      {
        headers: { Authorization: `Bearer ${token}` },
      },
    )
  },

  removeRole(userId, roleId) {
    return axios.delete(`${API_URL}/${userId}/roles/${roleId}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  getPermissions(userId) {
    return axios.get(`${API_URL}/${userId}/permissions`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  hasRole(userId, _roleName) {
    return axios.get(`${API_URL}/${userId}/roles`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  create(userData) {
    return axios.post(API_URL, userData, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  delete(userId) {
    return axios.delete(`${API_URL}/${userId}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  getUser(userId) {
    return axios.get(`${API_URL}/${userId}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },

  update(userData) {
    console.log(userData)
    return axios.put(`${API_URL}/${userData.id}`, userData, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
}
