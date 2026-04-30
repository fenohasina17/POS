/* eslint-disable vue/multi-word-component-names */
<template>
  <div class="printer-layout grid gap-3 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
    <aside class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-800">Filtres</h2>
        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600">
          {{ filteredPrinters.length }}
        </span>
      </div>

      <div class="mt-4 space-y-3">
        <div class="space-y-2">
          <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Type de connexion</h3>
          <div class="flex flex-col gap-2">
            <button
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-left text-sm font-medium transition hover:border-indigo-200 hover:text-indigo-600"
              :class="selectedConnectionType === null ? 'bg-indigo-500 text-white shadow' : 'bg-white text-slate-600'"
              @click="selectConnectionType(null)"
            >
              Tous
            </button>
            <button
              v-for="type in connectionTypes"
              :key="type"
              type="button"
              class="rounded-xl border border-slate-200 px-4 py-2 text-left text-sm font-medium transition hover:border-indigo-200 hover:text-indigo-600"
              :class="selectedConnectionType === type ? 'bg-indigo-500 text-white shadow' : 'bg-white text-slate-600'"
              @click="selectConnectionType(type)"
            >
              {{ getConnectionTypeLabel(type) }}
            </button>
          </div>
        </div>
      </div>
    </aside>

    <section class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
          <h1 class="text-base font-semibold text-slate-800">Gestion des imprimantes</h1>
          <p class="text-xs text-slate-400">Surveillez et configurez vos imprimantes connectées.</p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
          @click="openAddModal"
        >
          <FontAwesomeIcon icon="fa-solid fa-plus" />
          Ajouter une imprimante
        </button>
      </div>

      <div class="mt-3 flex-1 overflow-hidden">
        <div
          v-if="loading"
          class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-10 text-center text-sm text-slate-500"
        >
          <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
          <p class="mt-4 font-medium">Chargement des imprimantes...</p>
        </div>

        <div
          v-else-if="error"
          class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-rose-200 bg-rose-50/60 px-6 text-center text-sm text-rose-500"
        >
          <p>{{ error }}</p>
          <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
            @click="fetchPrinters"
          >
            Réessayer
          </button>
        </div>

        <div
          v-else-if="!filteredPrinters.length"
          class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-6 text-sm text-slate-500"
        >
          Aucune imprimante trouvée
        </div>

        <div v-else class="flex h-full flex-col overflow-hidden">
          <div class="flex-1 overflow-y-auto">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <article
                v-for="printer in filteredPrinters"
                :key="printer.id"
                class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md"
                :class="{ 'opacity-70': !isActive(printer) }"
              >
                <header class="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <h3 class="text-sm font-semibold text-slate-800">{{ printer.name }}</h3>
                    <p class="text-xs text-slate-400">{{ getConnectionTypeLabel(printer.connection_type) }}</p>
                  </div>
                  <div class="flex gap-2">
                    <span
                      v-if="printer.is_default == 1"
                      class="rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-600"
                    >
                      Par défaut
                    </span>
                    <span
                      class="rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-wide"
                      :class="isActive(printer) ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-200 text-slate-600'"
                    >
                      {{ isActive(printer) ? 'Actif' : 'Inactif' }}
                    </span>
                  </div>
                </header>

                <dl class="mt-3 space-y-1 text-xs text-slate-500">
                  <div class="flex items-center justify-between">
                    <dt class="font-semibold text-slate-600">Timeout</dt>
                    <dd>{{ printer.timeout ?? 'N/A' }}s</dd>
                  </div>
                  <div v-if="printer.cash_register" class="flex items-center justify-between">
                    <dt class="font-semibold text-slate-600">Caisse</dt>
                    <dd class="truncate text-right">{{ printer.cash_register.name }}</dd>
                  </div>
                  <div v-if="printer.cash_register?.point_of_sale" class="flex items-center justify-between">
                    <dt class="font-semibold text-slate-600">Point de vente</dt>
                    <dd class="truncate text-right">{{ printer.cash_register.point_of_sale.name }}</dd>
                  </div>
                </dl>

                <footer class="mt-4 flex justify-end gap-2">
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                    @click.stop="openEditModal(printer)"
                    aria-label="Modifier"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-pen" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-rose-500 transition hover:border-rose-200 hover:text-rose-600"
                    @click.stop="confirmDelete(printer)"
                    aria-label="Supprimer"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-trash" />
                  </button>
                </footer>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <PrinterEditModal
    :isOpen="isEditModalOpen"
    :printer="selectedPrinter"
    :isEdit="true"
    @close="closeEditModal"
    @save="handleSave"
    :selectedPOS="posStore.selectedPOS"
  />
  <PrinterCreateModal
    :isOpen="isAddModalOpen"
    @close="closeAddModal"
    @created="handleAdd"
    :selectedPOS="posStore.selectedPOS"
  />

  <transition name="fade">
    <div v-if="isDeleteConfirmOpen" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-slate-900/60" @click="closeDeleteConfirm"></div>
      <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Confirmer la suppression</h2>
            <p class="text-xs text-slate-400">Cette action supprimera définitivement l'imprimante.</p>
          </div>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:text-rose-500"
            @click="closeDeleteConfirm"
          >
            ×
          </button>
        </header>

        <section class="mt-4 space-y-2 text-sm text-slate-600">
          <p>Supprimer l'imprimante <strong>{{ printerToDelete?.name }}</strong> ?</p>
          <p class="text-xs text-rose-500">Cette opération est irréversible.</p>
        </section>

        <footer class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
            @click="closeDeleteConfirm"
            :disabled="isDeleting"
          >
            Annuler
          </button>
          <button
            type="button"
            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-60"
            @click="deletePrinter"
            :disabled="isDeleting"
          >
            Supprimer
          </button>
        </footer>
        <p v-if="deleteError" class="mt-2 text-xs text-rose-500">{{ deleteError }}</p>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import PrinterEditModal from './PrinterEditModal.vue'
