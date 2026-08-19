<template>
  <div class="space-y-4 px-2 pt-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Caisse</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Sessions de caisse</p>
        <p class="text-sm text-slate-500">{{ total.toLocaleString('fr-FR') }} session{{ total > 1 ? 's' : '' }} trouvée{{ total > 1 ? 's' : '' }}.</p>
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

    <!-- Cards grille -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm animate-pulse h-40"></div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <article
        v-for="s in sessions"
        :key="s.id"
        class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"
      >
        <!-- En-tête -->
        <div class="flex items-start justify-between mb-3">
          <div>
            <p class="text-xs font-bold text-slate-800">{{ s.terminal_id }}</p>
            <p class="text-[10px] text-slate-400">{{ s.restaurant_id }}</p>
          </div>
          <div class="flex gap-1">
            <span :class="s.is_closed ? 'bg-slate-100 text-slate-500' : 'bg-emerald-100 text-emerald-700'"
              class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase">
              {{ s.is_closed ? 'Clôturée' : 'En cours' }}
            </span>
            <span v-if="s.has_discrepancy" class="bg-rose-100 text-rose-600 rounded-full px-2 py-0.5 text-[9px] font-black uppercase">
              Écart
            </span>
          </div>
        </div>

        <!-- KPIs session -->
        <div class="grid grid-cols-2 gap-2 mb-3">
          <div class="rounded-xl bg-slate-50 p-2">
            <p class="text-[8px] font-black uppercase text-slate-400">Ventes totales</p>
            <p class="text-sm font-bold text-slate-900">{{ fmt(s.total_sales) }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-2">
            <p class="text-[8px] font-black uppercase text-slate-400">Fond de caisse</p>
            <p class="text-sm font-bold text-slate-900">{{ fmt(s.starting_amount) }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-2">
            <p class="text-[8px] font-black uppercase text-slate-400">Caisse attendue</p>
            <p class="text-sm font-bold text-slate-900">{{ fmt(s.expected_cash_amount) }}</p>
          </div>
          <div class="rounded-xl p-2" :class="s.has_discrepancy ? 'bg-rose-50' : 'bg-slate-50'">
            <p class="text-[8px] font-black uppercase" :class="s.has_discrepancy ? 'text-rose-400' : 'text-slate-400'">Caisse réelle</p>
            <p class="text-sm font-bold" :class="s.has_discrepancy ? 'text-rose-600' : 'text-slate-900'">{{ fmt(s.actual_cash_amount) }}</p>
          </div>
        </div>

        <!-- Dates -->
        <div class="text-[10px] text-slate-400 space-y-0.5">
          <p>Ouverture : <span class="font-semibold text-slate-600">{{ fmtDate(s.remote_opened_at) }}</span></p>
          <p v-if="s.is_closed">Clôture : <span class="font-semibold text-slate-600">{{ fmtDate(s.remote_closed_at) }}</span></p>
        </div>
      </article>

      <div v-if="!sessions.length" class="col-span-full rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
        <p class="text-sm text-slate-400">Aucune session trouvée.</p>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-5 py-3 shadow-sm">
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
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import { useTerminalsStore } from '@/stores/terminals'
import { fmt } from '@/utils/formatters'

const termStore = useTerminalsStore()
const terminals = computed(() => termStore.list)

const today    = new Date().toISOString().slice(0, 10)
const sessions = ref([])
const loading  = ref(false)
const page     = ref(1)
const lastPage = ref(1)
const total    = ref(0)
const filters  = ref({ terminal_id: '', date_from: today, date_to: today })

const fmtDate = v => v ? new Date(v).toLocaleString('fr-FR') : '—'

async function load(p = 1) {
  loading.value = true
  page.value = p
  try {
    const res = await api.get('/sessions', {
      params: {
        page: p,
        per_page: 30,
        terminal_id: filters.value.terminal_id || undefined,
        date_from:   filters.value.date_from   || undefined,
        date_to:     filters.value.date_to     || undefined,
      },
    })
    sessions.value = res.data.data
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

onMounted(() => {
  termStore.fetchAll()
  load(1)
})
</script>
