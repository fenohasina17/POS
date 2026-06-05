<template>
  <div class="contents">
    <!-- Modals -->
    <PaymentModal
      :isOpen="isPaymentModalOpen"
      :saleId="currentSaleId || 0"
      :totalAmount="totalPrice"
      :saleData="saleDataForModal"
      @close-modal="handleClosePaymentModal"
      @payment-success="handlePaymentSuccess"
      @payment-error="handlePaymentError"
      @clear-cart="handleClearCart"
    />

    <InvoiceModal
      :isOpen="isInvoiceModalOpen"
      :items="cart"
      :total="totalPrice"
      :clientName="'Client'"
      :invoiceNumber="currentInvoiceNumber"
      :paymentMethod="currentPaymentMethod"
      :payments="paymentsList"
      :discountPercentage="selectedDiscount"
      @close-modal="closeInvoiceModal"
    />

    <div class="direct-sale-layout grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px] p-4 bg-slate-50/50">
      <!-- OVERLAY DE VERROUILLAGE GLOBAL -->
      <div v-if="isSessionBilleted && !isAdmin" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-md transition-all duration-500">
        <div class="flex flex-col items-center gap-6 p-10 bg-white rounded-[3rem] shadow-2xl border-4 border-rose-500 scale-110">
          <div class="h-24 w-24 rounded-full bg-rose-500 flex items-center justify-center text-white shadow-2xl animate-bounce">
            <FontAwesomeIcon icon="fa-solid fa-lock" class="text-5xl" />
          </div>
          <div class="text-center">
            <h2 class="text-3xl font-black text-slate-900 uppercase tracking-tighter">Ventes Verrouillées</h2>
            <p class="text-lg font-bold text-rose-600 mt-2">Le billetage a déjà été validé.</p>
            <p class="text-sm font-medium text-slate-500 mt-1 italic">Clôturez cette session pour continuer.</p>
          </div>
          <button @click="router.push({ name: 'cash-registers-machine-link' })" class="mt-4 px-8 py-3 bg-slate-900 text-white rounded-2xl font-black shadow-xl hover:bg-indigo-600 transition-all">
            Retour à l'accueil
          </button>
        </div>
      </div>

      <!-- Section Produits -->
      <section class="flex min-h-0 flex-col overflow-hidden rounded-[2rem] border border-white bg-white/80 backdrop-blur-md p-5 shadow-xl shadow-slate-200/50">
        <div class="flex flex-col gap-5 border-b border-slate-100 pb-5">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-xl font-bold bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent">
                Menu Digital
              </h2>
              <p class="text-xs text-slate-400 font-medium">Sélectionnez les articles pour la vente</p>
            </div>

            <div class="relative w-full sm:max-w-xs group">
              <FontAwesomeIcon
                icon="fa-solid fa-search"
                class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-indigo-500"
              />
              <input
                type="text"
                placeholder="Rechercher un délice..."
                v-model="searchQuery"
                class="w-full rounded-2xl border-none bg-slate-100/80 py-3 pl-11 pr-4 text-sm text-slate-700 shadow-inner outline-none transition-all focus:bg-white focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>
          </div>

          <!-- Catégories Style "Grid" -->
          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
            <button
              type="button"
              class="rounded-xl px-2 py-3 text-[11px] font-black uppercase tracking-wider transition-all active:scale-95 shadow-sm border"
              :class="[
                activeCategoryId === null
                  ? 'bg-slate-900 text-white border-slate-900 shadow-slate-200'
                  : 'bg-white text-slate-500 hover:bg-slate-50 border-slate-100'
              ]"
              @click="setActiveCategory(null)"
            >
              Tous
            </button>
            <button
              v-for="category in categories"
              :key="category.id"
              type="button"
              class="rounded-xl px-2 py-3 text-[11px] font-black uppercase tracking-wider transition-all active:scale-95 shadow-sm border"
              :class="[
                activeCategoryId === category.id
                  ? 'bg-indigo-600 text-white border-indigo-600 shadow-indigo-100 ring-2 ring-indigo-500 ring-offset-2'
                  : 'bg-white text-slate-600 hover:border-indigo-200 hover:bg-indigo-50/30 border-slate-100'
              ]"
              @click="setActiveCategory(category.id)"
            >
              {{ category.name }}
            </button>

          </div>
        </div>

        <!-- Grid Produits -->
        <div class="mt-5 flex-1 overflow-hidden">
          <div
            v-if="filteredProducts.length"
            class="grid h-full grid-cols-2 gap-4 overflow-y-auto pr-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
          >
            <button
              v-for="product in filteredProducts"
              :key="product.id"
              type="button"
              class="group relative flex flex-col items-center rounded-3xl border border-slate-100 bg-white p-3 text-center transition-all duration-300 hover:border-indigo-100 hover:shadow-2xl hover:shadow-indigo-100/50 active:scale-95"
              @click="addToCart(product)"
            >
              <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-slate-50">
                <img
                  :src="getProductImageUrl(product)"
                  class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                  @error="handleImageError"
                  loading="lazy"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 transition-opacity group-hover:opacity-100"></div>
              </div>

              <div class="mt-3 w-full space-y-1">
                <p class="truncate text-sm font-bold text-slate-800">{{ product.name }}</p>
                <div class="flex items-center justify-center gap-1.5">
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                  <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    {{ product.category_name || 'Divers' }}
                  </p>
                </div>
                <div class="pt-1">
                  <span class="inline-block rounded-lg bg-indigo-50 px-2 py-1 text-xs font-black text-indigo-600">
                    {{ formatPrice(product.price) }}
                  </span>
                </div>
              </div>

              <!-- Badge flottant "Plus" -->
              <div class="absolute right-2 top-2 scale-0 rounded-full bg-indigo-600 p-1.5 text-white shadow-lg transition-transform group-hover:scale-100">
                <FontAwesomeIcon icon="fa-solid fa-plus" class="text-[10px]" />
              </div>
            </button>
          </div>

          <!-- Empty State -->
          <div
            v-else
            class="flex h-full flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-100 bg-slate-50/30 py-20"
          >
            <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white shadow-sm">
              <FontAwesomeIcon icon="fa-solid fa-boxes" class="text-3xl text-slate-200" />
            </div>
            <p class="text-base font-bold text-slate-400">Aucun produit trouvé</p>
            <p class="text-sm text-slate-300">Essayez une autre catégorie ou recherche</p>
          </div>
        </div>
      </section>

      <!-- Section Panier (Sidebar Premium - Mode Clair) -->
      <aside class="flex h-full min-h-0 flex-col overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-200/50">
        <div class="flex items-center justify-between border-b border-slate-100 pb-5">
          <div>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Votre Panier</h2>
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ cart.length }} articles</p>
          </div>
          <button
            v-if="cart.length"
            type="button"
            class="group flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 transition-all hover:bg-rose-50 hover:text-rose-500 active:scale-95"
            @click="clearCart"
          >
            <FontAwesomeIcon icon="fa-solid fa-trash" class="text-sm" />
          </button>
        </div>

        <!-- Liste Articles Panier (Compactée) -->
        <div v-if="cart.length > 0" class="flex-1 space-y-1 overflow-y-auto py-4 scrollbar-hide">
          <div
            v-for="item in cart"
            :key="item.id"
            class="group relative rounded-xl border border-slate-100 bg-slate-50/50 p-2 transition-all hover:bg-white hover:shadow-md"
          >
            <div class="flex items-center gap-2">
              <!-- Nom de l'article -->
              <div class="flex-1 min-w-0">
                <p class="truncate text-xs font-black text-slate-950 leading-none">{{ item.name }}</p>
                <p class="text-[9px] font-medium text-slate-400 mt-0.5">{{ formatPrice(item.price) }}</p>
              </div>

              <!-- Contrôles de quantité compacts -->
              <div class="flex items-center gap-1 rounded-lg bg-white p-0.5 shadow-sm border border-slate-100">
                <button
                  type="button"
                  class="flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-50 hover:text-slate-900"
                  @click="decrementQuantity(item)"
                >
                  <FontAwesomeIcon icon="fa-solid fa-minus" class="text-[8px]" />
                </button>
                <span class="w-4 text-center text-[10px] font-black text-slate-700">{{ item.quantity }}</span>
                <button
                  type="button"
                  class="flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-50 hover:text-slate-900"
                  @click="incrementQuantity(item)"
                >
                  <FontAwesomeIcon icon="fa-solid fa-plus" class="text-[8px]" />
                </button>
              </div>

              <!-- Montant Total -->
              <div class="min-w-[65px] text-right">
                <p class="text-xs font-black text-indigo-600">
                  {{ formatPrice(item.price * item.quantity) }}
                </p>
              </div>

              <!-- Supprimer -->
              <button
                type="button"
                class="text-slate-300 transition-colors hover:text-rose-500 p-1"
                @click="removeItem(item)"
              >
                <FontAwesomeIcon icon="fa-solid fa-xmark" class="text-[10px]" />
              </button>
            </div>
          </div>
        </div>

        <!-- Panier Vide -->
        <div
          v-else
          class="flex flex-1 flex-col items-center justify-center text-center"
        >
          <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-slate-50">
            <FontAwesomeIcon icon="fa-solid fa-shopping-cart" class="text-2xl text-slate-200" />
          </div>
          <p class="text-sm font-bold text-slate-400">Le panier est vide</p>
          <p class="text-[10px] font-medium text-slate-300 uppercase tracking-widest mt-1">En attente d'articles</p>
        </div>

        <!-- Résumé et Validation -->
        <div class="mt-auto space-y-4 border-t border-slate-100 pt-6">
          <div class="space-y-2">
            <div class="flex justify-between text-[11px] font-black uppercase tracking-widest text-slate-400">
              <span>Sous-total</span>
              <span>{{ formatPrice(totalPrice) }}</span>
            </div>
            <div class="flex justify-between items-end">
              <span class="text-sm font-black text-slate-700">Total à payer</span>
              <span class="text-2xl font-black text-slate-900 tracking-tight">{{ formatPrice(totalPrice) }}</span>
            </div>
          </div>

          <button
            type="button"
            class="group relative flex w-full items-center justify-center gap-3 overflow-hidden rounded-2xl bg-slate-900 py-4 font-black text-white shadow-xl shadow-slate-200 transition-all hover:bg-indigo-600 active:scale-[0.98] disabled:bg-slate-100 disabled:text-slate-300 disabled:shadow-none"
            @click="openPaymentModal"
          >
            <FontAwesomeIcon icon="fa-solid fa-check" class="text-lg" />
            <span>VALIDER L'ENCAISSEMENT</span>
          </button>
        </div>
      </aside>
    </div>
  </div>
