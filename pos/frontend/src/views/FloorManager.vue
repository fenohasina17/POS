<template>
  <div class="floor-manager-view min-h-screen bg-slate-50/50 p-4 lg:p-8">
    <Profile v-if="!embedded" class="mb-8" />

    <div class="mx-auto max-w-7xl">
      <!-- Header Premium -->
      <header class="mb-10 flex flex-wrap items-end justify-between gap-6 px-2">
        <div>
          <div class="mb-2 flex items-center gap-3">
            <div class="h-1 w-10 rounded-full bg-indigo-600"></div>
            <span class="text-[10px] font-black uppercase tracking-[0.3em] text-indigo-600/60">Gestion de salle</span>
          </div>
          <h1 class="text-4xl font-black tracking-tighter text-slate-900 lg:text-5xl">
            Floor <span class="text-slate-400">Manager</span>
          </h1>
        </div>

        <div class="flex items-center gap-4">
          <button
            @click="refreshData"
            class="group flex h-14 w-14 items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-400 shadow-sm transition-all hover:border-indigo-200 hover:text-indigo-600 hover:shadow-lg hover:shadow-indigo-50 active:scale-90"
            title="Rafraîchir le plan"
          >
            <font-awesome-icon icon="fa-solid fa-rotate" :class="{'fa-spin': loading}" />
          </button>

          <div class="flex items-center gap-1 rounded-[1.25rem] bg-slate-900 p-1.5 shadow-2xl shadow-slate-900/20">
            <button
              v-for="status in ['all', 'available', 'occupied', 'reserved']"
              :key="status"
              @click="statusFilter = status === 'all' ? '' : status"
              class="flex items-center gap-2 rounded-xl px-5 py-3 text-[10px] font-black tracking-wide transition-all"
              :class="[
                (statusFilter === status || (status === 'all' && !statusFilter))
                  ? 'bg-white text-slate-900 shadow-md'
                  : 'text-white/40 hover:text-white/70 hover:bg-white/5'
              ]"
            >
              <span v-if="status !== 'all'" class="h-4 w-4 rounded-full shadow-sm" :class="getStatusBgClass(status)"></span>
              {{ status === 'all' ? 'TOUTES' : getStatusText(status).toUpperCase() }} ({{ getTableCount(status) }})
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

      <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7">
        <article
          v-for="table in filteredTables"
          :key="table.id"
          class="group relative aspect-square overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white p-3 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
          :class="[
            isTableLockedByOther(table) ? 'bg-slate-50/50' : 'hover:border-indigo-400'
          ]"
        >
          <!-- Coin de Statut (Indicateur discret) -->
          <div
            class="absolute right-0 top-0 h-10 w-10 translate-x-5 -translate-y-5 rotate-45 opacity-20 transition-all group-hover:opacity-40"
            :class="getStatusBgClass(table.status)"
          ></div>

          <div class="flex h-full flex-col justify-between">
            <!-- Overlay Verrouillage simple (si occupé par autre) -->
            <div
              v-if="isTableLockedByOther(table)"
              class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-slate-900/10 backdrop-blur-[1px] transition-all"
            >
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white text-rose-500 shadow-xl border border-rose-100">
                    <font-awesome-icon icon="fa-solid fa-lock" class="text-3xl" />
                </div>
            </div>

            <!-- Header: Status Icon & Badge -->
            <div class="flex items-center justify-between">
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-lg shadow-sm"
                    :class="isTableLockedByOther(table) ? 'bg-slate-200 text-slate-400' : 'bg-slate-50 text-slate-400 group-hover:bg-indigo-600 group-hover:text-white'"
                >
                    <font-awesome-icon :icon="getStatusIcon(table.status)" class="text-[10px]" />
                </div>
                <div class="h-6 w-6 rounded-full animate-pulse shadow-sm" :class="getStatusBgClass(table.status)"></div>
            </div>

            <!-- Centre: Numéro de Table -->
            <div class="flex flex-col items-center justify-center text-center">
              <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 leading-none">Table</span>
              <h3 class="text-2xl font-black text-slate-800 tracking-tighter">{{ table.table_number }}</h3>
              <p v-if="table.name" class="max-w-full truncate text-[9px] font-bold text-slate-500">{{ table.name }}</p>
            </div>

            <!-- Footer: Actions ou Verrouillage -->
            <div class="relative z-30">
                <!-- Nom caissier si verrouillage -->
                <div v-if="isTableLockedByOther(table)" class="flex items-center justify-center gap-1 rounded-xl bg-white/90 py-1.5 border border-slate-200 shadow-sm">
                    <span class="truncate text-[9px] font-black text-rose-600 uppercase tracking-tighter">Par {{ table.locked_by_session?.user?.name || 'Occupé' }}</span>
                </div>

                <!-- Actions Compactes -->
                <div v-else class="flex items-center gap-1">
                    <button
                        @click="startTableService(table)"
                        :disabled="table.status === 'out_of_order' || isTableLockedByOther(table)"
                        class="flex flex-1 items-center justify-center gap-1 rounded-lg h-8 text-[9px] font-black text-white transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="[
                            (table.status === 'out_of_order' || isTableLockedByOther(table))
                                ? 'bg-slate-400'
                                : 'bg-slate-900 hover:bg-indigo-600'
                        ]"
                    >
                        {{ isTableLockedByOther(table) ? 'OCCUPÉ' : 'CMD' }}
                    </button>
                    <button
                        @click="viewTableDetails(table)"
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-all hover:bg-slate-200 hover:text-slate-800"
                    >
                        <font-awesome-icon icon="fa-solid fa-eye" class="text-[9px]" />
                    </button>
                    <button
                        @click="printTableBill(table)"
                        :disabled="table.status === 'available'"
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-all hover:bg-emerald-50 hover:text-emerald-600 disabled:opacity-30"
                    >
                        <font-awesome-icon icon="fa-solid fa-print" class="text-[9px]" />
                    </button>
                </div>
            </div>
          </div>
        </article>
      </div>
    </div>

    <!-- Modal Détails Premium -->
    <div v-if="showTableDetails" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md" @click="closeTableDetails"></div>

      <div class="relative w-full max-w-sm overflow-hidden rounded-[3rem] bg-white shadow-2xl transition-all border border-white">
        <header class="relative overflow-hidden bg-slate-900 p-8 pb-12">
          <!-- Motifs décoratifs -->
          <div class="absolute -right-4 -top-4 h-32 w-32 rounded-full bg-indigo-500/20 blur-3xl"></div>
          <div class="absolute -left-10 -bottom-10 h-32 w-32 rounded-full bg-emerald-500/10 blur-2xl"></div>

          <div class="relative z-10 flex items-center justify-between text-white">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.2em] text-white/40">Fiche Technique</p>
              <h2 class="mt-1 text-3xl font-black tracking-tight">Table {{ selectedTable?.table_number }}</h2>
            </div>
            <button @click="closeTableDetails" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-white hover:bg-white/20 transition-all active:scale-90">
              <font-awesome-icon icon="fa-solid fa-xmark" />
            </button>
          </div>
        </header>

        <div class="relative z-10 -mt-8 rounded-t-[3rem] bg-white p-8">
          <div class="space-y-3">
            <div v-for="(val, label) in getDetailRows()" :key="label" class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 border border-slate-100/50">
              <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ label }}</span>
              <span class="text-sm font-black text-slate-700">{{ val }}</span>
            </div>

            <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 border border-slate-100/50">
              <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">État du service</span>
              <span
                class="flex items-center gap-2 rounded-full px-4 py-1.5 text-[10px] font-black uppercase tracking-widest shadow-sm"
                :class="getStatusTextClass(selectedTable?.status)"
              >
                <span class="h-4 w-4 rounded-full shadow-sm" :class="getStatusBgClass(selectedTable?.status)"></span>
                {{ getStatusText(selectedTable?.status) }}
              </span>
            </div>
          </div>

          <button
            @click="closeTableDetails"
            class="mt-8 flex w-full items-center justify-center gap-3 rounded-2xl bg-slate-900 py-4 text-xs font-black text-white shadow-xl shadow-slate-200 transition-all hover:bg-indigo-600 hover:shadow-indigo-200 active:scale-95"
          >
            TERMINÉ
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/apiClient'
import EchoInstance from '@/services/echo'
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
const currentSessionId = ref(null)

