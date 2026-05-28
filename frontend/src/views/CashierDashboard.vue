<template>
  <div class="cashier-dashboard">
    <Profile />

    <main class="dashboard-wrapper">
      <header class="dashboard-header">
        <div>
          <h1>Tableau de bord caissier</h1>
          <p>Suivez l'activité de votre session de caisse en un coup d'œil.</p>
        </div>
        <button class="refresh-btn" type="button" @click="refreshDashboard" :disabled="isRefreshing">
          <span v-if="isRefreshing" class="loader"></span>
          <span v-else>Actualiser</span>
        </button>
      </header>

      <section v-if="session" class="session-section">
        <div class="session-card">
          <div class="session-header">
            <div class="session-title">
              <font-awesome-icon icon="fa-solid fa-cash-register" />
              <span>Session active</span>
            </div>
            <span class="session-status" :class="session.status?.toLowerCase()">{{ session.status || 'Active' }}</span>
          </div>

          <div class="session-grid">
            <div class="session-metric">
              <span class="label">Ouverture</span>
              <span class="value">{{ formatDate(session.opened_at) }}</span>
            </div>
            <div class="session-metric">
              <span class="label">Caissier</span>
              <span class="value">{{ session.user?.name || user?.name || '—' }}</span>
            </div>
            <div class="session-metric">
              <span class="label">Point de vente</span>
              <span class="value">{{ session.point_of_sale?.name || user?.point_of_sale_name || '—' }}</span>
            </div>
            <div class="session-metric">
              <span class="label">Fonds initial</span>
              <span class="value">{{ formatCurrency(session.opening_balance) }}</span>
            </div>
          </div>

          <div class="session-footer">
            <div>
              <span class="footer-label">Dernière synchronisation</span>
              <span class="footer-value">{{ lastUpdated }}</span>
            </div>
            <button class="quick-link" type="button" @click="goTo('cash-register-sessions')">
              Gérer les sessions
              <font-awesome-icon icon="fa-solid fa-arrow-right" />
            </button>
          </div>
        </div>

        <div class="stats-grid">
          <article class="stat-card">
            <div class="stat-icon primary">
              <font-awesome-icon icon="fa-solid fa-receipt" />
            </div>
            <div class="stat-info">
              <span class="stat-label">Total ventes</span>
              <span class="stat-value">{{ formatCurrency(stats.totalAmount) }}</span>
              <span class="stat-sub">{{ stats.totalTickets }} ticket(s)</span>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon secondary">
              <font-awesome-icon icon="fa-solid fa-scale-balanced" />
            </div>
            <div class="stat-info">
              <span class="stat-label">Ticket moyen</span>
              <span class="stat-value">{{ formatCurrency(stats.averageTicket) }}</span>
              <span class="stat-sub">Basé sur les ventes validées</span>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon warning">
              <font-awesome-icon icon="fa-solid fa-clock" />
            </div>
            <div class="stat-info">
              <span class="stat-label">Ventes en attente</span>
              <span class="stat-value">{{ stats.pendingSales }}</span>
              <span class="stat-sub">À finaliser rapidement</span>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon success">
              <font-awesome-icon icon="fa-solid fa-wallet" />
            </div>
            <div class="stat-info">
              <span class="stat-label">Transactions caisse</span>
              <span class="stat-value">{{ latestTransactions.length }}</span>
              <span class="stat-sub">Dernières 24 heures</span>
            </div>
          </article>
        </div>
      </section>

      <section v-else class="session-empty">
        <div class="empty-card">
          <font-awesome-icon icon="fa-solid fa-triangle-exclamation" />
          <h2>Aucune session active</h2>
          <p>Ouvrez une session de caisse pour activer votre tableau de bord.</p>
          <button class="primary" type="button" @click="goTo('cash-register-sessions')">Ouvrir une session</button>
        </div>
      </section>

      <section class="quick-actions">
        <h2>Actions rapides</h2>
        <div class="actions-grid">
          <button type="button" class="action-card" @click="goTo('direct')">
            <font-awesome-icon icon="fa-solid fa-bolt" />
            <span>Nouvelle vente directe</span>
          </button>
          <button type="button" class="action-card" @click="goTo('table')">
            <font-awesome-icon icon="fa-solid fa-chair" />
            <span>Service à table</span>
          </button>
          <button type="button" class="action-card" @click="goTo('cash-transactions')">
            <font-awesome-icon icon="fa-solid fa-money-bill-transfer" />
            <span>Transactions de caisse</span>
          </button>
          <button type="button" class="action-card" @click="goTo('user-sales')">
            <font-awesome-icon icon="fa-solid fa-list-check" />
            <span>Mes ventes</span>
          </button>
        </div>
      </section>

      <section v-if="latestTransactions.length" class="transactions-section">
        <div class="section-header">
          <h2>Derniers mouvements de caisse</h2>
          <button class="link" type="button" @click="goTo('cash-transactions')">
            Voir tout
            <font-awesome-icon icon="fa-solid fa-arrow-up-right-from-square" />
          </button>
        </div>
        <ul class="transaction-list">
          <li v-for="transaction in latestTransactions" :key="transaction.id" class="transaction-item">
            <div class="transaction-icon" :data-type="transaction.type">
              <font-awesome-icon :icon="transactionIcon(transaction.type)" />
            </div>
            <div class="transaction-info">
              <span class="transaction-amount">{{ formatCurrency(transaction.amount) }}</span>
              <span class="transaction-meta">
                {{ typeLabel(transaction.type) }} · Session #{{ transaction.session_id }}
              </span>
            </div>
            <span class="transaction-date">{{ formatDate(transaction.created_at) }}</span>
          </li>
        </ul>
      </section>

      <section v-if="!loading && session && !sales.length" class="empty-sales">
        <p>Aucune vente enregistrée pour cette session pour le moment.</p>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/apiClient'
