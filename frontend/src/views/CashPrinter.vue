<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur">
    <div class="relative mx-4 w-full max-w-4xl rounded-3xl border border-slate-200 bg-white shadow-2xl">
      <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Connexion caisse</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Associer cette machine à une caisse</h1>
          <p class="mt-1 text-sm text-slate-500">
            Sélectionnez ou créez une caisse pour pouvoir ouvrir une session et encaisser des ventes.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-rose-200 hover:text-rose-600"
          @click="closeModal"
        >
          <i class="fas fa-xmark"></i>
          Fermer
        </button>
      </header>

      <div
        class="grid gap-6 px-6 py-6"
        :class="{
          'justify-items-center': !isAdmin,
          'lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]': isAdmin
        }"
      >
        <section
          class="w-full space-y-6 rounded-2xl border border-slate-200 bg-slate-50 p-5"
          :class="{ 'mx-auto max-w-2xl': !isAdmin }"
        >
          <div v-if="!isAdmin">
            <div class="rounded-2xl border border-white bg-white/90 px-5 py-5 shadow-sm">
              <h2 class="text-lg font-semibold text-slate-900">Machine actuelle</h2>
              <p class="mt-1 text-sm text-slate-500">
                Nom détecté :
                <span v-if="machineIdentifier" class="font-semibold text-indigo-600">{{ machineIdentifier }}</span>
                <span v-else class="text-amber-600">Non détecté</span>
              </p>

              <p v-if="hasActiveSession" class="mt-3 rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-600">
                Session ouverte sur <strong>{{ activeSession?.cash_register?.name }}</strong>.
                Utilisez «&nbsp;Résumé &amp; Continuer&nbsp;» pour reprendre vos ventes.
              </p>

              <div v-if="loadingRegisters" class="py-6 text-center text-slate-500">
                Chargement des caisses...
              </div>
              <div v-else class="mt-4 space-y-3">
                <div v-if="machineRegister">
                  <p class="text-sm text-slate-700">
                    Caisse associée : <strong>{{ machineRegister.name }}</strong>
                    <span v-if="machineRegister.point_of_sale?.name" class="text-slate-400">
                      — {{ machineRegister.point_of_sale.name }}
                    </span>
                  </p>
                  <p class="text-xs text-slate-400">
                    Statut :
                    <span :class="['font-semibold', statusBadgeClass(machineRegister.id)]">
                      {{ statusBadgeText(machineRegister.id) || 'Disponible' }}
                    </span>
                  </p>
                </div>
                <div v-else class="space-y-2">
                  <p class="text-sm font-semibold text-rose-600">
                    Aucune caisse n'est associée à cette machine. Créez-la avant de continuer.
                  </p>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                    @click="goToMachineManagement"
                  >
                    <i class="fas fa-plus"></i>
                    Créer / associer une caisse
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else>
            <p class="text-sm font-semibold text-slate-700">Sélectionner une caisse</p>
            <div class="mt-3">
              <div v-if="loadingRegisters" class="py-6 text-center text-slate-500">
                Chargement des caisses...
              </div>
              <div v-else>
                <div v-if="availableRegisters.length" class="grid gap-3 sm:grid-cols-2">
                  <button
                    v-for="register in availableRegisters"
                    :key="register.id"
                    type="button"
                    class="rounded-2xl border border-slate-200 px-4 py-3 text-left transition hover:border-indigo-200 hover:bg-indigo-50"
                    :class="{
                      connected: registerStatuses[register.id] === 'connected',
                      selected: selectedCashRegister === register.id,
                      disabled: isRegisterLocked(register.id)
                    }"
                    :aria-disabled="isRegisterLocked(register.id)"
                    :aria-pressed="selectedCashRegister === register.id"
                    :disabled="isRegisterLocked(register.id)"
                    :tabindex="isRegisterLocked(register.id) ? -1 : 0"
                    @click="selectCashRegister(register.id)"
                    @keyup.enter.prevent="selectCashRegister(register.id)"
                    @keyup.space.prevent="selectCashRegister(register.id)"
                  >
                    <div class="flex items-center gap-3">
                      <span class="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                        <i class="fas fa-desktop"></i>
                      </span>
                      <div>
                        <p class="font-semibold text-slate-900">{{ register.name }}</p>
                        <p class="text-xs text-slate-400">
                          {{ register.point_of_sale?.name || 'Sans point de vente' }}
                        </p>
                      </div>
                    </div>
                    <div class="mt-3">
                      <span
                        v-if="statusBadgeText(register.id)"
                        :class="['px-2 py-1 rounded text-xs font-semibold', statusBadgeClass(register.id)]"
                      >
                        {{ statusBadgeText(register.id) }}
                      </span>
                    </div>
                  </button>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                  Aucune caisse disponible.
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-3">
            <p v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-600">
              {{ errorMessage }}
            </p>
            <p v-if="machineIdentifier && !isAdmin" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
              Machine détectée : <strong>{{ machineIdentifier }}</strong>
            </p>
            <p v-if="machineIdentifierMessage" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-600">
              {{ machineIdentifierMessage }}
            </p>
          </div>

          <button
            type="button"
            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
            :class="{ 'pointer-events-none': isProcessing }"
            :disabled="connectButtonDisabled"
            @click="onConnectButtonClick"
          >
            <i class="fas fa-link"></i>
            {{ connectButtonText }}
          </button>

          <div v-if="isSelfConnected && !isAdmin" class="mt-4 flex flex-wrap gap-2">
            <button
              class="inline-flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-600 transition hover:bg-amber-100"
              type="button"
              @click="resetCashRegister"
            >
              <i class="fas fa-sync-alt"></i>
              Remise à zéro
            </button>
            <button
              class="inline-flex items-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-100"
              type="button"
              @click="performCashCount"
            >
              <i class="fas fa-money-bill-wave"></i>
              Billetage
            </button>
            <button
              class="inline-flex items-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-100"
              type="button"
              @click="viewSales"
            >
              <i class="fas fa-chart-line"></i>
              Mes ventes
            </button>
          </div>

          <p v-if="isSelfConnected" class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-600">
            ✅ Caisse connectée : {{ activeSession?.cash_register?.name || connectedCashRegisterName }}
          </p>
        </section>
      </div>

      <AmountModal :isOpen="isAmountModalOpen" @close="closeAmountModal" @send="handleAmountModalSend" />

      <div v-if="isSummaryModalOpen" class="modal is-active summary-overlay">
        <div class="modal-background" @click="closeSummaryModal"></div>
        <div class="modal-card summary-modal">
          <header class="modal-card-head summary-modal-head">
            <div>
              <p class="summary-kicker">Connexion caisse</p>
              <p class="modal-card-title summary-modal-title">Résumé de la session</p>
            </div>
            <button class="summary-close-button" type="button" aria-label="Fermer" @click="closeSummaryModal">
              <i class="fas fa-xmark"></i>
              Fermer
            </button>
          </header>
          <section class="modal-card-body summary-modal-body">
            <div v-if="summaryLoading" class="py-4 text-center text-slate-500">
              Chargement du résumé...
            </div>
            <p v-else-if="summaryError" class="text-center text-sm text-rose-600">
              {{ summaryError }}
            </p>
            <div v-else class="summary-content">
              <p v-if="summaryInfo" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                {{ summaryInfo }}
              </p>
              <div class="summary-grid">
                <div class="summary-row">
                  <span class="summary-label">Caisse</span>
                  <span class="summary-value">{{ activeSession?.cash_register?.name }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-label">Ouverte par</span>
                  <span class="summary-value">{{ activeSession?.user?.name || 'Moi' }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-label">Ouverte le</span>
                  <span class="summary-value">{{ formatDate(activeSession?.opened_at) }}</span>
                </div>
                <div class="summary-row">
                  <span class="summary-label">Fond de caisse</span>
                  <span class="summary-value">{{ formatCurrency(activeSession?.starting_amount) }}</span>
                </div>
                <div v-if="sessionSummary" class="summary-row">
                  <span class="summary-label">Total transactions</span>
                  <span class="summary-value">{{ formatCurrency(sessionSummary?.total_transactions) }}</span>
                </div>
                <div v-if="sessionSummary" class="summary-row">
                  <span class="summary-label">Écarts signalés</span>
                  <span class="summary-value">{{ formatCurrency(sessionSummary?.total_discrepancies) }}</span>
                </div>
                <div class="summary-row" v-if="sessionSummary?.session?.expected_cash_amount !== undefined">
                  <span class="summary-label">Montant attendu</span>
                  <span class="summary-value">{{ formatCurrency(sessionSummary?.session?.expected_cash_amount) }}</span>
                </div>
              </div>
            </div>
          </section>
          <footer class="modal-card-foot summary-modal-foot">
            <button
              class="summary-action summary-action-primary"
              type="button"
              :disabled="summaryLoading"
              @click="continueAfterSummary"
            >
              Continuer à vendre
            </button>
            <button class="summary-action summary-action-secondary" type="button" :disabled="summaryLoading" @click="closeSummaryModal">
              Fermer
            </button>
          </footer>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import AmountModal from './AmountModal.vue'
import { useAuth } from '@/composables/useAuth'
import { API_BASE_URL, API_URL } from '@/utils/api'

const router = useRouter()
const { isAdmin, currentUser, loadUserData } = useAuth()

const isAmountModalOpen = ref(false)
const isProcessing = ref(false)
const loadingRegisters = ref(false)
const cashRegisters = ref([])
const registerStatuses = ref({})
const registerOwners = ref({})
const selectedCashRegister = ref(null)
const activeSession = ref(null)
const errorMessage = ref('')
const machineIdentifierMessage = ref('')
const connectedCashRegisterName = ref('')

const machineIdentifier = ref('')

const machineRegister = computed(() => {
  const registers = Array.isArray(cashRegisters.value) ? cashRegisters.value : []
  return findRegisterForMachine(registers)
})

const closeModal = () => {
  router.push({ name: 'dashboard-overview' })
}

const isSummaryModalOpen = ref(false)
const summaryLoading = ref(false)
const summaryError = ref('')
const summaryInfo = ref('')
const sessionSummary = ref(null)

const currentUserId = computed(() => currentUser.value?.id ?? null)
const currentUserPointOfSaleId = computed(() => {
  const pointOfSaleId = Number(currentUser.value?.point_of_sale_id ?? null)
  return Number.isFinite(pointOfSaleId) && pointOfSaleId > 0 ? pointOfSaleId : null
})

const isSessionOpen = (session) => {
  if (!session) return false
  const value = session.is_closed
  return value === false || value === null || value === undefined || value === 0 || value === '0'
}

const hasActiveSession = computed(() => isSessionOpen(activeSession.value))
const activeRegisterId = computed(() => (hasActiveSession.value ? activeSession.value.cash_register_id : null))
const isSelfConnected = computed(() => hasActiveSession.value && activeSession.value.user_id === currentUserId.value)

const availableRegisters = computed(() => {
  const registers = Array.isArray(cashRegisters.value) ? cashRegisters.value : []
  if (!registers.length) return []

  if (hasActiveSession.value) {
    return registers.filter(register => register.id === activeRegisterId.value)
  }

  if (!isAdmin.value) {
    return machineRegister.value ? [machineRegister.value] : []
  }

  return registers
})

const connectButtonText = computed(() => {
  if (isSelfConnected.value) return 'Résumé & Continuer'
  if (isAdmin.value) return 'Accéder'
  return 'Connecter'
})

const connectButtonDisabled = computed(() => {
  if (isProcessing.value) return true
  if (isSelfConnected.value) return false
  if (hasActiveSession.value && !isSelfConnected.value) {
    return !isAdmin.value
  }
  if (!selectedCashRegister.value) return true
  if (!isAdmin.value && !machineRegister.value) return true
  return false
})

const getAuthHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error("Token d'authentification manquant")
  return { Authorization: `Bearer ${token}` }
}

const resolveRegisterPointOfSaleId = (register) => {
  const pointOfSaleId = Number(
    register?.point_of_sale_id ??
    register?.pointOfSaleId ??
    register?.point_of_sale?.id ??
    register?.pointOfSale?.id ??
    null
  )

  return Number.isFinite(pointOfSaleId) && pointOfSaleId > 0 ? pointOfSaleId : null
}

const statusBadgeText = (registerId) => {
  if (isSelfConnected.value && registerId === activeRegisterId.value) {
    return 'Occupée (vous)'
  }
  const status = registerStatuses.value[registerId]
  const owner = registerOwners.value[registerId]
  if (status === 'connected') {
    return owner === currentUserId.value ? 'Occupée (vous)' : 'Occupée'
  }
  if (status === 'error') return 'Erreur'
  return 'Disponible'
}

const statusBadgeClass = (registerId) => {
  const status = statusBadgeText(registerId)
  if (status.includes('Erreur')) return 'bg-red-100 text-red-700'
  if (status.includes('Occupée (vous)')) return 'bg-blue-100 text-blue-700'
  if (status.includes('Occupée')) return 'bg-amber-100 text-amber-700'
  return 'bg-green-100 text-green-700'
}

const isRegisterLocked = (registerId) => {
  if (isSelfConnected.value && registerId === activeRegisterId.value) return true
  const status = registerStatuses.value[registerId]
  const owner = registerOwners.value[registerId]
  return status === 'connected' && owner && owner !== currentUserId.value
}

const selectCashRegister = (registerId) => {
  
  if (loadingRegisters.value || isProcessing.value) return
  if (isRegisterLocked(registerId)) return
  console.log("------ ------ ACTIVE REGISTER ID ", activeRegisterId.value);
  if (hasActiveSession.value && registerId !== activeRegisterId.value) return
  selectedCashRegister.value = registerId

  console.log("###################");
  console.log("###################", registerId);
  console.log("################### --- ", selectedCashRegister.value);
}

const goToMachineManagement = () => {
  router.push({ name: 'cash-registers-machine-link' })
}

const fetchCashRegisters = async () => {
  loadingRegisters.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers`, {
      headers: getAuthHeaders()
    })
    const registers = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    cashRegisters.value = currentUserPointOfSaleId.value
      ? registers.filter((register) => resolveRegisterPointOfSaleId(register) === currentUserPointOfSaleId.value)
      : registers
  } catch (error) {
    console.error('Erreur lors du chargement des caisses:', error)
    errorMessage.value = error.response?.data?.message || 'Impossible de charger les caisses'
    cashRegisters.value = []
  } finally {
    loadingRegisters.value = false
  }
}

const resolveMachineIdentifier = () => {
  machineIdentifierMessage.value = ''
  try {
    const storedIdentifiers = [
      localStorage.getItem('cashRegisterMachineName'),
      localStorage.getItem('cashPrinterName'),
      localStorage.getItem('cash_printer_name'),
      localStorage.getItem('machineIdentifier')
    ]

    const envIdentifier = import.meta.env?.VITE_CASH_PRINTER_NAME

    const resolved = [...storedIdentifiers, envIdentifier]
      .map(value => (typeof value === 'string' ? value.trim() : ''))
      .find(value => value)

    machineIdentifier.value = resolved || ''

    if (!machineIdentifier.value && !isAdmin.value) {
      machineIdentifierMessage.value = 'Identifiant machine introuvable. Configurez le nom de cashprinter pour cette machine.'
    }
  } catch (error) {
    console.error('Erreur récupération identifiant machine:', error)
    machineIdentifier.value = ''
    machineIdentifierMessage.value = "Impossible de déterminer l'identifiant de la machine."
  }
}

function normalizeName(name) {
  return (name || '').toString().trim().toLowerCase()
}

function findRegisterForMachine(registers) {
  if (!machineIdentifier.value) return null
  const normalizedMachineName = normalizeName(machineIdentifier.value)
  return registers.find(register => normalizeName(register.name) === normalizedMachineName) || null
}

const fetchRegisterStatus = async (registerId) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers-sessions/${registerId}/status`, {
      headers: getAuthHeaders()
    })

    const statusResponse = data.status ?? 'available'
    const sessionOwner = data.session?.user_id ?? data.user_id ?? null
    const normalizedStatus = statusResponse === 'in use' ? 'connected' : statusResponse

    registerStatuses.value = {
      ...registerStatuses.value,
      [registerId]: normalizedStatus
    }

    const owners = { ...registerOwners.value }
    if (sessionOwner) {
      owners[registerId] = sessionOwner
    } else {
      delete owners[registerId]
    }
    registerOwners.value = owners
  } catch (error) {
    registerStatuses.value = {
      ...registerStatuses.value,
      [registerId]: 'error'
    }
    console.error(`Erreur statut caisse ${registerId}:`, error.response?.data || error.message)
  }
}

