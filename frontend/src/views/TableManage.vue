<template>
  <div class="space-y-6">
    <!-- Header -->
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Point de Vente</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-table-cells" class="text-indigo-500" />
          Gestion des Tables
        </h1>
        <p class="mt-2 text-sm text-slate-500">Gérez les tables de votre point de vente.</p>
      </div>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95"
      >
        <font-awesome-icon icon="fa-solid fa-plus" />
        Nouvelle Table
      </button>
    </header>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100">
            <font-awesome-icon icon="fa-solid fa-table-cells" class="text-slate-600" />
          </div>
          <div>
            <p class="text-2xl font-bold text-slate-900">{{ statistics.total_tables }}</p>
            <p class="text-xs text-slate-500">Total</p>
          </div>
        </div>
      </div>
      <div class="rounded-3xl border border-emerald-100 bg-emerald-50 p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-100">
            <font-awesome-icon icon="fa-solid fa-circle-check" class="text-emerald-600" />
          </div>
          <div>
            <p class="text-2xl font-bold text-emerald-700">{{ statistics.available_tables }}</p>
            <p class="text-xs text-emerald-600">Disponibles</p>
          </div>
        </div>
      </div>
      <div class="rounded-3xl border border-rose-100 bg-rose-50 p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-100">
            <font-awesome-icon icon="fa-solid fa-users" class="text-rose-600" />
          </div>
          <div>
            <p class="text-2xl font-bold text-rose-700">{{ statistics.occupied_tables }}</p>
            <p class="text-xs text-rose-600">Occupées</p>
          </div>
        </div>
      </div>
      <div class="rounded-3xl border border-indigo-100 bg-indigo-50 p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-100">
            <font-awesome-icon icon="fa-solid fa-percent" class="text-indigo-600" />
          </div>
          <div>
            <p class="text-2xl font-bold text-indigo-700">{{ statistics.occupancy_rate }}%</p>
            <p class="text-xs text-indigo-600">Occupation</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-48">
          <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par numéro ou nom..."
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-4 text-sm text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in statusFilters"
            :key="status.value"
            @click="toggleStatusFilter(status.value)"
            class="inline-flex items-center gap-1.5 rounded-2xl px-3 py-2 text-xs font-semibold transition"
            :class="activeFilters.includes(status.value)
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'border border-slate-200 text-slate-600 hover:border-indigo-200 hover:text-indigo-600'"
          >
            <font-awesome-icon :icon="status.faIcon" />
            {{ status.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- Tables Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      <div
        v-for="table in filteredTables"
        :key="table.id"
        class="cursor-pointer rounded-3xl border bg-white shadow-sm transition hover:shadow-md"
        :class="{
          'border-emerald-200': table.status === 'available',
          'border-rose-200': table.status === 'occupied',
          'border-amber-200': table.status === 'reserved',
          'border-slate-200': table.status === 'out_of_order',
        }"
        @click="selectTable(table)"
      >
        <div class="border-b px-4 py-3 flex items-center justify-between"
          :class="{
            'border-emerald-100 bg-emerald-50/50': table.status === 'available',
            'border-rose-100 bg-rose-50/50': table.status === 'occupied',
            'border-amber-100 bg-amber-50/50': table.status === 'reserved',
            'border-slate-100 bg-slate-50': table.status === 'out_of_order',
          }"
        >
          <span class="text-lg font-bold text-slate-800">{{ table.table_number }}</span>
          <span
            class="rounded-full px-2.5 py-1 text-xs font-semibold"
            :class="{
              'bg-emerald-100 text-emerald-700': table.status === 'available',
              'bg-rose-100 text-rose-700': table.status === 'occupied',
              'bg-amber-100 text-amber-700': table.status === 'reserved',
              'bg-slate-100 text-slate-600': table.status === 'out_of_order',
            }"
          >
            <font-awesome-icon :icon="getStatusFaIcon(table.status)" class="mr-1" />
            {{ getStatusText(table.status) }}
          </span>
        </div>

        <div class="px-4 py-4">
          <h3 class="font-semibold text-slate-800">{{ table.name || 'Sans nom' }}</h3>
          <div class="mt-1 flex items-center gap-1 text-xs text-slate-500">
            <font-awesome-icon icon="fa-solid fa-users" />
            {{ table.capacity }} personnes
          </div>
          <div v-if="formatLocation(table)" class="mt-1 flex items-center gap-1 text-xs text-slate-400">
            <font-awesome-icon icon="fa-solid fa-location-dot" />
            {{ formatLocation(table) }}
          </div>
          <p v-if="table.description" class="mt-2 text-xs text-slate-400 line-clamp-2">{{ table.description }}</p>
        </div>

        <div class="border-t border-slate-100 px-4 py-3 flex items-center gap-2" @click.stop>
          <select
            :value="table.status"
            :disabled="loading && loadingTableId === table.id"
            @change.stop="handleStatusChange(table, $event)"
            class="flex-1 rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 focus:border-indigo-300 focus:outline-none"
          >
            <option v-for="s in statusFilters" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <button
            @click.stop="editTable(table)"
            class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-slate-400 transition hover:border-indigo-200 hover:text-indigo-600"
          >
            <font-awesome-icon icon="fa-solid fa-pen" class="text-xs" />
          </button>
          <button
            @click.stop="deleteTable(table)"
            class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:text-rose-600"
          >
            <font-awesome-icon icon="fa-solid fa-trash" class="text-xs" />
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="filteredTables.length === 0" class="flex flex-col items-center justify-center rounded-3xl border border-slate-200 bg-white py-16 shadow-sm">
      <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-100">
        <font-awesome-icon icon="fa-solid fa-table-cells" class="text-2xl text-slate-400" />
      </div>
      <h3 class="mt-4 text-base font-semibold text-slate-800">Aucune table trouvée</h3>
      <p class="mt-1 text-sm text-slate-400">{{ getEmptyStateMessage() }}</p>
      <button
        @click="openCreateModal"
        class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
      >
        <font-awesome-icon icon="fa-solid fa-plus" />
        Créer une table
      </button>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click="closeModal">
      <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-2xl" @click.stop>
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-800">
            {{ isEditing ? 'Modifier la table' : 'Nouvelle table' }}
          </h2>
          <button @click="closeModal" class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
            <font-awesome-icon icon="fa-solid fa-xmark" />
          </button>
        </div>

        <form @submit.prevent="saveTable" class="px-6 py-6">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-slate-600" for="table_number">Numéro <span class="text-rose-500">*</span></label>
              <input
                id="table_number"
                v-model="form.table_number"
                type="text"
                required
                placeholder="Ex: T01"
                class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm transition focus:outline-none focus:ring-2"
                :class="errors.table_number
                  ? 'border-rose-300 focus:ring-rose-100'
                  : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-100'"
              />
              <p v-if="errors.table_number" class="mt-1 text-xs text-rose-600">{{ errors.table_number }}</p>
            </div>

            <div>
              <label class="text-sm font-medium text-slate-600" for="name">Nom</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="Ex: Table terrasse"
                class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
            </div>

            <div>
              <label class="text-sm font-medium text-slate-600" for="capacity">Capacité <span class="text-rose-500">*</span></label>
              <input
                id="capacity"
                v-model.number="form.capacity"
                type="number"
                min="1"
                max="50"
                required
                class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm transition focus:outline-none focus:ring-2"
                :class="errors.capacity
                  ? 'border-rose-300 focus:ring-rose-100'
                  : 'border-slate-200 focus:border-indigo-500 focus:ring-indigo-100'"
              />
              <p v-if="errors.capacity" class="mt-1 text-xs text-rose-600">{{ errors.capacity }}</p>
            </div>

            <div>
              <label class="text-sm font-medium text-slate-600" for="status">Statut <span class="text-rose-500">*</span></label>
              <select
                id="status"
                v-model="form.status"
                required
                class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              >
                <option value="available">Disponible</option>
                <option value="occupied">Occupée</option>
                <option value="reserved">Réservée</option>
                <option value="out_of_order">Hors service</option>
              </select>
            </div>
          </div>

          <div class="mt-4">
            <label class="text-sm font-medium text-slate-600" for="description">Description</label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              placeholder="Description ou notes..."
              class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            ></textarea>
          </div>

          <div class="mt-4">
            <label class="text-sm font-medium text-slate-600">Position (optionnel)</label>
            <div class="mt-1 grid grid-cols-2 gap-3">
              <input
                v-model.number="form.location.x"
                type="number"
                placeholder="Coordonnée X"
                min="0"
                class="rounded-2xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
              <input
                v-model.number="form.location.y"
                type="number"
                placeholder="Coordonnée Y"
                min="0"
                class="rounded-2xl border border-slate-200 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
            </div>
          </div>

          <div class="mt-6 flex items-center justify-end gap-3">
            <button
              type="button"
              @click="closeModal"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
            >
              Annuler
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
            >
              <font-awesome-icon v-if="loading" icon="fa-solid fa-spinner" class="animate-spin" />
              {{ isEditing ? 'Modifier' : 'Créer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click="closeDeleteModal">
      <div class="w-full max-w-sm rounded-3xl border border-slate-200 bg-white shadow-2xl" @click.stop>
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-semibold text-slate-800">Confirmer la suppression</h2>
          <button @click="closeDeleteModal" class="flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100">
            <font-awesome-icon icon="fa-solid fa-xmark" />
          </button>
        </div>
        <div class="px-6 py-6">
          <p class="text-sm text-slate-600">
            Êtes-vous sûr de vouloir supprimer la table <strong class="text-slate-800">{{ tableToDelete?.table_number }}</strong> ?
          </p>
          <p class="mt-2 text-xs font-semibold text-rose-600">Cette action est irréversible.</p>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4">
          <button
            @click="closeDeleteModal"
            class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
          >
            Annuler
          </button>
          <button
            @click="confirmDelete"
            :disabled="loading"
            class="inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:opacity-50"
          >
            <font-awesome-icon v-if="loading" icon="fa-solid fa-spinner" class="animate-spin" />
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { API_BASE_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'

const { activePos } = useAuth()

defineProps({
  embedded: {
    type: Boolean,
    default: false
  }
})

const tables = ref([])
const statistics = ref({
  total_tables: 0,
  available_tables: 0,
  occupied_tables: 0,
  reserved_tables: 0,
  out_of_order_tables: 0,
  occupancy_rate: 0
})
const searchQuery = ref('')
const activeFilters = ref([])
const statusFilters = [
  { value: 'available', label: 'Disponibles', faIcon: 'fa-solid fa-circle-check' },
  { value: 'occupied', label: 'Occupées', faIcon: 'fa-solid fa-users' },
  { value: 'reserved', label: 'Réservées', faIcon: 'fa-solid fa-calendar-check' },
  { value: 'out_of_order', label: 'Hors service', faIcon: 'fa-solid fa-wrench' }
]
const showModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const loading = ref(false)
const loadingTableId = ref(null)
const tableToDelete = ref(null)
const editingTableId = ref(null)
const form = ref({
  table_number: '',
  name: '',
  capacity: 4,
  status: 'available',
  description: '',
  location: { x: null, y: null }
})
const errors = ref({})

const filteredTables = computed(() => {
  let filtered = tables.value
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(table =>
      table.table_number.toLowerCase().includes(query) ||
      (table.name && table.name.toLowerCase().includes(query)) ||
      (table.description && table.description.toLowerCase().includes(query))
    )
  }
  if (activeFilters.value.length > 0) {
    filtered = filtered.filter(table => activeFilters.value.includes(table.status))
  }
  return filtered
})

const loadTables = async () => {
  try {
    const posId = activePos.value?.id
    if (!posId) {
      tables.value = []
      return
    }
    const token = localStorage.getItem('token')
    const response = await fetch(`${API_BASE_URL}/tables?point_of_sale_id=${posId}`, {
      headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' }
    })
    if (response.ok) {
      const payload = await response.json()
      tables.value = normalizeTables(payload)
      refreshStatisticsFromTables()
    }
  } catch (error) {
    console.error('Erreur:', error)
  }
}

const normalizeTables = (payload) => {
  const rawTables = Array.isArray(payload) ? payload : Array.isArray(payload?.data) ? payload.data : []
  return rawTables.map((table) => ({
    ...table,
    status: normalizeStatus(table.status),
    location: normalizeLocation(table.location)
  }))
}

const normalizeStatus = (status) => {
  const normalized = String(status || 'available').trim().toLowerCase()
  const aliases = {
    disponible: 'available', available: 'available',
    occupee: 'occupied', occupée: 'occupied', occupied: 'occupied',
    reservee: 'reserved', réservée: 'reserved', reserved: 'reserved',
    hors_service: 'out_of_order', horsservice: 'out_of_order', out_of_order: 'out_of_order', outoforder: 'out_of_order'
  }
  return aliases[normalized] || normalized
}

const normalizeLocation = (location) => {
  if (!location || typeof location !== 'object') return { x: null, y: null }
  const x = Number(location.x ?? location.pos_x ?? location.left ?? null)
  const y = Number(location.y ?? location.pos_y ?? location.top ?? null)
  return { x: Number.isFinite(x) ? x : null, y: Number.isFinite(y) ? y : null }
}

const prepareLocationPayload = (location) => {
  const normalized = normalizeLocation(location)
  return (normalized.x === null && normalized.y === null) ? null : normalized
}

const refreshStatisticsFromTables = () => {
  const total = tables.value.length
  const available = tables.value.filter(t => t.status === 'available').length
  const occupied = tables.value.filter(t => t.status === 'occupied').length
  const reserved = tables.value.filter(t => t.status === 'reserved').length
  const outOfOrder = tables.value.filter(t => t.status === 'out_of_order').length
  statistics.value = {
    total_tables: total,
    available_tables: available,
    occupied_tables: occupied,
    reserved_tables: reserved,
    out_of_order_tables: outOfOrder,
    occupancy_rate: total > 0 ? Math.round((occupied / total) * 100) : 0
  }
}

const formatLocation = (table) => {
  const location = normalizeLocation(table?.location)
  if (location.x === null && location.y === null) return ''
  return `X: ${location.x ?? '-'} | Y: ${location.y ?? '-'}`
}

const toggleStatusFilter = (status) => {
  const index = activeFilters.value.indexOf(status)
  if (index > -1) activeFilters.value.splice(index, 1)
  else activeFilters.value.push(status)
}

const getStatusFaIcon = (status) => {
  const icons = {
    available: 'fa-solid fa-circle-check',
    occupied: 'fa-solid fa-users',
    reserved: 'fa-solid fa-calendar-check',
    out_of_order: 'fa-solid fa-wrench'
  }
  return icons[status] || 'fa-solid fa-circle-question'
}

const getStatusText = (status) => {
  const texts = {
    available: 'Disponible',
    occupied: 'Occupée',
    reserved: 'Réservée',
    out_of_order: 'Hors service'
  }
  return texts[status] || 'Inconnu'
}

const selectTable = (table) => {
  console.log('Table sélectionnée:', table)
}

const resetForm = () => {
  form.value = { table_number: '', name: '', capacity: 4, status: 'available', description: '', location: { x: null, y: null } }
  errors.value = {}
}

const openCreateModal = () => {
  isEditing.value = false
  resetForm()
  showModal.value = true
}

const editTable = (table) => {
  isEditing.value = true
  editingTableId.value = table.id
  form.value = {
    table_number: table.table_number,
    name: table.name || '',
    capacity: table.capacity,
    status: table.status,
    description: table.description || '',
    location: normalizeLocation(table.location)
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  resetForm()
}

const saveTable = async () => {
  loading.value = true
  errors.value = {}
  try {
    const token = localStorage.getItem('token')
    const posId = activePos.value?.id
    if (!posId) { alert('Point de vente actif non défini'); return }

    const url = isEditing.value
      ? `${API_BASE_URL}/tables/${editingTableId.value}`
      : `${API_BASE_URL}/tables`
    const method = isEditing.value ? 'PUT' : 'POST'

    const formData = {
      table_number: String(form.value.table_number || '').trim(),
      name: String(form.value.name || '').trim() || null,
      capacity: Number(form.value.capacity) || 0,
      status: normalizeStatus(form.value.status),
      description: String(form.value.description || '').trim() || null,
      point_of_sale_id: posId
    }
    const location = prepareLocationPayload(form.value.location)
    if (location) formData.location = location

    const response = await fetch(url, {
      method,
      headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify(formData)
    })

    const rawText = await response.text()
    let data = {}
    try { data = rawText ? JSON.parse(rawText) : {} } catch { data = { message: rawText } }

    if (response.ok) {
      await loadTables()
      closeModal()
    } else {
      if (response.status === 422) errors.value = data.errors || {}
      else alert(data.error || data.message || 'Erreur lors de la sauvegarde')
    }
  } catch (error) {
    console.error('Erreur:', error)
    alert('Erreur de connexion')
  } finally {
    loading.value = false
  }
}

const handleStatusChange = (table, event) => {
  const newStatus = normalizeStatus(event.target.value)
  if (newStatus === table.status) return
  updateTableStatus(table.id, newStatus)
}

const updateTableStatus = async (tableId, status) => {
  loadingTableId.value = tableId
  try {
    const token = localStorage.getItem('token')
    const response = await fetch(`${API_BASE_URL}/tables/${tableId}/status`, {
      method: 'PATCH',
      headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ status })
    })
    if (response.ok) await loadTables()
    else { const data = await response.json(); alert(data.error || 'Erreur lors de la mise à jour du statut') }
  } catch (error) {
    console.error('Erreur:', error)
    alert('Erreur de connexion')
  } finally {
    loadingTableId.value = null
  }
}

const deleteTable = (table) => {
  tableToDelete.value = table
  showDeleteModal.value = true
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  tableToDelete.value = null
}

const confirmDelete = async () => {
  if (!tableToDelete.value) return
  loading.value = true
  try {
    const token = localStorage.getItem('token')
    const response = await fetch(`${API_BASE_URL}/tables/${tableToDelete.value.id}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' }
    })
    if (response.ok) { await loadTables(); closeDeleteModal() }
    else { const data = await response.json(); alert(data.error || 'Erreur lors de la suppression') }
  } catch (error) {
    console.error('Erreur:', error)
    alert('Erreur de connexion')
  } finally {
    loading.value = false
  }
}

const getEmptyStateMessage = () => {
  if (searchQuery.value) return 'Aucune table ne correspond à votre recherche.'
  if (activeFilters.value.length > 0) return 'Aucune table ne correspond aux filtres sélectionnés.'
  return 'Commencez par créer votre première table.'
}

onMounted(loadTables)
</script>
