<template>
  <div class="space-y-6 px-2 pt-4">

    <!-- Retour -->
    <button @click="$router.back()" class="flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-indigo-600 transition-colors">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Retour aux terminaux
    </button>

    <!-- Skeleton -->
    <div v-if="loading" class="space-y-4">
      <div class="h-24 rounded-2xl bg-white border border-slate-200 animate-pulse"></div>
      <div class="h-32 rounded-2xl bg-white border border-slate-200 animate-pulse"></div>
    </div>

    <template v-else-if="terminal">

      <!-- En-tête -->
      <div class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm">
        <div class="flex items-center gap-4">
          <span
            class="flex h-14 w-14 items-center justify-center rounded-2xl text-base font-black"
            :class="terminal.status === 'online' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
          >
            {{ terminal.terminal_id.slice(-3).toUpperCase() }}
          </span>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl font-bold text-slate-900">{{ terminal.terminal_id }}</h1>
              <span
                :class="terminal.status === 'online' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'"
                class="flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-black uppercase"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="terminal.status === 'online' ? 'bg-emerald-500 animate-pulse' : 'bg-rose-400'"></span>
                {{ terminal.status === 'online' ? 'En ligne' : 'Hors ligne' }}
              </span>
            </div>
            <p class="text-sm text-slate-500 mt-0.5">Restaurant : <span class="font-semibold text-slate-700">{{ terminal.restaurant_id }}</span></p>
          </div>
        </div>
        <div class="text-right text-sm text-slate-500 space-y-0.5">
          <p>Version : <span class="font-semibold text-slate-700">{{ terminal.app_version ?? '—' }}</span></p>
          <p>IP : <span class="font-mono text-slate-700">{{ terminal.ip_address ?? '—' }}</span></p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <article v-for="card in statsCards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <span :class="['mb-3 flex h-10 w-10 items-center justify-center rounded-xl', card.iconBg]">
            <component :is="card.icon" class="h-4 w-4" />
          </span>
          <p class="text-xl font-bold text-slate-900">{{ card.value }}</p>
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mt-0.5">{{ card.label }}</p>
        </article>
      </div>

      <!-- Infos techniques -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="mb-4 text-xs font-semibold uppercase tracking-[0.15em] text-indigo-500">Informations techniques</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 text-sm">
          <div v-for="info in techInfos" :key="info.label" class="flex flex-col gap-0.5">
            <span class="text-[10px] font-black uppercase tracking-wide text-slate-400">{{ info.label }}</span>
            <span :class="['font-semibold', info.class ?? 'text-slate-800']">{{ info.value }}</span>
          </div>
        </div>
      </div>

    </template>

    <div v-else class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
      <p class="text-sm text-slate-400">Terminal introuvable.</p>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/utils/api'

const route    = useRoute()
const terminal = ref(null)
const loading  = ref(true)

// Icônes inline
const IconReceipt  = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>` }
const IconCurrency = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>` }
const IconSync     = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>` }
const IconClock    = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>` }

const fmt = val => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val ?? 0)
const fmtDate = val => val ? new Date(val).toLocaleString('fr-FR') : '—'

const statsCards = computed(() => [
  { label: 'Ventes aujourd\'hui', value: terminal.value?.stats?.sales_today ?? 0,        icon: IconReceipt,  iconBg: 'bg-indigo-100 text-indigo-600' },
  { label: 'CA aujourd\'hui',     value: fmt(terminal.value?.stats?.revenue_today),       icon: IconCurrency, iconBg: 'bg-emerald-100 text-emerald-600' },
  { label: 'En attente sync',     value: terminal.value?.pending_sync_count ?? 0,         icon: IconSync,     iconBg: terminal.value?.pending_sync_count > 0 ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-500' },
  { label: 'Dernière sync',       value: relativeTime(terminal.value?.last_sync_at),      icon: IconClock,    iconBg: 'bg-violet-100 text-violet-600' },
])

const techInfos = computed(() => [
  { label: 'Dernier heartbeat',        value: fmtDate(terminal.value?.last_heartbeat_at) },
  { label: 'Dernière synchronisation', value: fmtDate(terminal.value?.last_sync_at) },
  { label: 'Enregistrements pending',  value: terminal.value?.pending_sync_count ?? 0,
    class: terminal.value?.pending_sync_count > 0 ? 'text-amber-600' : 'text-slate-800' },
  { label: 'Version application',      value: terminal.value?.app_version ?? '—' },
  { label: 'Adresse IP',               value: terminal.value?.ip_address ?? '—' },
  { label: 'Restaurant',               value: terminal.value?.restaurant_id ?? '—' },
])

function relativeTime(val) {
  if (!val) return '—'
  const diff = Math.floor((Date.now() - new Date(val)) / 1000)
  if (diff < 60)   return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff / 60)}min`
  return `${Math.floor(diff / 3600)}h`
}

onMounted(async () => {
  try {
    const res = await api.get(`/terminals/${route.params.id}`)
    terminal.value = res.data
  } finally {
    loading.value = false
  }
})
</script>
