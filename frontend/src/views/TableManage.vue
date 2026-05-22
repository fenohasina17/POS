<template>
  <div class="table-management">
    <Profile v-if="!embedded" />

    <!-- Header -->
    <div class="management-header">
      <div class="header-content">
        <h1 class="page-title">
          <i class="fas fa-table mr-3"></i>
          Gestion des Tables
        </h1>
        <p class="page-subtitle">Gérez les tables de votre point de vente</p>
      </div>
      <div class="header-actions">
        <button @click="openCreateModal" class="btn-primary">
          <i class="fas fa-plus mr-2"></i>
          Nouvelle Table
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-table"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.total_tables }}</h3>
          <p>Nombre de tables</p>
        </div>
      </div>
      <div class="stat-card available">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.available_tables }}</h3>
          <p>Disponibles</p>
        </div>
      </div>
      <div class="stat-card occupied">
        <div class="stat-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.occupied_tables }}</h3>
          <p>Occupées</p>
        </div>
      </div>
      <div class="stat-card rate">
        <div class="stat-icon">
          <i class="fas fa-percentage"></i>
        </div>
        <div class="stat-content">
          <h3>{{ statistics.occupancy_rate }}%</h3>
          <p>Taux d'occupation</p>
        </div>
      </div>
    </div>

    <!-- Filters and Search -->
    <div class="filters-section">
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Rechercher par numéro ou nom de table..."
          class="search-input"
        />
      </div>
      <div class="filter-buttons">
        <button
          v-for="status in statusFilters"
          :key="status.value"
          @click="toggleStatusFilter(status.value)"
          :class="['filter-btn', { active: activeFilters.includes(status.value) }]"
        >
          <i :class="status.icon"></i>
          {{ status.label }}
        </button>
      </div>
    </div>

    <!-- Tables Grid -->
    <div class="tables-grid">
      <div
        v-for="table in filteredTables"
        :key="table.id"
        class="table-card"
        :class="getTableCardClass(table)"
        @click="selectTable(table)"
      >
        <div class="table-header">
          <div class="table-number">{{ table.table_number }}</div>
          <div class="table-status" :class="table.status">
            <i :class="getStatusIcon(table.status)"></i>
            {{ getStatusText(table.status) }}
          </div>
        </div>

        <div class="table-body">
          <h3 class="table-name">{{ table.name || 'Sans nom' }}</h3>
          <div class="table-capacity">
            <i class="fas fa-users"></i>
            {{ table.capacity }} personnes
          </div>
          <div v-if="formatLocation(table)" class="table-location">
            <i class="fas fa-location-dot"></i>
            {{ formatLocation(table) }}
          </div>
          <div v-if="table.description" class="table-description">
            {{ table.description }}
          </div>
        </div>

        <div class="table-actions">
          <select
            :value="table.status"
            class="status-select"
            :disabled="loading && loadingTableId === table.id"
            @click.stop
            @change.stop="handleStatusChange(table, $event)"
          >
            <option
              v-for="status in statusFilters"
              :key="status.value"
              :value="status.value"
            >
              {{ status.label }}
            </option>
          </select>
          <button @click.stop="editTable(table)" class="action-btn edit">
            <i class="fas fa-edit"></i>
          </button>
          <button @click.stop="toggleTableStatus(table)" class="action-btn toggle">
            <i :class="getToggleIcon(table.status)"></i>
          </button>
          <button @click.stop="deleteTable(table)" class="action-btn delete">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="filteredTables.length === 0" class="empty-state">
        <i class="fas fa-table"></i>
        <h3>Aucune table trouvée</h3>
        <p>{{ getEmptyStateMessage() }}</p>
        <button @click="openCreateModal" class="btn-primary">
          Créer une table
        </button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ isEditing ? 'Modifier la table' : 'Nouvelle table' }}</h2>
          <button @click="closeModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <form @submit.prevent="saveTable" class="modal-form">
          <div class="form-grid">
            <div class="form-group">
              <label for="table_number">Numéro de table *</label>
              <input
                id="table_number"
                v-model="form.table_number"
                type="text"
                required
                :class="{ 'error': errors.table_number }"
                placeholder="Ex: T01, Table 1"
              />
              <span v-if="errors.table_number" class="error-message">{{ errors.table_number }}</span>
            </div>

            <div class="form-group">
              <label for="name">Nom de la table</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                placeholder="Ex: Table fenêtre, Table terrasse"
              />
            </div>

            <div class="form-group">
              <label for="capacity">Capacité *</label>
              <input
                id="capacity"
                v-model.number="form.capacity"
                type="number"
                min="1"
                max="50"
                required
                :class="{ 'error': errors.capacity }"
              />
              <span v-if="errors.capacity" class="error-message">{{ errors.capacity }}</span>
            </div>

            <div class="form-group">
              <label for="status">Statut *</label>
              <select
                id="status"
                v-model="form.status"
                required
                :class="{ 'error': errors.status }"
              >
                <option value="available">Disponible</option>
                <option value="occupied">Occupée</option>
                <option value="reserved">Réservée</option>
                <option value="out_of_order">Hors service</option>
              </select>
              <span v-if="errors.status" class="error-message">{{ errors.status }}</span>
            </div>
          </div>

          <div class="form-group full-width">
            <label for="description">Description</label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              placeholder="Description ou notes sur cette table..."
            ></textarea>
          </div>

          <div class="form-group full-width">
            <label for="location">Position (optionnel)</label>
            <div class="location-inputs">
              <input
                id="location_x"
                v-model.number="form.location.x"
                type="number"
                placeholder="Coordonnée X"
                min="0"
              />
              <input
                id="location_y"
                v-model.number="form.location.y"
                type="number"
                placeholder="Coordonnée Y"
                min="0"
              />
            </div>
          </div>



          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn-secondary">
              Annuler
            </button>
            <button type="submit" class="btn-primary" :disabled="loading">
              <i v-if="loading" class="fas fa-spinner fa-spin"></i>
              {{ isEditing ? 'Modifier' : 'Créer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click="closeDeleteModal">
      <div class="modal-content small" @click.stop>
        <div class="modal-header">
          <h2>Confirmer la suppression</h2>
          <button @click="closeDeleteModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <p>Êtes-vous sûr de vouloir supprimer la table <strong>{{ tableToDelete?.table_number }}</strong> ?</p>
          <p class="warning">Cette action est irréversible.</p>
        </div>

        <div class="modal-actions">
          <button @click="closeDeleteModal" class="btn-secondary">
            Annuler
          </button>
          <button @click="confirmDelete" class="btn-danger" :disabled="loading">
            <i v-if="loading" class="fas fa-spinner fa-spin"></i>
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { API_BASE_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'
import Profile from './Profile.vue'

const { isAdmin, pointsOfSale, activePos } = useAuth()

const props = defineProps({
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
    { value: 'available', label: 'Disponibles', icon: 'fas fa-check-circle' },
    { value: 'occupied', label: 'Occupées', icon: 'fas fa-users' },
    { value: 'reserved', label: 'Réservées', icon: 'fas fa-calendar-check' },
    { value: 'out_of_order', label: 'Hors service', icon: 'fas fa-wrench' }
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
            console.warn('Point de vente actif non configuré')
            tables.value = []
            return
        }

        const token = localStorage.getItem('token')
        const response = await fetch(`${API_BASE_URL}/tables?point_of_sale_id=${posId}`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        })

        if (response.ok) {
            const payload = await response.json()
            tables.value = normalizeTables(payload)
            refreshStatisticsFromTables()
        } else {
            console.error('Erreur lors du chargement des tables')
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
        disponible: 'available',
        available: 'available',
        occupee: 'occupied',
        occupée: 'occupied',
        occupied: 'occupied',
        reservee: 'reserved',
        réservée: 'reserved',
        reserved: 'reserved',
        hors_service: 'out_of_order',
        horsservice: 'out_of_order',
        out_of_order: 'out_of_order',
        outoforder: 'out_of_order'
    }

    return aliases[normalized] || normalized
}

const normalizeLocation = (location) => {
    if (!location || typeof location !== 'object') {
        return { x: null, y: null }
    }

    const x = Number(location.x ?? location.pos_x ?? location.left ?? null)
    const y = Number(location.y ?? location.pos_y ?? location.top ?? null)

    return {
        x: Number.isFinite(x) ? x : null,
        y: Number.isFinite(y) ? y : null
    }
}

const prepareLocationPayload = (location) => {
    const normalized = normalizeLocation(location)
    if (normalized.x === null && normalized.y === null) {
        return null
    }

    return normalized
}

const normalizeStatistics = (payload) => {
    const source = payload?.data && !Array.isArray(payload.data) ? payload.data : payload
    const totalTables = Number(source?.total_tables ?? source?.total ?? source?.tables_count ?? 0) || 0
    const availableTables = Number(source?.available_tables ?? source?.available ?? 0) || 0
    const occupiedTables = Number(source?.occupied_tables ?? source?.occupied ?? 0) || 0
    const reservedTables = Number(source?.reserved_tables ?? source?.reserved ?? 0) || 0
    const outOfOrderTables = Number(source?.out_of_order_tables ?? source?.out_of_order ?? 0) || 0
    const occupancyRate = Number(source?.occupancy_rate ?? source?.occupation_rate ?? 0) || 0

    return {
        total_tables: totalTables,
        available_tables: availableTables,
        occupied_tables: occupiedTables,
        reserved_tables: reservedTables,
        out_of_order_tables: outOfOrderTables,
        occupancy_rate: occupancyRate
    }
}

const refreshStatisticsFromTables = () => {
    const total = tables.value.length
    const available = tables.value.filter((table) => table.status === 'available').length
    const occupied = tables.value.filter((table) => table.status === 'occupied').length
    const reserved = tables.value.filter((table) => table.status === 'reserved').length
    const outOfOrder = tables.value.filter((table) => table.status === 'out_of_order').length

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
    if (location.x === null && location.y === null) {
        return ''
    }

    return `X: ${location.x ?? '-'} | Y: ${location.y ?? '-'}`
}

const toggleStatusFilter = (status) => {
    const index = activeFilters.value.indexOf(status)
    if (index > -1) {
        activeFilters.value.splice(index, 1)
    } else {
        activeFilters.value.push(status)
    }
}

const getTableCardClass = (table) => {
    return {
        'table-card': true,
        'available': table.status === 'available',
        'occupied': table.status === 'occupied',
        'reserved': table.status === 'reserved',
        'out-of-order': table.status === 'out_of_order'
    }
}

const getStatusIcon = (status) => {
    const icons = {
        'available': 'fas fa-check-circle',
        'occupied': 'fas fa-users',
        'reserved': 'fas fa-calendar-check',
        'out_of_order': 'fas fa-wrench'
    }
    return icons[status] || 'fas fa-question-circle'
}

const getStatusText = (status) => {
    const texts = {
        'available': 'Disponible',
        'occupied': 'Occupée',
        'reserved': 'Réservée',
        'out_of_order': 'Hors service'
    }
    return texts[status] || 'Inconnu'
}

const getToggleIcon = (status) => {
    return status === 'available' ? 'fas fa-pause' : 'fas fa-play'
}

const selectTable = (table) => {
    console.log('Table sélectionnée:', table)
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
        
        if (!posId) {
            alert('Point de vente actif non défini')
            return
        }

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
        if (location) {
            formData.location = location
        }

        const response = await fetch(url, {
            method,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })

        const rawText = await response.text()
        let data = {}

        try {
            data = rawText ? JSON.parse(rawText) : {}
        } catch (parseError) {
            data = { message: rawText }
        }

        if (response.ok) {
            await loadTables()
            closeModal()
        } else {
            if (response.status === 422) {
                errors.value = data.errors || {}
            } else {
                console.error('Erreur creation/modification table:', response.status, data)
                alert(data.error || data.message || 'Erreur lors de la sauvegarde')
            }
        }
    } catch (error) {
        console.error('Erreur:', error)
        alert('Erreur de connexion')
    } finally {
        loading.value = false
    }
}

const toggleTableStatus = (table) => {
    const newStatus = table.status === 'available' ? 'out_of_order' : 'available'
    updateTableStatus(table.id, newStatus)
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
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ status })
        })

        if (response.ok) {
            await loadTables()
        } else {
            const data = await response.json()
            alert(data.error || 'Erreur lors de la mise à jour du statut')
        }
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
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        })

        if (response.ok) {
            await loadTables()
            closeDeleteModal()
        } else {
            const data = await response.json()
            alert(data.error || 'Erreur lors de la suppression')
        }
    } catch (error) {
        console.error('Erreur:', error)
        alert('Erreur de connexion')
    } finally {
        loading.value = false
    }
}

