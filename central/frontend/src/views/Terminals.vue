<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Terminaux POS</h1>
      <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">
          {{ onlineCount }} en ligne · {{ offlineCount }} hors ligne
        </span>
        <button @click="store.fetchAll()" class="text-xs bg-gray-700 hover:bg-gray-600 px-3 py-1.5 rounded-lg transition-colors">
          Actualiser
        </button>
      </div>
    </div>

    <!-- Filtre restaurant -->
    <div class="mb-4">
      <input v-model="filterRestaurant" placeholder="Filtrer par restaurant..."
        class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 w-64 focus:outline-none focus:border-emerald-500" />
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="bg-gray-800 rounded-xl p-5 animate-pulse h-36"></div>
    </div>

    <!-- Liste terminaux -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <TerminalCard
        v-for="t in filteredTerminals"
        :key="t.terminal_id"
        :terminal="t"
        @click="$router.push({ name: 'terminal-detail', params: { id: t.terminal_id } })" />
      <div v-if="!filteredTerminals.length" class="col-span-3 text-center py-16 text-gray-600">
        Aucun terminal enregistré.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useTerminalsStore } from '@/stores/terminals'
import TerminalCard from '@/components/TerminalCard.vue'
import echo from '@/utils/echo'

const store = useTerminalsStore()
const filterRestaurant = ref('')

const filteredTerminals = computed(() =>
  store.list.filter(t =>
    !filterRestaurant.value ||
    t.restaurant_id.toLowerCase().includes(filterRestaurant.value.toLowerCase())
  )
)

const onlineCount  = computed(() => store.list.filter(t => t.status === 'online').length)
const offlineCount = computed(() => store.list.filter(t => t.status === 'offline').length)

let interval
onMounted(() => {
  store.fetchAll()
  interval = setInterval(() => store.fetchAll(), 30_000)
  echo.channel('central-dashboard').listen('.terminal.data', (e) => {
    if (e.resource === 'heartbeat') store.updateFromHeartbeat(e)
  })
})
onUnmounted(() => {
  clearInterval(interval)
  echo.leaveChannel('central-dashboard')
})
</script>
