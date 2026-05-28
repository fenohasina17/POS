<template>
  <div class="floor-manager-view min-h-screen bg-slate-50/50 p-4 lg:p-8">
    <Profile v-if="!embedded" class="mb-8" />

    <div class="mx-auto max-w-7xl">
      <!-- Header Premium -->
      <header class="mb-8 flex flex-wrap items-center justify-between gap-6">
        <div>
          <h1 class="text-3xl font-black bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent">
            Floor Manager
          </h1>
          <p class="text-slate-500 font-medium">Gestion du service en salle et disponibilité des tables</p>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="refreshData"
            class="group flex h-12 w-12 items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 shadow-sm transition-all hover:border-indigo-200 hover:text-indigo-600 active:scale-95"
          >
            <font-awesome-icon icon="fa-solid fa-rotate" :class="{'fa-spin': loading}" />
          </button>

          <div class="flex items-center gap-2 rounded-2xl bg-slate-900 p-1.5 shadow-lg shadow-slate-900/10">
            <button
              v-for="status in ['all', 'available', 'occupied', 'reserved']"
              :key="status"
              @click="statusFilter = status === 'all' ? '' : status"
              class="rounded-xl px-4 py-2 text-xs font-bold transition-all"
              :class="[
                (statusFilter === status || (status === 'all' && !statusFilter))
                  ? 'bg-white text-slate-900 shadow-sm'
                  : 'text-white/40 hover:text-white/70'
              ]"
            >
              {{ status === 'all' ? 'Toutes' : getStatusText(status) }}
            </button>
          </div>
        </div>
      </header>

      <!-- Grid des Tables -->
      <div v-if="loading && !tables.length" class="flex h-64 items-center justify-center">
        <div class="flex flex-col items-center gap-4">
          <div class="h-12 w-12 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-600"></div>
          <p class="font-bold text-slate-400">Synchronisation du plan de salle...</p>
        </div>
      </div>

      <div v-else-if="!filteredTables.length" class="flex h-64 flex-col items-center justify-center rounded-[2.5rem] border-2 border-dashed border-slate-200 bg-white/50">
        <font-awesome-icon icon="fa-solid fa-table" class="mb-4 text-4xl text-slate-200" />
        <p class="text-lg font-bold text-slate-400">Aucune table ne correspond</p>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <article
          v-for="table in filteredTables"
          :key="table.id"
          class="group relative overflow-hidden rounded-[2rem] border border-white bg-white p-6 shadow-xl shadow-slate-200/50 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:shadow-indigo-100"
        >
          <!-- Indicateur Statut -->
          <div
            class="absolute right-0 top-0 h-24 w-24 translate-x-12 -translate-y-12 rotate-45 transition-transform group-hover:scale-110"
            :class="getStatusBgClass(table.status)"
          ></div>

          <div class="relative z-10">
            <div class="mb-4 flex items-center justify-between">
              <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                <font-awesome-icon icon="fa-solid fa-table" class="text-xl" />
              </div>
              <span
                class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest"
                :class="getStatusTextClass(table.status)"
              >
                {{ getStatusText(table.status) }}
              </span>
            </div>

            <h3 class="text-2xl font-black text-slate-800">Table {{ table.table_number }}</h3>
            <p v-if="table.name" class="text-sm font-bold text-slate-400">{{ table.name }}</p>
            <p class="mt-1 text-xs font-medium text-slate-300">{{ table.capacity }} places disponibles</p>

            <!-- Actions Rapides -->
            <div class="mt-8 flex items-center gap-2">
              <button
                @click="startTableService(table)"
                :disabled="table.status === 'out_of_order'"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 py-3 text-xs font-bold text-white transition-all hover:bg-indigo-600 active:scale-95 disabled:opacity-30"
              >
                <font-awesome-icon icon="fa-solid fa-plus" />
                COMMANDER
              </button>

              <button
                @click="viewTableDetails(table)"
                class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition-all hover:bg-slate-200 hover:text-slate-600"
              >
                <font-awesome-icon icon="fa-solid fa-eye" />
              </button>

              <button
                @click="printTableBill(table)"
                class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition-all hover:bg-emerald-50 hover:text-emerald-600"
              >
                <font-awesome-icon icon="fa-solid fa-print" />
              </button>
            </div>
          </div>
        </article>
      </div>
    </div>

    <!-- Modal Détails Premium -->
    <div v-if="showTableDetails" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeTableDetails"></div>

      <div class="relative w-full max-w-md overflow-hidden rounded-[2.5rem] bg-white shadow-2xl transition-all">
        <header class="relative h-32 bg-slate-900 p-8">
          <div class="flex items-center justify-between text-white">
            <div>
              <h2 class="text-2xl font-black">Table {{ selectedTable?.table_number }}</h2>
              <p class="text-white/40 font-bold uppercase tracking-widest text-[10px]">Détails techniques</p>
            </div>
            <button @click="closeTableDetails" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 transition-colors">
              <font-awesome-icon icon="fa-solid fa-xmark" />
            </button>
          </div>
          <!-- Cercle décoratif -->
          <div class="absolute -bottom-12 -right-12 h-40 w-40 rounded-full bg-indigo-500/20 blur-3xl"></div>
        </header>

        <div class="p-8">
          <div class="space-y-4">
            <div v-for="(val, label) in getDetailRows()" :key="label" class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 border border-slate-100">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ label }}</span>
              <span class="font-black text-slate-700">{{ val }}</span>
            </div>

            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 border border-slate-100">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Statut actuel</span>
              <span
                class="rounded-full px-4 py-1.5 text-[10px] font-black uppercase tracking-widest shadow-sm"
                :class="getStatusTextClass(selectedTable?.status)"
              >
                {{ getStatusText(selectedTable?.status) }}
              </span>
            </div>
          </div>

          <button
            @click="closeTableDetails"
            class="mt-8 w-full rounded-2xl bg-slate-900 py-4 font-black text-white shadow-xl shadow-slate-200 transition-all hover:bg-indigo-600 active:scale-95"
          >
            FERMER
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import Profile from './Profile.vue'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
  embedded: { type: Boolean, default: false }
})

