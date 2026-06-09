<template>

  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4 sm:px-6">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="closeModal"></div>
    <div class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white/95 shadow-2xl">
      <header class="flex items-center justify-between border-b border-slate-200 bg-gradient-to-r from-indigo-600 via-indigo-500 to-indigo-600 px-6 py-5 text-white">
        <div>
          <p class="text-xs uppercase tracking-[0.2em] text-white/80">Produits</p>
          <h2 class="text-2xl font-semibold leading-snug">Éditer le produit</h2>
        </div>
        <button
          class="flex size-10 items-center justify-center rounded-full bg-white/10 text-lg leading-none text-white transition hover:bg-white/20"
          aria-label="Fermer"
          @click="closeModal"
        >
          &times;
        </button>
      </header>

      <section class="flex-1 space-y-6 overflow-y-auto bg-slate-50/90 px-6 py-6">
        <!-- Nom du produit -->
        <div>
          <label class="mb-2 block text-sm font-semibold text-slate-700">Nom du produit</label>
          <input
            class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            type="text"
            v-model="localProduct.name"
            maxlength="255"
            required
            placeholder="Nom du produit"
          />
        </div>
        <!-- Ref du produit -->
        <div>
          <label class="mb-2 block text-sm font-semibold text-slate-700">Référence du produit</label>
          <input
            class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            type="text"
            v-model="localProduct.ref"
            maxlength="255"
            required
            placeholder="Référence du produit"
          />
        </div>

        <!-- Prix -->
        <div>
          <label class="mb-2 block text-sm font-semibold text-slate-700">Prix</label>
          <input
            class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            type="number"
            v-model.number="localProduct.price"
            min="0"
            step="0.01"
            required
            placeholder="Prix"
          />
        </div>

        <!-- Statut actif -->
        <div>
          <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700">
            <input type="checkbox" v-model="localProduct.status" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-2 focus:ring-indigo-200" />
            Actif
          </label>
        </div>

        <!-- Catégorie -->
        <div>
          <label class="mb-2 block text-sm font-semibold text-slate-700">Catégorie</label>
          <select
            v-model="localProduct.category_id"
            class="w-full rounded-2xl border border-slate-200 bg-white/90 px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
          >
            <option :value="null" disabled>Choisir une catégorie</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">
              {{ category.name }}
            </option>
          </select>
        </div>

        <!-- Image -->
        <div>
          <label class="mb-2 block text-sm font-semibold text-slate-700">Image</label>
          <input
            type="file"
            accept="image/*"
            @change="onImageChange"
            class="block w-full cursor-pointer text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:font-semibold file:text-white transition hover:file:bg-indigo-700"
          />
          <p v-if="imageError" class="mt-2 text-sm text-rose-500">{{ imageError }}</p>
          <figure class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white/80 p-4 text-center shadow-sm">
            <img :src="imageUrl" :alt="localProduct.name" class="mx-auto h-auto max-h-48 rounded-2xl object-contain shadow" loading="lazy" />
            <figcaption class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">
              {{ localProduct.name || 'Aperçu de l’image' }}
            </figcaption>
          </figure>
        </div>
      </section>

      <footer class="flex items-center justify-end gap-3 border-t border-slate-200 bg-white/90 px-6 py-4">
        <button
          class="inline-flex items-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
          :class="{ 'cursor-not-allowed': isSaving }"
          @click="saveProduct"
          :disabled="isSaving"
        >
          Enregistrer
        </button>
        <button
          class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
          @click="closeModal"
          :disabled="isSaving"
        >
          Annuler
        </button>
      </footer>
      <p v-if="saveError" class="px-6 pb-4 text-center text-sm font-semibold text-rose-600">{{ saveError }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, reactive, onMounted, computed } from 'vue'
import axios from 'axios'
import { API_BASE_URL, API_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
  isOpen: Boolean,
  product: Object
})

const emits = defineEmits(['close', 'save'])

const localProduct = reactive({
  id: null,
  name: '',
  ref: '',
  price: 0,
  pricing_id: null,
  status: false,
  category_id: null,
  image: null,
  imagePreview: ''
})

