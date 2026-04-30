<template>
  <transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal"></div>

      <div
        class="relative z-10 w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-2xl"
      >
        <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Ajouter un produit</h2>
            <p class="text-sm text-slate-500">
              Complétez les informations ci-dessous pour créer un nouveau produit.
            </p>
          </div>
          <button
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:text-rose-500"
            aria-label="Fermer"
            @click="closeModal"
          >
            &times;
          </button>
        </header>

        <section class="px-6 py-6">
          <form id="add-product-form" @submit.prevent="addProduct" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-600">Nom du produit</label>
                <input
                  v-model="localProduct.name"
                  type="text"
                  maxlength="255"
                  required
                  placeholder="Nom du produit"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                />
              </div>
              <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-600">Référence (max 4 caractères)</label>
                <input
                  v-model="localProduct.ref"
                  type="text"
                  maxlength="4"
                  required
                  placeholder="Ex: B001"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                />
                <p class="text-xs text-slate-400">{{ localProduct.ref.length }}/4 caractères</p>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-600">Prix</label>
                <input
                  v-model.number="localProduct.price"
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  placeholder="0.00"
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                />
              </div>
              <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-600">Catégorie</label>
                <select
                  v-model="localProduct.category_id"
                  required
                  class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
                >
                  <option :value="null" disabled>Choisir une catégorie</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div>
                <p class="text-sm font-semibold text-slate-700">Produit actif</p>
                <p class="text-xs text-slate-500">Affichez le produit sur vos points de vente</p>
              </div>
              <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" class="peer sr-only" v-model="localProduct.status" />
                <span
                  class="peer h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-indigo-500 after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"
                ></span>
              </label>
            </div>

            <div class="space-y-3">
              <label class="text-sm font-semibold text-slate-600">Image</label>
              <div
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center"
              >
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="onImageChange"
                />
                <img
                  :src="imageUrl"
                  alt="Aperçu du produit"
                  class="h-20 w-20 rounded-full object-cover shadow-inner ring-2 ring-white"
                />
                <div class="space-y-1 text-xs text-slate-500">
                  <p>Formats acceptés : JPG, PNG (max 2 Mo)</p>
                  <button
                    type="button"
                    class="font-semibold text-indigo-600 transition hover:text-indigo-700"
                    @click="triggerFileDialog"
                  >
                    Choisir une image
                  </button>
                </div>
                <p v-if="imageError" class="text-xs font-medium text-rose-500">{{ imageError }}</p>
              </div>
            </div>

            <p v-if="saveError" class="text-sm font-semibold text-rose-500">{{ saveError }}</p>
          </form>
        </section>

        <footer class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-end">
          <button
            type="button"
            class="w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 sm:w-auto"
            @click="closeModal"
            :disabled="isSaving"
          >
            Annuler
          </button>
          <button
            type="submit"
            form="add-product-form"
            class="w-full rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-400 sm:w-auto"
            :disabled="isSaving"
          >
            <span v-if="isSaving" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
            Ajouter le produit
          </button>
        </footer>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { API_BASE_URL, API_URL } from '@/utils/api'

const props = defineProps({
  isOpen: Boolean
})
const emits = defineEmits(['close', 'added'])

const localProduct = reactive({
  name: '',
  ref: '',
  price: 0,
  status: false,
  category_id: null,
  image: null,
  imagePreview: ''
})

const categories = ref([])
const imageError = ref('')
const fileInput = ref(null)
const isSaving = ref(false)
const saveError = ref('')

// Récupération des catégories avec gestion de la réponse
const fetchCategories = async () => {
  try {
    const token = localStorage.getItem('token')
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    const pointOfSaleId = user?.point_of_sale_id

    if (!pointOfSaleId) {
      console.warn('Point de vente non trouvé pour l’utilisateur')
      categories.value = []
      return
    }

    const response = await axios.get(`${API_BASE_URL}/categories`, {
      params: { point_of_sale_id: pointOfSaleId },
      headers: { Authorization: `Bearer ${token}` }
    })

    // Extraction flexible du tableau de catégories
    let data = response.data
    if (data?.data && Array.isArray(data.data)) data = data.data
    if (data?.categories && Array.isArray(data.categories)) data = data.categories
    if (!Array.isArray(data)) {
      console.warn('Format de réponse inattendu:', data)
      categories.value = []
      return
    }

    categories.value = data
    console.log(`${categories.value.length} catégories chargées`)
  } catch (error) {
    console.error('Erreur chargement catégories:', error.response?.data || error.message)
    categories.value = []
  }
}

// Chargement au montage
onMounted(() => {
  fetchCategories()
})

// Recharger les catégories à chaque ouverture du modal (utile si elles ont changé)
watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    fetchCategories()
    resetForm()
  }
})

const resetForm = () => {
  localProduct.name = ''
  localProduct.ref = ''
  localProduct.price = 0
  localProduct.status = true
  localProduct.category_id = null
  localProduct.image = null
  localProduct.imagePreview = ''
  imageError.value = ''
  saveError.value = ''
}

const imageUrl = computed(() => {
  return localProduct.imagePreview || `${API_URL}/storage/products/default-product-image.jpg`
})

const onImageChange = (event) => {
  const file = event.target.files[0]
  if (!file) return
  event.target.value = ''
  if (!file.type.startsWith('image/')) {
    imageError.value = 'Type de fichier invalide'
    return
  }
  if (file.size > 2048 * 1024) {
    imageError.value = 'Fichier trop volumineux (max 2MB)'
    return
  }
  imageError.value = ''
  localProduct.image = file
  localProduct.imagePreview = URL.createObjectURL(file)
}

const triggerFileDialog = () => {
  fileInput.value?.click()
}

const convertFileToBase64 = (file) => {
  return new Promise((resolve) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result.replace(/^data:image\/[a-z]+;base64,/, ''))
    reader.readAsDataURL(file)
  })
}

const addProduct = async () => {
  if (!localProduct.name || localProduct.price === null || localProduct.category_id === null) {
    saveError.value = 'Veuillez remplir tous les champs obligatoires.'
    return
  }
  saveError.value = ''
  isSaving.value = true
  try {
    const token = localStorage.getItem('token')
    const payload = {
      name: localProduct.name,
      ref: localProduct.ref,
      price: localProduct.price,
      status: localProduct.status,
      category_id: localProduct.category_id,
      image: localProduct.image ? await convertFileToBase64(localProduct.image) : null
    }
    const response = await axios.post(`${API_BASE_URL}/products`, payload, {
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' }
    })
    const updatedProductData = {
      ...response.data.product,
      price: localProduct.price
    }
    emits('added', updatedProductData)
    emits('close')
  } catch (error) {
    console.error('Erreur:', error.response?.data || error)
    saveError.value = error.response?.data?.message || 'Erreur lors de l\'ajout du produit'
  } finally {
    isSaving.value = false
  }
}

const closeModal = () => emits('close')
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: scale(0.98);
}
</style>