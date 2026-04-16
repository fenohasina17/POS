<template>
  <div class="table-sales-view">
    <Profile v-if="!embedded" />

    <section class="table-sales-content">
      <header class="table-sales-header">
        <div class="header-info">
          <h1>
            <font-awesome-icon icon="fa-solid fa-table" />
            Service en salle
          </h1>
          <p>Surveillez les tables, leurs commandes en cours et accédez aux détails rapidement.</p>
        </div>
        <div class="header-actions">
          <button type="button" class="action-button" @click="refreshData">
            <font-awesome-icon icon="fa-solid fa-rotate" />
            Actualiser
          </button>
        </div>
      </header>

      <div class="filters-card">
        <div class="filters-grid">
          <div class="filter-item">
            <label for="statusFilter">Statut</label>
            <select id="statusFilter" v-model="statusFilter" @change="filterTables">
              <option value="">Tous</option>
              <option value="occupied">Occupées</option>
              <option value="available">Disponibles</option>
              <option value="reserved">Réservées</option>
            </select>
          </div>
        </div>
      </div>

      <div class="tables-wrapper">
        <div v-if="loading" class="state loading">
          <span class="spinner"></span>
          Chargement des tables…
        </div>
        <template v-else>
          <div v-if="!filteredTables.length" class="state empty">
            Aucune table ne correspond aux filtres sélectionnés.
          </div>
          <div v-else class="tables-grid">
            <article
              v-for="table in filteredTables"
              :key="table.id"
              class="table-card"
              :class="`status-${table.status || 'unknown'}`"
            >
              <header class="table-card__header">
                <div class="table-card__info">
                  <p class="table-card__number">Table {{ table.table_number }}</p>
                  <p v-if="table.name" class="table-card__name">{{ table.name }}</p>
                  <div class="table-card__status">
                    <font-awesome-icon :icon="getStatusIcon(table.status)" />
                    <span>{{ getStatusText(table.status) }}</span>
                  </div>
                </div>
                <div class="table-card__meta">
                  <span v-if="table.active_sales?.length" class="table-card__total">
                    {{ formatPrice(getTableTotal(table)) }}
                  </span>
                  <span v-else class="table-card__badge">Libre</span>
                  <div class="table-card__actions">
                    <button
                      type="button"
                      class="icon-button"
                      :disabled="table.status === 'out_of_order'"
                      @click="startTableService(table)"
                      title="Prendre la commande"
                    >
                      <font-awesome-icon icon="fa-solid fa-plus" />
                    </button>
                    <button
                      type="button"
                      class="icon-button"
                      @click="viewTableDetails(table)"
                      title="Détails de la table"
                    >
                      <font-awesome-icon icon="fa-solid fa-eye" />
                    </button>
                    <button
                      type="button"
                      class="icon-button"
                      @click="printTableBill(table)"
                      title="Imprimer la facture"
                    >
                      <font-awesome-icon icon="fa-solid fa-print" />
                    </button>
                  </div>
                </div>
              </header>

              <div v-if="table.active_sales && table.active_sales.length" class="table-card__sales">
                <div
                  v-for="sale in table.active_sales"
                  :key="sale.id"
                  class="sale-card"
                >
                  <button type="button" class="sale-card__header" @click="toggleSaleLines(sale.id)">
                    <div class="sale-card__info">
                      <span class="sale-card__ticket">Ticket #{{ sale.ticket_number }}</span>
                      <span class="sale-card__time">{{ formatTime(sale.created_at) }}</span>
                    </div>
                    <span class="sale-card__amount">{{ formatPrice(sale.total_amount) }}</span>
                    <span class="sale-card__toggle">
                      <font-awesome-icon :icon="isSaleExpanded(sale.id) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" />
                    </span>
                  </button>
                  <transition name="fade">
                    <ul v-if="isSaleExpanded(sale.id) && sale.order_lines?.length" class="sale-card__lines">
                      <li
                        v-for="line in sale.order_lines"
                        :key="line.id || line.product_id || line.name"
                      >
                        <span class="line-name">{{ line.name }}</span>
                        <span class="line-qty">×{{ line.quantity }}</span>
                      </li>
                    </ul>
                  </transition>
                </div>
              </div>
              <div v-else class="table-card__empty">
                <font-awesome-icon icon="fa-solid fa-receipt" />
                <span>Aucune commande en cours</span>
              </div>
            </article>
          </div>
        </template>
      </div>
    </section>

    <div v-if="showTableDetails" class="modal-overlay" @click="closeTableDetails">
      <div class="modal-content" @click.stop>
        <header class="modal-header">
          <h3>Table {{ selectedTable?.table_number }}</h3>
          <button type="button" class="icon-button" @click="closeTableDetails">
            <font-awesome-icon icon="fa-solid fa-xmark" />
          </button>
        </header>
        <section class="modal-body">
          <div v-if="selectedTable" class="table-details">
            <div class="detail-row">
              <span class="label">Numéro</span>
              <span class="value">{{ selectedTable.table_number }}</span>
            </div>
            <div v-if="selectedTable.name" class="detail-row">
              <span class="label">Nom</span>
              <span class="value">{{ selectedTable.name }}</span>
            </div>
            <div class="detail-row">
              <span class="label">Capacité</span>
              <span class="value">{{ selectedTable.capacity }} personnes</span>
            </div>
            <div class="detail-row">
              <span class="label">Statut</span>
              <span class="value status-pill" :class="`status-${selectedTable.status}`">
                {{ getStatusText(selectedTable.status) }}
              </span>
            </div>
            <div v-if="selectedTable.description" class="detail-row">
              <span class="label">Description</span>
              <span class="value">{{ selectedTable.description }}</span>
            </div>
          </div>
        </section>
      </div>
    </div>

    <div v-if="showSaleDetails" class="modal-overlay" @click="closeSaleDetails">
      <div class="modal-content large" @click.stop>
        <header class="modal-header">
          <h3>Vente #{{ selectedSale?.ticket_number }}</h3>
          <button type="button" class="icon-button" @click="closeSaleDetails">
            <font-awesome-icon icon="fa-solid fa-xmark" />
          </button>
        </header>
        <section class="modal-body">
          <div v-if="selectedSale" class="sale-details">
            <div class="sale-info-grid">
              <div class="info-item">
                <span class="label">Table</span>
                <span class="value">{{ selectedSale.table?.table_number }}</span>
              </div>
              <div class="info-item">
                <span class="label">Serveur</span>
                <span class="value">{{ selectedSale.user?.name }}</span>
              </div>
              <div class="info-item">
                <span class="label">Date</span>
                <span class="value">{{ formatDateTime(selectedSale.created_at) }}</span>
              </div>
              <div class="info-item">
                <span class="label">Statut</span>
                <span class="value status-pill" :class="`status-${selectedSale.status}`">
                  {{ getSaleStatusText(selectedSale.status) }}
                </span>
              </div>
            </div>

            <div class="sale-items">
              <h4>Articles commandés</h4>
              <div class="items-list">
                <div
                  v-for="item in selectedSale.order_lines"
                  :key="item.id"
                  class="item-row"
                >
                  <div class="item-info">
                    <span class="item-name">{{ item.product?.name }}</span>
                    <span class="item-quantity">×{{ item.quantity }}</span>
                  </div>
                  <div class="item-price">{{ formatPrice(item.price) }}</div>
                  <div class="item-total">{{ formatPrice(item.total) }}</div>
                </div>
              </div>
            </div>

            <div class="sale-summary">
              <div class="summary-row">
                <span>Sous-total</span>
                <span>{{ formatPrice(selectedSale.total_amount) }}</span>
              </div>
              <div v-if="selectedSale.discount_percentage > 0" class="summary-row discount">
                <span>Remise ({{ selectedSale.discount_percentage }}%)</span>
                <span>-{{ formatPrice(selectedSale.total_amount * selectedSale.discount_percentage / 100) }}</span>
              </div>
              <div class="summary-row total">
                <span>Total</span>
                <span>{{ formatPrice(selectedSale.total_amount * (1 - selectedSale.discount_percentage / 100)) }}</span>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import Profile from './Profile.vue'

