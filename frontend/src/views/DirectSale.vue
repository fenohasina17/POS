<template>
  <!-- <div class="flex flex-col min-h-screen bg-gray-100 text-gray-800"> -->

    <!-- Modals -->
    <PaymentModal
      :isOpen="isPaymentModalOpen"
      :totalAmount="totalPrice"
      @close-modal="handleCloseModal"
      @confirm-payment="handlePaymentConfirmation"
    />
    <InvoiceModal
      :isOpen="isInvoiceModalOpen"
      :items="cart"
      :total="totalPrice"
      :clientName="'Client'"
      :invoiceNumber="currentInvoiceNumber"
      :paymentMethod="currentPaymentMethod"
      @close-modal="closeInvoiceModal"
      @openPaymentModal="openPaymentModal"
    />

    <div class="direct-sale-layout grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
      <!-- Produits -->
      <section class="flex min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800">
              <FontAwesomeIcon icon="fa-solid fa-boxes" />
              Produits
            </h2>
            <div class="relative w-full sm:max-w-xs">
              <FontAwesomeIcon
                icon="fa-solid fa-search"
                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
              />
              <input
                type="text"
                placeholder="Rechercher un produit..."
                v-model="searchQuery"
                @input="filterProducts"
                class="w-full rounded-full border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
              />
            </div>
          </div>

          <div class="flex gap-2 overflow-x-auto pb-1">
            <button
              type="button"
              class="rounded-full px-4 py-2 text-sm font-semibold transition"
              :class="[
                activeCategoryId === null
                  ? 'bg-indigo-500 text-white shadow'
                  : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
              ]"
              @click="setActiveCategory(null)"
            >
              Toutes
            </button>
            <button
              v-for="category in categories"
              :key="category.id"
              type="button"
              class="rounded-full px-4 py-2 text-sm font-semibold transition"
              :class="[
                activeCategoryId === category.id
                  ? 'bg-indigo-500 text-white shadow'
                  : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
              ]"
              @click="setActiveCategory(category.id)"
            >
              {{ category.name }}
            </button>
          </div>
        </div>

        <div class="mt-2.5 flex-1 overflow-hidden">
          <div
            v-if="filteredProducts.length"
            class="grid h-full grid-cols-1 gap-2.5 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
          >
            <button
              v-for="product in filteredProducts"
              :key="product.id"
              type="button"
              class="group flex flex-col rounded-3xl border border-slate-100 bg-white p-3 text-left shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
              @click="addToCart(product)"
            >
              <div class="overflow-hidden rounded-2xl bg-slate-100">
                <img
                  :src="getProductImageUrl(product)"
                  class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105"
                  @error="handleImageError"
                  loading="lazy"
                />
              </div>
              <div class="mt-3 space-y-1">
                <p class="text-sm font-semibold text-slate-900">{{ product.name }}</p>
                <p class="text-xs text-slate-400">{{ product.category_name || '—' }}</p>
                <p class="text-sm font-bold text-slate-900">
                  {{ formatPrice(product.price) }}
                  <span class="ml-1 text-xs font-medium text-slate-400">/portion</span>
                </p>
              </div>
            </button>
          </div>

          <div
            v-else
            class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 py-10 text-center text-sm text-slate-500"
          >
            <FontAwesomeIcon icon="fa-solid fa-boxes" class="mb-3 text-2xl text-slate-300" />
            Aucun produit disponible pour cette catégorie.
          </div>
        </div>
      </section>

      <!-- Panier -->
      <aside class="flex h-full min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
          <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800">
            <FontAwesomeIcon icon="fa-solid fa-shopping-cart" />
            Panier
          </h2>
          <button
            v-if="cart.length"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
            @click="clearCart"
          >
            <FontAwesomeIcon icon="fa-solid fa-trash" />
            Vider
          </button>
        </div>

        <div v-if="cart.length > 0" class="flex-1 space-y-3.5 overflow-y-auto py-3">
          <div
            v-for="item in cart"
            :key="item.id"
            class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
          >
            <div class="flex items-start justify-between">
              <div>
                <p class="text-sm font-semibold text-slate-800">{{ item.name }}</p>
                <p class="text-xs text-slate-400">{{ formatPrice(item.price) }}</p>
              </div>
          <button
            type="button"
            class="text-slate-400 transition hover:text-rose-500"
            @click="removeItem(item)"
          >
            <FontAwesomeIcon icon="fa-solid fa-xmark" />
          </button>
            </div>
            <div class="mt-3 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <button
                  type="button"
                  class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                  @click="decrementQuantity(item)"
                >
                  <FontAwesomeIcon icon="fa-solid fa-minus" />
                </button>
                <span class="text-sm font-semibold text-slate-700">{{ item.quantity }}</span>
                <button
                  type="button"
                  class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
                  @click="incrementQuantity(item)"
                >
                  <FontAwesomeIcon icon="fa-solid fa-plus" />
                </button>
              </div>
              <span class="text-sm font-semibold text-indigo-600">
                {{ formatPrice(item.price * item.quantity) }}
              </span>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 py-10 text-center text-sm text-slate-400">
          <FontAwesomeIcon icon="fa-solid fa-shopping-cart" class="mb-3 text-2xl" />
          Panier vide
        </div>

        <div class="mt-3 rounded-2xl border border-slate-100 bg-slate-50 p-3.5">
          <div class="flex items-center justify-between text-sm font-semibold text-slate-700">
            <span>Total</span>
            <span class="text-indigo-600">{{ formatPrice(totalPrice) }}</span>
          </div>
          <button
            type="button"
            class="mt-4 w-full rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-400"
            @click="checkout"
            :disabled="!cart.length"
          >
            <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-white/20">
              <FontAwesomeIcon icon="fa-solid fa-check" />
            </span>
            Valider la commande
          </button>
        </div>
      </aside>
    </div>
