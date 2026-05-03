<template>
  <div class="table-sale-view min-h-screen bg-slate-50/50 p-4">
    <Profile v-if="!embedded" class="mb-4" />

    <!-- HEADER PREMIUM STICKY -->
    <header class="sticky top-2 z-30 mb-4 overflow-hidden rounded-3xl border border-white bg-white/80 p-3 shadow-lg shadow-slate-200/50 backdrop-blur-md">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md shadow-slate-200">
            <FontAwesomeIcon icon="fa-solid fa-table" class="text-base" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-lg font-black text-slate-800 leading-tight">
                {{ selectedTable ? `Table ${selectedTable.table_number}` : 'Sélectionner une table' }}
              </h1>
              <span 
                v-if="selectedTable"
                class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-widest shadow-sm"
                :class="statusBadgeClass(selectedTable.status)"
              >
                {{ getStatusText(selectedTable.status) }}
              </span>
            </div>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">
              {{ selectedTable?.name || 'Service en cours' }} • {{ selectedTable?.point_of_sale?.name || 'POS Principal' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="hidden xs:flex items-center gap-4 pr-4 border-r border-slate-100">
            <div class="text-right">
              <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Articles</p>
              <p class="text-base font-black text-slate-800">{{ cart.length }}</p>
            </div>
            <div class="text-right">
              <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none">Total</p>
              <p class="text-base font-black text-indigo-600">{{ formatPrice(displayTotal) }}</p>
            </div>
          </div>
          
          <button
            @click="openTableSelector"
            class="flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 transition-all hover:bg-slate-200 active:scale-95"
          >
            <FontAwesomeIcon icon="fa-solid fa-table-list" />
            <span>Changer</span>
          </button>
          <button
            v-if="currentPendingOrder"
            @click="printBill"
            class="flex items-center gap-2 rounded-xl bg-indigo-500 px-3 py-2 text-xs font-bold text-white transition-all hover:bg-indigo-600 active:scale-95"
          >
            <FontAwesomeIcon icon="fa-solid fa-receipt" />
            <span>Imprimer l'Addition</span>
          </button>
        </div>
      </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
      <!-- SECTION PRODUITS -->
      <section class="flex min-h-0 flex-col overflow-hidden rounded-[2rem] border border-white bg-white/80 backdrop-blur-md p-6 shadow-xl shadow-slate-200/50">
        <div class="flex flex-col gap-5 border-b border-slate-100 pb-6">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-xl font-black text-slate-800">Menu Digital</h2>
            
            <div class="relative w-full sm:max-w-xs group">
              <FontAwesomeIcon
                icon="fa-solid fa-search"
                class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-indigo-500"
              />
              <input
                type="text"
                placeholder="Rechercher un produit..."
                v-model="searchQuery"
                class="w-full rounded-2xl border-none bg-slate-100/80 py-3 pl-11 pr-4 text-sm text-slate-700 shadow-inner outline-none transition-all focus:bg-white focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>
          </div>

          <!-- Catégories Style Grid -->
          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
            <button
              class="rounded-xl px-2 py-3 text-[10px] font-black uppercase tracking-widest transition-all active:scale-95 border shadow-sm"
              :class="activeCategoryId === null ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-500 border-slate-100 hover:bg-slate-50'"
              @click="activeCategoryId = null"
            >
              Tous
            </button>
            <button
              v-for="category in categories"
              :key="category.id"
              class="rounded-xl px-2 py-3 text-[10px] font-black uppercase tracking-widest transition-all active:scale-95 border shadow-sm"
              :class="activeCategoryId === category.id 
                ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-500 ring-offset-2' 
                : 'bg-white text-slate-600 border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/30'"
              @click="activeCategoryId = category.id"
            >
              {{ category.name }}
            </button>
          </div>
        </div>

        <!-- Grid Produits -->
        <div class="mt-6 flex-1 overflow-y-auto pr-2 scrollbar-thin">
          <div v-if="loadingProducts" class="flex h-64 items-center justify-center">
             <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-100 border-t-indigo-600"></div>
          </div>
          
          <div v-else-if="filteredProducts.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            <button
              v-for="product in filteredProducts"
              :key="product.id"
              class="group relative flex flex-col items-center rounded-3xl border border-slate-100 bg-white p-3 text-center transition-all duration-300 hover:border-indigo-100 hover:shadow-2xl hover:shadow-indigo-100/50 active:scale-95 disabled:opacity-30 disabled:grayscale"
              :disabled="isInteractionLocked"
              @click="addToCart(product)"
            >
              <div class="aspect-square w-full overflow-hidden rounded-2xl bg-slate-50">
                <img :src="getProductImageUrl(product)" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" @error="handleImageError" />
              </div>
              <div class="mt-3 space-y-1">
                <p class="truncate text-sm font-bold text-slate-800">{{ product.name }}</p>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ product.category_name }}</p>
                <span class="inline-block rounded-lg bg-indigo-50 px-2 py-1 text-[11px] font-black text-indigo-600">
                  {{ formatPrice(product.price) }}
                </span>
              </div>
            </button>
          </div>
        </div>
      </section>

      <!-- SIDEBAR PANIER & COMMANDE (Compactée - Mode Clair) -->
      <aside class="flex flex-col gap-4">
        <!-- Commande en Attente (Si existe) -->
        <div v-if="currentPendingOrder" class="rounded-[2rem] border border-amber-100 bg-amber-50/50 p-4 shadow-xl shadow-amber-900/5">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="flex items-center gap-2 text-[10px] font-black text-amber-800 uppercase tracking-widest">
              <FontAwesomeIcon icon="fa-solid fa-clock" />
              DÉJÀ COMMANDÉ
            </h3>
            <span class="rounded-lg bg-amber-100 px-2 py-0.5 text-[9px] font-black text-amber-600">
              #{{ currentPendingOrder.id }}
            </span>
          </div>
          
          <div class="max-h-40 space-y-1 overflow-y-auto pr-1 scrollbar-thin">
            <div v-for="line in currentPendingOrder.order_lines" :key="line.id" class="flex items-center justify-between rounded-xl bg-white/60 p-2 border border-amber-100">
              <span class="text-xs font-bold text-amber-900 leading-tight">
                <span class="text-amber-500">{{ line.quantity }}x</span> {{ line.product?.name }}
              </span>
              <span class="text-xs font-black text-amber-700 whitespace-nowrap ml-2">{{ formatPrice(line.price * line.quantity) }}</span>
            </div>
          </div>
        </div>

        <!-- Panier Actuel (Nouveaux Articles - Mode Clair Compact) -->
        <div class="flex flex-1 flex-col overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white p-5 shadow-2xl shadow-slate-200/50">
          <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-base font-black text-slate-800 tracking-tight">Nouvelle sélection</h2>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ cart.length }} articles</p>
            </div>
            <button v-if="cart.length" @click="clearCart" class="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:text-rose-500 transition-colors">
              <FontAwesomeIcon icon="fa-solid fa-trash" class="text-xs" />
            </button>
          </div>

          <div class="flex-1 space-y-1 overflow-y-auto pr-1 scrollbar-hide">
            <div v-for="item in cart" :key="item.id" class="group relative rounded-xl border border-slate-100 bg-slate-50/50 p-2 transition-all hover:bg-white hover:shadow-md">
              <div class="flex items-center gap-2">
                <!-- Nom et Prix Unit -->
                <div class="flex-1 min-w-0">
                  <p class="truncate text-xs font-black text-slate-950 leading-none">{{ item.name }}</p>
                  <p class="text-[9px] font-medium text-slate-400 mt-0.5">{{ formatPrice(item.price) }}</p>
                </div>

                <!-- Qte -->
                <div class="flex items-center gap-1 rounded-lg bg-white p-0.5 shadow-sm border border-slate-100">
                  <button @click="decrementQuantity(item)" class="flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-50 hover:text-slate-900">
                    <FontAwesomeIcon icon="fa-solid fa-minus" class="text-[8px]" />
                  </button>
                  <span class="w-4 text-center text-[10px] font-black text-slate-700">{{ item.quantity }}</span>
                  <button @click="incrementQuantity(item)" class="flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-50 hover:text-slate-900">
                    <FontAwesomeIcon icon="fa-solid fa-plus" class="text-[8px]" />
                  </button>
                </div>

                <!-- Total -->
                <div class="min-w-[65px] text-right">
                  <span class="text-xs font-black text-indigo-600">{{ formatPrice(item.price * item.quantity) }}</span>
                </div>

                <!-- Action -->
                <button @click="removeItem(item)" class="text-slate-300 hover:text-rose-500 transition-colors p-1">
                  <FontAwesomeIcon icon="fa-solid fa-xmark" class="text-[10px]" />
                </button>
              </div>
            </div>
            
            <div v-if="!cart.length" class="flex flex-col items-center justify-center py-6 text-slate-200">
              <FontAwesomeIcon icon="fa-solid fa-shopping-cart" class="text-2xl mb-2 opacity-20" />
              <p class="text-[10px] font-bold uppercase tracking-widest">En attente d'ajout</p>
            </div>
          </div>

          <!-- Footer Action -->
          <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
            <div class="flex justify-between items-end">
              <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Total Salle</span>
              <span class="text-xl font-black text-slate-900 tracking-tight">{{ formatPrice(displayTotal) }}</span>
            </div>

            <div class="grid gap-2">
              <button v-if="cart.length > 0 && !currentPendingOrder" @click="holdOrder" class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 py-3 text-xs font-black text-white transition-all hover:bg-indigo-600 active:scale-95 shadow-xl shadow-slate-200">
                <FontAwesomeIcon icon="fa-solid fa-receipt" />
                ENVOYER EN CUISINE
              </button>

              <button v-if="currentPendingOrder && !isAddingToPending" @click="beginAddToPending" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-50 py-3 text-xs font-black text-indigo-600 border border-indigo-100 transition-all hover:bg-indigo-100 active:scale-95">
                <FontAwesomeIcon icon="fa-solid fa-plus" />
                NOUVELLE TOURNÉE
              </button>

              <button v-if="currentPendingOrder && isAddingToPending" @click="confirmAddToPending" :disabled="!cart.length" class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 py-3 text-xs font-black text-white shadow-lg shadow-emerald-200 transition-all hover:bg-emerald-600 active:scale-95 disabled:opacity-30">
                <FontAwesomeIcon icon="fa-solid fa-check" />
                CONFIRMER L'AJOUT
              </button>

              <button v-if="cart.length > 0 || currentPendingOrder" @click="openPaymentModalDirectly" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3.5 text-xs font-black text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-500 active:scale-95">
                <FontAwesomeIcon icon="fa-solid fa-check-circle" />
                {{ currentPendingOrder ? 'ENCAISSER LA TABLE' : 'ENCAISSER DIRECT' }}
              </button>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- MODALS -->
    <TableSelectorModal :is-open="showTableSelector" @close="closeTableSelector" @table-selected="onTableSelected" />
    <PaymentModal 
      :is-open="isPaymentModalOpen" 
      :total-amount="paymentTotalAmount" 
      :sale-data="paymentSaleData" 
      :sale-id="currentPendingOrder?.id ?? null" 
      @close-modal="handleCloseModal" 
      @payment-success="onPaymentSuccess" 
      @payment-error="onPaymentError"
      @clear-cart="clearCart"
    />
    <InvoiceModal :is-open="isInvoiceModalOpen" :items="invoiceItems" :total="invoiceTotal" :client-name="selectedTable ? `Table ${selectedTable.table_number}` : 'Client'" :invoice-number="currentInvoiceNumber" :payment-method="currentPaymentMethod" :payments="invoicePayments" @close-modal="closeInvoiceModal" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, shallowRef } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import TableSelectorModal from '../components/TableSelectorModal.vue'
