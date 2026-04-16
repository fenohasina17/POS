<template>
  <div class="product-layout grid gap-3 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)] xl:grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
    <aside class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-800">Catégories</h2>
        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600">
          {{ totalProducts }}
        </span>
      </div>

      <div class="mt-4 flex-1 overflow-y-auto space-y-2 pb-1">
        <button
          type="button"
          class="flex w-full items-center justify-between rounded-2xl px-4 py-2 text-sm font-medium transition hover:bg-indigo-50"
          :class="selectedCategory === null ? 'bg-indigo-500/10 text-indigo-600' : 'text-slate-600'"
          @click="selectCategory(null)"
        >
          <span>Tous</span>
          <span class="rounded-full bg-white/70 px-2 py-0.5 text-xs font-semibold text-slate-500">
            {{ totalProducts }}
          </span>
        </button>

        <button
          v-for="cat in categories"
          :key="cat.id"
          type="button"
          class="flex w-full items-center justify-between rounded-2xl px-4 py-2 text-sm font-medium transition hover:bg-indigo-50"
          :class="selectedCategory === cat.id ? 'bg-indigo-500/10 text-indigo-600' : 'text-slate-600'"
          @click="selectCategory(cat.id)"
        >
          <span class="truncate">{{ cat.name }}</span>
          <span class="rounded-full bg-white/70 px-2 py-0.5 text-xs font-semibold text-slate-500">
            {{ getCategoryCount(cat.id) }}
          </span>
        </button>
      </div>
    </aside>

    <section class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
      <div class="flex flex-col gap-3 border-b border-slate-100 pb-2">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="relative w-full md:max-w-sm">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
              <FontAwesomeIcon icon="fa-solid fa-magnifying-glass" />
            </span>
            <input
              v-model.trim="searchQuery"
              type="text"
              placeholder="Rechercher un produit..."
              class="w-full rounded-full border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
            />
          </div>

          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
              @click="openAddModal"
            >
              <FontAwesomeIcon icon="fa-solid fa-plus" />
              Nouveau produit
            </button>
          </div>
        </div>
      </div>

      <div v-if="error" class="mt-2 rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-600">
        {{ error }}
      </div>

      <div class="mt-2.5 flex-1 overflow-hidden">
        <div
          v-if="loading"
          class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-10 text-center text-sm text-slate-500"
        >
          <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
          <p class="mt-4 font-medium">Chargement en cours...</p>
        </div>

        <template v-else>
          <div
            class="hidden grid-cols-[2.5fr,1.2fr,1fr,1fr,auto] items-center border-b border-slate-100 px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400 sm:grid"
          >
            <span>Produit</span>
            <span>Catégorie</span>
            <span>Prix</span>
            <span>Status</span>
            <span class="text-right">Actions</span>
          </div>

          <div
            v-if="filteredProducts.length === 0"
            class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-6 text-sm text-slate-500"
          >
            Aucun produit trouvé
          </div>

          <div v-else class="flex h-full flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto">
              <ul class="divide-y divide-slate-100">
                <li
                  v-for="product in filteredProducts"
                  :key="product.id"
                  class="grid gap-3 px-3 py-3 sm:grid-cols-[2.5fr,1.2fr,1fr,1fr,auto] sm:items-center sm:px-4"
                >
                  <div class="flex items-center gap-3">
                    <img
                      :src="getProductImage(product)"
                      :alt="product.name"
                      class="h-12 w-12 flex-shrink-0 rounded-full object-cover ring-2 ring-indigo-50"
                      loading="lazy"
                      @error="onProductImageError"
                    />
                    <div>
                      <p class="font-semibold text-slate-800">{{ product.name }}</p>
                      <p class="text-sm text-slate-400">{{ product.ref || 'Référence non fournie' }}</p>
                    </div>
                  </div>

                  <div class="text-sm font-medium text-slate-600 sm:text-base">
                    {{ product.category_name || '—' }}
                  </div>

                  <div class="text-sm font-semibold text-slate-800 sm:text-base">
                    {{ formatPrice(product.price) }}
                  </div>

                  <div>
                    <span :class="statusBadgeClass(product.status)">
                      {{ product.status ? 'Actif' : 'Inactif' }}
                    </span>
                  </div>

                  <div class="flex justify-end gap-2">
                    <button
                      type="button"
                      class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                      @click.stop="openEditModal(product)"
                      aria-label="Modifier"
                    >
                      <FontAwesomeIcon icon="fa-solid fa-pencil" />
                    </button>
                    <button
                      type="button"
                      class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-rose-500 transition hover:border-rose-200 hover:text-rose-600"
                      @click.stop="confirmDelete(product)"
                      aria-label="Supprimer"
                    >
                      <FontAwesomeIcon icon="fa-solid fa-trash" />
                    </button>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </template>
      </div>
    </section>
  </div>

  <ProductEditModal
    :isOpen="isEditModalOpen"
    :product="selectedProduct"
    @close="closeEditModal"
    @save="handleSave"
  />
  <AddProductModal :isOpen="isAddModalOpen" @close="closeAddModal" @added="handleAdd" />

  <div v-if="isDeleteConfirmOpen" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/60" @click="closeDeleteConfirm"></div>
    <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
      <h2 class="text-lg font-semibold text-slate-900">Confirmer la suppression</h2>
      <p class="mt-3 text-sm text-slate-500">
        Êtes-vous sûr de vouloir supprimer le produit
        <strong class="text-slate-700">{{ productToDelete?.name }}</strong> ?
      </p>
      <div class="mt-6 flex justify-end gap-3">
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-60"
          @click="closeDeleteConfirm"
          :disabled="isDeleting"
        >
          Annuler
        </button>
        <button
          type="button"
          class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60"
          @click="deleteProduct"
          :disabled="isDeleting"
        >
          Supprimer
        </button>
      </div>
    </div>

    <!-- Modal de confirmation de suppression de catégorie -->
    <div v-if="isCategoryDeleteConfirmOpen" class="modal is-active">
      <div class="modal-background" @click="closeCategoryDeleteConfirm"></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title">Confirmer la suppression</p>
          <button class="delete" @click="closeCategoryDeleteConfirm"></button>
        </header>
        <section class="modal-card-body">
          <p>Êtes-vous sûr de vouloir supprimer la catégorie <strong>{{ categoryToDelete?.name }}</strong> ?</p>
          <p class="has-text-danger">Cette action est irréversible et supprimera également tous les produits associés.</p>
        </section>
        <footer class="modal-card-foot">
          <button class="button is-danger" @click="deleteCategory" :disabled="isCategoryDeleting">Supprimer</button>
          <button class="button" @click="closeCategoryDeleteConfirm">Annuler</button>
        </footer>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { API_URL } from '@/utils/api'
