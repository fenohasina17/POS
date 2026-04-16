<template>
  <div class="sales-view">
    <Profile v-if="!embedded" />

    <section class="sales-content">
      <header class="sales-header">
        <div>
          <h1>{{ isAdmin ? 'Toutes les ventes' : 'Ventes de la session' }}</h1>
          <p v-if="isAdmin">Parcourez l’ensemble des ventes enregistrées et appliquez des filtres par caisse ou point de vente.</p>
          <p v-else>Consultez les tickets enregistrés pendant la session de caisse en cours.</p>
        </div>
        <div class="meta" v-if="(!isAdmin && sessionId) || filteredSales.length">
          <span v-if="!isAdmin && sessionId">Session #{{ sessionId }}</span>
          <span v-if="filteredSales.length">
            {{ filteredSales.length }} vente<span v-if="filteredSales.length > 1">s</span>
          </span>
        </div>
      </header>

      <div class="utilities">
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Rechercher par ticket, produit, date…"
          class="search-input"
        />

        <div class="filters">
          <label v-if="isAdmin">
            Point de vente
            <select v-model="pointOfSaleFilter">
              <option value="">
                {{ pointOfSales.length ? 'Tous' : 'Aucun point de vente' }}
              </option>
              <option
                v-for="(pos, index) in pointOfSales"
                :key="getPointOfSaleId(pos) ?? `pos-${index}`"
                :value="String(getPointOfSaleId(pos) ?? '')"
                v-if="getPointOfSaleId(pos) !== null"
              >
                {{ formatPointOfSaleName(pos) }}
              </option>
            </select>
          </label>

          <label v-if="isAdmin">
            Caisse
            <select v-model="cashRegisterFilter" :disabled="!availableCashRegisters.length">
              <option value="">
                {{ availableCashRegisters.length ? 'Toutes' : pointOfSaleFilter ? 'Aucune caisse pour ce point de vente' : 'Aucune caisse' }}
              </option>
              <option
                v-for="(register, index) in availableCashRegisters"
                :key="getRegisterId(register) ?? `register-${index}`"
                :value="String(getRegisterId(register) ?? '')"
                v-if="getRegisterId(register) !== null"
              >
                {{ formatRegisterName(register) }}
              </option>
            </select>
          </label>

          <label>
            Période
            <select v-model="periodFilter" @change="applyPeriodFilter">
              <option value="">Toutes</option>
              <option value="today">Aujourd'hui</option>
              <option value="thisWeek">Cette semaine</option>
              <option value="thisMonth">Ce mois</option>
            </select>
          </label>
          <label>
            Date début
            <input type="date" v-model="startDate" />
          </label>
          <label>
            Date fin
            <input type="date" v-model="endDate" />
          </label>
        </div>
      </div>

      <div v-if="loading" class="state loading">Chargement des ventes…</div>

      <div v-else class="sales-list">
        <div v-if="loadError" class="state error">{{ loadError }}</div>
        <div v-else-if="filteredSales.length === 0" class="state empty">Aucune vente trouvée.</div>

        <transition-group name="fade" tag="div">
          <article
            v-for="sale in filteredSales"
            :key="sale?.id ?? sale?.ticket_number ?? sale?.created_at"
            class="sale-card"
          >
            <header class="sale-card__header" @click="toggleSale(sale?.id)">
              <div class="info">
                <h2>Ticket #{{ sale?.ticket_number || 'N/A' }}</h2>
                <p>
                  Créé le {{ formatDate(sale?.created_at) }} · Total {{ formatPrice(sale?.total_amount || 0) }}
                </p>
              </div>

              <div class="status-badge" :class="statusClass(sale?.status)">
                <FontAwesomeIcon :icon="statusIcon(sale?.status)" />
                <span>{{ formatStatus(sale?.status) }}</span>
              </div>

              <div class="actions">
                <button class="ghost" @click.stop="editSale(sale?.id)">
                  <FontAwesomeIcon icon="fa-solid fa-pen-to-square" />
                </button>
                <button class="ghost danger" @click.stop="deleteSale(sale?.id)">
                  <FontAwesomeIcon icon="fa-solid fa-trash" />
                </button>
                <button class="toggle" @click.stop="toggleSale(sale?.id)">
                  <FontAwesomeIcon :icon="expandedSales.has(sale?.id) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" />
                </button>
              </div>
            </header>

            <transition name="expand">
              <div v-if="expandedSales.has(sale?.id)" class="sale-card__details">
                <table>
                  <thead>
                    <tr>
                      <th>Produit</th>
                      <th>Quantité</th>
                      <th>Prix unitaire</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="line in sale?.order_lines" :key="line?.id">
                      <td>{{ line?.product?.name || 'N/A' }}</td>
                      <td>{{ line?.quantity }}</td>
                      <td>{{ formatPrice(line?.price) }}</td>
                      <td>{{ formatPrice(line?.total) }}</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="3">Total ticket</td>
                      <td>{{ formatPrice(totalLinesAmount(sale)) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </transition>
          </article>
        </transition-group>
      </div>
    </section>

    <EditSaleModal v-if="isEditModalOpen" :sale="saleToEdit" @save="saveSale" @close="closeEditModal" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import EditSaleModal from './EditSaleModal.vue'
import Profile from './Profile.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faChevronDown,
  faChevronUp,
  faCircleCheck,
  faCircleXmark,
  faClock,
  faPenToSquare,
  faTrash
} from '@fortawesome/free-solid-svg-icons'
import { useAuth } from '@/composables/useAuth'
import { API_BASE_URL } from '@/utils/api'

library.add(faChevronDown, faChevronUp, faCircleCheck, faCircleXmark, faClock, faPenToSquare, faTrash)

const props = defineProps({
  embedded: { type: Boolean, default: false }
})
const embedded = computed(() => props.embedded)
const { isAdmin, loadUserData, currentUser } = useAuth()

const sales = ref([])
const loading = ref(true)
const searchQuery = ref('')
const periodFilter = ref('')
const startDate = ref('')
const endDate = ref('')
const expandedSales = ref(new Set())
const isEditModalOpen = ref(false)
const saleToEdit = ref(null)
const sessionId = ref(null)
const loadError = ref('')
const cashRegisterFilter = ref('')
const pointOfSaleFilter = ref('')
const cashRegisters = ref([])
const pointOfSales = ref([])

const authHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) {
    const error = new Error('Token manquant. Veuillez vous reconnecter.')
    error.code = 'NO_TOKEN'
    throw error
  }
  return { Authorization: `Bearer ${token}` }
}