export default {
  name: 'TableSales',
  components: {
    Profile
  },
  props: {
    embedded: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      tables: [],
      statusFilter: '',
      loading: false,
      showTableDetails: false,
      showSaleDetails: false,
      selectedTable: null,
      selectedSale: null,
      expandedSales: {}
    }
  },
  computed: {
    filteredTables() {
      let filtered = [...this.tables]

      if (this.statusFilter) {
        filtered = filtered.filter(table => table.status === this.statusFilter)
      }

      return filtered
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

    formatPrice(price) {
      return `${parseFloat(price).toFixed(2)} Ar`
    },

    toggleSaleLines(saleId) {
      this.expandedSales[saleId] = !this.expandedSales[saleId]
    },

    isSaleExpanded(saleId) {
      return !!this.expandedSales[saleId]
    },

    formatTime(dateTime) {
      return new Date(dateTime).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit'
      })
    },

    formatDateTime(dateTime) {
      return new Date(dateTime).toLocaleString('fr-FR')
    },

    getStatusIcon(status) {
      const icons = {
        available: 'fa-solid fa-circle-check',
        occupied: 'fa-solid fa-users',
        reserved: 'fa-solid fa-calendar-check',
        out_of_order: 'fa-solid fa-wrench'
      }
      return icons[status] || 'fa-solid fa-circle-question'
    },

    getStatusText(status) {
      const texts = {
        available: 'Disponible',
        occupied: 'Occupée',
        reserved: 'Réservée',
        out_of_order: 'Hors service'
      }
      return texts[status] || 'Inconnu'
    },

    getSaleStatusText(status) {
      const texts = {
        pending: 'En attente',
        completed: 'Terminée',
        cancelled: 'Annulée'
      }
      return texts[status] || status
    },

    getTableTotal(table) {
      if (!table.active_sales) return 0
      return table.active_sales.reduce((sum, sale) => sum + parseFloat(sale.total_amount), 0)
    },

    isSaleActive(sale) {
      if (!sale) return false
      const status = (sale.status || sale.state || sale.sale_status || '').toString().toLowerCase()
      if (!status) return true
      return !['completed', 'complete', 'terminée', 'terminee', 'closed', 'fermee', 'paid', 'paye', 'cancelled', 'annulée', 'annulee', 'annule', 'refunded'].includes(status)
    },

    normalizeSaleLine(rawLine) {
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
    },

    aggregateLineItems(lines = []) {
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
    },

    normalizeSale(rawSale, table = null) {
      if (!rawSale) return null

      const lines = Array.isArray(rawSale.order_lines)
        ? rawSale.order_lines
        : Array.isArray(rawSale.lines)
          ? rawSale.lines
          : Array.isArray(rawSale.items)
            ? rawSale.items
            : []

      const normalizedLines = this.aggregateLineItems(
        lines
          .map(line => this.normalizeSaleLine(line))
          .filter(Boolean)
      )

      let total = Number(rawSale.total_amount ?? rawSale.total ?? rawSale.amount ?? rawSale.total_price ?? 0)
      if ((!total || Number.isNaN(total)) && normalizedLines.length > 0) {
        total = normalizedLines.reduce((sum, line) => sum + (line.total ?? 0), 0)
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
    },

    resolveActiveSales(rawTable) {
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
        .map(sale => this.normalizeSale(sale, rawTable))
        .filter(sale => sale && this.isSaleActive(sale))
    },

    normalizeTable(rawTable) {
      if (!rawTable || typeof rawTable !== 'object') return rawTable
      const activeSales = this.resolveActiveSales(rawTable)
      return {
        ...rawTable,
        status: this.normalizeStatus(rawTable.status),
        active_sales: activeSales
      }
    },

    async loadPendingOrdersForTables(tables = []) {
      if (!Array.isArray(tables) || !tables.length) return

      const token = localStorage.getItem('token')
      const tasks = []

      tables.forEach(table => {
        if (!table || !table.id) return
        const url = `${API_BASE_URL}/tables/${table.id}/pending-orders`
        tasks.push((async () => {
          try {
            const response = await axios.get(url, {
              headers: { Authorization: `Bearer ${token}` }
            })

            const pendingOrders = Array.isArray(response.data)
              ? response.data
              : Array.isArray(response.data?.data)
                ? response.data.data
                : []

            const normalizedPending = []
            for (const rawOrder of pendingOrders) {
              let normalized = this.normalizeSale(rawOrder, table)
              if (normalized && (!normalized.order_lines || normalized.order_lines.length === 0)) {
                const details = await this.fetchSaleDetails(normalized.id)
                if (details) {
                  normalized = this.normalizeSale(details, table)
                }
              }
              if (normalized && this.isSaleActive(normalized)) {
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
            console.error(`Erreur lors du chargement des commandes en attente pour la table ${table.id}:`, error.response?.data || error.message)
          }
        })())
      })

      if (tasks.length) {
        try {
          await Promise.all(tasks)
        } catch (error) {
          console.error('Erreur lors de la récupération des lignes de commande:', error)
        }
      }
    },

    async populateSaleLines(tables = []) {
      if (!Array.isArray(tables) || !tables.length) return

      const tasks = []

      tables.forEach(table => {
        const sales = Array.isArray(table.active_sales) ? table.active_sales : []
        sales.forEach(sale => {
          if (!sale || (sale.order_lines && sale.order_lines.length)) return
          tasks.push((async () => {
            const details = await this.fetchSaleDetails(sale.id)
            if (!details) return
            const normalized = this.normalizeSale(details, table)
            Object.assign(sale, normalized)
          })())
        })
      })

      if (tasks.length) {
        try {
          await Promise.all(tasks)
        } catch (error) {
          console.error('Erreur lors de la récupération des lignes de commande:', error)
        }
      }
    },

    async startTableService(table) {
      if (!table || !table.id) {
        return
      }

      if (table.status === 'out_of_order') {
        alert('Cette table est hors service pour le moment.')
        return
      }

      this.$router.push({
        name: 'dashboard-table-order',
        params: { tableId: table.id }
      })
    },

    async updateTableStatus(tableId, status) {
      try {
        const token = localStorage.getItem('token')
        await axios.put(`${API_BASE_URL}/tables/${tableId}`,
          { status },
          {
            headers: {
              'Content-Type': 'application/json',
              Authorization: `Bearer ${token}`
            }
          }
        )
        return true
      } catch (error) {
        console.error('Erreur lors de la mise à jour du statut de la table :', error.response?.data || error.message)
        alert("Impossible de mettre à jour le statut de la table. Veuillez réessayer.")
        return false
      }
    },

    async loadTables() {
      this.loading = true
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(`${API_BASE_URL}/tables`, {
          params: {
            with_sales: 1
          },
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        })

        const rawTables = Array.isArray(response.data) ? response.data : response.data.data || []
        this.tables = rawTables.map(table => this.normalizeTable(table))
        await this.populateSaleLines(this.tables)
        await this.loadPendingOrdersForTables(this.tables)
      } catch (error) {
        console.error('Erreur lors du chargement des tables:', error.response?.data || error.message)
      } finally {
        this.loading = false
      }
    },

    filterTables() {
      // La logique de filtrage est dans le computed filteredTables
    },

    refreshData() {
      this.loadTables()
    },

    viewTableDetails(table) {
      this.selectedTable = table
      this.showTableDetails = true
    },

    closeTableDetails() {
      this.showTableDetails = false
      this.selectedTable = null
    },

    viewSaleDetails(sale) {
      this.selectedSale = sale
      this.showSaleDetails = true
    },

    closeSaleDetails() {
      this.showSaleDetails = false
      this.selectedSale = null
    },

    printTableBill(table) {
      console.log('Impression de la facture pour la table:', table.table_number)
    },

    async closeSale(sale) {
      if (!confirm('Êtes-vous sûr de vouloir fermer cette vente ?')) {
        return
      }

      try {
        const token = localStorage.getItem('token')
        await axios.put(`${API_BASE_URL}/sales/${sale.id}`,
          { status: 'completed' },
          {
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json'
            }
          }
        )

        this.loadTables()
      } catch (error) {
        console.error('Erreur lors de la fermeture de la vente:', error.response?.data || error.message)
      }
    }
  },

  async mounted() {
    await this.loadTables()
  }
}
</script>