const fetchMyActiveSession = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-session/my-active-session`, {
      headers: getAuthHeaders()
    })

    const session = data?.data || data || null

    if (isSessionOpen(session)) {
      activeSession.value = session
      selectedCashRegister.value = session.cash_register_id
      connectedCashRegisterName.value = session.cash_register?.name || ''
      localStorage.setItem('cashRegisterSession', JSON.stringify(session))
      localStorage.setItem('cash_register_session', JSON.stringify(session))
      registerStatuses.value = {
        ...registerStatuses.value,
        [session.cash_register_id]: 'connected'
      }
      registerOwners.value = {
        ...registerOwners.value,
        [session.cash_register_id]: session.user_id
      }
    } else {
      activeSession.value = null
      localStorage.removeItem('cashRegisterSession')
      localStorage.removeItem('cash_register_session')
      if (!isAdmin.value) {
        selectedCashRegister.value = null
      }
    }
  } catch (error) {
    if (error.response?.status !== 404) {
      console.error('Erreur récupération session active:', error.response?.data || error.message)
    }
    activeSession.value = null
    localStorage.removeItem('cashRegisterSession')
    localStorage.removeItem('cash_register_session')
    if (!isAdmin.value) {
      selectedCashRegister.value = null
    }
  }
}

const initializeSessions = async () => {
  const registers = Array.isArray(cashRegisters.value) ? cashRegisters.value : []
  await Promise.all(registers.map(register => fetchRegisterStatus(register.id)))
  if (hasActiveSession.value) {
    selectedCashRegister.value = activeRegisterId.value
  } else if (!selectedCashRegister.value) {
    if (machineRegister.value) {
      selectedCashRegister.value = machineRegister.value.id
      machineIdentifierMessage.value = ''
    } else {
      const fallback = registers.find(register => !isRegisterLocked(register.id)) || registers[0]
      selectedCashRegister.value = fallback?.id ?? null

      if (!isAdmin.value && machineIdentifier.value) {
        machineIdentifierMessage.value = `Aucune caisse ne correspond au nom « ${machineIdentifier.value} ». Créez-la depuis la gestion des caisses.`
      }
    }
  }
}

const fetchSessionSummary = async (sessionId) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-sessions/${sessionId}/summary`, {
      headers: getAuthHeaders()
    })
    return data?.data || data || null
  } catch (error) {
    if (error.response?.status === 409) {
      const message =
        error.response?.data?.message ||
        "Le résumé n'est pas disponible tant que la session n'est pas clôturée."
      throw Object.assign(new Error(message), { response: error.response })
    }
    throw error
  }
}