</template>


<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { API_BASE_URL, API_URL } from '@/utils/api'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faList,
  faFolder,
  faBoxes,
  faSearch,
  faShoppingCart,
  faTrash,
  faMinus,
  faPlus,
  faCheck,
  faXmark
} from '@fortawesome/free-solid-svg-icons'
import PaymentModal from './PaymentModal.vue'
import InvoiceModal from './InvoiceModal.vue'
import placeholderImage from '../assets/avatar.png'
import { useCashTransactionStore } from '@/stores/cashTransactionStore'
import { useCategories } from '@/composables/useCategories'

// Modal controls
const isPaymentModalOpen = ref(false)
const isInvoiceModalOpen = ref(false)

const cashTransactionStore = useCashTransactionStore()

const { categories, products, filteredProducts, activeCategory, loadCategories, loadProducts } = useCategories()

const getProductImageUrl = (product) => {
  const raw = product?.image || product?.product?.image
  if (!raw) return placeholderImage
  if (/^https?:\/\//i.test(raw)) return raw
  if (raw.startsWith('storage/')) return `${API_URL}/${raw}`
  if (raw.startsWith('products/')) return `${API_URL}/storage/${raw}`
  return `${API_URL}/storage/products/${raw}`
}

const openPaymentModal = () => {
  isInvoiceModalOpen.value = false
  isPaymentModalOpen.value = true
}

const handleCloseModal = () => {
  isPaymentModalOpen.value = false
}

const paymentMethodMap = {
  'TPE': 1,
  'Orange Money': 2,
  'MVola': 3,
  'Espèce': 4,
  'Airtel Money': 5
}

const currentInvoiceNumber = ref('')
const currentPaymentMethod = ref('')

const generateInvoiceNumber = () => {
  return 'INV-' + Date.now()
}

const closeInvoiceModal = () => {
  isInvoiceModalOpen.value = false
}

const handlePaymentConfirmation = async (paymentData) => {
  console.log('Paiement confirmé:', paymentData)
  const token = localStorage.getItem('token')
  const user = JSON.parse(localStorage.getItem('user'))
  if (!user) {
    console.error('Utilisateur non authentifié')
    return
  }
  try {
    const sessionData = await fetchCurrentSession()
    const sessionStartTicket = Number(sessionData?.start_ticket_number)

    const ticketNumberValue = Number.isInteger(sessionStartTicket) && sessionStartTicket >= 0
      ? sessionStartTicket
      : Date.now()

    const ticketNumber = ticketNumberValue.toString()
    const totalAmount = cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0)
    const discount = Number(paymentData.discount_percentage ?? 0)
    const finalAmount = Number(
      paymentData.final_total !== undefined
        ? paymentData.final_total
        : (totalAmount * (1 - discount / 100)).toFixed(2)
    )

    const saleData = {
      user_id: user.id,
      point_of_sale_id: user.point_of_sale_id,
      cash_register_session_id: sessionData?.id || null,
      total_amount: Math.round(totalAmount), // Convert to integer
      discount_percentage: discount,
      final_amount: Math.round(finalAmount),
      amount_received: paymentData.received || 0,
      change_returned: paymentData.change || 0,
      payment_method: paymentData.method,
      status: paymentData.status || 'completed',
      date: new Date().toISOString().split('T')[0],
      payment_id: paymentMethodMap[paymentData.method] || null,
      ticket_number: Number(ticketNumber),
      items: cart.value.map(item => ({
        product_id: item.id,
        quantity: item.quantity,
        unit_price: Math.round(item.price),
        total: Math.round(item.price * item.quantity)
      }))
    }

    const response = await axios.post(`${API_BASE_URL}/sales`, saleData, {
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`
      }
    })

    const saleId = response.data.id

    if (sessionData?.id) {
      try {
        await axios.post(`${API_BASE_URL}/cash-transactions`, {
          session_id: sessionData.id,
          type: 'sale',
          amount: finalAmount,
          description: `Vente ticket #${ticketNumber}`
        }, {
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`
          }
        })
        await cashTransactionStore.fetchTransactions()
      } catch (transactionError) {
        console.error('Erreur lors de la mise à jour des transactions de caisse:', transactionError.response?.data || transactionError.message)
      }
    }

    try {
      await axios.post(`${API_BASE_URL}/printers/invoice/${saleId}`, {}, {
        headers: {
          Authorization: `Bearer ${token}`
        }
      })
    } catch (printError) {
      console.error('Erreur lors de l\'impression de la facture:', printError.response?.data || printError.message)
    }


    saleData.value = {
      ...response.data,
      items: [...cart.value],
      paymentMethod: paymentData.method,
      finalAmount
    }



    currentInvoiceNumber.value = `INV-${response.data.id || Date.now()}`
    currentPaymentMethod.value = paymentData.method

    clearCart()
    handleCloseModal()
  } catch (error) {
    console.error('Erreur lors de la création de la vente:', error.response?.data || error.message)
    handleCloseModal()
  }
}

defineExpose({
  openPaymentModal
})

const checkout = () => {
  if (cart.value.length === 0) return
  currentInvoiceNumber.value = generateInvoiceNumber()
  currentPaymentMethod.value = 'En attente'
  isInvoiceModalOpen.value = true
}

const authHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error('Token manquant')
  return { Authorization: `Bearer ${token}` }
}

const fetchCurrentSession = async () => {
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-session/my-active-session`, {
      headers: authHeaders()
    })
    return data?.data || data || null
  } catch (error) {
    console.error('Impossible de récupérer la session de caisse:', error.response?.data || error.message)
    return null
  }
}

// Add icons to library
library.add(faList, faFolder, faBoxes, faSearch, faShoppingCart, faTrash, faMinus, faPlus, faCheck, faXmark)

const cart = ref([])
const activeCategoryId = ref(null)
const searchQuery = ref('')

const formatPrice = (price) => {
  const value = Number.parseFloat(price)
  if (!Number.isFinite(value)) return '—'
  return `${value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Ar`
}

const applyFilters = () => {
  let base = products.value
  const categoryId = activeCategoryId.value

  if (categoryId !== null) {
    base = base.filter((product) => product.category_id === categoryId)
  }

  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    base = base.filter(
      (product) =>
        product.name.toLowerCase().includes(query) ||
        (product.description && product.description.toLowerCase().includes(query))
    )
  }

  filteredProducts.value = base
}

