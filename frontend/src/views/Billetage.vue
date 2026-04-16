<template>
  <div class="min-h-screen bg-[linear-gradient(160deg,#eef2ff_0%,#f8fafc_100%)] px-4 py-6 md:px-6">
    <Profile />

    <section class="mx-auto flex w-full max-w-[1400px] flex-col gap-6">
      <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-rose-500">Remise a zero</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-900">Cloture et recapitulatif de session</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-500">
              Consultez toutes les ventes de la session en cours, puis lancez le billetage des especes recues
              pour comparer la caisse reelle avec les ventes enregistrees.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
              @click="resetForm"
              :disabled="isSubmitting || isLoading"
            >
              <i class="fas fa-rotate-left text-xs"></i>
              Reinitialiser
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="goToSummary"
              :disabled="isSubmitting || isLoading || !sessionId || !hasRecordedBilletage"
            >
              <i class="fas fa-receipt text-xs"></i>
              Voir le recapitulatif final
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
              @click="showCashCount = true"
            >
              <i class="fas fa-money-bill-wave text-xs"></i>
              Faire le billetage
            </button>
          </div>
        </div>
      </header>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_420px]">
        <section class="min-w-0 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
          <div class="mb-3 border-b border-slate-100 px-2 pb-3">
            <h2 class="text-lg font-semibold text-slate-900">Recapitulatif des ventes de la session</h2>
            <p class="text-sm text-slate-500">
              Cette liste reprend les ventes visibles dans <strong>Mes ventes</strong> pour la session active.
            </p>
          </div>

          <div class="mb-4 grid gap-3 px-2 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="item in categoryGroups"
              :key="item.label"
              class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
            >
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ item.label }}</p>
              <p class="mt-2 text-lg font-semibold text-slate-900">{{ item.productTypes }} produit(s)</p>
              <p class="mt-1 text-xs text-slate-500">{{ item.products }} article(s) vendu(s)</p>
            </article>

            <article class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Session</p>
              <p class="mt-2 text-lg font-semibold text-slate-900">{{ totalProductTypes }} produit(s)</p>
              <p class="mt-1 text-xs text-slate-500">{{ sessionProductsCount }} article(s) vendu(s)</p>
            </article>
          </div>

          <div v-if="categoryGroups.length" class="space-y-3 px-2">
            <article
              v-for="category in categoryGroups"
              :key="category.label"
              class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
            >
              <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3">
                <div>
                  <h3 class="text-base font-semibold text-slate-900">{{ category.label }}</h3>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ category.productTypes }} produit(s) distinct(s) • {{ category.products }} article(s)
                  </p>
                </div>
              </div>

              <div class="mt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Produits vendus</p>
                <ul v-if="category.items.length" class="space-y-2">
                  <li
                    v-for="item in category.items"
                    :key="item.name"
                    class="flex items-center justify-between rounded-2xl border border-white bg-white px-3 py-2 text-sm text-slate-700"
                  >
                    <span class="font-medium text-slate-900">{{ item.name }}</span>
                    <span class="text-xs font-semibold text-slate-500">x{{ item.quantity }}</span>
                  </li>
                </ul>
                <p v-else class="text-sm text-slate-400">Aucun produit detaille dans cette categorie.</p>
              </div>
            </article>
          </div>

          <div
            v-else
            class="mx-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500"
          >
            Aucun ticket trouve pour la session active.
          </div>
        </section>

        <form ref="formRef" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
          <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Billetage des especes</h2>
                <p class="text-sm text-slate-500">
                  Comptez uniquement les billets et pieces effectivement recus en caisse.
                </p>
              </div>
              <span
                v-if="sessionClosed"
                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-600"
              >
                Session cloturee
              </span>
              <span
                v-else-if="hasRecordedBilletage"
                class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600"
              >
                Billetage enregistre
              </span>
            </div>

            <div v-if="!sessionId" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
              Aucune session active n'est detectee pour enregistrer le billetage. Le recapitulatif reste visible, mais
              l'enregistrement du comptage necessite une session ouverte.
            </div>

            <div v-if="showCashCount" class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div
                v-for="denomination in denominations"
                :key="denomination.value"
                class="grid items-center gap-3 sm:grid-cols-[120px_minmax(0,1fr)_130px]"
              >
                <label :for="`denom-${denomination.value}`" class="text-sm font-semibold text-slate-700">
                  {{ denomination.label }}
                </label>
                <input
                  :id="`denom-${denomination.value}`"
                  v-model="counts[denomination.value]"
                  type="number"
                  inputmode="numeric"
                  min="0"
                  step="1"
                  :disabled="isSubmitting || isLoading || sessionClosed"
                  @focus="showKeyboard({ type: 'denomination', value: denomination.value })"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
                />
                <span class="text-right text-sm font-semibold text-slate-600">
                  {{ formatCurrency(denominationTotal(denomination.value)) }}
                </span>
              </div>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
              Cliquez sur <strong>Faire le billetage</strong> pour saisir le comptage des billets et pieces recues.
            </div>

            <p class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
              Montant compte :
              <strong class="font-semibold text-slate-900">{{ formatCurrency(actualTotal) }}</strong>
            </p>

            <div
              v-if="hasRecordedBilletage"
              class="space-y-3 rounded-2xl border px-4 py-4"
              :class="varianceCardClass"
            >
              <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">Controle d'ecart</h3>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="varianceBadgeClass">
                  {{ varianceStatusLabel }}
                </span>
              </div>

              <div class="grid gap-3 text-sm sm:grid-cols-2">
                <p class="flex items-center justify-between gap-3">
                  <span>Fond de caisse</span>
                  <strong>{{ formatCurrency(startingAmount) }}</strong>
                </p>
                <p class="flex items-center justify-between gap-3">
                  <span>Ventes especes</span>
                  <strong>{{ formatCurrency(cashSalesAmount) }}</strong>
                </p>
                <p class="flex items-center justify-between gap-3">
                  <span>Billetage valide</span>
                  <strong>{{ formatCurrency(actualTotal) }}</strong>
                </p>
                <p class="flex items-center justify-between gap-3">
                  <span>Ecart calcule</span>
                  <strong>{{ formatCurrency(varianceAmount) }}</strong>
                </p>
              </div>

              <p class="text-xs">
                Formule : billetage - fond de caisse - ventes especes
              </p>
            </div>
          </section>

          <div class="flex flex-wrap justify-end gap-3">
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isSubmitting || isLoading || !sessionId || sessionClosed || hasRecordedBilletage || !showCashCount"
            >
              <i v-if="isSubmitting" class="fas fa-circle-notch animate-spin text-xs"></i>
              <span v-if="isSubmitting">Enregistrement…</span>
              <span v-else>Enregistrer le billetage</span>
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-2xl bg-rose-50 px-5 py-2 text-sm font-semibold text-rose-600 shadow-sm transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
              @click="closeSession"
              :disabled="isSubmitting || isLoading || !sessionId || sessionClosed || !hasRecordedBilletage"
            >
              Cloturer la session
            </button>
          </div>

          <div class="space-y-2">
            <p
              v-if="hasRecordedBilletage"
              class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-600"
            >
              Le billetage est enregistre pour cette session.
            </p>
            <p
              v-if="errorMessage"
              class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-600"
            >
              {{ errorMessage }}
            </p>
            <p
              v-if="successMessage"
              class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-600"
            >
              {{ successMessage }}
            </p>
          </div>
        </form>
      </div>
    </section>

    <Keyboard
      v-if="keyboardVisible"
      :initial-position="keyboardPosition"
      @key-pressed="handleKeyPress"
      @close="hideKeyboard"
    />
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import Keyboard from '../components/tools/Keyboard.vue'
import { API_BASE_URL } from '@/utils/api'
import Profile from './Profile.vue'

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
const sessionSales = ref([])
const sessionData = ref(null)

const authHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error("Token d'authentification manquant")
  return { Authorization: `Bearer ${token}` }
}

const closeModal = () => {
  router.push({ name: 'cashier-dashboard' })
}

const hasUserInput = computed(() => {
  return denominations.some(denomination => Number(counts[denomination.value]) > 0)
})

const actualTotal = computed(() => {
  const billsTotal = denominations.reduce((sum, d) => sum + d.value * (Number(counts[d.value]) || 0), 0)
  return Number(billsTotal.toFixed(2))
})

const startingAmount = computed(() => Number(sessionData.value?.starting_amount ?? 0))

const normalizeText = (value) => {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

const isCashSale = (sale) => {
  const paymentName = normalizeText(
    sale?.payment_method ??
    sale?.payment_name ??
    sale?.payment?.name ??
    sale?.paymentType?.name
  )

  const paymentId = Number(sale?.payment_id ?? sale?.payment?.id ?? 0)
  return paymentId === 4 || ['espece', 'cash', 'liquide'].some((keyword) => paymentName.includes(keyword))
}

const cashSalesAmount = computed(() => {
  return Number(sessionSales.value.reduce((sum, sale) => {
    if (!isCashSale(sale)) return sum
    const amount = Number(sale?.total_amount ?? sale?.final_amount ?? sale?.total ?? 0)
    return sum + (Number.isFinite(amount) ? amount : 0)
  }, 0).toFixed(2))
})

const varianceAmount = computed(() => {
  return Number((actualTotal.value - startingAmount.value - cashSalesAmount.value).toFixed(2))
})

const varianceStatus = computed(() => {
  if (Math.abs(varianceAmount.value) < 0.01) return 'conforme'
  return varianceAmount.value > 0 ? 'positif' : 'negatif'
})

const varianceStatusLabel = computed(() => {
  if (varianceStatus.value === 'conforme') return 'Billetage conforme'
  if (varianceStatus.value === 'positif') return 'Ecart positif'
  return 'Ecart negatif'
})

const varianceCardClass = computed(() => {
  if (varianceStatus.value === 'conforme') return 'border-emerald-200 bg-emerald-50 text-emerald-700'
  if (varianceStatus.value === 'positif') return 'border-amber-200 bg-amber-50 text-amber-700'
  return 'border-rose-200 bg-rose-50 text-rose-700'
})

const varianceBadgeClass = computed(() => {
  if (varianceStatus.value === 'conforme') return 'bg-emerald-100 text-emerald-700'
  if (varianceStatus.value === 'positif') return 'bg-amber-100 text-amber-700'
  return 'bg-rose-100 text-rose-700'
})

const resolveCategoryLabel = (line) => {
  return (
    line?.product?.category?.name ??
    line?.category?.name ??
    line?.category_name ??
    'Sans categorie'
  )
}

const categoryGroups = computed(() => {
  const groups = new Map()

  sessionSales.value.forEach((sale) => {
    getSaleLines(sale).forEach((line) => {
      const label = line.categoryLabel
      if (!groups.has(label)) {
        groups.set(label, { label, products: 0, productTypes: 0, itemsMap: new Map() })
      }

      const current = groups.get(label)
      current.products += line.quantity
      if (!current.itemsMap.has(line.name)) {
        current.itemsMap.set(line.name, { name: line.name, quantity: 0 })
        current.productTypes += 1
      }
      current.itemsMap.get(line.name).quantity += line.quantity
    })
  })

  return Array.from(groups.values())
    .map((group) => ({
      label: group.label,
      products: group.products,
      productTypes: group.productTypes,
      items: Array.from(group.itemsMap.values()).sort((a, b) => b.quantity - a.quantity)
    }))
    .sort((a, b) => b.products - a.products)
})

const sessionProductsCount = computed(() => {
  return sessionSales.value.reduce((sum, sale) => {
    return sum + getSaleLines(sale).reduce((lineSum, line) => lineSum + line.quantity, 0)
  }, 0)
})

const totalProductTypes = computed(() => {
  return categoryGroups.value.reduce((sum, category) => sum + category.productTypes, 0)
})

const getSaleLines = (sale) => {
  const rawLines = Array.isArray(sale?.order_lines)
    ? sale.order_lines
    : Array.isArray(sale?.items)
      ? sale.items
      : Array.isArray(sale?.lines)
        ? sale.lines
        : []

  return rawLines.map((line, index) => ({
    key: line?.id ?? `${sale?.id ?? 'sale'}-${index}`,
    name: line?.product?.name ?? line?.name ?? 'Produit supprime',
    quantity: Number(line?.quantity ?? 0) || 0,
    categoryLabel: resolveCategoryLabel(line)
  }))
}

const denominationTotal = (value) => {
  return Number((value * (Number(counts[value]) || 0)).toFixed(2))
}

const formatCurrency = (amount) => {
  const number = Number(amount)
  if (!Number.isFinite(number)) return '0 Ar'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'MGA' }).format(number).replace('MGA', 'Ar').trim()
}