import { API_BASE_URL } from '@/utils/api'
import Profile from './Profile.vue'
import { useCashTransactionStore } from '@/stores/cashTransactionStore'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faArrowRight,
  faArrowUpRightFromSquare,
  faBolt,
  faCashRegister,
  faChair,
  faClock,
  faListCheck,
  faMoneyBillTransfer,
  faReceipt,
  faScaleBalanced,
  faTriangleExclamation,
  faWallet
} from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(
  faCashRegister,
  faReceipt,
  faScaleBalanced,
  faClock,
  faWallet,
  faBolt,
  faChair,
  faMoneyBillTransfer,
  faListCheck,
  faArrowRight,
  faArrowUpRightFromSquare,
  faTriangleExclamation
)

const router = useRouter()
const cashTransactionStore = useCashTransactionStore()

const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
const session = ref(null)
const sales = ref([])
const loading = ref(true)
const isRefreshing = ref(false)
const lastUpdated = ref('—')

const formatCurrency = (value) => {
  const amount = Number(value || 0)
  return `${amount.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} Ar`
}

const formatDate = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const typeLabel = (type) => {
  switch ((type || '').toLowerCase()) {
    case 'sale':
      return 'Vente'
    case 'refund':
      return 'Remboursement'
    case 'adjustment':
      return 'Ajustement'
    default:
      return 'Transaction'
  }
}

const transactionIcon = (type) => {
  switch ((type || '').toLowerCase()) {
    case 'sale':
      return ['fas', 'cash-register']
    case 'refund':
      return ['fas', 'arrow-right']
    case 'adjustment':
      return ['fas', 'scale-balanced']
    default:
      return ['fas', 'wallet']
  }
}

const stats = computed(() => {
  if (!sales.value.length) {
    return {
      totalAmount: 0,
      totalTickets: 0,
      averageTicket: 0,
      pendingSales: 0
    }
  }

  const amounts = sales.value.map((sale) => Number(sale.total_amount || sale.total || 0))
  const totalAmount = amounts.reduce((total, value) => total + value, 0)
  const totalTickets = sales.value.length
  const averageTicket = totalTickets ? totalAmount / totalTickets : 0
  const pendingSales = sales.value.filter((sale) => !['paid', 'completed', 'completed_cash', 'completed_card'].includes((sale.status || '').toLowerCase())).length

  return { totalAmount, totalTickets, averageTicket, pendingSales }
})

