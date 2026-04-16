<template>
  <div class="role-list-container">
    <!-- Header Section -->
    <div class="header-section">
      <div class="header-content">
        <h1 class="page-title">
          <span class="title-text">Gestion des Rôles</span>
          <span class="title-badge">{{ roles.length }}</span>
        </h1>
        <p class="page-subtitle">Gérez les rôles et permissions de votre système</p>
      </div>
      <button @click="showCreateRole = true" class="btn-primary">
        <svg class="icon-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Nouveau Rôle</span>
      </button>
    </div>

    <!-- Search and Filter Section -->
    <div class="controls-section">
      <div class="search-container">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0118 0z" />
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Rechercher un rôle..."
          class="search-input"
        />
      </div>

      <div class="filter-tabs">
        <button
          @click="activeFilter = 'all'"
          :class="['filter-tab', { active: activeFilter === 'all' }]"
        >
          Tous
        </button>
        <button
          @click="activeFilter = 'admin'"
          :class="['filter-tab', { active: activeFilter === 'admin' }]"
        >
          Administrateurs
        </button>
        <button
          @click="activeFilter = 'user'"
          :class="['filter-tab', { active: activeFilter === 'user' }]"
        >
          Utilisateurs
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>Chargement des rôles...</p>
    </div>

    <!-- Roles Grid -->
    <div v-else class="roles-container">
      <div class="roles-grid">
        <div
          v-for="role in filteredRoles"
          :key="role.id"
          class="role-card"
          :class="[`role-${role.name}`, { 'role-highlight': role.name === 'admin' }]"
        >
          <!-- Card Header -->
          <div class="card-header">
            <div class="role-icon-container">
              <div class="role-icon" :class="`icon-${role.name}`">
                <svg v-if="role.name === 'admin'" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
              </div>
            </div>

            <div class="role-info">
              <h3 class="role-name">{{ role.name }}</h3>
              <span class="role-type">{{ role.name === 'admin' ? 'Administrateur' : 'Utilisateur' }}</span>
            </div>

            <div class="role-status">
              <span class="status-indicator" :class="role.users_count > 0 ? 'active' : 'inactive'">
                {{ role.users_count > 0 ? 'Actif' : 'Inactif' }}
              </span>
            </div>
          </div>

          <!-- Card Body -->
          <div class="card-body">
            <div class="role-stats">
              <div class="stat-item">
                <span class="stat-number">{{ role.permissions?.length || 0 }}</span>
                <span class="stat-label">Permissions</span>
              </div>
              <div class="stat-item">
                <span class="stat-number">{{ role.users_count || 0 }}</span>
                <span class="stat-label">Utilisateurs</span>
              </div>
            </div>

            <div class="permissions-list">
              <h4 class="permissions-title">Permissions principales</h4>
              <div class="permissions-tags">
                <span
                  v-for="permission in role.permissions?.slice(0, 4)"
                  :key="permission.id"
                  class="permission-tag"
                >
                  {{ permission.name }}
                </span>
                <span v-if="role.permissions?.length > 4" class="more-count">
                  +{{ role.permissions.length - 4 }}
                </span>
              </div>
            </div>
          </div>

          <!-- Card Footer -->
          <div class="card-footer">
            <div class="role-meta">
              <span class="created-date">Créé le {{ formatDate(role.created_at) }}</span>
            </div>

            <div class="card-actions">
              <button @click="editRole(role)" class="btn-action btn-edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Modifier
              </button>
              <button
                @click="deleteRole(role)"
                class="btn-action btn-delete"
                :disabled="role.name === 'admin'"
                :class="{ 'disabled': role.name === 'admin' }"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Supprimer
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!loading && filteredRoles.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h3>Aucun rôle trouvé</h3>
      <p>{{ searchQuery ? 'Aucun rôle ne correspond à votre recherche.' : 'Commencez par créer votre premier rôle.' }}</p>
      <button v-if="!searchQuery" @click="showCreateRole = true" class="btn-primary">
        Créer un rôle
      </button>
    </div>

    <!-- Modals -->
    <CreateRole
      v-if="showCreateRole"
      @role-created="onRoleCreated"
      @close="showCreateRole = false"
    />

    <RoleEdit
      v-if="editingRole"
      :role="editingRole"
      @role-updated="onRoleUpdated"
      @close="editingRole = null"
    />
  </div>
</template>

<script>
import axios from 'axios';
import CreateRole from './CreateRole.vue';
import RoleEdit from './RoleEdit.vue';

export default {
  name: 'RoleList',
  components: {
    CreateRole,
    RoleEdit
  },
  data() {
    return {
      roles: [],
      searchQuery: '',
      activeFilter: 'all',
      loading: false,
      error: null,
      showCreateRole: false,
      editingRole: null
    };
  },
  computed: {
    filteredRoles() {
      let filtered = this.roles;

      if (this.searchQuery) {
        const query = this.searchQuery.toLowerCase();
        filtered = filtered.filter(role =>
          role.name.toLowerCase().includes(query) ||
          role.description?.toLowerCase().includes(query)
        );
      }

      if (this.activeFilter !== 'all') {
        filtered = filtered.filter(role => role.name === this.activeFilter);
      }

      return filtered;
    }
  },
  mounted() {
    this.fetchRoles();
  },
  methods: {
    async fetchRoles() {
      this.loading = true;
      try {
        const response = await axios.get('/api/roles');
        this.roles = response.data;
      } catch (error) {
        this.error = 'Erreur lors du chargement des rôles';
        console.error('Error fetching roles:', error);
      } finally {
        this.loading = false;
      }
    },
    editRole(role) {
      this.editingRole = role;
    },
    async deleteRole(role) {
      if (role.name === 'admin') {
        alert('Le rôle admin ne peut pas être supprimé');
        return;
      }

      if (confirm(`Êtes-vous sûr de vouloir supprimer le rôle "${role.name}" ?`)) {
        try {
          await axios.delete(`/api/roles/${role.id}`);
          this.fetchRoles();
        } catch (error) {
          alert('Erreur lors de la suppression du rôle');
          console.error('Error deleting role:', error);
        }
      }
    },
    onRoleCreated() {
      this.showCreateRole = false;
      this.fetchRoles();
    },
    onRoleUpdated() {
      this.editingRole = null;
      this.fetchRoles();
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString('fr-FR');
    }
  }
};
</script>

