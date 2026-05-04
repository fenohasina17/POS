<template>
  <div class="p-4 md:p-6">
    <section class="mx-auto w-full max-w-6xl space-y-6 rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-lg backdrop-blur-sm">
      <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-500">Exportations</p>
          <h1 class="mt-2 text-3xl font-bold text-slate-900">Exporter les ventes</h1>
          <p class="mt-2 text-sm text-slate-500">
            Filtrez vos ventes par point de vente, caisse et période pour générer un fichier exportable.
          </p>
        </div>
      </header>

      <div class="space-y-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-lg font-semibold text-slate-800">Filtres d'exportation</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <!-- Filtre Point de Vente -->
          <label class="flex flex-col">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Point de vente</span>
            <select
              v-model="filters.pointOfSaleId"
              class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            >
              <option value="">Tous les points de vente</option>
              <option
                v-for="pos in pointOfSales"
                :key="pos.id"
                :value="pos.id"
              >
                {{ pos.name }}
              </option>
            </select>
          </label>

          <!-- Filtre Caisse -->
          <label class="flex flex-col">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Caisse</span>
            <select
              v-model="filters.cashRegisterId"
              :disabled="!availableCashRegisters.length"
              class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 disabled:opacity-60"
            >
              <option value="">Toutes les caisses</option>
              <option
                v-for="register in availableCashRegisters"
                :key="register.id"
                :value="register.id"
              >
                {{ register.name }}
              </option>
            </select>
          </label>

          <!-- Filtre Date Début -->
          <label class="flex flex-col">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Date début</span>
            <input
              type="date"
              v-model="filters.startDate"
              class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            />
          </label>

          <!-- Filtre Date Fin -->
          <label class="flex flex-col">
            <span class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Date fin</span>
            <input
              type="date"
              v-model="filters.endDate"
              class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            />
          </label>
        </div>
      </div>

      <div class="flex justify-center pt-6">
        <button
          type="button"
          @click="exportSales"
          :disabled="isExporting || !isAdmin"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed"
        >
          <FontAwesomeIcon v-if="isExporting" icon="fa-solid fa-circle-notch" class="animate-spin" />
          {{ isExporting ? 'Exportation en cours...' : 'Exporter les ventes (CSV)' }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faDownload, faCircleNotch } from '@fortawesome/free-solid-svg-icons'
import { useAuth } from '@/composables/useAuth'
import { API_BASE_URL } from '@/utils/api'

library.add(faDownload, faCircleNotch)

const { isAdmin, user: currentUser, loadUserData } = useAuth()

const pointOfSales = ref([])
const cashRegisters = ref([])
const filters = ref({
  pointOfSaleId: '',
  cashRegisterId: '',
  startDate: '',
  endDate: ''
})
const isExporting = ref(false)
const exportError = ref('')

const availableCashRegisters = computed(() => {
  // Logique pour filtrer les caisses basées sur le point de vente sélectionné
  if (!filters.value.pointOfSaleId) {
    return cashRegisters.value // Afficher toutes les caisses si aucun POS n'est sélectionné
  }
  return cashRegisters.value.filter(register => String(register?.point_of_sale_id ?? register?.pointOfSaleId) === filters.value.pointOfSaleId)
})

const fetchPointOfSales = async () => {
  try {
    const token = localStorage.getItem('token')
    if (!token) throw new Error('Token manquant')
    const { data } = await axios.get(`${API_BASE_URL}/point-of-sales`, {
      headers: { Authorization: `Bearer ${token}` },
      params: { per_page: 500 }
    })
    pointOfSales.value = Array.isArray(data?.data) ? data.data : []
  } catch (error) {
    console.error('Erreur chargement points de vente:', error)
    // Potentiellement afficher un message d'erreur utilisateur
  }
}

const fetchCashRegisters = async () => {
  try {
    const token = localStorage.getItem('token')
    if (!token) throw new Error('Token manquant')
    const { data } = await axios.get(`${API_BASE_URL}/cash-registers`, {
      headers: { Authorization: `Bearer ${token}` },
      params: { per_page: 500 }
    })
    cashRegisters.value = Array.isArray(data?.data) ? data.data : []
  } catch (error) {
    console.error('Erreur chargement caisses:', error)
    // Potentiellement afficher un message d'erreur utilisateur
  }
}

const getPointOfSaleId = (pos) => pos?.id ?? pos?.point_of_sale_id ?? pos?.pointOfSaleId ?? null
const formatPointOfSaleName = (pos) => pos?.name ?? pos?.label ?? `POS #${getPointOfSaleId(pos) ?? '—'}`
const getRegisterId = (register) => register?.id ?? register?.cash_register_id ?? register?.cashRegisterId ?? null
const formatRegisterName = (register) => register?.name ?? register?.label ?? `Caisse #${getRegisterId(register) ?? '—'}`

const exportSales = async () => {
  if (!isAdmin.value) {
    alert("Seuls les administrateurs peuvent exporter les ventes.")
    return
  }
  if (isExporting.value) return

  exportError.value = ''
  isExporting.value = true

  try {
    const params = { ...filters.value }
    if (params.startDate && params.endDate) {
      // Assurer que les dates sont envoyées dans un format backend compatible si nécessaire (ex: YYYY-MM-DD)
      // Assumer que les dates sont déjà dans un format utilisable ou seront gérées par le backend
    }

    const token = localStorage.getItem('token')
    if (!token) throw new Error('Token manquant')

    const response = await axios.get(`${API_BASE_URL}/sales/export`, {
      params,
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'text/csv', // Important pour le téléchargement de fichiers
      },
      responseType: 'blob', // Important pour télécharger le fichier
    })

    // Créer un lien de téléchargement
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }))
    const link = document.createElement('a')
    link.href = url
    // Définir un nom de fichier pertinent, par exemple basé sur les filtres ou la date
    link.setAttribute('download', `ventes_export_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link)
    link.click()

    link.remove()
    window.URL.revokeObjectURL(url)
    alert('Exportation des ventes terminée !')

  } catch (error) {
    console.error('Erreur lors de l'exportation:', error)
    exportError.value = error.response?.data?.message || error.message || 'Une erreur est survenue lors de l'exportation.'
    alert(`Erreur : ${exportError.value}`)
  } finally {
    isExporting.value = false
  }
}

onMounted(() => {
  loadUserData() // Assurez-vous que les données utilisateur sont chargées pour isAdmin
  fetchPointOfSales()
  fetchCashRegisters()
})
</script>

<style scoped>
.sales-export-view {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  padding: 1.5rem 1rem;
  min-height: 100vh;
  background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 100%);
}
@media (min-width: 768px) {
  .sales-export-view {
    padding: 2rem 3rem;
  }
}
.sales-export-section {
  background-color: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(8px);
  border-radius: 1.5rem;
  border: 1px solid rgba(209, 213, 219, 0.7);
  box-shadow: 0 25px 60px -36px rgba(15, 23, 42, 0.1);
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
.sales-export-header h1 {
  font-size: 1.75rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: 0.04em;
}
.sales-export-header p {
  margin-top: 0.5rem;
  font-size: 0.9rem;
  color: #64748b;
}
.filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1rem;
}
.filters label {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  color: #475569;
  font-weight: 600;
  font-size: 0.9rem;
}
.filters select,
.filters input[type="date"] {
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  border: 1px solid #cbd5f5;
  background: #f8fafc;
  color: #0f172a;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.filters select:focus,
.filters input[type="date"]:focus {
  outline: none;
  border-color: #818cf8;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
}
.filters input[type="date"] {
  /* Specific styling for date inputs if needed */
}
</style>
