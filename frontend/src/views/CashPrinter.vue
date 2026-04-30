<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur">
    <div class="relative mx-4 w-full max-w-4xl rounded-3xl border border-slate-200 bg-white shadow-2xl">

      <!-- Header -->
      <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Connexion caisse</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Choisir une caisse</h1>
          <p class="mt-1 text-sm text-slate-500">Sélectionnez la caisse que vous souhaitez utiliser</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="debugMode"
            type="button"
            class="inline-flex items-center gap-1 rounded-2xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"
            @click="refreshAllStatuses"
          >
            <i class="fas fa-sync-alt"></i> Rafraîchir
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-rose-200 hover:text-rose-600"
            @click="closeModal"
          >
            <i class="fas fa-xmark"></i> Fermer
          </button>
        </div>
      </header>

      <div class="px-6 py-6">

        <!-- Message machine détectée -->
        <div v-if="machineIdentifier" class="mb-6 rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-600">
          <i class="fas fa-microchip mr-2"></i>
          Machine détectée : <strong>{{ machineIdentifier }}</strong>
          <span v-if="machineRegister"> → Caisse associée : {{ machineRegister.name }}</span>
          <span v-else> → Aucune caisse ne porte ce nom. Vous pouvez en créer une ci-dessous.</span>
        </div>

        <!-- Conteneur des caisses -->
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
          <div class="flex justify-between items-center mb-4">
            <p class="text-sm font-semibold text-slate-700">Caisses disponibles</p>
            <button
              v-if="debugMode"
              type="button"
              class="text-xs text-indigo-500 underline"
              @click="refreshAllStatuses"
            >
              🔄 Rafraîchir les statuts
            </button>
          </div>

          <div v-if="loadingRegisters" class="py-8 text-center text-slate-500">
            Chargement des caisses...
          </div>

          <div v-else>
            <div v-if="cashRegisters.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <button
                v-for="register in cashRegisters"
                :key="register.id"
                type="button"
                class="rounded-2xl border border-slate-200 px-5 py-5 text-left transition-all hover:border-indigo-200 hover:bg-indigo-50 disabled:cursor-not-allowed"
                :class="{
                  'selected': selectedCashRegister === register.id,
                  'opacity-60 bg-slate-100 pointer-events-none': isRegisterLocked(register.id)
                }"
                :disabled="isRegisterLocked(register.id)"
                @click="selectCashRegister(register.id)"
              >
                <div class="flex items-start gap-4">
                  <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                    <i class="fas fa-desktop text-xl"></i>
                  </span>
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-900 truncate">{{ register.name }}</p>
                    <p class="text-xs text-slate-400 mt-0.5 truncate">
                      {{ register.point_of_sale?.name || 'Sans point de vente' }}
                    </p>
                  </div>
                </div>

                <div class="mt-4">
                  <span
                    v-if="statusBadgeText(register.id)"
                    :class="['inline-block px-3.5 py-1 rounded-xl text-xs font-semibold', statusBadgeClass(register.id)]"
                  >
                    {{ statusBadgeText(register.id) }}
                  </span>
                </div>

                <!-- Panneau de debug -->
                <div v-if="debugMode" class="mt-2 pt-2 border-t border-slate-200 text-[10px] font-mono text-slate-400 space-y-0.5">
                  <div>🔍 Status: {{ registerStatuses[register.id] || '?' }}</div>
                  <div>👤 Owner: {{ registerOwners[register.id] || '-' }}</div>
                  <div>🔒 Locked: {{ isRegisterLocked(register.id) }}</div>
                </div>
              </button>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-500">
              Aucune caisse disponible.
              <button
                type="button"
                class="ml-2 text-indigo-600 underline hover:text-indigo-700"
                @click="goToMachineManagement"
              >
                Créer une caisse
              </button>
            </div>
          </div>
        </div>



        <p v-if="isSelfConnected" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-600">
          ✅ Caisse connectée : {{ activeSession?.cash_register?.name || connectedCashRegisterName }}
        </p>

        <!-- Bouton principal -->
        <button
          type="button"
          class="mt-6 w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="connectButtonDisabled"
          @click="onConnectButtonClick"
        >
          <i class="fas fa-link"></i> {{ connectButtonText }}
        </button>

        <!-- Boutons supplémentaires (uniquement si connecté) -->
        <div v-if="isSelfConnected && canAccessCashActions" class="mt-4 flex flex-wrap gap-3">
          <button
            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-600 transition hover:bg-amber-100"
            @click="resetCashRegister"
          >
            <i class="fas fa-sync-alt"></i> Remise à zéro
          </button>
          <button
            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-2.5 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-100"
            @click="performCashCount"
          >
            <i class="fas fa-money-bill-wave"></i> Billetage
          </button>
          <button
            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-2.5 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-100"
            @click="viewSales"
          >
            <i class="fas fa-chart-line"></i> Mes ventes
          </button>
        </div>

        <!-- Message d'erreur -->
        <div v-if="errorMessage" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-600">
          {{ errorMessage }}
        </div>
      </div>

      <!-- Modals -->
      <AmountModal :isOpen="isAmountModalOpen" @close="closeAmountModal" @send="handleAmountModalSend" />

      <!-- Summary Modal -->
      <div v-if="isSummaryModalOpen" class="modal is-active summary-overlay">
        <div class="modal-background" @click="closeSummaryModal"></div>
        <div class="modal-card summary-modal">
          <header class="modal-card-head summary-modal-head">
            <div>
              <p class="summary-kicker">Connexion caisse</p>
              <p class="modal-card-title summary-modal-title">Résumé de la session</p>
            </div>
            <button class="summary-close-button" type="button" aria-label="Fermer" @click="closeSummaryModal">
              <i class="fas fa-xmark"></i> Fermer
            </button>
          </header>
          <section class="modal-card-body summary-modal-body">
            <div v-if="summaryLoading" class="py-4 text-center text-slate-500">Chargement du résumé...</div>
            <p v-else-if="summaryError" class="text-center text-sm text-rose-600">{{ summaryError }}</p>
            <div v-else class="summary-content">
              <p v-if="summaryInfo" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ summaryInfo }}</p>
              <div class="summary-grid">
                <div class="summary-row"><span class="summary-label">Caisse</span><span class="summary-value">{{ activeSession?.cash_register?.name }}</span></div>
                <div class="summary-row"><span class="summary-label">Ouverte par</span><span class="summary-value">{{ activeSession?.user?.name || 'Moi' }}</span></div>
                <div class="summary-row"><span class="summary-label">Ouverte le</span><span class="summary-value">{{ formatDate(activeSession?.opened_at) }}</span></div>
                <div class="summary-row"><span class="summary-label">Fond de caisse</span><span class="summary-value">{{ formatCurrency(activeSession?.starting_amount) }}</span></div>
                <div v-if="sessionSummary" class="summary-row"><span class="summary-label">Total transactions</span><span class="summary-value">{{ formatCurrency(sessionSummary?.total_transactions) }}</span></div>
                <div v-if="sessionSummary" class="summary-row"><span class="summary-label">Écarts signalés</span><span class="summary-value">{{ formatCurrency(sessionSummary?.total_discrepancies) }}</span></div>
                <div class="summary-row" v-if="sessionSummary?.session?.expected_cash_amount !== undefined">
                  <span class="summary-label">Montant attendu</span>
                  <span class="summary-value">{{ formatCurrency(sessionSummary?.session?.expected_cash_amount) }}</span>
                </div>
              </div>
            </div>
          </section>
          <footer class="modal-card-foot summary-modal-foot">
            <button class="summary-action summary-action-primary" type="button" :disabled="summaryLoading" @click="continueAfterSummary">Continuer à vendre</button>
            <button class="summary-action summary-action-secondary" type="button" :disabled="summaryLoading" @click="closeSummaryModal">Fermer</button>
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
import { API_BASE_URL } from '@/utils/api'

