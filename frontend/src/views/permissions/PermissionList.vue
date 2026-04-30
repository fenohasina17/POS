<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-key" class="text-indigo-500" />
          Gestion des permissions
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Définissez des permissions fines pour affiner les accès des rôles.
        </p>
      </div>
      <router-link
        :to="{ name: 'dashboard-permissions-create' }"
        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
      >
        <font-awesome-icon icon="fa-solid fa-plus" />
        Nouvelle permission
      </router-link>
    </header>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-800">Permissions disponibles</h2>
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
            {{ permissions.length }} permission{{ permissions.length > 1 ? 's' : '' }}
          </span>
        </div>
      </div>

      <div v-if="loading" class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center text-sm text-slate-500">
        <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
        <div>
          <p class="font-semibold text-slate-700">Chargement des permissions…</p>
          <p class="text-xs text-slate-400">Veuillez patienter pendant le chargement des données.</p>
        </div>
      </div>

      <div
        v-else-if="permissions.length === 0"
        class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center"
      >
        <div class="flex size-20 items-center justify-center rounded-full bg-slate-50 text-3xl text-slate-400">
          <font-awesome-icon icon="fa-solid fa-list-ul" />
        </div>
        <div class="space-y-2">
          <h3 class="text-lg font-semibold text-slate-800">Aucune permission</h3>
          <p class="text-sm text-slate-500">
            Créez votre première permission pour contrôler l’accès aux fonctionnalités.
          </p>
        </div>
        <router-link
          :to="{ name: 'dashboard-permissions-create' }"
          class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        >
          <font-awesome-icon icon="fa-solid fa-plus" />
          Créer une permission
        </router-link>
      </div>

      <div v-else class="grid gap-4 px-4 py-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <article
          v-for="permission in permissions"
          :key="permission.id"
          class="flex flex-col justify-between rounded-3xl border border-slate-200 bg-white/80 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
        >
          <div>
            <h3 class="text-base font-semibold text-slate-800">{{ permission.name }}</h3>
            <p class="mt-1 text-xs text-slate-400">ID : {{ permission.id }}</p>
          </div>

          <div class="mt-5 flex justify-end">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100"
              @click="deletePermission(permission.id)"
            >
              <font-awesome-icon icon="fa-solid fa-trash" />
              Supprimer
            </button>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import permissionService from '@/services/permissionService'

defineOptions({ name: 'PermissionListView', components: { FontAwesomeIcon } })

const permissions = ref([])
const loading = ref(false)

const fetchPermissions = async () => {
  loading.value = true
  try {
    const response = await permissionService.getAll()
    permissions.value = Array.isArray(response.data) ? response.data : []
  } catch (error) {
    console.error('Impossible de récupérer les permissions :', error)
    permissions.value = []
  } finally {
    loading.value = false
  }
}

const deletePermission = async (id) => {
  if (!confirm('Êtes-vous sûr de vouloir supprimer cette permission ?')) return
  try {
    await permissionService.delete(id)
    await fetchPermissions()
  } catch (error) {
    console.error('Impossible de supprimer la permission :', error)
    alert('Impossible de supprimer la permission.')
  }
}

onMounted(fetchPermissions)
</script>

<style scoped>
</style>