import PaymentModal from './PaymentModal.vue'
import InvoiceModal from './InvoiceModal.vue'
import Profile from './Profile.vue'
import placeholderImage from '../assets/avatar.png'
import { useAuth } from '@/composables/useAuth'
import { useCategories } from '@/composables/useCategories'
import { tableService } from '@/services/tableService'
import { printingService } from '@/services/printing/PrintingService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { API_BASE_URL, API_URL } from '@/utils/api'
import { faClock, faReceipt } from '@fortawesome/free-solid-svg-icons'

const props = defineProps({
  tableId: { type: [Number, String], default: null },
  embedded: { type: Boolean, default: false },
})

const router = useRouter()
const { categories, products, loadCategories } = useCategories()

// ========== ÉTATS ==========
const activeCategoryId = ref(null)
const cart = ref([])
const searchQuery = ref('')
const selectedTable = ref(null)
const showTableSelector = ref(false)
const isPaymentModalOpen = ref(false)
const isInvoiceModalOpen = ref(false)
const invoiceItems = ref([])
const invoiceTotal = ref(0)
const invoicePayments = ref([])
const currentInvoiceNumber = ref('')
const currentPaymentMethod = ref('')
const currentPendingOrder = ref(null)
const isAddingToPending = ref(false)
const isProcessing = ref(false)
const loadingProducts = ref(false)