const openSummaryModal = async () => {
  if (!activeSession.value?.id) {
    router.push({ name: 'dashboard-direct' })
    return
  }

  summaryError.value = ''
  summaryInfo.value = ''
  sessionSummary.value = null
  isSummaryModalOpen.value = true
  if (isSessionOpen(activeSession.value)) {
    summaryLoading.value = false
    summaryInfo.value = "Le résumé détaillé sera disponible après la clôture de la session. Vous pouvez reprendre les ventes immédiatement."
    return
  }

  summaryLoading.value = true

  try {
    sessionSummary.value = await fetchSessionSummary(activeSession.value.id)
  } catch (error) {
    summaryError.value =
      error.message ||
      error.response?.data?.message ||
      'Impossible de récupérer le résumé de la session.'
  } finally {
    summaryLoading.value = false
  }
}

const closeSummaryModal = () => {
  isSummaryModalOpen.value = false
}

const continueAfterSummary = () => {
  if (activeSession.value?.id) {
    localStorage.setItem('cashRegisterSession', JSON.stringify(activeSession.value))
    localStorage.setItem('cash_register_session', JSON.stringify(activeSession.value))
  }
  closeSummaryModal()
  router.push({ name: 'dashboard-direct' })
}

const openAmountModal = () => {
  if (isProcessing.value) return
  isAmountModalOpen.value = true
}

