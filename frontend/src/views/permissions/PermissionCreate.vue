<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-key" class="text-indigo-500" />
          Créer une Permission
        </h1>
        <p class="mt-2 text-sm text-slate-500">Définissez un nouveau droit d'accès dans le système.</p>
      </div>
      <router-link
        :to="{ name: 'dashboard-permissions' }"
        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
      >
        <font-awesome-icon icon="fa-solid fa-arrow-left" />
        Retour
      </router-link>
    </header>

    <div v-if="error" class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
      <font-awesome-icon icon="fa-solid fa-circle-exclamation" />
      {{ error }}
    </div>

    <div v-if="successMessage" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-6 py-5">
      <div class="flex items-center gap-3 text-sm font-semibold text-emerald-700">
        <font-awesome-icon icon="fa-solid fa-circle-check" />
        {{ successMessage }}
      </div>
      <div class="mt-4 flex flex-wrap items-center gap-3">
        <router-link
          :to="{ name: 'dashboard-permissions' }"
          class="inline-flex items-center gap-2 rounded-2xl border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
        >
          <font-awesome-icon icon="fa-solid fa-list" />
          Voir toutes les permissions
        </router-link>
        <button
          @click="createAnother"
          class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
        >
          <font-awesome-icon icon="fa-solid fa-plus" />
          Créer une autre
        </button>
      </div>
    </div>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-base font-semibold text-slate-800">Informations de la permission</h2>
      </div>
      <div class="px-6 py-6">
        <form @submit.prevent="createPermission">
          <div class="mb-6">
            <label class="text-sm font-medium text-slate-600" for="perm-name">
              Nom de la permission <span class="text-rose-500">*</span>
            </label>
            <input
              id="perm-name"
              v-model="permissionName"
              type="text"
              required
              placeholder="Ex: edit.posts, delete.users"
              @input="validateName"
              class="mt-1.5 w-full rounded-2xl border px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:outline-none focus:ring-2"
              :class="nameError
                ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100'
                : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-100'"
            />
            <p v-if="nameError" class="mt-1.5 flex items-center gap-1 text-xs text-rose-600">
              <font-awesome-icon icon="fa-solid fa-circle-exclamation" />
              {{ nameError }}
            </p>
            <p class="mt-1.5 text-xs text-slate-400">Format recommandé : verbe.ressource (ex: view.sales)</p>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <button
              type="submit"
              :disabled="isSubmitting"
              class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
            >
              <font-awesome-icon v-if="isSubmitting" icon="fa-solid fa-spinner" class="animate-spin" />
              <font-awesome-icon v-else icon="fa-solid fa-plus" />
              {{ isSubmitting ? 'Création...' : 'Créer la permission' }}
            </button>
            <button
              type="button"
              @click="resetForm"
              :disabled="isSubmitting"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600 disabled:opacity-50"
            >
              <font-awesome-icon icon="fa-solid fa-rotate-left" />
              Réinitialiser
            </button>
          </div>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import permissionService from '@/services/permissionService'
import { validatePermissionName } from '@/utils/validators'

const permissionName = ref('')
const isSubmitting = ref(false)
const error = ref(null)
const nameError = ref(null)
const successMessage = ref(null)

const validateName = () => {
  nameError.value = validatePermissionName(permissionName.value)
  return !nameError.value
}

const createPermission = async () => {
  if (!validateName()) return
  try {
    isSubmitting.value = true
    error.value = null
    const response = await permissionService.create({ name: permissionName.value })
    successMessage.value = `Permission "${response.data.name}" créée avec succès !`
    permissionName.value = ''
  } catch (e) {
    if (e.response?.status === 422) {
      const errors = e.response.data.errors
      if (errors?.name) {
        nameError.value = errors.name[0]
      } else {
        error.value = 'Veuillez corriger les erreurs dans le formulaire'
      }
    } else {
      error.value = e.response?.data?.message || 'Une erreur est survenue lors de la création.'
    }
  } finally {
    isSubmitting.value = false
  }
}

const resetForm = () => {
  permissionName.value = ''
  error.value = null
  nameError.value = null
  successMessage.value = null
}

const createAnother = () => {
  resetForm()
  nextTick(() => {
    document.getElementById('perm-name')?.focus()
  })
}
</script>
