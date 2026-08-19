<template>
  <div v-if="alerts.list.length" class="space-y-2">
    <div
      v-for="alert in alerts.list"
      :key="alert.id"
      :class="[
        'flex items-start gap-3 rounded-2xl border px-5 py-3',
        alert.level === 'critical'
          ? 'border-rose-200 bg-rose-50'
          : 'border-amber-200 bg-amber-50',
      ]"
    >
      <svg class="h-5 w-5 shrink-0 mt-0.5" :class="alert.level === 'critical' ? 'text-rose-500' : 'text-amber-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div class="flex-1">
        <p class="text-sm font-semibold" :class="alert.level === 'critical' ? 'text-rose-800' : 'text-amber-800'">
          {{ alert.message }}
        </p>
        <p v-if="alert.terminal_id" class="text-xs mt-0.5" :class="alert.level === 'critical' ? 'text-rose-500' : 'text-amber-500'">
          Terminal : {{ alert.terminal_id }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAlertsStore } from '@/stores/alerts'

const alerts = useAlertsStore()
onMounted(() => alerts.fetchActive())
</script>
