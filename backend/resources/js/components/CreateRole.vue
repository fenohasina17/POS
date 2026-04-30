<template>
  <div class="create-role-container">
    <h2>Créer un nouveau rôle</h2>

    <form @submit.prevent="createRole" class="role-form">
      <div class="form-group">
        <label for="roleName">Nom du rôle:</label>
        <input
          type="text"
          id="roleName"
          v-model="roleName"
          required
          class="form-control"
          placeholder="Entrez le nom du rôle"
        >
      </div>

      <div class="permissions-section">
        <h3>Permissions par table</h3>

        <div class="table-permissions">
          <div v-for="table in tables" :key="table.name" class="table-card">
            <h4>{{ table.label }}</h4>
            <div class="permissions-grid">
              <label v-for="permission in permissions" :key="permission" class="permission-checkbox">
                <input
                  type="checkbox"
                  :value="`${table.name}.${permission}`"
                  v-model="selectedPermissions"
                >
                {{ permission }}
              </label>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" :disabled="loading">
        {{ loading ? 'Création...' : 'Créer le rôle' }}
      </button>
    </form>

    <div v-if="error" class="alert alert-danger">
      {{ error }}
    </div>

    <div v-if="success" class="alert alert-success">
      Rôle créé avec succès!
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'CreateRole',
  data() {
    return {
      roleName: '',
      loading: false,
      error: null,
      success: false,
      selectedPermissions: [],
      tables: [
        { name: 'users', label: 'Utilisateurs' },
        { name: 'products', label: 'Produits' },
        { name: 'categories', label: 'Catégories' },
        { name: 'sales', label: 'Ventes' },
        { name: 'order_lines', label: 'Lignes de commande' },
        { name: 'payments', label: 'Paiements' },
        { name: 'cash_registers', label: 'Caisses' },
        { name: 'cash_register_sessions', label: 'Sessions de caisse' },
        { name: 'cash_transactions', label: 'Transactions de caisse' },
        { name: 'point_of_sales', label: 'Points de vente' },
        { name: 'printers', label: 'Imprimantes' },
        { name: 'pricing', label: 'Tarification' }
      ],
      permissions: ['view', 'create', 'update', 'delete']
    };
  },
  methods: {
    async createRole() {
      this.loading = true;
      this.error = null;
      this.success = false;

      try {
        const response = await axios.post('/api/roles', {
          name: this.roleName,
          permissions: this.selectedPermissions
        });

        this.success = true;
        this.roleName = '';
        this.selectedPermissions = [];

        // Emit event to parent component
        this.$emit('role-created', response.data);

      } catch (error) {
        this.error = error.response?.data?.message || 'Erreur lors de la création du rôle';
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
.create-role-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.role-form {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-group {
  margin-bottom: 20px;
}

.form-control {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 16px;
}

.permissions-section {
  margin: 30px 0;
}

.table-permissions {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.table-card {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.table-card h4 {
  margin-top: 0;
  margin-bottom: 15px;
  color: #495057;
}

.permissions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.permission-checkbox {
  display: flex;
  align-items: center;
  font-size: 14px;
}

.permission-checkbox input {
  margin-right: 8px;
}

.btn {
  padding: 12px 24px;
  font-size: 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:disabled {
  background-color: #6c757d;
  cursor: not-allowed;
}

.alert {
  padding: 12px;
  margin: 20px 0;
  border-radius: 4px;
}

.alert-danger {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.alert-success {
  background-color: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}
</style>