const latestTransactions = computed(() => {
  return cashTransactionStore.transactions.slice(0, 5)
})

const getSaleUserId = (sale) => {
  if (!sale || typeof sale !== 'object') return null
  return sale.user_id ?? sale.userId ?? sale.user?.id ?? null
}

const goTo = (routeName) => {
  router.push({ name: routeName })
}

const fetchActiveSession = async () => {
  try {
    const { data } = await apiClient.get('/my-active-session')
    session.value = data?.data || data || null
  } catch (error) {
    console.error('Erreur lors de la récupération de la session active:', error.response?.data || error.message)
    session.value = null
  }
}

const fetchSales = async () => {
  if (!session.value || !user.value) {
    sales.value = []
    return
  }

  try {
    const { data } = await apiClient.get('/sales/current-session', {
      params: {
        cash_register_session_id: session.value.id
      }
    })
    const sessionSales = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    const userSales = sessionSales.filter((sale) => String(getSaleUserId(sale) ?? '') === String(user.value.id))
    sales.value = userSales.length ? userSales : sessionSales
  } catch (error) {
    console.error('Erreur lors du chargement des ventes de la session:', error.response?.data || error.message)
    sales.value = []
  }
}

const fetchTransactions = async () => {
  try {
    await cashTransactionStore.fetchTransactions()
  } catch (error) {
    console.error('Erreur lors du chargement des transactions de caisse:', error)
  }
}

const updateTimestamp = () => {
  lastUpdated.value = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

const loadDashboard = async () => {
  loading.value = true
  try {
    await fetchActiveSession()
    if (session.value) {
      await Promise.all([fetchSales(), fetchTransactions()])
    } else {
      cashTransactionStore.transactions = []
    }
    updateTimestamp()
  } finally {
    loading.value = false
  }
}

const refreshDashboard = async () => {
  isRefreshing.value = true
  await loadDashboard()
  isRefreshing.value = false
}

onMounted(async () => {
  await loadDashboard()
})
</script>

<style scoped>
.cashier-dashboard {
  min-height: 100vh;
  background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
}

.dashboard-wrapper {
  max-width: 1200px;
  margin: 140px auto 48px;
  padding: 0 24px 48px;
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 24px;
  padding: 24px 32px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.1);
  border: 1px solid rgba(148, 163, 184, 0.2);
}

.dashboard-header h1 {
  margin: 0;
  font-size: 1.9rem;
  font-weight: 700;
  color: #0f172a;
}

.dashboard-header p {
  margin: 6px 0 0;
  color: #475569;
  font-size: 0.95rem;
}

.refresh-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.65rem 1.4rem;
  border-radius: 12px;
  border: 1px solid rgba(37, 99, 235, 0.1);
  background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 14px 30px rgba(37, 99, 235, 0.25);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: wait;
  box-shadow: none;
}

.refresh-btn:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 18px 38px rgba(37, 99, 235, 0.3);
}

.loader {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 3px solid rgba(255, 255, 255, 0.6);
  border-top-color: #fff;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.session-section {
  display: grid;
  gap: 24px;
}

.session-card {
  background: linear-gradient(135deg, #1d4ed8 0%, #312e81 100%);
  color: #fff;
  border-radius: 24px;
  padding: 28px;
  box-shadow: 0 20px 50px rgba(30, 64, 175, 0.45);
}

.session-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.session-title {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 1.1rem;
  font-weight: 600;
}

.session-status {
  padding: 6px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.2);
  font-weight: 600;
  text-transform: capitalize;
}

.session-status.closed {
  background: rgba(220, 38, 38, 0.2);
}

.session-status.open {
  background: rgba(74, 222, 128, 0.2);
}

.session-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 18px;
}

