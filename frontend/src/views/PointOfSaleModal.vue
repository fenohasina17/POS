<template>
  <transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-slate-900/60" @click="$emit('close')"></div>
      <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
            <p class="text-xs text-slate-400">Définissez le nom affiché pour ce point de vente.</p>
          </div>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:text-rose-500"
            @click="$emit('close')"
          >
            ×
          </button>
        </header>

        <form class="mt-4 space-y-4" @submit.prevent="onSubmit">
          <div class="space-y-2">
            <label class="text-sm font-semibold text-slate-700" for="pos-name">Nom du point de vente</label>
            <input
              id="pos-name"
              v-model.trim="localName"
              type="text"
              placeholder="Ex : Comptoir principal"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              required
            />
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button
              type="button"
              class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
              @click="$emit('close')"
            >
              Annuler
            </button>
            <button
              type="submit"
              class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
              :disabled="!localName"
            >
              {{ submitLabel }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, ref, watch, defineProps, defineEmits } from 'vue'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  title: { type: String, default: 'Point de vente' },
  submitLabel: { type: String, default: 'Valider' },
  initialName: { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit'])

const localName = ref('')

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      localName.value = props.initialName || ''
    }
  },
  { immediate: true }
)

const onSubmit = () => {
  if (!localName.value.trim()) return
  emit('submit', localName.value.trim())
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