<style scoped>
/* Modern CSS Variables */
:root {
  --primary-color: #3b82f6;
  --primary-hover: #2563eb;
  --secondary-color: #64748b;
  --success-color: #10b981;
  --danger-color: #ef4444;
  --warning-color: #f59e0b;
  --background: #f8fafc;
  --surface: #ffffff;
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --border: #e2e8f0;
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
  --radius: 8px;
  --radius-lg: 12px;
}

/* Container Styles */
.role-list-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
  background: var(--background);
  min-height: 100vh;
}

/* Header Section */
.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  background: var(--surface);
  padding: 24px;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.header-content .page-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
  font-size: 28px;
  font-weight: 700;
  color: var(--text-primary);
}

.title-badge {
  background: var(--primary-color);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

.page-subtitle {
  margin: 4px 0 0 0;
  color: var(--text-secondary);
  font-size: 16px;
}

.btn-primary {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--primary-color);
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: var(--radius);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: var(--shadow-sm);
}

.btn-primary:hover {
  background: var(--primary-hover);
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}

.icon-plus {
  width: 20px;
  height: 20px;
}

/* Controls Section */
.controls-section {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.search-container {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  color: var(--text-secondary);
}

.search-input {
  width: 100%;
  padding: 12px 12px 12px 44px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
  background: var(--surface);
  transition: all 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-tabs {
  display: flex;
  gap: 8px;
  background: var(--surface);
  padding: 4px;
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
}

.filter-tab {
  padding: 8px 16px;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
}

.filter-tab.active {
  background: var(--primary-color);
  color: white;
}

/* Loading State */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
}

.loading-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid var(--border);
  border-top: 4px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-container p {
  margin-top: 16px;
  color: var(--text-secondary);
  font-size: 16px;
}

/* Roles Grid */
.roles-container {
  margin-top: 24px;
  background: #ffffff;
  border: 2px solid #e2e8f0;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 24px;
}

.role-card {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border-radius: var(--radius-lg);
  box-shadow: 0 4px 16px rgba(170, 162, 162, 0.08);
  transition: all 0.3s ease;
  overflow: hidden;
  border: 2px solid #e2e8f0;
  position: relative;
}

.role-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #3b82f6, #06d6a0);
}

.role-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-4px);
}

.role-card-admin {
  border-left: 4px solid var(--warning-color);
}

.role-card-user {
  border-left: 4px solid var(--success-color);
}

.role-card.role-highlight {
  border-left: 4px solid var(--primary-color);
}

/* Card Header */
.card-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.role-icon-container {
  flex-shrink: 0;
}

.role-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.icon-admin {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.icon-user {
  background: linear-gradient(135deg, #10b981, #059669);
}

.role-icon svg {
  width: 24px;
  height: 24px;
}

.role-info {
  flex: 1;
}

.role-name {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--text-primary);
}

.role-type {
  font-size: 14px;
  color: var(--text-secondary);
}

.role-status {
  flex-shrink: 0;
}

.status-indicator {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.status-indicator.active {
  background: rgba(16, 185, 129, 0.1);
  color: var(--success-color);
}

.status-indicator.inactive {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger-color);
}

/* Card Body */
.card-body {
  padding: 20px;
}

.role-stats {
  display: flex;
  gap: 24px;
  margin-bottom: 20px;
}

.stat-item {
  text-align: center;
}

.stat-number {
  display: block;
  font-size: 24px;
  font-weight: 700;
  color: var(--text-primary);
}

.stat-label {
  font-size: 12px;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.permissions-list {
  margin-bottom: 20px;
}

.permissions-title {
  margin: 0 0 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
}

.permissions-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.permission-tag {
  background: rgba(59, 130, 246, 0.1);
  color: var(--primary-color);
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 500;
}

.more-count {
  background: var(--text-secondary);
  color: white;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
}

/* Card Footer */
.card-footer {
  padding: 16px 20px;
  background: #f8fafc;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.role-meta {
  font-size: 12px;
  color: var(--text-secondary);
}

.card-actions {
  display: flex;
  gap: 8px;
}

.btn-action {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-edit {
  background: rgba(59, 130, 246, 0.1);
  color: var(--primary-color);
}

.btn-edit:hover {
  background: rgba(59, 130, 246, 0.2);
}

.btn-delete {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger-color);
}

.btn-delete:hover:not(.disabled) {
  background: rgba(239, 68, 68, 0.2);
}

.btn-delete.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-action svg {
  width: 14px;
  height: 14px;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 80px 20px;
}

.empty-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  color: var(--text-secondary);
}

.empty-state h3 {
  margin: 0 0 8px 0;
  font-size: 20px;
  color: var(--text-primary);
}

.empty-state p {
  margin: 0 0 24px 0;
  color: var(--text-secondary);
}

/* Responsive Design */
@media (max-width: 768px) {
  .role-list-container {
    padding: 16px;
  }

  .header-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .controls-section {
    flex-direction: column;
  }

  .roles-grid {
    grid-template-columns: 1fr;
  }

  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .card-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
}
</style>
