<template>
  <section class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Rapports</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <FontAwesomeIcon :icon="faReceipt" class="text-indigo-500" />
          Historique des Ventes
        </h1>
        <p class="mt-2 text-sm text-slate-500">Consultez, filtrez et gérez vos transactions.</p>
      </div>
      <div v-if="filteredSales.length" class="flex items-center gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
          <span class="text-lg font-bold text-emerald-600">{{ totalAmount }} Ar</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 shadow-sm">
          {{ filteredSales.length }} vente{{ filteredSales.length > 1 ? 's' : '' }}
        </div>
      </div>
    </header>

    <!-- Filtres (inchangés) -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-4 mb-8">
      <div class="flex items-center gap-4 overflow-x-auto">
        <div class="flex-1 min-w-0">
          <label class="block text-xs font-medium text-slate-500 mb-1">Rechercher</label>
          <div class="relative">
            <input v-model="searchQuery" type="text" placeholder="N° ticket, client, produit..."
              class="w-full pl-10 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:border-indigo-500 text-sm" />
            <FontAwesomeIcon :icon="faSearch" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          </div>
        </div>
        <div class="w-44 flex-shrink-0">
          <label class="block text-xs font-medium text-slate-500 mb-1">Point de vente</label>
          <div class="w-full py-2 px-3 bg-slate-100 border border-slate-300 rounded-xl text-slate-700 text-sm">
            {{ activePos?.name || 'Aucun point de vente' }}
          </div>
        </div>
        <div class="w-36 flex-shrink-0">
          <label class="block text-xs font-medium text-slate-500 mb-1">Période</label>
          <select v-model="periodFilter" @change="applyPeriodFilter" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            <option value="">Toutes</option>
            <option value="today">Aujourd’hui</option>
            <option value="thisWeek">Cette semaine</option>
            <option value="thisMonth">Ce mois</option>
          </select>
        </div>
        <div class="w-44 flex-shrink-0">
          <label class="block text-xs font-medium text-slate-500 mb-1">Session</label>
          <select v-model="sessionFilter" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            <option value="">Toutes les sessions</option>
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.cash_register?.name || s.id }} - {{ s.user?.name || '' }}</option>
          </select>
        </div>
        <!-- Nouveau filtre : Statut Session -->
        <div class="w-44 flex-shrink-0">
          <label class="block text-xs font-medium text-slate-500 mb-1">Statut Session</label>
          <select v-model="sessionStatusFilter" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-sm">
            <option value="">Toutes</option>
            <option value="open">Ouvertes</option>
            <option value="closed">Fermées</option>
          </select>
        </div>
        <div class="flex gap-3 flex-shrink-0">
          <div class="w-36">
            <label class="block text-xs font-medium text-slate-500 mb-1">Du</label>
            <input type="date" v-model="startDate" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-sm" />
          </div>
          <div class="w-36">
            <label class="block text-xs font-medium text-slate-500 mb-1">Au</label>
            <input type="date" v-model="endDate" class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-sm" />
          </div>
        </div>
      </div>
    </div>

    <!-- États -->
    <div v-if="loading" class="text-center py-20">Chargement des ventes...</div>
    <div v-else-if="loadError" class="bg-red-50 text-red-700 p-8 rounded-3xl text-center">{{ loadError }}</div>
    <div v-else-if="filteredSales.length === 0" class="bg-white rounded-3xl p-16 text-center text-slate-500">
      Aucune vente trouvée.
    </div>

    <!-- Liste des ventes (Groupées par caissier) -->
    <div v-else class="space-y-6">
      <div v-for="group in salesByCashier" :key="group.id" class="bg-slate-50/50 rounded-3xl border border-slate-200 overflow-hidden">
        <!-- Header du Groupe (Caissier) -->
        <div 
          @click="toggleCashier(group.id)"
          class="px-6 py-4 flex items-center justify-between cursor-pointer hover:bg-slate-100/80 transition-all"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 shadow-sm flex items-center justify-center text-indigo-600">
              <FontAwesomeIcon :icon="faUser" class="text-lg" />
            </div>
            <div>
              <h3 class="font-bold text-slate-900 text-lg">{{ group.name }}</h3>
              <p class="text-xs font-medium text-slate-500">{{ group.sales.length }} vente{{ group.sales.length > 1 ? 's' : '' }} au total</p>
            </div>
          </div>
          
          <div class="flex items-center gap-8">
            <div class="text-right">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Chiffre d'affaires</p>
              <p class="font-black text-xl text-indigo-600">{{ formatPrice(group.total) }}</p>
            </div>
            <div class="w-8 h-8 rounded-full flex items-center justify-center bg-white border border-slate-200 text-slate-400 shadow-sm">
              <FontAwesomeIcon :icon="expandedCashiers.has(group.id) ? faChevronUp : faChevronDown" />
            </div>
          </div>
        </div>

        <!-- Liste des ventes du caissier -->
        <div v-if="expandedCashiers.has(group.id)" class="p-4 space-y-4 bg-white border-t border-slate-200">
          <article v-for="sale in group.sales" :key="sale.id" class="bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-sm transition-all">
            <header class="px-4 py-3 flex items-center justify-between cursor-pointer hover:bg-slate-50" @click="toggleSale(sale.id)">
              <div class="flex items-center gap-4">
                <div class="bg-indigo-50 text-indigo-700 font-mono font-bold text-lg px-3 py-1.5 rounded-xl border border-indigo-100">
                  #{{ sale.sale_number || sale.ticket_number || 'N/A' }}
                </div>
                <div>
                  <p class="text-xs text-slate-500">Date & Heure</p>
                  <p class="font-medium text-sm">{{ formatDate(sale.created_at) }}</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="text-right">
                  <p class="text-2xl font-bold text-emerald-600">{{ formatPrice(sale.final_amount || sale.total_amount) }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <div :class="['status-badge', statusClass(sale.status)]">
                    <FontAwesomeIcon :icon="statusIcon(sale.status)" class="mr-1" />
                    {{ formatStatus(sale.status) }}
                  </div>
                  <div class="flex gap-1">
                    <button @click.stop="editSale(sale)" class="p-2 hover:bg-slate-100 rounded-lg transition">
                      <FontAwesomeIcon :icon="faPenToSquare" />
                    </button>
                    <button @click.stop="deleteSale(sale.id)" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition">
                      <FontAwesomeIcon :icon="faTrash" />
                    </button>
                    <button @click.stop="toggleSale(sale.id)" class="p-2 hover:bg-slate-100 rounded-lg transition">
                      <FontAwesomeIcon :icon="expandedSales.has(sale.id) ? faChevronUp : faChevronDown" />
                    </button>
                  </div>
                </div>
              </div>
            </header>

            <transition name="expand">
              <div v-if="expandedSales.has(sale.id)" class="border-t border-slate-100 bg-slate-50 px-6 py-6">
                <div v-if="getOrderLines(sale).length" class="overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="border-b border-slate-200">
                        <th class="text-left py-2 font-medium text-slate-500">Produit</th>
                        <th class="text-center py-2 font-medium text-slate-500">Quantité</th>
                        <th class="text-right py-2 font-medium text-slate-500">Prix Unitaire</th>
                        <th class="text-right py-2 font-medium text-slate-500">Total</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="(line, index) in getOrderLines(sale)" :key="index">
                        <td class="py-3 font-medium">{{ line.product?.name || line.name || 'Produit inconnu' }}</td>
                        <td class="py-3 text-center font-medium">{{ line.quantity }}</td>
                        <td class="py-3 text-right">{{ formatPrice(line.price) }}</td>
                        <td class="py-3 text-right font-semibold">{{ formatPrice(line.total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-else class="text-slate-500 italic py-6">Aucun détail de produit disponible</p>
              </div>
            </transition>
          </article>
        </div>
      </div>
    </div>

    <!-- Modal d'édition -->
    <EditSaleModal v-if="showEditModal && selectedSale" :sale="selectedSale" @save="onSaleUpdated" @close="closeEditModal" />
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { storage } from '@/utils/storage'
import { useAuth } from '@/composables/useAuth'
import apiClient from '@/services/apiClient'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSearch, faPenToSquare, faTrash, faChevronUp, faChevronDown, faCheckCircle, faTimesCircle, faClock, faUser, faReceipt } from '@fortawesome/free-solid-svg-icons'
import { API_BASE_URL } from '@/utils/api'
import EditSaleModal from './EditSaleModal.vue'

const { isAdmin, activePos } = useAuth()

// États
const sales = ref([])
const loading = ref(false)
const loadError = ref(null)
const searchQuery = ref('')
const pointOfSaleFilter = ref('')
const periodFilter = ref('')
const startDate = ref('')
const endDate = ref('')
const expandedSales = ref(new Set())
const expandedCashiers = ref(new Set()) // Pour l'accordéon par caissier
const pointOfSales = ref([])
const sessions = ref([])
const sessionFilter = ref('')
const sessionStatusFilter = ref('') // Nouveau filtre
const showEditModal = ref(false)
const selectedSale = ref(null)

// Computed
const filteredSales = computed(() => {
  let result = sales.value
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(sale =>
      (sale.sale_number || sale.ticket_number || '').toLowerCase().includes(q) ||
      (sale.customer?.name || '').toLowerCase().includes(q) ||
      getOrderLines(sale).some(line => (line.product?.name || line.name || '').toLowerCase().includes(q))
    )
  }
  // Pour les non-admin, filtrer uniquement par le POS actif
  if (!isAdmin.value && activePos.value?.id) {
    result = result.filter(sale => sale.point_of_sale_id === activePos.value.id)
  } else if (pointOfSaleFilter.value) {
    result = result.filter(sale => sale.point_of_sale_id === parseInt(pointOfSaleFilter.value))
  }
  if (sessionFilter.value) {
    result = result.filter(sale => {
      const sid = sale.cash_register_session_id ?? sale.cash_register_session?.id
      return sid == parseInt(sessionFilter.value)
    })
  }
  // Logique du nouveau filtre
  if (sessionStatusFilter.value) {
    result = result.filter(sale => {
      const isClosed = sale.cash_register_session?.is_closed ?? false
      return sessionStatusFilter.value === 'closed' ? isClosed : !isClosed
    })
  }
  if (startDate.value) {
    result = result.filter(sale => sale.created_at?.split('T')[0] >= startDate.value)
  }
  if (endDate.value) {
    result = result.filter(sale => sale.created_at?.split('T')[0] <= endDate.value)
  }
  if (periodFilter.value) {
    const now = new Date()
    const today = now.toISOString().split('T')[0]
    const weekAgo = new Date(now.setDate(now.getDate() - 7)).toISOString().split('T')[0]
    const monthAgo = new Date(now.setMonth(now.getMonth() - 1)).toISOString().split('T')[0]
    switch (periodFilter.value) {
      case 'today': result = result.filter(s => s.created_at?.split('T')[0] === today); break
      case 'thisWeek': result = result.filter(s => s.created_at?.split('T')[0] >= weekAgo); break
      case 'thisMonth': result = result.filter(s => s.created_at?.split('T')[0] >= monthAgo); break
    }
  }
  return result
})

// Nouveau: Groupement par caissier
const salesByCashier = computed(() => {
  const groups = {}
  filteredSales.value.forEach(sale => {
    const userId = sale.user?.id || 0
    const userName = sale.user?.name || 'Vendeur Inconnu'
    
    if (!groups[userId]) {
      groups[userId] = {
        id: userId,
        name: userName,
        sales: [],
        total: 0
      }
    }
    
    groups[userId].sales.push(sale)
    if (sale.status !== 'pending' && sale.status !== 'cancelled') {
      const amount = Number(sale.final_amount ?? sale.total_amount ?? 0)
      groups[userId].total += isNaN(amount) ? 0 : amount
    }
  })
  
  // Trier les groupes par nom de caissier
  return Object.values(groups).sort((a, b) => a.name.localeCompare(b.name))
})

const totalAmount = computed(() =>
  filteredSales.value.reduce((sum, sale) => {
    // Exclure les ventes en attente du calcul
    if (sale.status === 'pending') return sum;

    const amount = Number(sale.final_amount ?? sale.total_amount ?? 0);
    return sum + (isNaN(amount) ? 0 : amount);
  }, 0)
);

// Méthodes utilitaires
const formatPrice = (price) => Number(price || 0).toLocaleString('fr-FR') + ' Ar'
const formatDate = (date) => date ? new Date(date).toLocaleString('fr-FR') : ''
const getOrderLines = (sale) => sale.orderlines || sale.items || sale.products || []
const statusClass = (status) => {
  if (status === 'completed' || status === 'paid') return 'status-completed'
  if (status === 'cancelled') return 'status-cancelled'
  return 'status-pending'
}
const statusIcon = (status) => {
  if (status === 'completed' || status === 'paid') return faCheckCircle
  if (status === 'cancelled') return faTimesCircle
  return faClock
}
const formatStatus = (status) => {
  if (status === 'completed') return 'Terminée'
  if (status === 'paid') return 'Payée'
  if (status === 'cancelled') return 'Annulée'
  return 'En attente'
}
const toggleSale = (id) => {
  if (expandedSales.value.has(id)) expandedSales.value.delete(id)
  else expandedSales.value.add(id)
}
const toggleCashier = (id) => {
  if (expandedCashiers.value.has(id)) expandedCashiers.value.delete(id)
  else expandedCashiers.value.add(id)
}
const applyPeriodFilter = () => { /* géré par le computed */ }

// Chargement des ventes
const loadSales = async () => {
  loading.value = true
  loadError.value = null
  try {
    const response = await apiClient.get('/sales')
    sales.value = response.data.data || response.data || []
  } catch (err) {
    console.error(err)
    loadError.value = "Impossible de charger les ventes."
  } finally {
    loading.value = false
  }
}

const loadPointOfSales = async () => {
  try {
    const response = await apiClient.get('/point-of-sales')
    pointOfSales.value = response.data.data || response.data || []
  } catch (err) {
    console.error(err)
  }
}

const loadSessions = async () => {
  try {
    const response = await apiClient.get('/cash-register-sessions')
    sessions.value = response.data.data || response.data || []
  } catch (err) {
    console.error('Failed to load sessions', err)
  }
}

// Gestion du modal
const editSale = (sale) => {
  selectedSale.value = sale
  showEditModal.value = true
}

const closeEditModal = () => {
  showEditModal.value = false
  selectedSale.value = null
}

// Mise à jour après sauvegarde du modal : recharge toutes les ventes
const onSaleUpdated = async () => {
  closeEditModal()
  await loadSales()  // ← recharge les données fraîches depuis l'API
}

const deleteSale = async (saleId) => {
  if (!confirm("Supprimer définitivement cette vente ?")) return
  try {
    const auth = storage.getAuth()
    await axios.delete(`${API_BASE_URL}/sales/${saleId}`, {
      headers: { Authorization: `Bearer ${auth?.token}` }
    })
    await loadSales()  // recharge après suppression
  } catch (err) {
    console.error(err)
    alert("Erreur lors de la suppression")
  }
}

onMounted(() => {
  loadSales()
  if (isAdmin.value) {
    loadPointOfSales()
  }
  loadSessions()
})
</script>

<style scoped>
.status-badge {
  @apply inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold;
}
.status-completed { @apply bg-emerald-100 text-emerald-700; }
.status-pending { @apply bg-amber-100 text-amber-700; }
.status-cancelled { @apply bg-rose-100 text-rose-700; }
.expand-enter-active, .expand-leave-active { transition: all 0.2s ease; }
.expand-enter-from, .expand-leave-to { opacity: 0; transform: translateY(-10px); }
</style>