const resetForm = () => {
  denominations.forEach(d => { counts[d.value] = 0 })
  errorMessage.value = ''
  successMessage.value = ''
  activeField.value = null
  keyboardVisible.value = false
}

const goToSummary = () => {
  if (!sessionId.value) {
    errorMessage.value = 'Aucune session active pour afficher le récapitulatif.'
    return
  }
  if (!hasRecordedBilletage.value) {
    errorMessage.value = 'Enregistrez le billetage avant de consulter le récapitulatif.'
    return
  }
  router.push({ name: 'billetage-summary', params: { sessionId: sessionId.value } })
}

const fetchActiveSession = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-session/my-active-session`, {
      headers: authHeaders()
    })
    const session = data?.data || data || null
    if (!session) {
      errorMessage.value = 'Aucune session de caisse active.'
      sessionId.value = null
      sessionClosed.value = false
      hasRecordedBilletage.value = false
      sessionData.value = null
      return
    }
    sessionData.value = session
    sessionId.value = session.id
    sessionClosed.value = Boolean(session.is_closed)
    hasRecordedBilletage.value = session.actual_cash_amount !== null && session.actual_cash_amount !== undefined
    if (hasRecordedBilletage.value) {
      resetCountsFromActualAmount(Number(session.actual_cash_amount ?? 0))
    }
    await fetchSessionSales(session.id)
  } catch (error) {
    console.error('Erreur récupération session:', error.response?.data || error.message)
    errorMessage.value = error.response?.data?.message || 'Impossible de récupérer la session de caisse active.'
    sessionId.value = null
    sessionClosed.value = false
    hasRecordedBilletage.value = false
    sessionSales.value = []
    sessionData.value = null
  } finally {
    isLoading.value = false
  }
}

const resetCountsFromActualAmount = (amount) => {
  let remaining = Math.max(0, Math.round(Number(amount) || 0))
  denominations.forEach((denomination) => {
    const qty = Math.floor(remaining / denomination.value)
    counts[denomination.value] = qty
    remaining -= qty * denomination.value
  })
}

const extractSalesArray = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.items)) return payload.items
  if (Array.isArray(payload?.results)) return payload.results
  return []
}

const getSaleSessionId = (sale) => {
  if (!sale || typeof sale !== 'object') return null
  return sale.cash_register_session_id ?? sale.cashRegisterSessionId ?? sale.session_id ?? sale.sessionId ?? null
}

const fetchSessionSales = async (activeSessionId) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/sales`, {
      params: { cash_register_session_id: activeSessionId },
      headers: authHeaders()
    })

    const fetchedSales = extractSalesArray(data).filter((sale) => sale && typeof sale === 'object')
    sessionSales.value = fetchedSales.filter((sale) => String(getSaleSessionId(sale) ?? '') === String(activeSessionId))
  } catch (error) {
    console.error('Erreur chargement des ventes de session:', error.response?.data || error.message)
    sessionSales.value = []
  }
}

