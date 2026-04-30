import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

const API_URL = `${API_BASE_URL}/printers`
const token = localStorage.getItem('token')

export default {
  getAll() {
    return axios.get(API_URL, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  getById(id) {
    return axios.get(`${API_URL}/${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  create(printer) {
    return axios.post(API_URL, printer, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  update(id, printer) {
    return axios.put(`${API_URL}/${id}`, printer, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
  delete(id) {
    return axios.delete(`${API_URL}/${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
  },
}
