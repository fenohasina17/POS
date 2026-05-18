<template>
  <transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-slate-900/60" @click="close"></div>
      <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Ajouter une catégorie</h2>
            <p class="text-xs text-slate-400">Créez une nouvelle catégorie pour organiser vos produits.</p>
          </div>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:text-rose-500"
            @click="close"
          >
            ×
          </button>
        </header>

        <section class="mt-4 space-y-4">
          <div class="space-y-2">
            <label class="text-sm font-semibold text-slate-700">Nom</label>
            <input
              v-model.trim="category.name"
              type="text"
              placeholder="Nom de la catégorie"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              required
            />
          </div>

          <div class="space-y-2">
            <label class="text-sm font-semibold text-slate-700">Imprimante associée</label>
            <select
              v-model="category.printer"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            >
              <optgroup label="Imprimantes configurées">
                <option v-for="p in availablePrinters" :key="p.id" :value="p.name">
                  {{ p.name }}
                </option>
              </optgroup>
              <optgroup label="Rôles logiques">
                <option value="receipt">receipt (Caisse)</option>
                <option value="kitchen">kitchen (Cuisine)</option>
                <option value="cook">cook (Cuisson)</option>
                <option value="bar">bar (Bar)</option>
              </optgroup>
            </select>
            <p class="text-[10px] text-slate-400">Si l'imprimante n'est pas branchée, le ticket sera imprimé sur la caisse.</p>
          </div>
        </section>

        <footer class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
            @click="close"
          >
            Annuler
          </button>
          <button
            type="button"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
            @click="submit"
            :disabled="!category.name.trim() || isLoadingPrinters"
          >
            <span v-if="isLoadingPrinters" class="mr-2"><i class="fas fa-spinner fa-spin"></i></span>
            Ajouter
          </button>
        </footer>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, watch, defineEmits, defineProps, onMounted } from 'vue'
import printerService from '../services/printerService.js'

const props = defineProps({
  isOpen: Boolean,
})

const emit = defineEmits(['close', 'added'])

const category = ref({ name: '', description: '', printer: 'receipt' })
const availablePrinters = ref([])
const isLoadingPrinters = ref(false)

const fetchPrinters = async () => {
  try {
    isLoadingPrinters.value = true
    const response = await printerService.getAll()
    availablePrinters.value = response.data.data ? response.data.data : response.data
  } catch (error) {
    console.error('Erreur lors du chargement des imprimantes:', error)
  } finally {
    isLoadingPrinters.value = false
  }
}

onMounted(() => {
  fetchPrinters()
})

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    fetchPrinters()
    category.value = { name: '', description: '', printer: 'receipt' }
  }
})

const close = () => {
  emit('close')
}

const submit = () => {
  if (!category.value.name.trim()) return
  emit('added', { ...category.value })
  close()
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
