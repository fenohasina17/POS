<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <h1 class="text-2xl font-bold">Vue d'ensemble</h1>

      <!-- Filtres -->
      <div class="flex flex-wrap items-center gap-3 text-sm">
        <select v-model="filters.restaurant_id" @change="applyFilters"
          class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-gray-300 focus:outline-none focus:border-emerald-500">
          <option value="">Tous les restaurants</option>
          <option v-for="r in restaurants" :key="r" :value="r">{{ r }}</option>
        </select>
        <input type="date" v-model="filters.date_from" @change="applyFilters"
          class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-gray-300 focus:outline-none focus:border-emerald-500" />
        <input type="date" v-model="filters.date_to" @change="applyFilters"
          class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-gray-300 focus:outline-none focus:border-emerald-500" />
        <button @click="resetFilters"
          class="text-xs text-gray-500 hover:text-gray-300 transition-colors">
          Réinitialiser
        </button>
        <ExportButton
          :date-from="filters.date_from"
          :date-to="filters.date_to"
          :restaurant-id="filters.restaurant_id" />
      </div>
    </div>

    <!-- Alertes actives -->
    <AlertBanner />

    <!-- KPI Cards -->
    <div v-if="store.loading" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="i in 4" :key="i" class="bg-gray-800 rounded-xl p-5 animate-pulse h-24"></div>
    </div>

    <div v-else-if="store.data" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <StatCard label="Terminaux en ligne"
        :value="store.data.terminals.online"
        :sub="`/ ${store.data.terminals.total} total`"
        color="emerald" />
      <StatCard label="Terminaux hors ligne"
        :value="store.data.terminals.offline"
        sub="Vérifier la connexion"
        color="red" />
      <StatCard label="Ventes (période)"
        :value="store.data.sales_today.count"
        :sub="evolutionLabel"
        color="blue" />
      <StatCard label="CA (période)"
        :value="formatCurrency(store.data.sales_today.revenue)"
        :sub="evolutionLabel"
        color="violet" />
    </div>

    <!-- Sync en attente -->
    <div v-if="store.data && store.data.terminals.pending_sync > 0"
      class="mb-6 bg-yellow-900/30 border border-yellow-700 rounded-xl p-4 flex items-center gap-3">
      <span class="text-yellow-400">⚠</span>
      <span class="text-yellow-300 text-sm">
        <strong>{{ store.data.terminals.pending_sync }}</strong> enregistrement(s) en attente de synchronisation.
      </span>
    </div>

    <!-- Graphique + tableau -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-gray-800 rounded-xl p-5">
        <h2 class="font-semibold mb-4 text-gray-300">Chiffre d'affaires — évolution</h2>
        <Line v-if="chartData" :data="chartData" :options="chartOptions" />
        <p v-else class="text-gray-500 text-sm">Aucune donnée disponible.</p>
      </div>

      <div class="bg-gray-800 rounded-xl p-5">
        <h2 class="font-semibold mb-4 text-gray-300">CA par restaurant</h2>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-gray-500 border-b border-gray-700">
              <th class="text-left pb-2">Restaurant</th>
              <th class="text-right pb-2">Ventes</th>
              <th class="text-right pb-2">CA</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in store.data?.by_restaurant ?? []" :key="r.restaurant_id"
              class="border-b border-gray-700/50">
              <td class="py-2 text-gray-200">{{ r.restaurant_id }}</td>
              <td class="py-2 text-right text-gray-400">{{ r.sales_count }}</td>
              <td class="py-2 text-right text-emerald-400 font-mono">{{ formatCurrency(r.revenue) }}</td>
            </tr>
            <tr v-if="!store.data?.by_restaurant?.length">
              <td colspan="3" class="py-4 text-center text-gray-600">Aucune vente sur la période</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler } from 'chart.js'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/StatCard.vue'
import AlertBanner from '@/components/AlertBanner.vue'
import ExportButton from '@/components/ExportButton.vue'
import echo from '@/utils/echo'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

const store = useDashboardStore()

const today = new Date().toISOString().slice(0, 10)
const filters = ref({ restaurant_id: '', date_from: today, date_to: today })

const restaurants = computed(() =>
  [...new Set((store.data?.by_restaurant ?? []).map(r => r.restaurant_id))]
)

function applyFilters() {
  store.fetch({
    restaurant_id: filters.value.restaurant_id || undefined,
    date_from:     filters.value.date_from || undefined,
    date_to:       filters.value.date_to   || undefined,
  })
}

function resetFilters() {
  filters.value = { restaurant_id: '', date_from: today, date_to: today }
  store.fetch()
}

const evolutionLabel = computed(() => {
  const pct = store.data?.sales_today?.evolution_pct
  if (pct === null || pct === undefined) return ''
  return `${pct >= 0 ? '+' : ''}${pct}% vs période préc.`
})

const chartData = computed(() => {
  const days = store.data?.sales_7_days
  if (!days?.length) return null
  return {
    labels: days.map(d => d.date),
    datasets: [{
      label: 'CA (€)',
      data: days.map(d => d.revenue),
      borderColor: '#34d399',
      backgroundColor: 'rgba(52,211,153,0.1)',
      fill: true,
      tension: 0.4,
    }],
  }
})

const chartOptions = {
  responsive: true,
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks: { color: '#6b7280' }, grid: { color: '#1f2937' } },
    y: { ticks: { color: '#6b7280' }, grid: { color: '#1f2937' } },
  },
}

function formatCurrency(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val ?? 0)
}

let interval
onMounted(() => {
  store.fetch()
  interval = setInterval(() => store.fetch(), 30_000)
  echo.channel('central-dashboard').listen('.terminal.data', () => applyFilters())
})
onUnmounted(() => {
  clearInterval(interval)
  echo.leaveChannel('central-dashboard')
})
</script>
