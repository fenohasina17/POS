<template>
  <div class="relative" ref="menuRef">
    <button @click="open = !open" :disabled="loading"
      class="flex items-center gap-2 bg-gray-700 hover:bg-gray-600 disabled:opacity-50
             text-sm text-gray-200 px-3 py-1.5 rounded-lg transition-colors">
      <span>{{ loading ? 'Export...' : 'Exporter' }}</span>
      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div v-if="open"
      class="absolute right-0 mt-1 bg-gray-800 border border-gray-700 rounded-xl shadow-xl z-10 py-1 min-w-36">
      <button @click="doExport('csv')"
        class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 transition-colors">
        📄 Export CSV
      </button>
      <button @click="doExport('pdf')"
        class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 transition-colors">
        🖨️ Rapport HTML (imprimable)
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/utils/api'

const props = defineProps({
  dateFrom:     { type: String, default: '' },
  dateTo:       { type: String, default: '' },
  restaurantId: { type: String, default: '' },
})

const open    = ref(false)
const loading = ref(false)
const menuRef = ref(null)

async function doExport(format) {
  open.value    = false
  loading.value = true
  try {
    const params = {
      format,
      date_from:     props.dateFrom || undefined,
      date_to:       props.dateTo   || undefined,
      restaurant_id: props.restaurantId || undefined,
    }
    const res = await api.get('/export/sales', {
      params,
      responseType: 'blob',
    })

    const ext      = format === 'csv' ? 'csv' : 'html'
    const mime     = format === 'csv' ? 'text/csv' : 'text/html'
    const date     = props.dateFrom || new Date().toISOString().slice(0, 10)
    const filename = `ventes_${date}.${ext}`

    const url  = URL.createObjectURL(new Blob([res.data], { type: mime }))
    const link = document.createElement('a')
    link.href  = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  } finally {
    loading.value = false
  }
}

// Fermeture au clic extérieur
function handleClick(e) {
  if (menuRef.value && !menuRef.value.contains(e.target)) open.value = false
}
onMounted(() => document.addEventListener('mousedown', handleClick))
onUnmounted(() => document.removeEventListener('mousedown', handleClick))
</script>
