<template>
  <div class="bg-gray-800 rounded-xl p-5 cursor-pointer hover:bg-gray-750 hover:ring-1 hover:ring-gray-600 transition-all">
    <!-- En-tête -->
    <div class="flex items-center justify-between mb-3">
      <span class="font-semibold text-gray-200 text-sm">{{ terminal.terminal_id }}</span>
      <span :class="terminal.status === 'online'
          ? 'bg-emerald-500/20 text-emerald-400'
          : 'bg-red-500/20 text-red-400'"
        class="text-xs px-2 py-0.5 rounded-full">
        {{ terminal.status === 'online' ? '● En ligne' : '○ Hors ligne' }}
      </span>
    </div>

    <!-- Infos -->
    <p class="text-xs text-gray-500 mb-3">{{ terminal.restaurant_id }}</p>

    <div class="grid grid-cols-2 gap-2 text-xs">
      <div class="bg-gray-900/50 rounded-lg p-2">
        <p class="text-gray-500">Pending sync</p>
        <p :class="terminal.pending_sync_count > 0 ? 'text-yellow-400' : 'text-gray-300'" class="font-mono font-semibold">
          {{ terminal.pending_sync_count ?? 0 }}
        </p>
      </div>
      <div class="bg-gray-900/50 rounded-lg p-2">
        <p class="text-gray-500">Dernier heartbeat</p>
        <p class="text-gray-300 font-mono">{{ formatRelative(terminal.last_heartbeat_at) }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  terminal: { type: Object, required: true },
})

function formatRelative(val) {
  if (!val) return '—'
  const diff = Math.floor((Date.now() - new Date(val)) / 1000)
  if (diff < 60)   return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff / 60)}min`
  return `${Math.floor(diff / 3600)}h`
}
</script>
