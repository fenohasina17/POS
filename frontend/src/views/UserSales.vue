<template>
  <div :class="embedded ? 'user-sales-embedded' : 'user-sales-view'">
    <Profile v-if="!embedded" />

<div class="user-sales-layout grid gap-2 lg:grid-cols-[minmax(0,1fr)_600px]">
      <!-- Tableau des ventes -->
      <section
        class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-400 bg-white p-2 shadow-sm"
      >
        <div class="flex items-center justify-between border-b border-slate-100 pb-1">
          <h1 class="text-sm font-semibold text-slate-800">Mes ventes</h1>
          <span class="text-[10px] font-semibold text-slate-400">{{ total }} total</span>
        </div>

        <div class="mt-1 flex-1 overflow-hidden">
          <div
            v-if="loading"
            class="flex h-full flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center text-xs text-slate-500"
          >
            <span class="h-8 w-8 animate-spin rounded-full border-3 border-slate-200 border-t-indigo-500"></span>
            <p class="mt-2 font-medium">Chargement...</p>
          </div>

          <template v-else>
            <div
              v-if="!sales.length"
              class="flex h-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 text-xs text-slate-500"
            >
              Aucune vente trouvée
            </div>

            <div v-else class="flex h-full flex-col overflow-hidden">
              <div class="flex-1">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                  <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    <tr>
                      <th class="px-2 py-1.5 text-left">Date</th>
                      <th class="px-2 py-1.5 text-left">Ticket</th>
                      <th class="px-2 py-1.5 text-left">Montant</th>
                      <th class="px-2 py-1.5 text-left">Statut</th>
                      <th class="px-2 py-1.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr
                      v-for="sale in sales"
                      :key="sale.id"
                      @click="selectSale(sale)"
                      :class="[
                        'cursor-pointer transition-all duration-150 hover:bg-indigo-50/60',
                        selectedSale?.id === sale.id
                          ? 'bg-indigo-100 shadow-inner ring-1 ring-indigo-200'
                          : 'bg-white',
                      ]"
                    >
                      <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ formatDate(sale.created_at) }}</td>
                      <td class="px-2 py-1.5 font-mono text-slate-600">{{ sale.sale_number || sale.ticket_number }}</td>
                      <td class="px-2 py-1.5 font-semibold text-slate-800">
                        {{ formatPrice(sale.total_amount) }}
                      </td>
                      <td class="px-2 py-1.5">
                        <span
                          :class="[
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize',
                            statusClass(sale.status),
                          ]"
                        >
                          <i :class="getStatusIcon(sale.status)" class="text-[9px]"></i>
                          {{ formatStatus(sale.status) }}
                        </span>
                      </td>
                      <td class="px-2 py-1.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                          <button
                            type="button"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                            @click.stop="printDuplicateSale(sale)"
                            aria-label="Réimprimer"
                          >
                            <FontAwesomeIcon icon="fa-solid fa-print" class="text-xs" />
                          </button>
                          <button
                            type="button"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                            @click.stop="openEditModal(sale)"
                            aria-label="Modifier"
                            v-if="isAdmin"
                          >
                            <FontAwesomeIcon icon="fa-solid fa-pen" class="text-xs" />
                          </button>
                          <button
                            type="button"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-rose-200 hover:text-rose-600"
                            @click.stop="confirmDeleteSale(sale)"
                            aria-label="Supprimer"
                            v-if="isAdmin"
                          >
                            <FontAwesomeIcon icon="fa-solid fa-trash" class="text-xs" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination compacte -->
              <div class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2">
                <div class="text-[10px] text-slate-500">
                  Page {{ currentPage }} / {{ lastPage }}
                </div>
                <div class="flex gap-0.5">
                  <button
                    type="button"
                    class="rounded-md border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                    :disabled="currentPage <= 1"
                    @click="changePage(currentPage - 1)"
                  >
                    ←
                  </button>
                  <button
                    type="button"
                    class="rounded-md border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                    :disabled="currentPage >= lastPage"
                    @click="changePage(currentPage + 1)"
                  >
                    →
                  </button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </section>

      <!-- Détails de la vente (compact) -->
      <aside
        class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-sm"
      >
        <div class="border-b border-slate-100 pb-1">
          <h2 class="text-sm font-semibold text-slate-800">Détails</h2>
          <p class="text-[10px] text-slate-400">
            {{ selectedSale ? 'Ticket ' + selectedSale.ticket_number : 'Sélectionnez une vente' }}
          </p>
        </div>

        <div class="mt-1 flex-1 overflow-hidden">
          <div
            v-if="!selectedSale"
            class="flex h-full flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 text-center text-xs text-slate-500"
          >
            <FontAwesomeIcon icon="fa-solid fa-receipt" class="mb-1 text-base" />
            Choisissez une vente
          </div>

          <div v-else class="flex h-full flex-col overflow-hidden">
            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-2 text-xs text-slate-600">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-slate-800">Total</span>
                <span class="text-indigo-600">{{ formatPrice(selectedSale.total_amount) }}</span>
              </div>
              <div class="mt-1 flex items-center justify-between text-[10px] text-slate-400">
                <span>Statut</span>
                <span class="rounded-full bg-indigo-50 px-1.5 py-0.5 text-[9px] font-semibold text-indigo-600">
                  {{ formatStatus(selectedSale.status) }}
                </span>
              </div>
              <div class="mt-1 flex items-center justify-between text-[10px] text-slate-400">
                <span>Créé le</span>
                <span>{{ formatDate(selectedSale.created_at) }}</span>
              </div>
            </div>

            <div class="mt-2 flex-1 overflow-hidden">
              <div class="flex h-full flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 pb-1">
                  <h3 class="text-xs font-semibold text-slate-700">Articles</h3>
                  <span class="text-[9px] text-slate-400">
                    {{ selectedSale.order_lines?.length || 0 }}
                  </span>
                </div>

                <div v-if="selectedSale.order_lines?.length" class="flex-1 overflow-y-auto text-[10px]">
                  <div class="divide-y divide-slate-100">
                    <div class="grid grid-cols-4 gap-1 px-1 py-1 text-[9px] font-semibold text-slate-400">
                      <div>Produit</div>
                      <div>Qté</div>
                      <div>Prix</div>
                      <div class="text-right">Total</div>
                    </div>
                    <div
                      v-for="line in selectedSale.order_lines"
                      :key="line.id"
                      class="grid grid-cols-4 gap-1 px-1 py-1 text-[10px]"
                    >
                      <div class="truncate font-semibold text-slate-800">
                        {{ line.product?.name || 'Supprimé' }}
                      </div>
                      <div class="text-slate-500">{{ line.quantity }}</div>
                      <div class="text-slate-500">{{ formatPrice(line.price) }}</div>
                      <div class="text-right font-semibold text-indigo-600">
                        {{ formatPrice(line.total) }}
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="flex flex-1 items-center justify-center text-[9px] text-slate-400">
                  Aucun article
                </div>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <EditSaleModal
      v-if="showEditModal"
      :sale="selectedSale"
      @save="saveSale"
      @close="closeEditModal"
    />
  </div>
