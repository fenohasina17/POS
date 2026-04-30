<template>
  <div class="space-y-6">
    <header
      class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8"
    >
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">
          Administration
        </p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-user-gear" class="text-indigo-500" />
          Modifier le rôle
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Ajustez le nom du rôle et gérez les permissions qui définissent les accès de votre équipe.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <router-link
          :to="{ name: 'dashboard-roles' }"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
        >
          <font-awesome-icon icon="fa-solid fa-arrow-left" />
          Retour
        </router-link>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
          @click="deleteRole"
          :disabled="isDeleting || role?.name === 'admin'"
        >
          <font-awesome-icon icon="fa-solid fa-trash" />
          Supprimer
        </button>
      </div>
    </header>

    <div
      v-if="loading"
      class="flex flex-col items-center justify-center gap-3 rounded-3xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-500 shadow-sm"
    >
      <span
        class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"
      ></span>
      <div>
        <p class="font-semibold text-slate-700">Chargement du rôle…</p>
        <p class="text-xs text-slate-400">
          Nous récupérons les informations du rôle et ses permissions.
        </p>
      </div>
    </div>

    <div v-else class="space-y-6">
      <div
        v-if="error"
        class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-600"
      >
        <div class="flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-triangle-exclamation" />
          <span>{{ error }}</span>
        </div>
        <button
          type="button"
          class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-100"
          @click="error = null"
        >
          Fermer
        </button>
      </div>

      <div v-if="role" class="grid gap-6 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
        <section class="space-y-6 rounded-3xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-semibold text-slate-800">Informations du rôle</h2>
          </div>
          <div class="space-y-6 px-6 pb-6">
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-600">Nom du rôle</label>
              <div class="relative">
                <span
                  class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                >
                  <font-awesome-icon icon="fa-solid fa-tag" />
                </span>
                <input
                  v-model="role.name"
                  type="text"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pl-9 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:opacity-50"
                  :disabled="role.name === 'admin'"
                  placeholder="Nom du rôle"
                />
              </div>
              <p
                v-if="role.name === 'admin'"
                class="flex items-center gap-2 text-xs font-semibold text-amber-500"
              >
                <font-awesome-icon icon="fa-solid fa-lock" />
                Le rôle administrateur ne peut pas être renommé.
              </p>
            </div>

            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
              @click="updateRole"
              :disabled="isSaving || role.name === 'admin'"
            >
              <font-awesome-icon
                :icon="isSaving ? 'fa-solid fa-spinner' : 'fa-solid fa-floppy-disk'"
                :class="{ 'animate-spin': isSaving }"
              />
              {{ isSaving ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </div>

          <div class="border-t border-slate-100 px-6 py-4">
            <h3 class="text-base font-semibold text-slate-800">Permissions actuelles</h3>
            <p class="mt-1 text-xs text-slate-400">
              Retirez des permissions pour limiter les actions autorisées.
            </p>
          </div>
          <div class="px-6 pb-6">
            <div
              v-if="role.permissions && role.permissions.length > 0"
              class="flex flex-wrap gap-2"
            >
              <span
                v-for="permission in role.permissions"
                :key="permission.id"
                class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50/80 px-3 py-1 text-xs font-semibold text-indigo-600"
              >
                <font-awesome-icon icon="fa-solid fa-shield-halved" /> {{ permission.name }}
                <button
                  type="button"
                  class="rounded-full border border-indigo-100 px-1 text-[11px] text-indigo-500 transition hover:bg-white"
                  @click="revokePermission(permission.id)"
                  :disabled="isRevoking"
                >
                  <font-awesome-icon icon="fa-solid fa-xmark" />
                </button>
              </span>
            </div>
            <div
              v-else
              class="flex items-center gap-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500"
            >
              <font-awesome-icon icon="fa-solid fa-circle-info" />
              Aucune permission n’est encore associée à ce rôle.
            </div>
          </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-semibold text-slate-800">Ajouter des permissions</h2>
            <p class="mt-1 text-xs text-slate-400">
              Sélectionnez des permissions à ajouter puis validez.
            </p>
          </div>

          <div class="space-y-6 px-6 pb-6">
            <div
              v-if="availablePermissions.length > 0"
              class="max-h-[340px] overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
            >
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                <label
                  v-for="permission in availablePermissions"
                  :key="permission.id"
                  class="flex items-center gap-3 rounded-2xl border border-transparent bg-white px-4 py-3 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600"
                >
                  <input
                    v-model="selectedPermissions"
                    type="checkbox"
                    :value="permission.name"
                    class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-200"
                    :disabled="isAssigning"
                  />
                  <span class="truncate">{{ permission.name }}</span>
                </label>
              </div>
            </div>
            <div
              v-else
              class="flex items-center gap-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500"
            >
              <font-awesome-icon icon="fa-solid fa-circle-check" />
              Toutes les permissions disponibles sont déjà attribuées à ce rôle.
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                @click="assignPermissions"
                :disabled="selectedPermissions.length === 0 || isAssigning"
              >
                <font-awesome-icon
                  :icon="isAssigning ? 'fa-solid fa-spinner' : 'fa-solid fa-plus'"
                  :class="{ 'animate-spin': isAssigning }"
                />
                Ajouter la sélection
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
                @click="clearSelection"
                :disabled="selectedPermissions.length === 0 || isAssigning"
              >
                <font-awesome-icon icon="fa-solid fa-xmark" />
                Effacer la sélection
              </button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import roleService from '@/services/roleService'
import permissionService from '@/services/permissionService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

defineOptions({ name: 'RoleEdit', components: { FontAwesomeIcon } })

const router = useRouter()
const route = useRoute()

const role = ref(null)
const availablePermissions = ref([])
const selectedPermissions = ref([])
const loading = ref(true)
const error = ref(null)
const isSaving = ref(false)
const isAssigning = ref(false)
const isRevoking = ref(false)
const isDeleting = ref(false)

const fetchRole = async () => {
  try {
    loading.value = true
    const response = await roleService.getById(route.params.id)
    role.value = response.data
  } catch (err) {
    console.error('Erreur lors de la récupération du rôle:', err)
    error.value = 'Impossible de charger le rôle. Veuillez réessayer.'
  } finally {
    loading.value = false
  }
}

const fetchAvailablePermissions = async () => {
  try {
    const response = await permissionService.getAll()
    if (role.value?.permissions) {
      availablePermissions.value = response.data.filter(
        (permission) => !role.value.permissions.some((p) => p.id === permission.id),
      )
    } else {
      availablePermissions.value = response.data
    }
  } catch (err) {
    console.error('Erreur lors de la récupération des permissions:', err)
  }
}

const updateRole = async () => {
  if (role.value?.name === 'admin') {
    error.value = 'Le rôle admin ne peut pas être modifié.'
    return
  }

  try {
    isSaving.value = true
    await roleService.update(role.value.id, { name: role.value.name })
  } catch (err) {
    console.error('Erreur lors de la mise à jour du rôle:', err)
    error.value = err.response?.data?.error || 'Erreur lors de la mise à jour du rôle.'
  } finally {
    isSaving.value = false
  }
}

const assignPermissions = async () => {
  if (selectedPermissions.value.length === 0) return

  try {
    isAssigning.value = true
    for (const permissionName of selectedPermissions.value) {
      await roleService.assignPermission(role.value.id, permissionName)
    }

    await fetchRole()
    await fetchAvailablePermissions()
    selectedPermissions.value = []
  } catch (err) {
    console.error("Erreur lors de l'ajout des permissions:", err)
    error.value = err.response?.data?.error || "Erreur lors de l'ajout des permissions."
  } finally {
    isAssigning.value = false
  }
}

const clearSelection = () => {
  selectedPermissions.value = []
}

const revokePermission = async (permissionId) => {
  try {
    isRevoking.value = true
    await roleService.revokePermission(role.value.id, permissionId)
    await fetchRole()
    await fetchAvailablePermissions()
  } catch (err) {
    console.error('Erreur lors du retrait de la permission:', err)
    error.value = err.response?.data?.error || 'Erreur lors du retrait de la permission.'
  } finally {
    isRevoking.value = false
  }
}

const deleteRole = async () => {
  if (role.value?.name === 'admin') {
    error.value = 'Le rôle admin ne peut pas être supprimé.'
    return
  }

  if (!confirm(`Êtes-vous sûr de vouloir supprimer le rôle "${role.value?.name}" ?`)) {
    return
  }

  try {
    isDeleting.value = true
    await roleService.delete(role.value.id)
    router.push({ name: 'dashboard-roles' })
  } catch (err) {
    console.error('Erreur lors de la suppression du rôle:', err)
    if (err.response?.status === 403) {
      error.value = 'Ce rôle est encore attribué à des utilisateurs et ne peut être supprimé.'
    } else {
      error.value = err.response?.data?.error || 'Erreur lors de la suppression.'
    }
  } finally {
    isDeleting.value = false
  }
}

onMounted(async () => {
  await fetchRole()
  await fetchAvailablePermissions()
})
</script>

<style scoped></style>