const router = useRouter()
const { activePos, pointsOfSale, setActivePos } = useAuth()  // Récupérer le POS actif

// ========== ÉTATS ==========
const tables = ref([])
const statusFilter = ref('')
const loading = ref(false)
const showTableDetails = ref(false)
const selectedTable = ref(null)

// ========== LOGIQUE ==========
const normalizeStatus = (status) => {
  const normalized = String(status || 'available').trim().toLowerCase()
  const aliases = {
    disponible: 'available', libre: 'available',
    occupee: 'occupied', occupée: 'occupied',
    reservee: 'reserved', réservée: 'reserved',
    hors_service: 'out_of_order'
  }
  return aliases[normalized] || normalized
}

const getStatusText = (status) => {
  const texts = {
    available: 'Libre',
    occupied: 'En cours',
    reserved: 'Réservée',
    out_of_order: 'HS'
  }
  return texts[status] || '—'
}

const getStatusBgClass = (status) => {
  const classes = {
    available: 'bg-emerald-500/10',
    occupied: 'bg-indigo-500/10',
    reserved: 'bg-amber-500/10',
    out_of_order: 'bg-rose-500/10'
  }
  return classes[status] || 'bg-slate-100'
}

const getStatusTextClass = (status) => {
  const classes = {
    available: 'bg-emerald-100 text-emerald-600',
    occupied: 'bg-indigo-100 text-indigo-600',
    reserved: 'bg-amber-100 text-amber-600',
    out_of_order: 'bg-rose-100 text-rose-600'
  }
  return classes[status] || 'bg-slate-100 text-slate-500'
}

// Filtrer les tables par point de vente actif ET par statut
const filteredTables = computed(() => {
  let result = tables.value

  // Filtrer par point de vente actif
  if (activePos.value && activePos.value.id) {
    result = result.filter(t => t.point_of_sale_id === activePos.value.id)
  }

  // Filtrer par statut
  if (statusFilter.value) {
    result = result.filter(t => t.status === statusFilter.value)
  }

  return result
})

const getDetailRows = () => {
  if (!selectedTable.value) return {}
  return {
    'Numéro': `#${selectedTable.value.table_number}`,
    'Identification': selectedTable.value.name || 'Sans nom',
    'Capacité': `${selectedTable.value.capacity} pers.`,
    'Point de vente': activePos.value?.name || 'Non défini'
  }
}

// ========== ACTIONS ==========
const loadTables = async () => {
  // Vérifier qu'un point de vente est sélectionné
  if (!activePos.value || !activePos.value.id) {
    console.warn('Aucun point de vente sélectionné')
    tables.value = []
    return
  }

  loading.value = true
  try {
    const token = localStorage.getItem('token')
    // Option 1: Si l'API supporte le filtrage par point_of_sale_id
    const response = await axios.get(`${API_BASE_URL}/tables`, {
      params: { point_of_sale_id: activePos.value.id },  // Filtrer côté API
      headers: { Authorization: `Bearer ${token}` }
    })

    const rawTables = Array.isArray(response.data) ? response.data : response.data.data || []
    tables.value = rawTables.map(t => ({
      ...t,
      status: normalizeStatus(t.status)
    }))
  } catch (error) {
    console.error('Erreur chargement tables:', error)
    tables.value = []
  } finally {
    loading.value = false
  }
}

const refreshData = () => loadTables()

const startTableService = (table) => {
  if (table.status === 'out_of_order') return
  router.push({
    name: 'dashboard-table-order',
    params: { tableId: table.id },
    query: { posId: activePos.value?.id }  // Passer le POS à la vue commande
  })
}

const viewTableDetails = (table) => {
  selectedTable.value = table
  showTableDetails.value = true
}

const closeTableDetails = () => {
  showTableDetails.value = false
  selectedTable.value = null
}

const printTableBill = (table) => {
  console.log('Impression facture table:', table.table_number, 'POS:', activePos.value?.name)
}

// Surveiller les changements de point de vente actif
watch(activePos, (newPos, oldPos) => {
  if (newPos?.id !== oldPos?.id) {
    loadTables()  // Recharger les tables quand le POS change
  }
}, { deep: true })

// Montrer un message si aucun POS n'est sélectionné
const hasActivePos = computed(() => !!activePos.value?.id)

onMounted(() => {
  if (hasActivePos.value) {
    loadTables()
  }
})
</script>

<style scoped>
.floor-manager-view {
  min-height: calc(100vh - 5rem);
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