const closeAmountModal = () => {
  isAmountModalOpen.value = false
}

const handleAmountModalSend = (payload) => {
  if (isProcessing.value) return
  sendFondDeCaisse(payload)
}

const sendFondDeCaisse = async ({ amount, ticketNumber, note }) => {
  if (!selectedCashRegister.value) {
    alert('Sélectionnez une caisse')
    return
  }

  isProcessing.value = true
  try {
    const user = currentUser.value
    if (!user?.id) throw new Error('Utilisateur non authentifié')

    const headers = { ...getAuthHeaders(), 'Content-Type': 'application/json' }
    const payload = {
      cash_register_id: selectedCashRegister.value,
      user_id: user.id,
      starting_amount: amount,
      note,
      expected_cash_amount: 0,
      start_ticket_number: ticketNumber ?? null
    }

    console.log("+++++++ Data alefa VUE : ", payload)

    const { data } = await axios.post(`${API_BASE_URL}/cash-register-sessions`, payload, { headers })
    const createdSession = data?.data || data || null

    if (createdSession) {
      localStorage.setItem('cashRegisterSession', JSON.stringify(createdSession))
      localStorage.setItem('cash_register_session', JSON.stringify(createdSession))

      if (ticketNumber !== undefined && ticketNumber !== null && ticketNumber !== '') {
        localStorage.setItem('currentTicketNumber', ticketNumber.toString())
      }

      await fetchMyActiveSession()
      if (selectedCashRegister.value) {
        await fetchRegisterStatus(selectedCashRegister.value)
      }

      router.push({ name: 'dashboard-direct' })
    }
  } catch (error) {
    console.error('Erreur connexion caisse:', error)
    alert(error.response?.data?.message || error.message || 'Erreur de connexion à la caisse')
  } finally {
    isProcessing.value = false
    closeAmountModal()
  }
}