const router = useRouter()
const { isAdmin, currentUser, hasRole, loadUserData } = useAuth()

const canAccessCashActions = computed(() => {
  return isAdmin.value || hasRole('caissier')
})

// Mode debug : activer avec ?debug=true dans l'URL
const debugMode = ref(window.location.search.includes('debug=true'))

const isAmountModalOpen = ref(false)
const isProcessing = ref(false)
const loadingRegisters = ref(false)
const cashRegisters = ref([])
const registerStatuses = ref({})
const registerOwners = ref({})
const selectedCashRegister = ref(null)
const activeSession = ref(null)
const errorMessage = ref('')
const machineIdentifier = ref('')
const connectedCashRegisterName = ref('')
const isSummaryModalOpen = ref(false)
const summaryLoading = ref(false)
const summaryError = ref('')
const summaryInfo = ref('')
const sessionSummary = ref(null)

const machineRegister = computed(() => {
  const registers = Array.isArray(cashRegisters.value) ? cashRegisters.value : []
  return findRegisterForMachine(registers)
})

const closeModal = () => router.push({ name: 'dashboard-overview' })

const currentUserId = computed(() => currentUser?.value?.id ?? null)
const currentUserName = computed(() => currentUser?.value?.name ?? null)

const isSessionOpen = (session) => {
  if (!session) return false
  const value = session.is_closed
  return value === false || value === null || value === undefined || value === 0 || value === '0'
}