</template>

<script setup>
// (le script reste identique, aucun changement nécessaire)
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/services/apiClient'
import { API_BASE_URL } from '@/utils/api'
import EditSaleModal from './EditSaleModal.vue'
import Profile from './Profile.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPen, faReceipt, faTrash } from '@fortawesome/free-solid-svg-icons'
import { useAuth } from '@/composables/useAuth'
import { printingService } from '@/services/printing/PrintingService'

library.add(faPen, faReceipt, faTrash)

const props = defineProps({
  embedded: { type: Boolean, default: false },
})
const embedded = computed(() => props.embedded)

const { loadUserData } = useAuth()

// États
const allSales = ref([])
const sales = ref([])
const loading = ref(true)
const selectedSale = ref(null)
const showEditModal = ref(false)
const sessionId = ref(null)

// Pagination front
const currentPage = ref(1)
const itemsPerPage = 10
const lastPage = ref(1)
const total = ref(0)

// Headers API
const fetchAllSales = async () => {
  try {
    const userRaw = localStorage.getItem('user')
    const user = userRaw ? JSON.parse(userRaw) : null
    if (!user?.id) return []

    const params = {
      cash_register_session_id: sessionId.value,
      user_id: user.id,
    }
    const { data } = await apiClient.get('/sales', {
      params,
    })

    let items = []
    if (Array.isArray(data)) items = data
    else if (data?.data && Array.isArray(data.data)) items = data.data
    else if (data?.items) items = data.items
    return items
  } catch (error) {
    console.error('Erreur chargement ventes:', error)
    return []
  }
}

const updateDisplayedSales = () => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  sales.value = allSales.value.slice(start, end)
  lastPage.value = Math.ceil(allSales.value.length / itemsPerPage) || 1
  total.value = allSales.value.length
}

const loadSales = async () => {
  loading.value = true
  allSales.value = await fetchAllSales()
  currentPage.value = 1
  updateDisplayedSales()
  loading.value = false
}

const changePage = (newPage) => {
  if (newPage < 1 || newPage > lastPage.value) return
  currentPage.value = newPage
  updateDisplayedSales()
}

const fetchCurrentSession = async () => {
  try {
    const { data } = await apiClient.get('/my-active-session')
    return data?.data || data || null
  } catch (error) {
    console.error('Erreur session:', error)
    return null
  }
}

const fetchSaleDetails = async (saleId) => {
  try {
    const { data } = await apiClient.get(`/sales/${saleId}`)
    return data?.data || data
  } catch (error) {
    console.error('Erreur détails vente:', error)
    return null
  }
}

const selectSale = async (sale) => {
  selectedSale.value = null // Réinitialisation pour déclencher la réactivité
  const details = await fetchSaleDetails(sale.id)

  if (details) {
    // Gestion robuste : on accepte order_lines ou orderlines
    const lines = details.order_lines || details.orderlines || []
    selectedSale.value = { ...sale, order_lines: lines }
  } else {
    selectedSale.value = { ...sale, order_lines: [] }
  }
}