const resetCashRegister = async () => {
  if (isSelfConnected.value) {
    alert('Fermez votre session avant de réinitialiser la caisse.')
    return
  }
  if (!selectedCashRegister.value) {
    alert('Sélectionnez une caisse')
    return
  }
  if (!confirm('Confirmez la remise à zéro ?')) return

  try {
    await axios.post(`${API_BASE_URL}/cash-registers/reset`, {
      cash_register_id: selectedCashRegister.value
    }, {
      headers: getAuthHeaders()
    })
    alert('RAZ effectuée avec succès !')
    await initializeSessions()
  } catch (error) {
    console.error('Erreur reset caisse:', error)
    alert(error.response?.data?.message || 'Échec de la remise à zéro')
  }
}

const performCashCount = () => {
  router.push({ name: 'billetage' })
}

const viewSales = () => {
  router.push({ name: 'dashboard-user-sales' })
}

const onConnectButtonClick = () => {
  if (isProcessing.value) return

  if (isSelfConnected.value) {
    openSummaryModal()
    return
  }

  if (!selectedCashRegister.value) {
    alert('Sélectionnez une caisse')
    return
  }

  if (!isAdmin.value && !machineRegister.value) {
    alert('Associez cette machine à une caisse avant de continuer.')
    return
  }

  openAmountModal()
}

