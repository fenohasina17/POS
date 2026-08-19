<template>
  <div class="space-y-6 px-2 pt-4">

    <!-- ── Header + filtres ─────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Vue d'ensemble</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Supervision centrale</p>
        <p class="text-sm text-slate-500">
          {{ (store.data?.sales_today?.count ?? 0).toLocaleString('fr-FR') }} vente{{ store.data?.sales_today?.count > 1 ? 's' : '' }} analysée{{ store.data?.sales_today?.count > 1 ? 's' : '' }}.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <select
          v-model="filters.terminal_id"
          @change="applyFilters"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
        >
          <option value="">Tous les terminaux</option>
          <option v-for="t in terminals" :key="t.terminal_id" :value="t.terminal_id">{{ t.terminal_id }}</option>
        </select>
        <select
          v-model="filters.restaurant_id"
          @change="applyFilters"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
        >
          <option value="">Tous les restaurants</option>
          <option v-for="r in restaurants" :key="r" :value="r">{{ r }}</option>
        </select>
        <input type="date" v-model="filters.date_from" @change="applyFilters"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
        <input type="date" v-model="filters.date_to" @change="applyFilters"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
        <button @click="resetFilters" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">Réinitialiser</button>
        <ExportButton :date-from="filters.date_from" :date-to="filters.date_to" :restaurant-id="filters.restaurant_id" />
      </div>
    </div>

    <!-- Alertes -->
    <AlertBanner />

    <!-- Pending sync -->
    <div v-if="store.data?.terminals?.pending_sync > 0"
      class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3">
      <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="text-sm text-amber-700"><strong>{{ store.data.terminals.pending_sync }}</strong> enregistrement(s) en attente de synchronisation.</span>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────────────────────── -->
    <div v-if="store.loading" class="grid grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 3" :key="i" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm animate-pulse h-28"></div>
    </div>

    <section v-else-if="store.data" class="grid grid-cols-2 lg:grid-cols-3 gap-4">
      <article v-for="card in kpiCards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <span :class="['flex h-12 w-12 items-center justify-center rounded-xl', card.iconBg]">
              <component :is="card.icon" class="h-5 w-5" />
            </span>
            <div>
              <p class="text-sm font-medium text-slate-500">{{ card.label }}</p>
              <p class="text-3xl font-semibold text-slate-900">{{ card.value }}</p>
            </div>
          </div>
          <span v-if="card.change !== null"
            :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold', card.changePositive ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600']">
            {{ card.changePositive ? '↑' : '↓' }} {{ card.change }}
          </span>
        </div>
        <p class="mt-3 text-sm text-slate-500">{{ card.description }}</p>
      </article>
    </section>

    <!-- ── Tendance quotidienne + Heatmap ───────────────────────────────── -->
    <section v-if="store.data" class="flex flex-col gap-6 lg:flex-row">

      <!-- Tendance 7 jours -->
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm flex-1">
        <div class="flex items-start justify-between mb-1">
          <div>
            <p class="text-base font-semibold text-slate-900">Tendance quotidienne</p>
            <p class="text-sm text-slate-500">CA des 7 derniers jours</p>
          </div>
          <span v-if="trendChange !== null"
            :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold', trendChange >= 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600']">
            {{ trendChange >= 0 ? '↑' : '↓' }} {{ Math.abs(trendChange) }}%
          </span>
        </div>

        <div class="mt-4 h-36">
          <svg v-if="trendChart.line" viewBox="0 0 100 48" class="h-full w-full">
            <defs>
              <linearGradient id="trendGrad" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#d81f33" stop-opacity="0.2"/>
                <stop offset="100%" stop-color="#d81f33" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path :d="trendChart.area" fill="url(#trendGrad)" />
            <path :d="trendChart.line" fill="none" stroke="#d81f33" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <circle v-for="(p, i) in trendChart.points" :key="i" :cx="p.x" :cy="p.y" r="1.8" fill="#d81f33" />
          </svg>
          <div v-else class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400">
            Aucune donnée pour les 7 derniers jours.
          </div>
        </div>

        <!-- Grille jours -->
        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3">
          <div v-for="d in trendDays" :key="d.date" class="rounded-xl bg-slate-50 border border-slate-100 px-3 py-2">
            <p class="text-[10px] font-black uppercase text-slate-400">{{ d.label }}</p>
            <p class="text-sm font-bold text-slate-900">{{ fmt(d.revenue) }}</p>
            <p class="text-[10px] text-slate-400">{{ d.sales_count }} vente{{ d.sales_count > 1 ? 's' : '' }}</p>
          </div>
        </div>
      </article>

      <!-- Heatmap horaire -->
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm flex-1 min-w-0">
        <p class="text-base font-semibold text-slate-900 mb-1">Heatmap horaire</p>
        <p class="text-sm text-slate-500 mb-4">Intensité des ventes par créneau — 7 derniers jours</p>

        <div v-if="heatmapHasData" class="overflow-x-auto">
          <div class="min-w-[520px]">
            <!-- Entêtes créneaux -->
            <div class="grid grid-cols-7 gap-1 mb-1" style="grid-template-columns: 60px repeat(6, 1fr)">
              <div></div>
              <div v-for="slot in store.data.heatmap_slots" :key="slot.key"
                class="text-center text-[8px] font-black uppercase text-slate-400">{{ slot.label }}</div>
            </div>
            <!-- Lignes jours -->
            <div v-for="dayRow in store.data.heatmap" :key="dayRow.day"
              class="grid grid-cols-7 gap-1 mb-1" style="grid-template-columns: 60px repeat(6, 1fr)">
              <div class="flex items-center text-[10px] font-semibold text-slate-600">{{ fmtDay(dayRow.day) }}</div>
              <div v-for="cell in dayRow.slots" :key="cell.key"
                class="flex flex-col items-center justify-center rounded-lg px-1 py-1.5 text-[9px] font-semibold transition"
                :style="heatmapStyle(cell.revenue)">
                <span>{{ fmtShort(cell.revenue) }}</span>
                <span class="opacity-70">{{ cell.count }}v</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="flex h-32 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400">
          Pas encore de ventes pour calculer le heatmap.
        </div>
      </article>
    </section>

    <!-- ── Ventes mensuelles + Top produits ─────────────────────────────── -->
    <section v-if="store.data" class="grid gap-6 xl:grid-cols-3">

      <!-- Barres mensuelles -->
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
        <p class="text-base font-semibold text-slate-900 mb-1">Ventes mensuelles</p>
        <p class="text-sm text-slate-500 mb-5">Chiffre d'affaires mois par mois — 12 derniers mois</p>
        <div class="h-48" v-if="monthlySalesData.length">
          <div class="flex h-full items-end gap-2">
            <div v-for="m in monthlySalesData" :key="m.month" class="flex flex-1 flex-col items-center gap-2">
              <div class="flex h-full w-full items-end justify-center rounded-t-full bg-indigo-100">
                <div class="relative w-8 rounded-t-full bg-indigo-500 shadow-inner"
                  :style="{ height: getBarHeight(m.revenue) }">
                  <span class="absolute -top-6 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-white px-1.5 py-0.5 text-[9px] font-bold text-slate-600 shadow">
                    {{ fmtShort(m.revenue) }}
                  </span>
                </div>
              </div>
              <span class="text-[9px] font-medium text-slate-400">{{ m.label }}</span>
            </div>
          </div>
        </div>
        <div v-else class="flex h-48 flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 text-center text-sm text-slate-400">
          Aucune vente enregistrée sur les 12 derniers mois.
        </div>
      </article>

      <!-- Top produits -->
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
          <div>
            <p class="text-base font-semibold text-slate-900">Top produits</p>
            <p class="text-sm text-slate-500">Sur la période</p>
          </div>
          <div class="flex gap-1 rounded-xl bg-slate-100 p-1 text-[10px] font-black uppercase">
            <button @click="topMode = 'revenue'" :class="topMode === 'revenue' ? 'bg-white shadow-sm text-slate-800 rounded-lg' : 'text-slate-400'" class="px-2 py-1 transition">CA</button>
            <button @click="topMode = 'qty'"     :class="topMode === 'qty'     ? 'bg-white shadow-sm text-slate-800 rounded-lg' : 'text-slate-400'" class="px-2 py-1 transition">Qté</button>
          </div>
        </div>
        <ul v-if="store.data.top_products?.length" class="space-y-3">
          <li v-for="(p, i) in store.data.top_products" :key="p.product_name" class="flex items-center gap-3">
            <span :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-black', badgeColors[i % badgeColors.length]]">
              {{ p.product_name?.slice(0, 2).toUpperCase() }}
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-800 truncate">{{ p.product_name }}</p>
              <p class="text-[10px] text-slate-400">{{ p.total_qty }} unité{{ p.total_qty > 1 ? 's' : '' }}</p>
            </div>
            <p class="text-sm font-bold text-slate-900 shrink-0">
              {{ topMode === 'revenue' ? fmt(p.total_revenue) : p.total_qty }}
            </p>
          </li>
        </ul>
        <div v-else class="flex h-32 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400">
          Aucun produit sur la période.
        </div>
      </article>
    </section>

    <!-- ── CA par restaurant ─────────────────────────────────────────────── -->
    <article v-if="store.data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <p class="mb-1 text-xs font-semibold uppercase tracking-[0.15em] text-indigo-500">Répartition</p>
      <p class="mb-4 text-base font-semibold text-slate-900">CA par restaurant</p>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
              <th class="pb-2 text-left">Restaurant</th>
              <th class="pb-2 text-right">Ventes</th>
              <th class="pb-2 text-right">CA</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in store.data.by_restaurant" :key="r.restaurant_id" class="border-b border-slate-50">
              <td class="py-2.5 font-medium text-slate-800">{{ r.restaurant_id }}</td>
              <td class="py-2.5 text-right text-slate-500">{{ r.sales_count }}</td>
              <td class="py-2.5 text-right font-mono font-semibold text-indigo-600">{{ fmt(r.revenue) }}</td>
            </tr>
            <tr v-if="!store.data.by_restaurant?.length">
              <td colspan="3" class="py-8 text-center text-sm text-slate-400">Aucune vente sur la période.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { useTerminalsStore } from '@/stores/terminals'