const hasActiveSession = computed(() => isSessionOpen(activeSession.value))
const activeRegisterId = computed(() => hasActiveSession.value ? activeSession.value.cash_register_id : null)
const isSelfConnected = computed(() => hasActiveSession.value && activeSession.value.user_id === currentUserId.value)

const connectButtonText = computed(() => {
  return isSelfConnected.value ? 'Résumé & Continuer' : 'Connecter'
})

const connectButtonDisabled = computed(() => {
  if (isProcessing.value) return true
  if (isSelfConnected.value) return false
  if (!selectedCashRegister.value) return true
  return false
})

const getAuthHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error("Token d'authentification manquant")
  return { Authorization: `Bearer ${token}` }
}

const statusBadgeClass = (registerId) => {
  const status = statusBadgeText(registerId)
  if (status.includes('Erreur')) return 'bg-red-100 text-red-700'
  if (status.includes('Occupée (vous)')) return 'bg-blue-100 text-blue-700'
  if (status.includes('Occupée')) return 'bg-amber-100 text-amber-700'
  return 'bg-green-100 text-green-700'
}

const isRegisterLocked = (registerId) => {
  // Sécuriser l'accès aux variables réactives
  const userId = currentUserId.value
  const userName = currentUserName.value

  if (isSelfConnected.value && registerId === activeRegisterId.value) return false
  const status = registerStatuses.value[registerId]
  const owner = registerOwners.value[registerId]
  if (status !== 'connected') return false
  if (!owner) return false
  if (typeof owner === 'number') return owner !== userId
  return owner !== userName
}

const statusBadgeText = (registerId) => {
  if (isSelfConnected.value && registerId === activeRegisterId.value) return 'Occupée (vous)'
  const status = registerStatuses.value[registerId]
  const owner = registerOwners.value[registerId]
  if (status === 'connected') {
    if (owner && ((typeof owner === 'number' && owner === currentUserId.value) ||
                   (typeof owner === 'string' && owner === currentUserName.value))) {
      return 'Occupée (vous)'
    }
    return owner ? `Occupée par ${owner}` : 'Occupée'
  }
  if (status === 'error') return 'Erreur'
  return 'Disponible'
}

const selectCashRegister = (registerId) => {
  if (loadingRegisters.value || isProcessing.value) return
  if (isRegisterLocked(registerId)) return
  selectedCashRegister.value = registerId
}

const goToMachineManagement = () => router.push({ name: 'cash-registers-machine-link' })

