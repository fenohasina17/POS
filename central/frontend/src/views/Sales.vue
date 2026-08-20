<template>
  <div class="space-y-4 px-2 pt-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Transactions</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Liste des ventes</p>
        <p class="text-sm text-slate-500">{{ total.toLocaleString('fr-FR') }} vente{{ total > 1 ? 's' : '' }} trouvée{{ total > 1 ? 's' : '' }}.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="filters.terminal_id" @change="load(1)"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300">
          <option value="">Tous les terminaux</option>
          <option v-for="t in terminals" :key="t.terminal_id" :value="t.terminal_id">{{ t.terminal_id }}</option>
        </select>
        <input type="date" v-model="filters.date_from" @change="load(1)"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
        <input type="date" v-model="filters.date_to" @change="load(1)"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
        <button @click="resetFilters" class="text-xs text-slate-400 hover:text-slate-600 transition">Réinitialiser</button>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
              <th class="px-5 py-3 text-left">N° Ticket</th>
              <th class="px-5 py-3 text-left">Terminal</th>
              <th class="px-5 py-3 text-left">Point de vente</th>
              <th class="px-5 py-3 text-left">Vendeur</th>
              <th class="px-5 py-3 text-left">Statut</th>
              <th class="px-5 py-3 text-right">Montant</th>
              <th class="px-5 py-3 text-right">Date</th>
              <th class="px-5 py-3 text-right"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="py-12 text-center text-sm text-slate-400">Chargement…</td>
            </tr>
            <template v-else>
              <tr
                v-for="s in sales"
                :key="s.id"
                class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors cursor-pointer"
                @click="openDetail(s)"
              >
                <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ s.ticket_number ?? s.sale_number ?? `#${s.remote_id}` }}</td>
                <td class="px-5 py-3 text-slate-500 text-xs">{{ s.terminal_id }}</td>
                <td class="px-5 py-3 text-slate-600 text-xs font-medium">{{ s.point_of_sale_name ?? s.restaurant_id }}</td>
                <td class="px-5 py-3 text-xs">
                  <span v-if="s.seller_name" class="inline-flex items-center gap-1">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[8px] font-black text-indigo-700">
                      {{ s.seller_name.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase() }}
                    </span>
                    <span class="text-slate-600">{{ s.seller_name }}</span>
                  </span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-5 py-3">
                  <span :class="s.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                    class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">{{ s.status }}</span>
                </td>
                <td class="px-5 py-3 text-right font-mono font-semibold text-indigo-600">{{ fmt(s.final_amount) }}</td>
                <td class="px-5 py-3 text-right text-xs text-slate-400">{{ fmtDate(s.remote_created_at) }}</td>
                <td class="px-5 py-3 text-right">
                  <svg class="h-4 w-4 text-slate-300 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </td>
              </tr>
              <tr v-if="!sales.length">
                <td colspan="9" class="py-12 text-center text-sm text-slate-400">Aucune vente trouvée.</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-3">
        <p class="text-xs text-slate-400">Page {{ page }} / {{ lastPage }}</p>
        <div class="flex gap-2">
          <button @click="load(page - 1)" :disabled="page === 1"
            class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition">
            ← Préc.
          </button>
          <button @click="load(page + 1)" :disabled="page === lastPage"
            class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition">
            Suiv. →
          </button>
        </div>
      </div>
    </div>

    <!-- Modal détail vente -->
    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" @click.self="selected = null">
      <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-indigo-500">Vente</p>
            <p class="text-lg font-bold text-slate-900">{{ selected.ticket_number ?? selected.sale_number ?? `#${selected.remote_id}` }}</p>
          </div>
          <button @click="selected = null" class="text-slate-400 hover:text-slate-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Infos -->
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div v-for="info in saleInfos" :key="info.label" class="rounded-xl bg-slate-50 p-3">
            <p class="text-[9px] font-black uppercase text-slate-400">{{ info.label }}</p>
            <p class="mt-0.5 text-sm font-semibold" :class="info.class ?? 'text-slate-800'">{{ info.value }}</p>
          </div>
        </div>

        <!-- Lignes -->
        <p class="mb-2 text-xs font-black uppercase text-slate-400">Articles</p>
        <div v-if="linesLoading" class="py-4 text-center text-sm text-slate-400">Chargement…</div>
        <ul v-else class="space-y-2">
          <li v-for="line in lines" :key="line.id" class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-2 text-sm">
            <div>
              <p class="font-semibold text-slate-800">{{ line.product_name }}</p>
              <p class="text-xs text-slate-400">{{ line.quantity }} × {{ fmt(line.unit_price) }}</p>
            </div>
            <p class="font-mono font-bold text-slate-900">{{ fmt(line.total) }}</p>
          </li>
          <li v-if="!lines.length" class="py-4 text-center text-sm text-slate-400">Aucun article.</li>
        </ul>

        <div class="mt-4 flex justify-between border-t border-slate-100 pt-3">
          <p class="text-sm font-semibold text-slate-600">Total</p>
          <p class="text-lg font-bold text-indigo-600">{{ fmt(selected.final_amount) }}</p>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import { useTerminalsStore } from '@/stores/terminals'
import { fmt } from '@/utils/formatters'

const termStore = useTerminalsStore()
const terminals = computed(() => termStore.list)

const today   = new Date().toISOString().slice(0, 10)
const sales   = ref([])
const loading = ref(false)
const page    = ref(1)
const lastPage = ref(1)
const total   = ref(0)
const filters = ref({ terminal_id: '', date_from: today, date_to: today })

const selected     = ref(null)
const lines        = ref([])
const linesLoading = ref(false)

const fmtDate = v => v ? new Date(v).toLocaleString('fr-FR') : '—'

const saleInfos = computed(() => selected.value ? [
  { label: 'Terminal',        value: selected.value.terminal_id },
  { label: 'Point de vente', value: selected.value.point_of_sale_name ?? selected.value.restaurant_id },
  { label: 'Vendeur',        value: selected.value.seller_name ?? '—' },
  { label: 'Statut',         value: selected.value.status },
  { label: 'Montant',     value: fmt(selected.value.final_amount), class: 'text-indigo-600' },
  { label: 'Reçu',        value: fmt(selected.value.amount_received) },
  { label: 'Rendu',       value: fmt(selected.value.change_amount) },
  { label: 'Date',        value: fmtDate(selected.value.remote_created_at) },
  { label: 'Clôturée le', value: fmtDate(selected.value.remote_completed_at) },
] : [])

async function load(p = 1) {
  loading.value = true
  page.value = p
  try {
    const res = await api.get('/sales', {
      params: {
        page: p,
        per_page: 50,
        terminal_id:   filters.value.terminal_id   || undefined,
        date_from:     filters.value.date_from      || undefined,
        date_to:       filters.value.date_to        || undefined,
      },
    })
    sales.value    = res.data.data
    lastPage.value = res.data.last_page
    total.value    = res.data.total
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { terminal_id: '', date_from: today, date_to: today }
  load(1)
}

async function openDetail(sale) {
  selected.value     = sale
  lines.value        = []
  linesLoading.value = true
  try {
    const res = await api.get(`/sales/${sale.remote_id}/lines`, {
      params: { terminal_id: sale.terminal_id },
    })
    lines.value = res.data
  } finally {
    linesLoading.value = false
  }
}

onMounted(() => {
  termStore.fetchAll()
  load(1)
})
</script>
