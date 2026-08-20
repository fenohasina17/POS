<template>
  <div class="space-y-4 px-2 pt-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Supervision</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Alertes</p>
        <p class="text-sm text-slate-500">
          <span v-if="alerts.counts.critical > 0" class="text-red-600 font-semibold">{{ alerts.counts.critical }} critique{{ alerts.counts.critical > 1 ? 's' : '' }}</span>
          <span v-if="alerts.counts.critical > 0 && alerts.counts.warning > 0"> · </span>
          <span v-if="alerts.counts.warning > 0" class="text-amber-600 font-semibold">{{ alerts.counts.warning }} avertissement{{ alerts.counts.warning > 1 ? 's' : '' }}</span>
          <span v-if="!alerts.counts.total" class="text-emerald-600 font-semibold">Tout est normal ✓</span>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-xs text-slate-500 cursor-pointer">
          <input type="checkbox" v-model="showResolved" @change="load" class="rounded" />
          Voir résolues
        </label>
        <button @click="load" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
          Actualiser
        </button>
      </div>
    </div>

    <!-- Résumé sévérité -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="kpi in kpis" :key="kpi.label"
        class="rounded-2xl border p-4 shadow-sm cursor-pointer transition hover:shadow-md"
        :class="[kpi.bg, filterType === kpi.type ? 'ring-2 ring-indigo-400' : '']"
        @click="filterType = filterType === kpi.type ? null : kpi.type">
        <p class="text-[10px] font-black uppercase tracking-wider" :class="kpi.textMuted">{{ kpi.label }}</p>
        <p class="mt-1 text-2xl font-black" :class="kpi.text">{{ kpi.count }}</p>
        <p class="mt-0.5 text-[10px]" :class="kpi.textMuted">{{ kpi.hint }}</p>
      </div>
    </div>

    <!-- Liste alertes -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Chargement…</div>
      <template v-else>
        <div v-if="!filtered.length" class="py-12 text-center">
          <p class="text-3xl mb-2">✅</p>
          <p class="text-sm text-slate-400">Aucune alerte active.</p>
        </div>
        <ul v-else class="divide-y divide-slate-100">
          <li
            v-for="alert in filtered"
            :key="alert.id"
            class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50/50 transition-colors"
            :class="alert.resolved_at ? 'opacity-50' : ''"
          >
            <!-- Icône sévérité -->
            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
              :class="severityStyle(alert.severity).bg">
              <span class="text-base">{{ severityStyle(alert.severity).icon }}</span>
            </div>

            <!-- Contenu -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase"
                  :class="severityStyle(alert.severity).badge">
                  {{ alert.severity }}
                </span>
                <span class="rounded-full border px-2 py-0.5 text-[9px] font-semibold text-slate-500 border-slate-200">
                  {{ typeLabel(alert.type) }}
                </span>
                <span class="text-[10px] text-slate-400">{{ alert.terminal_id }}</span>
              </div>
              <p class="mt-1 text-sm font-medium text-slate-800">{{ alert.message }}</p>
              <div v-if="alert.context && Object.keys(alert.context).length" class="mt-1.5 flex flex-wrap gap-3">
                <span v-for="(val, key) in alert.context" :key="key"
                  class="text-[10px] text-slate-400">
                  <span class="font-semibold text-slate-500">{{ key }}</span>: {{ val }}
                </span>
              </div>
              <p class="mt-1 text-[10px] text-slate-400">
                {{ alert.resolved_at ? '✓ Résolue le ' + fmtDate(alert.resolved_at) : 'Depuis ' + fmtDate(alert.created_at) }}
              </p>
            </div>

            <!-- Action -->
            <button
              v-if="!alert.resolved_at"
              @click.stop="resolve(alert)"
              class="shrink-0 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:border-emerald-300 hover:text-emerald-600 transition"
            >
              Résoudre
            </button>
          </li>
        </ul>
      </template>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useAlertsStore } from '@/stores/alerts'
import api from '@/utils/api'

const alerts      = useAlertsStore()
const list        = ref([])
const loading     = ref(false)
const showResolved = ref(false)
const filterType  = ref(null)

const TYPE_LABELS = {
  terminal_offline:      'Terminal hors ligne',
  sync_backlog:          'Backlog sync',
  cash_discrepancy:      'Écart de caisse',
  cancelled_sales_spike: 'Pic d\'annulations',
}

const typeLabel = type => TYPE_LABELS[type] ?? type

const fmtDate = v => v ? new Date(v).toLocaleString('fr-FR') : '—'

function severityStyle(severity) {
  if (severity === 'critical') return {
    bg: 'bg-red-100', icon: '🔴',
    badge: 'bg-red-100 text-red-700',
  }
  return {
    bg: 'bg-amber-100', icon: '🟡',
    badge: 'bg-amber-100 text-amber-700',
  }
}

const kpis = computed(() => {
  const active = list.value.filter(a => !a.resolved_at)
  return [
    {
      label: 'Critiques',      type: 'critical',
      count: active.filter(a => a.severity === 'critical').length,
      bg: 'bg-red-50 border-red-100', text: 'text-red-600', textMuted: 'text-red-400',
      hint: 'Intervention requise',
    },
    {
      label: 'Avertissements', type: 'warning',
      count: active.filter(a => a.severity === 'warning').length,
      bg: 'bg-amber-50 border-amber-100', text: 'text-amber-600', textMuted: 'text-amber-400',
      hint: 'À surveiller',
    },
    {
      label: 'Terminaux offline', type: 'terminal_offline',
      count: active.filter(a => a.type === 'terminal_offline').length,
      bg: 'bg-slate-50 border-slate-200', text: 'text-slate-700', textMuted: 'text-slate-400',
      hint: 'Sans heartbeat',
    },
    {
      label: 'Écarts caisse',  type: 'cash_discrepancy',
      count: active.filter(a => a.type === 'cash_discrepancy').length,
      bg: 'bg-slate-50 border-slate-200', text: 'text-slate-700', textMuted: 'text-slate-400',
      hint: 'Lors de clôtures',
    },
  ]
})

const filtered = computed(() => {
  return list.value.filter(a => {
    if (!showResolved.value && a.resolved_at) return false
    if (filterType.value === 'critical')   return a.severity === 'critical' && !a.resolved_at
    if (filterType.value === 'warning')    return a.severity === 'warning'  && !a.resolved_at
    if (filterType.value)                  return a.type === filterType.value && !a.resolved_at
    return true
  })
})

async function load() {
  loading.value = true
  try {
    const res = await api.get('/alerts', {
      params: showResolved.value ? {} : { active: 1 },
    })
    list.value = res.data
    alerts.fetchCounts()
  } finally {
    loading.value = false
  }
}

async function resolve(alert) {
  await alerts.resolve(alert.id)
  alert.resolved_at = new Date().toISOString()
  alerts.fetchCounts()
}

// Refresh automatique toutes les 30s
let timer
onMounted(() => {
  load()
  timer = setInterval(load, 30_000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>
