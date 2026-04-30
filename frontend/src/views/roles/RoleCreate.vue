<template>
  <div class="role-create role-management-page">
    <div class="container is-fluid">
      <div class="columns is-centered">
        <div class="column is-12">
          <div class="card">
            <header class="card-header">
              <p class="card-header-title">
                <i class="fas fa-user-tag me-2"></i>
                Créer un Nouveau Rôle
              </p>
            </header>
            <div class="card-content">
              <form @submit.prevent="createRole">
                <div class="field">
                  <label class="label">Nom du rôle *</label>
                  <div class="control">
                    <input v-model="role.name" class="input is-large" :class="{ 'is-danger': errors.name }" type="text"
                      placeholder="Ex: Administrateur, Utilisateur, Modérateur" maxlength="50" required />
                  </div>
                  <p v-if="errors.name" class="help is-danger">{{ errors.name }}</p>
                  <p class="help">Le nom du rôle doit être unique et contenir entre 3 et 50 caractères.</p>
                </div>

                <div class="field">
                  <label class="label is-size-4">Permissions</label>
                  <div v-if="loading" class="has-text-centered">
                    <div class="button is-loading is-large"></div>
                    <p class="is-size-5">Chargement des permissions...</p>
                  </div>
                  <div v-if="error" class="notification is-danger">
                    <strong>Erreur:</strong> {{ error }}
                  </div>
                  <div v-if="!loading && !error">
                    <div v-if="permissions.length === 0" class="notification is-warning">
                      <strong>Aucune permission disponible.</strong>
                    </div>
                    <div v-else>
                      <div class="columns is-multiline is-mobile">
                        <div v-for="permission in permissions" :key="permission.id"
                          class="column is-12-mobile is-6-tablet is-4-desktop is-3-widescreen">
                          <div class="field">
                            <div class="control">
                              <label class="checkbox is-size-5">
                                <input type="checkbox" :value="permission.name" v-model="selectedPermissions" />
                                {{ permission.name }}
                              </label>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <br>
                <div class="field is-grouped is-grouped-right">

                  <div class="control">
                    <router-link :to="{ name: 'dashboard-roles' }" class="button is-light is-large">
                      <i class="fas fa-arrow-left me-1"></i> Annuler
                    </router-link>
                  </div>
                  <div class="control">
                    <button type="submit" class="button is-primary is-large"
                      :disabled="isCreating || !role.name.trim() || loading">
                      <span v-if="isCreating">
                        <span class="icon">
                          <i class="fas fa-spinner fa-pulse"></i>
                        </span>
                        <span>Création...</span>
                      </span>
                      <span v-else>
                        <i class="fas fa-plus me-1"></i> Créer
                      </span>
                    </button>
                  </div>
                </div>
              </form>
            </div>
            <footer class="card-footer">
              <div class="card-footer-item">
                <div class="notification is-info is-light">
                </div>
              </div>
            </footer>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import roleService from '@/services/roleService'
import permissionService from '@/services/permissionService'

export default {
  name: 'RoleCreate',
  data() {
    return {
      role: {
        name: '',
      },
      permissions: [],
      selectedPermissions: [],
      errors: {},
      isCreating: false,
      loading: false,
      error: null,
    }
  },
  async mounted() {
    await this.fetchPermissions()
  },
  methods: {
    async fetchPermissions() {
      this.loading = true
      this.error = null
      try {
        const response = await permissionService.getAll()
        this.permissions = response.data
      } catch (error) {
        console.error('Erreur lors du chargement des permissions:', error)
        this.error = 'Impossible de charger les permissions. Veuillez réessayer.'
      } finally {
        this.loading = false
      }
    },
    async createRole() {
      this.errors = {}
      if (!this.role.name.trim()) {
        this.errors.name = 'Le nom du rôle est requis'
        return
      }
      if (this.role.name.trim().length < 3) {
        this.errors.name = 'Le nom du rôle doit contenir au moins 3 caractères'
        return
      }
      try {
        this.isCreating = true
        const roleResponse = await roleService.create({
          name: this.role.name.trim(),
        })
        if (this.selectedPermissions.length > 0) {
          for (const permissionName of this.selectedPermissions) {
            await roleService.assignPermission(roleResponse.data.id, permissionName)
          }
        }
        this.$router.push({ name: 'dashboard-roles' })
      } catch (error) {
        console.error('Erreur lors de la création du rôle:', error)
        this.error = 'Erreur lors de la création du rôle. Veuillez réessayer.'
      } finally {
        this.isCreating = false
      }
    },
  },
}
</script>

<style scoped>
.role-create {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 0;
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.card {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  background: white;
  border-radius: 15px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.card-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.card-header-title {
  color: white;
  font-size: 1.5rem;
  font-weight: 600;
}

.card-content {
  padding: 2.5rem;
}

.field {
  margin-bottom: 1.5rem;
}

.label {
  color: #2d3748;
  font-weight: 600;
  font-size: 1.1rem;
}

.input {
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
}

.input:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.checkbox {
  color: #4a5568;
  font-size: 1.1rem;
  transition: color 0.3s ease;
}

.checkbox:hover {
  color: #667eea;
}

.notification {
  border-radius: 8px;
  border: none;
}

.button {
  border-radius: 8px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.button.is-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
}

.button.is-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.button.is-light {
  border: 1px solid #e2e8f0;
}

.button.is-light:hover {
  background: #f7fafc;
  transform: translateY(-1px);
}

.columns.is-multiline {
  margin: -0.5rem;
}

.column {
  padding: 0.5rem;
}

@media screen and (max-width: 768px) {
  .container.is-fluid {
    padding: 0 1rem;
  }

  .card-content {
    padding: 1.5rem;
  }

  .role-create {
    padding: 1rem 0;
  }
}
</style>
