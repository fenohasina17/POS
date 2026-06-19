<template>
  <div class="table-status-widget">
    <div class="widget-header">
      <h4><i class="fas fa-table"></i> État des tables</h4>
      <button @click="refreshStatus" class="btn-refresh">
        <i class="fas fa-sync-alt"></i>
      </button>
    </div>

    <div class="status-summary">
      <div class="status-item available">
        <span class="status-count">{{ statusCounts.available }}</span>
        <span class="status-label">Disponibles</span>
      </div>
      <div class="status-item occupied">
        <span class="status-count">{{ statusCounts.occupied }}</span>
        <span class="status-label">Occupées</span>
      </div>
      <div class="status-item reserved">
        <span class="status-count">{{ statusCounts.reserved }}</span>
        <span class="status-label">Réservées</span>
      </div>
      <div class="status-item out-of-order">
        <span class="status-count">{{ statusCounts.out_of_order }}</span>
        <span class="status-label">Hors service</span>
      </div>
    </div>

    <div v-if="activeTables.length > 0" class="active-tables">
      <h5>Tables actives</h5>
      <div class="active-list">
        <div
          v-for="table in activeTables.slice(0, 5)"
          :key="table.id"
          class="active-table-item"
          @click="goToTableSales"
        >
          <span class="table-number">{{ table.table_number }}</span>
          <span class="table-sales">{{ table.active_sales?.length || 0 }} vente(s)</span>
          <span class="table-total">{{ formatPrice(getTableTotal(table)) }}</span>
        </div>
      </div>
      <button v-if="activeTables.length > 5" @click="goToTableSales" class="btn-view-all">
        Voir toutes les tables actives
      </button>
    </div>

    <div v-else class="no-active-tables">
      <i class="fas fa-table"></i>
      <p>Aucune table active</p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/apiClient'
import { API_BASE_URL } from '@/utils/api'

