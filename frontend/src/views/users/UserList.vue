<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="users" class="text-indigo-500" />
          Gestion des utilisateurs
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Gérez les comptes et les rôles associés à votre point de vente.
        </p>
      </div>
      <router-link
        :to="{ name: 'dashboard-users-create' }"
        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
      >
        <font-awesome-icon icon="plus" />
        Nouvel utilisateur
      </router-link>
    </header>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-800">Liste des utilisateurs</h2>
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
            {{ users.length }} utilisateur{{ users.length > 1 ? 's' : '' }}
          </span>
        </div>
      </div>

      <div v-if="loading" class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center text-sm text-slate-500">
        <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
        <div>
          <p class="font-semibold text-slate-700">Chargement des utilisateurs…</p>
          <p class="text-xs text-slate-400">Veuillez patienter pendant le chargement des données.</p>
        </div>
      </div>

      <div
        v-else-if="users.length === 0"
        class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center"
      >
        <div class="flex size-20 items-center justify-center rounded-full bg-slate-50 text-3xl text-slate-400">
          <font-awesome-icon icon="users" />
        </div>
        <div class="space-y-2">
          <h3 class="text-lg font-semibold text-slate-800">Aucun utilisateur</h3>
          <p class="text-sm text-slate-500">
            Créez votre premier utilisateur pour gérer l’accès et les droits.
          </p>
        </div>
        <router-link
          :to="{ name: 'dashboard-users-create' }"
          class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
        >
          <font-awesome-icon icon="plus" />
          Créer un utilisateur
        </router-link>
      </div>

      <div v-else class="overflow-hidden">
        <div
          class="hidden items-center border-b border-slate-100 bg-slate-50 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 md:grid md:grid-cols-[1.1fr,1.4fr,1.6fr,1.2fr,auto]"
        >
          <span>Point de vente</span>
          <span>Utilisateur</span>
          <span>Email</span>
          <span>Rôles</span>
          <span class="text-right">Actions</span>
        </div>

        <ul class="divide-y divide-slate-100">
          <li
            v-for="user in users"
            :key="user.id"
            class="grid gap-4 px-4 py-4 md:grid-cols-[1.1fr,1.4fr,1.6fr,1.2fr,auto] md:items-center md:px-6"
          >
            <div class="flex items-center gap-3">
              <div class="flex size-10 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-600">
                {{ (user.point_of_sale_name || '?').charAt(0).toUpperCase() }}
              </div>
              <div>
                <p class="font-semibold text-slate-800">
                  {{ user.point_of_sale_name || 'Non défini' }}
                </p>
                <p class="text-xs text-slate-400">
                  ID POS : {{ user.point_of_sale_id || '—' }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <div
                class="flex size-10 items-center justify-center rounded-full text-sm font-semibold text-white"
                :style="{ backgroundColor: getAvatarColor(user.name) }"
                aria-hidden="true"
              >
                {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
              </div>
              <div>
                <p class="font-semibold text-slate-800">{{ user.name || 'Nom non défini' }}</p>
                <p class="text-xs text-slate-400">ID: {{ user.id }}</p>
              </div>
            </div>

            <div class="flex items-center gap-2 text-sm text-slate-600">
              <font-awesome-icon icon="envelope" class="hidden text-slate-400 md:inline" />
              <span class="break-all">{{ user.email }}</span>
            </div>

            <div class="flex flex-wrap gap-2">
              <span
                v-for="role in user.roles"
                :key="role"
                class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600"
              >
                {{ role }}
              </span>
              <span
                v-if="!user.roles || user.roles.length === 0"
                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500"
              >
                Aucun rôle
              </span>
            </div>

            <div class="flex items-center justify-between gap-3 md:justify-end">
              <div class="text-xs text-slate-400 md:hidden">
                {{ formatDate(user.created_at) }}
              </div>
              <div class="hidden text-sm text-slate-500 md:block">
                {{ formatDate(user.created_at) }}
              </div>
              <div class="flex gap-2">
                <router-link
                  :to="{ name: 'dashboard-users-edit', params: { id: user.id } }"
                  class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                  title="Modifier l'utilisateur"
                >
                  <font-awesome-icon icon="pencil" />
                </router-link>
                <button
                  type="button"
                  class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-rose-500 transition hover:border-rose-200 hover:text-rose-600"
                  @click="deleteUser(user.id)"
                  title="Supprimer l'utilisateur"
                >
                  <font-awesome-icon icon="trash" />
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script>
import userService from '@/services/userService'

export default {
  name: 'UserList',
  data() {
    return {
      users: [],
      loading: true
    }
  },
  async mounted() {
    await this.loadUsers()
  },
  methods: {
    async loadUsers() {
      try {
        this.loading = true
        const response = await userService.getAll()
        const users = Array.isArray(response.data) ? response.data : []

        const usersWithRoles = await Promise.all(
          users.map(async (user) => {
            try {
              const rolesResponse = await userService.getRoles(user.id)
              const roles = Array.isArray(rolesResponse.data) ? rolesResponse.data : []
              return {
                ...user,
                roles: roles.map(role => role.name || role)
              }
            } catch (error) {
              console.error(`Erreur lors du chargement des rôles pour l'utilisateur ${user.id}:`, error)
              return {
                ...user,
                roles: []
              }
            }
          })
        )

        this.users = usersWithRoles
      } catch (error) {
        console.error('Erreur lors du chargement des utilisateurs:', error)
        this.users = []
      } finally {
        this.loading = false
      }
    },
    async deleteUser(userId) {
      if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
        try {
          await userService.delete(userId)
          await this.loadUsers()
        } catch (error) {
          console.error('Erreur lors de la suppression:', error)
          alert("Erreur lors de la suppression de l'utilisateur")
        }
      }
    },
    formatDate(date) {
      if (!date) return 'Non défini'
      return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      })
    },
    getAvatarColor(name) {
      if (!name) return '#6b7280'

      const colors = [
        '#3b82f6', '#ef4444', '#10b981', '#f59e0b',
        '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'
      ]

      const index = name.charCodeAt(0) % colors.length
      return colors[index]
    }
  }
}
</script>

<style scoped>
</style>