import PrinterCreateModal from './PrinterCreateModal.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import printerService from '../services/printerService.js'
import { usePosStore } from '../stores/posStore.js'
import { useAuth } from '../composables/useAuth.js'

const posStore = usePosStore()

// États réactifs
const printers = ref([])
const selectedConnectionType = ref(null)
const loading = ref(true)
const error = ref(null)

const { isAdmin, loadUserData } = useAuth()
const isUserAdmin = ref(false)

// États des modals
const isEditModalOpen = ref(false)
const selectedPrinter = ref(null)
const isAddModalOpen = ref(false)

// États pour la suppression
const isDeleteConfirmOpen = ref(false)
const printerToDelete = ref(null)
const isDeleting = ref(false)
const deleteError = ref('')

const fetchPrinters = async () => {
  try {
    loading.value = true
    const response = await printerService.getAll()
    // Fix: assign printers.value to array of printers
    printers.value = response.data.data ? response.data.data : response.data
  } catch (e) {
    console.error("Erreur de chargement des imprimantes :", e)
    error.value = e.response?.data?.message || e.message || 'Erreur de chargement des données'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadUserData()
  isUserAdmin.value = isAdmin.value
  if (isUserAdmin.value) {
    await fetchPrinters()
  }
})

// Méthodes d'interface utilisateur
const selectConnectionType = (type) => {
  selectedConnectionType.value = type
}

const getConnectionTypeLabel = (type) => {
  const labels = {
    cups: 'CUPS'
  }
  return labels[type] || type
}

const isActive = (printer) => {
  return printer.is_active === true || printer.is_active === 'true' || printer.is_active == 1
}

// Propriétés calculées
const filteredPrinters = computed(() => {
  let result = Array.isArray(printers.value) ? printers.value : []

  // Filtrage par POS sélectionné (pour les admins)
  if (isUserAdmin.value && posStore.selectedPOS) {
    result = result.filter(printer =>
      printer.cash_register &&
      printer.cash_register.point_of_sale &&
      printer.cash_register.point_of_sale.id === posStore.selectedPOS.id
    )
  }

  // Filtrage par type de connexion
  if (selectedConnectionType.value !== null) {
    result = result.filter(printer => printer.connection_type === selectedConnectionType.value)
  }

  return result
})

const connectionTypes = computed(() => {
  const types = new Set()
  if (Array.isArray(printers.value)) {
    printers.value.forEach((printer) => {
      if (printer?.connection_type) types.add(printer.connection_type)
    })
  }
  return Array.from(types)
})

// Gestion des modals d'édition
const openEditModal = (printer) => {
  selectedPrinter.value = { ...printer }
  isEditModalOpen.value = true
}

const closeEditModal = () => {
  isEditModalOpen.value = false
  selectedPrinter.value = null
}

const handleSave = async (updatedPrinter) => {
  try {
    // Fetch the full printer data with relations (cash_register, point_of_sale)
    const response = await printerService.getById(updatedPrinter.id)
    const fullPrinter = response.data.data ? response.data.data : response.data
    const index = printers.value.findIndex(p => p.id === fullPrinter.id)
    if (index !== -1) {
      printers.value[index] = fullPrinter
    }
  } catch (error) {
    console.error('Error fetching updated printer:', error)
    // Fallback to partial update if fetch fails
    const printerData = updatedPrinter.data ? (updatedPrinter.data.data ? updatedPrinter.data.data : updatedPrinter.data) : updatedPrinter
    const index = printers.value.findIndex(p => p.id === printerData.id)
    if (index !== -1) {
      printers.value[index] = { ...printers.value[index], ...printerData }
    }
  }
  closeEditModal()
}

// Gestion des modals d'ajout
const openAddModal = () => {
  isAddModalOpen.value = true
}

const closeAddModal = () => {
  isAddModalOpen.value = false
}

const handleAdd = async (newPrinter) => {
  try {
    // Fetch the full printer data with relations (cash_register, point_of_sale)
    const response = await printerService.getById(newPrinter.id)
    const fullPrinter = response.data.data ? response.data.data : response.data
    printers.value = [fullPrinter, ...printers.value]
  } catch (error) {
    console.error('Error fetching new printer:', error)
    // Fallback to adding the provided data
    printers.value = [newPrinter, ...printers.value]
  }
  closeAddModal()
}

// Gestion de la suppression
const confirmDelete = (printer) => {
  printerToDelete.value = printer
  isDeleteConfirmOpen.value = true
}

const closeDeleteConfirm = () => {
  isDeleteConfirmOpen.value = false
  printerToDelete.value = null
}

const deletePrinter = async () => {
  if (!printerToDelete.value) return
  isDeleting.value = true
  deleteError.value = ''
  try {
    await printerService.delete(printerToDelete.value.id)
    printers.value = printers.value.filter(p => p.id !== printerToDelete.value.id)
    closeDeleteConfirm()
  } catch (error) {
    console.error('Erreur lors de la suppression:', error.response?.data || error)
    deleteError.value = error.response?.data?.message || 'Erreur lors de la suppression de l\'imprimante'
  } finally {
    isDeleting.value = false
  }
}
</script>

<style scoped>
.printer-layout {
  min-height: calc(100vh - 5rem);
  min-height: calc(100dvh - 5rem);
}

@media (min-width: 1024px) {
  .printer-layout {
    height: calc(100vh - 5.5rem);
    height: calc(100dvh - 5.5rem);
    max-height: calc(100vh - 5.5rem);
    max-height: calc(100dvh - 5.5rem);
    overflow: hidden;
  }
}

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
