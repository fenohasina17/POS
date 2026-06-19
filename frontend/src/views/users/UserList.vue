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

      <div v-else class="p-6 space-y-8">
        <div v-for="group in usersByRole" :key="group.role" class="rounded-2xl border border-slate-100 bg-white">
          <div 
            @click="toggleRole(group.role)"
            class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex items-center justify-between cursor-pointer hover:bg-slate-100 transition-colors rounded-t-2xl"
          >
            <h3 class="text-xs font-black text-slate-700 uppercase tracking-widest">{{ group.role }} ({{ group.users.length }})</h3>
            <font-awesome-icon 
              icon="fa-solid fa-chevron-down" 
              class="text-slate-400 transition-transform duration-300"
              :class="{ 'rotate-180': expandedRoles.has(group.role) }"
            />
          </div>
          
          <div v-if="!expandedRoles.has(group.role)" class="space-y-3 p-4">
            <div
              v-for="user in group.users"
              :key="user.id"
              class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm hover:border-indigo-100 hover:shadow-md transition-all flex items-center justify-between gap-4"
            >
              <div class="flex items-center gap-4">
                <div
                  class="flex size-10 items-center justify-center rounded-2xl text-sm font-black text-white shadow-inner"
                  :style="{ backgroundColor: user.avatarColor }"
                >
                  {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
                </div>
                <div>
                  <p class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ user.name }}</p>
                  <p class="text-[10px] font-bold text-slate-400">{{ user.email }}</p>
                </div>
              </div>

              <div class="flex items-center gap-6">
                <div class="flex flex-wrap gap-1.5 justify-end">
                  <span 
                    v-for="pos in user.points_of_sale" 
                    :key="pos.id"
                    class="rounded-lg bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-slate-500"
                  >
                    {{ pos.name }}
                  </span>
                </div>

                <div class="flex items-center gap-2">
                  <router-link
                    :to="{ name: 'dashboard-users-edit', params: { id: user.id } }"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-600 hover:text-white transition-all"
                    title="Modifier"
                  >
                    <font-awesome-icon icon="fa-solid fa-pencil" class="text-xs" />
                  </router-link>
                  <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-rose-600 hover:text-white transition-all"
                    @click="deleteUser(user.id)"
                    title="Supprimer"
                  >
                    <font-awesome-icon icon="fa-solid fa-trash" class="text-xs" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import userService from '@/services/userService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faUsers, faPlus, faEnvelope, faPencil, faTrash, faChevronDown } from '@fortawesome/free-solid-svg-icons'

library.add(faUsers, faPlus, faEnvelope, faPencil, faTrash, faChevronDown)

defineOptions({ name: 'UserList' })

const users = ref([])
const loading = ref(true)
const expandedRoles = ref(new Set())

const usersByRole = computed(() => {
  const groups = {};
  users.value.forEach(user => {
    const role = user.roles && user.roles.length > 0 ? user.roles[0] : 'Sans rôle';
    if (!groups[role]) {
      groups[role] = [];
    }
    groups[role].push(user);
  });
  return Object.keys(groups).sort().map(role => ({ role, users: groups[role] }));
});

const toggleRole = (role) => {
  if (expandedRoles.value.has(role)) expandedRoles.value.delete(role);
  else expandedRoles.value.add(role);
};

const loadUsers = async () => {
  try {
    loading.value = true
    const response = await userService.getAll()
    const rawUsers = response.data?.data || response.data || []
    users.value = rawUsers.map(user => ({
      ...user,
      avatarColor: getAvatarColor(user.name)
    }))
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