</template>
<script setup>
import { ref, shallowRef, onMounted, computed, watch } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/services/apiClient'
import { API_BASE_URL, API_URL } from '@/utils/api'
import { dataCacheService } from '@/services/dataCacheService'
import { storage } from '@/utils/storage'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBoxes, faSearch, faShoppingCart, faTrash, faMinus, faPlus, faCheck, faXmark, faLock } from '@fortawesome/free-solid-svg-icons'
const { activePos } = useAuth()
const route = useRoute()
const router = useRouter()

import PaymentModal from './PaymentModal.vue'
import InvoiceModal from './InvoiceModal.vue'
import placeholderImage from '../assets/avatar.png'

library.add(faBoxes, faSearch, faShoppingCart, faTrash, faMinus, faPlus, faCheck, faXmark, faLock)

// ========== ÉTATS ==========
const isPaymentModalOpen = ref(false)
const isInvoiceModalOpen = ref(false)

const currentSaleId = ref(null)
const currentInvoiceNumber = ref('')
const currentPaymentMethod = ref('')
const paymentsList = ref([])

import { useCart } from '@/composables/useCart'
import { useCashRegisterSession } from '@/composables/useCashRegisterSession'

const { cart, totalPrice, addToCart, removeFromCart, clearCart } = useCart()