import ProductEditModal from './ProductEditModal.vue'
import AddProductModal from './AddProductModal.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import placeholderImage from '@/assets/avatar.png'

const products = ref([])
const categories = ref([])
const searchQuery = ref('')
const selectedCategory = ref(null)
const loading = ref(true)
const error = ref(null)

const isEditModalOpen = ref(false)
const selectedProduct = ref(null)
const isAddModalOpen = ref(false)

const isDeleteConfirmOpen = ref(false)
const productToDelete = ref(null)
const isDeleting = ref(false)

const fetchData = async () => {
  try {
    loading.value = true
    const token = localStorage.getItem('token')
    const user = JSON.parse(localStorage.getItem('user'))
    const pointOfSaleId = user?.point_of_sale_id

    if (!pointOfSaleId) {
      throw new Error('Point de vente non configuré pour cet utilisateur')
    }

    const response = await axios.get(`${API_URL}/api/categories`, {
      params: { with_products: 1, point_of_sale_id: pointOfSaleId, with_pricing: 1 },
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })

    const apiResponse = response.data

    if (!Array.isArray(apiResponse)) {
      throw new Error('Structure de réponse API invalide')
    }

    categories.value = apiResponse

    products.value = categories.value.reduce((allProducts, category) => {
      if (Array.isArray(category.products)) {
        const categoryProducts = category.products.map((product) => ({
          ...product,
          category_id: category.id,
          category_name: category.name,
          price: extractProductPrice(product, pointOfSaleId),
        }))
        return [...allProducts, ...categoryProducts]
      }
      return allProducts
    }, [])
  } catch (e) {
    console.error('Erreur de chargement des données :', e)
    error.value = e.response?.data?.message || e.message || 'Erreur de chargement des données'
  } finally {
    loading.value = false
  }
}

const extractProductPrice = (product, pointOfSaleId) => {
  if (!Array.isArray(product.pricing)) return null
  const pricing = product.pricing.find((p) => p.point_of_sale_id === pointOfSaleId)
  return pricing ? parseFloat(pricing.price) : null
}