const openEditModal = (sale) => {
  saleToEdit.value = sale
  isEditModalOpen.value = true
}

const closeEditModal = () => {
  isEditModalOpen.value = false
  saleToEdit.value = null
}

const saveSale = async (updatedSale) => {
  if (!updatedSale?.id) {
    closeEditModal()
    return
  }

  const index = sales.value.findIndex((s) => s?.id === updatedSale.id)
  if (index !== -1) {
    sales.value[index] = updatedSale
  }

  try {
    await axios.put(`${API_BASE_URL}/sales/${updatedSale.id}`, updatedSale, {
      headers: {
        'Content-Type': 'application/json',
        ...authHeaders()
      }
    })
  } catch (error) {
    console.error('Erreur lors de la mise à jour de la vente:', error.response?.data || error.message)
  }

  closeEditModal()
}

const editSale = (saleId) => {
  const sale = sales.value.find((s) => s?.id === saleId)
  if (sale) {
    openEditModal(sale)
  }
}

const deleteSale = async (saleId) => {
  if (!saleId) return
  if (!confirm('Êtes-vous sûr de vouloir supprimer cette vente ?')) return

  try {
    await axios.delete(`${API_BASE_URL}/sales/${saleId}`, {
      headers: authHeaders()
    })
    sales.value = sales.value.filter((s) => s?.id !== saleId)
    const current = new Set(expandedSales.value)
    current.delete(saleId)
    expandedSales.value = current
  } catch (error) {
    console.error('Erreur lors de la suppression de la vente:', error.response?.data || error.message)
    loadError.value = error.response?.data?.message || 'Impossible de supprimer la vente.'
  }
}