const categories = ref([])
const products = shallowRef([])
const activeCategoryId = ref(null)
const searchQuery = ref('')
const user = ref(null)
const selectedDiscount = ref(0)
const isLoading = ref(false)
const { isSessionBilleted, checkActiveSession } = useCashRegisterSession()
const canSell = computed(() => {
  return !isSessionBilleted.value || isAdmin.value
})

const filteredProducts = computed(() => {
  let base = products.value
  if (activeCategoryId.value !== null) {
    base = base.filter(p => p.category_id === activeCategoryId.value)
  }
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    base = base.filter(p => p.name.toLowerCase().includes(query))
  }
  return base
})

// ========== COMPUTED ==========
const saleDataForModal = computed(() => {
  // Récupérer la session depuis localStorage
  const cashSession = localStorage.getItem('cashRegisterSession')
  let cashRegisterSessionId = null
  let originalUserId = null
  let isSupervisionMode = false

  if (cashSession) {
    try {
      const parsed = JSON.parse(cashSession)

      console.log('📋 Session parsée:', {
        id: parsed.id,
        is_supervision_mode: parsed.is_supervision_mode,
        original_user_id: parsed.original_user_id,
        admin_user_id: parsed.admin_user_id
      })

      // Mode supervision: utiliser l'ID de la session réelle
      if (parsed.is_supervision_mode && parsed.id) {
        cashRegisterSessionId = parsed.id  // 🔥 ID de la session réelle (ex: 1)
        originalUserId = parsed.original_user_id  // ID du caissier (ex: 2)
        isSupervisionMode = true
        console.log('🏷️ Mode supervision:')
        console.log('  - cash_register_session_id:', cashRegisterSessionId)
        console.log('  - user_id (caissier):', originalUserId)
      }
      // Mode normal
      else if (parsed.id && !isNaN(parseInt(parsed.id))) {
        cashRegisterSessionId = parseInt(parsed.id)
        console.log('🏷️ Mode normal - cash_register_session_id:', cashRegisterSessionId)
      }
    } catch(e) {
      console.error('Erreur parsing session:', e)
    }
  }

  // 🔥 Déterminer le user_id à associer à la vente
  // En supervision: c'est l'ID du caissier occupant
  // En mode normal: c'est l'ID de l'utilisateur connecté
  let saleUserId = user.value?.id

  if (isSupervisionMode && originalUserId) {
    saleUserId = originalUserId  // Utiliser l'ID du caissier (ex: 2)
    console.log('👥 Vente associée au caissier occupant ID:', saleUserId)
  }

  const saleData = {
    items: cart.value.map(item => ({
      product_id: item.id,
      quantity: item.quantity,
      unit_price: Math.round(item.price),
      total: Math.round(item.price * item.quantity),
      name: item.name
    })),
    total_amount: Math.round(totalPrice.value),
    customer_id: null,
    point_of_sale_id: activePos.value?.id || null,
    cash_register_session_id: cashRegisterSessionId,  // 🔥 ID session réelle (1)
    user_id: saleUserId  // 🔥 ID caissier (2)
  }

  console.log('📤 Payload final:', {
    cash_register_session_id: saleData.cash_register_session_id,
    user_id: saleData.user_id,
    point_of_sale_id: saleData.point_of_sale_id,
    total_amount: saleData.total_amount
  })

  return saleData
})

