<template>
  <div class="flex min-h-screen flex-1 flex-col">
    <div class="py-0 px-0">
      <section class="flex w-full flex-col gap-6">
        <!-- En-tête de la page -->
        <header class="rounded-3xl border border-slate-200 bg-white/80 backdrop-blur-sm p-6 shadow-lg">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.35em] text-rose-500">Sécurité caisse</p>
              <h1 class="mt-2 text-3xl font-bold text-slate-900">Clôture de session</h1>
              <p class="mt-2 max-w-3xl text-sm text-slate-500">
                Comptez les espèces présentes dans la caisse. L’écart sera calculé automatiquement.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50"
                @click="resetForm"
                :disabled="isSubmitting || isLoading"
              >
                <i class="fas fa-rotate-left text-xs"></i> Réinitialiser
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-indigo-700 disabled:opacity-60"
                @click="showCashCount = true"
                :disabled="!hasAnySale"
                :title="!hasAnySale ? 'Aucune vente dans cette session, billetage inutile' : 'Commencer le comptage'"
              >
                <i class="fas fa-coins text-xs"></i> Billetage
              </button>
            </div>
          </div>
        </header>

        <!-- Sélecteur de session (admin/manager) -->
        <div v-if="canSelectSession" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <label class="block text-sm font-semibold text-slate-700">Session à traiter</label>
          <select v-model="selectedSessionId" @change="onSessionChange" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option v-for="sess in openSessions" :key="sess.id" :value="sess.id">
              {{ sess.cash_register?.name }} - ouverte le {{ formatDate(sess.opened_at) }} ({{ sess.user?.name }})
            </option>
          </select>
          <p v-if="openSessions.length === 0" class="mt-2 text-sm text-amber-600">Aucune session ouverte pour ce point de vente.</p>
        </div>

        <!-- Grille principale : récapitulatif + billetage + clavier -->
        <div class="grid gap-6 xl:grid-cols-[1fr_420px_auto]">
          <!-- Colonne 1 : Récapitulatif des ventes -->
          <section class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4 shadow-md">
            <div class="mb-3 border-b border-slate-100 pb-3">
              <h2 class="text-lg font-semibold text-slate-900">Produits vendus</h2>
              <p class="text-sm text-slate-500">Liste des articles écoulés pendant la session (hors montants).</p>
            </div>

            <div v-if="loadingDetails" class="mb-3 rounded-xl bg-slate-100 p-2 text-center text-xs text-slate-600">
              Chargement des détails des produits… {{ loadingProgress }}%
              <div class="mt-1 h-1 w-full rounded-full bg-slate-200 overflow-hidden">
                <div class="h-full bg-indigo-500 transition-all duration-300" :style="{ width: loadingProgress + '%' }"></div>
              </div>
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase text-slate-400">Total tickets</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ sessionSales.length }}</p>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase text-slate-400">Articles vendus</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ sessionProductsCount }}</p>
              </div>
              <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase text-indigo-500">Produits distincts</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ totalProductTypes }}</p>
              </div>
            </div>
          </section>

          <!-- Colonne 2 : Formulaire de comptage -->
          <form ref="formRef" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-md" @submit.prevent="submit">
            <div class="space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 class="text-lg font-semibold text-slate-900">Comptage des espèces</h2>
                  <p class="text-sm text-slate-500">Saisissez le nombre de billets et pièces réellement présents dans la caisse.</p>
                </div>
                <span v-if="sessionClosed" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600">Session clôturée</span>
                <span v-else-if="hasRecordedBilletage" class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">Billetage validé</span>
              </div>

              <div v-if="!sessionId" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                <i class="fas fa-info-circle mr-2"></i> Aucune session active. Veuillez ouvrir une session depuis la page d’accueil.
              </div>

              <div v-if="showCashCount && sessionId && !sessionClosed && hasAnySale" class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div v-for="denomination in denominations" :key="denomination.value" class="grid items-center gap-3 sm:grid-cols-[120px_minmax(0,1fr)_110px]">
                  <label :for="`denom-${denomination.value}`" class="text-sm font-semibold text-slate-700">{{ denomination.label }} Ar</label>
                  <input
                    :id="`denom-${denomination.value}`"
                    v-model="counts[denomination.value]"
                    type="number"
                    inputmode="numeric"
                    min="0"
                    step="1"
                    :disabled="isSubmitting || isLoading || sessionClosed || hasRecordedBilletage || !canEditBilletage"
                    @focus="showKeyboard({ type: 'denomination', value: denomination.value }, $event)"
                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 disabled:opacity-60"
                  />
                  <span class="text-right text-sm font-semibold text-slate-600">{{ formatCurrency(denominationTotal(denomination.value)) }}</span>
                </div>
              </div>
              <div v-else-if="showCashCount && !hasAnySale" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                <i class="fas fa-info-circle mr-2"></i> Aucune vente dans cette session. Le billetage n’est pas nécessaire.
              </div>
              <div v-else-if="!showCashCount" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                <i class="fas fa-calculator mb-2 text-2xl text-slate-300"></i>
                <p>Cliquez sur <strong>« Billetage »</strong> pour commencer le comptage.</p>
              </div>

              <!-- Résultat du contrôle -->
              <div v-if="validationAttempted || hasRecordedBilletage" class="rounded-2xl border p-5 shadow-sm transition-all"
                   :class="varianceStatus === 'conforme' ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'">
                <div class="flex items-start justify-between">
                  <div>
                    <h3 class="text-lg font-bold" :class="varianceStatus === 'conforme' ? 'text-emerald-800' : 'text-rose-800'">
                      {{ varianceStatusLabel }}
                    </h3>
                    <p class="text-sm opacity-80 mt-1">
                      {{ varianceStatus === 'conforme'
                         ? 'La caisse est équilibrée.'
                         : (varianceAmount > 0 ? 'Il y a un excédent de fonds.' : 'Il manque des fonds en caisse.') }}
                    </p>
                  </div>
                  <div class="text-right">
                    <p class="text-xs uppercase font-black opacity-60">Écart final</p>
                    <p class="text-2xl font-black" :class="varianceStatus === 'conforme' ? 'text-emerald-700' : 'text-rose-700'">
                      {{ varianceAmount > 0 ? '+' : '' }}{{ formatCurrency(varianceAmount) }}
                    </p>
                  </div>
                </div>

                <div class="mt-4 space-y-2 rounded-xl bg-white/60 p-3 border border-white/40">
                  <p class="text-[10px] font-black uppercase text-slate-400 mb-2">Détails du calcul</p>
                  <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Fond de caisse (A)</span>
                    <span class="font-bold text-slate-700">{{ formatCurrency(sessionData?.starting_amount || 0) }}</span>
                  </div>
                  <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Ventes Espèces (B)</span>
                    <span class="font-bold text-emerald-600">+ {{ formatCurrency(cashSalesAmount) }}</span>
                  </div>
                  <div class="flex justify-between text-xs border-b border-slate-200 pb-2">
                    <span class="text-slate-500 text-[10px] italic">(Somme des tickets payés en espèce)</span>
                  </div>
                  <div class="flex justify-between text-xs pt-1">
                    <span class="font-black text-slate-600 uppercase">Théorique attendu (A + B)</span>
                    <span class="font-black text-slate-800">{{ formatCurrency(Number(sessionData?.starting_amount || 0) + cashSalesAmount) }}</span>
                  </div>
                  <div class="flex justify-between text-xs pt-1 border-t border-slate-200 mt-1">
                    <span class="font-black text-indigo-600 uppercase tracking-tighter">Réel Compté (Billetage)</span>
                    <span class="font-black text-indigo-700">{{ formatCurrency(actualTotal) }}</span>
                  </div>
                </div>

                <div v-if="varianceAmount !== 0 && !hasRecordedBilletage" class="mt-4 animate-in fade-in slide-in-from-top-2 duration-300">
                  <label class="block text-xs font-black uppercase text-rose-600 mb-1">Pourquoi y a-t-il un écart ? (Obligatoire)</label>
                  <textarea
                    v-model="discrepancyExplanation"
                    rows="3"
                    class="w-full rounded-xl border border-rose-300 bg-white p-3 text-sm text-slate-800 shadow-inner outline-none transition focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                    placeholder="Ex: Erreur rendu monnaie ticket #12, retrait pour achat fournitures non saisi..."
                  ></textarea>
                </div>

                <div v-if="varianceAmount !== 0" class="mt-4 flex gap-2">
                  <button
                    type="button"
                    @click="showSalesLines = true"
                    class="flex-1 rounded-xl bg-white/80 border border-rose-200 py-2 text-xs font-bold text-rose-700 shadow-sm transition hover:bg-rose-100"
                  >
                    <i class="fas fa-list-ul mr-1"></i> Analyser tickets
                  </button>
                  <button
                    type="button"
                    @click="showSessionDetails = true"
                    class="flex-1 rounded-xl bg-white/80 border border-rose-200 py-2 text-xs font-bold text-rose-700 shadow-sm transition hover:bg-rose-100"
                  >
                    <i class="fas fa-info-circle mr-1"></i> Détails session
                  </button>
                </div>
              </div>

              <p v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-600">{{ errorMessage }}</p>
              <p v-if="successMessage" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-600">{{ successMessage }}</p>
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
              <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-indigo-700 disabled:opacity-60"
                :disabled="isSubmitting || isLoading || !sessionId || sessionClosed || hasRecordedBilletage || !showCashCount || !canEditBilletage || !hasAnySale"
              >
                <i v-if="isSubmitting" class="fas fa-circle-notch animate-spin"></i>
                {{ isSubmitting ? 'Enregistrement...' : (validationAttempted && varianceAmount !== 0 ? 'Confirmer la justification' : 'Valider le billetage') }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-rose-100 px-5 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-200 disabled:opacity-60"
                @click="closeSession"
                :disabled="isSubmitting || isLoading || !sessionId || sessionClosed || !hasRecordedBilletage || (!isAdmin && !hasRole('gerant'))"
              >
                Clôturer la session
              </button>
            </div>
          </form>

          <!-- Colonne 3 : Clavier numérique fixe (visible sur écran large) -->
          <aside class="hidden xl:block">
            <div class="sticky top-24 space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-md">
              <div class="mb-3 border-b border-slate-100 pb-3">
                <h2 class="text-lg font-semibold text-slate-900">Clavier numérique</h2>
                <p class="text-sm text-slate-500">Utilisez ce clavier pour saisir les quantités.</p>
              </div>
              <NumericKeypad
                :disabled="isKeypadDisabled"
                @press="handleKeyPress"
                @delete="() => handleKeyPress('DEL')"
              />
            </div>
          </aside>
        </div>
      </section>
    </div>

    <!-- Modal Détails des tickets -->
    <div v-if="showSalesLines" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
      <div class="flex h-full max-h-[90vh] w-full max-w-[95vw] flex-col rounded-3xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 p-6">
          <div>
            <h3 class="text-xl font-bold text-slate-900">Détails des tickets</h3>
            <p class="text-sm text-slate-500">Liste complète des ventes de la session</p>
          </div>
          <button @click="showSalesLines = false" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 transition-colors">
            <FontAwesomeIcon :icon="faXmark" class="text-xl" />
          </button>
        </header>
        <div class="flex-1 overflow-y-auto p-4">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10">
            <div v-for="sale in sessionSales" :key="sale.id" class="flex flex-col rounded-xl border border-slate-100 bg-slate-50 p-3 shadow-sm transition hover:shadow-md">
              <div class="mb-2 border-b border-slate-200 pb-1">
                <p class="text-[10px] font-black uppercase tracking-tighter text-slate-400">Vente #{{ sale.sale_number || sale.ticket_number || sale.id }}</p>
                <p class="text-xs font-bold text-indigo-600">{{ formatCurrency(sale.final_amount) }}</p>
              </div>
              <ul class="flex-1 space-y-1">
                <li v-for="line in sale.order_lines" :key="line.id" class="flex flex-col border-b border-slate-100 last:border-0 pb-1">
                  <span class="truncate text-[9px] font-medium text-slate-700" :title="line.product?.name || line.name">
                    {{ line.product?.name || line.name }}
                  </span>
                  <div class="flex items-center justify-between text-[8px] font-bold text-slate-500">
                    <span>x{{ line.quantity }}</span>
                    <span>{{ formatCurrency(line.total) }}</span>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Détails Session -->
    <div v-if="showSessionDetails" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-100 p-6">
          <h3 class="text-xl font-bold text-slate-900">Détails de la session</h3>
          <button @click="showSessionDetails = false" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-rose-600 transition-colors">
            <FontAwesomeIcon :icon="faXmark" class="text-xl" />
          </button>
        </header>
        <div class="p-6">
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4 rounded-2xl bg-slate-50 p-4 text-sm">
              <div>
                <p class="text-[10px] font-bold uppercase text-slate-400">ID Session</p>
                <p class="font-semibold text-slate-700">#{{ sessionId }}</p>
              </div>
              <div>
                <p class="text-[10px] font-bold uppercase text-slate-400">Caissier</p>
                <p class="font-semibold text-slate-700">{{ sessionData?.user?.name }}</p>
              </div>
              <div>
                <p class="text-[10px] font-bold uppercase text-slate-400">Caisse</p>
                <p class="font-semibold text-slate-700">{{ sessionData?.cash_register?.name }}</p>
              </div>
              <div>
                <p class="text-[10px] font-bold uppercase text-slate-400">Ouverte le</p>
                <p class="font-semibold text-slate-700">{{ formatDate(sessionData?.opened_at) }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <h4 class="text-xs font-bold uppercase text-slate-400">Mouvements de caisse</h4>
              <div class="max-h-60 overflow-y-auto space-y-2">
                <div v-for="trans in cashTransactions" :key="trans.id" class="flex items-center justify-between rounded-xl border border-slate-100 p-3 text-xs">
                  <div>
                    <p class="font-semibold text-slate-700">{{ trans.description || trans.type }}</p>
                    <p class="text-[10px] text-slate-400">{{ formatDate(trans.created_at) }}</p>
                  </div>
                  <span :class="trans.amount >= 0 ? 'text-emerald-600' : 'text-rose-600'" class="font-bold">
                    {{ trans.amount >= 0 ? '+' : '' }}{{ formatCurrency(trans.amount) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Clavier virtuel flottant pour mobile/tablette -->
    <Keyboard v-if="keyboardVisible" :initial-position="keyboardPosition" @key-pressed="handleKeyPress" @close="hideKeyboard" />
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Keyboard from '../components/tools/Keyboard.vue'
import NumericKeypad from '@/components/NumericKeypad.vue'
import { API_BASE_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faXmark } from '@fortawesome/free-solid-svg-icons'

// Liste des billets / pièces
const denominations = [
  { value: 20000, label: '20 000' },
  { value: 10000, label: '10 000' },
  { value: 5000, label: '5 000' },
  { value: 2000, label: '2 000' },
  { value: 1000, label: '1 000' },
  { value: 500, label: '500' },
  { value: 200, label: '200' },
  { value: 100, label: '100' }
]

const router = useRouter()
const route = useRoute()
const { isAdmin, user: currentUser, hasRole, loadUserData } = useAuth()

// États principaux
const counts = reactive(Object.fromEntries(denominations.map(d => [d.value, 0])))
const keyboardVisible = ref(false)
const activeField = ref(null)
const keyboardPosition = ref({ top: 0, left: 0 })
const formRef = ref(null)

const sessionId = ref(null)
const sessionClosed = ref(false)
const hasRecordedBilletage = ref(false)
const isLoading = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const showCashCount = ref(false)
const discrepancyExplanation = ref('')
const validationAttempted = ref(false)
const sessionSales = ref([])
const sessionData = ref(null)
const cashTransactions = ref([])
const openSessions = ref([])
const selectedSessionId = ref(null)
const loadingDetails = ref(false)
const loadingProgress = ref(0)

const showSalesLines = ref(false)
const showSessionDetails = ref(false)

// Permissions
const canSelectSession = computed(() => isAdmin.value || hasRole('gerant'))
const canEditBilletage = computed(() => isAdmin.value || hasRole('caissier'))

const isKeypadDisabled = computed(() => {
  return isSubmitting.value || isLoading.value || sessionClosed.value || hasRecordedBilletage.value || !canEditBilletage.value || !hasAnySale.value
})

// ========== UTILITAIRES ==========
const authHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error('Token manquant')
  return { Authorization: `Bearer ${token}` }
}

const formatCurrency = (amount) => {
  const num = Number(amount)
  if (!Number.isFinite(num)) return '0 Ar'
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(num) + ' Ar'
}

const denominationTotal = (value) => value * (Number(counts[value]) || 0)

const formatDate = (dateStr) => dateStr ? new Date(dateStr).toLocaleString('fr-FR') : ''

const resetForm = () => {
  denominations.forEach(d => { counts[d.value] = 0 })
  errorMessage.value = ''
  successMessage.value = ''
  validationAttempted.value = false
  discrepancyExplanation.value = ''
}

// ========== CHARGEMENT OPTIMISÉ DES VENTES ==========
const fetchAllSalesFast = async (sessionId) => {
  let allSales = []
  let currentPage = 1
  let lastPage = 1
  try {
    do {
      const { data } = await axios.get(`${API_BASE_URL}/sales`, {
        params: {
          cash_register_session_id: sessionId,
          page: currentPage,
          per_page: 250
        },
        headers: authHeaders()
      })
      let items = []
      if (Array.isArray(data)) items = data
      else if (data?.data && Array.isArray(data.data)) {
        items = data.data
        lastPage = data.last_page || data.meta?.last_page || currentPage
      } else if (data?.items) items = data.items
      else items = []
      allSales.push(...items)
      currentPage++
    } while (currentPage <= lastPage)
    return allSales
  } catch (err) {
    console.error('Erreur chargement ventes:', err)
    return []
  }
}

const loadMissingLines = async (sales, concurrency = 5) => {
  const missing = sales.filter(s => !s.order_lines || s.order_lines.length === 0)
  if (missing.length === 0) return sales

  loadingDetails.value = true
  loadingProgress.value = 0
  const results = [...sales]
  let processed = 0

  for (let i = 0; i < missing.length; i += concurrency) {
    const batch = missing.slice(i, i + concurrency)
    await Promise.all(batch.map(async (sale) => {
      try {
        const { data } = await axios.get(`${API_BASE_URL}/sales/${sale.id}`, { headers: authHeaders() })
        const details = data?.data || data
        const index = results.findIndex(s => s.id === sale.id)
        if (index !== -1) results[index] = { ...sale, order_lines: details.order_lines || [] }
      } catch (err) {
        console.warn(`Erreur lignes vente ${sale.id}:`, err)
        const index = results.findIndex(s => s.id === sale.id)
        if (index !== -1) results[index] = { ...sale, order_lines: [] }
      }
      processed++
      loadingProgress.value = Math.round((processed / missing.length) * 100)
    }))
  }
  loadingDetails.value = false
  return results
}

const loadSessionSales = async (sessionId) => {
  isLoading.value = true
  sessionSales.value = []
  try {
    const rawSales = await fetchAllSalesFast(sessionId)
    const filtered = rawSales.filter(s => String(s.cash_register_session_id) === String(sessionId))
    const enriched = await loadMissingLines(filtered)
    sessionSales.value = enriched
  } catch (err) {
    console.error('Erreur loadSessionSales:', err)
    sessionSales.value = []
  } finally {
    isLoading.value = false
  }
}

const fetchCashTransactions = async (sessionId) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-transactions/session/${sessionId}`, { headers: authHeaders() })
    if (Array.isArray(data)) cashTransactions.value = data
    else if (data?.in && data?.out) cashTransactions.value = [...data.in, ...data.out]
    else cashTransactions.value = []
  } catch (err) {
    console.error('Erreur transactions:', err)
    cashTransactions.value = []
  }
}

const fetchSessionData = async (id) => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-sessions/${id}`, { headers: authHeaders() })
    const session = data?.data || data
    if (!session?.id) throw new Error('Session introuvable')

    sessionData.value = session
    sessionId.value = session.id
    sessionClosed.value = Boolean(session.is_closed)
    hasRecordedBilletage.value = session.actual_cash_amount !== null

    await Promise.all([
      loadSessionSales(session.id),
      fetchCashTransactions(session.id)
    ])

    if (hasRecordedBilletage.value) {
      let remaining = Math.round(Number(session.actual_cash_amount) || 0)
      for (const d of denominations) {
        const qty = Math.floor(remaining / d.value)
        counts[d.value] = qty
        remaining -= qty * d.value
      }
    } else {
      resetForm()
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.message || 'Erreur chargement session.'
  } finally {
    isLoading.value = false
  }
}

const fetchOpenSessions = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-sessions/open`, { headers: authHeaders() })
    openSessions.value = Array.isArray(data) ? data : data?.data || []
    if (openSessions.value.length) {
      selectedSessionId.value = openSessions.value[0].id
      await fetchSessionData(selectedSessionId.value)
    } else {
      errorMessage.value = 'Aucune session ouverte.'
    }
  } catch (err) {
    errorMessage.value = 'Impossible de charger les sessions ouvertes.'
  }
}

const onSessionChange = () => {
  if (selectedSessionId.value) fetchSessionData(selectedSessionId.value)
}

// ========== PRODUITS VENDUS ==========
const resolveCategoryLabel = (line) => line?.product?.category?.name ?? line?.category?.name ?? line?.category_name ?? 'Sans catégorie'
const getSaleLines = (sale) => (sale?.order_lines || []).map(line => ({
  id: line.id,
  name: line.product?.name ?? line.name ?? 'Produit supprimé',
  quantity: Number(line.quantity ?? 0),
  categoryLabel: resolveCategoryLabel(line)
}))

const categoryGroups = computed(() => {
  const groups = new Map()
  for (const sale of sessionSales.value) {
    for (const line of getSaleLines(sale)) {
      const label = line.categoryLabel
      if (!groups.has(label)) {
        groups.set(label, { label, products: 0, productTypes: 0, itemsMap: new Map() })
      }
      const g = groups.get(label)
      g.products += line.quantity
      if (!g.itemsMap.has(line.name)) {
        g.itemsMap.set(line.name, { name: line.name, quantity: 0 })
        g.productTypes++
      }
      g.itemsMap.get(line.name).quantity += line.quantity
    }
  }
  return Array.from(groups.values()).map(g => ({
    label: g.label,
    products: g.products,
    productTypes: g.productTypes,
    items: Array.from(g.itemsMap.values()).sort((a, b) => b.quantity - a.quantity)
  })).sort((a, b) => b.products - a.products)
})

const sessionProductsCount = computed(() => {
  return sessionSales.value.reduce((sum, sale) => sum + getSaleLines(sale).reduce((s, l) => s + l.quantity, 0), 0)
})

const totalProductTypes = computed(() => categoryGroups.value.reduce((s, c) => s + c.productTypes, 0))

const hasAnySale = computed(() => sessionSales.value.length > 0)

// ========== BILLETAGE ==========
const actualTotal = computed(() => {
  return denominations.reduce((sum, d) => sum + d.value * (Number(counts[d.value]) || 0), 0)
})

const cashSalesAmount = computed(() => {
  return cashTransactions.value.filter(t => t.type === 'sale').reduce((s, t) => s + (Number(t.amount) || 0), 0)
})

const varianceAmount = computed(() => actualTotal.value - cashSalesAmount.value)
const varianceStatus = computed(() => varianceAmount.value === 0 ? 'conforme' : 'erreur')
const varianceStatusLabel = computed(() => varianceStatus.value === 'conforme' ? 'Caisse conforme' : (varianceAmount.value > 0 ? 'Excédent (Erreur)' : 'Manquant (Erreur)'))

const submit = async () => {
  errorMessage.value = ''

  if (!sessionId.value) { errorMessage.value = 'Session introuvable.'; return }
  if (sessionClosed.value) { errorMessage.value = 'Session déjà clôturée.'; return }
  if (hasRecordedBilletage.value) { errorMessage.value = 'Billetage déjà enregistré.'; return }
  if (!canEditBilletage.value) { errorMessage.value = 'Vous n’avez pas la permission de valider le billetage.'; return }
  if (!hasAnySale.value) { errorMessage.value = 'Aucune vente dans cette session. Le billetage n’est pas requis.'; return }
  if (actualTotal.value === 0) { errorMessage.value = 'Saisissez au moins un billet.'; return }

  if (!validationAttempted.value) {
    validationAttempted.value = true
    if (varianceAmount.value === 0) {
      if (!confirm(`Confirmer le billetage de ${formatCurrency(actualTotal.value)} ?`)) return
    } else {
      return // attend la justification
    }
  }

  if (varianceAmount.value !== 0 && !discrepancyExplanation.value.trim()) {
    errorMessage.value = 'Veuillez saisir une explication pour l’écart de caisse.'
    return
  }

  if (!confirm(`Confirmer l'enregistrement du billetage ?`)) return

  isSubmitting.value = true
  try {
    await axios.put(`${API_BASE_URL}/cash-register-sessions/${sessionId.value}`, {
      actual_cash_amount: actualTotal.value,
      is_bill_checked: true

    }, { headers: authHeaders() })

    if (varianceAmount.value !== 0 && discrepancyExplanation.value.trim()) {
      await axios.post(`${API_BASE_URL}/cash-register-sessions/${sessionId.value}/discrepancies`, {
        description: discrepancyExplanation.value,
        amount: varianceAmount.value
      }, { headers: authHeaders() })
    }

    successMessage.value = 'Billetage et justification enregistrés avec succès.'
    hasRecordedBilletage.value = true
    if (sessionData.value) sessionData.value.actual_cash_amount = actualTotal.value
    validationAttempted.value = false
    discrepancyExplanation.value = ''
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Erreur d’enregistrement.'
  } finally {
    isSubmitting.value = false
  }
}

