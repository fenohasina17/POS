<template>
  <section class="ui-card mx-auto flex max-w-3xl flex-col gap-4 p-5">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
      <div>
        <p class="ui-page-eyebrow">Imprimantes de cette caisse</p>
        <h1 class="mt-1 text-xl font-semibold text-slate-900">Routage des bons</h1>
        <p class="mt-1 text-sm text-slate-500">
          Choisissez l'imprimante de chaque poste. En cas de panne ou avec peu d'imprimantes,
          tout peut sortir sur une seule.
        </p>
      </div>
      <button type="button" class="ui-btn rounded-full" @click="router.back()">
        <FontAwesomeIcon icon="fa-solid fa-arrow-left" /> Retour
      </button>
    </header>

    <!-- Hors application de caisse : les imprimantes de la machine ne sont pas accessibles -->
    <p v-if="!isElectron" class="rounded-2xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800">
      Ce réglage se fait dans l'application de caisse (Electron), sur la machine reliée aux imprimantes.
    </p>

    <template v-else>
      <p v-if="loading" class="text-sm text-slate-500">Détection des imprimantes…</p>
      <p v-else-if="!printers.length" class="rounded-2xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
        Aucune imprimante détectée sur cette machine.
      </p>

      <template v-else>
        <!-- Panne / une seule imprimante -->
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm font-semibold text-slate-800">Tout sur une seule imprimante</p>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <select v-model="singlePrinter" class="ui-input min-w-[14rem] flex-1" aria-label="Imprimante unique">
              <option value="">Choisir une imprimante…</option>
              <option v-for="p in printers" :key="p.name" :value="p.name">{{ printerLabel(p) }}</option>
            </select>
            <button type="button" class="ui-btn ui-btn-primary rounded-full" :disabled="!singlePrinter" @click="applySingle">
              Appliquer à tous les postes
            </button>
          </div>
        </div>

        <!-- Un réglage par poste -->
        <ul class="divide-y divide-slate-100">
          <li v-for="station in STATIONS" :key="station.id" class="flex flex-wrap items-center gap-3 py-3">
            <div class="min-w-[10rem] flex-1">
              <p class="text-sm font-semibold text-slate-800">{{ station.label }}</p>
              <p class="text-xs text-slate-400">Sortie actuelle : {{ effective(station.id) }}</p>
            </div>
            <select v-model="routing[station.id]" class="ui-input min-w-[14rem]" :aria-label="`Imprimante ${station.label}`">
              <option value="">Automatique</option>
              <option v-for="p in printers" :key="p.name" :value="p.name">{{ printerLabel(p) }}</option>
              <option v-if="routing[station.id] && !isDetected(routing[station.id])" :value="routing[station.id]">
                {{ routing[station.id] }} (introuvable)
              </option>
            </select>
            <button type="button" class="ui-btn rounded-full" :disabled="testing === station.id" @click="testStation(station)">
              <FontAwesomeIcon icon="fa-solid fa-print" /> Tester
            </button>
          </li>
        </ul>

        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
          <button type="button" class="ui-btn rounded-full" @click="resetAll">Tout en automatique</button>
          <div class="flex items-center gap-3">
            <span v-if="message" class="text-sm" :class="messageError ? 'text-danger-600' : 'text-success-600'">{{ message }}</span>
            <button type="button" class="ui-btn ui-btn-primary rounded-full" :disabled="saving" @click="save">
              <FontAwesomeIcon icon="fa-solid fa-check" /> Enregistrer
            </button>
          </div>
        </footer>
      </template>
    </template>
  </section>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowLeft, faPrint, faCheck } from '@fortawesome/free-solid-svg-icons'
import { STATIONS, resolvePrinter, routeAllTo, normalizeRouting } from '@/services/printing/printerRouting'
import { printingService } from '@/services/printing/PrintingService'

library.add(faArrowLeft, faPrint, faCheck)

const router = useRouter()
const api = typeof window !== 'undefined' ? window.electronAPI : null
const isElectron = !!(api && api.getPrinterRouting)

const printers = ref([])
const routing = reactive(Object.fromEntries(STATIONS.map(({ id }) => [id, ''])))
const singlePrinter = ref('')
const loading = ref(true)
const saving = ref(false)
const testing = ref(null)
const message = ref('')
const messageError = ref(false)

const printerLabel = (p) => `${p.displayName || p.name}${p.isDefault ? ' (par défaut)' : ''}`
const isDetected = (name) => printers.value.some((p) => p.name === name)

// Imprimante réellement utilisée avec le réglage affiché (même règle qu'Electron)
const effective = (station) => resolvePrinter(station, normalizeRouting(routing), printers.value).name || 'aucune'

const setRouting = (values) => {
  for (const { id } of STATIONS) routing[id] = values?.[id] || ''
}

const notify = (text, isError = false) => {
  message.value = text
  messageError.value = isError
}

const applySingle = () => {
  setRouting(routeAllTo(singlePrinter.value))
  notify('Tous les postes sur « ' + singlePrinter.value + ' » — pensez à enregistrer.')
}

const resetAll = () => {
  setRouting({})
  notify('Tous les postes en automatique — pensez à enregistrer.')
}

const save = async () => {
  saving.value = true
  try {
    setRouting(await api.setPrinterRouting(normalizeRouting(routing)))
    notify('Réglage enregistré.')
  } catch (err) {
    notify('Enregistrement impossible : ' + err.message, true)
  } finally {
    saving.value = false
  }
}

// Le test suit le réglage enregistré : enregistrer d'abord pour tester un changement
const testStation = async (station) => {
  testing.value = station.id
  try {
    const result = await printingService.printOrderPDF(
      { name: 'TEST', ticketNumber: `Test ${station.label}` },
      [{ name: `Test impression – ${station.label}`, quantity: 1, price: 0 }],
      station.id
    )
    if (result?.success === false) notify(`Test ${station.label} : ${result.message}`, true)
    else notify(`Test envoyé pour ${station.label}.`)
  } catch (err) {
    notify(`Test ${station.label} : ${err.message}`, true)
  } finally {
    testing.value = null
  }
}

onMounted(async () => {
  if (!isElectron) {
    loading.value = false
    return
  }
  try {
    const [detected, saved] = await Promise.all([api.getPrinters(), api.getPrinterRouting()])
    printers.value = detected || []
    setRouting(saved)
  } catch (err) {
    notify('Lecture des imprimantes impossible : ' + err.message, true)
  } finally {
    loading.value = false
  }
})
</script>