onMounted(fetchData)

const selectCategory = (catId) => {
  selectedCategory.value = catId
}

const formatPrice = (price) => {
  if (price === null || price === undefined || Number.isNaN(price)) {
    return 'Non disponible'
  }
  return `${new Intl.NumberFormat('fr-FR').format(price)} Ar`
}

const getProductImage = (product) => {
  const raw = product?.image || product?.product?.image
  if (!raw) {
    return placeholderImage
  }

  if (/^https?:\/\//i.test(raw)) {
    return raw
  }

  if (raw.startsWith('storage/')) {
    return `${API_URL}/${raw}`
  }

  if (raw.startsWith('products/')) {
    return `${API_URL}/storage/${raw}`
  }

  return `${API_URL}/storage/products/${raw}`
}

const onProductImageError = (event) => {
  if (!event?.target) {
    return
  }
  event.target.onerror = null
  event.target.src = placeholderImage
}

const productsCountByCategory = computed(() => {
  const counts = {}
  for (const product of products.value) {
    counts[product.category_id] = (counts[product.category_id] || 0) + 1
  }
  return counts
})

const totalProducts = computed(() => products.value.length)

const getCategoryCount = (catId) => productsCountByCategory.value[catId] || 0

const filteredProducts = computed(() => {
  let result = products.value

  if (selectedCategory.value !== null) {
    result = result.filter((product) => product.category_id === selectedCategory.value)
  }

  if (searchQuery.value.trim() !== '') {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(
      (product) =>
        product.name.toLowerCase().includes(query) ||
        (product.ref && product.ref.toLowerCase().includes(query))
    )
  }

  return result
})

const openEditModal = (product) => {
  selectedProduct.value = JSON.parse(JSON.stringify(product))
  isEditModalOpen.value = true
}

const closeEditModal = () => {
  isEditModalOpen.value = false
  selectedProduct.value = null
}

const handleSave = (updatedProduct) => {
  const index = products.value.findIndex((p) => p.id === updatedProduct.id)
  if (index !== -1) {
    products.value[index] = {
      ...products.value[index],
      ...updatedProduct,
      category_id: updatedProduct.category_id,
      category_name: updatedProduct.category_name,
      price:
        updatedProduct.price !== undefined ? updatedProduct.price : products.value[index].price,
    }
  }
  closeEditModal()
}

const openAddModal = () => {
  isAddModalOpen.value = true
}

const closeAddModal = () => {
  isAddModalOpen.value = false
}

const handleAdd = async (newProduct) => {
  try {
    const token = localStorage.getItem('token')
    const response = await axios.get(`${API_URL}/api/products/${newProduct.id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })

    const fullProduct = {
      ...response.data,
      category_id: newProduct.category_id,
      category_name: categories.value.find((c) => c.id === newProduct.category_id)?.name || '',
      price: newProduct.price ?? response.data.price ?? null,
    }

    products.value = [fullProduct, ...products.value]
  } catch (error) {
    console.error('Erreur lors de la récupération du produit ajouté:', error)
    products.value = [newProduct, ...products.value]
  }
  closeAddModal()
}

const confirmDelete = (product) => {
  productToDelete.value = product
  isDeleteConfirmOpen.value = true
}

const closeDeleteConfirm = () => {
  isDeleteConfirmOpen.value = false
  productToDelete.value = null
}

const deleteProduct = async () => {
  if (!productToDelete.value) return
  isDeleting.value = true
  try {
    const token = localStorage.getItem('token')
    await axios.delete(`${API_URL}/api/products/${productToDelete.value.id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    products.value = products.value.filter((p) => p.id !== productToDelete.value.id)
    closeDeleteConfirm()
  } catch (error) {
    console.error('Erreur lors de la suppression:', error.response?.data || error)
    alert(error.response?.data?.message || 'Erreur lors de la suppression du produit')
  } finally {
    isDeleting.value = false
  }
}

const statusBadgeClass = (isActive) =>
  isActive
    ? 'inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-600'
    : 'inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600'
</script>

<style scoped>
.product-layout {
  min-height: calc(100vh - 5rem);
  min-height: calc(100dvh - 5rem);
}

@media (min-width: 1024px) {
  .product-layout {
    height: calc(100vh - 5.5rem);
    height: calc(100dvh - 5.5rem);
    max-height: calc(100vh - 5.5rem);
    max-height: calc(100dvh - 5.5rem);
    overflow: hidden;
  }
}
</style>