const applyPeriodFilter = () => {
  const now = new Date()

  switch (periodFilter.value) {
    case 'today': {
      const today = now.toISOString().slice(0, 10)
      startDate.value = today
      endDate.value = today
      break
    }
    case 'thisWeek': {
      const firstDayOfWeek = new Date(now)
      firstDayOfWeek.setDate(now.getDate() - now.getDay())
      startDate.value = firstDayOfWeek.toISOString().slice(0, 10)
      endDate.value = now.toISOString().slice(0, 10)
      break
    }
    case 'thisMonth': {
      const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
      startDate.value = firstDayOfMonth.toISOString().slice(0, 10)
      endDate.value = now.toISOString().slice(0, 10)
      break
    }
    default:
      startDate.value = ''
      endDate.value = ''
  }
}

const formatPrice = (price) => {
  const value = Number(price)
  const amount = Number.isFinite(value) ? value : 0
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(amount))} Ar`
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })
}

const statusIcon = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'completed':
    case 'paid':
      return 'fa-solid fa-circle-check'
    case 'cancelled':
    case 'refund':
      return 'fa-solid fa-circle-xmark'
    default:
      return 'fa-solid fa-clock'
  }
}

const statusClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'completed':
    case 'paid':
      return 'status--success'
    case 'cancelled':
    case 'refund':
      return 'status--danger'
    default:
      return 'status--pending'
  }
}

const formatStatus = (status) => {
  if (!status) return 'En attente'
  const labels = {
    completed: 'Terminée',
    paid: 'Payée',
    pending: 'En attente',
    cancelled: 'Annulée',
    refund: 'Remboursée'
  }
  return labels[status.toLowerCase()] || status
}

const totalLinesAmount = (sale) => {
  if (!sale?.order_lines) return 0
  return sale.order_lines.reduce((sum, line) => sum + (Number(line?.total) || 0), 0)
}

const toggleSale = (saleId) => {
  if (!saleId) return
  const current = new Set(expandedSales.value)
  if (current.has(saleId)) {
    current.delete(saleId)
  } else {
    current.add(saleId)
  }
  expandedSales.value = current
}

const getRegisterId = (register) => {
  return register?.id ?? register?.cash_register_id ?? register?.cashRegisterId ?? null
}

const getPointOfSaleIdFromRegister = (register) => {
  return (
    register?.point_of_sale_id ??
    register?.pointOfSaleId ??
    register?.point_of_sale?.id ??
    null
  )
}

const getPointOfSaleId = (pointOfSale) => {
  return pointOfSale?.id ?? pointOfSale?.point_of_sale_id ?? pointOfSale?.pointOfSaleId ?? null
}

const formatRegisterName = (register) => {
  if (!register) return 'Caisse'
  return register?.name || register?.label || `Caisse #${getRegisterId(register) ?? '—'}`
}

const formatPointOfSaleName = (pointOfSale) => {
  if (!pointOfSale) return 'Point de vente'
  return pointOfSale?.name || pointOfSale?.label || `Point de vente #${getPointOfSaleId(pointOfSale) ?? '—'}`
}

const getSaleCashRegisterId = (sale) => {
  return (
    sale?.cash_register_id ??
    sale?.cash_register?.id ??
    sale?.cashRegisterId ??
    null
  )
}

const getSalePointOfSaleId = (sale) => {
  return (
    sale?.point_of_sale_id ??
    sale?.pointOfSaleId ??
    sale?.cash_register?.point_of_sale_id ??
    sale?.cash_register?.point_of_sale?.id ??
    null
  )
}

const availableCashRegisters = computed(() => {
  if (!pointOfSaleFilter.value) {
    return cashRegisters.value
  }
  return cashRegisters.value.filter((register) => {
    const registerPosId = getPointOfSaleIdFromRegister(register)
    return String(registerPosId ?? '') === pointOfSaleFilter.value
  })
})

