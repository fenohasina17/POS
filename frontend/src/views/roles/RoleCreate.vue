<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-user-tag" class="text-indigo-500" />
          Créer un Rôle
        </h1>
        <p class="mt-2 text-sm text-slate-500">Définissez un nouveau rôle et assignez des permissions.</p>
      </div>
      <router-link
        :to="{ name: 'dashboard-roles' }"
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

    <form @submit.prevent="createRole" class="space-y-6">
      <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-800">Informations du rôle</h2>
        </div>
        <div class="px-6 py-6">
          <div>
            <label class="text-sm font-medium text-slate-600" for="role-name">
              Nom du rôle <span class="text-rose-500">*</span>
            </label>
            <input
              id="role-name"
              v-model="name"
              type="text"
              maxlength="50"
              required
              placeholder="Ex: Administrateur, Gérant, Modérateur"
              class="mt-1.5 w-full rounded-2xl border px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:outline-none focus:ring-2"
              :class="nameError
                ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100'
                : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-100'"
            />
            <p v-if="nameError" class="mt-1.5 text-xs text-rose-600">{{ nameError }}</p>
            <p class="mt-1.5 text-xs text-slate-400">Entre 3 et 50 caractères, unique dans le système.</p>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-800">Permissions</h2>
        </div>
        <div class="px-6 py-6">
          <div v-if="loadingPerms" class="flex items-center justify-center py-10 text-sm text-slate-500">
            <font-awesome-icon icon="fa-solid fa-spinner" class="mr-2 animate-spin text-indigo-500" />
            Chargement des permissions...
          </div>
          <div v-else-if="permError" class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
            <font-awesome-icon icon="fa-solid fa-circle-exclamation" />
            {{ permError }}
          </div>
          <p v-else-if="permissions.length === 0" class="py-8 text-center text-sm text-slate-400">
            Aucune permission disponible.
          </p>
          <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            <label
              v-for="permission in permissions"
              :key="permission.id"
              class="flex cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 transition hover:border-indigo-200 hover:bg-indigo-50/50"
              :class="selectedPermissions.includes(permission.name)
                ? 'border-indigo-200 bg-indigo-50'
                : 'border-slate-100'"
            >
              <input
                type="checkbox"
                :value="permission.name"
                v-model="selectedPermissions"
                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <span class="text-sm text-slate-700">{{ permission.name }}</span>
            </label>
          </div>
        </div>
      </section>

      <div class="flex items-center justify-end gap-3">
        <router-link
          :to="{ name: 'dashboard-roles' }"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
        >
          Annuler
        </router-link>
        <button
          type="submit"
          :disabled="isCreating || !name.trim() || loadingPerms"
          class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
        >
          <font-awesome-icon v-if="isCreating" icon="fa-solid fa-spinner" class="animate-spin" />
          <font-awesome-icon v-else icon="fa-solid fa-plus" />
          {{ isCreating ? 'Création...' : 'Créer le rôle' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import roleService from '@/services/roleService'
import permissionService from '@/services/permissionService'

const router = useRouter()

const name = ref('')
const nameError = ref('')
const permissions = ref([])
const selectedPermissions = ref([])
const isCreating = ref(false)
const loadingPerms = ref(false)
const error = ref(null)
const permError = ref(null)

const fetchPermissions = async () => {
  loadingPerms.value = true
  permError.value = null
  try {
    const response = await permissionService.getAll()
    permissions.value = response.data
  } catch {
    permError.value = 'Impossible de charger les permissions. Veuillez réessayer.'
  } finally {
    loadingPerms.value = false
  }
}

const createRole = async () => {
  nameError.value = ''
  error.value = null
  if (!name.value.trim()) {
    nameError.value = 'Le nom du rôle est requis'
    return
  }
  if (name.value.trim().length < 3) {
    nameError.value = 'Le nom du rôle doit contenir au moins 3 caractères'
    return
  }
  try {
    isCreating.value = true
    const roleResponse = await roleService.create({ name: name.value.trim() })
    for (const permissionName of selectedPermissions.value) {
      await roleService.assignPermission(roleResponse.data.id, permissionName)
    }
    router.push({ name: 'dashboard-roles' })
  } catch {
    error.value = 'Erreur lors de la création du rôle. Veuillez réessayer.'
  } finally {
    isCreating.value = false
  }
}

onMounted(fetchPermissions)
</script>
