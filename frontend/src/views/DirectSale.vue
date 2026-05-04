import { ref, shallowRef, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { API_BASE_URL, API_URL } from '@/utils/api'
import { dataCacheService } from '@/services/dataCacheService'
import { storage } from '@/utils/storage'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faBoxes, faSearch, faShoppingCart,
  faTrash, faMinus, faPlus, faCheck, faXmark
} from '@fortawesome/free-solid-svg-icons'

import PaymentModal from './PaymentModal.vue'
import InvoiceModal from './InvoiceModal.vue'
import placeholderImage from '../assets/avatar.png'

library.add(faBoxes, faSearch, faShoppingCart, faTrash, faMinus, faPlus, faCheck, faXmark)

// ========== ÉTATS ==========
const isPaymentModalOpen = ref(false)
const isInvoiceModalOpen = ref(false)

const currentSaleId = ref(null)
const currentInvoiceNumber = ref('')
const currentPaymentMethod = ref('')
const paymentsList = ref([])

const cart = ref([])
const categories = ref([])
const products = shallowRef([]) // Optimisation : pas de réactivité profonde sur les objets produits
const activeCategoryId = ref(null)
const searchQuery = ref('')
const user = ref(null)
const selectedDiscount = ref(0)
const isLoading = ref(false)

// Router pour redirection
const router = useRouter()

// ========== COMPUTED ==========
const totalPrice = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0)
})

// Filtrage ultra-rapide via computed
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

const saleDataForModal = computed(() => ({
  items: cart.value.map(item => ({
    product_id: item.id,
    quantity: item.quantity,
    unit_price: Math.round(item.price),
    total: Math.round(item.price * item.quantity),
    name: item.name
  })),
  total_amount: Math.round(totalPrice.value),
  customer_id: null,
  point_of_sale_id: user.value?.point_of_sale_id || null
}))

// ========== MÉTHODES ==========
const formatPrice = (price) => {
  const value = Number.parseFloat(price) || 0
  return `${value.toLocaleString('fr-FR')} Ar`
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
const addToCart = (product) => {
  const uniqueId = product.id || product.product_id;
  const existing = cart.value.find(p => (p.id || p.product_id) === uniqueId);

  if (existing) {
    existing.quantity++;
  } else {
    cart.value.push({
      ...product,
      id: uniqueId,
      quantity: 1,
      price: Number(product.price) || 0,
      category: product.category || { name: product.category_name },
      printer: product.printer || product.category?.printer || null
    });
  }
}

const incrementQuantity = (item) => { item.quantity++ }
const decrementQuantity = (item) => {
  if (item.quantity > 1) item.quantity--
  else removeItem(item)
}

const removeItem = (item) => {
  cart.value = cart.value.filter(i => i.id !== item.id)
}

const clearCart = () => {
  cart.value = []
}

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
      price: product.pricing?.[0]?.price
        ? Number.parseFloat(product.pricing[0].price)
        : 0,
    }))
  ))
}

const loadData = async (forceRefresh = false) => {
  const auth = storage.getAuth()
  if (!user.value?.point_of_sale_id || !auth?.token) return

  try {
    isLoading.value = true
    // Utilisation du cache pour un affichage instantané
    const data = await dataCacheService.getCategories(
      user.value.point_of_sale_id,
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

// ========== VÉRIFICATION SESSION ACTIVE ==========
const checkActiveSessionAndRedirect = async () => {
  const auth = storage.getAuth()
  if (!auth?.token) {
    router.push({ name: 'login' })
    return false
  }

  try {
    const response = await axios.get(`${API_BASE_URL}/my-active-session`, {
      headers: { Authorization: `Bearer ${auth.token}` }
    })
    const hasSession = response.data?.has_active_session === true
    if (!hasSession) {
      router.push({ name: 'cash-registers-machine-link' })
      return false
    }
    return true
  } catch (error) {
    console.error('Erreur vérification session:', error)
    router.push({ name: 'cash-registers-machine-link' })
    return false
  }
}

// ========== INITIALISATION ==========
onMounted(async () => {
  const sessionOk = await checkActiveSessionAndRedirect()
  if (!sessionOk) return

  const auth = storage.getAuth()
  if (auth?.user) {
    user.value = auth.user
  }

  // Premier chargement (depuis cache si possible)
  await loadData(false)

  // Mise à jour silencieuse en arrière-plan pour garantir la fraîcheur des données
  setTimeout(() => loadData(true), 1000)
})

const handleClosePaymentModal = () => {
  isPaymentModalOpen.value = false
}

const handlePaymentSuccess = (data) => {
  const saleId = data.sale_id || data.id
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
  const ticketNumber = data.ticket_number || data.sale?.ticket_number
  currentInvoiceNumber.value = ticketNumber ? `Ticket #${ticketNumber}` : `INV-${saleId.toString().padStart(6, '0')}`
  currentPaymentMethod.value = paymentsList.value[0]?.payment_method_name || 'Espèces'

  isPaymentModalOpen.value = false
  isInvoiceModalOpen.value = true
}

// Correction: handlePaymentError function was missing
const handlePaymentError = (error) => {
  console.warn('Erreur lors du paiement:', error); // Use warn for less critical logs
}

const handleClearCart = () => {
  cart.value = []; // Clear cart state
  console.log('Cart cleared in DirectSale.'); // Concise log
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

<style scoped>
.direct-sale-layout {
  min-height: calc(100vh - 5rem);
  min-height: calc(100dvh - 5rem);
}

@media (min-width: 1024px) {
  .direct-sale-layout {
    height: calc(100vh - 5.5rem);
    height: calc(100dvh - 5.5rem);
    max-height: calc(100vh - 5.5rem);
    max-height: calc(100dvh - 5.5rem);
    overflow: hidden;
  }
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.overflow-y-auto::-webkit-scrollbar {
  width: 4px;
}
.overflow-y-auto::-webkit-scrollbar-track {
  background: transparent;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 10px;
}
</style>
