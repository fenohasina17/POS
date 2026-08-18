<template>
  <div v-if="store.list.length" class="space-y-2 mb-6">
    <div v-for="alert in store.list" :key="alert.id"
      :class="alert.severity === 'critical'
        ? 'bg-red-900/30 border-red-700 text-red-300'
        : 'bg-yellow-900/30 border-yellow-700 text-yellow-300'"
      class="border rounded-xl px-4 py-3 flex items-center justify-between text-sm">
      <div class="flex items-center gap-3">
        <span>{{ alert.severity === 'critical' ? '🔴' : '🟡' }}</span>
        <span>{{ alert.message }}</span>
        <span class="text-xs opacity-60">{{ formatRelative(alert.created_at) }}</span>
      </div>
      <button @click="store.resolve(alert.id)"
        class="text-xs opacity-60 hover:opacity-100 transition-opacity ml-4 shrink-0">
        Résoudre ✕
      </button>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAlertsStore } from '@/stores/alerts'

const store = useAlertsStore()
onMounted(() => store.fetchActive())

function formatRelative(val) {
  if (!val) return ''
  const diff = Math.floor((Date.now() - new Date(val)) / 1000)
  if (diff < 60)   return `il y a ${diff}s`
  if (diff < 3600) return `il y a ${Math.floor(diff / 60)}min`
  return `il y a ${Math.floor(diff / 3600)}h`
}
</script>