const formatCurrency = (value) => {
  const number = Number(value)
  if (!Number.isFinite(number)) return '0,00 Ar'
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(number)
}

const formatDate = (date) => {
  if (!date) return '—'
  const parsed = new Date(date)
  if (Number.isNaN(parsed.getTime())) return '—'
  return parsed.toLocaleString('fr-FR')
}

// Lifecycle
onMounted(async () => {
  try {
    await loadUserData()
  } catch (error) {
    console.error('Erreur chargement utilisateur:', error)
  }

  resolveMachineIdentifier()
  await fetchMyActiveSession()
  await fetchCashRegisters()
  await initializeSessions()
})
</script>

<style scoped>
.summary-overlay {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.summary-overlay .modal-background {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(8px);
}

.summary-modal {
  position: relative;
  width: min(100%, 760px);
  border: 1px solid rgb(226 232 240 / 0.95);
  border-radius: 1.75rem;
  overflow: hidden;
  background:
    radial-gradient(circle at top right, rgb(224 231 255 / 0.85), transparent 30%),
    linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  box-shadow: 0 32px 80px -36px rgba(15, 23, 42, 0.45);
}

.summary-modal-head,
.summary-modal-body,
.summary-modal-foot {
  position: relative;
  z-index: 1;
}

.summary-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.5rem 1.75rem 1.25rem;
  border-bottom: 1px solid rgb(226 232 240 / 0.9);
  background: rgb(255 255 255 / 0.86);
}

