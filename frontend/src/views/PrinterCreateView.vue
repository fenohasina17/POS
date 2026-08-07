<template>
  <div class="min-h-screen bg-slate-100 text-slate-900">
    <Profile />
    <section class="pt-24 pb-12">
      <div class="max-w-3xl mx-auto px-4">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
          <header class="mb-8">
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Configuration</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900 flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl">
                <FontAwesomeIcon :icon="faPrint" />
              </span>
              Ajouter une imprimante
            </h1>
            <p class="mt-2 text-sm text-slate-500">
              Renseignez les informations ci-dessous pour connecter l'imprimante à la caisse.
            </p>
          </header>

          <form class="space-y-6" @submit.prevent="savePrinter">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-slate-800">Machine locale</h2>
                <button
                  type="button"
                  class="px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700 border border-slate-200 rounded-xl transition hover:border-indigo-200 hover:bg-indigo-50"
                  @click="refreshMachineName"
                >
                  Réessayer la détection
                </button>
              </div>

              <div class="space-y-3">
                <div class="flex flex-col gap-1">
                  <label class="text-sm font-medium text-slate-700" for="machine-name">Nom détecté</label>
                  <input
                    id="machine-name"
                    v-model="printer.name"
                    type="text"
                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                    :class="{ 'bg-slate-100 text-slate-500': autoDetectedName && !allowNameEdit }"
                    :readonly="!allowNameEdit && autoDetectedName"
                    placeholder="Nom de l'imprimante"
                    maxlength="255"
                    required
                  />
                </div>

                <p v-if="autoDetectedName" class="text-xs text-emerald-600 flex items-center gap-2">
                  <FontAwesomeIcon :icon="faCheckCircle" />
                  Nom détecté automatiquement : <strong>{{ autoDetectedName }}</strong>
                </p>
                <p v-else class="text-xs text-amber-600 flex items-center gap-2">
                  <FontAwesomeIcon :icon="faTriangleExclamation" />
                  Impossible de détecter automatiquement le nom de la machine. Saisissez-le manuellement.
                </p>

                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                  <input type="checkbox" v-model="allowNameEdit" class="rounded" />
                  Autoriser la modification manuelle du nom
                </label>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-6 space-y-5">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="cash-register">
                  Caisse associée
                </label>
                <select
                  id="cash-register"
                  v-model="printer.cash_register_id"
                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                  required
                >
                  <option :value="null" disabled>Choisir une caisse</option>
                  <option v-for="register in cashRegisters" :key="register.id" :value="register.id">
                    {{ register.name }}
                    <span v-if="register.point_of_sale"> — {{ register.point_of_sale.name }}</span>
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="printer-type">
                  Type d'imprimante
                </label>
                <select
                  id="printer-type"
                  v-model="printer.printer_type_id"
                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                  required
                >
                  <option :value="null" disabled>Choisir un type d'imprimante</option>
                  <option v-for="type in printerTypes" :key="type.id" :value="type.id">
                    {{ type.name }}
                  </option>
                </select>
              </div>

              <div class="grid md:grid-cols-2 gap-5">
                <label class="block">
                  <span class="text-sm font-medium text-slate-700">Timeout (secondes)</span>
                  <input
                    v-model.number="printer.timeout"
                    type="number"
                    min="1"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                  />
                </label>

                <div class="space-y-3">
                  <label class="inline-flex items-center gap-3 text-sm text-slate-700">
                    <input type="checkbox" v-model="printer.is_default" class="rounded" />
                    Imprimante par défaut pour cette caisse
                  </label>
                  <label class="inline-flex items-center gap-3 text-sm text-slate-700">
                    <input type="checkbox" v-model="printer.is_active" class="rounded" />
                    Imprimante active
                  </label>
                </div>
              </div>
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-200">
              <div class="text-sm">
                <p v-if="saveError" class="text-rose-600">{{ saveError }}</p>
                <p v-else class="text-slate-500">Assurez-vous que l'imprimante est installée sur la machine locale.</p>
              </div>
              <div class="flex gap-3">
                <button
                  type="button"
                  class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 transition hover:border-slate-200 hover:bg-slate-50"
                  @click="goBack"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                  :disabled="isSaving || !isFormValid"
                >
                  <FontAwesomeIcon v-if="isSaving" :icon="faSpinner" class="animate-spin" />
                  {{ isSaving ? 'Enregistrement...' : 'Créer l\'imprimante' }}
                </button>
              </div>
            </footer>
          </form>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import Profile from './Profile.vue'
