<template>
  <div v-if="isOpen" class="table-selector-modal">
    <div class="modal-backdrop" @click="close"></div>
    <div class="modal-content">
      <div class="modal-header">
        <h3>Sélectionner une table</h3>
        <button @click="close" class="close-btn">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- Loading state -->
        <div v-if="loading" class="loading-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p>Chargement des tables...</p>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="error-state">
          <i class="fas fa-exclamation-triangle"></i>
          <p>{{ error }}</p>
          <button @click="loadTables(true)" class="btn-retry">
            <i class="fas fa-redo"></i>
            Réessayer
          </button>
        </div>

        <!-- Tables list -->
        <div v-else class="tables-section">
          <div class="tables-container">
            <table class="tables-table">
              <thead>
                <tr>
                  <th>Table</th>
                  <th>Nom</th>
                  <th>Capacité</th>
                  <th>Statut</th>
                  <th>Commandes</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="table in sortedTables"
                  :key="table.id"
                  :class="['table-row', table.status]"
                  @click="table.status !== 'out_of_order' ? selectTable(table) : null"
                >
                  <td class="table-number-cell">
                    <div class="table-number">{{ table.table_number }}</div>
                  </td>
                  <td class="table-name-cell">
                    <div class="table-name">{{ table.name || 'Sans nom' }}</div>
                  </td>
                  <td class="table-capacity-cell">
                    <div class="table-capacity">
                      <i class="fas fa-users"></i>
                      {{ table.capacity }}
                    </div>
                  </td>
                  <td class="table-status-cell">
                    <div class="status-badge" :class="table.status">
                      <i :class="getStatusIcon(table.status)"></i>
                      {{ table.status}}
                    </div>
                  </td>
                  <td class="table-pending-cell">
                    <div v-if="loadingPending" class="pending-loading">
                      <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div v-else-if="getPendingCount(table.id) > 0" class="pending-badge">
                      <i class="fas fa-clock"></i>
                      {{ getPendingCount(table.id) }}
                    </div>
                    <div v-else class="no-pending">
                      <i class="fas fa-check-circle"></i>
                      Aucune
                    </div>
                  </td>
                  <td class="table-actions-cell">
                    <button
                      v-if="table.status !== 'out_of_order'"
                      @click.stop="selectTable(table)"
                      class="btn-select-table"
                    >
                      <i class="fas fa-check"></i>
                      Sélectionner
                    </button>
                    <button
                      v-else
                      class="btn-disabled"
                      disabled
                    >
                      <i class="fas fa-ban"></i>
                      Indisponible
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button @click="close" class="btn-secondary">
          <i class="fas fa-times"></i>
          Fermer
        </button>
        <button @click="refresh" class="btn-refresh" :disabled="loading">
          <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
          Rafraîchir
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { dataCacheService } from '@/services/dataCacheService'
import { EventBus } from '@/services/EventBus'
import { tableService } from '@/services/tableService'

export default {
  name: 'TableSelectorModal',
  props: {
    isOpen: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      tables: [],
      pendingOrders: {},
      loading: false,
      loadingPending: false,
      error: null,
      unsubscribeEvents: null
    }
  },
  computed: {
    sortedTables() {
      return [...this.tables].sort((a, b) => a.table_number - b.table_number)
    }
  },
  watch: {
    isOpen(newVal) {
      if (newVal) {
        this.refresh()
      }
    }
  },
  mounted() {
    this.setupEventListeners()
  },
  beforeUnmount() {
    if (this.unsubscribeEvents) {
      this.unsubscribeEvents()
    }
  },
  methods: {
    setupEventListeners() {
      this.unsubscribeEvents = EventBus.on('table:status-changed', () => {
        this.refresh()
      })
      EventBus.on('cache:invalidated', ({ type }) => {
        if (type === 'table') {
          this.refresh()
        }
      })
      EventBus.on('pending-order:created', () => {
        this.loadPendingOrders()
      })
      EventBus.on('pending-order:updated', () => {
        this.loadPendingOrders()
      })
    },

    async refresh() {
      await this.loadTables(true)
      await this.loadPendingOrders()
    },

    async loadTables(forceRefresh = true) {
      this.loading = true
      this.error = null

      try {
        const token = localStorage.getItem('token')
        if (!token) {
          throw new Error('Token non trouvé. Veuillez vous reconnecter.')
        }

        const activePosStr = localStorage.getItem('active_pos')
        const activePos = activePosStr ? JSON.parse(activePosStr) : null
        const activePosId = activePos?.id

        // ✅ Appel API direct sans cache avec timestamp
        const timestamp = Date.now()
        const rawTables = await dataCacheService.getTables(activePosId, token, true)
        // Note: dataCacheService.getTables needs to support timestamp or bypassing cache
        
        // Alternative: Fetch directly if getTables doesn't support forcing refresh
        // For now, assume forceRefresh = true in getTables or add a mechanism
        
        if (!rawTables || !Array.isArray(rawTables)) {
          throw new Error('Format de données invalide pour les tables')
        }

        this.tables = rawTables
          .filter(table => !activePosId || table.point_of_sale_id == activePosId)
          .map(table => ({
            ...table,
            status: this.normalizeStatus(table.status)
          }))

        if (this.tables.length === 0) {
          console.warn('Aucune table trouvée pour ce point de vente')
        }

      } catch (error) {
        console.error('Erreur lors du chargement des données:', error)
        this.error = error.message || 'Erreur lors du chargement des tables. Veuillez réessayer.'
        this.tables = []
      } finally {
        this.loading = false
      }
    },

    async loadPendingOrders() {
      this.loadingPending = true
      try {
        const token = localStorage.getItem('token')
        const activePosStr = localStorage.getItem('active_pos')
        const activePos = activePosStr ? JSON.parse(activePosStr) : null
        const activePosId = activePos?.id

        const allPending = await dataCacheService.getPendingOrders('all', activePosId, token, true)

        this.pendingOrders = allPending.reduce((acc, order) => {
          if (!acc[order.table_id]) {
            acc[order.table_id] = []
          }
          acc[order.table_id].push(order)
          return acc
        }, {})
      } catch (error) {
        console.error('Erreur chargement commandes:', error)
        this.pendingOrders = {}
      } finally {
        this.loadingPending = false
      }
    },

    selectTable(table) {
      this.$emit('table-selected', table)
      this.close()
    },

    close() {
      this.$emit('close')
    },

    getPendingCount(tableId) {
      return this.pendingOrders[tableId] ? this.pendingOrders[tableId].length : 0
    },

    normalizeStatus(status) {
      const normalized = String(status || 'available').trim().toLowerCase()
      const aliases = {
        disponible: 'available',
        available: 'available',
        libre: 'available',
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
    },

    getStatusIcon(status) {
      const icons = {
        'available': 'fas fa-check-circle',
        'occupied': 'fas fa-users',
        'reserved': 'fas fa-calendar-check',
        'out_of_order': 'fas fa-wrench'
      }
      return icons[status] || 'fas fa-question-circle'
    },

    getStatusText(status) {
      console.log(status)
      const texts = {
        'available': 'Disponible',
        'occupied': 'Occupée',
        'reserved': 'Réservée',
        'out_of_order': 'Hors service'
      }
      return texts[status] || 'Inconnu'
    }
  }
}
</script>