const submit = async () => {
  if (!sessionId.value) {
    errorMessage.value = 'Aucune session active pour enregistrer le billetage.'
    return
  }
  if (sessionClosed.value) {
    errorMessage.value = 'La session est clôturée, impossible de modifier le billetage.'
    return
  }
  if (hasRecordedBilletage.value) {
    errorMessage.value = 'Le billetage a déjà été enregistré pour cette session.'
    return
  }

  if (!hasUserInput.value) {
    window.alert('Aucun montant n\'a été saisi pour le billetage.')
    errorMessage.value = 'Aucun montant saisi pour le billetage.'
    return
  }

  const confirmed = window.confirm('Confirmez-vous l\'enregistrement du billetage ?')
  if (!confirmed) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await axios.put(`${API_BASE_URL}/cash-register-sessions/${sessionId.value}`, {
      actual_cash_amount: actualTotal.value
    }, {
      headers: authHeaders()
    })
    successMessage.value = 'Billetage enregistré avec succès.'
    hasRecordedBilletage.value = true
    if (sessionData.value) {
      sessionData.value = {
        ...sessionData.value,
        actual_cash_amount: actualTotal.value
      }
    }
  } catch (error) {
    console.error('Erreur enregistrement billetage:', error.response?.data || error.message)
    errorMessage.value = error.response?.data?.message || "Impossible d'enregistrer le billetage."
  } finally {
    isSubmitting.value = false
  }
}

