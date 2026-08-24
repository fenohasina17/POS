<template>
  <div class="relative" ref="menuRef">
    <button @click="doExport" :disabled="loading"
      class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:border-indigo-200 hover:text-indigo-600 disabled:opacity-50 transition">
      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
      </svg>
      {{ loading ? 'Export…' : 'Exporter CSV' }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/utils/api'

const props = defineProps({
  endpoint:   { type: String, required: true },
  filename:   { type: String, default: 'export' },
  params:     { type: Object, default: () => ({}) },
})

const loading = ref(false)

async function doExport() {
  loading.value = true
  try {
    const res = await api.get(props.endpoint, {
      params: props.params,
      responseType: 'blob',
    })

    const url  = URL.createObjectURL(new Blob([res.data], { type: 'text/csv' }))
    const link = document.createElement('a')
    link.href     = url
    link.download = `${props.filename}.csv`
    link.click()
    URL.revokeObjectURL(url)
  } finally {
    loading.value = false
  }
}
</script>