const filteredSales = computed(() => {
  let filtered = sales.value.filter((sale) => sale && typeof sale === 'object')

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()

    filtered = filtered.filter((sale) => {
      const ticketValue = sale.ticket_number != null ? String(sale.ticket_number) : ''
      const dateValue = formatDate(sale.created_at)

      const ticketMatch = ticketValue.toLowerCase().includes(query)
      const dateMatch = dateValue.toLowerCase().includes(query)
      const productMatch = sale.order_lines?.some((line) => {
        const name = typeof line?.product?.name === 'string' ? line.product.name : ''
        return name.toLowerCase().includes(query)
      })

      return ticketMatch || dateMatch || productMatch
    })
  }

  if (startDate.value) {
    const start = new Date(startDate.value)
    start.setHours(0, 0, 0, 0)
    filtered = filtered.filter((sale) => {
      const saleDate = new Date(sale.created_at)
      return saleDate >= start
    })
  }

  if (endDate.value) {
    const end = new Date(endDate.value)
    end.setHours(23, 59, 59, 999)
    filtered = filtered.filter((sale) => {
      const saleDate = new Date(sale.created_at)
      return saleDate <= end
    })
  }

  if (cashRegisterFilter.value) {
    filtered = filtered.filter((sale) => {
      const id = getSaleCashRegisterId(sale)
      return String(id ?? '') === cashRegisterFilter.value
    })
  }

  if (pointOfSaleFilter.value) {
    filtered = filtered.filter((sale) => {
      const id = getSalePointOfSaleId(sale)
      return String(id ?? '') === pointOfSaleFilter.value
    })
  }

  return filtered
})

watch(pointOfSaleFilter, () => {
  if (!cashRegisterFilter.value) return
  const isValid = availableCashRegisters.value.some((register) => {
    const registerId = getRegisterId(register)
    return String(registerId ?? '') === cashRegisterFilter.value
  })
  if (!isValid) {
    cashRegisterFilter.value = ''
  }
})

const fetchActiveSession = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-session/my-active-session`, {
      headers: authHeaders()
    })

    const session = data?.data || data || null
    if (!session) {
      loadError.value = 'Aucune session de caisse active.'
      return null
    }

    return session
  } catch (error) {
    console.error('Erreur session active:', error.response?.data || error.message)
    loadError.value = error.response?.data?.message || error.message || 'Impossible de récupérer la session active.'
    throw error
  }
}

const fetchSales = async (params = {}) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/sales`, {
      params,
      headers: {
        'Content-Type': 'application/json',
        ...authHeaders()
      }
    })

    const payload = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    sales.value = payload.filter((sale) => sale && typeof sale === 'object')
    expandedSales.value = new Set()
  } catch (error) {
    console.error('Erreur chargement ventes:', error.response?.data || error.message)
    loadError.value = error.response?.data?.message || 'Impossible de charger les ventes de la session.'
    throw error
  }
}

const extractArray = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.items)) return payload.items
  if (Array.isArray(payload?.results)) return payload.results
  return []
}

const fetchCashRegisters = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers`, {
      params: { per_page: 500 },
      headers: authHeaders()
    })
    const registers = extractArray(data?.data ?? data)
    const unique = []
    const seen = new Set()
    registers.forEach((register) => {
      const id = getRegisterId(register)
      if (id == null || seen.has(id)) return
      seen.add(id)
      unique.push(register)
    })
    cashRegisters.value = unique
  } catch (error) {
    console.error('Erreur chargement caisses:', error.response?.data || error.message)
  }
}

const fetchPointOfSales = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/point-of-sales`, {
      params: { per_page: 500 },
      headers: authHeaders()
    })
    const points = extractArray(data?.data ?? data)
    const unique = []
    const seen = new Set()
    points.forEach((point) => {
      const id = getPointOfSaleId(point)
      if (id == null || seen.has(id)) return
      seen.add(id)
      unique.push(point)
    })
    pointOfSales.value = unique
  } catch (error) {
    console.error('Erreur chargement points de vente:', error.response?.data || error.message)
  }
}

