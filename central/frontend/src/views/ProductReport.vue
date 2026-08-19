<template>
  <div class="space-y-6">

    <!-- En-tête + filtres -->
    <div class="flex flex-wrap items-end gap-3">
      <div>
        <h1 class="text-xl font-black text-slate-900">Reporting produits</h1>
        <p class="text-xs text-slate-400">Ventes par produit et catégorie — données reçues depuis les terminaux</p>
      </div>
      <div class="ml-auto flex flex-wrap items-end gap-2">
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Du</label>
          <input v-model="filters.date_from" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Au</label>
          <input v-model="filters.date_to" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none" />
        </div>
        <select v-model="filters.region" @change="filters.terminal_id = ''" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none">
          <option value="">Toutes les régions</option>
          <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
        </select>
        <select v-model="filters.terminal_id" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none">
          <option value="">Tous les terminaux</option>
          <option v-for="t in filteredTerminals" :key="t.terminal_id" :value="t.terminal_id">{{ t.name || t.terminal_id }}</option>
        </select>
        <button @click="load" :disabled="loading" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 transition">
          {{ loading ? 'Chargement…' : 'Actualiser' }}
        </button>
      </div>
    </div>

    <!-- KPI globaux -->
    <div v-if="data" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">CA total</p>
        <p class="mt-1 text-2xl font-black text-slate-900">{{ fmtShort(data.totals?.total_revenue) }}</p>
        <p class="text-xs text-slate-400">{{ fmt(data.totals?.total_revenue) }}</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Quantité vendue</p>
        <p class="mt-1 text-2xl font-black text-slate-900">{{ Number(data.totals?.total_qty ?? 0).toLocaleString('fr-FR') }}</p>
        <p class="text-xs text-slate-400">articles</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Produits distincts</p>
        <p class="mt-1 text-2xl font-black text-slate-900">{{ data.totals?.product_count ?? 0 }}</p>
        <p class="text-xs text-slate-400">{{ data.period?.from }} → {{ data.period?.to }}</p>
      </div>
    </div>

    <div v-if="data" class="grid grid-cols-1 gap-6 lg:grid-cols-2">

      <!-- CA par catégorie -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-black text-slate-900">CA par catégorie</h2>
        </div>
        <div class="divide-y divide-slate-50">
          <div v-for="cat in data.by_category" :key="cat.category_name"
               class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-slate-50 transition"
               @click="filterByCategory(cat.category_name)">
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-semibold text-slate-800 truncate">{{ cat.category_name }}</span>
                <span class="text-sm font-bold text-indigo-600 ml-2 shrink-0">{{ fmt(cat.total_revenue) }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full bg-indigo-500 transition-all"
                     :style="{ width: categoryPct(cat.total_revenue) + '%' }"></div>
              </div>
              <p class="mt-1 text-[10px] text-slate-400">{{ Number(cat.total_qty).toLocaleString('fr-FR') }} articles · {{ cat.product_count }} produits</p>
            </div>
          </div>
          <div v-if="!data.by_category?.length" class="px-5 py-8 text-center text-sm text-slate-400">
            Aucune donnée — les noms de produits seront disponibles après la prochaine synchronisation
          </div>
        </div>
      </div>

      <!-- Évolution journalière -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-black text-slate-900">Évolution journalière</h2>
        </div>
        <div class="px-5 py-4">
          <div v-if="data.daily_trend?.length" class="flex items-end gap-1 h-32">
            <div v-for="day in data.daily_trend" :key="day.day"
                 class="flex-1 flex flex-col items-center gap-1 group">
              <div class="relative flex-1 w-full flex items-end">
                <div class="w-full rounded-t bg-indigo-500 group-hover:bg-indigo-600 transition-all"
                     :style="{ height: trendPct(day.total_revenue) + '%', minHeight: '2px' }"
                     :title="`${day.day} — ${fmt(day.total_revenue)}`"></div>
              </div>
              <span class="text-[8px] text-slate-400 rotate-45 origin-left whitespace-nowrap">{{ fmtDay(day.day) }}</span>
            </div>
          </div>
          <div v-else class="py-8 text-center text-sm text-slate-400">Pas de données</div>
        </div>
      </div>
    </div>

    <!-- Top produits -->
    <div v-if="data" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <h2 class="text-sm font-black text-slate-900">Top produits</h2>
        <div class="flex gap-1 rounded-lg border border-slate-200 p-0.5">
          <button @click="sortBy = 'revenue'" :class="sortBy === 'revenue' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700'" class="rounded px-3 py-1 text-xs font-semibold transition">CA</button>
          <button @click="sortBy = 'qty'"     :class="sortBy === 'qty'     ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-700'" class="rounded px-3 py-1 text-xs font-semibold transition">Quantité</button>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-left">
              <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">#</th>
              <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Produit</th>
              <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Catégorie</th>
              <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-wide text-slate-400">Qté</th>
              <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-wide text-slate-400">CA</th>
              <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-wide text-slate-400">Prix moy.</th>
              <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-wide text-slate-400">% CA</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="(p, i) in sortedProducts" :key="p.product_name" class="hover:bg-slate-50 transition">
              <td class="px-5 py-3 text-slate-400 font-mono text-xs">{{ i + 1 }}</td>
              <td class="px-5 py-3 font-semibold text-slate-900">{{ p.product_name }}</td>
              <td class="px-5 py-3">
                <span class="inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600">
                  {{ p.category_name || '—' }}
                </span>
              </td>
              <td class="px-5 py-3 text-right font-mono text-slate-700">{{ Number(p.total_qty).toLocaleString('fr-FR') }}</td>
              <td class="px-5 py-3 text-right font-mono font-semibold text-indigo-600">{{ fmt(p.total_revenue) }}</td>
              <td class="px-5 py-3 text-right font-mono text-slate-500 text-xs">{{ fmt(p.avg_price) }}</td>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <div class="h-1.5 w-16 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-indigo-400" :style="{ width: productPct(p.total_revenue) + '%' }"></div>
                  </div>
                  <span class="text-xs font-semibold text-slate-500 w-8 text-right">{{ productPct(p.total_revenue) }}%</span>
                </div>
              </td>
            </tr>
            <tr v-if="!sortedProducts.length">
              <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-400">
                Aucun produit — lancez une synchronisation depuis le POS pour remplir les données
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- État vide initial -->
    <div v-if="!data && !loading" class="rounded-2xl border border-slate-200 bg-white py-16 text-center">
      <p class="text-slate-400">Cliquez sur Actualiser pour charger le reporting produits</p>
    </div>
    <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white py-16 text-center">
      <p class="text-slate-400 animate-pulse">Chargement…</p>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import { useTerminalsStore } from '@/stores/terminals'
import { fmt, fmtShort } from '@/utils/formatters'

const terminalsStore = useTerminalsStore()

const loading = ref(false)
const data    = ref(null)
const sortBy  = ref('revenue')
const selectedCategory = ref(null)

const today = new Date().toISOString().slice(0, 10)
const thirtyDaysAgo = new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10)

