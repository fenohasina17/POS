<template>
  <div :class="['bg-gray-800 rounded-xl p-5', borderClass]">
    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">{{ label }}</p>
    <p :class="['text-2xl font-bold font-mono', valueClass]">{{ value }}</p>
    <p v-if="sub" class="text-xs text-gray-500 mt-1">{{ sub }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: String,
  value: [String, Number],
  sub:   { type: String, default: '' },
  color: { type: String, default: 'gray' },
})

const colorMap = {
  emerald: { border: 'border-l-4 border-emerald-500', value: 'text-emerald-400' },
  blue:    { border: 'border-l-4 border-blue-500',    value: 'text-blue-400'    },
  red:     { border: 'border-l-4 border-red-500',     value: 'text-red-400'     },
  yellow:  { border: 'border-l-4 border-yellow-500',  value: 'text-yellow-400'  },
  violet:  { border: 'border-l-4 border-violet-500',  value: 'text-violet-400'  },
  gray:    { border: 'border-l-4 border-gray-600',    value: 'text-gray-200'    },
}

const borderClass = computed(() => colorMap[props.color]?.border ?? colorMap.gray.border)
const valueClass  = computed(() => colorMap[props.color]?.value  ?? colorMap.gray.value)
</script>
