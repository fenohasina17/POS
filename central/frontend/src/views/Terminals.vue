<template>
  <div class="space-y-4 px-2 pt-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Supervision</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Terminaux POS</p>
        <p class="text-sm text-slate-500">
          <span class="font-semibold text-emerald-600">{{ onlineCount }} en ligne</span>
          &nbsp;·&nbsp;
          <span :class="offlineCount > 0 ? 'font-semibold text-rose-600' : 'text-slate-400'">{{ offlineCount }} hors ligne</span>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <input
          v-model="filterText"
          placeholder="Filtrer par restaurant ou ID…"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 w-56"
        />
        <button
          @click="store.fetchAll()"
          class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Actualiser
        </button>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="i in 8" :key="i" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm animate-pulse h-36"></div>
    </div>

    <!-- Cards grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="t in filteredTerminals"
        :key="t.terminal_id"
        class="cursor-pointer rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md"
        @click="$router.push({ name: 'terminal-detail', params: { id: t.terminal_id } })"
      >
        <!-- En-tête card -->
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <span
              class="flex h-8 w-8 items-center justify-center rounded-lg text-[10px] font-black"
              :class="t.status === 'online' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
            >
              {{ t.terminal_id.slice(-3).toUpperCase() }}
            </span>
            <div>
              <p class="text-xs font-bold text-slate-800">{{ t.terminal_id }}</p>
              <p class="text-[10px] text-slate-400">{{ t.restaurant_id }}</p>
            </div>
          </div>
          <span
            :class="t.status === 'online'
              ? 'bg-emerald-100 text-emerald-700'
              : 'bg-rose-100 text-rose-600'"
            class="flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-black uppercase"
          >
            <span class="h-1.5 w-1.5 rounded-full" :class="t.status === 'online' ? 'bg-emerald-500 animate-pulse' : 'bg-rose-400'"></span>
            {{ t.status === 'online' ? 'En ligne' : 'Hors ligne' }}
          </span>
        </div>

        <!-- Mini KPIs -->
        <div class="grid grid-cols-2 gap-2">
          <div class="rounded-xl bg-slate-50 p-2">
            <p class="text-[8px] font-black uppercase text-slate-400">Pending sync</p>
            <p class="mt-0.5 text-sm font-bold" :class="t.pending_sync_count > 0 ? 'text-amber-500' : 'text-slate-700'">
              {{ t.pending_sync_count ?? 0 }}
            </p>
          </div>
          <div class="rounded-xl bg-slate-50 p-2">
            <p class="text-[8px] font-black uppercase text-slate-400">Heartbeat</p>
            <p class="mt-0.5 text-sm font-bold text-slate-700">{{ relativeTime(t.last_heartbeat_at) }}</p>
          </div>
        </div>

        <!-- Flèche -->
        <div class="mt-3 flex justify-end">
          <svg class="h-4 w-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </div>

      <div v-if="!filteredTerminals.length" class="col-span-full rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
        <p class="text-sm text-slate-400">Aucun terminal trouvé.</p>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useTerminalsStore } from '@/stores/terminals'
import echo from '@/utils/echo'

const store      = useTerminalsStore()
const filterText = ref('')

const filteredTerminals = computed(() =>
  store.list.filter(t =>
    !filterText.value ||
    t.terminal_id.toLowerCase().includes(filterText.value.toLowerCase()) ||
    t.restaurant_id.toLowerCase().includes(filterText.value.toLowerCase())
  )
)
const onlineCount  = computed(() => store.list.filter(t => t.status === 'online').length)
const offlineCount = computed(() => store.list.filter(t => t.status === 'offline').length)

function relativeTime(val) {
  if (!val) return '—'
  const diff = Math.floor((Date.now() - new Date(val)) / 1000)
  if (diff < 60)   return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff / 60)}min`
  return `${Math.floor(diff / 3600)}h`
}

onMounted(() => {
  store.fetchAll()
  echo.channel('central-dashboard').listen('.heartbeat', p => store.updateFromHeartbeat(p))
})
onUnmounted(() => {
  echo.leaveChannel('central-dashboard')
})
</script>
