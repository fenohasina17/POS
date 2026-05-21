<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-users" class="text-indigo-500" />
          Gestion des utilisateurs
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Gérez les comptes et les rôles associés à vos points de vente.
        </p>
      </div>
      <router-link
        :to="{ name: 'dashboard-users-create' }"
        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
      >
        <font-awesome-icon icon="fa-solid fa-plus" />
        Nouvel utilisateur
      </router-link>
    </header>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
          <font-awesome-icon icon="fa-solid fa-users" />
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
          <font-awesome-icon icon="fa-solid fa-plus" />
          Créer un utilisateur
        </router-link>
      </div>

      <div v-else class="overflow-x-auto">
        <div
          class="hidden min-w-[800px] items-center border-b border-slate-100 bg-slate-50 px-6 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 md:grid md:grid-cols-[1.5fr,1.5fr,1.5fr,1fr,auto]"
        >
          <span>Sites autorisés</span>
          <span>Utilisateur</span>
          <span>Email</span>
          <span>Rôles</span>
          <span class="text-right">Actions</span>
        </div>

        <ul class="divide-y divide-slate-100 min-w-[800px]">
          <li
            v-for="user in users"
            :key="user.id"
            class="grid gap-4 px-4 py-4 md:grid-cols-[1.5fr,1.5fr,1.5fr,1fr,auto] md:items-center md:px-6 hover:bg-slate-50/50 transition-colors"
          >
            <div class="flex flex-col gap-1.5">
              <div class="flex flex-wrap gap-1.5">
                <span 
                  v-for="pos in user.points_of_sale" 
                  :key="pos.id"
                  class="rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-slate-500 border border-slate-200"
                >
                  {{ pos.name }}
                </span>
                <span 
                  v-if="!user.points_of_sale || user.points_of_sale.length === 0"
                  class="text-[10px] text-slate-400 italic"
                >
                  Aucun site
                </span>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <div
                class="flex size-9 items-center justify-center rounded-xl text-xs font-black text-white shadow-sm"
                :style="{ backgroundColor: getAvatarColor(user.name) }"
              >
                {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
              </div>
              <div>
                <p class="text-sm font-black text-slate-700 uppercase tracking-tight">{{ user.name || 'Nom non défini' }}</p>
                <p class="text-[10px] font-bold text-slate-400">ID: #{{ user.id }}</p>
              </div>
            </div>

            <div class="flex items-center gap-2 text-sm text-slate-600">
              <font-awesome-icon icon="fa-solid fa-envelope" class="text-slate-300" />
              <span class="font-medium text-slate-500">{{ user.email }}</span>
            </div>

            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="role in user.roles"
                :key="role"
                class="rounded-lg bg-indigo-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-tighter text-indigo-600 border border-indigo-100"
              >
                {{ role }}
              </span>
              <span
                v-if="!user.roles || user.roles.length === 0"
                class="rounded-lg bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-400 border border-slate-100"
              >
                AUCUN RÔLE
              </span>
            </div>

            <div class="flex items-center justify-end gap-2">
              <router-link
                :to="{ name: 'dashboard-users-edit', params: { id: user.id } }"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 transition hover:border-indigo-200 hover:text-indigo-600 hover:shadow-sm active:scale-95"
                title="Modifier"
              >
                <font-awesome-icon icon="fa-solid fa-pencil" class="text-xs" />
              </router-link>
              <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:text-rose-600 hover:shadow-sm active:scale-95"
                @click="deleteUser(user.id)"
                title="Supprimer"
              >
                <font-awesome-icon icon="fa-solid fa-trash" class="text-xs" />
              </button>
            </div>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import userService from '@/services/userService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faUsers, faPlus, faEnvelope, faPencil, faTrash } from '@fortawesome/free-solid-svg-icons'

library.add(faUsers, faPlus, faEnvelope, faPencil, faTrash)

defineOptions({ name: 'UserList' })

const users = ref([])
const loading = ref(true)

const loadUsers = async () => {
  try {
    loading.value = true
    const response = await userService.getAll()
    const rawUsers = response.data?.data || response.data || []
    
    // Charger les rôles pour chaque utilisateur
    users.value = await Promise.all(
      rawUsers.map(async (user) => {
        try {
          const rolesRes = await userService.getRoles(user.id)
          const rolesData = rolesRes.data?.data || rolesRes.data || []
          return {
            ...user,
            roles: rolesData.map(r => r.name || r)
          }
        } catch (err) {
          console.error(`Erreur rôles user ${user.id}:`, err)
          return { ...user, roles: [] }
        }
      })
    )
  } catch (error) {
    console.error('Erreur lors du chargement des utilisateurs:', error)
  } finally {
    loading.value = false
  }
}

const deleteUser = async (userId) => {
  if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
    try {
      await userService.delete(userId)
      await loadUsers()
    } catch (error) {
      console.error('Erreur lors de la suppression:', error)
    }
  }
}

const getAvatarColor = (name) => {
  if (!name) return '#64748b'
  const colors = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
  const index = name.charCodeAt(0) % colors.length
  return colors[index]
}

onMounted(loadUsers)
</script>