const closeSession = async () => {
  if (!sessionId.value) {
    errorMessage.value = 'Aucune session active à clôturer.'
    return
  }
  if (sessionClosed.value) {
    successMessage.value = 'La session est déjà clôturée.'
    return
  }
  if (!hasRecordedBilletage.value) {
    errorMessage.value = 'Enregistrez le billetage avant de clôturer la session.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await axios.put(`${API_BASE_URL}/cash-register-sessions/${sessionId.value}`, {
      actual_cash_amount: actualTotal.value,
      is_closed: true,
      closed_at: new Date().toISOString()
    }, {
      headers: authHeaders()
    })
    successMessage.value = 'Session clôturée avec succès.'
    sessionClosed.value = true
    sessionId.value = null
    sessionData.value = null
    sessionSales.value = []
    localStorage.removeItem('cashRegisterSession')
    localStorage.removeItem('cash_register_session')
    localStorage.removeItem('currentTicketNumber')
    router.push({ name: 'cash-printer' })
  } catch (error) {
    console.error('Erreur clôture session:', error.response?.data || error.message)
    errorMessage.value = error.response?.data?.message || "Impossible de clôturer la session."
  } finally {
    isSubmitting.value = false
  }
}

const KEYBOARD_WIDTH = 600
const KEYBOARD_HEIGHT = 400
const KEYBOARD_MARGIN = 16

const updateKeyboardPosition = () => {
  const formEl = formRef.value
  if (!formEl) return

  const rect = formEl.getBoundingClientRect()
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight

  let left = rect.right + KEYBOARD_MARGIN
  if (left + KEYBOARD_WIDTH > viewportWidth - KEYBOARD_MARGIN) {
    left = viewportWidth - KEYBOARD_WIDTH - KEYBOARD_MARGIN
  }
  left = Math.max(KEYBOARD_MARGIN, left)

  let top = rect.top
  if (top + KEYBOARD_HEIGHT > viewportHeight - KEYBOARD_MARGIN) {
    top = viewportHeight - KEYBOARD_HEIGHT - KEYBOARD_MARGIN
  }
  top = Math.max(KEYBOARD_MARGIN, top)

  keyboardPosition.value = { top, left }
}

const handleViewportChange = () => {
  updateKeyboardPosition()
}

const detachKeyboardListeners = () => {
  window.removeEventListener('resize', handleViewportChange)
  window.removeEventListener('scroll', handleViewportChange, true)
}

const showKeyboard = async (field) => {
  activeField.value = field
  keyboardVisible.value = true
  await nextTick()
  updateKeyboardPosition()
}

const handleKeyPress = (key) => {
  if (!activeField.value) return

  if (activeField.value.type === 'denomination') {
    const denominationValue = activeField.value.value
    const current = counts[denominationValue]
    const baseString = current === 0 || current === '' ? '' : String(current)

    if (key === 'BACKSPACE') {
      const updated = baseString.slice(0, -1)
      counts[denominationValue] = updated === '' ? '' : Number(updated)
      return
    }

    if (!/^[0-9]$/.test(key)) return
    const updated = `${baseString}${key}`
    counts[denominationValue] = Number(updated)
    return
  }
}

const hideKeyboard = () => {
  keyboardVisible.value = false
  activeField.value = null
}

onMounted(fetchActiveSession)

watch(keyboardVisible, (isVisible) => {
  if (isVisible) {
    nextTick(() => {
      updateKeyboardPosition()
      window.addEventListener('resize', handleViewportChange)
      window.addEventListener('scroll', handleViewportChange, true)
    })
  } else {
    detachKeyboardListeners()
  }
})

onBeforeUnmount(() => {
  detachKeyboardListeners()
})
</script>
