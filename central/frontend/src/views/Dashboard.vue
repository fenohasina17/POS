<template>
  <div class="space-y-6 px-2 pt-4">

    <!-- Header avec filtres -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Vue d'ensemble</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Supervision centrale</p>
        <p class="text-sm text-slate-500">Données en temps réel · actualisation auto.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <select
          v-model="filters.restaurant_id"
          @change="applyFilters"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
        >
          <option value="">Tous les restaurants</option>
          <option v-for="r in restaurants" :key="r" :value="r">{{ r }}</option>
        </select>
        <input
          type="date"
          v-model="filters.date_from"
          @change="applyFilters"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300"
        />
        <input
          type="date"
          v-model="filters.date_to"
          @change="applyFilters"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300"
        />
        <button @click="resetFilters" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">
          Réinitialiser
        </button>
        <ExportButton :date-from="filters.date_from" :date-to="filters.date_to" :restaurant-id="filters.restaurant_id" />
      </div>
    </div>

    <!-- Alertes -->
    <AlertBanner />

    <!-- Sync en attente -->
    <div
      v-if="store.data && store.data.terminals.pending_sync > 0"
      class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3"
    >
      <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="text-sm text-amber-700">
        <strong>{{ store.data.terminals.pending_sync }}</strong> enregistrement(s) en attente de synchronisation.
      </span>
    </div>

    <!-- KPI Cards skeleton -->
    <div v-if="store.loading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="i in 4" :key="i" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm animate-pulse h-28"></div>
    </div>

    <!-- KPI Cards -->
    <div v-else-if="store.data" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <article v-for="card in kpiCards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
          <span :class="['flex h-12 w-12 items-center justify-center rounded-xl', card.iconBg]">
            <component :is="card.icon" class="h-5 w-5" />
          </span>
          <span v-if="card.badge" :class="['text-[10px] font-bold px-2 py-0.5 rounded-full', card.badgeClass]">
            {{ card.badge }}
          </span>
        </div>
        <p class="mt-4 text-2xl font-bold text-slate-900">{{ card.value }}</p>
        <p class="text-xs font-semibold text-slate-500">{{ card.label }}</p>
        <p v-if="card.sub" class="mt-0.5 text-[10px] text-slate-400">{{ card.sub }}</p>
      </article>
    </div>

    <!-- Graphiques -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- CA 7 jours -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="mb-1 text-xs font-semibold uppercase tracking-[0.15em] text-indigo-500">Évolution</p>
        <p class="mb-4 text-base font-semibold text-slate-900">Chiffre d'affaires — 7 jours</p>
        <Line v-if="chartData" :data="chartData" :options="chartOptions" />
        <p v-else class="py-8 text-center text-sm text-slate-400">Aucune donnée disponible.</p>
      </div>

      <!-- CA par restaurant -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="mb-1 text-xs font-semibold uppercase tracking-[0.15em] text-indigo-500">Répartition</p>
        <p class="mb-4 text-base font-semibold text-slate-900">CA par restaurant</p>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
              <th class="pb-2 text-left">Restaurant</th>
              <th class="pb-2 text-right">Ventes</th>
              <th class="pb-2 text-right">CA</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="r in store.data?.by_restaurant ?? []"
              :key="r.restaurant_id"
              class="border-b border-slate-50"
            >
              <td class="py-2.5 font-medium text-slate-800">{{ r.restaurant_id }}</td>
              <td class="py-2.5 text-right text-slate-500">{{ r.sales_count }}</td>
              <td class="py-2.5 text-right font-mono font-semibold text-indigo-600">{{ fmt(r.revenue) }}</td>
            </tr>
            <tr v-if="!store.data?.by_restaurant?.length">
              <td colspan="3" class="py-8 text-center text-sm text-slate-400">Aucune vente sur la période.</td>
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
import AlertBanner from '@/components/AlertBanner.vue'
import ExportButton from '@/components/ExportButton.vue'

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
    date_from:     filters.value.date_from      || undefined,
    date_to:       filters.value.date_to        || undefined,
  })
}
function resetFilters() {
  filters.value = { restaurant_id: '', date_from: today, date_to: today }
  store.fetch()
}

const fmt = val => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val ?? 0)

// Icônes inline
const IconWifi   = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>` }
const IconWifiOff = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M6.343 17.657L4.929 19.07M3 3l18 18M9.88 9.88A5 5 0 0112 9c1.657 0 3.156.807 4.121 2.062M1.394 9.393c1.58-1.58 3.43-2.71 5.433-3.36"/></svg>` }
const IconReceipt = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>` }
const IconCurrency = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>` }

const evolutionLabel = computed(() => {
  const pct = store.data?.sales_today?.evolution_pct
  if (pct === null || pct === undefined) return ''
  return `${pct >= 0 ? '+' : ''}${pct}% vs période préc.`
})

const kpiCards = computed(() => {
  const d = store.data
  return [
    {
      label: 'Terminaux en ligne', value: d?.terminals.online ?? '—',
      sub: `/ ${d?.terminals.total ?? '—'} total`,
      icon: IconWifi, iconBg: 'bg-emerald-100 text-emerald-600',
      badge: 'Actif', badgeClass: 'bg-emerald-100 text-emerald-700',
    },
    {
      label: 'Terminaux hors ligne', value: d?.terminals.offline ?? '—',
      sub: 'Vérifier la connexion',
      icon: IconWifiOff, iconBg: 'bg-rose-100 text-rose-600',
      badge: d?.terminals.offline > 0 ? 'Alerte' : null,
      badgeClass: 'bg-rose-100 text-rose-700',
    },
    {
      label: 'Ventes (période)', value: d?.sales_today.count ?? '—',
      sub: evolutionLabel.value || 'Période sélectionnée',
      icon: IconReceipt, iconBg: 'bg-indigo-100 text-indigo-600',
    },
    {
      label: 'CA (période)', value: fmt(d?.sales_today.revenue),
      sub: evolutionLabel.value || 'Chiffre d\'affaires net',
      icon: IconCurrency, iconBg: 'bg-violet-100 text-violet-600',
    },
  ]
})

const chartData = computed(() => {
  const days = store.data?.sales_7_days
  if (!days?.length) return null
  return {
    labels: days.map(d => d.date),
    datasets: [{
      label: 'CA (€)',
      data: days.map(d => d.revenue),
      borderColor: '#d81f33',
      backgroundColor: 'rgba(216,31,51,0.08)',
      fill: true,
      tension: 0.4,
      pointBackgroundColor: '#d81f33',
      pointRadius: 3,
    }],
  }
})

const chartOptions = {
  responsive: true,
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
    y: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
  },
}

import echo from '@/utils/echo'
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
