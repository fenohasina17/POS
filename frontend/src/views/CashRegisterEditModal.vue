<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="modal-background absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal"></div>
    <div class="modal-card relative z-10 w-full max-w-md overflow-hidden rounded-[2.5rem] border border-white bg-white p-8 shadow-2xl transition-all duration-300">
      <header class="mb-8 flex items-center justify-between">
        <div>
          <p class="text-[10px] font-black uppercase tracking-[0.3em] text-indigo-500">Caisse</p>
          <h3 class="text-2xl font-black text-slate-800">{{ title }}</h3>
        </div>
        <button @click="closeModal" class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 transition-all hover:bg-rose-50 hover:text-rose-500 active:scale-95">
          <FontAwesomeIcon icon="fa-solid fa-xmark" />
        </button>
      </header>

      <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="space-y-2">
          <label class="text-xs font-black uppercase tracking-widest text-slate-400">Nom de la caisse</label>
          <div class="relative">
            <input
              v-model="form.name"
              type="text"
              class="w-full rounded-2xl border-2 border-slate-50 bg-slate-50 px-5 py-4 text-sm font-bold text-slate-700 transition-all focus:border-indigo-500 focus:bg-white focus:outline-none"
              placeholder="Ex: Caisse Principale"
              required
            />
          </div>
        </div>

        <div v-if="isAdmin" class="space-y-2">
          <label class="text-xs font-black uppercase tracking-widest text-slate-400">Point de Vente</label>
          <select
            v-model="form.point_of_sale_id"
            class="w-full rounded-2xl border-2 border-slate-50 bg-slate-50 px-5 py-4 text-sm font-bold text-slate-700 transition-all focus:border-indigo-500 focus:bg-white focus:outline-none"
            required
          >
            <option :value="null">Choisir un point de vente...</option>
            <option v-for="pos in pointsOfSale" :key="pos.id" :value="pos.id">
              {{ pos.name }}
            </option>
          </select>
        </div>

        <div v-if="error" class="rounded-2xl bg-rose-50 p-4 text-xs font-bold text-rose-500">
          {{ error }}
        </div>

        <div class="flex gap-3 pt-4">
          <button
            type="button"
            @click="closeModal"
            class="flex-1 rounded-2xl border-2 border-slate-100 py-4 text-sm font-black text-slate-400 transition-all hover:bg-slate-50 active:scale-95"
          >
            ANNULER
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="flex-[2] rounded-2xl bg-slate-900 py-4 text-sm font-black text-white shadow-xl shadow-slate-200 transition-all hover:bg-indigo-600 disabled:opacity-50 active:scale-95"
          >
            <span v-if="!loading">{{ submitLabel }}</span>
            <span v-else class="flex items-center justify-center gap-2">
              <FontAwesomeIcon icon="fa-solid fa-spinner" class="animate-spin" />
              CHARGEMENT...
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faXmark, faSpinner } from '@fortawesome/free-solid-svg-icons'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
  isOpen: Boolean,
  register: Object,
  pointsOfSale: Array,
  title: { type: String, default: 'Modifier la Caisse' },
  submitLabel: { type: String, default: 'ENREGISTRER' }
})

const emit = defineEmits(['close', 'submit'])

const { isAdmin } = useAuth()
const loading = ref(false)
const error = ref(null)

const form = ref({
  id: null,
  name: '',
  point_of_sale_id: null
})

watch(() => props.register, (newVal) => {
  if (newVal) {
    form.value = {
      id: newVal.id,
      name: newVal.name,
      point_of_sale_id: newVal.point_of_sale_id || newVal.pointOfSaleId || (newVal.point_of_sale?.id)
    }
  } else {
    form.value = { id: null, name: '', point_of_sale_id: null }
  }
}, { immediate: true })

const closeModal = () => {
  error.value = null
  emit('close')
}

const handleSubmit = async () => {
  loading.value = true
  error.value = null
  try {
    emit('submit', { ...form.value })
  } catch (err) {
    error.value = err.message || 'Une erreur est survenue'
  } finally {
    loading.value = false
  }
}
</script>