<style scoped>
.table-sales-view {
  min-height: 100vh;
  padding: 3rem 1.5rem;
  background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 100%);
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.table-sales-content {
  width: 100%;
  max-width: none;
  margin: 0;
  background: #fff;
  border-radius: 1.25rem;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.12);
  padding: 2rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
}

.table-sales-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
}

.table-sales-header h1 {
  margin: 0;
  font-size: 1.9rem;
  font-weight: 700;
  color: #1e293b;
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
}

.table-sales-header p {
  margin: 0.35rem 0 0;
  color: #64748b;
  max-width: 520px;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.action-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid #dbe4ff;
  background: rgba(59, 130, 246, 0.08);
  color: #1d4ed8;
  font-weight: 600;
  padding: 0.65rem 1.1rem;
  border-radius: 0.9rem;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.action-button:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 20px rgba(37, 99, 235, 0.18);
  background: rgba(59, 130, 246, 0.12);
}

.action-button.alt {
  border-color: #ede9fe;
  background: rgba(129, 140, 248, 0.12);
  color: #4338ca;
}

.action-button.alt:hover {
  box-shadow: 0 10px 20px rgba(129, 140, 248, 0.18);
}

.filters-card {
  background: rgba(248, 250, 252, 0.85);
  border: 1px solid rgba(226, 232, 240, 0.6);
  border-radius: 1rem;
  padding: 1.25rem 1.5rem;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.filter-item label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.filter-item select {
  height: 2.75rem;
  border-radius: 0.85rem;
  border: 1px solid #dbe4ff;
  background: #fff;
  color: #1e293b;
  padding: 0 0.9rem;
  font-weight: 600;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.filter-item select:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}

.tables-wrapper {
  min-height: 300px;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem;
  border-radius: 1rem;
  font-weight: 600;
  color: #1e293b;
  background: rgba(226, 232, 240, 0.4);
}

.state.loading {
  background: rgba(59, 130, 246, 0.12);
  color: #1d4ed8;
}

.state.empty {
  background: rgba(148, 163, 184, 0.16);
  color: #475569;
}

.spinner {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 9999px;
  border: 3px solid rgba(148, 163, 184, 0.25);
  border-top-color: #1d4ed8;
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.tables-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 1.25rem;
}

.table-card {
  background: linear-gradient(135deg, rgba(248, 250, 252, 0.85), #fff);
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 1.15rem;
  padding: 1.25rem 1.4rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.table-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 25px 55px rgba(15, 23, 42, 0.12);
}

.table-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.table-card__info {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.table-card__number {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
}

.table-card__name {
  margin: 0;
  color: #475569;
  font-weight: 600;
}

.table-card__status {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.35rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: capitalize;
  background: rgba(59, 130, 246, 0.12);
  color: #1d4ed8;
}

.table-card.status-available .table-card__status,
.status-pill.status-available {
  background: rgba(34, 197, 94, 0.12);
  color: #15803d;
}

.table-card.status-occupied .table-card__status,
.status-pill.status-occupied {
  background: rgba(249, 115, 22, 0.12);
  color: #c2410c;
}

.table-card.status-reserved .table-card__status,
.status-pill.status-reserved {
  background: rgba(99, 102, 241, 0.12);
  color: #4338ca;
}

.table-card.status-out_of_order .table-card__status,
.status-pill.status-out_of_order {
  background: rgba(239, 68, 68, 0.12);
  color: #b91c1c;
}

.table-card__meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.6rem;
}

.table-card__total {
  font-weight: 700;
  color: #1f2937;
  background: rgba(59, 130, 246, 0.12);
  padding: 0.4rem 0.75rem;
  border-radius: 9999px;
}

.table-card__badge {
  padding: 0.4rem 0.75rem;
  border-radius: 9999px;
  font-weight: 600;
  background: rgba(34, 197, 94, 0.12);
  color: #15803d;
}

.table-card__actions {
  display: flex;
  gap: 0.4rem;
}

.icon-button {
  border: none;
  background: rgba(226, 232, 240, 0.65);
  color: #334155;
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.icon-button:hover:not(:disabled) {
  transform: translateY(-1px);
  background: rgba(99, 102, 241, 0.12);
  color: #4338ca;
}

.icon-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.table-card__sales {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.sale-card {
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 1rem;
  background: rgba(248, 250, 252, 0.7);
  overflow: hidden;
}

.sale-card__header {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.85rem 1rem;
  background: rgba(59, 130, 246, 0.08);
  border: none;
  cursor: pointer;
  color: inherit;
}

.sale-card__info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  text-align: left;
}

.sale-card__ticket {
  font-weight: 600;
  color: #1d4ed8;
}

.sale-card__time {
  font-size: 0.8rem;
  color: #64748b;
}

.sale-card__amount {
  font-weight: 600;
  color: #0f172a;
}

.sale-card__lines {
  list-style: none;
  margin: 0;
  padding: 0.75rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: #fff;
}

.sale-card__lines li {
  display: flex;
  justify-content: space-between;
  color: #475569;
  font-weight: 500;
}

.line-qty {
  color: #6366f1;
  font-weight: 600;
}

.table-card__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 1.5rem;
  border: 1px dashed rgba(148, 163, 184, 0.5);
  border-radius: 1rem;
  color: #475569;
  background: rgba(248, 250, 252, 0.6);
  font-weight: 600;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.4);
  padding: 1.5rem;
  backdrop-filter: blur(2px);
  z-index: 1100;
}

.modal-content {
  width: 100%;
  max-width: 520px;
  background: #fff;
  border-radius: 1.25rem;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-content.large {
  max-width: 680px;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.5rem;
  background: linear-gradient(140deg, #eef2ff 0%, #f8fafc 100%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
}

.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #1e293b;
}

.modal-body {
  padding: 1.5rem;
  max-height: 70vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.table-details,
.sale-details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.detail-row,
.info-item,
.item-row,
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-radius: 1rem;
  background: rgba(248, 250, 252, 0.85);
  border: 1px solid rgba(226, 232, 240, 0.7);
  gap: 1rem;
}

.detail-row .label,
.info-item .label,
.summary-row span:first-child {
  color: #64748b;
  font-weight: 600;
}

.detail-row .value,
.info-item .value,
.item-row .item-info,
.summary-row span:last-child {
  color: #0f172a;
  font-weight: 600;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.35rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  text-transform: capitalize;
}

.sale-items h4 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1f2937;
}

.items-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.item-info {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.item-name {
  font-weight: 600;
  color: #1f2937;
}

.item-quantity {
  font-size: 0.8rem;
  color: #64748b;
}

.item-price,
.item-total {
  font-weight: 600;
  color: #4338ca;
}

.sale-summary {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.summary-row.total {
  background: rgba(59, 130, 246, 0.12);
  color: #1d4ed8;
}

.summary-row.discount {
  background: rgba(254, 226, 226, 0.6);
  color: #b91c1c;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 1024px) {
  .tables-grid {
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  }
}

@media (max-width: 768px) {
  .table-sales-view {
    padding: 2rem 1rem;
  }

  .table-sales-content {
    padding: 1.5rem;
  }

  .header-actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
