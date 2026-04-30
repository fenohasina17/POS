<template>

  <div class="table-selector">
    <div class="selector-header">
      <h3>Sélectionner une table</h3>
      <button @click="$emit('close')" class="close-btn">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="selector-content">
      <div class="tables-section">
        <h4 class="section-title">
          <i class="fas fa-th"></i>
          Toutes les tables ({{ tables.length }})
        </h4>
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
                    {{ getStatusText(table.status) }}
                  </div>
                </td>
                <td class="table-pending-cell">
                  <div v-if="getPendingCount(table.id) > 0" class="pending-badge">
                    {{ getPendingCount(table.id) }} en attente
                  </div>
                  <div v-else class="no-pending">Aucune</div>
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

    <!-- Quick Actions -->
    <div class="selector-actions">
      <button @click="$emit('close')" class="btn-secondary">
        Fermer
      </button>
    </div>
  </div>
</template>

<script>
import { API_BASE_URL } from '@/utils/api'

export default {
  name: 'TableSelector',
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
      loading: false
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
        this.loadTables()
      }
    }
  },
  mounted() {
    if (this.isOpen) {
      this.loadTables()
    }
  },
  methods: {
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

    async loadTables() {
      this.loading = true
      try {
        const token = localStorage.getItem('token')
        const response = await fetch(`${API_BASE_URL}/tables`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        })

        if (!response.ok) {
          console.error('Erreur lors du chargement des tables')
          this.tables = []
          return
        }

        const payload = await response.json()
        const normalizedTables = Array.isArray(payload)
          ? payload
          : Array.isArray(payload?.data)
            ? payload.data
            : Array.isArray(payload?.data?.data)
              ? payload.data.data
              : []

        this.tables = normalizedTables.map((table) => ({
          ...table,
          status: this.normalizeStatus(table.status)
        }))

        // Réinitialiser les commandes en attente avant de recharger les données
        this.pendingOrders = {}

        await Promise.all(
          this.tables.map((table) => this.loadPendingOrdersForTable(table.id))
        )
      } catch (error) {
        console.error('Erreur:', error)
      } finally {
        this.loading = false
      }
    },

    selectTable(table) {
      this.$emit('table-selected', table)
    },

    async loadPendingOrdersForTable(tableId) {
      try {
        const token = localStorage.getItem('token')
        const response = await fetch(`${API_BASE_URL}/tables/${tableId}/pending-orders`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        })

        if (response.ok) {
          const orders = await response.json()
          const normalizedOrders = Array.isArray(orders)
            ? orders
            : Array.isArray(orders?.data)
              ? orders.data
              : Array.isArray(orders?.data?.data)
                ? orders.data.data
                : []

          this.pendingOrders = {
            ...this.pendingOrders,
            [tableId]: normalizedOrders
          }
          return
        }

        this.pendingOrders = {
          ...this.pendingOrders,
          [tableId]: []
        }
      } catch (error) {
        console.error('Erreur lors du chargement des commandes en attente:', error)
        this.pendingOrders = {
          ...this.pendingOrders,
          [tableId]: []
        }
      }
    },

    getPendingCount(tableId) {
      return this.pendingOrders[tableId] ? this.pendingOrders[tableId].length : 0
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
.table-selector {
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  max-width: 800px;
  width: 90vw;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}

.selector-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.selector-header h3 {
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

.selector-content {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.tables-section {
  margin-bottom: 2rem;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #374151;
  font-size: 1rem;
  font-weight: 600;
  margin: 0 0 1rem 0;
}

.tables-container {
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.tables-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.tables-table th {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 1rem 0.75rem;
  text-align: left;
  font-weight: 600;
  color: #374151;
  border-bottom: 2px solid #e2e8f0;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.tables-table th:first-child {
  border-top-left-radius: 8px;
}

.tables-table th:last-child {
  border-top-right-radius: 8px;
}

.tables-table td {
  padding: 1rem 0.75rem;
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

.table-row.out-of-order {
  cursor: not-allowed;
  opacity: 0.6;
}

.table-row.out-of-order:hover {
  background: #f8fafc;
  opacity: 0.4;
}

.table-number-cell,
.table-name-cell,
.table-capacity-cell,
.table-status-cell,
.table-pending-cell,
.table-actions-cell {
  position: relative;
}

.table-number {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  text-align: center;
  padding: 0.5rem;
  border-radius: 6px;
  background: rgba(59, 130, 246, 0.1);
  border: 2px solid rgba(59, 130, 246, 0.2);
}

.table-name {
  font-size: 0.875rem;
  color: #374151;
  font-weight: 500;
  padding: 0.25rem 0;
}

.table-capacity {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #64748b;
  padding: 0.5rem;
  border-radius: 6px;
  background: rgba(107, 114, 128, 0.1);
  border: 1px solid rgba(107, 114, 128, 0.2);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-badge.available {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
  border: 1px solid #10b981;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.status-badge.occupied {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  color: #92400e;
  border: 1px solid #f59e0b;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
}

.status-badge.reserved {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: #1e40af;
  border: 1px solid #3b82f6;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}

.status-badge.out_of_order {
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  color: #dc2626;
  border: 1px solid #ef4444;
  opacity: 0.8;
}

.pending-badge {
  background: #f59e0b;
  color: white;
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 12px;
  font-weight: 600;
  text-align: center;
  display: inline-block;
  box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.no-pending {
  color: #9ca3af;
  font-size: 0.75rem;
  font-style: italic;
}

.btn-select-table {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.btn-select-table:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

.btn-disabled {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: not-allowed;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  opacity: 0.6;
}

.selector-actions {
  padding: 1rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-secondary {
  background: #f8fafc;
  color: #374151;
  border: 1px solid #d1d5db;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-secondary:hover {
  background: #f1f5f9;
}

@media (max-width: 768px) {
  .table-selector {
    width: 95vw;
    max-height: 90vh;
  }

  .tables-container {
    font-size: 0.875rem;
  }

  .tables-table th,
  .tables-table td {
    padding: 0.75rem 0.5rem;
  }

  .table-number {
    font-size: 1rem;
    padding: 0.25rem;
  }

  .table-name {
    font-size: 0.8rem;
  }

  .table-capacity {
    font-size: 0.75rem;
    padding: 0.25rem;
  }

  .status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
  }

  .btn-select-table,
  .btn-disabled {
    font-size: 0.7rem;
    padding: 0.4rem 0.8rem;
  }

  .pending-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
  }
}
</style>
