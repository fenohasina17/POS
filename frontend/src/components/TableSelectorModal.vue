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
          <button @click="refresh" class="btn-retry">
            <i class="fas fa-redo"></i>
            Réessayer
          </button>
        </div>

        <!-- Tables grid -->
        <div v-else class="tables-section">
          <div class="tables-grid">
            <button
              v-for="table in sortedTables"
              :key="table.id"
              :class="[
                'table-box', 
                table.status,
                { 'selected-table': selectedTableId === table.id },
                { 'locked': isTableLockedByOther(table) }
              ]"
              :disabled="isTableLockedByOther(table) || table.status === 'out_of_order'"
              @click="selectTable(table)"
            >
              <div class="flex flex-col items-center">
                  <span class="table-number">{{ table.table_number }}</span>
                  <span v-if="isTableLockedByOther(table)" class="text-[8px] truncate max-w-full">
                    {{ table.locked_by_session?.user?.name || 'Occupé' }}
                  </span>
              </div>
            </button>
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

export default {
  name: 'TableSelectorModal',
  props: {
    isOpen: {
      type: Boolean,
      default: false
    },
    currentSessionId: {
      type: [Number, String],
      default: null
    }
  },
  data() {
    return {
      tables: [],
      loading: false,
      error: null,
      unsubscribeEvents: null,
      selectedTableId: null
    }
  },
  computed: {
    sortedTables() {
      return [...this.tables].sort((a, b) => parseInt(a.table_number) - parseInt(b.table_number))
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
    this.initEcho()
  },
  beforeUnmount() {
    if (this.unsubscribeEvents) {
      this.unsubscribeEvents()
    }
    if (window.Echo) {
      window.Echo.leave('tables')
    }
  },
  methods: {
    initEcho() {
        if(window.Echo) {
            window.Echo.channel('tables')
            .listen('TableLockUpdated', () => {
                console.log('📡 Événement de verrouillage reçu, rafraîchissement...')
                this.refresh()
            })
        }
    },
    setupEventListeners() {
  // ...

      this.unsubscribeEvents = EventBus.on('table:status-changed', () => {
        this.refresh()
      })
    },

    async refresh() {
      await this.loadTables(true)
    },

    async loadTables(forceRefresh = true) {
      this.loading = true
      this.error = null

      try {
        const token = localStorage.getItem('token')
        const activePosStr = localStorage.getItem('active_pos')
        const activePos = activePosStr ? JSON.parse(activePosStr) : null
        
        const rawTables = await dataCacheService.getTables(activePos?.id, token, true)
        
        console.log('DEBUG: Received raw tables:', rawTables);
        
        this.tables = (Array.isArray(rawTables) ? rawTables : [])
          .filter(table => !activePos?.id || table.point_of_sale_id == activePos.id)
          .map(table => ({
            ...table,
            status: this.normalizeStatus(table.status)
          }))
          
        if (this.tables.length > 0) {
            const firstAvailable = this.tables.find(t => t.status === 'available')
            this.selectedTableId = firstAvailable ? firstAvailable.id : null
        }

      } catch (error) {
        this.error = error.message || 'Erreur lors du chargement des tables.'
        this.tables = []
      } finally {
        this.loading = false
      }
    },

    selectTable(table) {
      this.selectedTableId = table.id
      this.$emit('table-selected', table)
    },

    isTableLockedByOther(table) {
        // Log pour comprendre la comparaison
        if (!this._alerted) {
            alert(`DEBUG Table ${table.table_number}: Status=${table.status}, TableLockedByID=${table.locked_by_session_id}, MySessionID=${this.currentSessionId}`);
            this._alerted = true;
        }
        
        const isLocked = table.status === 'occupied' && 
               table.locked_by_session_id && 
               String(table.locked_by_session_id) !== String(this.currentSessionId);
               
        return isLocked;
    },

    close() {
      this.$emit('close')
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
        out_of_order: 'out_of_order'
      }
      return aliases[normalized] || normalized
    }
  }
}
</script>

<style scoped>
.table-selector-modal {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-backdrop {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
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
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
  padding: 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-body {
  padding: 1rem;
}

.tables-grid {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  gap: 0.5rem;
  padding: 0.5rem;
}

.table-box {
  aspect-ratio: 1 / 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-weight: 800;
  color: white;
  transition: all 0.2s ease;
}

.table-box:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.table-box.available { background-color: #22c55e; }
.table-box.occupied { background-color: #ef4444; }
.table-box.reserved { background-color: #f59e0b; }
.table-box.out_of_order { background-color: #64748b; }

.table-box.selected-table {
  outline: 4px solid #4f46e5; /* Bleu Indigo */
  outline-offset: 2px;
  transform: scale(1.1);
  box-shadow: 0 0 20px rgba(79, 70, 229, 0.5);
}

.table-box.locked {
    background-color: #94a3b8 !important;
    cursor: not-allowed;
    opacity: 0.6;
}

.table-number {
  font-size: 0.875rem;
}

.modal-footer {
  padding: 1rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.btn-secondary, .btn-refresh {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}
.btn-refresh { background: #3b82f6; color: white; border: none; }
</style>