export default {
  name: 'TableStatusWidget',
  setup() {
    const router = useRouter()
    const tables = ref([])
    const loading = ref(false)

    const statusCounts = computed(() => {
      const counts = {
        available: 0,
        occupied: 0,
        reserved: 0,
        out_of_order: 0
      }

      tables.value.forEach(table => {
        if (counts.hasOwnProperty(table.status)) {
          counts[table.status]++
        }
      })

      return counts
    })

    const activeTables = computed(() => {
      return tables.value.filter(table =>
        table.status === 'occupied' && table.active_sales && table.active_sales.length > 0
      )
    })

    const formatPrice = (price) => {
      return `${parseFloat(price).toFixed(2)} Ar`
    }

    const getTableTotal = (table) => {
      if (!table.active_sales) return 0
      return table.active_sales.reduce((sum, sale) => sum + parseFloat(sale.total_amount), 0)
    }

    const isSaleActive = (sale) => {
      if (!sale) return false
      const status = (sale.status || sale.state || sale.sale_status || '').toString().toLowerCase()
      if (!status) return true
      return !['completed', 'complete', 'terminée', 'terminee', 'closed', 'fermee', 'paid', 'paye', 'cancelled', 'annulée', 'annulee', 'annule', 'refunded'].includes(status)
    }

    const normalizeSaleLine = (rawLine) => {
      if (!rawLine) return null

      const product = rawLine.product || rawLine.item || {}
      const name = product.name || rawLine.name || rawLine.product_name || 'Produit'
      const quantity = Number(rawLine.quantity ?? rawLine.qty ?? 0) || 0
      const price = Number(rawLine.price ?? rawLine.unit_price ?? rawLine.unitPrice ?? product?.price ?? 0) || 0
      const total = Number(rawLine.total ?? rawLine.total_amount ?? rawLine.amount ?? quantity * price) || 0
      const id = rawLine.id ?? rawLine.order_line_id ?? rawLine.line_id ?? `${name}-${price}`
      const categoryId = rawLine.category_id ?? product?.category_id ?? null
      const printerTypeId = rawLine.printer_type_id ?? product?.printer_type_id ?? null

      return {
        ...rawLine,
        id,
        name,
        quantity,
        price,
        total,
        category_id: categoryId,
        printer_type_id: printerTypeId,
        product
      }
    }

    const aggregateLineItems = (lines = []) => {
      if (!Array.isArray(lines) || lines.length === 0) {
        return []
      }

      const aggregated = new Map()

      lines.forEach(line => {
        if (!line) return
        const product = line.product || null
        const productId = line.product_id ?? line.id ?? product?.id ?? null
        if (!productId) return

        const price = Number(line.price ?? product?.price ?? 0) || 0
        const categoryId = line.category_id ?? product?.category_id ?? null
        const printerTypeId = line.printer_type_id ?? product?.printer_type_id ?? null
        const key = `${productId}|${price}|${categoryId ?? ''}|${printerTypeId ?? ''}`

        const name = line.name || product?.name || 'Produit'
        const quantity = Number(line.quantity ?? 0) || 0
        const total = Number(line.total ?? line.total_amount ?? line.amount ?? quantity * price) || 0

        if (aggregated.has(key)) {
          const existing = aggregated.get(key)
          existing.quantity += quantity
          existing.total += total
        } else {
          aggregated.set(key, {
            ...line,
            product_id: productId,
            name,
            quantity,
            price,
            total,
            category_id: categoryId,
            printer_type_id: printerTypeId,
            product
          })
        }
      })

      return Array.from(aggregated.values()).map(item => ({
        ...item,
        quantity: Number(item.quantity.toFixed(3)),
        total: Number((Number(item.price || 0) * Number(item.quantity || 0)).toFixed(2))
      }))
    }

    const normalizeSale = (rawSale, table = null) => {
      if (!rawSale) return null

      const lines = Array.isArray(rawSale.order_lines)
        ? rawSale.order_lines
        : Array.isArray(rawSale.lines)
          ? rawSale.lines
          : Array.isArray(rawSale.items)
            ? rawSale.items
            : []

      const normalizedLines = aggregateLineItems(
        lines
          .map(line => normalizeSaleLine(line))
          .filter(Boolean)
      )

      let total = Number(rawSale.total_amount ?? rawSale.total ?? rawSale.amount ?? rawSale.total_price ?? 0)
      if ((!total || Number.isNaN(total)) && normalizedLines.length > 0) {
        total = normalizedLines.reduce((sum, line) => {
          const lineTotal = Number(line.total ?? line.total_amount ?? line.amount ?? 0)
          if (!Number.isNaN(lineTotal) && lineTotal) {
            return sum + lineTotal
          }
          const qty = Number(line.quantity ?? line.qty ?? 0)
          const price = Number(line.price ?? line.unit_price ?? line.unitPrice ?? 0)
          if (!Number.isNaN(qty) && !Number.isNaN(price)) {
            return sum + qty * price
          }
          return sum
        }, 0)
      }

      const createdAt = rawSale.created_at || rawSale.createdAt || rawSale.date || rawSale.emitted_at || rawSale.updated_at || new Date().toISOString()
      const id = rawSale.id ?? rawSale.sale_id ?? rawSale.order_id ?? null
      const ticket = rawSale.ticket_number || rawSale.invoice_number || rawSale.reference || rawSale.code || rawSale.order_number || (id ? `SALE-${id}` : 'Commande')

      return {
        ...rawSale,
        id,
        order_lines: normalizedLines,
        total_amount: Number.isFinite(total) ? total : 0,
        created_at: createdAt,
        status: rawSale.status || rawSale.state || rawSale.sale_status || '',
        ticket_number: ticket,
        table: rawSale.table || table || null
      }
    }

    const resolveActiveSales = (rawTable) => {
      if (!rawTable || typeof rawTable !== 'object') return []
      const candidates = [
        rawTable.active_sales,
        rawTable.activeSales,
        rawTable.pending_orders,
        rawTable.pendingOrders,
        rawTable.open_sales,
        rawTable.openSales,
        rawTable.sales
      ]

      let source = []
      for (const arr of candidates) {
        if (Array.isArray(arr) && arr.length) {
          source = arr
          break
        }
      }

      return source
        .map(sale => normalizeSale(sale, rawTable))
        .filter(sale => sale && isSaleActive(sale))
    }

    const normalizeTable = (rawTable) => {
      if (!rawTable || typeof rawTable !== 'object') return rawTable
      const activeSales = resolveActiveSales(rawTable)
      return {
        ...rawTable,
        active_sales: activeSales
      }
    }

    const fetchSaleDetails = async (saleId) => {
      if (!saleId) return null
      try {
        const response = await apiClient.get(`/sales/${saleId}`, {
          params: { with_lines: 1, with_order_lines: 1 }
        })
        return response.data?.data || response.data || null
      } catch (error) {
        console.error('Erreur chargement détail vente widget:', error.response?.data || error.message)
        return null
      }
    }

    const populateSaleLines = async (tableList = []) => {
      if (!Array.isArray(tableList) || !tableList.length) return
      const tasks = []
      tableList.forEach(table => {
        const sales = Array.isArray(table.active_sales) ? table.active_sales : []
        sales.forEach(sale => {
          if (!sale || (sale.order_lines && sale.order_lines.length)) return
          tasks.push((async () => {
            const details = await fetchSaleDetails(sale.id)
            if (!details) return
            const normalized = normalizeSale(details, table)
            Object.assign(sale, normalized)
          })())
        })
      })
      if (tasks.length) {
        try {
          await Promise.all(tasks)
        } catch (error) {
          console.error('Erreur enrichissement lignes widget:', error)
        }
      }
    }

    const loadPendingOrders = async (tableList = []) => {
      if (!Array.isArray(tableList) || !tableList.length) return
      const tasks = []

      tableList.forEach(table => {
        if (!table || !table.id) return
        tasks.push((async () => {
          try {
            const response = await apiClient.get(`/tables/${table.id}/pending-orders`)

            const pendingOrders = Array.isArray(response.data)
              ? response.data
              : Array.isArray(response.data?.data)
                ? response.data.data
                : []

            const normalizedPending = []
            for (const rawOrder of pendingOrders) {
              let normalized = normalizeSale(rawOrder, table)
              if (normalized && (!normalized.order_lines || normalized.order_lines.length === 0)) {
                const details = await fetchSaleDetails(normalized.id)
                if (details) {
                  normalized = normalizeSale(details, table)
                }
              }
              if (normalized && isSaleActive(normalized)) {
                normalizedPending.push(normalized)
              }
            }

            const existing = Array.isArray(table.active_sales) ? table.active_sales : []
            const merged = [...existing]
            const indexById = new Map(existing.map((sale, idx) => [sale.id, idx]))

            normalizedPending.forEach(order => {
              if (!order) return
              if (indexById.has(order.id)) {
                const idx = indexById.get(order.id)
                merged[idx] = order
              } else {
                indexById.set(order.id, merged.length)
                merged.push(order)
              }
            })

            table.active_sales = merged
          } catch (error) {
            console.error(`Erreur commandes en attente widget table ${table.id}:`, error.response?.data || error.message)
          }
        })())
      })

      if (tasks.length) {
        try {
          await Promise.all(tasks)
        } catch (error) {
          console.error('Erreur récupération commandes en attente widget:', error)
        }
      }
    }

    const loadTables = async () => {
      loading.value = true
      try {
        const response = await apiClient.get('/tables', {
          params: { with_sales: 1 }
        })

        const rawTables = Array.isArray(response.data) ? response.data : response.data.data || []
        tables.value = rawTables.map(table => normalizeTable(table))
        await populateSaleLines(tables.value)
        await loadPendingOrders(tables.value)
      } catch (error) {
        console.error('Erreur lors du chargement des tables:', error.response?.data || error.message)
      } finally {
        loading.value = false
      }
    }

    const refreshStatus = () => {
      loadTables()
    }

    const goToTableSales = () => {
      router.push({ name: 'table-sales' })
    }

    onMounted(() => {
      loadTables()
    })

    return {
      tables,
      loading,
      statusCounts,
      activeTables,
      formatPrice,
      getTableTotal,
      refreshStatus,
      goToTableSales
    }
  }
}
</script>