const closeSession = async () => {
  if (!sessionId.value) { errorMessage.value = 'Session introuvable.'; return }
  if (sessionClosed.value) { successMessage.value = 'Session déjà clôturée.'; return }
  if (!hasRecordedBilletage.value) { errorMessage.value = 'Veuillez d’abord enregistrer le billetage.'; return }
  if (!canEditBilletage.value) { errorMessage.value = 'Vous n’avez pas la permission de clôturer la session.'; return }
  if (!confirm('Clôturer définitivement cette session ?')) return

  isSubmitting.value = true
  try {
    await axios.put(`${API_BASE_URL}/cash-register-sessions/${sessionId.value}`, {
      actual_cash_amount: actualTotal.value,
      is_closed: true,
      closed_at: new Date().toISOString()
    }, { headers: authHeaders() })
    successMessage.value = 'Session clôturée avec succès.'
    const closedSessionId = sessionId.value
    sessionClosed.value = true
    sessionId.value = null
    sessionData.value = null
    sessionSales.value = []
    cashTransactions.value = []
    await fetchOpenSessions()
    router.push({ name: 'billetage-summary', params: { sessionId: closedSessionId } })
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Erreur lors de la clôture.'
  } finally {
    isSubmitting.value = false
  }
}

// ========== CLAVIER VIRTUEL ==========
const showKeyboard = async (field, event) => {
  activeField.value = field
  if (window.innerWidth < 1280) {
    keyboardVisible.value = true
  }
  await nextTick()
  if (keyboardVisible.value) updateKeyboardPosition(event.target)
}

