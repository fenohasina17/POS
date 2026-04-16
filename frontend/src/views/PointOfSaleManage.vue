<template>
  <div class="pos-layout grid gap-3 lg:grid-cols-[minmax(0,1fr)]">
    <section class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
          <h1 class="text-base font-semibold text-slate-800">Gestion des points de vente</h1>
          <p class="text-xs text-slate-400">Administrez les différents points de vente disponibles.</p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
          @click="openCreate"
        >
          <FontAwesomeIcon icon="fa-solid fa-plus" />
          Nouveau point de vente
        </button>
      </div>

      <div class="mt-3 flex-1 overflow-hidden">
        <div
          v-if="loading"
          class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-10 text-center text-sm text-slate-500"
        >
          <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
          <p class="mt-4 font-medium">Chargement des points de vente...</p>
        </div>

        <div
          v-else-if="!pointsOfSale.length"
          class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-6 text-sm text-slate-500"
        >
          Aucun point de vente enregistré.
        </div>

        <div v-else class="flex h-full flex-col overflow-hidden">
          <div class="flex-1 overflow-y-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3 text-left">Nom</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="pos in pointsOfSale" :key="pos.id" class="bg-white">
                  <td class="px-4 py-3 text-slate-700">{{ pos.name }}</td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                        @click="editPointOfSale(pos)"
                        aria-label="Éditer"
                      >
                        <FontAwesomeIcon icon="fa-solid fa-pen" />
                      </button>
                      <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-rose-500 transition hover:border-rose-200 hover:text-rose-600"
                        @click="deletePointOfSale(pos.id)"
                        aria-label="Supprimer"
                      >
                        <FontAwesomeIcon icon="fa-solid fa-trash" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>

  <PointOfSaleModal
    :is-open="showAddForm || showEditForm"
    :title="showEditForm ? 'Éditer un point de vente' : 'Ajouter un point de vente'"
    :submit-label="showEditForm ? 'Mettre à jour' : 'Ajouter'"
    :initial-name="form.name"
    @submit="submitForm"
    @close="closeForm"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PointOfSaleModal from './PointOfSaleModal.vue'
import { API_BASE_URL } from '@/utils/api'

const pointsOfSale = ref([])
const loading = ref(true)
const showAddForm = ref(false)
const showEditForm = ref(false)
const form = ref({
  id: null,
  name: '',
})

const fetchPointsOfSale = async () => {
  loading.value = true
  try {
    const response = await axios.get(`${API_BASE_URL}/point_of_sales`)
    pointsOfSale.value = response.data.data || response.data
  } catch (error) {
    console.error('Erreur lors du chargement des points de vente:', error)
  } finally {
    loading.value = false
  }
}

const submitForm = async (name) => {
  if (typeof name === 'string') {
    form.value.name = name
  }
  if (showEditForm.value) {
    // Update existing point of sale
    try {
      await axios.put(`${API_BASE_URL}/point_of_sales/${form.value.id}`, {
        name: form.value.name,
      })
      await fetchPointsOfSale()
      closeForm()
    } catch (error) {
      console.error('Erreur lors de la mise à jour du point de vente:', error)
    }
  } else {
    // Add new point of sale
    try {
      await axios.post(`${API_BASE_URL}/point_of_sales`, {
        name: form.value.name,
      })
      await fetchPointsOfSale()
      closeForm()
    } catch (error) {
      console.error('Erreur lors de l\'ajout du point de vente:', error)
    }
  }
}

const editPointOfSale = (pos) => {
  form.value.id = pos.id
  form.value.name = pos.name
  showEditForm.value = true
  showAddForm.value = false
}

const deletePointOfSale = async (id) => {
  if (confirm('Voulez-vous vraiment supprimer ce point de vente ?')) {
    try {
      await axios.delete(`${API_BASE_URL}/point_of_sales/${id}`)
      await fetchPointsOfSale()
    } catch (error) {
      console.error('Erreur lors de la suppression du point de vente:', error)
    }
  }
}

const closeForm = () => {
  showAddForm.value = false
  showEditForm.value = false
  form.value.id = null
  form.value.name = ''
}

const openCreate = () => {
  showAddForm.value = true
  form.value.id = null
  form.value.name = ''
}

onMounted(() => {
  fetchPointsOfSale()
})
</script>

<style scoped>
.pos-layout {
  min-height: calc(100vh - 5rem);
  min-height: calc(100dvh - 5rem);
}

@media (min-width: 1024px) {
  .pos-layout {
    height: calc(100vh - 5.5rem);
    height: calc(100dvh - 5.5rem);
    max-height: calc(100vh - 5.5rem);
    max-height: calc(100dvh - 5.5rem);
    overflow: hidden;
  }
}
</style>
