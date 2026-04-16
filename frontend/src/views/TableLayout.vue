<template>
  <div class="table-layout">
    <div class="layout-header">
      <h3>Plan de salle</h3>
      <div class="layout-controls">
        <button @click="toggleViewMode" class="control-btn">
          <i :class="viewMode === 'grid' ? 'fas fa-th' : 'fas fa-list'"></i>
        </button>
        <button @click="refreshLayout" class="control-btn">
          <i class="fas fa-sync-alt"></i>
        </button>
      </div>
    </div>

    <!-- Grid View -->
    <div v-if="viewMode === 'grid'" class="layout-grid">
      <div
        v-for="table in tables"
        :key="table.id"
        class="table-node"
        :class="getTableNodeClass(table)"
        :style="getTablePosition(table)"
        @click="selectTable(table)"
      >
        <div class="table-info">
          <div class="table-number">{{ table.table_number }}</div>
          <div class="table-status">
            <i :class="getStatusIcon(table.status)"></i>
          </div>
        </div>
        <div class="table-tooltip" v-if="hoveredTable === table.id">
          <div class="tooltip-content">
            <strong>{{ table.table_number }}</strong>
            <div v-if="table.name">{{ table.name }}</div>
            <div>Capacité: {{ table.capacity }} personnes</div>
            <div v-if="table.description">{{ table.description }}</div>
            <div class="tooltip-status" :class="table.status">
              {{ getStatusText(table.status) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- List View -->
    <div v-else class="layout-list">
      <div
        v-for="table in tables"
        :key="table.id"
        class="table-list-item"
        :class="getTableNodeClass(table)"
        @click="selectTable(table)"
      >
        <div class="list-item-content">
          <div class="table-number">{{ table.table_number }}</div>
          <div class="table-details">
            <div class="table-name">{{ table.name || 'Sans nom' }}</div>
            <div class="table-capacity">
              <i class="fas fa-users"></i>
              {{ table.capacity }} personnes
            </div>
          </div>
          <div class="table-status" :class="table.status">
            <i :class="getStatusIcon(table.status)"></i>
            {{ getStatusText(table.status) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="layout-legend">
      <div class="legend-item">
        <div class="legend-color available"></div>
        <span>Disponible</span>
      </div>
      <div class="legend-item">
        <div class="legend-color occupied"></div>
        <span>Occupée</span>
      </div>
      <div class="legend-item">
        <div class="legend-color reserved"></div>
        <span>Réservée</span>
      </div>
      <div class="legend-item">
        <div class="legend-color out-of-order"></div>
        <span>Hors service</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TableLayout',
  props: {
    tables: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      viewMode: 'grid', // 'grid' or 'list'
      hoveredTable: null
    }
  },
  methods: {
    getTableNodeClass(table) {
      return {
        'table-node': true,
        'available': table.status === 'available',
        'occupied': table.status === 'occupied',
        'reserved': table.status === 'reserved',
        'out-of-order': table.status === 'out_of_order'
      }
    },

    getTablePosition(table) {
      // Si la table a des coordonnées, les utiliser
      if (table.location && table.location.x !== null && table.location.y !== null) {
        return {
          left: `${table.location.x}px`,
          top: `${table.location.y}px`
        }
      }

      // Sinon, position automatique en grid
      const index = this.tables.findIndex(t => t.id === table.id)
      const cols = Math.ceil(Math.sqrt(this.tables.length))
      const x = (index % cols) * 120 + 20
      const y = Math.floor(index / cols) * 120 + 20

      return {
        left: `${x}px`,
        top: `${y}px`
      }
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
    },

    selectTable(table) {
      this.$emit('table-selected', table)
    },

    toggleViewMode() {
      this.viewMode = this.viewMode === 'grid' ? 'list' : 'grid'
    },

    refreshLayout() {
      this.$emit('refresh')
    }
  }
}
</script>

<style scoped>
.table-layout {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.layout-header {
  padding: 1rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.layout-header h3 {
  margin: 0;
  color: #1e293b;
  font-size: 1.125rem;
  font-weight: 600;
}

.layout-controls {
  display: flex;
  gap: 0.5rem;
}

.control-btn {
  width: 32px;
  height: 32px;
  border: 1px solid #d1d5db;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.control-btn:hover {
  background: #f8fafc;
  border-color: #9ca3af;
}

.layout-grid {
  position: relative;
  width: 100%;
  height: 400px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  margin: 1rem;
  border-radius: 8px;
  overflow: hidden;
}

.table-node {
  position: absolute;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  border: 3px solid;
}

.table-node.available {
  border-color: #10b981;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.table-node.available:hover {
  border-color: #059669;
  background: linear-gradient(135deg, #a7f3d0 0%, #6ee7b7 100%);
  transform: scale(1.1);
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.table-node.available::before {
  content: '';
  position: absolute;
  top: -2px;
  left: -2px;
  right: -2px;
  bottom: -2px;
  background: linear-gradient(45deg, #10b981, #34d399, #10b981);
  border-radius: 50%;
  z-index: -1;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.table-node.available:hover::before {
  opacity: 0.3;
}

.table-node.occupied {
  border-color: #f59e0b;
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  color: #92400e;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
  animation: pulse-occupied 2s infinite;
}

.table-node.occupied:hover {
  border-color: #d97706;
  background: linear-gradient(135deg, #fde68a 0%, #fcd34d 100%);
  transform: scale(1.1);
  box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
}

@keyframes pulse-occupied {
  0%, 100% {
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
  }
  50% {
    box-shadow: 0 4px 20px rgba(245, 158, 11, 0.4);
  }
}

.table-node.reserved {
  border-color: #3b82f6;
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  color: #1e40af;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.table-node.reserved:hover {
  border-color: #2563eb;
  background: linear-gradient(135deg, #bfdbfe 0%, #93c5fd 100%);
  transform: scale(1.1);
  box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
}

.table-node.reserved::after {
  content: '⏰';
  position: absolute;
  top: -8px;
  right: -8px;
  background: #3b82f6;
  color: white;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.table-node.out-of-order {
  border-color: #ef4444;
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  color: #dc2626;
  cursor: not-allowed;
  opacity: 0.7;
  position: relative;
}

.table-node.out-of-order::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(45deg);
  width: 60%;
  height: 3px;
  background: #dc2626;
  z-index: 1;
}

.table-node.out-of-order::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-45deg);
  width: 60%;
  height: 3px;
  background: #dc2626;
  z-index: 1;
}

.table-node.out-of-order:hover {
  transform: none;
  opacity: 0.5;
}

.table-info {
  text-align: center;
}

.table-number {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1;
}

.table-status {
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.table-tooltip {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  white-space: nowrap;
  z-index: 10;
  margin-bottom: 0.5rem;
}

.table-tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: rgba(0, 0, 0, 0.8);
}

.tooltip-content {
  text-align: center;
}

.tooltip-status {
  margin-top: 0.5rem;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.625rem;
}

.tooltip-status.available {
  background: #10b981;
}

.tooltip-status.occupied {
  background: #f59e0b;
}

.tooltip-status.reserved {
  background: #3b82f6;
}

.tooltip-status.out_of_order {
  background: #6b7280;
}

.layout-list {
  padding: 1rem;
}

.table-list-item {
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.table-list-item.available {
  border-left: 6px solid #10b981;
  background: linear-gradient(90deg, #f0fdf4 0%, #dcfce7 100%);
  position: relative;
}

.table-list-item.available::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: linear-gradient(180deg, #10b981, #34d399);
}

.table-list-item.available:hover {
  background: linear-gradient(90deg, #dcfce7 0%, #bbf7d0 100%);
  transform: translateX(4px);
}

.table-list-item.occupied {
  border-left: 6px solid #f59e0b;
  background: linear-gradient(90deg, #fffbeb 0%, #fef3c7 100%);
  position: relative;
  animation: pulse-occupied-list 2s infinite;
}

.table-list-item.occupied::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: linear-gradient(180deg, #f59e0b, #fbbf24);
}

.table-list-item.occupied:hover {
  background: linear-gradient(90deg, #fef3c7 0%, #fde68a 100%);
}

@keyframes pulse-occupied-list {
  0%, 100% {
    box-shadow: inset 0 0 0 0 rgba(245, 158, 11, 0.1);
  }
  50% {
    box-shadow: inset 0 0 10px 2px rgba(245, 158, 11, 0.2);
  }
}

.table-list-item.reserved {
  border-left: 6px solid #3b82f6;
  background: linear-gradient(90deg, #eff6ff 0%, #dbeafe 100%);
  position: relative;
}

.table-list-item.reserved::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: linear-gradient(180deg, #3b82f6, #60a5fa);
}

.table-list-item.reserved::after {
  content: '⏰';
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  background: #3b82f6;
  color: white;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.table-list-item.reserved:hover {
  background: linear-gradient(90deg, #dbeafe 0%, #bfdbfe 100%);
  transform: translateX(4px);
}

.table-list-item.out-of-order {
  border-left: 6px solid #ef4444;
  background: linear-gradient(90deg, #fef2f2 0%, #fecaca 100%);
  cursor: not-allowed;
  opacity: 0.7;
  position: relative;
}

.table-list-item.out-of-order::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: linear-gradient(180deg, #ef4444, #f87171);
}

.table-list-item.out-of-order::after {
  content: '🚫';
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  background: #ef4444;
  color: white;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.table-list-item.out-of-order:hover {
  transform: none;
  opacity: 0.5;
}

.list-item-content {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.table-number {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  min-width: 60px;
}

.table-details {
  flex: 1;
}

.table-name {
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.table-capacity {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  color: #64748b;
  font-size: 0.875rem;
}

.table-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 6px;
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
  background: #f3f4f6;
  color: #374151;
}

.layout-legend {
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  display: flex;
  justify-content: center;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.875rem;
  color: #374151;
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  background: white;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  transition: all 0.2s;
}

.legend-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid;
  position: relative;
}

.legend-color.available {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-color: #10b981;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.legend-color.occupied {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-color: #f59e0b;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
  animation: pulse-legend-occupied 2s infinite;
}

@keyframes pulse-legend-occupied {
  0%, 100% {
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
  }
  50% {
    box-shadow: 0 2px 12px rgba(245, 158, 11, 0.3);
  }
}

.legend-color.reserved {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border-color: #3b82f6;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}

.legend-color.reserved::after {
  content: '⏰';
  position: absolute;
  top: -6px;
  right: -6px;
  background: #3b82f6;
  color: white;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 6px;
  box-shadow: 0 1px 4px rgba(59, 130, 246, 0.3);
}

.legend-color.out-of-order {
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  border-color: #ef4444;
  opacity: 0.8;
  position: relative;
}

.legend-color.out-of-order::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(45deg);
  width: 50%;
  height: 2px;
  background: #dc2626;
  z-index: 1;
}

.legend-color.out-of-order::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-45deg);
  width: 50%;
  height: 2px;
  background: #dc2626;
  z-index: 1;
}

@media (max-width: 768px) {
  .layout-grid {
    height: 300px;
  }

  .table-node {
    width: 60px;
    height: 60px;
  }

  .table-number {
    font-size: 0.875rem;
  }

  .layout-legend {
    flex-direction: column;
    align-items: center;
  }
}
</style>
