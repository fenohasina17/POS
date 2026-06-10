<template>
  <div class="flex min-h-screen flex-1 flex-col">
    <div class="py-0 px-0">
      <section class="flex w-full flex-col gap-6">
        <!-- En-tête -->
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
                v-if="canShowBilletageButton"
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-indigo-700 disabled:opacity-60"
                @click="showCashCount = true"
                :disabled="!sessionId || sessionClosed || hasRecordedBilletage"
              >
                <i class="fas fa-coins text-xs"></i> Billetage
              </button>
            </div>
          </div>
        </header>

        <!-- Sélecteur de session -->
        <div v-if="canSelectSession" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <label class="block text-sm font-semibold text-slate-700">Session à traiter</label>
          <select v-model="selectedSessionId" @change="onSessionChange" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <option v-for="sess in openSessions" :key="sess.id" :value="sess.id">
              {{ sess.cash_register?.name }} - ouverte le {{ formatDate(sess.opened_at) }} ({{ sess.user?.name }})
            </option>
          </select>
        </div>

        <!-- Grille principale -->
        <div class="grid gap-6 xl:grid-cols-[1fr_420px_auto]">
          <!-- Récapitulatif -->
          <section class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4 shadow-md">
            <div class="mb-3 border-b border-slate-100 pb-3">
              <h2 class="text-lg font-semibold text-slate-900">Produits vendus</h2>
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase text-slate-400">Total tickets</p>
                <p class="mt-2 text-2xl font-bold text-slate-800">{{ ticketCount }}</p>
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

          <!-- Formulaire Billetage -->
          <form class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-md" @submit.prevent="submit">
            <div class="space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 class="text-lg font-semibold text-slate-900">Comptage des espèces</h2>
                </div>
                <span v-if="sessionClosed" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600">Session clôturée</span>
                <span v-else-if="hasRecordedBilletage" class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600">Billetage validé</span>
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
                    @focus="activeField = { type: 'denomination', value: denomination.value }"
                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 disabled:opacity-60"
                  />
                  <span class="text-right text-sm font-semibold text-slate-600">{{ formatCurrency(denominationTotal(denomination.value)) }}</span>
                </div>
              </div>
              
              <!-- Résultat -->
              <div v-if="validationAttempted || hasRecordedBilletage" class="rounded-2xl border p-5 shadow-sm"
                   :class="varianceStatus === 'conforme' ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'">
                <div class="flex items-start justify-between">
                  <div>
                    <h3 class="text-lg font-bold" :class="varianceStatus === 'conforme' ? 'text-emerald-800' : 'text-rose-800'">
                      {{ varianceStatusLabel }}
                    </h3>
                  </div>
                  <div class="text-right">
                    <p class="text-2xl font-black" :class="varianceStatus === 'conforme' ? 'text-emerald-700' : 'text-rose-700'">
                      {{ varianceAmount > 0 ? '+' : '' }}{{ formatCurrency(varianceAmount) }}
                    </p>
                  </div>
                </div>
              </div>

              <p v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-600">{{ errorMessage }}</p>
              <p v-if="successMessage" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-600">{{ successMessage }}</p>
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
              <button
                v-if="!sessionClosed"
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl px-5 py-2 text-sm font-semibold text-white shadow-md transition disabled:opacity-60"
                :class="[
                  sessionClosed ? 'bg-slate-400' : 'bg-indigo-600 hover:bg-indigo-700'
                ]"
                :disabled="isSubmitting || isLoading || !sessionId || sessionClosed || hasRecordedBilletage || !showCashCount || !canEditBilletage || !hasAnySale"
              >
                <i v-if="isSubmitting" class="fas fa-circle-notch animate-spin"></i>
                {{ hasRecordedBilletage ? 'Billetage déjà validé' : (isSubmitting ? 'Enregistrement...' : 'Valider le billetage') }}
              </button>

              <button
                type="button"
                @click="closeSession"
                class="inline-flex items-center gap-2 rounded-xl bg-rose-100 px-5 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-200 disabled:opacity-60"
                :disabled="isSubmitting || isLoading || !sessionId || sessionClosed || !canCloseSession || !hasRecordedBilletage"
              >
                Clôturer la session
              </button>
            </div>
          </form>

          <!-- Clavier numérique -->
          <aside class="hidden xl:block">
            <div class="sticky top-24 space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-md">
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
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/services/apiClient'
import NumericKeypad from '@/components/NumericKeypad.vue'
import { useAuth } from '@/composables/useAuth'

// Données
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
const { isAdmin, hasRole, loadUserData } = useAuth()

// États
const counts = reactive(Object.fromEntries(denominations.map(d => [d.value, 0])))
const activeField = ref(null)

const sessionId = ref(null)
const sessionClosed = ref(false)
const hasRecordedBilletage = ref(false)
const isLoading = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const showCashCount = ref(false)
const validationAttempted = ref(false)
const sessionSales = ref([])
const sessionData = ref(null)
const openSessions = ref([])
const selectedSessionId = ref(null)

// Computed
const hasAnySale = computed(() => sessionSales.value.length > 0)
const ticketCount = computed(() => sessionSales.value.length)

const sessionProductsCount = computed(() => {
  console.log('🔍 DEBUG: Computing sessionProductsCount, sales length:', sessionSales.value.length);
  const total = sessionSales.value.reduce((total, sale) => {
    const lines = sale.orderlines || [];
    const sum = lines.reduce((sum, line) => sum + (Number(line.quantity) || 0), 0);
    return total + sum;
  }, 0);
  console.log('🔍 DEBUG: Calculated total products:', total);
  return total;
})