const fetchCashRegisters = async () => {
  loadingRegisters.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers`, { headers: getAuthHeaders() })
    let registers = []
    if (Array.isArray(data)) registers = data
    else if (data?.data && Array.isArray(data.data)) registers = data.data
    else if (data?.items && Array.isArray(data.items)) registers = data.items
    else if (data?.results && Array.isArray(data.results)) registers = data.results
    else if (data?.cash_registers && Array.isArray(data.cash_registers)) registers = data.cash_registers
    else for (const key in data) if (Array.isArray(data[key])) { registers = data[key]; break }

    cashRegisters.value = registers

    // Mettre à jour les statuts à partir des données de l'API (is_occupied + current_session)
    const newStatuses = {}
    const newOwners = {}
    registers.forEach(register => {
      const isOccupied = register.is_occupied === true
      const currentSession = register.current_session
      if (isOccupied && currentSession) {
        newStatuses[register.id] = 'connected'
        const ownerId = currentSession.user_id || currentSession.user?.id || null
        const ownerName = currentSession.user?.name || null
        newOwners[register.id] = ownerId || ownerName || 'unknown'
      } else {
        newStatuses[register.id] = 'available'
      }
    })
    registerStatuses.value = newStatuses
    registerOwners.value = newOwners
  } catch (error) {
    console.error('Erreur chargement caisses:', error)
    errorMessage.value = error.response?.data?.message || 'Impossible de charger les caisses'
    cashRegisters.value = []
  } finally {
    loadingRegisters.value = false
  }
}

const resolveMachineIdentifier = () => {
  try {
    const storedIdentifiers = [
      localStorage.getItem('cashRegisterMachineName'),
      localStorage.getItem('cashPrinterName'),
      localStorage.getItem('cash_printer_name'),
      localStorage.getItem('machineIdentifier')
    ]
    const envIdentifier = import.meta.env?.VITE_CASH_PRINTER_NAME
    const resolved = [...storedIdentifiers, envIdentifier]
      .map(v => typeof v === 'string' ? v.trim() : '')
      .find(v => v)

    machineIdentifier.value = resolved || ''
  } catch (error) {
    console.error('Erreur récupération identifiant machine:', error)
    machineIdentifier.value = ''
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

const refreshAllStatuses = async () => {
  await fetchCashRegisters()
  if (debugMode.value) console.log('Statuts rafraîchis', registerStatuses.value)
}

const fetchMyActiveSession = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/my-active-session`, { headers: getAuthHeaders() })
    console.log('Réponse my-active-session:', data)

    // Si l'API indique explicitement qu'il n'y a pas de session active
    if (data?.has_active_session === false || !data?.data) {
      activeSession.value = null
      localStorage.removeItem('cashRegisterSession')
      localStorage.removeItem('cash_register_session')
      selectedCashRegister.value = null
      return
    }

    // Sinon, on prend la session (data.data doit contenir l'objet session)
    const session = data?.data
    if (session && isSessionOpen(session)) {
      activeSession.value = session
      selectedCashRegister.value = session.cash_register_id
      connectedCashRegisterName.value = session.cash_register?.name || ''
      localStorage.setItem('cashRegisterSession', JSON.stringify(session))
      localStorage.setItem('cash_register_session', JSON.stringify(session))
      registerStatuses.value = { ...registerStatuses.value, [session.cash_register_id]: 'connected' }
      registerOwners.value = { ...registerOwners.value, [session.cash_register_id]: currentUserId.value }
    } else {
      activeSession.value = null
      localStorage.removeItem('cashRegisterSession')
      localStorage.removeItem('cash_register_session')
      selectedCashRegister.value = null
    }
  } catch (error) {
    if (error.response?.status !== 404) console.error('Erreur récupération session active:', error)
    activeSession.value = null
    localStorage.removeItem('cashRegisterSession')
    localStorage.removeItem('cash_register_session')
    selectedCashRegister.value = null
  }
}

const initializeSessions = async () => {
  if (hasActiveSession.value) {
    selectedCashRegister.value = activeRegisterId.value
  } else if (!selectedCashRegister.value) {
    if (machineRegister.value) {
      selectedCashRegister.value = machineRegister.value.id
    } else {
      const registers = Array.isArray(cashRegisters.value) ? cashRegisters.value : []
      const fallback = registers.find(r => !isRegisterLocked(r.id)) || registers[0]
      selectedCashRegister.value = fallback?.id ?? null
    }
  }
}