import AlertBanner from '@/components/AlertBanner.vue'
import ExportButton from '@/components/ExportButton.vue'
import echo from '@/utils/echo'

const store     = useDashboardStore()
const termStore = useTerminalsStore()

const today   = new Date().toISOString().slice(0, 10)
const topMode = ref('revenue')
const filters = ref({ terminal_id: '', restaurant_id: '', date_from: today, date_to: today })

const terminals   = computed(() => termStore.list)
const restaurants = computed(() => [...new Set((store.data?.by_restaurant ?? []).map(r => r.restaurant_id))])

function applyFilters() {
  store.fetch({
    terminal_id:   filters.value.terminal_id   || undefined,
    restaurant_id: filters.value.restaurant_id || undefined,
    date_from:     filters.value.date_from      || undefined,
    date_to:       filters.value.date_to        || undefined,
  })
}
function resetFilters() {
  filters.value = { terminal_id: '', restaurant_id: '', date_from: today, date_to: today }
  store.fetch()
}

// ── Formatage ───────────────────────────────────────────────────────────────
const fmt      = v => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v ?? 0)
const fmtShort = v => {
  if (!v) return '0€'
  if (v >= 1000) return `${Math.round(v / 1000)}k€`
  return `${Math.round(v)}€`
}
const fmtDay   = d => {
  const days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam']
  const date = new Date(d + 'T12:00:00')
  return `${days[date.getDay()]} ${date.getDate()}`
}

