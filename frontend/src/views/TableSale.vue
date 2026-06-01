<template>
  <div class="table-sale-view min-h-screen bg-slate-50/50 p-4">
    <Profile v-if="!embedded" class="mb-4" />

    <!-- HEADER PREMIUM STICKY -->
    <header
      class="sticky top-2 z-30 mb-4 overflow-hidden rounded-3xl border border-white bg-white/80 p-3 shadow-lg shadow-slate-200/50 backdrop-blur-md"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white shadow-md shadow-slate-200"
          >
            <FontAwesomeIcon icon="fa-solid fa-table" class="text-base" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-lg font-black text-slate-800 leading-tight">
                {{
                  selectedTable ? `Table ${selectedTable.table_number}` : 'Sélectionner une table'
                }}
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
              {{ selectedTable?.name || 'Service en cours' }} •
              {{ selectedTable?.point_of_sale?.name || 'POS Principal' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="hidden xs:flex items-center gap-4 pr-4 border-r border-slate-100">
            <div class="text-right">
              <p
                class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none"
              >
                Articles
              </p>
              <p class="text-base font-black text-slate-800">{{ cart.length }}</p>
            </div>
            <div class="text-right">
              <p
                class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none"
              >
                Total
              </p>
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
      <section
        class="flex min-h-0 flex-col overflow-hidden rounded-[2rem] border border-white bg-white/80 backdrop-blur-md p-6 shadow-xl shadow-slate-200/50"
      >
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
              :class="
                activeCategoryId === null
                  ? 'bg-slate-900 text-white border-slate-900'
                  : 'bg-white text-slate-500 border-slate-100 hover:bg-slate-50'
              "
              @click="activeCategoryId = null"
            >
              Tous
            </button>
            <button
              v-for="category in categories"
              :key="category.id"
              class="rounded-xl px-2 py-3 text-[10px] font-black uppercase tracking-widest transition-all active:scale-95 border shadow-sm"
              :class="
                activeCategoryId === category.id
                  ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-500 ring-offset-2'
                  : 'bg-white text-slate-600 border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/30'
              "
              @click="activeCategoryId = category.id"
            >
              {{ category.name }}
            </button>
          </div>
        </div>

        <!-- Grid Produits -->
        <div class="mt-6 flex-1 overflow-y-auto pr-2 scrollbar-thin">
          <div v-if="loadingProducts" class="flex h-64 items-center justify-center">
            <div
              class="h-10 w-10 animate-spin rounded-full border-4 border-slate-100 border-t-indigo-600"
            ></div>
          </div>

          <div
            v-else-if="filteredProducts.length"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
          >
            <button
              v-for="product in filteredProducts"
              :key="product.id"
              class="group relative flex flex-col items-center rounded-3xl border border-slate-100 bg-white p-3 text-center transition-all duration-300 hover:border-indigo-100 hover:shadow-2xl hover:shadow-indigo-100/50 active:scale-95 disabled:opacity-30 disabled:grayscale"
              :disabled="isInteractionLocked"
              @click="addToCart(product)"
            >
              <div class="aspect-square w-full overflow-hidden rounded-2xl bg-slate-50">
                <img
                  :src="getProductImageUrl(product)"
                  class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  @error="handleImageError"
                />
              </div>
              <div class="mt-3 space-y-1">
                <p class="truncate text-sm font-bold text-slate-800">{{ product.name }}</p>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                  {{ getCategoryName(product) }}
                </p>
                <span
                  class="inline-block rounded-lg bg-indigo-50 px-2 py-1 text-[11px] font-black text-indigo-600"
                >
                  {{ formatPrice(getProductPrice(product)) }}
                </span>
              </div>
            </button>
          </div>
        </div>
      </section>

      <!-- SIDEBAR PANIER & COMMANDE -->
      <aside class="flex flex-col gap-4">
        <!-- Commande en Attente -->
        <div
          v-if="currentPendingOrder"
          class="rounded-[2rem] border border-amber-100 bg-amber-50/50 p-4 shadow-xl shadow-amber-900/5"
        >
          <div class="mb-3 flex items-center justify-between">
            <h3
              class="flex items-center gap-2 text-[10px] font-black text-amber-800 uppercase tracking-widest"
            >
              <FontAwesomeIcon icon="fa-solid fa-clock" />
              DÉJÀ COMMANDÉ
            </h3>
            <span class="rounded-lg bg-amber-100 px-2 py-0.5 text-[9px] font-black text-amber-600">
              #{{ currentPendingOrder.id }}
            </span>
          </div>

          <div v-if="currentPendingOrder?.order_lines" class="max-h-40 space-y-1 overflow-y-auto pr-1 scrollbar-thin">
            <div
              v-for="line in currentPendingOrder.order_lines"
              :key="line.id"
              class="flex items-center justify-between rounded-xl bg-white/60 p-2 border border-amber-100"
            >
              <span class="text-xs font-bold text-amber-900 leading-tight">
                <span class="text-amber-500">{{ line.quantity }}x</span> {{ line.product?.name }}
              </span>
              <span class="text-xs font-black text-amber-700 whitespace-nowrap ml-2">
                {{ formatPrice(getLinePrice(line) * line.quantity) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Panier Actuel -->
        <div
          class="flex flex-1 flex-col overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white p-5 shadow-2xl shadow-slate-200/50"
        >
          <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <h2 class="text-base font-black text-slate-800 tracking-tight">Nouvelle sélection</h2>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                {{ cart.length }} articles
              </p>
            </div>
            <button
              v-if="cart.length"
              @click="clearCart"
              class="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:text-rose-500 transition-colors"
            >
              <FontAwesomeIcon icon="fa-solid fa-trash" class="text-xs" />
            </button>
          </div>

          <div class="flex-1 space-y-1 overflow-y-auto pr-1 scrollbar-hide">
            <div
              v-for="item in cart"
              :key="item.id"
              class="group relative rounded-xl border border-slate-100 bg-slate-50/50 p-2 transition-all hover:bg-white hover:shadow-md"
            >
              <div class="flex items-center gap-2">
                <div class="flex-1 min-w-0">
                  <p class="truncate text-xs font-black text-slate-950 leading-none">
                    {{ item.name }}
                  </p>
                  <p class="text-[9px] font-medium text-slate-400 mt-0.5">
                    {{ formatPrice(getItemPrice(item)) }}
                  </p>
                </div>

                <div
                  class="flex items-center gap-1 rounded-lg bg-white p-0.5 shadow-sm border border-slate-100"
                >
                  <button
                    @click="decrementQuantity(item)"
                    class="flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-50 hover:text-slate-900"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-minus" class="text-[8px]" />
                  </button>
                  <span class="w-4 text-center text-[10px] font-black text-slate-700">{{
                    item.quantity
                  }}</span>
                  <button
                    @click="incrementQuantity(item)"
                    class="flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-50 hover:text-slate-900"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-plus" class="text-[8px]" />
                  </button>
                </div>

                <div class="min-w-[65px] text-right">
                  <span class="text-xs font-black text-indigo-600">{{
                    formatPrice(getItemTotal(item))
                  }}</span>
                </div>

                <button
                  @click="removeItem(item)"
                  class="text-slate-300 hover:text-rose-500 transition-colors p-1"
                >
                  <FontAwesomeIcon icon="fa-solid fa-xmark" class="text-[10px]" />
                </button>
              </div>
            </div>

            <div
              v-if="!cart.length"
              class="flex flex-col items-center justify-center py-6 text-slate-200"
            >
              <FontAwesomeIcon icon="fa-solid fa-shopping-cart" class="text-2xl mb-2 opacity-20" />
              <p class="text-[10px] font-bold uppercase tracking-widest">En attente d'ajout</p>
            </div>
          </div>

          <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
            <div class="flex justify-between items-end">
              <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest"
                >Total Salle</span
              >
              <span class="text-xl font-black text-slate-900 tracking-tight">{{
                formatPrice(displayTotal)
              }}</span>
            </div>

            <div class="grid gap-2">
              <button
                v-if="cart.length > 0 && !currentPendingOrder"
                @click="holdOrder"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 py-3 text-xs font-black text-white transition-all hover:bg-indigo-600 active:scale-95 shadow-xl shadow-slate-200"
              >
                <FontAwesomeIcon icon="fa-solid fa-receipt" />
                ENVOYER EN CUISINE
              </button>

              <button
                v-if="currentPendingOrder && !isAddingToPending"
                @click="beginAddToPending"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-50 py-3 text-xs font-black text-indigo-600 border border-indigo-100 transition-all hover:bg-indigo-100 active:scale-95"
              >
                <FontAwesomeIcon icon="fa-solid fa-plus" />
                NOUVELLE TOURNÉE
              </button>

              <button
                v-if="currentPendingOrder && isAddingToPending"
                @click="confirmAddToPending"
                :disabled="!cart.length"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 py-3 text-xs font-black text-white shadow-lg shadow-emerald-200 transition-all hover:bg-emerald-600 active:scale-95 disabled:opacity-30"
              >
                <FontAwesomeIcon icon="fa-solid fa-check" />
                CONFIRMER L'AJOUT
              </button>

              <button
                v-if="cart.length > 0 || currentPendingOrder"
                @click="openPaymentModalDirectly"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3.5 text-xs font-black text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-500 active:scale-95"
              >
                <FontAwesomeIcon icon="fa-solid fa-check-circle" />
                {{ currentPendingOrder ? 'ENCAISSER LA TABLE' : 'ENCAISSER DIRECT' }}
              </button>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- MODALS -->
    <TableSelectorModal
      ref="tableSelectorModal"
      :is-open="showTableSelector"
      :current-session-id="currentSessionId"
      @close="closeTableSelector"
      @table-selected="onTableSelected"
    />
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
    <InvoiceModal
      :is-open="isInvoiceModalOpen"
      :items="invoiceItems"
      :total="invoiceTotal"
      :client-name="selectedTable ? `Table ${selectedTable.table_number}` : 'Client'"
      :invoice-number="currentInvoiceNumber"
      :payment-method="currentPaymentMethod"
      :payments="invoicePayments"
      @close-modal="closeInvoiceModal"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '@/services/apiClient'
import axios from 'axios'
import TableSelectorModal from '../components/TableSelectorModal.vue'
import PaymentModal from './PaymentModal.vue'
import InvoiceModal from './InvoiceModal.vue'
import Profile from './Profile.vue'
import placeholderImage from '../assets/avatar.png'
import { useCategories } from '@/composables/useCategories'
import { tableService } from '@/services/tableService'
import { printingService } from '@/services/printing/PrintingService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { API_BASE_URL, API_URL } from '@/utils/api'

const props = defineProps({
  tableId: { type: [Number, String], default: null },
  embedded: { type: Boolean, default: false },
})

const { categories, products, loadCategories } = useCategories()

// ========== ÉTATS ==========
const activeCategoryId = ref(null)
const cart = ref([])
const searchQuery = ref('')
const selectedTable = ref(null)
const showTableSelector = ref(false)
const currentSessionId = ref(null)
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
const tableSelectorModal = ref(null)


// ========== FONCTIONS UTILITAIRES ==========

// Extraction du prix d'un produit
const getProductPrice = (product) => {
  if (!product) return 0
  if (product.price) return Number(product.price)
  if (product.pricings?.length > 0) return Number(product.pricings[0].price)
  if (product.unit_price) return Number(product.unit_price)
  return 0
}

// Extraction du prix d'un item du panier
const getItemPrice = (item) => {
  if (!item) return 0
  if (item.price) return Number(item.price)
  if (item.pricings?.length > 0) return Number(item.pricings[0].price)
  if (item.unit_price) return Number(item.unit_price)
  return 0
}

// Extraction du prix d'une ligne de commande
const getLinePrice = (line) => {
  if (!line) return 0
  if (line.price) return Number(line.price)
  if (line.unit_price) return Number(line.unit_price)
  return 0
}

// Calcul du total d'un item
const getItemTotal = (item) => {
  return getItemPrice(item) * (item.quantity || 0)
}

// Récupération du nom de la catégorie
const getCategoryName = (product) => {
  if (!product) return 'Sans catégorie'
  if (product.category_name) return product.category_name
  if (product.category?.name) return product.category.name
  if (product.category_name_fr) return product.category_name_fr
  if (product.category_id && categories.value.length) {
    const foundCategory = categories.value.find((c) => c.id === product.category_id)
    if (foundCategory) return foundCategory.name
  }
  return 'Produit'
}

// Récupération de la session active
const getActiveSession = async () => {
  let session = null

  // Tentative via API
  try {
    const { data: response } = await apiClient.get('/my-active-session')
    const sessionResponse = response.data
    session = sessionResponse?.current_session || sessionResponse?.data || sessionResponse
    if (session?.id) {
      localStorage.setItem('cash_register_session', JSON.stringify(session))
      localStorage.setItem('cash_register_session_id', session.id.toString())
      return session
    }
  } catch (e) {
    console.warn('API session fetch failed:', e.message)
  }

  // Fallback localStorage
  const storageKeys = ['cash_register_session', 'current_session', 'active_session']
  for (const key of storageKeys) {
    const stored = localStorage.getItem(key)
    if (stored) {
      try {
        const parsed = JSON.parse(stored)
        if (parsed?.id) {
          session = parsed
          break
        }
      } catch (e) {}
    }
  }

  if (!session?.id) {
    const sessionId = localStorage.getItem('cash_register_session_id')
    if (sessionId) {
      session = { id: parseInt(sessionId) }
    }
  }

  return session
}

// Normalisation du statut
const normalizeStatus = (status) => {
  const s = String(status || 'available')
    .trim()
    .toLowerCase()
  const map = {
    disponible: 'available',
    libre: 'available',
    occupée: 'occupied',
    reservee: 'reserved',
    hors_service: 'out_of_order',
  }
  return map[s] || s
}

// Formatage des prix
const formatPrice = (price) => {
  const numPrice = Number(price) || 0
  return `${numPrice.toLocaleString('fr-FR')} Ar`
}

// URL de l'image produit
const getProductImageUrl = (product) => {
  const raw = product?.image || product?.product?.image
  if (!raw) return placeholderImage
  if (raw.startsWith('http')) return raw
  return `${API_URL}/storage/${raw.startsWith('products/') ? '' : 'products/'}${raw}`
}

const handleImageError = (e) => {
  e.target.src = placeholderImage
}

// ========== COMPUTED ==========
const totalPrice = computed(() => {
  return cart.value.reduce((sum, item) => sum + getItemTotal(item), 0)
})

const displayTotal = computed(() => {
  if (cart.value.length > 0) return totalPrice.value
  return parseFloat(
    currentPendingOrder.value?.final_amount || currentPendingOrder.value?.total_amount || 0,
  )
})

const filteredProducts = computed(() => {
  let base = products.value
  if (activeCategoryId.value !== null) {
    base = base.filter((p) => p.category_id === activeCategoryId.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    base = base.filter((p) => p.name.toLowerCase().includes(q))
  }
  return base
})

const isInteractionLocked = computed(() => !!currentPendingOrder.value && !isAddingToPending.value)

const getStatusText = (status) => {
  const texts = {
    available: 'Libre',
    occupied: 'En service',
    reserved: 'Réservée',
    out_of_order: 'HS',
  }
  return texts[status] || status
}

const statusBadgeClass = (status) => {
  const classes = {
    available: 'bg-emerald-100 text-emerald-600',
    occupied: 'bg-indigo-100 text-indigo-600',
    reserved: 'bg-amber-100 text-amber-600',
    out_of_order: 'bg-rose-100 text-rose-600',
  }
  return classes[status] || 'bg-slate-100 text-slate-400'
}

// ========== ACTIONS PANIER ==========
const addToCart = (product) => {
  if (isInteractionLocked.value) return

  const id = String(product.id || product.product_id)
  const existing = cart.value.find((p) => String(p.id) === id)
  const productPrice = getProductPrice(product)

  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({
      ...product,
      id,
      quantity: 1,
      price: productPrice,
      category: product.category || { name: product.category_name },
      printer: product.printer || product.category?.printer || null,
    })
  }
}

const incrementQuantity = (item) => {
  item.quantity++
}

const decrementQuantity = (item) => {
  if (item.quantity > 1) {
    item.quantity--
  } else {
    removeItem(item)
  }
}

const removeItem = (item) => {
  cart.value = cart.value.filter((i) => i.id !== item.id)
}

const clearCart = () => {
  cart.value = []
}

// ========== CHARGEMENT DONNÉES ==========
const loadTableAndData = async (tableId) => {
  loadingProducts.value = true
  try {
    const [context] = await Promise.all([
      tableService.getTableFullContext(tableId),
      loadCategories(),
    ])

    if (context.table) {
      selectedTable.value = {
        ...context.table,
        status: normalizeStatus(context.table.status),
      }
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

// ========== LOGIQUE COMMANDE ==========
const holdOrder = async () => {
  if (!cart.value.length || !selectedTable.value) return

  try {
    const token = localStorage.getItem('token')
    const user = JSON.parse(localStorage.getItem('user'))

    if (!user || !token) {
      alert('Utilisateur non authentifié')
      return
    }

    const session = await getActiveSession()

    if (!session?.id) {
      alert('Aucune session de caisse valide trouvée. Veuillez ouvrir une session.')
      return
    }

    const activePosStr = localStorage.getItem('active_pos')
    const activePos = activePosStr ? JSON.parse(activePosStr) : null

    let pointOfSaleId =
      activePos?.id || session.cash_register?.point_of_sale_id || session.point_of_sale_id

    if (!pointOfSaleId) {
      alert('Point de vente non trouvé. Veuillez sélectionner un point de vente.')
      return
    }

    const orderData = {
      table_id: selectedTable.value.id,
      user_id: user.id,
      point_of_sale_id: pointOfSaleId,
      cash_register_session_id: session.id,
      discount_percentage: null,
      order_lines: cart.value.map((item) => ({
        product_id: item.id,
        quantity: item.quantity,
        price: getItemPrice(item),
      })),
    }

    // ✅ CORRECTION : Utiliser la bonne route
    // Route du backend : Route::post('/sales/pending-order', ...)
    const { data } = await apiClient.post('/sales/pending-order', orderData)

    currentPendingOrder.value = data.data || data
    
    // Explicitly reload to get updated status from server
    await loadTableAndData(selectedTable.value.id)

    try {
      await printingService.printOrder(selectedTable.value, [...cart.value])
    } catch (printError) {
      console.warn('Print error:', printError)
    }

    clearCart()
  } catch (e) {
    console.error('Erreur hold order:', e)
    const errorMsg = e.response?.data?.message || e.response?.data?.error || e.message
    alert(`Erreur: ${errorMsg}`)
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

    const orderLines = cart.value.map((i) => ({
      product_id: i.id,
      quantity: i.quantity,
      price: getItemPrice(i),
    }))

    // ✅ CORRECTION: Utiliser la bonne route POST /sales/{saleId}/add-products
    await tableService.addProductsToPendingOrder(saleId, cart.value, selectedTable.value.id)

    await printingService.printOrder(selectedTable.value, [...cart.value])

    // Explicitly reload to get updated status from server
    await loadTableAndData(selectedTable.value.id)

    clearCart()
    isAddingToPending.value = false
  } catch (e) {
    console.error('Erreur ajout:', e)
    alert(`Erreur: ${e.response?.data?.message || e.message}`)
  } finally {
    isProcessing.value = false
  }
}

// ========== PAIEMENT & MODALS ==========
// TableSale.vue
const openTableSelector = () => {
  showTableSelector.value = true

  // ✅ Rafraîchir immédiatement (pas besoin de setTimeout)
  if (tableSelectorModal.value) {
    // Appel direct sans setTimeout
    tableSelectorModal.value.refresh()
  }
}

const closeTableSelector = () => {
  showTableSelector.value = false
}

const onTableSelected = async (table) => {
  selectedTable.value = table
  showTableSelector.value = false

  // ✅ Charger les données de la table sélectionnée
  await loadTableAndData(table.id)

  // ✅ Forcer le rafraîchissement du modal des tables
  if (tableSelectorModal.value) {
    await tableSelectorModal.value.refresh(true)
  }
}

const openPaymentModalDirectly = () => {
  isPaymentModalOpen.value = true
}

const handleCloseModal = () => {
  isPaymentModalOpen.value = false
}

const closeInvoiceModal = () => {
  isInvoiceModalOpen.value = false
  loadTableAndData(selectedTable.value?.id)
}

const onPaymentSuccess = async (formattedSaleData) => {
  currentInvoiceNumber.value = `VENTE #${formattedSaleData.sale.sale_number || formattedSaleData.sale.ticket_number || formattedSaleData.sale.id}`

  const allItems = formattedSaleData.categories.flatMap((category) =>
    category.items.map((item) => ({
      name: item.product_name,
      quantity: item.quantity,
      price: item.unit_price,
    })),
  )

  invoiceItems.value = allItems
  invoiceTotal.value = Number(formattedSaleData.totals.final_amount)
  invoicePayments.value = formattedSaleData.payments || []

  isPaymentModalOpen.value = false
  isInvoiceModalOpen.value = true

  if (selectedTable.value?.id) {
    await loadTableAndData(selectedTable.value.id)
  }
}

const onPaymentError = (err) => {
  console.error('Paiement error:', err)
  alert(`Erreur de paiement: ${err.message || 'Erreur inconnue'}`)
}

const printBill = async () => {
  if (!currentPendingOrder.value || !selectedTable.value) return

  isProcessing.value = true
  try {
    const billData = {
      companyName: 'INTERNATIONAL GASTRONOMY PIZZA',
      address: 'Antananarivo, Madagascar',
      number: 'ADD-' + (currentPendingOrder.value.ticket_number || currentPendingOrder.value.id),
      date: new Date().toLocaleString('fr-FR'),
      items:
        currentPendingOrder.value.order_lines?.map((l) => ({
          name: l.product?.name || l.name || 'Produit',
          quantity: l.quantity,
          price: getLinePrice(l),
        })) || [],
      total: currentPendingOrder.value.final_amount || currentPendingOrder.value.total_amount || 0,
      client: `Table ${selectedTable.value.table_number || selectedTable.value.name}`,
    }
    await printingService.printInvoice(billData)
  } catch (error) {
    console.error('Erreur impression addition:', error)
    alert("Impossible d'imprimer l'addition. Veuillez vérifier l'imprimante.")
  } finally {
    isProcessing.value = false
  }
}

// ========== COMPUTED POUR PAIEMENT ==========
const paymentTotalAmount = computed(() => displayTotal.value)

const paymentSaleData = computed(() => {
  const items = []

  if (currentPendingOrder.value?.order_lines) {
    items.push(
      ...currentPendingOrder.value.order_lines.map((l) => ({
        product_id: l.product_id,
        quantity: l.quantity,
        unit_price: getLinePrice(l),
        name: l.product?.name || l.name || 'Produit',
      })),
    )
  }

  if (cart.value.length > 0) {
    items.push(
      ...cart.value.map((i) => ({
        product_id: i.id,
        quantity: i.quantity,
        unit_price: getItemPrice(i),
        name: i.name || 'Produit',
      })),
    )
  }

  return {
    items,
    total_amount: displayTotal.value,
    table_id: selectedTable.value?.id,
    id: currentPendingOrder.value?.id,
  }
})

// ========== HELPERS ==========
const fetchCurrentSession = async () => {
  try {
    const { data } = await apiClient.get('/my-active-session')
    return data?.data || data || null
  } catch (error) {
    console.error('Erreur session:', error)
    return null
  }
}

// ========== WATCHERS & LIFECYCLE ==========
watch(
  () => props.tableId,
  (newId) => {
    if (newId) loadTableAndData(newId)
  },
  { immediate: true },
)

onMounted(async () => {
  await loadCategories()
  
  // Récupérer la session active
  const session = await fetchCurrentSession()
  if (session?.id) {
    currentSessionId.value = session.id
  }

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
