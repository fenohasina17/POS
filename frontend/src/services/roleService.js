import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

const API_URL = `${API_BASE_URL}/roles`
const token = localStorage.getItem('token')
export default {
  getById(id) {
    return axios.get(`${API_URL}/${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  getAll() {
    return axios.get(API_URL, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  create(role) {
    return axios.post(API_URL, role, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  update(id, role) {
    return axios.put(`${API_URL}/${id}`, role, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  delete(id) {
    return axios.delete(`${API_URL}/${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  assignPermission(roleId, permission) {
    return axios.post(
      `${API_URL}/${roleId}/permissions`,
      { permission },
      {
        headers: { Authorization: `Bearer ${token}` },
      },
    )
  },
  revokePermission(roleId, permissionId) {
    return axios.delete(`${API_URL}/${roleId}/permissions/${permissionId}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
}