const getEmptyStateMessage = () => {
    if (searchQuery.value) {
        return 'Aucune table ne correspond à votre recherche.'
    }
    if (activeFilters.value.length > 0) {
        return 'Aucune table ne correspond aux filtres sélectionnés.'
    }
    return 'Commencez par créer votre première table.'
}

onMounted(async () => {
    await loadTables()
})
</script>
</script>

<style scoped>
.table-management {
  min-height: 100vh;
  background: #f8fafc;
  padding: 80px 20px 20px;
}

.management-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding: 1.5rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header-content .page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.header-content .page-subtitle {
  color: #64748b;
  margin: 0.5rem 0 0 0;
}

.header-actions .btn-primary {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.header-actions .btn-primary:hover {
  background: #2563eb;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 1rem;
}

.stat-card.available {
  border-left: 4px solid #10b981;
}

.stat-card.occupied {
  border-left: 4px solid #f59e0b;
}

.stat-card.rate {
  border-left: 4px solid #8b5cf6;
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: #64748b;
}

.stat-card.available .stat-icon {
  background: #d1fae5;
  color: #10b981;
}

.stat-card.occupied .stat-icon {
  background: #fef3c7;
  color: #f59e0b;
}

.stat-card.rate .stat-icon {
  background: #f3e8ff;
  color: #8b5cf6;
}

.stat-content h3 {
  font-size: 2rem;
  font-weight: 700;
  margin: 0;
  color: #1e293b;
}

.stat-content p {
  color: #64748b;
  margin: 0;
  font-size: 0.875rem;
}

.filters-section {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.search-box {
  position: relative;
  margin-bottom: 1rem;
}

.search-box i {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
}

.search-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.filter-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border: 1px solid #e2e8f0;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.875rem;
}

.filter-btn:hover {
  background: #f8fafc;
}

.filter-btn.active {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.tables-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  cursor: pointer;
  transition: all 0.2s;
}

.table-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.table-card.available {
  border-left: 4px solid #10b981;
}

.table-card.occupied {
  border-left: 4px solid #f59e0b;
}

.table-card.reserved {
  border-left: 4px solid #3b82f6;
}

.table-card.out-of-order {
  border-left: 4px solid #ef4444;
}

.table-header {
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f1f5f9;
}

.table-number {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
}

.table-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.table-status.available {
  background: #d1fae5;
  color: #065f46;
}

.table-status.occupied {
  background: #fef3c7;
  color: #92400e;
}

.table-status.reserved {
  background: #dbeafe;
  color: #1e40af;
}

.table-status.out_of_order {
  background: #fecaca;
  color: #991b1b;
}

.table-body {
  padding: 1rem;
}

.table-name {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.table-capacity {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #64748b;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.table-description {
  color: #64748b;
  font-size: 0.875rem;
  line-height: 1.4;
}

.table-location {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #475569;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.table-actions {
  padding: 1rem;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 0.5rem;
  border-top: 1px solid #f1f5f9;
}

.status-select {
  flex: 1;
  min-width: 0;
  height: 32px;
  border: 1px solid #dbe3ef;
  border-radius: 6px;
  padding: 0 0.75rem;
  background: white;
  color: #1e293b;
  font-size: 0.8rem;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.action-btn.edit {
  background: #dbeafe;
  color: #3b82f6;
}

.action-btn.edit:hover {
  background: #3b82f6;
  color: white;
}

.action-btn.toggle {
  background: #f3e8ff;
  color: #8b5cf6;
}

.action-btn.toggle:hover {
  background: #8b5cf6;
  color: white;
}

.action-btn.delete {
  background: #fecaca;
  color: #ef4444;
}

.action-btn.delete:hover {
  background: #ef4444;
  color: white;
}

.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 3rem 1rem;
  color: #64748b;
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  color: #cbd5e1;
}

.empty-state h3 {
  font-size: 1.25rem;
  margin-bottom: 0.5rem;
}

.empty-state p {
  margin-bottom: 1.5rem;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content.small {
  max-width: 400px;
}

.modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  margin: 0;
  color: #1e293b;
  font-size: 1.25rem;
  font-weight: 600;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 4px;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.modal-form {
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group input.error,
.form-group select.error {
  border-color: #ef4444;
}

.error-message {
  color: #ef4444;
  font-size: 0.75rem;
}

.location-inputs {
  display: flex;
  gap: 0.5rem;
}

.location-inputs input {
  flex: 1;
}

.modal-body {
  padding: 1.5rem;
  text-align: center;
}

.modal-body .warning {
  color: #f59e0b;
  margin-top: 1rem;
}

.modal-actions {
  padding: 1.5rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  border-top: 1px solid #e2e8f0;
}

.btn-secondary {
  background: #f8fafc;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-secondary:hover {
  background: #f1f5f9;
}

.btn-danger {
  background: #ef4444;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-danger:hover {
  background: #dc2626;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .table-management {
    padding: 70px 10px 10px;
  }

  .management-header {
    flex-direction: column;
    gap: 1rem;
    text-align: center;
  }

  .header-content .page-title {
    font-size: 1.5rem;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .tables-grid {
    grid-template-columns: 1fr;
  }

  .filter-buttons {
    justify-content: center;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .modal-content {
    margin: 1rem;
  }
}
</style>
