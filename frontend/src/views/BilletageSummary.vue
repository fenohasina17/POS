<template>
  <div class="billetage-summary-view">
    <Profile />

    <section class="page-section">
      <header class="page-header">
        <div>
          <h1>Résumé de la session</h1>
          <p v-if="sessionLabel" class="subtitle">{{ sessionLabel }}</p>
        </div>
        <div class="header-actions">
          <button type="button" class="btn" @click="goBack">
            Retour au billetage
          </button>
          <button
            type="button"
            class="btn primary"
            @click="printSummary"
            :disabled="loading || Boolean(errorMessage) || isPrinting"
          >
            <span v-if="isPrinting">Envoi en cours…</span>
            <span v-else>Imprimer</span>
          </button>
        </div>
      </header>

      <p v-if="printSuccess" class="feedback success-banner">{{ printSuccess }}</p>

      <section v-if="loading" class="card status-card">
        <p>Chargement du récapitulatif…</p>
      </section>

      <section v-else-if="errorMessage" class="card feedback error">
        {{ errorMessage }}
      </section>

      <section v-else class="summary-content" ref="summaryRef">
        <article class="card billetage-card">
          <h2 class="card-title">Cloture de session</h2>
          <ul class="billetage-stats">
            <li>
              <span class="label">Fond de caisse</span>
              <strong>{{ formatCurrency(startingAmount) }}</strong>
            </li>
            <li>
              <span class="label">Ventes especes</span>
              <strong>{{ formatCurrency(cashSalesTotal) }}</strong>
            </li>
            <li>
              <span class="label">Montant attendu en caisse</span>
              <strong>{{ formatCurrency(expectedCashAmount) }}</strong>
            </li>
            <li>
              <span class="label">Montant billetage</span>
              <strong>{{ formatCurrency(billetageAmount) }}</strong>
            </li>
            <li>
              <span class="label">Ecart de caisse</span>
              <strong :class="{ positive: differenceAmount > 0, negative: differenceAmount < 0 }">
                {{ formatCurrency(differenceAmount) }}
              </strong>
            </li>
          </ul>
        </article>

        <article class="card recap-card recap-card--categories">
          <h2 class="card-title">Ventes par catégorie</h2>
          <table v-if="categorySummary.length" class="category-table">
            <thead>
              <tr>
                <th class="category-col">Catégorie</th>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Montant</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="category in categorySummary" :key="category.category_id ?? category.category_name">
                <tr class="category-table__group-row">
                  <td colspan="4">
                    <div class="category-group__header">
                      <h3 class="category-title">{{ category.category_name }}</h3>
                      <span class="category-total">{{ formatCurrency(category.amount) }}</span>
                    </div>
                  </td>
                </tr>
                <tr v-if="!category.products.length">
                  <td class="list-empty" colspan="4">Aucun produit enregistré pour cette catégorie.</td>
                </tr>
                <tr
                  v-for="product in category.products"
                  :key="`${category.category_id ?? category.category_name}-${product.product_id ?? product.product_name}`"
                >
                  <td></td>
                  <td>{{ product.product_name }}</td>
                  <td class="numeric">{{ formatQuantity(product.quantity) }}</td>
                  <td class="numeric">{{ formatCurrency(product.amount) }}</td>
                </tr>
              </template>
            </tbody>
          </table>
          <p v-else class="list-empty">Aucune vente enregistrée.</p>
        </article>

        <article class="card recap-card payments-card">
          <h2 class="card-title">Montants par paiement</h2>
          <ul v-if="paymentSummary.length" class="payment-list">
            <li v-for="item in paymentSummary" :key="item.payment_id ?? item.payment_name">
              <span class="payment-name">{{ item.payment_name }}</span>
              <span class="payment-amount">{{ formatCurrency(item.total) }}</span>
            </li>
            <li class="payment-total">
              <span>Total des paiements</span>
              <span>{{ formatCurrency(totalPaymentsAmount) }}</span>
            </li>
          </ul>
          <p v-else class="list-empty">Aucun paiement enregistré.</p>
        </article>
      </section>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Profile from './Profile.vue'