.summary-kicker {
  margin: 0 0 0.35rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: rgb(99 102 241);
}

.summary-modal-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: rgb(15 23 42);
}

.summary-close-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid rgb(226 232 240);
  border-radius: 9999px;
  background: rgb(255 255 255 / 0.95);
  padding: 0.7rem 1rem;
  font-size: 0.92rem;
  font-weight: 600;
  color: rgb(71 85 105);
  transition: all 0.2s ease;
}

.summary-close-button:hover {
  border-color: rgb(251 113 133 / 0.35);
  color: rgb(225 29 72);
}

.summary-modal-body {
  padding: 1.5rem 1.75rem;
}

.summary-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.summary-grid {
  display: grid;
  gap: 0.9rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.summary-row {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1rem 1.1rem;
  border: 1px solid rgb(226 232 240);
  border-radius: 1rem;
  background: rgb(255 255 255 / 0.92);
  box-shadow: 0 12px 30px -24px rgba(15, 23, 42, 0.35);
}

.summary-label {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgb(100 116 139);
}

.summary-value {
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.35;
  color: rgb(15 23 42);
  word-break: break-word;
}

.summary-modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.25rem 1.75rem 1.6rem;
  border-top: 1px solid rgb(226 232 240 / 0.9);
  background: rgb(248 250 252 / 0.85);
}

.summary-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 180px;
  border-radius: 9999px;
  padding: 0.85rem 1.25rem;
  font-size: 0.95rem;
  font-weight: 700;
  transition: all 0.2s ease;
}

.summary-action:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.summary-action-primary {
  border: 1px solid transparent;
  background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
  color: #fff;
  box-shadow: 0 20px 36px -24px rgba(79, 70, 229, 0.8);
}

.summary-action-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 24px 42px -24px rgba(79, 70, 229, 0.95);
}

.summary-action-secondary {
  border: 1px solid rgb(226 232 240);
  background: rgb(255 255 255 / 0.95);
  color: rgb(71 85 105);
}

.summary-action-secondary:hover:not(:disabled) {
  border-color: rgb(148 163 184);
  color: rgb(15 23 42);
}

@media (max-width: 640px) {
  .summary-overlay {
    padding: 0.85rem;
  }

  .summary-modal-head,
  .summary-modal-body,
  .summary-modal-foot {
    padding-left: 1rem;
    padding-right: 1rem;
  }

  .summary-modal-head,
  .summary-modal-foot {
    flex-direction: column;
    align-items: stretch;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .summary-action {
    width: 100%;
    min-width: 0;
  }
}
</style>