// ========== LOGIQUE ==========

const getTableCount = (status) => {
  if (status === 'all') return tables.value.length
  return tables.value.filter(t => t.status === status).length
}

// Récupération de la session active
const loadCurrentSession = async () => {
  try {
    const response = await apiClient.get('/my-active-session')
    const session = response.data?.current_session || response.data?.data || response.data
    if (session?.id) {
      currentSessionId.value = session.id
    }
  } catch (e) {
    console.warn('Erreur lors de la récupération de la session:', e.message)
    // Fallback localStorage
    const sessionId = localStorage.getItem('cash_register_session_id')
    if (sessionId) {
      currentSessionId.value = parseInt(sessionId)
    }
  }
}

const isTableLockedByOther = (table) => {
  return table.status === 'occupied' &&
         table.locked_by_session_id &&
         String(table.locked_by_session_id) !== String(currentSessionId.value)
}

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
    available: 'Disponible',
    occupied: 'En service',
    reserved: 'Réservée',
    out_of_order: 'Hors-service'
  }
  return texts[status] || 'Inconnu'
}

const getStatusBgClass = (status) => {
  const classes = {
    available: 'bg-emerald-500',
    occupied: 'bg-indigo-500',
    reserved: 'bg-amber-500',
    out_of_order: 'bg-slate-500'
  }
  return classes[status] || 'bg-slate-400'
}

const getStatusTextClass = (status) => {
  const classes = {
    available: 'text-emerald-600 bg-emerald-50',
    occupied: 'text-indigo-600 bg-indigo-50',
    reserved: 'text-amber-600 bg-amber-50',
    out_of_order: 'text-slate-600 bg-slate-50'
  }
  return classes[status] || 'text-slate-500 bg-slate-50'
}

const getStatusIcon = (status) => {
  const icons = {
    available: 'fa-solid fa-check-circle',
    occupied: 'fa-solid fa-utensils',
    reserved: 'fa-solid fa-calendar-check',
    out_of_order: 'fa-solid fa-triangle-exclamation'
  }
  return icons[status] || 'fa-solid fa-table'
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
    const response = await apiClient.get('/tables', {
      params: { point_of_sale_id: activePos.value.id }
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

// Real-time updates
const initEcho = () => {
  if (EchoInstance) {
    EchoInstance.channel('tables')
      .listen('.table.updated', (e) => {
        loadTables();
      });
  }
}

const startTableService = async (table) => {
  if (table.status === 'out_of_order') return

  try {
    // Verrouiller la table immédiatement via API
    await apiClient.post(`/tables/${table.id}/lock`)

    // Rediriger vers la page de commande
    router.push({
      name: 'dashboard-table-order',
      params: { tableId: table.id },
      query: { posId: activePos.value?.id }
    })
  } catch (error) {
    console.error('Erreur lors du verrouillage de la table:', error)
    alert(error.response?.data?.message || 'Impossible de prendre la table')
  }
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
  loadCurrentSession()
  initEcho()
  if (hasActivePos.value) {
    loadTables()
  }
})

onBeforeUnmount(() => {
  if (EchoInstance) {
    EchoInstance.leave('tables')
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
