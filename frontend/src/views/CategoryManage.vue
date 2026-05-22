<template>
  <div class="category-wrapper">
    <div class="category-layout grid gap-3 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
      <aside class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Catégories</h2>
          <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600">
            {{ filteredCategories.length }}
          </span>
        </div>

        <div class="mt-4 flex-1 space-y-3 overflow-y-auto pb-1">
          <div>
            <button
              type="button"
              class="flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
              @click="openCreateModal"
            >
              <FontAwesomeIcon icon="fa-solid fa-plus" />
              Nouvelle catégorie
            </button>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Recherche</label>
            <div class="relative mt-1">
              <FontAwesomeIcon
                icon="fa-solid fa-magnifying-glass"
                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
              />
              <input
                v-model.trim="searchQuery"
                type="text"
                placeholder="Rechercher une catégorie..."
                class="w-full rounded-full border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
            </div>
          </div>
        </div>
      </aside>

      <section class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
          <h1 class="text-base font-semibold text-slate-800">Gestion des catégories</h1>
          <span class="text-xs font-semibold text-slate-400">{{ filteredCategories.length }} résultat(s)</span>
        </div>

        <div class="mt-2.5 flex-1 overflow-hidden">
          <div
            v-if="loading"
            class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-10 text-center text-sm text-slate-500"
          >
            <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
            <p class="mt-4 font-medium">Chargement des catégories...</p>
          </div>

          <div
            v-else-if="filteredCategories.length === 0"
            class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-6 text-sm text-slate-500"
          >
            Aucune catégorie trouvée
          </div>

          <div v-else class="flex h-full flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto">
              <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li
                  v-for="category in filteredCategories"
                  :key="category.id"
                  class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex-1">
                      <p class="text-sm font-semibold text-slate-800">{{ category.name }}</p>
                      <p v-if="category.description" class="mt-1 text-xs text-slate-500">{{ category.description }}</p>
                      <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ category.products_count || 0 }} produit(s)
                      </p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                      <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                        @click.stop="openEditModal(category)"
                      >
                        <FontAwesomeIcon icon="fa-solid fa-pen" />
                      </button>
                      <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-rose-500 transition hover:border-rose-200 hover:text-rose-600"
                        @click.stop="confirmDelete(category)"
                      >
                        <FontAwesomeIcon icon="fa-solid fa-trash" />
                      </button>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>
    </div>

    <CategoryCreateModal :isOpen="isCreateModalOpen" @close="closeCreateModal" @added="handleAdded" />
    <CategoryEditModal
      :isOpen="isEditModalOpen"
      :categoryData="selectedCategory"
      @close="closeEditModal"
      @updated="handleUpdated"
    />

    <div v-if="isDeleteConfirmOpen" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-slate-900/60" @click="closeDeleteConfirm"></div>
      <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-slate-900">Confirmer la suppression</h2>
        <p class="mt-3 text-sm text-slate-500">
          Êtes-vous sûr de vouloir supprimer la catégorie
          <strong class="text-slate-700">{{ categoryToDelete?.name }}</strong> ?
        </p>
        <p class="mt-2 text-xs text-rose-500">
          Cette action est irréversible et supprimera également tous les produits associés.
        </p>
        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
            @click="closeDeleteConfirm"
            :disabled="isDeleting"
          >
            Annuler
          </button>
          <button
            type="button"
            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60"
            @click="deleteCategory"
            :disabled="isDeleting"
          >
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import CategoryCreateModal from './CategoryCreateModal.vue'
import CategoryEditModal from './CategoryEditModal.vue'
import { API_BASE_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'

// =============================================
// ÉTATS RÉACTIFS
// =============================================
const { activePos } = useAuth()
const categories = ref([])
const searchQuery = ref('')
const loading = ref(true)
const error = ref(null)

// États des modals
const isCreateModalOpen = ref(false)
const isEditModalOpen = ref(false)
const selectedCategory = ref(null)

// États pour la suppression
const isDeleteConfirmOpen = ref(false)
const categoryToDelete = ref(null)
const isDeleting = ref(false)

// =============================================
// MÉTHODES DE CHARGEMENT DES DONNÉES
// =============================================

/**
 * Charge les catégories depuis l'API
 */
const fetchCategories = async () => {
  try {
    loading.value = true
    const posId = activePos.value?.id
    if (!posId) throw new Error('Point de vente non configuré')
    
    const token = localStorage.getItem('token');
    const response = await axios.get(`${API_BASE_URL}/categories`, {
      params: {
        'with_products': 1,
        'point_of_sale_id': posId,
        'with_pricing': 1,
      },
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`
      }
    })
    const data = Array.isArray(response.data) ? response.data : response.data.data || []
    categories.value = data.map(cat => ({
      ...cat,
      products_count: cat.products ? cat.products.length : 0
    }))
    loading.value = false
  } catch (error) {
    console.error('Erreur de chargement des produits :', error.response?.data || error.message)
    loading.value = false
  }
};

// Chargement initial
onMounted(() => {
  fetchCategories()
})

// =============================================
// COMPUTED PROPERTIES
// =============================================

/**
 * Liste filtrée des catégories basée sur la recherche
 */
const filteredCategories = computed(() => {
  if (!searchQuery.value.trim()) return categories.value
  const query = searchQuery.value.toLowerCase()
  return categories.value.filter(category =>
    category.name.toLowerCase().includes(query) ||
    (category.description && category.description.toLowerCase().includes(query))
  )
})

// =============================================
// GESTION DES MODALS
// =============================================

/**
 * Ouvre le modal de création
 */
const openCreateModal = () => {
  isCreateModalOpen.value = true
}

/**
 * Ferme le modal de création
 */
const closeCreateModal = () => {
  isCreateModalOpen.value = false
}

/**
 * Gère l'ajout d'une nouvelle catégorie
 */
const handleAdded = async (newCategory) => {
  try {
    const token = localStorage.getItem('token')
    const response = await axios.post(`${API_BASE_URL}/categories`, newCategory, {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    })
    
    // Normalisation des données reçues pour correspondre au format attendu par le template
    const addedCat = response.data?.data || response.data
    categories.value.push({
      ...addedCat,
      products_count: 0 // Une nouvelle catégorie a 0 produit
    })
    
    closeCreateModal()
  } catch (e) {
    console.error('Erreur lors de l\'ajout:', e)
    alert(e.response?.data?.message || 'Erreur lors de l\'ajout de la catégorie')
  }
}

/**
 * Ouvre le modal d'édition
 */
const openEditModal = (category) => {
  selectedCategory.value = { ...category }
  isEditModalOpen.value = true
}

/**
 * Ferme le modal d'édition
 */
const closeEditModal = () => {
  isEditModalOpen.value = false
  selectedCategory.value = null
}

/**
 * Gère la mise à jour d'une catégorie
 */
const handleUpdated = async (updatedCategory) => {
  try {
    const token = localStorage.getItem('token')
    await axios.put(`${API_BASE_URL}/categories/${updatedCategory.id}`, updatedCategory, {
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    })
    await fetchCategories()
    closeEditModal()
  } catch (e) {
    console.error('Erreur lors de la modification:', e)
    alert(e.response?.data?.message || 'Erreur lors de la modification de la catégorie')
  }
}

// =============================================
// GESTION DE LA SUPPRESSION
// =============================================

/**
 * Ouvre la confirmation de suppression
 */
const confirmDelete = (category) => {
  categoryToDelete.value = category
  isDeleteConfirmOpen.value = true
}

/**
 * Ferme la confirmation de suppression
 */
const closeDeleteConfirm = () => {
  isDeleteConfirmOpen.value = false
  categoryToDelete.value = null
}

/**
 * Supprime une catégorie
 */
const deleteCategory = async () => {
  if (!categoryToDelete.value) return
  isDeleting.value = true
  try {
    const token = localStorage.getItem('token')
    await axios.delete(`${API_BASE_URL}/categories/${categoryToDelete.value.id}`, {
      headers: { Authorization: `Bearer ${token}` }
    })
    categories.value = categories.value.filter(c => c.id !== categoryToDelete.value.id)
    closeDeleteConfirm()
  } catch (e) {
    console.error('Erreur lors de la suppression:', e)
    alert(e.response?.data?.message || 'Erreur lors de la suppression de la catégorie')
  } finally {
    isDeleting.value = false
  }
}
</script>

<style scoped>
.category-layout {
  min-height: calc(100vh - 5rem);
  min-height: calc(100dvh - 5rem);
}

@media (min-width: 1024px) {
  .category-layout {
    height: calc(100vh - 5.5rem);
    height: calc(100dvh - 5.5rem);
    max-height: calc(100vh - 5.5rem);
    max-height: calc(100dvh - 5.5rem);
    overflow: hidden;
  }
}
</style>