const handleKeyPress = (key) => {
  if (!activeField.value || activeField.value.type !== 'denomination') return
  const denom = activeField.value.value
  let val = counts[denom] === 0 || counts[denom] === '' ? 0 : counts[denom]
  let str = val === 0 ? '' : String(val)
  if (key === 'BACKSPACE' || key === 'DEL') {
    str = str.slice(0, -1)
    counts[denom] = str === '' ? 0 : Number(str)
    return
  }
  if (/^[0-9]$/.test(key)) counts[denom] = Number(str + key)
}

const updateKeyboardPosition = (targetElement) => {
  const el = targetElement || document.activeElement
  if (!el || el.tagName !== 'INPUT') return
  const rect = el.getBoundingClientRect()
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight
  const KEYBOARD_WIDTH = 640
  const KEYBOARD_HEIGHT = 280
  const MARGIN = 16
  let top = rect.bottom + MARGIN
  let left = rect.left
  if (left + KEYBOARD_WIDTH > viewportWidth - MARGIN) left = viewportWidth - KEYBOARD_WIDTH - MARGIN
  if (top + KEYBOARD_HEIGHT > viewportHeight - MARGIN) top = rect.top - KEYBOARD_HEIGHT - MARGIN
  keyboardPosition.value = {
    top: Math.max(MARGIN, top),
    left: Math.max(MARGIN, Math.max(0, left))
  }
}

const hideKeyboard = () => {
  keyboardVisible.value = false
  activeField.value = null
}

const handleViewportChange = () => { if (keyboardVisible.value) updateKeyboardPosition() }
const detachKeyboardListeners = () => {
  window.removeEventListener('resize', handleViewportChange)
  window.removeEventListener('scroll', handleViewportChange, true)
}

onMounted(async () => {
  await loadUserData()
  if (!isAdmin.value && !hasRole('gerant') && !hasRole('caissier')) {
    router.push({ name: 'dashboard-overview' })
    return
  }
  await fetchOpenSessions()
})

onBeforeUnmount(() => {
  detachKeyboardListeners()
})

watch(keyboardVisible, (visible) => {
  if (visible) {
    nextTick(() => {
      updateKeyboardPosition()
      window.addEventListener('resize', handleViewportChange)
      window.addEventListener('scroll', handleViewportChange, true)
    })
  } else {
    detachKeyboardListeners()
  }
})
</script>


