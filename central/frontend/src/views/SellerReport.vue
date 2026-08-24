<template>
  <div class="space-y-4 px-2 pt-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Performance</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Rapport vendeurs</p>
        <p class="text-sm text-slate-500">Qui vend quoi, combien, et où.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="filters.terminal_id" @change="load"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300">
          <option value="">Tous les terminaux</option>
          <option v-for="t in termStore.list" :key="t.terminal_id" :value="t.terminal_id">{{ t.terminal_id }}</option>
        </select>
        <input type="date" v-model="filters.date_from" @change="load"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
        <input type="date" v-model="filters.date_to" @change="load"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
        <button @click="resetFilters" class="text-xs text-slate-400 hover:text-slate-600 transition">Réinitialiser</button>
        <ExportButton
          endpoint="/export/sellers"
          :filename="`vendeurs_${filters.date_from}_${filters.date_to}`"
          :params="{ date_from: filters.date_from, date_to: filters.date_to, terminal_id: filters.terminal_id || undefined }"
        />
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-2xl bg-slate-100"></div>
    </div>

    <template v-else-if="data">

      <!-- KPI cards -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">CA Total</p>
          <p class="mt-1 text-2xl font-black text-indigo-600">{{ fmtShort(data.totals.total_ca) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Ventes</p>
          <p class="mt-1 text-2xl font-black text-slate-800">{{ data.totals.total_sales.toLocaleString('fr-FR') }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Vendeurs actifs</p>
          <p class="mt-1 text-2xl font-black text-slate-800">{{ data.totals.total_sellers }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Panier moyen</p>
          <p class="mt-1 text-2xl font-black text-slate-800">
            {{ data.totals.total_sales > 0 ? fmtShort(data.totals.total_ca / data.totals.total_sales) : '—' }}
          </p>
        </div>
      </div>

      <!-- Bar chart CA par vendeur + tableau -->
      <div class="grid gap-4 lg:grid-cols-2">

        <!-- Bar chart -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="mb-4 text-xs font-black uppercase tracking-wider text-slate-400">CA par vendeur (top 10)</p>
          <div class="space-y-2">
            <div
              v-for="(seller, i) in top10"
              :key="seller.seller_name"
              class="flex items-center gap-3 cursor-pointer group"
              @click="filterSeller = filterSeller === seller.seller_name ? null : seller.seller_name"
            >
              <!-- Rang + avatar -->
              <div class="flex w-5 items-center justify-center shrink-0">
                <span v-if="i < 3" class="text-sm">{{ ['🥇','🥈','🥉'][i] }}</span>
                <span v-else class="text-[10px] font-black text-slate-300">{{ i + 1 }}</span>
              </div>
              <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[8px] font-black text-indigo-700">
                {{ initials(seller.seller_name) }}
              </div>
              <!-- Barre -->
              <div class="flex-1">
                <div class="mb-0.5 flex items-center justify-between">
                  <span class="text-xs font-semibold text-slate-700 group-hover:text-indigo-600 transition"
                    :class="filterSeller === seller.seller_name ? 'text-indigo-600' : ''">
                    {{ seller.seller_name }}
                  </span>
                  <span class="text-[10px] font-bold text-slate-500">{{ fmtShort(seller.ca) }}</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                  <div class="h-full rounded-full transition-all duration-500"
                    :class="filterSeller === seller.seller_name ? 'bg-indigo-500' : 'bg-indigo-400'"
                    :style="{ width: barWidth(seller.ca) }">
                  </div>
                </div>
              </div>
              <!-- nb ventes -->
              <span class="w-12 text-right text-[10px] text-slate-400 shrink-0">{{ seller.sales_count }}v</span>
            </div>
          </div>
        </div>

        <!-- Répartition par point de vente -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="mb-4 text-xs font-black uppercase tracking-wider text-slate-400">Par point de vente</p>
          <div class="space-y-2">
            <div v-for="pos in data.by_pos" :key="pos.point_of_sale_name" class="flex items-center gap-3">
              <div class="flex-1">
                <div class="mb-0.5 flex items-center justify-between">
                  <span class="text-xs font-semibold text-slate-700">{{ pos.point_of_sale_name ?? 'Inconnu' }}</span>
                  <span class="text-[10px] font-bold text-slate-500">{{ fmtShort(pos.ca) }}</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                  <div class="h-full rounded-full bg-emerald-400 transition-all duration-500"
                    :style="{ width: posBarWidth(pos.ca) }">
                  </div>
                </div>
                <p class="mt-0.5 text-[9px] text-slate-400">{{ pos.sellers_count }} vendeur{{ pos.sellers_count > 1 ? 's' : '' }} · {{ pos.sales_count }} ventes</p>
              </div>
            </div>
            <p v-if="!data.by_pos.length" class="py-4 text-center text-sm text-slate-400">Aucune donnée.</p>
          </div>
        </div>
      </div>

      <!-- Tableau détaillé -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
          <p class="text-xs font-black uppercase tracking-wider text-slate-400">
            Détail vendeurs
            <span v-if="filterSeller" class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-indigo-600">
              {{ filterSeller }}
              <button @click="filterSeller = null" class="ml-1 text-indigo-400 hover:text-indigo-600">×</button>
            </span>
          </p>
          <p class="text-xs text-slate-400">{{ filteredSellers.length }} vendeur{{ filteredSellers.length > 1 ? 's' : '' }}</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
                <th class="px-5 py-3 text-left">Rang</th>
                <th class="px-5 py-3 text-left">Vendeur</th>
                <th class="px-5 py-3 text-left">Point de vente</th>
                <th class="px-5 py-3 text-right cursor-pointer hover:text-indigo-600 transition" @click="toggleSort('ca')">
                  CA {{ sortBy === 'ca' ? (sortDir === 'desc' ? '↓' : '↑') : '' }}
                </th>
                <th class="px-5 py-3 text-right cursor-pointer hover:text-indigo-600 transition" @click="toggleSort('sales_count')">
                  Ventes {{ sortBy === 'sales_count' ? (sortDir === 'desc' ? '↓' : '↑') : '' }}
                </th>
                <th class="px-5 py-3 text-right">Panier moyen</th>
                <th class="px-5 py-3 text-right">Meilleure vente</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(seller, i) in sortedSellers" :key="seller.seller_name + seller.point_of_sale_name"
                class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                <td class="px-5 py-3 text-xs text-slate-400 font-mono">
                  <span v-if="i < 3">{{ ['🥇','🥈','🥉'][i] }}</span>
                  <span v-else class="text-slate-300">{{ i + 1 }}</span>
                </td>
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-black text-indigo-700">
                      {{ initials(seller.seller_name) }}
                    </span>
                    <span class="text-sm font-semibold text-slate-800">{{ seller.seller_name }}</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-xs text-slate-500">{{ seller.point_of_sale_name ?? '—' }}</td>
                <td class="px-5 py-3 text-right font-mono font-bold text-indigo-600">{{ fmt(seller.ca) }}</td>
                <td class="px-5 py-3 text-right text-sm text-slate-700">{{ seller.sales_count }}</td>
                <td class="px-5 py-3 text-right text-xs text-slate-500">{{ fmt(seller.avg_basket) }}</td>
                <td class="px-5 py-3 text-right text-xs text-slate-500">{{ fmt(seller.max_sale) }}</td>
              </tr>
              <tr v-if="!filteredSellers.length">
                <td colspan="7" class="py-12 text-center text-sm text-slate-400">Aucun vendeur trouvé.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import { useTerminalsStore } from '@/stores/terminals'
import { fmt, fmtShort } from '@/utils/formatters'
import ExportButton from '@/components/ExportButton.vue'

const termStore = useTerminalsStore()

const today = new Date().toISOString().slice(0, 10)
const oneMonthAgo = new Date(Date.now() - 30 * 86400_000).toISOString().slice(0, 10)

const filters     = ref({ terminal_id: '', date_from: oneMonthAgo, date_to: today })
const data        = ref(null)
const loading     = ref(false)
const filterSeller = ref(null)
const sortBy      = ref('ca')
const sortDir     = ref('desc')

const initials = name => (name ?? '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()

const maxCa = computed(() => data.value?.sellers[0]?.ca ?? 1)
const maxPosCa = computed(() => data.value?.by_pos[0]?.ca ?? 1)
const barWidth = ca => `${Math.max(4, (ca / maxCa.value) * 100).toFixed(1)}%`
const posBarWidth = ca => `${Math.max(4, (ca / maxPosCa.value) * 100).toFixed(1)}%`

const top10 = computed(() => data.value?.sellers.slice(0, 10) ?? [])

const filteredSellers = computed(() => {
  if (!data.value) return []
  if (!filterSeller.value) return data.value.sellers
  return data.value.sellers.filter(s => s.seller_name === filterSeller.value)
})

const sortedSellers = computed(() => {
  return [...filteredSellers.value].sort((a, b) => {
    const dir = sortDir.value === 'desc' ? -1 : 1
    return dir * (Number(a[sortBy.value]) - Number(b[sortBy.value]))
  })
})

function toggleSort(field) {
  if (sortBy.value === field) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortBy.value = field
    sortDir.value = 'desc'
  }
}

async function load() {
  loading.value = true
  try {
    const res = await api.get('/sellers/report', {
      params: {
        date_from:   filters.value.date_from   || undefined,
        date_to:     filters.value.date_to     || undefined,
        terminal_id: filters.value.terminal_id || undefined,
      },
    })
    data.value = res.data
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { terminal_id: '', date_from: oneMonthAgo, date_to: today }
  load()
}

onMounted(() => {
  termStore.fetchAll()
  load()
})
</script>