const fetchSessionSummary = async (sessionId) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-sessions/${sessionId}/summary`, { headers: getAuthHeaders() })
    return data?.data || data || null
  } catch (error) {
    if (error.response?.status === 409) throw new Error(error.response?.data?.message || "Le résumé n'est pas disponible tant que la session n'est pas clôturée.")
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

const isSessionOpen = (session) => {
  // Une session valide doit avoir une propriété 'is_closed'
  if (!session || typeof session !== 'object' || !('is_closed' in session)) return false
  const value = session.is_closed
  return value === false || value === null || value === undefined || value === 0 || value === '0'
}

  summaryLoading.value = true
  try {
    sessionSummary.value = await fetchSessionSummary(activeSession.value.id)
  } catch (error) {
    summaryError.value = error.message || error.response?.data?.message || 'Impossible de récupérer le résumé de la session.'
  } finally {
    summaryLoading.value = false
  }
}

const closeSummaryModal = () => { isSummaryModalOpen.value = false }

const continueAfterSummary = () => {
  if (activeSession.value?.id) {
    localStorage.setItem('cashRegisterSession', JSON.stringify(activeSession.value))
    localStorage.setItem('cash_register_session', JSON.stringify(activeSession.value))
  }
  closeSummaryModal()
  router.push({ name: 'dashboard-direct' })
}

const openAmountModal = () => { if (!isProcessing.value) isAmountModalOpen.value = true }
const closeAmountModal = () => { isAmountModalOpen.value = false }
const handleAmountModalSend = (payload) => { if (!isProcessing.value) sendFondDeCaisse(payload) }

const sendFondDeCaisse = async ({ amount, ticketNumber, note }) => {
  if (!selectedCashRegister.value) {
    alert('Sélectionnez une caisse');
    return
  }
  isProcessing.value = true
  try {
    // Utiliser une récupération directe via localStorage si currentUser est indisponible
    const user = currentUser?.value || JSON.parse(localStorage.getItem('user') || '{}')
    if (!user?.id) throw new Error('Utilisateur non authentifié')

    const payload = {
      cash_register_id: selectedCashRegister.value,
      user_id: user.id,
      starting_amount: amount,
      note: note,
      expected_cash_amount: 0,
      start_ticket_number: ticketNumber ?? null
    }

    const { data } = await axios.post(`${API_BASE_URL}/cash-register-sessions`, payload, {
      headers: { ...getAuthHeaders(), 'Content-Type': 'application/json' }
    })

    const createdSession = data?.data || data || null
    if (createdSession) {
      localStorage.setItem('cashRegisterSession', JSON.stringify(createdSession))
      localStorage.setItem('cash_register_session', JSON.stringify(createdSession))
      if (ticketNumber) localStorage.setItem('currentTicketNumber', ticketNumber.toString())
      await fetchMyActiveSession()
      await fetchCashRegisters()
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
    alert('Sélectionnez une caisse');
    return
  }
  if (!confirm('Confirmez la remise à zéro ?')) return

  try {
    await axios.post(`${API_BASE_URL}/cash-registers/reset`, { cash_register_id: selectedCashRegister.value }, { headers: getAuthHeaders() })
    alert('RAZ effectuée avec succès !')
    await fetchCashRegisters()
    await initializeSessions()
  } catch (error) {
    console.error('Erreur reset caisse:', error)
    alert(error.response?.data?.message || 'Échec de la remise à zéro')
  }
}

const performCashCount = () => router.push({ name: 'billetage' })
const viewSales = () => router.push({ name: 'dashboard-user-sales' })

const onConnectButtonClick = () => {
  if (isProcessing.value) return
  if (isSelfConnected.value) {
    openSummaryModal();
    return
  }
  if (!selectedCashRegister.value) {
    alert('Sélectionnez une caisse');
    return
  }
  openAmountModal()
}

const formatCurrency = (value) => {
  const number = Number(value)
  if (!Number.isFinite(number)) return '0,00 Ar'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(number)
}

const formatDate = (date) => {
  if (!date) return '—'
  const parsed = new Date(date)
  if (isNaN(parsed.getTime())) return '—'
  return parsed.toLocaleString('fr-FR')
}

onMounted(async () => {
  try { await loadUserData() } catch (error) { console.error('Erreur chargement utilisateur:', error) }
  resolveMachineIdentifier()
  await fetchMyActiveSession()
  await fetchCashRegisters()
  await initializeSessions()
})
</script>

<style scoped>
/* Styles du summary modal */
.summary-overlay { position: fixed; inset: 0; z-index: 70; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
.summary-overlay .modal-background { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.35); backdrop-filter: blur(8px); }
.summary-modal { position: relative; width: min(100%, 760px); border: 1px solid rgb(226 232 240 / 0.95); border-radius: 1.75rem; overflow: hidden; background: radial-gradient(circle at top right, rgb(224 231 255 / 0.85), transparent 30%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); box-shadow: 0 32px 80px -36px rgba(15, 23, 42, 0.45); }

.summary-modal-head, .summary-modal-body, .summary-modal-foot { position: relative; z-index: 1; }
.summary-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.5rem 1.75rem 1.25rem; border-bottom: 1px solid rgb(226 232 240 / 0.9); background: rgb(255 255 255 / 0.86); }
.summary-kicker { margin: 0 0 0.35rem; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.28em; text-transform: uppercase; color: rgb(99 102 241); }
.summary-modal-title { margin: 0; font-size: 1.4rem; font-weight: 700; color: rgb(15 23 42); }
.summary-close-button { display: inline-flex; align-items: center; gap: 0.5rem; border: 1px solid rgb(226 232 240); border-radius: 9999px; background: rgb(255 255 255 / 0.95); padding: 0.7rem 1rem; font-size: 0.92rem; font-weight: 600; color: rgb(71 85 105); transition: all 0.2s ease; }
.summary-close-button:hover { border-color: rgb(251 113 133 / 0.35); color: rgb(225 29 72); }

.summary-modal-body { padding: 1.5rem 1.75rem; }
.summary-content { display: flex; flex-direction: column; gap: 1rem; }
.summary-grid { display: grid; gap: 0.9rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.summary-row { display: flex; flex-direction: column; gap: 0.35rem; padding: 1rem 1.1rem; border: 1px solid rgb(226 232 240); border-radius: 1rem; background: rgb(255 255 255 / 0.92); box-shadow: 0 12px 30px -24px rgba(15, 23, 42, 0.35); }
.summary-label { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgb(100 116 139); }
.summary-value { font-size: 1rem; font-weight: 700; line-height: 1.35; color: rgb(15 23 42); word-break: break-word; }

.summary-modal-foot { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1.25rem 1.75rem 1.6rem; border-top: 1px solid rgb(226 232 240 / 0.9); background: rgb(248 250 252 / 0.85); }
.summary-action { display: inline-flex; align-items: center; justify-content: center; min-width: 180px; border-radius: 9999px; padding: 0.85rem 1.25rem; font-size: 0.95rem; font-weight: 700; transition: all 0.2s ease; }
.summary-action:disabled { cursor: not-allowed; opacity: 0.6; }
.summary-action-primary { border: 1px solid transparent; background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: #fff; box-shadow: 0 20px 36px -24px rgba(79, 70, 229, 0.8); }
.summary-action-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 24px 42px -24px rgba(79, 70, 229, 0.95); }
.summary-action-secondary { border: 1px solid rgb(226 232 240); background: rgb(255 255 255 / 0.95); color: rgb(71 85 105); }
.summary-action-secondary:hover:not(:disabled) { border-color: rgb(148 163 184); color: rgb(15 23 42); }

@media (max-width: 640px) {
  .summary-overlay { padding: 0.85rem; }
  .summary-modal-head, .summary-modal-body, .summary-modal-foot { padding-left: 1rem; padding-right: 1rem; }
  .summary-modal-head, .summary-modal-foot { flex-direction: column; align-items: stretch; }
  .summary-grid { grid-template-columns: 1fr; }
  .summary-action { width: 100%; min-width: 0; }
}

.selected {
  border-color: #4f46e5 !important;
  background-color: #eef2ff !important;
  box-shadow: 0 0 0 2px #4f46e5;
  transition: all 0.2s ease;
}
</style>