.session-metric {
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: rgba(255, 255, 255, 0.12);
  padding: 16px;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.session-metric .label {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.8;
}

.session-metric .value {
  font-size: 1.1rem;
  font-weight: 700;
}

.session-footer {
  margin-top: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.footer-label {
  display: block;
  font-size: 0.8rem;
  opacity: 0.75;
}

.footer-value {
  font-weight: 600;
}

.quick-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}

.quick-link:hover {
  background: rgba(255, 255, 255, 0.25);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 18px;
}

.stat-card {
  display: flex;
  gap: 16px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 20px;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
  border: 1px solid rgba(226, 232, 240, 0.9);
}

.stat-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.3rem;
}

.stat-icon.primary {
  background: linear-gradient(135deg, #2563eb, #1e3a8a);
}

.stat-icon.secondary {
  background: linear-gradient(135deg, #6366f1, #4338ca);
}

.stat-icon.warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-icon.success {
  background: linear-gradient(135deg, #10b981, #047857);
}

.stat-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-label {
  font-size: 0.85rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.stat-sub {
  font-size: 0.85rem;
  color: #94a3b8;
}

.session-empty {
  display: flex;
  justify-content: center;
}

.empty-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 48px 40px;
  text-align: center;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 24px;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
  border: 1px solid rgba(226, 232, 240, 0.9);
  max-width: 420px;
}

.empty-card svg {
  font-size: 2rem;
  color: #f97316;
}

.empty-card h2 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.empty-card p {
  margin: 0;
  color: #475569;
}

.empty-card .primary {
  margin-top: 12px;
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 18px 40px rgba(37, 99, 235, 0.25);
}

.quick-actions h2,
.transactions-section h2 {
  margin: 0 0 16px;
  font-size: 1.3rem;
  font-weight: 700;
  color: #0f172a;
}

.quick-actions {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 24px;
  padding: 28px;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
  border: 1px solid rgba(226, 232, 240, 0.9);
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 18px;
}

.action-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  padding: 20px;
  border-radius: 18px;
  border: 1px solid rgba(203, 213, 225, 0.9);
  background: linear-gradient(135deg, rgba(248, 250, 252, 0.9), rgba(224, 231, 255, 0.8));
  color: #1e293b;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.action-card svg {
  font-size: 1.4rem;
  color: #2563eb;
}

.action-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 30px rgba(37, 99, 235, 0.18);
}

.transactions-section {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 24px;
  padding: 28px;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
  border: 1px solid rgba(226, 232, 240, 0.9);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.section-header .link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  color: #2563eb;
  font-weight: 600;
  cursor: pointer;
}

.transaction-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.transaction-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 18px;
  border-radius: 16px;
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: rgba(248, 250, 252, 0.9);
}

.transaction-icon {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.transaction-icon[data-type='sale'] {
  background: linear-gradient(135deg, #22c55e, #16a34a);
}

.transaction-icon[data-type='refund'] {
  background: linear-gradient(135deg, #f97316, #ea580c);
}

.transaction-icon[data-type='adjustment'] {
  background: linear-gradient(135deg, #6366f1, #4338ca);
}

.transaction-icon[data-type=''] {
  background: linear-gradient(135deg, #94a3b8, #64748b);
}

.transaction-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.transaction-amount {
  font-weight: 700;
  color: #0f172a;
}

.transaction-meta {
  font-size: 0.85rem;
  color: #64748b;
}

.transaction-date {
  font-size: 0.85rem;
  color: #64748b;
  min-width: 120px;
  text-align: right;
}

.empty-sales {
  display: flex;
  justify-content: center;
  color: #64748b;
  font-weight: 600;
  padding-bottom: 24px;
}

@media (max-width: 768px) {
  .dashboard-wrapper {
    margin-top: 160px;
    padding: 0 16px 32px;
  }

  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .session-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }

  .actions-grid {
    grid-template-columns: 1fr 1fr;
  }

  .transaction-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .transaction-date {
    text-align: left;
  }
}
</style>