const filters = ref({
  date_from:   thirtyDaysAgo,
  date_to:     today,
  region:      '',
  terminal_id: '',
})

const regions = computed(() => {
  const r = new Set(terminalsStore.terminals.map(t => t.region).filter(Boolean))
  return [...r].sort()
})

const filteredTerminals = computed(() => {
  if (!filters.value.region) return terminalsStore.terminals
  return terminalsStore.terminals.filter(t => t.region === filters.value.region)
})

const totalRevenue = computed(() => data.value?.totals?.total_revenue ?? 0)

const categoryPct = (rev) => {
  const max = Math.max(...(data.value?.by_category?.map(c => c.total_revenue) ?? [1]))
  return max ? Math.round((rev / max) * 100) : 0
}

const trendMax = computed(() => Math.max(...(data.value?.daily_trend?.map(d => d.total_revenue) ?? [1])))
const trendPct = (rev) => trendMax.value ? Math.round((rev / trendMax.value) * 100) : 2

const productPct = (rev) => totalRevenue.value ? Math.round((rev / totalRevenue.value) * 100) : 0

const sortedProducts = computed(() => {
  if (!data.value?.top_products) return []
  const list = selectedCategory.value
    ? data.value.top_products.filter(p => p.category_name === selectedCategory.value)
    : data.value.top_products
  return [...list].sort((a, b) =>
    sortBy.value === 'revenue'
      ? b.total_revenue - a.total_revenue
      : b.total_qty - a.total_qty
  )
})

function filterByCategory(name) {
  selectedCategory.value = selectedCategory.value === name ? null : name
}

const fmtDay = (d) => {
  const date = new Date(d + 'T12:00:00')
  return `${date.getDate()}/${date.getMonth() + 1}`
}

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(
      Object.entries(filters.value).filter(([, v]) => v !== '')
    )
    const res = await api.get('/products/report', { params })
    data.value = res.data
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await terminalsStore.fetch()
  load()
})
</script>