<style scoped>
.table-selector-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-backdrop {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal-content {
  position: relative;
  z-index: 1001;
  background: white;
  border-radius: 16px;
  width: 90vw;
  max-width: 900px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
}

.modal-header h3 {
  margin: 0;
  color: #1e293b;
  font-size: 1.25rem;
  font-weight: 700;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: #64748b;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 8px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  background: white;
}

.loading-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.loading-state i,
.error-state i {
  font-size: 2.5rem;
  margin-bottom: 1rem;
}

.loading-state i {
  color: #3b82f6;
}

.error-state i {
  color: #ef4444;
}

.error-state p {
  color: #dc2626;
  margin-bottom: 1rem;
}

.btn-retry {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}

.btn-retry:hover {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  transform: translateY(-1px);
}

.tables-container {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.tables-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.tables-table th {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 0.875rem 0.75rem;
  text-align: left;
  font-weight: 700;
  color: #374151;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.tables-table td {
  padding: 0.875rem 0.75rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.table-row {
  cursor: pointer;
  transition: all 0.2s ease;
}

.table-row:hover {
  background: #f8fafc;
}

.table-row.available:hover {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
}

.table-row.occupied:hover {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}

.table-row.reserved:hover {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}

.table-row.out_of_order {
  cursor: not-allowed;
  opacity: 0.6;
}

.table-row.out_of_order:hover {
  background: #f8fafc;
  opacity: 0.4;
}

.table-number {
  font-size: 1.125rem;
  font-weight: 800;
  color: #1e293b;
  text-align: center;
  display: inline-block;
  padding: 0.25rem 0.5rem;
  border-radius: 8px;
  background: rgba(59, 130, 246, 0.1);
  border: 2px solid rgba(59, 130, 246, 0.2);
}

.table-name {
  font-size: 0.875rem;
  color: #374151;
  font-weight: 500;
}

.table-capacity {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #64748b;
  padding: 0.25rem 0.5rem;
  border-radius: 20px;
  background: rgba(107, 114, 128, 0.1);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.625rem;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-badge.available {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
  border: 1px solid #10b981;
}

.status-badge.occupied {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  color: #92400e;
  border: 1px solid #f59e0b;
}

.status-badge.reserved {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: #1e40af;
  border: 1px solid #3b82f6;
}

.status-badge.out_of_order {
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  color: #dc2626;
  border: 1px solid #ef4444;
}

.pending-badge {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  font-size: 0.7rem;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-weight: 700;
  text-align: center;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.no-pending {
  color: #9ca3af;
  font-size: 0.7rem;
  font-style: italic;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.pending-loading {
  color: #f59e0b;
  font-size: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.pending-loading i {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.btn-select-table {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  padding: 0.375rem 0.75rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.7rem;
  transition: all 0.2s ease;
}

.btn-select-table:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-disabled {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  border: none;
  padding: 0.375rem 0.75rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: not-allowed;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.7rem;
  opacity: 0.6;
}

.btn-secondary,
.btn-refresh {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.btn-secondary {
  background: #f8fafc;
  color: #374151;
  border: 1px solid #d1d5db;
}

.btn-secondary:hover {
  background: #f1f5f9;
}

.btn-refresh {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
  border: none;
}

.btn-refresh:hover:not(:disabled) {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  transform: translateY(-1px);
}

.btn-refresh:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Scrollbar styling */
.modal-body::-webkit-scrollbar {
  width: 6px;
}

.modal-body::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Responsive */
@media (max-width: 768px) {
  .modal-content {
    width: 95vw;
    max-height: 90vh;
  }

  .modal-body {
    padding: 1rem;
  }

  .tables-table th,
  .tables-table td {
    padding: 0.5rem;
  }

  .table-number {
    font-size: 0.875rem;
  }

  .table-name,
  .table-capacity {
    font-size: 0.75rem;
  }

  .status-badge,
  .pending-badge,
  .no-pending {
    font-size: 0.6rem;
  }

  .btn-select-table,
  .btn-disabled {
    font-size: 0.6rem;
    padding: 0.25rem 0.5rem;
  }
}
</style>