// ========== VÉRIFICATION ADMIN ==========
const isAdmin = computed(() => {
  const auth = storage.getAuth()
  const userRole = auth?.user?.role || auth?.user?.is_admin
  return userRole === 'admin' || userRole === true
})

// Vérifier si on peut bypasser la session (admin + route dashboard-direct)
const shouldBypassSession = computed(() => {
  return route.name === 'dashboard-direct' && isAdmin.value
})

// ========== MÉTHODES ==========
const formatPrice = (price) => {
  const value = Number.parseFloat(price) || 0
  // Always show two decimal places for currency clarity
  return `${value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Ar`
}

const getProductImageUrl = (product) => {
  const raw = product?.image || product?.product?.image
  if (!raw) return placeholderImage
  if (/^https?:\/\//i.test(raw)) return raw
  if (raw.startsWith('storage/')) return `${API_URL}/${raw}`
  if (raw.startsWith('products/')) return `${API_URL}/storage/${raw}`
  return `${API_URL}/storage/products/${raw}`
}

const handleImageError = (event) => {
  if (event?.target) {
    event.target.onerror = null
    event.target.src = placeholderImage
  }
}

// Gestion du panier
// Use composable's addToCart, removeFromCart, and clearCart directly
// Increment and decrement adjust quantity on the composable's cart items
const incrementQuantity = (item) => { item.quantity++ }
const decrementQuantity = (item) => {
  if (item.quantity > 1) item.quantity--
  else removeFromCart(item.id)
}

const removeItem = (item) => {
  removeFromCart(item.id)
}

// clearCart already imported from useCart; no extra implementation needed

const setActiveCategory = (categoryId) => {
  activeCategoryId.value = categoryId
}

// ========== LOGIQUE DE CHARGEMENT DES DONNÉES ==========
const processData = (data) => {
  categories.value = data
  products.value = Object.freeze(data.flatMap(category =>
    (category.products || []).map(product => ({
      ...product,
      category_id: category.id,
      category_name: category.name,
      printer: category.printer,
      // Use 'pricings' (plural) as returned by the API; if missing, omit price
      ...(product.pricings?.[0]?.price ? { price: Number.parseFloat(product.pricings[0].price) } : {})    }))
  ))
}

const loadData = async (forceRefresh = false) => {
  const auth = storage.getAuth()
  if (!activePos.value?.id || !auth?.token) return

  try {
    isLoading.value = true
    const data = await dataCacheService.getCategories(
      activePos.value.id,
      auth.token,
      forceRefresh
    )
    processData(data)
  } catch (error) {
    console.error('Erreur de chargement des données:', error)
  } finally {
    isLoading.value = false
  }
}

// ========== VÉRIFICATION SESSION ACTIVE (MODIFIÉE POUR ADMIN) ==========
const checkActiveSessionAndRedirect = async () => {
  const auth = storage.getAuth()
  if (!auth?.token) {
    router.push({ name: 'login' })
    return false
  }

  // 🔓 ADMIN : bypass la vérification de session
  if (shouldBypassSession.value) {
    console.log('👑 Admin détecté - bypass de la vérification de session caisse')

    // Récupérer la session virtuelle admin ou définir un point de vente par défaut
    const localSession = localStorage.getItem('cashRegisterSession')
    if (localSession) {
      try {
        const parsed = JSON.parse(localSession)
        if (parsed.is_admin_session === true && parsed.cash_register_id) {
          if (auth.user) {
            user.value = auth.user
          }
        }
      } catch (e) {}
    }


    return true
  }

  // 👤 Caissier normal : vérifier la session active
  try {
    const response = await apiClient.get('/my-active-session')

    const isAdminVirtualSession = (
      localStorage.getItem('cashRegisterSession') &&
      JSON.parse(localStorage.getItem('cashRegisterSession')).is_admin_session === true
    )

    // Vérifier si response.data.data contient un ID de session valide
    console.log('🔍 Debug Session - response.data:', response.data)
    console.log('🔍 Debug Session - isAdminVirtualSession:', isAdminVirtualSession)

    const sessionData = response.data && response.data.data ? response.data.data : response.data
    const hasSession = (sessionData && sessionData.id) || isAdminVirtualSession

    if (!hasSession) {
      console.warn('⚠️ Redirection: Aucune session active détectée')
      router.push({ name: 'cash-registers-machine-link' })
      return false
    }

    // 🔥 MISE À JOUR DE L'ÉTAT DE VERROUILLAGE
    isSessionBilleted.value = !!(sessionData && sessionData.is_bill_checked)
    console.log('🔍 État du billetage session:', isSessionBilleted.value)

    // Mettre à jour le localStorage avec les données réelles de la session
    if (sessionData && sessionData.id) {
      localStorage.setItem('cashRegisterSession', JSON.stringify(sessionData))
      console.log('✅ Session mise à jour dans localStorage')
    }

    // Récupérer les infos utilisateur
    if (auth?.user) {
      user.value = auth.user
    }

    return true
  } catch (error) {
    console.error('Erreur vérification session:', error)

    const localSession = localStorage.getItem('cashRegisterSession')
    if (localSession) {
      try {
        const parsed = JSON.parse(localSession)
        isSessionBilleted.value = !!parsed.is_bill_checked // 👈 Ajouter ici

        if (parsed.is_admin_session === true) {
          console.log('👑 Session virtuelle admin trouvée')
          if (auth?.user) {
            user.value = auth.user
          }
          return true
        }
      } catch (e) {}
    }

    router.push({ name: 'cash-registers-machine-link' })
    return false
  }
}

// ========== INITIALISATION ==========
onMounted(async () => {
  console.log('DirectSale - Route:', route.name, 'IsAdmin:', isAdmin.value)

  const sessionOk = await checkActiveSessionAndRedirect()
  if (!sessionOk) return

  // Chargement des données si point de vente actif disponible
  if (activePos.value?.id) {
    await loadData(false)
    setTimeout(() => loadData(true), 1000)
  } else if (isAdmin.value) {
    console.warn('Admin: Aucun point de vente actif trouvé, certaines fonctionnalités peuvent être limitées')
  }
})

const handleClosePaymentModal = () => {
  isPaymentModalOpen.value = false
}

const handlePaymentSuccess = (data) => {
  const saleId = data.sale?.id || data.id
  if (!saleId) return

  let receivedPayments = []
  if (data.payments && Array.isArray(data.payments)) {
    receivedPayments = data.payments
  } else if (data.sale?.payments && Array.isArray(data.sale.payments)) {
    receivedPayments = data.sale.payments
  }

  paymentsList.value = receivedPayments.map(p => ({
    payment_method_name: p.payment_method_name || p.method || p.type || 'Paiement',
    amount: Math.round(p.amount || 0),
    reference: p.reference || ''
  }))

  currentSaleId.value = saleId
  const ticketNumber = data.sale_number || data.sale?.sale_number || data.ticket_number || data.sale?.ticket_number
  currentInvoiceNumber.value = ticketNumber ? `Vente #${ticketNumber}` : `INV-${saleId.toString().padStart(6, '0')}`
  currentPaymentMethod.value = paymentsList.value[0]?.payment_method_name || 'Espèces'

  isPaymentModalOpen.value = false
  isInvoiceModalOpen.value = true
}

const handlePaymentError = (error) => {
  console.error('Erreur de paiement:', error);
  alert(error?.message || error?.response?.data?.message || "Une erreur est survenue lors du paiement. Veuillez réessayer.");
}

const handleClearCart = () => {
  cart.value = [];
  console.log('Cart cleared from DirectSale component after payment.');
}

const closeInvoiceModal = () => {
  isInvoiceModalOpen.value = false
  paymentsList.value = []
  currentSaleId.value = null
  currentInvoiceNumber.value = ''
  currentPaymentMethod.value = ''
  clearCart()
}

const openPaymentModal = () => {
  if (cart.value.length === 0) return
  currentSaleId.value = null
  isPaymentModalOpen.value = true
}
</script>

<style>
@import '@/assets/styles/DirectSale.css';
</style>
