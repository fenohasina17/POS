<template>
  <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="w-full max-w-6xl rounded-3xl border border-slate-200 bg-white p-6 md:p-8 shadow-2xl flex flex-col max-h-[90vh]">
      <div class="mb-6 flex-shrink-0">
        <h2 class="text-xl md:text-2xl font-bold text-slate-900">Sélectionnez un point de vente</h2>
        <p class="text-sm md:text-base text-slate-500">Veuillez choisir le point de vente pour votre session de travail.</p>
      </div>
      
      <div class="overflow-y-auto flex-grow pr-2">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
          <button
            v-for="pos in pointsOfSale"
            :key="pos.id"
            @click="$emit('select', pos)"
            class="group flex flex-col items-center justify-center p-4 rounded-xl border border-slate-200 bg-slate-50 transition-all hover:bg-indigo-600 hover:border-indigo-600 hover:shadow-lg text-center"
          >
            <div class="mb-2 p-2 rounded-full bg-white group-hover:bg-indigo-500/20 text-indigo-600 group-hover:text-white transition-colors">
              <i class="fas fa-store text-lg"></i>
            </div>
            <span class="text-xs font-semibold text-slate-700 group-hover:text-white transition-colors truncate w-full px-1">
              {{ pos.name }}
            </span>
          </button>
        </div>
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
