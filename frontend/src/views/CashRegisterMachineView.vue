<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur">
    <div class="relative mx-4 w-full max-w-5xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
      <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Configuration</p>
          <h1 class="mt-2 flex items-center gap-2 text-2xl font-semibold text-slate-900">
            <i class="fas fa-desktop text-indigo-500"></i>
            Associer une caisse à cette machine
          </h1>
          <p class="mt-1 text-sm text-slate-500">
            Identifiez le poste actuel et rattachez-le au point de vente pour pouvoir ouvrir une session
            de caisse et encaisser des ventes.
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
            @click="resetForm"
          >
            <i class="fas fa-rotate"></i>
            Nouvelle caisse
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-rose-200 hover:text-rose-600"
            @click="closeModal"
          >
            <i class="fas fa-xmark"></i>
            Fermer
          </button>
        </div>
      </header>

      <div class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <section class="space-y-5 rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
          <header class="flex items-start justify-between gap-2 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Machine détectée</p>
              <p class="mt-2 text-sm text-slate-600">
                {{ machineName ? 'Nom récupéré automatiquement.' : 'Impossible de détecter le nom.' }}
              </p>
              <p class="mt-1 text-lg font-semibold text-slate-900">
                {{ machineName || '—' }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 transition hover:border-indigo-200 hover:bg-indigo-50"
              @click="refreshMachineName"
            >
              <i class="fas fa-arrows-rotate"></i>
              Réessayer
            </button>
          </header>

          <form class="space-y-5" @submit.prevent="submitForm">
            <div class="space-y-2">
              <label for="cashregister-name" class="text-sm font-medium text-slate-700">Nom de la caisse / machine</label>
              <input
                id="cashregister-name"
                v-model="formName"
                type="text"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                placeholder="Ex. POS-CAISSE-01"
                maxlength="255"
                required
              />
              <p class="text-xs text-slate-500">Vous pouvez garder le nom détecté automatiquement ou le personnaliser.</p>
            </div>

            <div class="space-y-2">
              <label for="point-of-sale" class="text-sm font-medium text-slate-700">Point de vente</label>
              <select
                id="point-of-sale"
                v-model="selectedPointOfSaleId"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                :disabled="!isAdmin"
                required
              >
                <option :value="null" disabled>Choisir un point de vente</option>
                <option v-for="pos in pointOfSales" :key="pos.id" :value="pos.id">
                  {{ pos.name }}
                </option>
              </select>
              <p v-if="!isAdmin" class="text-xs text-slate-500">Ce champ est automatiquement défini selon votre compte.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="isSaving || !formValid"
              >
                <i v-if="isSaving" class="fas fa-spinner fa-spin"></i>
                <span>{{ isSaving ? 'Enregistrement…' : 'Enregistrer' }}</span>
              </button>
              <p v-if="errorMessage" class="text-sm font-semibold text-rose-500">{{ errorMessage }}</p>
              <p v-else-if="successMessage" class="text-sm font-semibold text-emerald-600">{{ successMessage }}</p>
            </div>
          </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
          <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Caisses enregistrées</h2>
              <p class="text-xs text-slate-400">Liste par point de vente | Total {{ cashRegisters.length }}</p>
            </div>
          </header>

          <div class="max-h-[420px] overflow-y-auto px-5 py-4">
            <template v-if="groupedRegisters.length">
              <div
                v-for="group in groupedRegisters"
                :key="group.pointOfSaleId ?? 'none'"
                class="mb-5 rounded-2xl border border-slate-200 bg-slate-50/80"
              >
                <div class="flex items-center justify-between rounded-2xl border-b border-slate-200 bg-white px-4 py-3">
                  <div>
                    <p class="text-sm font-semibold text-slate-800">{{ group.pointOfSaleName || 'Sans point de vente' }}</p>
                    <p class="text-xs text-slate-500">{{ group.registers.length }} caisse(s)</p>
                  </div>
                </div>
                <ul class="divide-y divide-slate-200">
                  <li
                    v-for="register in group.registers"
                    :key="register.id"
                    class="flex items-center justify-between px-4 py-3 text-sm text-slate-700"
                  >
                    <div>
                      <p class="font-semibold text-slate-900">{{ register.name }}</p>
                      <p class="text-xs text-slate-400">ID : {{ register.id }}</p>
                    </div>
                    <button
                      type="button"
                      class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
                      @click="confirmDelete(register)"
                    >
                      Supprimer
                    </button>
                  </li>
                </ul>
              </div>
            </template>
            <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
              Aucune caisse enregistrée pour le moment.
            </p>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { API_BASE_URL, API_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'
import { storage } from '@/utils/storage'

const { isAdmin, pointsOfSale, activePos } = useAuth()

const machineName = ref('')
const formName = ref('')
const pointOfSales = ref([])
const selectedPointOfSaleId = ref(null)
const cashRegisters = ref([])

const isSaving = ref(false)
const successMessage = ref('')
const errorMessage = ref('')


const getAuthHeaders = () => {
  const auth = storage.getAuth()
  if (!auth?.token) throw new Error("Token d'authentification manquant")
  return { Authorization: `Bearer ${auth.token}` }
}

const detectMachineName = () => {
  let detected = ''

  try {
    if (typeof window !== 'undefined' && typeof window.require === 'function') {
      const os = window.require?.('os')
      if (os?.hostname) {
        detected = os.hostname()
      }
    }
  } catch (error) {
    console.warn('Détection via API Node impossible:', error)
  }

  if (!detected && typeof window !== 'undefined') {
    const stored =
      localStorage.getItem('cashRegisterMachineName') ||
      localStorage.getItem('cashPrinterName')
    if (stored) {
      detected = stored
    }
  }

  if (!detected && typeof window !== 'undefined') {
    const hostname = window.location?.hostname
    if (hostname && hostname !== 'localhost') {
      detected = hostname
    }
  }

  if (!detected && typeof navigator !== 'undefined') {
    const platform = navigator.userAgentData?.platform || navigator.platform || ''
    if (platform) {
      detected = `POS-${platform.replace(/\s+/g, '-').toUpperCase()}`
    }
  }

  return detected
}

const applyMachineName = () => {
  machineName.value = detectMachineName()
  formName.value = machineName.value
}

const refreshMachineName = () => {
  applyMachineName()
}

const fetchPointOfSales = async () => {
  try {
    // pointsOfSale is provided by useAuth()
    if (isAdmin.value) {
      const { data } = await axios.get(`${API_BASE_URL}/point-of-sales`, { headers: getAuthHeaders() })
      // Assuming pointsOfSale might need to be set if not reactive from useAuth, 
      // but useAuth.pointsOfSale is already reactive. Let's just use it.
    }
  } catch (error) {
    console.error('Erreur chargement points de vente:', error)
  }
}

const fetchCashRegisters = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers`, {
      headers: getAuthHeaders()
    })
    cashRegisters.value = data?.data || data || []
  } catch (error) {
    console.error('Erreur lors du chargement des caisses:', error)
  }
}

const groupedRegisters = computed(() => {
  if (!cashRegisters.value.length) return []

  const groups = new Map()
  cashRegisters.value.forEach((register) => {
    const posId = register.point_of_sale_id || 'none'
    if (!groups.has(posId)) {
      groups.set(posId, {
        pointOfSaleId: register.point_of_sale_id ?? null,
        pointOfSaleName: register.point_of_sale?.name || pointOfSales.value.find(pos => pos.id === register.point_of_sale_id)?.name || 'Sans point de vente',
        registers: []
      })
    }
    groups.get(posId).registers.push(register)
  })

  return Array.from(groups.values()).map(group => ({
    ...group,
    registers: group.registers.sort((a, b) => a.name.localeCompare(b.name))
  }))
})

const formValid = computed(() => {
  if (!formName.value?.trim()) return false
  if (isAdmin.value) {
    return Boolean(selectedPointOfSaleId.value)
  }
  return true
})

const submitForm = async () => {
  if (!formValid.value) return

  isSaving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const payload = {
      name: formName.value.trim()
    }

    if (isAdmin.value && selectedPointOfSaleId.value) {
      payload.point_of_sale_id = selectedPointOfSaleId.value
    }

    const { data } = await axios.post(`${API_BASE_URL}/cash-registers`, payload, {
      headers: getAuthHeaders()
    })

    const created = data?.data || data || null
    if (created) {
      cashRegisters.value.push(created)
      successMessage.value = 'Caisse enregistrée avec succès.'
      localStorage.setItem('cashRegisterMachineName', created.name)
      localStorage.setItem('cashPrinterName', created.name)
      machineName.value = created.name
      formName.value = created.name
    }
  } catch (error) {
    console.error('Erreur enregistrement caisse:', error)
    const validationError = error.response?.data?.details?.name?.[0]
    errorMessage.value = validationError
      || error.response?.data?.message
      || error.message
      || 'Enregistrement impossible.'
  } finally {
    isSaving.value = false
    await fetchCashRegisters()
  }
}

const resetForm = () => {
  applyMachineName()
  successMessage.value = ''
  errorMessage.value = ''
}

const deleteRegister = async (register) => {
  if (!register) return
  isSaving.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await axios.delete(`${API_BASE_URL}/cash-registers/${register.id}`, {
      headers: getAuthHeaders()
    })

    cashRegisters.value = cashRegisters.value.filter(item => item.id !== register.id)
    successMessage.value = `Caisse « ${register.name} » supprimée.`
  } catch (error) {
    console.error('Erreur suppression caisse:', error)
    errorMessage.value = error.response?.data?.message || error.message || 'Suppression impossible.'
  } finally {
    isSaving.value = false
  }
}

const confirmDelete = (register) => {
  if (!register) return
  const confirmed = window.confirm(`Confirmer la suppression de la caisse « ${register.name} » ?`)
  if (confirmed) {
    deleteRegister(register)
  }
}

onMounted(async () => {
  await loadUserData()
  applyMachineName()
  await Promise.all([fetchPointOfSales(), fetchCashRegisters()])
  if (!isAdmin.value && userPointOfSaleId.value) {
    selectedPointOfSaleId.value = userPointOfSaleId.value
  }
})

const router = useRouter()
function closeModal() {
  router.push({ name: 'dashboard-overview' })
}
</script>

<style scoped>
</style>
