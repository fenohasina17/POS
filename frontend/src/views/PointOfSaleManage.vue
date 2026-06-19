<template>
  <div class="pos-manage-view min-h-screen bg-slate-50/50 p-4 lg:p-8">
    <div class="mx-auto max-w-6xl">
      <!-- Header Premium -->
      <header class="mb-8 flex flex-wrap items-center justify-between gap-6">
        <div>
          <h1 class="text-3xl font-black bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent">
            Points de Vente
          </h1>
          <p class="text-slate-500 font-medium">Gérez vos sites et affectez vos collaborateurs</p>
        </div>
        
        <button 
          @click="openCreate"
          class="flex items-center gap-2 rounded-2xl bg-slate-900 px-6 py-3 text-sm font-black text-white shadow-xl shadow-slate-200 transition-all hover:bg-indigo-600 active:scale-95"
        >
          <FontAwesomeIcon icon="fa-solid fa-plus" />
          NOUVEAU SITE
        </button>
      </header>

      <!-- Liste des Points de Vente -->
      <div v-if="loading" class="flex h-64 items-center justify-center">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-100 border-t-indigo-600"></div>
      </div>

      <div v-else class="space-y-6">
        <div 
          v-for="pos in pointsOfSale" 
          :key="pos.id"
          class="overflow-hidden rounded-[2rem] border border-white bg-white shadow-xl shadow-slate-200/50 transition-all hover:shadow-2xl"
        >
          <!-- En-tête du POS -->
          <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-50 bg-slate-50/30 p-6">
            <div class="flex items-center gap-4">
              <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-indigo-500 shadow-sm">
                <FontAwesomeIcon icon="fa-solid fa-store" class="text-xl" />
              </div>
              <div>
                <h3 class="text-xl font-black text-slate-800">{{ pos.name }}</h3>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">ID: #{{ pos.id }} • Créé le {{ formatDate(pos.created_at) }}</p>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button @click="editPointOfSale(pos)" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-indigo-600 hover:border-indigo-100 transition-all active:scale-95 shadow-sm">
                <FontAwesomeIcon icon="fa-solid fa-pen" class="text-sm" />
              </button>
              <button @click="deletePointOfSale(pos.id)" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-rose-600 hover:border-rose-100 transition-all active:scale-95 shadow-sm">
                <FontAwesomeIcon icon="fa-solid fa-trash" class="text-sm" />
              </button>
            </div>
          </div>

          <!-- Corps : Gestion des Utilisateurs -->
          <div class="p-6">
            <div class="mb-4 flex items-center justify-between">
              <h4 class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-slate-400">
                <FontAwesomeIcon icon="fa-solid fa-user-check" class="text-indigo-400" />
                Équipe Affectée
              </h4>
              <span class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-500">
                {{ pos.users?.length || 0 }} Membres
              </span>
            </div>

            <!-- Liste des membres -->
            <div class="flex flex-wrap gap-3 mb-6">
              <div 
                v-for="user in pos.users" 
                :key="user.id"
                class="group flex items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50/50 py-2 pl-3 pr-2 transition-all hover:bg-white hover:shadow-md"
              >
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm group-hover:bg-indigo-50 group-hover:text-indigo-600">
                  <FontAwesomeIcon icon="fa-solid fa-user" class="text-xs" />
                </div>
                <div>
                  <p class="text-xs font-black text-slate-700">{{ user.name }}</p>
                  <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">{{ user.email }}</p>
                </div>
                <button @click="deleteUserFromPos(user.id, pos.id)" class="ml-2 flex h-6 w-6 items-center justify-center rounded-lg text-slate-300 hover:bg-rose-50 hover:text-rose-500 transition-all">
                  <FontAwesomeIcon icon="fa-solid fa-xmark" class="text-[10px]" />
                </button>
              </div>
              
              <div v-if="!pos.users?.length" class="py-4 text-xs font-bold italic text-slate-300">
                Aucun collaborateur affecté à ce site.
              </div>
            </div>

            <!-- Action : Ajouter un membre -->
            <div class="flex flex-wrap items-center gap-3 rounded-[1.5rem] bg-indigo-50/50 p-4 border border-indigo-100/50">
              <span class="text-[10px] font-black uppercase tracking-widest text-indigo-400">Affecter un nouveau membre :</span>
              <div class="flex-1 min-w-[200px]">
                <select 
                  v-model="selectedUserForPos[pos.id]"
                  class="w-full rounded-xl border-none bg-white py-2 px-4 text-xs font-bold text-slate-700 shadow-sm focus:ring-2 focus:ring-indigo-500/20"
                >
                  <option :value="null">Choisir un utilisateur...</option>
                  <option v-for="user in getAvailableUsersForPos(pos)" :key="user.id" :value="user.id">
                    {{ user.name }} ({{ user.email }})
                  </option>
                </select>
              </div>
              <button 
                @click="attachUserToPos(selectedUserForPos[pos.id], pos.id)"
                :disabled="!selectedUserForPos[pos.id]"
                class="flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-black text-white transition-all hover:bg-indigo-700 disabled:opacity-30 active:scale-95 shadow-lg shadow-indigo-900/10"
              >
                <FontAwesomeIcon icon="fa-solid fa-plus" />
                ATTACHER
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Modal Design Premium -->
    <PointOfSaleModal
      :is-open="showAddForm || showEditForm"
      :title="showEditForm ? 'Édition du Site' : 'Nouveau Site'"
      :submit-label="showEditForm ? 'Mettre à jour' : 'Créer le site'"
      :initial-name="form.name"
      @submit="submitForm"
      @close="closeForm"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PointOfSaleModal from './PointOfSaleModal.vue'