import { API_BASE_URL } from '@/utils/api'

const route = useRoute()
const router = useRouter()

const summaryRef = ref(null)
const loading = ref(false)
const errorMessage = ref('')
const summaryData = ref(null)
const categorySummary = ref([])
const paymentSummary = ref([])
const isPrinting = ref(false)
const printSuccess = ref('')

const sessionId = computed(() => {
  const raw = route.params.sessionId ?? route.query.sessionId
  const parsed = Number(raw)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
})

const sessionInfo = computed(() => summaryData.value?.session ?? null)

const sessionLabel = computed(() => {
  if (!sessionInfo.value) return ''
  const id = sessionInfo.value.id ? `#${sessionInfo.value.id}` : ''
  const register = sessionInfo.value.cash_register?.name ? ` — ${sessionInfo.value.cash_register.name}` : ''
  return `Session ${id}${register}`.trim()
})

const removeDiacritics = (value) => {
  if (!value) return ''
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}

const billetageAmount = computed(() => Number(sessionInfo.value?.actual_cash_amount ?? 0))
const startingAmount = computed(() => Number(sessionInfo.value?.starting_amount ?? 0))

const cashSalesTotal = computed(() => {
  const keywords = ['cash', 'espe', 'liquide']
  return paymentSummary.value.reduce((sum, payment) => {
    const rawName = String(payment?.payment_name ?? '')
    const normalized = removeDiacritics(rawName).toLowerCase()
    const isCash = keywords.some(keyword => normalized.includes(keyword))
    if (!isCash) return sum
    const amount = Number(payment?.total ?? 0)
    return sum + (Number.isFinite(amount) ? amount : 0)
  }, 0)
})

const expectedCashAmount = computed(() => Number((startingAmount.value + cashSalesTotal.value).toFixed(2)))
const differenceAmount = computed(() => Number((billetageAmount.value - expectedCashAmount.value).toFixed(2)))

const totalPaymentsAmount = computed(() => {
  return paymentSummary.value.reduce((sum, payment) => {
    const amount = Number(payment?.total ?? 0)
    return sum + (Number.isFinite(amount) ? amount : 0)
  }, 0)
})

const authHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error("Token d'authentification manquant")
  return { Authorization: `Bearer ${token}` }
}

const formatCurrency = (amount) => {
  const number = Number(amount)
  if (!Number.isFinite(number)) return '0 Ar'
  return `${new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(Math.round(number))} Ar`
}

const formatQuantity = (value) => {
  const number = Number(value)
  if (!Number.isFinite(number)) return '0'
  return new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
    minimumFractionDigits: 0
  }).format(Math.round(number))
}

