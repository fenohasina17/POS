<template>
  <transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-slate-900/60" @click="close"></div>
      <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Modifier la catégorie</h2>
            <p class="text-xs text-slate-400">Mettez à jour les informations de la catégorie sélectionnée.</p>
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

          <div v-if="printerTypes.length" class="space-y-2">
            <label class="text-sm font-semibold text-slate-700">Type d'imprimante</label>
            <select
              v-model="category.printer_type_id"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            >
              <option v-for="type in printerTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
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
            :disabled="!category.name.trim()"
          >
            Sauvegarder
          </button>
        </footer>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, watch, defineEmits, defineProps } from 'vue'
import { usePrinterTypes } from '../composables/usePrinterTypes.js'

const props = defineProps({
  isOpen: Boolean,
  categoryData: Object,
})

const emit = defineEmits(['close', 'updated'])

const { printerTypes, fetchPrinterTypes } = usePrinterTypes()

const category = ref({ id: null, name: '', printer_type_id: '', printer_type: '' })

watch(() => props.isOpen, (newVal) => {
  if (newVal && props.categoryData) {
    fetchPrinterTypes().then(() => {
      category.value = {
        id: props.categoryData.id ?? null,
        name: props.categoryData.name ?? '',
        printer_type_id:
          props.categoryData.printer_type_id ?? props.categoryData.printer_type?.id ?? props.categoryData.printer_type ?? '',
        printer_type:
          props.categoryData.printer_type_id ?? props.categoryData.printer_type?.id ?? props.categoryData.printer_type ?? '',
      }
      if (!category.value.printer_type_id && printerTypes.value.length) {
        category.value.printer_type_id = printerTypes.value[0].id
        category.value.printer_type = printerTypes.value[0].id
      }
    })
  }
})

watch(
  () => category.value.printer_type_id,
  (value) => {
    category.value.printer_type = value
  }
)

const close = () => {
  emit('close')
}

const submit = () => {
  if (!category.value.name.trim()) return
  const payload = {
    ...category.value,
    printer_type: category.value.printer_type_id || category.value.printer_type,
  }
  emit('updated', payload)
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