const setActiveCategory = (categoryId) => {
  activeCategoryId.value = categoryId
  applyFilters()
}

const filterProducts = () => {
  applyFilters()
}

const addToCart = (product) => {
  const existing = cart.value.find(p => p.id === product.id)
  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({
      ...product,
      quantity: 1,
      price: Number.isFinite(product.price) ? product.price : 0
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
  cart.value = cart.value.filter(i => i.id !== item.id)
}

const clearCart = () => {
  cart.value = []
}

const totalPrice = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0)
})

onMounted(async () => {
  await loadCategories()
  const user = JSON.parse(localStorage.getItem('user'))
  const token = localStorage.getItem('token')

  if (!user?.point_of_sale_id || !token) {
    console.error('Utilisateur non authentifié ou point de vente non défini')
    return
  }

  try {
    const response = await axios.get(`${API_BASE_URL}/categories`, {
      params: {
        'with_products': 1,
        'point_of_sale_id': user.point_of_sale_id,
        'with_pricing': 1,
      },
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`
      }
    })
    const data = Array.isArray(response.data) ? response.data : response.data.data || []
    categories.value = data
    products.value = data.flatMap((category) =>
      (category.products || []).map((product) => ({
        ...product,
        category_id: category.id,
        category_name: category.name,
        price: product.pricing?.[0]?.price ? Number.parseFloat(product.pricing[0].price) : 0,
      }))
    )

    activeCategoryId.value = null
    applyFilters()
  } catch (error) {
    console.error('Erreur de chargement des produits :', error.response?.data || error.message)
  }
})

const handleImageError = (event) => {
  if (!event?.target) {
    return
  }
  event.target.onerror = null
  event.target.src = placeholderImage
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
</style>