<style scoped>
.table-status-widget {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 1rem;
  margin-bottom: 1rem;
}

.widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.widget-header h4 {
  margin: 0;
  color: #1e293b;
  font-size: 1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-refresh {
  width: 24px;
  height: 24px;
  border: none;
  border-radius: 4px;
  background: #f3f4f6;
  color: #374151;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-refresh:hover {
  background: #e5e7eb;
  color: #1e293b;
}

.status-summary {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.status-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.5rem;
  border-radius: 6px;
  text-align: center;
}

.status-item.available {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
}

.status-item.occupied {
  background: #fffbeb;
  border: 1px solid #fed7aa;
}

.status-item.reserved {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
}

.status-item.out-of-order {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
}

.status-count {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.status-label {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
}

.active-tables h5 {
  margin: 0 0 0.75rem 0;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 600;
}

.active-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.active-table-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.active-table-item:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.table-number {
  font-weight: 600;
  color: #1e293b;
}

.table-sales {
  color: #64748b;
  font-size: 0.75rem;
}

.table-total {
  font-weight: 600;
  color: #2563eb;
}

.btn-view-all {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: white;
  color: #374151;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-view-all:hover {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.no-active-tables {
  text-align: center;
  color: #9ca3af;
  padding: 1rem;
}

.no-active-tables i {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
}

.no-active-tables p {
  margin: 0;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .status-summary {
    grid-template-columns: repeat(4, 1fr);
  }

  .active-table-item {
    flex-direction: column;
    align-items: stretch;
    gap: 0.25rem;
  }

  .table-number, .table-sales, .table-total {
    text-align: center;
  }
}
</style>
