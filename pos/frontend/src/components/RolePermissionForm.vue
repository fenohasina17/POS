<template>
  <div>
    <h3>Permissions associées</h3>
    <ul>
      <li v-for="permission in rolePermissions" :key="permission.id">
        {{ permission.name }}
        <button @click="revokePermission(permission.id)">Retirer</button>
      </li>
    </ul>

    <h3>Ajouter une permission</h3>
    <select v-model="selectedPermission">
      <option v-for="permission in allPermissions" :key="permission.id" :value="permission.name">
        {{ permission.name }}
      </option>
    </select>
    <button @click="assignPermission">Attribuer</button>
  </div>
</template>

<script>
import permissionService from '@/services/permissionService';
import roleService from '@/services/roleService';

export default {
  props: ['roleId'],
  data() {
    return {
      rolePermissions: [],
      allPermissions: [],
      selectedPermission: ''
    };
  },
  async created() {
    await this.fetchRolePermissions();
    await this.fetchAllPermissions();
  },
  methods: {
    async fetchRolePermissions() {
      const roleRes = await roleService.get(this.roleId);
      this.rolePermissions = roleRes.data.permissions;
    },
    async fetchAllPermissions() {
      const response = await permissionService.getAll();
      this.allPermissions = response.data;
    },
    async assignPermission() {
      if (!this.selectedPermission) return;
      await roleService.assignPermission(this.roleId, this.selectedPermission);
      this.fetchRolePermissions();
    },
    async revokePermission(permissionId) {
      await roleService.revokePermission(this.roleId, permissionId);
      this.fetchRolePermissions();
    }
  }
};
</script>