const totalProductTypes = computed(() => {
  const productIds = new Set()
  sessionSales.value.forEach(sale => {
    sale.orderlines?.forEach(line => {
      if (line.product_id) productIds.add(line.product_id)
    })
  })
  return productIds.size
})

const totalCounted = computed(() => {
  return denominations.reduce((sum, d) => sum + denominationTotal(d.value), 0)
})

const varianceAmount = computed(() => {
  const starting = parseFloat(sessionData.value?.starting_amount) || 0
  
  const isCashPayment = (name) => {
    if (!name) return false;
    const normalized = name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    return ['esp', 'cash', 'liq'].some(keyword => normalized.includes(keyword));
  };

  // Sommer uniquement les montants des paiements en espèces pour chaque vente
  const totalCashSales = sessionSales.value.reduce((sum, sale) => {
    const cashPayments = sale.payments?.filter(p => isCashPayment(p.payment?.name)) || []
    const saleCash = cashPayments.reduce((pSum, p) => pSum + parseFloat(p.amount || 0), 0)
    return sum + saleCash
  }, 0)
  
  const expected = starting + totalCashSales
  
  // Si déjà validé, on utilise le montant réel stocké en base
  const counted = hasRecordedBilletage.value 
    ? parseFloat(sessionData.value?.actual_cash_amount || 0) 
    : totalCounted.value
  
  return counted - expected
})

const varianceStatus = computed(() => (varianceAmount.value === 0 ? 'conforme' : 'ecart'))
const varianceStatusLabel = computed(() => (varianceAmount.value === 0 ? 'Caisse conforme' : 'Écart détecté'))

// Permissions
const canSelectSession = computed(() => isAdmin.value || hasRole('gérant'))
const canEditBilletage = computed(() => isAdmin.value || hasRole('caissier'))
const canShowBilletageButton = computed(() => isAdmin.value || hasRole('caissier'))
const canCloseSession = computed(() => isAdmin.value || hasRole('gérant'))
const canAccessBilletage = computed(() => isAdmin.value || hasRole('gérant') || hasRole('caissier'))

const isKeypadDisabled = computed(() => isSubmitting.value || isLoading.value || sessionClosed.value || hasRecordedBilletage.value || !canEditBilletage.value)

// Utilitaires
const formatCurrency = (amount) => {
  const num = Number(amount) || 0
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(num) + ' Ar'
}

const denominationTotal = (value) => value * (Number(counts[value]) || 0)
const formatDate = (dateStr) => dateStr ? new Date(dateStr).toLocaleString('fr-FR') : ''

const resetForm = () => {
  denominations.forEach(d => counts[d.value] = 0)
  errorMessage.value = ''
  successMessage.value = ''
  validationAttempted.value = false
}

// Chargement
const fetchOpenSessions = async () => {
  try {
    const { data } = await apiClient.get('/cash-register-sessions/open')
    openSessions.value = Array.isArray(data) ? data : data?.data || []
    if (openSessions.value.length) {
      selectedSessionId.value = openSessions.value[0].id
      await fetchSessionData(selectedSessionId.value)
    } else {
      errorMessage.value = 'Aucune session ouverte.'
    }
  } catch (err) {
    errorMessage.value = 'Impossible de charger les sessions.'
  }
}

const fetchSessionData = async (id) => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await apiClient.get(`/cash-register-sessions/${id}`)
    const session = data?.data || data
    sessionData.value = session
    sessionId.value = session.id
    sessionClosed.value = Boolean(session.is_closed)
    hasRecordedBilletage.value = Boolean(session.is_bill_checked)

    const salesResponse = await apiClient.get('/sales', { params: { cash_register_session_id: id } })
    sessionSales.value = Array.isArray(salesResponse.data) ? salesResponse.data : salesResponse.data?.data || []
    
    // sessionProductsCount sera mis à jour automatiquement via la computed property
  } catch (err) {
    errorMessage.value = 'Erreur lors du chargement de la session.'
  } finally {
    isLoading.value = false
  }
}

const onSessionChange = () => { if (selectedSessionId.value) fetchSessionData(selectedSessionId.value) }

const submit = async () => {
  if (!canEditBilletage.value) return
  isSubmitting.value = true
  try {
    await apiClient.put(`/cash-register-sessions/${sessionId.value}`, {
      actual_cash_amount: totalCounted.value,
      is_bill_checked: true
    })
    successMessage.value = 'Billetage validé !'
    hasRecordedBilletage.value = true
    validationAttempted.value = true
  } catch (err) {
    errorMessage.value = 'Erreur lors de la validation.'
  } finally { isSubmitting.value = false }
}

const closeSession = async () => {
  if (!canCloseSession.value || !hasRecordedBilletage.value) return
  isSubmitting.value = true
  try {
    await apiClient.put(`/cash-register-sessions/${sessionId.value}`, {
      is_closed: true,
      actual_cash_amount: totalCounted.value,
      closed_at: new Date().toISOString()
    })
    router.push({ name: 'dashboard-overview' })
  } catch (err) {
    errorMessage.value = 'Erreur lors de la clôture.'
  } finally { isSubmitting.value = false }
}

const handleKeyPress = (key) => {
  if (!activeField.value) return
  const denom = activeField.value.value
  let val = String(counts[denom] || '0')
  if (key === 'DEL') counts[denom] = val.length > 1 ? parseInt(val.slice(0, -1)) : 0
  else if (key === 'C') counts[denom] = 0
  else counts[denom] = parseInt(val === '0' ? String(key) : val + key)
}

onMounted(async () => {
  await loadUserData()
  if (!canAccessBilletage.value) router.push({ name: 'dashboard-overview' })
  else await fetchOpenSessions()
})
</script>