// ── KPI Cards ───────────────────────────────────────────────────────────────
const IconWifi     = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>` }
const IconReceipt  = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>` }
const IconCurrency = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>` }

const kpiCards = computed(() => {
  const d = store.data
  if (!d) return []
  const evoPct = d.sales_today.evolution_pct
  return [
    {
      label: 'Chiffre d\'affaires', value: fmt(d.sales_today.revenue),
      icon: IconCurrency, iconBg: 'bg-indigo-100 text-indigo-600',
      description: evoPct !== null ? `${evoPct >= 0 ? '+' : ''}${evoPct}% vs période préc.` : 'Période sélectionnée',
      change: evoPct !== null ? `${Math.abs(evoPct)}%` : null,
      changePositive: evoPct >= 0,
    },
    {
      label: 'Nombre de ventes', value: d.sales_today.count,
      icon: IconReceipt, iconBg: 'bg-violet-100 text-violet-600',
      description: `Ticket moyen : ${fmt(d.sales_today.avg_ticket)}`,
      change: null,
    },
    {
      label: 'Terminaux actifs', value: `${d.terminals.online} / ${d.terminals.total}`,
      icon: IconWifi, iconBg: 'bg-emerald-100 text-emerald-600',
      description: `${d.terminals.offline} hors ligne · ${d.terminals.pending_sync} en attente sync`,
      change: null,
    },
  ]
})

// ── Tendance 7 jours ────────────────────────────────────────────────────────
const trendDays = computed(() => {
  const days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam']
  return (store.data?.sales_7_days ?? []).map(d => ({
    ...d,
    label: (() => { const dt = new Date(d.date + 'T12:00:00'); return `${days[dt.getDay()]} ${dt.getDate()}` })(),
  }))
})

const trendChange = computed(() => {
  const arr = store.data?.sales_7_days ?? []
  if (arr.length < 2) return null
  const last = arr[arr.length - 1]?.revenue ?? 0
  const prev = arr[arr.length - 2]?.revenue ?? 0
  if (!prev) return null
  return Math.round(((last - prev) / prev) * 100)
})

const trendChart = computed(() => {
  const arr = store.data?.sales_7_days ?? []
  if (arr.length < 2) return { line: null, area: null, points: [] }
  const values = arr.map(d => d.revenue)
  const max = Math.max(...values) || 1
  const pts = values.map((v, i) => ({
    x: (i / (values.length - 1)) * 96 + 2,
    y: 44 - (v / max) * 40,
  }))
  const line = pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ')
  const area = `${line} L${pts[pts.length - 1].x},48 L${pts[0].x},48 Z`
  return { line, area, points: pts }
})

// ── Heatmap ─────────────────────────────────────────────────────────────────
const heatmapHasData = computed(() =>
  (store.data?.heatmap ?? []).some(row => row.slots.some(s => s.revenue > 0))
)
const heatmapMax = computed(() => {
  let max = 0
  for (const row of store.data?.heatmap ?? []) {
    for (const s of row.slots) if (s.revenue > max) max = s.revenue
  }
  return max || 1
})
function heatmapStyle(revenue) {
  const intensity = revenue / heatmapMax.value
  const alpha = Math.pow(intensity, 0.5)
  return {
    backgroundColor: `rgba(216,31,51,${alpha * 0.85})`,
    color: intensity > 0.45 ? '#fff' : '#64748b',
  }
}

// ── Ventes mensuelles ────────────────────────────────────────────────────────
const monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']
const monthlySalesData = computed(() =>
  (store.data?.monthly_sales ?? []).map(m => ({
    ...m,
    label: monthNames[parseInt(m.month.split('-')[1]) - 1],
  }))
)
const monthlySalesMax = computed(() => Math.max(...monthlySalesData.value.map(m => m.revenue), 1))
const getBarHeight = v => `${Math.max(4, (v / monthlySalesMax.value) * 100)}%`

// ── Top produits ─────────────────────────────────────────────────────────────
const badgeColors = ['bg-indigo-100 text-indigo-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-violet-100 text-violet-700']

// ── Lifecycle ────────────────────────────────────────────────────────────────
let interval
onMounted(() => {
  store.fetch()
  termStore.fetchAll()
  interval = setInterval(() => store.fetch(), 30_000)
  echo.channel('central-dashboard').listen('.terminal.data', () => applyFilters())
})
onUnmounted(() => {
  clearInterval(interval)
  echo.leaveChannel('central-dashboard')
})
</script>
