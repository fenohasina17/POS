<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-user-gear" class="text-indigo-500" />
          Gestion des rôles
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Attribuez des rôles pour contrôler les accès et responsabilités.
        </p>
      </div>
      <router-link
        :to="{ name: 'dashboard-roles-create' }"
        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
      >
        <font-awesome-icon icon="fa-solid fa-plus" />
        Nouveau rôle
      </router-link>
    </header>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-800">Liste des rôles</h2>
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
            {{ roles.length }} rôle{{ roles.length > 1 ? 's' : '' }}
          </span>
        </div>
      </div>

      <div v-if="loading" class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center text-sm text-slate-500">
        <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
        <div>
          <p class="font-semibold text-slate-700">Chargement des rôles…</p>
          <p class="text-xs text-slate-400">Veuillez patienter pendant le chargement des données.</p>
        </div>
      </div>

      <div
        v-else-if="roles.length === 0"
        class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center"
      >
        <div class="flex size-20 items-center justify-center rounded-full bg-slate-50 text-3xl text-slate-400">
          <font-awesome-icon icon="fa-solid fa-user-gear" />
        </div>
        <div class="space-y-2">
          <h3 class="text-lg font-semibold text-slate-800">Aucun rôle</h3>
          <p class="text-sm text-slate-500">
            Créez un rôle pour structurer les accès de votre équipe.
          </p>
        </div>
        <router-link
          :to="{ name: 'dashboard-roles-create' }"
          class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        >
          <font-awesome-icon icon="fa-solid fa-plus" />
          Créer un rôle
        </router-link>
      </div>

      <div v-else class="overflow-hidden">
        <div
          class="hidden items-center border-b border-slate-100 bg-slate-50 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 md:grid md:grid-cols-[120px_1fr_auto]"
        >
          <span>ID</span>
          <span>Nom du rôle</span>
          <span class="text-right">Actions</span>
        </div>

        <ul class="divide-y divide-slate-100">
          <li
            v-for="role in roles"
            :key="role.id"
            class="grid gap-4 px-4 py-4 md:grid-cols-[120px_1fr_auto] md:items-center md:px-6"
          >
            <div class="text-sm font-semibold text-slate-500 md:text-base">#{{ role.id }}</div>
            <div>
              <p class="font-semibold text-slate-800">{{ role.name }}</p>
              <p class="text-xs text-slate-400">Créé le : {{ formatDate(role.created_at) }}</p>
            </div>
            <div class="flex justify-start gap-2 md:justify-end">
              <router-link
                :to="{ name: 'dashboard-roles-edit', params: { id: role.id } }"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                title="Modifier le rôle"
              >
                <font-awesome-icon icon="fa-solid fa-pencil" />
              </router-link>
              <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-rose-500 transition hover:border-rose-200 hover:text-rose-600"
                @click="deleteRole(role.id)"
                title="Supprimer le rôle"
              >
                <font-awesome-icon icon="fa-solid fa-trash" />
              </button>
            </div>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import roleService from '@/services/roleService'

defineOptions({ name: 'RoleListView', components: { FontAwesomeIcon } })

const roles = ref([])
const loading = ref(false)

const fetchRoles = async () => {
  loading.value = true
  try {
    const response = await roleService.getAll()
    roles.value = Array.isArray(response.data) ? response.data : []
  } catch (error) {
    console.error('Erreur lors du chargement des rôles:', error)
    roles.value = []
  } finally {
    loading.value = false
  }
}

const formatDate = (date) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

const deleteRole = async (id) => {
  if (!confirm('Supprimer ce rôle ?')) return
  try {
    await roleService.delete(id)
    await fetchRoles()
  } catch (error) {
    console.error('Erreur lors de la suppression:', error)
    alert('Impossible de supprimer le rôle.')
  }
}

onMounted(fetchRoles)
</script>

<style scoped>
</style>