const fetchSummary = async () => {
  if (!sessionId.value) {
    errorMessage.value = 'Session introuvable.'
    return
  }

  loading.value = true
  errorMessage.value = ''

  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-sessions/${sessionId.value}/summary`, {
      headers: authHeaders()
    })

    summaryData.value = data || null
    categorySummary.value = Array.isArray(data?.categories)
      ? data.categories.map(category => ({
          ...category,
          quantity: Number(category?.quantity ?? 0),
          amount: Number(category?.amount ?? 0),
          products: Array.isArray(category?.products)
            ? category.products.map(product => ({
                ...product,
                quantity: Number(product?.quantity ?? 0),
                amount: Number(product?.amount ?? 0)
              }))
            : []
        }))
      : []
    paymentSummary.value = Array.isArray(data?.payments)
      ? data.payments.map(payment => ({
          ...payment,
          total: Number(payment?.total ?? 0),
          transactions: Number(payment?.transactions ?? 0)
        }))
      : []
  } catch (error) {
    console.error('Erreur chargement résumé billetage:', error.response?.data || error.message)
    errorMessage.value = error.response?.data?.message || 'Impossible de charger le récapitulatif.'
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.push({ name: 'billetage' })
}

const printSummary = async () => {
  if (!sessionId.value) return
  isPrinting.value = true
  printSuccess.value = ''
  errorMessage.value = ''
  try {
    await axios.post(`${API_BASE_URL}/printers/session-recap/${sessionId.value}`, {}, {
      headers: authHeaders()
    })
    printSuccess.value = 'Récapitulatif envoyé à l\'imprimante.'
  } catch (error) {
    console.error('Erreur impression recap:', error.response?.data || error.message)
    const backendMessage = error.response?.data?.message
    errorMessage.value = backendMessage || "Impossible d'envoyer le récapitulatif à l'imprimante."
  } finally {
    isPrinting.value = false
  }
}

onMounted(fetchSummary)
</script>

  <style scoped>
.billetage-summary-view {
  min-height: 100vh;
  background: #f8fafc;
  color: #0f172a;
}

.page-section {
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 2rem clamp(1rem, 3vw, 2.5rem) 3rem;
  display: grid;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
}

.page-header h1 {
  font-size: 1.9rem;
  font-weight: 700;
}

.subtitle {
  margin-top: 0.35rem;
  color: #64748b;
  font-size: 0.95rem;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.card {
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 15px 40px rgba(15, 23, 42, 0.08);
  padding: 1.5rem;
  display: grid;
  gap: 1.2rem;
}

.card-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: #1e293b;
}

.summary-content {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: 1fr;
}

.billetage-card .billetage-stats {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.75rem;
}

.billetage-stats li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.6rem 0.75rem;
  border-radius: 0.6rem;
  background: linear-gradient(135deg, rgba(6, 182, 212, 0.08), rgba(14, 116, 144, 0.08));
}

.billetage-stats .label {
  color: #475569;
  font-weight: 500;
}

.billetage-stats strong {
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}

.positive {
  color: #16a34a !important;
}

.negative {
  color: #dc2626 !important;
}

.list-empty {
  color: #94a3b8;
  font-size: 0.9rem;
}

.category-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}

.category-table thead {
  background: #0f172a;
  color: #f8fafc;
}

.category-table th,
.category-table td {
  padding: 0.75rem 0.85rem;
  border: 1px solid rgba(148, 163, 184, 0.35);
}

.category-table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.78rem;
  letter-spacing: 0.03em;
}

.category-table__group-row td {
  padding: 0;
  background: linear-gradient(135deg, rgba(96, 165, 250, 0.15), rgba(59, 130, 246, 0.1));
}

.category-group__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}

.category-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: #0f172a;
}

.category-total {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.35rem 0.9rem;
  border-radius: 9999px;
  background: rgba(15, 23, 42, 0.08);
  font-weight: 600;
  color: #0f172a;
}

.category-col {
  width: 22%;
}

.category-table td.numeric {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.payment-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.55rem;
}

.payment-list li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.62rem 0.85rem;
  background: linear-gradient(135deg, rgba(15, 118, 110, 0.06), rgba(13, 148, 136, 0.1));
  border-radius: 0.7rem;
}

.payment-list .payment-total {
  background: rgba(15, 23, 42, 0.08);
  font-weight: 700;
  color: #0f172a;
}

.payment-name {
  font-weight: 500;
  color: #1f2937;
}

.payment-amount {
  font-weight: 700;
  color: #0f766e;
}

.btn {
  border: none;
  padding: 0.6rem 1.1rem;
  border-radius: 0.75rem;
  background: rgba(148, 163, 184, 0.2);
  color: #1e293b;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn:hover {
  background: rgba(148, 163, 184, 0.3);
}

.btn.primary {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
}

.btn.primary:hover {
  background: linear-gradient(135deg, #1d4ed8, #1e3a8a);
}

.feedback {
  font-size: 0.95rem;
  text-align: center;
}

.feedback.error {
  color: #dc2626;
}

.feedback.success-banner {
  background: rgba(34, 197, 94, 0.12);
  color: #166534;
  border-radius: 0.75rem;
  padding: 0.75rem 1rem;
  margin: 0;
}

.status-card {
  text-align: center;
}

</style>
