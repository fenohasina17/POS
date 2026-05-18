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

        <!-- Message admin -->
        <div v-if="isAdmin && !hasActiveSession" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
          <i class="fas fa-shield-alt mr-2"></i>
          Mode Administrateur : Vous pouvez superviser une caisse existante ou en créer une nouvelle.
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
                  'opacity-60 bg-slate-100 pointer-events-none': isRegisterLocked(register.id) && !isAdmin
                }"
                :disabled="isRegisterLocked(register.id) && !isAdmin"
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
                    <p v-if="register.current_session?.user?.name" class="text-xs text-indigo-600 mt-1">
                      👤 Occupé par: {{ register.current_session.user.name }}
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

        <p v-if="isAdminVirtualSession" class="mt-4 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-600">
          👑 Mode supervision Admin - Session virtuelle active
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

        <!-- Message d'erreur -->
        <div v-if="errorMessage" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-600">
          {{ errorMessage }}
        </div>
      </div>
    </div>
  </div>

  <AmountModal
    :is-open="isAmountModalOpen"
    @close="closeAmountModal"
    @send="handleAmountModalSend"
  />

  <!-- Modal résumé session -->
  <div v-if="isSummaryModalOpen" class="summary-overlay" @click.self="closeSummaryModal">
    <div class="summary-modal">
      <div class="summary-modal-head">
        <div>
          <p class="summary-kicker">RÉSUMÉ DE SESSION</p>
          <h3 class="summary-modal-title">Session caisse</h3>
        </div>
        <button type="button" class="summary-close-button" @click="closeSummaryModal">
          <i class="fas fa-xmark"></i> Fermer
        </button>
      </div>
      <div class="summary-modal-body">
        <div v-if="summaryLoading" class="text-center py-8 text-slate-500">
          <i class="fas fa-spinner fa-spin mr-2"></i> Chargement...
        </div>
        <div v-else-if="summaryError" class="text-center py-8 text-rose-500">
          {{ summaryError }}
        </div>
        <div v-else-if="sessionSummary" class="summary-content">
          <div class="summary-grid">
            <div class="summary-row">
              <span class="summary-label">Caissier</span>
              <span class="summary-value">{{ sessionSummary.user_name || sessionSummary.user?.name || '—' }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Date d'ouverture</span>
              <span class="summary-value">{{ formatDate(sessionSummary.started_at) }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Fond de caisse</span>
              <span class="summary-value">{{ formatCurrency(sessionSummary.starting_amount) }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Ventes totales</span>
              <span class="summary-value">{{ formatCurrency(sessionSummary.total_sales) }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Espèces encaissées</span>
              <span class="summary-value">{{ formatCurrency(sessionSummary.cash_collected) }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Fermeture prévue</span>
              <span class="summary-value">{{ formatDate(sessionSummary.expected_close_date) || 'Non définie' }}</span>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-slate-500">
          Aucune donnée disponible
        </div>
      </div>
      <div class="summary-modal-foot">
        <button type="button" class="summary-action summary-action-secondary" @click="closeSummaryModal">
          Annuler
        </button>
        <button type="button" class="summary-action summary-action-primary" @click="continueAfterSummary">
          Continuer
        </button>
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
const { isAdmin, user: currentUser, hasRole, loadUserData } = useAuth()

// ========== ÉTATS ==========
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
const sessionSummary = ref(null)

// ========== COMPUTED ==========
const canAccessCashActions = computed(() => isAdmin.value || hasRole('caissier'))
const currentUserId = computed(() => currentUser?.value?.id ?? null)
const currentUserName = computed(() => currentUser?.value?.name ?? null)

const isAdminVirtualSession = computed(() => {
  const session = localStorage.getItem('cashRegisterSession')
  if (!session) return false
  try {
    const parsed = JSON.parse(session)
    return parsed.is_admin_session === true
  } catch {
    return false
  }
})

const machineRegister = computed(() => {
  const registers = Array.isArray(cashRegisters.value) ? cashRegisters.value : []
  return findRegisterForMachine(registers)
})

const isSessionOpen = (session) => {
  if (!session) return false
  const value = session.is_closed
  return value === false || value === null || value === undefined || value === 0 || value === '0'
}

const hasActiveSession = computed(() => isSessionOpen(activeSession.value))
const activeRegisterId = computed(() => hasActiveSession.value ? activeSession.value.cash_register_id : null)
const isSelfConnected = computed(() => hasActiveSession.value && activeSession.value?.user_id === currentUserId.value)

const connectButtonText = computed(() => {
  if (isAdmin.value && !hasActiveSession.value) {
    return 'Accéder au tableau de bord (Supervision)'
  }

  const currentSel = String(selectedCashRegister.value)
  const myReg = String(activeRegisterId.value)

  if (isSelfConnected.value && currentSel === myReg) {
    return 'Continuer ma session'
  }

  const status = registerStatuses.value[selectedCashRegister.value]
  if (status === 'connected') return 'Voir le résumé'
  return 'Connecter'
})

const connectButtonDisabled = computed(() => {
  if (isProcessing.value) return true
  if (isSelfConnected.value) return false
  if (isAdmin.value) return false
  if (!selectedCashRegister.value) return true
  return false
})

// ========== FONCTIONS UTILITAIRES ==========
const getAuthHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error("Token d'authentification manquant")
  return { Authorization: `Bearer ${token}` }
}

const statusBadgeClass = (registerId) => {
  const text = statusBadgeText(registerId)
  if (text?.includes('Erreur')) return 'bg-red-100 text-red-700'
  if (text?.includes('Occupée (vous)')) return 'bg-blue-100 text-blue-700'
  if (text?.includes('Occupée')) return 'bg-amber-100 text-amber-700'
  return 'bg-green-100 text-green-700'
}

const isRegisterLocked = (registerId) => {
  if (!registerId) return true
  if (isAdmin.value) return false
  if (isSelfConnected.value && registerId === activeRegisterId.value) return false

  const status = registerStatuses.value[registerId]
  const owner = registerOwners.value[registerId]

  if (status !== 'connected') return false
  if (!owner) return true

  const userId = currentUserId.value
  const userName = currentUserName.value

  if (typeof owner === 'number') return owner !== userId
  return owner !== userName
}

const statusBadgeText = (registerId) => {
  if (!registerId) return ''

  if (isSelfConnected.value && registerId === activeRegisterId.value) {
    return 'Occupée (vous)'
  }

  const status = registerStatuses.value[registerId]
  const owner = registerOwners.value[registerId]

  if (status === 'connected') {
    const userId = currentUserId.value
    const userName = currentUserName.value

    const isOwn = (typeof owner === 'number' && owner === userId) ||
                  (typeof owner === 'string' && owner === userName)

    if (isOwn) return 'Occupée (vous)'
    return owner ? `Occupée par ${owner}` : 'Occupée'
  }

  if (status === 'error') return 'Erreur'
  return 'Disponible'
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

function normalizeName(name) {
  return (name || '').toString().trim().toLowerCase()
}

function findRegisterForMachine(registers) {
  if (!machineIdentifier.value) return null
  const normalizedMachineName = normalizeName(machineIdentifier.value)
  return registers.find(register => normalizeName(register.name) === normalizedMachineName) || null
}

// ========== API CALLS ==========
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
    else {
      for (const key in data) {
        if (Array.isArray(data[key])) {
          registers = data[key]
          break
        }
      }
    }

    cashRegisters.value = registers

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
        newOwners[register.id] = null
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

const fetchMyActiveSession = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/my-active-session`, { headers: getAuthHeaders() })

    if (data?.has_active_session === false || !data?.data) {
      activeSession.value = null
      const existingSession = localStorage.getItem('cashRegisterSession')
      if (existingSession) {
        try {
          const parsed = JSON.parse(existingSession)
          if (parsed.is_admin_session !== true) {
            localStorage.removeItem('cashRegisterSession')
            localStorage.removeItem('cash_register_session')
          }
        } catch {
          localStorage.removeItem('cashRegisterSession')
          localStorage.removeItem('cash_register_session')
        }
      }
      return
    }

    const session = data?.data
    if (session && isSessionOpen(session)) {
      activeSession.value = session
      selectedCashRegister.value = session.cash_register_id
      connectedCashRegisterName.value = session.cash_register?.name || ''
      localStorage.setItem('cashRegisterSession', JSON.stringify(session))
      localStorage.setItem('cash_register_session', JSON.stringify(session))
      registerStatuses.value = { ...registerStatuses.value, [session.cash_register_id]: 'connected' }
      registerOwners.value = { ...registerOwners.value, [session.cash_register_id]: currentUserId.value || session.user_id }
    } else {
      activeSession.value = null
    }
  } catch (error) {
    if (error.response?.status !== 404) {
      console.error('Erreur récupération session active:', error)
    }
    activeSession.value = null
  }
}

const getCurrentSessionForRegister = async (registerId) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers/${registerId}/current-session`, {
      headers: getAuthHeaders()
    })
    return data?.data || null
  } catch (error) {
    console.error('Erreur récupération session:', error)
    return null
  }
}

const fetchSessionSummary = async (sessionId) => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-sessions/${sessionId}/summary`, { headers: getAuthHeaders() })
    return data?.data || data || null
  } catch (error) {
    if (error.response?.status === 409) {
      return error.response?.data?.data || error.response?.data || null
    }
    throw error
  }
}

// ========== GESTION DES SESSIONS ADMIN ==========
const createAdminSupervisionSession = (registerId, occupantSession, selectedRegister) => {
  // Récupérer les informations du caissier occupant
  const occupantId = occupantSession.user_id || occupantSession.user?.id
  const occupantName = occupantSession.user?.name || occupantSession.user_name
  const realSessionId = occupantSession.id // 🔥 C'est ça qu'on veut pour cash_register_session_id

  console.log('👤 Session supervision - Détails:', {
    real_session_id: realSessionId,    // ID de la session réelle (ex: 1)
    occupant_user_id: occupantId,       // ID du caissier (ex: 2)
    occupant_name: occupantName
  })

  const adminSession = {
    // 🔥 Pour les requêtes SQL, on utilise l'ID de la session réelle
    id: realSessionId,  // ⚠️ Changement: utiliser realSessionId au lieu de occupantId
    cash_register_id: registerId,
    admin_user_id: currentUserId.value,    // Qui est l'admin (ex: 1)
    original_user_id: occupantId,          // Caissier d'origine (ex: 2)
    original_session_id: realSessionId,    // Session réelle (ex: 1)
    user_name: currentUserName.value,
    occupant_name: occupantName,
    is_closed: false,
    is_admin_session: true,
    is_supervision_mode: true,
    started_at: new Date().toISOString(),
    cash_register: selectedRegister
  }

  localStorage.setItem('cashRegisterSession', JSON.stringify(adminSession))
  localStorage.setItem('cash_register_session', JSON.stringify(adminSession))

  console.log('✅ Session supervision créée:', {
    cash_register_session_id: adminSession.id,  // Sera 1 (ID session réelle)
    admin_id: adminSession.admin_user_id,       // Sera 1 (ID admin)
    occupant_id: adminSession.original_user_id  // Sera 2 (ID caissier)
  })

  return adminSession
}

const createAdminNormalSession = (registerId, selectedRegister) => {
  const adminSession = {
    id: currentUserId.value,
    cash_register_id: registerId,
    user_id: currentUserId.value,
    user_name: currentUserName.value,
    is_closed: false,
    is_admin_session: true,
    is_supervision_mode: false,
    started_at: new Date().toISOString(),
    cash_register: selectedRegister
  }

  localStorage.setItem('cashRegisterSession', JSON.stringify(adminSession))
  localStorage.setItem('cash_register_session', JSON.stringify(adminSession))

  console.log('✅ Session normale admin créée:', adminSession)
  return adminSession
}

// ========== GESTION DES SESSIONS CAISSIER ==========
const sendFondDeCaisse = async ({ amount, ticketNumber, note }) => {
  if (!selectedCashRegister.value) {
    alert('Sélectionnez une caisse')
    return
  }

  isProcessing.value = true
  try {
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

// ========== ACTIONS UI ==========
const selectCashRegister = (registerId) => {
  if (loadingRegisters.value || isProcessing.value) return
  if (isRegisterLocked(registerId) && !isAdmin.value) return
  selectedCashRegister.value = registerId
}

const goToMachineManagement = () => {
  router.push({ name: 'dashboard-cash-register-sessions' })
}

const refreshAllStatuses = async () => {
  await fetchCashRegisters()
  if (debugMode.value) console.log('Statuts rafraîchis', registerStatuses.value)
}

const closeModal = () => router.push({ name: 'dashboard-overview' })
const closeAmountModal = () => { isAmountModalOpen.value = false }
const openAmountModal = () => { if (!isProcessing.value) isAmountModalOpen.value = true }
const handleAmountModalSend = (payload) => { if (!isProcessing.value) sendFondDeCaisse(payload) }

const openSummaryModalForSession = async (sessionId) => {
  summaryError.value = ''
  sessionSummary.value = null
  isSummaryModalOpen.value = true
  summaryLoading.value = true

  try {
    const summary = await fetchSessionSummary(sessionId)
    if (!summary) throw new Error("Aucune donnée disponible pour cette session.")
    sessionSummary.value = summary
  } catch (error) {
    summaryError.value = error.message || 'Impossible de récupérer le résumé.'
    console.error('Erreur modal résumé:', error)
  } finally {
    summaryLoading.value = false
  }
}

const continueAfterSummary = () => {
  if (activeSession.value?.id) {
    localStorage.setItem('cashRegisterSession', JSON.stringify(activeSession.value))
    localStorage.setItem('cash_register_session', JSON.stringify(activeSession.value))
  }
  closeSummaryModal()
  router.push({ name: 'dashboard-direct' })
}

const closeSummaryModal = () => {
  isSummaryModalOpen.value = false
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

const initializeSessions = async () => {
  const registers = Array.isArray(cashRegisters.value) ? cashRegisters.value : []

  if (isAdmin.value) {
    if (machineRegister.value) {
      selectedCashRegister.value = machineRegister.value.id
    } else if (registers.length > 0) {
      selectedCashRegister.value = registers[0].id
    } else {
      selectedCashRegister.value = null
    }
    return
  }

  if (hasActiveSession.value && activeRegisterId.value) {
    selectedCashRegister.value = activeRegisterId.value
    return
  }

  if (!selectedCashRegister.value) {
    if (machineRegister.value && !isRegisterLocked(machineRegister.value.id)) {
      selectedCashRegister.value = machineRegister.value.id
      return
    }

    const unlockedRegister = registers.find(r => !isRegisterLocked(r.id))
    if (unlockedRegister) {
      selectedCashRegister.value = unlockedRegister.id
    } else if (registers.length > 0) {
      selectedCashRegister.value = registers[0].id
    } else {
      selectedCashRegister.value = null
    }
  }
}

// ========== ACTION PRINCIPALE ==========
const onConnectButtonClick = async () => {
  if (isProcessing.value) return

  // ADMIN
  if (isAdmin.value) {
    if (!selectedCashRegister.value) {
      alert('Veuillez sélectionner une caisse')
      return
    }

    const registerId = selectedCashRegister.value
    const selectedRegister = cashRegisters.value.find(r => r.id === registerId)
    const isOccupied = selectedRegister?.is_occupied === true

    if (isOccupied) {
      const occupantSession = await getCurrentSessionForRegister(registerId)
      if (occupantSession) {
        createAdminSupervisionSession(registerId, occupantSession, selectedRegister)
      } else {
        console.warn('⚠️ Impossible de récupérer la session, création normale')
        createAdminNormalSession(registerId, selectedRegister)
      }
    } else {
      createAdminNormalSession(registerId, selectedRegister)
    }

    router.push({ name: 'dashboard-direct' })
    return
  }

  // CAISSIER
  if (!selectedCashRegister.value) {
    alert('Sélectionnez une caisse')
    return
  }

  const registerId = selectedCashRegister.value
  const status = registerStatuses.value[registerId]
  const isSelf = isSelfConnected.value && registerId === activeRegisterId.value

  if (isSelf) {
    router.push({ name: 'dashboard-direct' })
    return
  }

  if (status === 'connected') {
    try {
      const { data } = await axios.get(`${API_BASE_URL}/cash-registers/${registerId}/current-session`, {
        headers: getAuthHeaders()
      })
      const session = data?.data
      if (session && session.id) {
        await openSummaryModalForSession(session.id)
      } else {
        alert('Impossible de récupérer la session active.')
      }
    } catch (error) {
      console.error('Erreur accès session:', error)
      alert('Impossible d\'accéder à la session de cette caisse.')
    }
  } else {
    openAmountModal()
  }
}

// ========== INIT ==========
onMounted(async () => {
  try {
    await loadUserData()
  } catch (error) {
    console.error('Erreur chargement utilisateur:', error)
  }

  resolveMachineIdentifier()
  await fetchCashRegisters()
  await fetchMyActiveSession()
  await initializeSessions()
})
</script>

<style scoped>
/* ... vos styles existants ... */
.selected {
  border-color: #4f46e5 !important;
  background-color: #eef2ff !important;
  box-shadow: 0 0 0 2px #4f46e5;
  transition: all 0.2s ease;
}

.summary-overlay {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  background: rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(8px);
}

.summary-modal {
  position: relative;
  width: min(100%, 760px);
  border: 1px solid rgb(226 232 240 / 0.95);
  border-radius: 1.75rem;
  overflow: hidden;
  background: radial-gradient(circle at top right, rgb(224 231 255 / 0.85), transparent 30%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  box-shadow: 0 32px 80px -36px rgba(15, 23, 42, 0.45);
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
  .summary-modal-head, .summary-modal-body, .summary-modal-foot {
    padding-left: 1rem;
    padding-right: 1rem;
  }
  .summary-modal-head, .summary-modal-foot {
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