import { usePrinterTypes } from '../composables/usePrinterTypes.js'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPrint, faCheckCircle, faTriangleExclamation, faSpinner } from '@fortawesome/free-solid-svg-icons'
library.add(faPrint, faCheckCircle, faTriangleExclamation, faSpinner)

const router = useRouter()

const printer = reactive({
  name: '',
  cash_register_id: null,
  printer_type_id: null,
  connection_type: 'cups',
  timeout: 30,
  is_default: false,
  is_active: true
})

const autoDetectedName = ref('')
const allowNameEdit = ref(false)
const cashRegisters = ref([])
const isSaving = ref(false)
const saveError = ref('')

const { printerTypes, fetchPrinterTypes } = usePrinterTypes()

const detectMachineName = () => {
  let detected = ''
  try {
    if (typeof window !== 'undefined' && typeof window.require === 'function') {
      const os = window.require?.('os')
      if (os?.hostname) detected = os.hostname()
    }
  } catch (error) {
    console.warn('Detection via Node API impossible:', error)
  }
  if (!detected && typeof window !== 'undefined') {
    const stored = localStorage.getItem('cashRegisterMachineName') || localStorage.getItem('cashPrinterName')
    if (stored) detected = stored
  }
  if (!detected && typeof window !== 'undefined') {
    const hostname = window.location?.hostname
    if (hostname && hostname !== 'localhost') detected = hostname
  }
  if (!detected && typeof navigator !== 'undefined') {
    const platform = navigator.userAgentData?.platform || navigator.platform || ''
    if (platform) detected = `POS-${platform.replace(/\s+/g, '-').toUpperCase()}`
  }
  return detected
}

const applyDetectedName = () => {
  autoDetectedName.value = detectMachineName()
  if (autoDetectedName.value) {
    printer.name = autoDetectedName.value
    allowNameEdit.value = false
    localStorage.setItem('cashPrinterName', autoDetectedName.value)
    localStorage.setItem('cashRegisterMachineName', autoDetectedName.value)
  } else {
    allowNameEdit.value = true
  }
}

const refreshMachineName = () => { applyDetectedName() }

const fetchCashRegisters = async () => {
  try {
    const token = sessionStorage.getItem('token')
    const response = await axios.get(`${API_BASE_URL}/cash-registers`, {
      headers: { Authorization: `Bearer ${token}` }
    })
    cashRegisters.value = response.data.data ? response.data.data : response.data
  } catch (error) {
    console.error('Erreur lors du chargement des caisses:', error)
  }
}

onMounted(async () => {
  applyDetectedName()
  await fetchCashRegisters()
  await fetchPrinterTypes()
})

const isFormValid = computed(() => printer.name.trim() && printer.cash_register_id && printer.printer_type_id)

const savePrinter = async () => {
  if (!isFormValid.value) return
  try {
    isSaving.value = true
    saveError.value = ''
    const token = sessionStorage.getItem('token')
    const payload = { ...printer, name: printer.name.trim(), connection_type: 'cups', ip_address: null, port: null }
    const { data } = await axios.post(`${API_BASE_URL}/printers`, payload, { headers: { Authorization: `Bearer ${token}` } })
    const created = data?.data || data
    if (created?.name) {
      localStorage.setItem('cashPrinterName', created.name)
      localStorage.setItem('cashRegisterMachineName', created.name)
      autoDetectedName.value = created.name
    }
    router.push({ name: 'printers' })
  } catch (error) {
    console.error('Erreur création imprimante:', error)
    saveError.value = error.response?.data?.message || error.message || 'Erreur lors de la création'
  } finally {
    isSaving.value = false
  }
}

const goBack = () => { router.back() }
</script>