import { API_BASE_URL } from '@/utils/api'
import { faStore, faUserCheck, faUser, faPlus, faPen, faTrash, faXmark } from '@fortawesome/free-solid-svg-icons'

// ========== ÉTATS ==========
const pointsOfSale = ref([])
const loading = ref(true)
const error = ref(null)
const showAddForm = ref(false)
const showEditForm = ref(false)
const form = ref({ id: null, name: '' })
const allUsers = ref([])
const selectedUserForPos = reactive({})

// ========== UTILITAIRES ==========
const formatDate = (dateString) => {
  if (!dateString) return '—'
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric'
  }).format(new Date(dateString))
}

const getAvailableUsersForPos = (pos) => {
  const currentUserIds = pos.users?.map(u => u.id) || []
  return allUsers.value.filter(user => !currentUserIds.includes(user.id))
}

// ========== ACTIONS ==========
const fetchPointsOfSale = async () => {
  loading.value = true
  try {
    const token = localStorage.getItem('token')
    const response = await axios.get(`${API_BASE_URL}/point-of-sales`, {
      headers: { Authorization: `Bearer ${token}` }
    })
    pointsOfSale.value = response.data.data || response.data || []
  } catch (err) {
    console.error('Erreur:', err)
  } finally {
    loading.value = false
  }
}

const fetchAllUsers = async () => {
  try {
    const token = localStorage.getItem('token')
    const response = await axios.get(`${API_BASE_URL}/users`, {
      headers: { Authorization: `Bearer ${token}` }
    })
    allUsers.value = response.data.data || response.data || []
  } catch (err) {
    console.error('Erreur users:', err)
  }
}

const submitForm = async (name) => {
  if (typeof name === 'string') form.value.name = name
  const token = localStorage.getItem('token')
  try {
    if (showEditForm.value) {
      await axios.put(`${API_BASE_URL}/point-of-sales/${form.value.id}`, { name: form.value.name }, { headers: { Authorization: `Bearer ${token}` } })
    } else {
      await axios.post(`${API_BASE_URL}/point-of-sales`, { name: form.value.name }, { headers: { Authorization: `Bearer ${token}` } })
    }
    await fetchPointsOfSale()
    closeForm()
  } catch (err) {
    alert(err.response?.data?.message || 'Erreur lors de la sauvegarde')
  }
}

const editPointOfSale = (pos) => {
  form.value = { id: pos.id, name: pos.name }
  showEditForm.value = true
}

const deletePointOfSale = async (id) => {
  if (!confirm('Voulez-vous vraiment supprimer ce point de vente ?')) return
  try {
    const token = localStorage.getItem('token')
    await axios.delete(`${API_BASE_URL}/point-of-sales/${id}`, { headers: { Authorization: `Bearer ${token}` } })
    await fetchPointsOfSale()
  } catch (err) {
    alert('Suppression impossible')
  }
}

const deleteUserFromPos = async (userId, pointOfSaleId) => {
  if (!confirm('Détacher cet utilisateur ?')) return
  const token = localStorage.getItem('token')
  try {
    await axios.delete(`${API_BASE_URL}/point-of-sales/${pointOfSaleId}/users/${userId}`, { headers: { Authorization: `Bearer ${token}` } })
    await Promise.all([fetchPointsOfSale(), fetchAllUsers()])
  } catch (err) {
    alert('Action impossible')
  }
}

const attachUserToPos = async (userId, pointOfSaleId) => {
  if (!userId) return
  const token = localStorage.getItem('token')
  try {
    await axios.post(`${API_BASE_URL}/point-of-sales/${pointOfSaleId}/users/${userId}`, {}, { headers: { Authorization: `Bearer ${token}` } })
    await Promise.all([fetchPointsOfSale(), fetchAllUsers()])
    delete selectedUserForPos[pointOfSaleId]
  } catch (err) {
    alert('Lien impossible')
  }
}

const closeForm = () => {
  showAddForm.value = false
  showEditForm.value = false
  form.value = { id: null, name: '' }
}

const openCreate = () => {
  showAddForm.value = true
}

onMounted(() => {
  fetchPointsOfSale()
  fetchAllUsers()
})
</script>

<style scoped>
.pos-manage-view {
  min-height: calc(100vh - 5rem);
}
</style>
