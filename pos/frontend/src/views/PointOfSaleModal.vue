<template>
  <div v-show="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
      <div class="border-b border-slate-100 px-5 py-4">
        <h3 class="text-lg font-semibold text-slate-800">{{ title }}</h3>
      </div>
      <div class="p-5">
        <label class="block text-sm font-medium text-slate-700">Nom du point de vente</label>
        <input
          v-model="localName"
          type="text"
          class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-300 focus:outline-none focus:ring focus:ring-indigo-200"
          placeholder="Ex: Restaurant Centre"
          @keyup.enter="submit"
        />
      </div>
      <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
        <button
          type="button"
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
          @click="close"
        >
          Annuler
        </button>
        <button
          type="button"
          class="rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
          @click="submit"
        >
          {{ submitLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: Boolean,
  title: String,
  submitLabel: String,
  initialName: String,
})

const emit = defineEmits(['submit', 'close'])

const localName = ref(props.initialName || '')

watch(() => props.initialName, (newVal) => {
  localName.value = newVal || ''
})

const submit = () => {
  if (localName.value.trim()) {
    emit('submit', localName.value.trim())
  }
}

const close = () => {
  emit('close')
}
</script>