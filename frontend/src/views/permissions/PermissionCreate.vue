<template>
  <div class="permission-create">
    <div class="header">
      <h2>Créer une nouvelle permission</h2>
      <router-link :to="{ name: 'dashboard-permissions' }" class="btn btn-back">
        <FontAwesomeIcon :icon="faArrowLeft" />
        <span>Retour à la liste</span>
      </router-link>
    </div>

    <div v-if="error" class="alert alert-danger">
      <FontAwesomeIcon :icon="faExclamationTriangle" />
      {{ error }}
    </div>

    <form @submit.prevent="createPermission" class="permission-form">
      <div class="form-group">
        <label for="name">Nom de la permission *</label>
        <input type="text" id="name" v-model="permission.name" required class="form-control"
          placeholder="Ex: edit_posts, delete_users" :class="{ 'is-invalid': nameError }" @input="validateName">
        <div v-if="nameError" class="invalid-feedback">
          <FontAwesomeIcon :icon="faExclamationCircle" />
          {{ nameError }}
        </div>
        <small class="form-text text-muted">
          Format recommandé: verbe.tables
        </small>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
          <span v-if="isSubmitting">
            <FontAwesomeIcon :icon="faSpinner" spin />
            Création en cours...
          </span>
          <span v-else>
            <FontAwesomeIcon :icon="faPlus" />
            Créer la permission
          </span>
        </button>

        <button type="button" class="btn btn-outline-secondary" @click="resetForm" :disabled="isSubmitting">
          <FontAwesomeIcon :icon="faUndo" />
          Réinitialiser
        </button>
      </div>
    </form>

    <div v-if="successMessage" class="alert alert-success mt-4">
      <FontAwesomeIcon :icon="faCheckCircle" />
      {{ successMessage }}
      <div class="mt-3">
        <router-link :to="{ name: 'dashboard-permissions' }" class="btn btn-sm btn-outline-success">
          <FontAwesomeIcon :icon="faList" />
          Voir toutes les permissions
        </router-link>
        <button class="btn btn-sm btn-outline-primary ml-2" @click="createAnother">
          <FontAwesomeIcon :icon="faPlus" />
          Créer une autre permission
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import permissionService from '@/services/permissionService';
import { validatePermissionName } from '@/utils/validators';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faArrowLeft,
  faExclamationTriangle,
  faExclamationCircle,
  faSpinner,
  faPlus,
  faUndo,
  faCheckCircle,
  faList
} from '@fortawesome/free-solid-svg-icons';

export default {
  components: {
    FontAwesomeIcon
  },
  data() {
    return {
      permission: {
        name: '',
        description: ''
      },
      isSubmitting: false,
      error: null,
      nameError: null,
      successMessage: null,
      faArrowLeft,
      faExclamationTriangle,
      faExclamationCircle,
      faSpinner,
      faPlus,
      faUndo,
      faCheckCircle,
      faList
    };
  },
  methods: {
    validateName() {
      this.nameError = validatePermissionName(this.permission.name);
      return !this.nameError;
    },

    async createPermission() {
      if (!this.validateName()) return;

      try {
        this.isSubmitting = true;
        this.error = null;

        const response = await permissionService.create({
          name: this.permission.name,
        });

        this.successMessage = `Permission "${response.data.name}" créée avec succès !`;
        this.permission = { name: '', description: '' };

      } catch (error) {
        console.error('Erreur création permission:', error);

        if (error.response?.status === 422) {
          const errors = error.response.data.errors;
          if (errors.name) {
            this.nameError = errors.name[0];
          } else {
            this.error = "Veuillez corriger les erreurs dans le formulaire";
          }
        } else {
          this.error = error.response?.data?.message ||
            "Une erreur est survenue lors de la création de la permission. Veuillez réessayer.";
        }
      } finally {
        this.isSubmitting = false;
      }
    },

    resetForm() {
      this.permission = { name: '', description: '' };
      this.error = null;
      this.nameError = null;
      this.successMessage = null;
    },

    createAnother() {
      this.successMessage = null;
      this.resetForm();
      this.$nextTick(() => {
        document.getElementById('name').focus();
      });
    }
  }
};
</script>

<style scoped>
.permission-create {
  max-width: 600px;
  margin: 0 auto;
  padding: 2rem;
  background-color: #f8f9fa;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #e9ecef;
}

h2 {
  color: #2c3e50;
  margin: 0;
  font-size: 1.75rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.3s ease;
}

.btn-back {
  background-color: #e3f2fd;
  color: #1976d2;
  border: 1px solid #90caf9;
}

.btn-back:hover {
  background-color: #bbdefb;
  border-color: #64b5f6;
}

.permission-form {
  background-color: #ffffff;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.form-group {
  margin-bottom: 1.5rem;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #2c3e50;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
  transition: border-color 0.3s ease;
}

.form-control:focus {
  outline: none;
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2);
}

.form-control.is-invalid {
  border-color: #dc3545;
}

.invalid-feedback {
  color: #dc3545;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.form-text {
  color: #6c757d;
  font-size: 0.875rem;
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
}

.btn-primary {
  background-color: #1976d2;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #1565c0;
}

.btn-primary:disabled {
  background-color: #90caf9;
  cursor: not-allowed;
}

.btn-outline-secondary {
  background-color: transparent;
  color: #6c757d;
  border: 1px solid #6c757d;
}

.btn-outline-secondary:hover:not(:disabled) {
  background-color: #6c757d;
  color: white;
}

.alert {
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1rem;
}

.alert-danger {
  background-color: #ffebee;
  color: #c62828;
  border: 1px solid #ef9a9a;
}

.alert-success {
  background-color: #e8f5e8;
  color: #2e7d32;
  border: 1px solid #a5d6a7;
}

.ml-2 {
  margin-left: 0.5rem;
}

.mt-3 {
  margin-top: 1rem;
}

.mt-4 {
  margin-top: 1.5rem;
}

/* Responsive styles */
@media screen and (max-width: 768px) {
  .permission-create {
    margin: 1rem;
    padding: 1.5rem;
  }

  .header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  h2 {
    font-size: 1.5rem;
  }

  .form-actions {
    flex-direction: column;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }
}

@media screen and (max-width: 480px) {
  .permission-create {
    margin: 0.5rem;
    padding: 1rem;
  }

  .permission-form {
    padding: 1.5rem;
  }

  h2 {
    font-size: 1.25rem;
  }
}
</style>
