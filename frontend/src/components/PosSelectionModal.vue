<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur" v-if="isOpen">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-2xl">
      <h2 class="mb-6 text-xl font-semibold text-slate-900">Choisir un point de vente</h2>
      <div class="space-y-3">
        <button
          v-for="pos in pointsOfSale"
          :key="pos.id"
          @click="$emit('select', pos)"
          class="w-full rounded-xl border border-slate-200 px-4 py-3 text-left transition hover:bg-indigo-50 hover:border-indigo-200"
        >
          {{ pos.name }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  isOpen: { type: Boolean, required: true },
  pointsOfSale: { type: Array, required: true }
})
defineEmits(['select'])
</script>
