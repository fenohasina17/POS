<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Vue d'ensemble</h1>

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
      <StatCard label="Ventes aujourd'hui"
        :value="store.data.sales_today.count"
        :sub="evolutionLabel"
        color="blue" />
      <StatCard label="CA aujourd'hui"
        :value="formatCurrency(store.data.sales_today.revenue)"
        :sub="evolutionLabel"
        color="violet" />
    </div>

    <!-- Sync en attente -->
    <div v-if="store.data && store.data.terminals.pending_sync > 0"
      class="mb-6 bg-yellow-900/30 border border-yellow-700 rounded-xl p-4 flex items-center gap-3">
      <span class="text-yellow-400 text-lg">⚠</span>
      <span class="text-yellow-300 text-sm">
        <strong>{{ store.data.terminals.pending_sync }}</strong> enregistrement(s) en attente de synchronisation sur les terminaux.
      </span>
    </div>

    <!-- Graphique 7 jours + tableau par restaurant -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Courbe CA 7 jours -->
      <div class="bg-gray-800 rounded-xl p-5">
        <h2 class="font-semibold mb-4 text-gray-300">Chiffre d'affaires — 7 derniers jours</h2>
        <Line v-if="chartData" :data="chartData" :options="chartOptions" />
        <p v-else class="text-gray-500 text-sm">Aucune donnée disponible.</p>
      </div>

      <!-- Top restaurants -->
      <div class="bg-gray-800 rounded-xl p-5">
        <h2 class="font-semibold mb-4 text-gray-300">CA par restaurant (aujourd'hui)</h2>
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
              <td colspan="3" class="py-4 text-center text-gray-600">Aucune vente aujourd'hui</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale,
  PointElement, LineElement, Tooltip, Filler
} from 'chart.js'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/StatCard.vue'
import echo from '@/utils/echo'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

const store = useDashboardStore()

const evolutionLabel = computed(() => {
  const pct = store.data?.sales_today?.evolution_pct
  if (pct === null || pct === undefined) return ''
  return `${pct >= 0 ? '+' : ''}${pct}% vs hier`
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

// Rechargement auto toutes les 30s + WebSocket temps réel
let interval
onMounted(() => {
  store.fetch()
  interval = setInterval(() => store.fetch(), 30_000)
  echo.channel('central-dashboard').listen('.terminal.data', () => store.fetch())
})
onUnmounted(() => {
  clearInterval(interval)
  echo.leaveChannel('central-dashboard')
})
</script>
