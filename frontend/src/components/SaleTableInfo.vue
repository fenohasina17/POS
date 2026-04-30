<template>
  <div v-if="table" class="sale-table-info">
    <div class="table-info-header">
      <i class="fas fa-table"></i>
      <span class="table-number">{{ table.table_number }}</span>
    </div>
    <div class="table-details">
      <div v-if="table.name" class="table-name">{{ table.name }}</div>
      <div class="table-capacity">
        <i class="fas fa-users"></i>
        {{ table.capacity }} personnes
      </div>
      <div class="table-status" :class="table.status">
        <i :class="getStatusIcon(table.status)"></i>
        {{ getStatusText(table.status) }}
      </div>
    </div>
  </div>
  <div v-else class="sale-table-info no-table">
    <i class="fas fa-table"></i>
    <span>Aucune table assignée</span>
  </div>
</template>

<script>
export default {
  name: 'SaleTableInfo',
  props: {
    table: {
      type: Object,
      default: null
    }
  },
  methods: {
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
.sale-table-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.sale-table-info.no-table {
  color: #64748b;
  justify-content: center;
}

.table-info-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #2563eb;
  font-weight: 600;
}

.table-info-header i {
  font-size: 1rem;
}

.table-number {
  font-size: 1rem;
  font-weight: 700;
}

.table-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.table-name {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.875rem;
}

.table-capacity {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  color: #64748b;
  font-size: 0.75rem;
}

.table-status {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
  font-size: 0.625rem;
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
</style>
