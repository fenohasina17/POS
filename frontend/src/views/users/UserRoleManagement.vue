<template>
  <div>
    <h2>Gestion des Rôles pour {{ user.name }}</h2>

    <h3>Rôles attribués</h3>
    <ul>
      <li v-for="role in userRoles" :key="role.id">
        {{ role.name }}
        <button @click="removeRole(role.id)">Retirer</button>
      </li>
    </ul>

    <h3>Ajouter un rôle</h3>
    <select v-model="selectedRole">
      <option v-for="role in allRoles" :key="role.id" :value="role.name">
        {{ role.name }}
      </option>
    </select>
    <button @click="assignRole">Attribuer</button>
  </div>
</template>

<script>
import userService from '@/services/userService';
import roleService from '@/services/roleService';

export default {
  props: ['userId'],
  data() {
    return {
      user: {},
      userRoles: [],
      allRoles: [],
      selectedRole: ''
    };
  },
  async created() {
    await this.fetchUser();
    await this.fetchUserRoles();
    await this.fetchAllRoles();
  },
  methods: {
    async fetchUser() {
      const response = await userService.get(this.userId);
      this.user = response.data;
    },
    async fetchUserRoles() {
      const response = await userService.getRoles(this.userId);
      this.userRoles = response.data;
    },
    async fetchAllRoles() {
      const response = await roleService.getAll();
      this.allRoles = response.data;
    },
    async assignRole() {
      if (!this.selectedRole) return;
      await userService.assignRole(this.userId, this.selectedRole);
      this.fetchUserRoles();
    },
    async removeRole(roleId) {
      await userService.removeRole(this.userId, roleId);
      this.fetchUserRoles();
    }
  }
};
</script>