onMounted(async () => {
  loading.value = true
  loadError.value = ''

  try {
    await loadUserData()
  } catch (error) {
    console.error('Erreur chargement utilisateur:', error)
  }

  try {
    if (isAdmin.value) {
      sessionId.value = null
      await Promise.allSettled([fetchSales(), fetchCashRegisters(), fetchPointOfSales()])
    } else {
      const session = await fetchActiveSession()
      if (!session) return
      sessionId.value = session.id
      await fetchSales({
        cash_register_session_id: session.id,
        user_id: currentUser.value?.id ?? null
      })
    }
  } catch (error) {
    if (!loadError.value) {
      loadError.value = error.message || 'Une erreur est survenue lors du chargement des ventes.'
    }
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.sales-view {
  min-height: 100vh;
  padding: 3rem 1.5rem;
  background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 100%);
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.sales-content {
  max-width: 1100px;
  margin: 0 auto;
  background: #fff;
  border-radius: 1.25rem;
  box-shadow: 0 25px 60px rgba(15, 23, 42, 0.12);
  padding: 2rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.sales-header {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: flex-start;
}

.sales-header h1 {
  font-size: 1.9rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.sales-header p {
  margin: 0.35rem 0 0;
  color: #64748b;
}

.sales-header .meta {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  color: #475569;
  font-weight: 600;
}

.utilities {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.utilities .search-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 0.85rem;
  border: 1px solid #cbd5f5;
  background: #f8fafc;
  color: #0f172a;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.utilities .search-input:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
}

.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1rem;
}

.filters label {
  display: grid;
  gap: 0.45rem;
  color: #475569;
  font-weight: 600;
  font-size: 0.9rem;
}

.filters select,
.filters input {
  padding: 0.6rem 0.75rem;
  border-radius: 0.75rem;
  border: 1px solid #cbd5f5;
  background: #f1f5f9;
  color: #0f172a;
}

.sales-list {
  display: grid;
  gap: 1.2rem;
}

.state {
  padding: 0.85rem 1.1rem;
  border-radius: 0.85rem;
  font-weight: 600;
  text-align: center;
}

.state.loading {
  background: rgba(59, 130, 246, 0.12);
  color: #1d4ed8;
}

.state.error {
  background: rgba(220, 38, 38, 0.12);
  color: #991b1b;
}

.state.empty {
  background: rgba(148, 163, 184, 0.16);
  color: #475569;
}

.sale-card {
  background: linear-gradient(135deg, rgba(248, 250, 252, 0.9), #fff);
  border: 1px solid rgba(226, 232, 240, 0.7);
  border-radius: 1rem;
  padding: 1.1rem 1.3rem;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
  display: grid;
  gap: 0.9rem;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.sale-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 25px 55px rgba(15, 23, 42, 0.12);
}

.sale-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  cursor: pointer;
}

.sale-card__header .info {
  flex: 1;
}

.sale-card__header h2 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: #0f172a;
}

.sale-card__header p {
  margin: 0.3rem 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-weight: 600;
  padding: 0.45rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.85rem;
  text-transform: capitalize;
}

.status--success {
  background: rgba(34, 197, 94, 0.12);
  color: #15803d;
}

.status--danger {
  background: rgba(248, 113, 113, 0.12);
  color: #b91c1c;
}

.status--pending {
  background: rgba(59, 130, 246, 0.12);
  color: #1d4ed8;
}

.actions {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.actions button {
  border: none;
  cursor: pointer;
  background: transparent;
  border-radius: 9999px;
  height: 2.2rem;
  width: 2.2rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.actions .ghost {
  color: #1e293b;
  background: rgba(226, 232, 240, 0.5);
}

.actions .ghost:hover {
  background: rgba(59, 130, 246, 0.16);
  color: #1d4ed8;
  transform: translateY(-1px);
}

.actions .ghost.danger {
  color: #b91c1c;
  background: rgba(254, 226, 226, 0.6);
}

.actions .ghost.danger:hover {
  background: rgba(248, 113, 113, 0.18);
  color: #991b1b;
}

.actions .toggle {
  background: rgba(226, 232, 240, 0.4);
  color: #475569;
}

.actions .toggle:hover {
  background: rgba(37, 99, 235, 0.18);
  color: #1d4ed8;
}

.sale-card__details {
  overflow: hidden;
  border-radius: 0.85rem;
  border: 1px solid rgba(226, 232, 240, 0.8);
  background: #fff;
}

.sale-card__details table {
  width: 100%;
  border-collapse: collapse;
}

.sale-card__details th,
.sale-card__details td {
  padding: 0.75rem 1rem;
  text-align: left;
  color: #334155;
  font-size: 0.9rem;
}

.sale-card__details thead {
  background: rgba(226, 232, 240, 0.4);
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.04em;
  color: #64748b;
}

.sale-card__details tbody tr:nth-child(even) {
  background: rgba(248, 250, 252, 0.7);
}

.sale-card__details tfoot {
  font-weight: 700;
  background: rgba(226, 232, 240, 0.4);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.expand-enter-active,
.expand-leave-active {
  transition: all 0.2s ease;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (max-width: 768px) {
  .sales-content {
    padding: 1.5rem;
  }

  .sales-header h1 {
    font-size: 1.6rem;
  }

  .actions button {
    height: 2rem;
    width: 2rem;
  }
}
</style>
