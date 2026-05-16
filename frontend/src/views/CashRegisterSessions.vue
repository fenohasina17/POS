<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Caisse</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-cash-register" class="text-indigo-500" />
          Gestion des Caisses
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Gérez et configurez les caisses enregistreuses par point de vente.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
          @click="fetchRegisters"
          :disabled="loading"
        >
          <font-awesome-icon icon="fa-solid fa-rotate" :class="{ 'animate-spin': loading }" />
          Actualiser
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
          @click="showCreateForm = !showCreateForm"
        >
          <font-awesome-icon icon="fa-solid fa-plus" />
          {{ showCreateForm ? 'Fermer' : 'Nouvelle caisse' }}
        </button>
      </div>
    </header>

    <!-- Filtre Point de Vente (Admin uniquement) -->
    <section v-if="isAdmin" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2 text-sm font-bold text-slate-600">
          <font-awesome-icon icon="fa-solid fa-filter" class="text-indigo-500" />
          Filtrer par site :
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            @click="selectedPosFilter = null"
            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-widest transition-all"
            :class="selectedPosFilter === null ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' : 'bg-slate-50 text-slate-400 hover:bg-slate-100'"
          >
            Tous les sites
          </button>
          <button
            v-for="pos in pointsOfSale"
            :key="pos.id"
            @click="selectedPosFilter = pos.id"
            class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-widest transition-all"
            :class="selectedPosFilter === pos.id ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' : 'bg-slate-50 text-slate-400 hover:bg-slate-100'"
          >
            {{ pos.name }}
          </button>
        </div>
      </div>
    </section>

    <!-- Alertes -->
    <div v-if="errorMessage" class="flex items-center justify-between gap-3 rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-600">
      <div class="flex items-center gap-2">
        <font-awesome-icon icon="fa-solid fa-triangle-exclamation" />
        <span>{{ errorMessage }}</span>
      </div>
      <button @click="errorMessage = ''" class="text-xs font-bold uppercase tracking-widest">Fermer</button>
    </div>

    <div v-if="successMessage" class="flex items-center justify-between gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-600">
      <div class="flex items-center gap-2">
        <font-awesome-icon icon="fa-solid fa-circle-check" />
        <span>{{ successMessage }}</span>
      </div>
      <button @click="successMessage = ''" class="text-xs font-bold uppercase tracking-widest">Fermer</button>
    </div>

    <!-- Formulaire de Création -->
    <section v-if="showCreateForm" class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
        <h2 class="text-base font-bold text-slate-800">Ajouter une caisse</h2>
        <p class="text-xs text-slate-500 font-medium">Configurez une nouvelle unité de vente sur un site.</p>
      </div>

      <form class="p-6 space-y-6" @submit.prevent="createRegister">
        <div class="grid gap-6 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Nom de la caisse</label>
            <input
              v-model.trim="form.name"
              type="text"
              class="w-full rounded-2xl border-2 border-slate-50 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700 transition-all focus:border-indigo-500 focus:bg-white focus:outline-none"
              placeholder="Ex. Caisse 01"
              required
            />
          </div>

          <div v-if="isAdmin" class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Point de Vente</label>
            <select
              v-model="form.point_of_sale_id"
              class="w-full rounded-2xl border-2 border-slate-50 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700 transition-all focus:border-indigo-500 focus:bg-white focus:outline-none"
              required
            >
              <option :value="null">Choisir un point de vente...</option>
              <option v-for="pos in pointsOfSale" :key="pos.id" :value="pos.id">
                {{ pos.name }}
              </option>
            </select>
          </div>
          <div v-else class="space-y-2">
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Point de Vente</label>
            <div class="px-4 py-3 text-sm font-bold text-slate-400 italic">
              {{ userPointOfSaleName }}
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="resetForm" class="rounded-xl px-6 py-2 text-xs font-black uppercase tracking-widest text-slate-400 hover:bg-slate-50 transition-all">
            Annuler
          </button>
          <button type="submit" :disabled="saving" class="rounded-xl bg-slate-900 px-8 py-2 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-slate-200 hover:bg-indigo-600 transition-all disabled:opacity-50">
            {{ saving ? 'Création...' : 'Créer la caisse' }}
          </button>
        </div>
      </form>
    </section>

    <!-- Liste des Caisses -->
    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="border-b border-slate-100 bg-slate-50/30 px-6 py-4">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-bold text-slate-800">Unités de vente enregistrées</h2>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-slate-500">
            {{ filteredRegisters.length }} caisse{{ filteredRegisters.length > 1 ? 's' : '' }}
          </span>
        </div>
      </div>

      <div v-if="loading" class="flex flex-col items-center justify-center py-16 text-slate-400">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-100 border-t-indigo-600"></div>
        <p class="mt-4 text-xs font-bold uppercase tracking-widest">Récupération des données...</p>
      </div>

      <div v-else-if="filteredRegisters.length === 0" class="py-16 text-center">
        <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 text-slate-300">
          <font-awesome-icon icon="fa-solid fa-cash-register" class="text-2xl" />
        </div>
        <p class="mt-4 text-sm font-bold text-slate-400 uppercase tracking-widest">Aucune caisse trouvée</p>
      </div>

      <div v-else class="grid divide-y divide-slate-50">
        <div
          v-for="register in filteredRegisters"
          :key="register.id"
          class="group flex flex-wrap items-center justify-between gap-4 px-6 py-5 transition-all hover:bg-slate-50/50"
        >
          <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-indigo-500 shadow-sm border border-slate-100 group-hover:border-indigo-100 transition-all">
              <font-awesome-icon icon="fa-solid fa-cash-register" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <p class="font-black text-slate-700 uppercase tracking-tight">{{ register.name }}</p>
                <!-- Badge Statut -->
                <span 
                  v-if="register.current_session" 
                  class="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-tighter text-emerald-600 border border-emerald-100"
                >
                  Ouverte
                </span>
                <span 
                  v-else 
                  class="rounded-full bg-slate-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-tighter text-slate-400 border border-slate-100"
                >
                  Fermée
                </span>
              </div>
              <div class="flex flex-wrap items-center gap-3 mt-1">
                <div class="flex items-center gap-1.5">
                  <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Site :</span>
                  <span class="text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
                    {{ register.point_of_sale?.name || 'Inconnu' }}
                  </span>
                </div>
                <div v-if="register.current_session" class="flex items-center gap-1.5">
                  <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Caissier :</span>
                  <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">
                    {{ register.current_session.user?.name || 'Inconnu' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="openEditModal(register)"
              class="flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-indigo-600 hover:border-indigo-100 hover:shadow-md transition-all active:scale-95 shadow-sm"
              title="Modifier"
            >
              <font-awesome-icon icon="fa-solid fa-pen" class="text-xs" />
            </button>
            <button
              @click="deleteRegister(register)"
              class="flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-rose-600 hover:border-rose-100 hover:shadow-md transition-all active:scale-95 shadow-sm"
              title="Supprimer"
            >
              <font-awesome-icon icon="fa-solid fa-trash" class="text-xs" />
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Modal d'Édition -->
    <CashRegisterEditModal
      :is-open="showEditModal"
      :register="selectedRegister"
      :points-of-sale="pointsOfSale"
      @close="closeEditModal"
      @submit="handleEditSubmit"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCashRegister, faPlus, faRotate, faTrash, faPen, faXmark, faFilter, faTriangleExclamation, faCircleCheck } from '@fortawesome/free-solid-svg-icons'
import { useAuth } from '@/composables/useAuth'
import CashRegisterEditModal from './CashRegisterEditModal.vue'

library.add(faCashRegister, faPlus, faRotate, faTrash, faPen, faXmark, faFilter, faTriangleExclamation, faCircleCheck)

const { isAdmin, user: currentUser } = useAuth()

const loading = ref(false)
const saving = ref(false)
const showCreateForm = ref(false)
const showEditModal = ref(false)
const registers = ref([])
const pointsOfSale = ref([])
const selectedPosFilter = ref(null)
const selectedRegister = ref(null)

const form = ref({
  name: '',
  point_of_sale_id: null
})

const errorMessage = ref('')
const successMessage = ref('')

const userPointOfSaleId = computed(() => currentUser.value?.point_of_sale_id)
const userPointOfSaleName = computed(() => currentUser.value?.point_of_sale?.name || 'Point de vente non défini')

const filteredRegisters = computed(() => {
  if (selectedPosFilter.value === null) return registers.value
  return registers.value.filter(r => (r.point_of_sale_id || r.point_of_sale?.id) === selectedPosFilter.value)
})

const getAuthHeaders = () => {
  const token = localStorage.getItem('token')
  return { Authorization: `Bearer ${token}` }
}

const fetchPointsOfSale = async () => {
  if (!isAdmin.value) return
  try {
    const { data } = await axios.get(`${API_BASE_URL}/point-of-sales`, { headers: getAuthHeaders() })
    pointsOfSale.value = data?.data || data || []
  } catch (err) {
    console.error('Erreur sites:', err)
  }
}

const fetchRegisters = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers`, { headers: getAuthHeaders() })
    registers.value = data?.data || data || []
  } catch (err) {
    console.error('Erreur caisses:', err)
    errorMessage.value = 'Impossible de charger les caisses.'
  } finally {
    loading.value = false
  }
}

const createRegister = async () => {
  if (!form.value.name.trim()) return
  
  const posId = isAdmin.value ? form.value.point_of_sale_id : userPointOfSaleId.value
  if (!posId) {
    errorMessage.value = 'Veuillez sélectionner un point de vente.'
    return
  }

  saving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload = {
      name: form.value.name.trim(),
      point_of_sale_id: posId
    }

    await axios.post(`${API_BASE_URL}/cash-registers`, payload, { headers: getAuthHeaders() })
    successMessage.value = 'Caisse ajoutée avec succès.'
    resetForm()
    await fetchRegisters()
  } catch (error) {
    console.error('Erreur création:', error)
    errorMessage.value = error.response?.data?.message || 'Erreur lors de la création.'
  } finally {
    saving.value = false
  }
}

const deleteRegister = async (register) => {
  if (!confirm(`Supprimer la caisse "${register.name}" ?`)) return

  loading.value = true
  try {
    await axios.delete(`${API_BASE_URL}/cash-registers/${register.id}`, { headers: getAuthHeaders() })
    successMessage.value = 'Caisse supprimée.'
    await fetchRegisters()
  } catch (err) {
    errorMessage.value = 'Suppression impossible.'
  } finally {
    loading.value = false
  }
}

const openEditModal = (register) => {
  selectedRegister.value = { ...register }
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedRegister.value = null
}

const handleEditSubmit = async (formData) => {
  try {
    await axios.put(`${API_BASE_URL}/cash-registers/${formData.id}`, formData, { headers: getAuthHeaders() })
    successMessage.value = 'Caisse mise à jour avec succès.'
    closeEditModal()
    await fetchRegisters()
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Mise à jour impossible.'
  }
}

const resetForm = () => {
  showCreateForm.value = false
  form.value = { name: '', point_of_sale_id: isAdmin.value ? null : userPointOfSaleId.value }
}

onMounted(() => {
  fetchPointsOfSale()
  fetchRegisters()
  if (!isAdmin.value) {
    form.value.point_of_sale_id = userPointOfSaleId.value
  }
})
</script>