const openEditModal = (sale) => {
  selectedSale.value = sale
  showEditModal.value = true
}
const closeEditModal = () => {
  showEditModal.value = false
  selectedSale.value = null
}

const confirmDeleteSale = (sale) => {
  if (confirm(`Supprimer la vente n°${sale.sale_number || sale.ticket_number} ?`)) {
    deleteSale(sale)
  }
}

const printDuplicateSale = async (sale) => {
  // On s'assure d'avoir les détails (au cas où on clique sans avoir cliqué sur la ligne avant)
  let saleToPrint = sale;
  if (!sale.order_lines) {
    const details = await fetchSaleDetails(sale.id);
    saleToPrint = details ? { ...sale, order_lines: details.order_lines || [] } : sale;
  }

  const invoiceData = {
    companyName: 'INTERNATIONAL GASTRONOMY PIZZA',
    address: 'Antananarivo, Madagascar',
    number: saleToPrint.sale_number || saleToPrint.ticket_number || 'DUP-' + saleToPrint.id,
    date: formatDate(saleToPrint.created_at),
    items: (saleToPrint.order_lines || []).map(line => ({
      name: line.product?.name || 'Article',
      price: line.price,
      quantity: line.quantity
    })),
    total: saleToPrint.total_amount,
    client: 'Client'
  }

  try {
    await printingService.printInvoice(invoiceData)
  } catch (error) {
    console.error('Erreur impression duplicata:', error)
  }
}

const deleteSale = async (sale) => {
  try {
    await apiClient.delete(`/sales/${sale.id}`)
    await loadSales()
    if (selectedSale.value?.id === sale.id) selectedSale.value = null
  } catch (error) {
    console.error('Erreur suppression:', error)
    alert('Erreur lors de la suppression')
  }
}

const saveSale = async (updatedSale) => {
  if (!updatedSale?.id) {
    closeEditModal()
    return
  }
  try {
    const orderLines = updatedSale.orderlines || updatedSale.order_lines || []
    const subtotal = orderLines.reduce((sum, line) => sum + (line.quantity * line.price), 0)
    const discountPercent = Number(updatedSale.discount_percentage) || 0
    const totalAmount = Math.round(subtotal)
    const finalAmount = Math.round(subtotal * (100 - discountPercent) / 100)

    const payload = {
      status: updatedSale.status || 'pending',
      discount_percentage: discountPercent,
      total_amount: totalAmount,
      final_amount: finalAmount,
      items: orderLines.map(line => ({
        product_id: line.product?.id,
        quantity: line.quantity,
        price: Math.round(Number(line.price) || 0),
        total: Math.round((line.quantity || 0) * (line.price || 0))
      })).filter(item => item.product_id)
    }

    await apiClient.put(`/sales/${updatedSale.id}`, payload)

    await loadSales()
    if (selectedSale.value?.id === updatedSale.id) {
      const refreshed = await fetchSaleDetails(updatedSale.id)
      if (refreshed) selectedSale.value = refreshed
    }
  } catch (error) {
    console.error('Erreur mise à jour:', error)
  }
  closeEditModal()
}

const formatPrice = (price) => {
  const val = Number.parseFloat(price)
  if (!Number.isFinite(val)) return '—'
  return `${val.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} Ar`
}
const formatDate = (dateString) => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return isNaN(date.getTime()) ? '—' : date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}
const getStatusIcon = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'completed': case 'paid': return 'fas fa-circle-check'
    case 'cancelled': case 'refund': return 'fas fa-circle-xmark'
    default: return 'fas fa-clock'
  }
}
const statusClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'completed': case 'paid': return 'bg-emerald-50 text-emerald-600'
    case 'cancelled': case 'refund': return 'bg-rose-50 text-rose-600'
    default: return 'bg-slate-100 text-slate-500'
  }
}
const formatStatus = (status) => {
  if (!status) return 'En attente'
  const labels = { completed: 'Terminée', paid: 'Payée', pending: 'En attente', cancelled: 'Annulée', refund: 'Remboursée' }
  return labels[status.toLowerCase()] || status
}

onMounted(async () => {
  loading.value = true
  await loadUserData()
  const session = await fetchCurrentSession()
  if (session?.id) {
    sessionId.value = session.id
    await loadSales()
    if (sales.value.length) {
      const firstSale = sales.value[0]
      const details = await fetchSaleDetails(firstSale.id)
      selectedSale.value = details ? { ...firstSale, order_lines: details.order_lines || [] } : { ...firstSale, order_lines: [] }
    }
  } else {
    loading.value = false
  }
})
</script>

<style scoped>
.user-sales-view {
  min-height: 1000vh;
  padding: 1.5rem 1rem;
  background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 100%);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.user-sales-embedded {
  min-height: 0;
}
.user-sales-layout {
  width: 100%;
max-width: 1100px;
  margin: 0 auto;
}
@media (max-width: 768px) {
  .user-sales-layout {
    max-width: 100%;
  }
}
.user-sales-embedded .user-sales-layout {
  max-width: none;
}

@media (max-width: 768px) {
  .user-sales-view {
    padding: 1rem;
  }
}
</style>