const categories = ref([])
const imageError = ref('')

const auth = useAuth()
const { activePos } = auth

const fetchCategories = async () => {
  try {
    const token = localStorage.getItem('token')
    const pointOfSaleId = activePos.value?.id

    console.log('🔍 pointOfSaleId utilisé:', pointOfSaleId)

    const response = await axios.get(`${API_BASE_URL}/categories`, {
      params: { point_of_sale_id: pointOfSaleId },
      headers: { Authorization: `Bearer ${token}` }
    })

    console.log('📦 Réponse brute /categories:', response.data)

    // Extraction flexible
    let data = response.data
    if (data?.data && Array.isArray(data.data)) data = data.data
    if (data?.categories && Array.isArray(data.categories)) data = data.categories
    if (!Array.isArray(data)) {
      console.warn('⚠️ Pas de tableau trouvé, structure:', data)
      categories.value = []
      return
    }

    categories.value = data
    console.log(`✅ ${categories.value.length} catégories chargées:`, categories.value)
  } catch (error) {
    console.error('❌ Erreur chargement catégories:', error.response?.data || error.message)
    categories.value = []
  }
}
onMounted(fetchCategories)

watch(
  () => props.product,
  (newProduct) => {
    if (newProduct) {
      Object.assign(localProduct, {
        id: newProduct.id,
        name: newProduct.name,
        ref: newProduct.ref,
        price: newProduct.price,
        pricing_id: newProduct.pricing_id,
        status: newProduct.status,
        category_id: newProduct.category_id,
        image: null,
        imagePreview: newProduct.image
          ? (newProduct.image.startsWith('blob:') ? newProduct.image : `${API_URL}/storage/${newProduct.image}`)
          : `${API_URL}/storage/products/default-product-image.jpg`
      })
    }
  },
  { immediate: true }
)

const price = computed({
  get() {
    return localProduct.price
  },
  set(value) {
    localProduct.price = value
  }
})

const imageUrl = computed(() => {
  return localProduct.imagePreview || `${API_URL}/storage/products/default-product-image.jpg`
})

const onImageChange = (event) => {
  const file = event.target.files[0]
  if (!file) return
  event.target.value = ''

  if (!file.type.startsWith('image/')) {
    imageError.value = "Type de fichier invalide"
    return
  }
  if (file.size > 2048 * 1024) {
    imageError.value = "Fichier trop volumineux (max 2MB)"
    return
  }

  imageError.value = ''
  localProduct.image = file
  localProduct.imagePreview = URL.createObjectURL(file)
}

const isSaving = ref(false)
const saveError = ref('')

const saveProduct = async () => {
  try {
    isSaving.value = true
    saveError.value = ''
    const token = localStorage.getItem('token')
    
    // Payload unique pour la mise à jour atomique
    const payload = {
      id: localProduct.id,
      name: localProduct.name,
      ref: localProduct.ref,
      price: localProduct.price, // Désormais envoyé dans la même requête
      status: localProduct.status,
      category_id: localProduct.category_id,
      image: localProduct.image ? await convertFileToBase64(localProduct.image) : null
    }

    const response = await axios.put(
      `${API_BASE_URL}/products/${localProduct.id}`,
      payload,
      { 
        headers: { 
          Authorization: `Bearer ${token}`, 
          'Content-Type': 'application/json',
          'X-Active-POS-ID': activePos.value?.id || ''
        } 
      }
    )

    // La réponse contient le produit avec les pricings mis à jour
    emits('save', response.data.product)
    emits('close')
  } catch (error) {
    console.error('Erreur:', error.response?.data || error)
    saveError.value = error.response?.data?.message || 'Erreur de mise à jour'
  } finally {
    isSaving.value = false
  }
}

const convertFileToBase64 = (file) => {
  return new Promise((resolve) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result.replace(/^data:image\/[a-z]+;base64,/, ''))
    reader.readAsDataURL(file)
  })
}

const closeModal = () => emits('close')
</script>
<style scoped>
</style>