// ========== COMPUTED ==========
const totalPrice = computed(() => cart.value.reduce((sum, item) => sum + item.price * item.quantity, 0))
const displayTotal = computed(() => {
  if (cart.value.length > 0) return totalPrice.value
  return parseFloat(currentPendingOrder.value?.final_amount || currentPendingOrder.value?.total_amount || 0)
})

const filteredProducts = computed(() => {
  let base = products.value
  if (activeCategoryId.value !== null) {
    base = base.filter(p => p.category_id === activeCategoryId.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    base = base.filter(p => p.name.toLowerCase().includes(q))
  }
  return base
})

const isInteractionLocked = computed(() => !!currentPendingOrder.value && !isAddingToPending.value)

// ========== ACTIONS ==========
const addToCart = (product) => {
  if (isInteractionLocked.value) return
  const id = String(product.id || product.product_id)
  const existing = cart.value.find(p => String(p.id) === id)
  if (existing) existing.quantity++
  else {
    cart.value.push({
      ...product,
      id,
      quantity: 1,
      price: Number(product.price) || 0,
      category: product.category || { name: product.category_name },
      printer: product.printer || product.category?.printer || null
    })
  }
}

const incrementQuantity = (item) => item.quantity++
const decrementQuantity = (item) => {
  if (item.quantity > 1) item.quantity--
  else removeItem(item)
}
const removeItem = (item) => cart.value = cart.value.filter(i => i.id !== item.id)
const clearCart = () => cart.value = []

// ========== CHARGEMENT DONNÉES ==========
const loadTableAndData = async (tableId) => {
  loadingProducts.value = true
  try {
    const [context] = await Promise.all([
      tableService.getTableFullContext(tableId),
      loadCategories()
    ])

    if (context.table) {
      selectedTable.value = { ...context.table, status: normalizeStatus(context.table.status) }
    }

    if (context.pendingOrders?.length) {
      currentPendingOrder.value = context.pendingOrders[0]
      isAddingToPending.value = false
    } else {
      currentPendingOrder.value = null
    }
  } catch (error) {
    console.error('Erreur chargement table:', error)
  } finally {
    loadingProducts.value = false
  }
}

const normalizeStatus = (status) => {
  const s = String(status || 'available').trim().toLowerCase()
  const map = { disponible: 'available', libre: 'available', occupée: 'occupied', reservee: 'reserved', hors_service: 'out_of_order' }
  return map[s] || s
}

const getStatusText = (status) => {
  const texts = { available: 'Libre', occupied: 'En service', reserved: 'Réservée', out_of_order: 'HS' }
  return texts[status] || status
}

const statusBadgeClass = (status) => {
  const classes = {
    available: 'bg-emerald-100 text-emerald-600',
    occupied: 'bg-indigo-100 text-indigo-600',
    reserved: 'bg-amber-100 text-amber-600',
    out_of_order: 'bg-rose-100 text-rose-600'
  }
  return classes[status] || 'bg-slate-100 text-slate-400'
}

const formatPrice = (price) => `${(Number.parseFloat(price) || 0).toLocaleString('fr-FR')} Ar`
const getProductImageUrl = (p) => {
  const raw = p?.image || p?.product?.image
  if (!raw) return placeholderImage
  if (raw.startsWith('http')) return raw
  return `${API_URL}/storage/${raw.startsWith('products/') ? '' : 'products/'}${raw}`
}
const handleImageError = (e) => e.target.src = placeholderImage

// ========== LOGIQUE COMMANDE ==========
const holdOrder = async () => {
  if (!cart.value.length || !selectedTable.value) return
  try {
    const token = localStorage.getItem('token')
    const user = JSON.parse(localStorage.getItem('user'))
    
    // Récupérer session active
    const { data: sessionData } = await axios.get(`${API_BASE_URL}/my-active-session`, {
      headers: { Authorization: `Bearer ${token}` }
    })

    const orderData = {
      table_id: selectedTable.value.id,
      user_id: user.id,
      point_of_sale_id: user.point_of_sale_id,
      cash_register_session_id: sessionData.id || sessionData.data?.id,
      order_lines: cart.value.map(i => ({ product_id: i.id, quantity: i.quantity, price: i.price }))
    }

    const { data } = await axios.post(`${API_BASE_URL}/sales/pending-order`, orderData, {
      headers: { Authorization: `Bearer ${token}` }
    })

    currentPendingOrder.value = data.data || data
    clearCart()
    await printingService.printOrder(selectedTable.value, orderData.order_lines)
  } catch (e) {
    console.error('Erreur hold order:', e)
  }
}

const beginAddToPending = () => {
  isAddingToPending.value = true
  clearCart()
}

const confirmAddToPending = async () => {
  if (!cart.value.length || isProcessing.value) return
  isProcessing.value = true
  try {
    const token = localStorage.getItem('token')
    const saleId = currentPendingOrder.value.id
    
    // Logique simplifiée d'ajout (on peut aussi utiliser l'endpoint updateLines si dispo)
    const orderLines = cart.value.map(i => ({ product_id: i.id, quantity: i.quantity, price: i.price }))
    
    await axios.post(`${API_BASE_URL}/sales/${saleId}/add-products`, { order_lines: orderLines }, {
      headers: { Authorization: `Bearer ${token}` }
    })

    await loadTableAndData(selectedTable.value.id)
    await printingService.printOrder(selectedTable.value, orderLines)
    clearCart()
    isAddingToPending.value = false
  } catch (e) {
    console.error('Erreur ajout:', e)
  } finally {
    isProcessing.value = false
  }
}

// ========== PAIEMENT & MODALS ==========
const openTableSelector = () => showTableSelector.value = true
const closeTableSelector = () => showTableSelector.value = false
const onTableSelected = (table) => {
  selectedTable.value = table
  showTableSelector.value = false
  loadTableAndData(table.id)
}

const openPaymentModalDirectly = () => isPaymentModalOpen.value = true
const handleCloseModal = () => isPaymentModalOpen.value = false
const closeInvoiceModal = () => {
  isInvoiceModalOpen.value = false
  loadTableAndData(selectedTable.value?.id)
}

const onPaymentSuccess = async (formattedSaleData) => {
  currentInvoiceNumber.value = `TICKET #${formattedSaleData.sale.ticket_number || formattedSaleData.sale.id}`;

  const allItems = formattedSaleData.categories.flatMap(category =>
    category.items.map(item => ({
      name: item.product_name,
      quantity: item.quantity,
      price: item.unit_price
    }))
  );
  invoiceItems.value = allItems;
  
  invoiceTotal.value = Number(formattedSaleData.totals.final_amount);
  invoicePayments.value = formattedSaleData.payments || [];
  
  isPaymentModalOpen.value = false;
  isInvoiceModalOpen.value = true;

  // Rafraîchir l'état de la table
  if (selectedTable.value?.id) {
    await loadTableAndData(selectedTable.value.id);
  }
}

const onPaymentError = (err) => console.error('Paiement error:', err)

const printBill = async () => {
  if (!currentPendingOrder.value || !selectedTable.value) return
  isProcessing.value = true
  try {
    await printingService.printBill(currentPendingOrder.value, selectedTable.value)
  } catch (error) {
    console.error('Erreur impression addition:', error)
    alert('Impossible d\'imprimer l\'addition. Veuillez vérifier l\'imprimante.')
  } finally {
    isProcessing.value = false
  }
}

const paymentTotalAmount = computed(() => displayTotal.value)
const paymentSaleData = computed(() => {
  let items = []
  
  if (currentPendingOrder.value && currentPendingOrder.value.order_lines) {
    items = currentPendingOrder.value.order_lines.map(l => ({
      product_id: l.product_id,
      quantity: l.quantity,
      unit_price: l.price,
      name: l.product?.name || l.name || 'Produit'
    }))
  }
  
  if (cart.value.length > 0) {
    cart.value.forEach(i => {
      items.push({
        product_id: i.id,
        quantity: i.quantity,
        unit_price: i.price,
        name: i.name || 'Produit'
      })
    })
  }

  return {
    items: items,
    total_amount: displayTotal.value,
    table_id: selectedTable.value?.id,
    id: currentPendingOrder.value?.id
  }
})

// ========== WATCHERS & LIFECYCLE ==========
watch(() => props.tableId, (newId) => { if (newId) loadTableAndData(newId) }, { immediate: true })

onMounted(() => {
  if (!props.tableId) openTableSelector()
})

</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.05);
  border-radius: 10px;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
