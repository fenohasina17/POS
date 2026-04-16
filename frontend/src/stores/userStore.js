import { defineStore } from 'pinia';
import axios from 'axios';
import { API_BASE_URL } from '@/utils/api';

export const useUserStore = defineStore('user', {
  state: () => ({ users: [] }),
  actions: {
    async fetchUsers() {
      const response = await axios.get(`${API_BASE_URL}/users`);
      this.users = response.data;
    }
  }
});
