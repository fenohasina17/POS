<template>
  <div class="ui-card p-5">
    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ title }}</p>
    <p class="mt-2 text-2xl font-black text-slate-900">{{ value }}</p>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number]
})
</script>
