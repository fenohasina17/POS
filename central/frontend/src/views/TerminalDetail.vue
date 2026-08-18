<template>
  <div>
    <button @click="$router.back()" class="text-gray-500 hover:text-gray-300 text-sm mb-4 flex items-center gap-1">
      ← Retour
    </button>

    <div v-if="loading" class="animate-pulse space-y-4">
      <div class="h-8 bg-gray-800 rounded w-64"></div>
      <div class="h-32 bg-gray-800 rounded"></div>
    </div>

    <div v-else-if="terminal">
      <!-- En-tête -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold flex items-center gap-3">
            {{ terminal.terminal_id }}
            <span :class="terminal.status === 'online' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
              class="text-xs px-2 py-1 rounded-full font-normal">
              {{ terminal.status === 'online' ? '● En ligne' : '○ Hors ligne' }}
            </span>
          </h1>
          <p class="text-gray-500 mt-1">Restaurant : {{ terminal.restaurant_id }}</p>
        </div>
        <div class="text-right text-sm text-gray-500">
          <p>Version : <span class="text-gray-300">{{ terminal.app_version ?? '—' }}</span></p>
          <p>IP : <span class="text-gray-300">{{ terminal.ip_address ?? '—' }}</span></p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <StatCard label="Ventes aujourd'hui" :value="terminal.stats?.sales_today ?? 0" color="blue" />
        <StatCard label="CA aujourd'hui" :value="formatCurrency(terminal.stats?.revenue_today)" color="emerald" />
        <StatCard label="En attente sync" :value="terminal.pending_sync_count" color="yellow" />
        <StatCard label="Dernière sync" :value="formatRelative(terminal.last_sync_at)" color="gray" />
      </div>

      <!-- Infos techniques -->
      <div class="bg-gray-800 rounded-xl p-5 text-sm space-y-3">
        <h2 class="font-semibold text-gray-300 mb-3">Informations techniques</h2>
        <div class="grid grid-cols-2 gap-y-2 text-gray-400">
          <span>Dernier heartbeat</span>
          <span class="text-gray-200">{{ formatDate(terminal.last_heartbeat_at) }}</span>
          <span>Dernière synchronisation</span>
          <span class="text-gray-200">{{ formatDate(terminal.last_sync_at) }}</span>
          <span>Enregistrements en attente</span>
          <span :class="terminal.pending_sync_count > 0 ? 'text-yellow-400' : 'text-gray-200'">
            {{ terminal.pending_sync_count }}
          </span>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-16 text-gray-600">Terminal introuvable.</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/utils/api'
import StatCard from '@/components/StatCard.vue'

const route    = useRoute()
const terminal = ref(null)
const loading  = ref(true)

onMounted(async () => {
  try {
    const res  = await api.get(`/terminals/${route.params.id}`)
    terminal.value = res.data
  } finally {
    loading.value = false
  }
})

function formatCurrency(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val ?? 0)
}

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleString('fr-FR')
}

function formatRelative(val) {
  if (!val) return '—'
  const diff = Math.floor((Date.now() - new Date(val)) / 1000)
  if (diff < 60)   return `il y a ${diff}s`
  if (diff < 3600) return `il y a ${Math.floor(diff / 60)}min`
  return `il y a ${Math.floor(diff / 3600)}h`
}
</script>
