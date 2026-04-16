<template>
  <div class="flex flex-col gap-4 pb-6">
    <Profile v-if="!embedded" class="mb-2" />

    <section
      class="sticky top-[3rem] z-30 rounded-3xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/80 sm:top-[3.25rem] lg:top-[3.5rem]"
    >
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
            <FontAwesomeIcon icon="fa-solid fa-table" />
          </span>
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Table en service</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
              <span class="text-lg font-semibold text-slate-900">
                {{ selectedTable ? `Table ${selectedTable.table_number}` : 'Aucune table sélectionnée' }}
              </span>
              <span v-if="selectedTable?.name" class="text-sm text-slate-500">— {{ selectedTable.name }}</span>
              <span
                v-if="selectedTable"
                class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold"
                :class="statusBadgeClass(selectedTable.status)"
              >
                <FontAwesomeIcon :icon="getStatusIcon(selectedTable.status)" />
                {{ getStatusText(selectedTable.status) }}
              </span>
            </div>
            <p v-if="selectedTable" class="text-xs text-slate-400">
              {{ selectedTable?.point_of_sale?.name || 'Point de vente non défini' }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-600">
            Articles : <span class="text-slate-900">{{ cart.length }}</span>
          </span>
          <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-600">
            Total : <span class="text-slate-900">{{ formatPrice(totalPrice) }}</span>
          </span>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
            @click="openTableSelector"
          >
            <FontAwesomeIcon icon="fa-solid fa-table-list" />
            {{ selectedTable ? 'Changer de table' : 'Sélectionner une table' }}
          </button>
        </div>
      </div>

      <div
        v-if="currentPendingOrder"
        class="mt-4 flex flex-wrap items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700"
      >
        <FontAwesomeIcon icon="fa-solid fa-clock-rotate-left" />
        Commande en attente n° {{ currentPendingOrder.reference || currentPendingOrder.id }}
      </div>
    </section>

    <div class="grid gap-4 min-h-[calc(100vh-6.5rem)] lg:grid-cols-[minmax(0,1fr)_320px]">
      <section
        class="sticky top-[5.25rem] flex max-h-[calc(100vh-6.5rem)] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:top-[5.5rem] lg:top-[5.75rem]"
      >
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-3">
          <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800">
                <FontAwesomeIcon icon="fa-solid fa-boxes" />
                Produits
              </h2>
              <p class="text-xs text-slate-400">Ajoutez des articles au panier de la table sélectionnée.</p>
            </div>
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
                :disabled="isInteractionLocked"
                class="w-full rounded-full border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-600 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100"
              />
            </div>
          </header>

          <div class="flex gap-2 overflow-x-auto pb-1">
            <button
              type="button"
              class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition"
              :class="[
                activeCategory ? 'bg-slate-100 text-slate-500 hover:bg-slate-200' : 'bg-indigo-500 text-white shadow'
              ]"
              @click="showAllProducts"
            >
              Toutes
            </button>
            <button
              v-for="category in categories"
              :key="category.id"
              type="button"
              class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition"
              :class="[
                activeCategory?.id === category.id
                  ? 'bg-indigo-500 text-white shadow'
                  : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
              ]"
              :disabled="isInteractionLocked"
              @click="loadProducts(category)"
            >
              {{ category.name }}
            </button>
          </div>

          <div
            v-if="currentPendingOrder"
            class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700"
          >
            <FontAwesomeIcon icon="fa-solid fa-clock-rotate-left" />
            Commande en attente n° {{ currentPendingOrder.reference || currentPendingOrder.id }}
          </div>
        </div>

        <div class="mt-3 flex-1 overflow-hidden">
          <div v-if="filteredProducts.length" class="h-full overflow-y-auto pr-1">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              <button
                v-for="product in filteredProducts"
                :key="product.id"
                type="button"
                :disabled="isInteractionLocked || !canSelectProduct(product)"
                :class="productCardClasses(product)"
                @click="addToCart(product, $event)"
              >
                <div class="relative overflow-hidden rounded-2xl bg-slate-100">
                  <img
                    :src="getProductImageUrl(product)"
                    :alt="product.name"
                    class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105"
                    @error="handleImageError"
                  />
                  <span
                    v-if="!canSelectProduct(product)"
                    class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-600"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-triangle-exclamation" />
                    Rupture
                  </span>
                  <span
                    v-else-if="isProductOnPromotion(product)"
                    class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-600"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-fire" />
                    Promo
                  </span>
                  <span
                    v-else-if="isProductPopular(product)"
                    class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-600"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-star" />
                    Populaire
                  </span>
                </div>
                <div class="mt-3 space-y-1 text-left">
                  <p class="text-sm font-semibold text-slate-900">{{ product.name }}</p>
                  <p class="text-xs text-slate-400">{{ product.category_name || '—' }}</p>
                  <div class="flex items-center justify-between text-sm font-semibold text-slate-900">
                    <span class="flex items-baseline gap-1">
                      {{ formatPrice(product.price) }}
                      <span class="text-xs font-medium text-slate-400">/portion</span>
                    </span>
                    <span
                      v-if="product.stock !== null && product.stock !== undefined"
                      class="text-xs font-medium text-slate-400"
                    >
                      Stock: {{ product.stock }}
                    </span>
                  </div>
                </div>
              </button>
            </div>
          </div>

          <div
            v-else
            class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 py-10 text-center text-sm text-slate-500"
          >
            <FontAwesomeIcon icon="fa-solid fa-boxes" class="mb-3 text-2xl text-slate-300" />
            Aucun produit disponible pour cette sélection.
          </div>
        </div>
      </section>

      <aside
        class="sticky top-[5.25rem] flex max-h-[calc(100vh-6.5rem)] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:top-[5.5rem] lg:top-[5.75rem]"
      >
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h2 class="flex items-center gap-2 text-base font-semibold text-slate-800">
            <FontAwesomeIcon icon="fa-solid fa-shopping-cart" />
            Panier
          </h2>
          <button
            v-if="cart.length"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 disabled:opacity-60"
            @click="clearCart"
            :disabled="isInteractionLocked"
          >
            <FontAwesomeIcon icon="fa-solid fa-trash" />
            Vider
          </button>
        </div>

        <div class="mt-3 flex-1 overflow-hidden">
          <div v-if="cart.length" class="h-full space-y-3 overflow-y-auto pr-1">
            <div
              v-for="item in cart"
              :key="item.id"
              class="rounded-2xl border border-slate-100 bg-slate-50/60 p-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-slate-800">{{ item.name }}</p>
                  <p class="text-xs text-slate-400">{{ formatPrice(item.price) }}</p>
                </div>
                <button
                  type="button"
                  class="text-slate-400 transition hover:text-rose-500 disabled:opacity-60"
                  @click="removeItem(item)"
                  :disabled="isInteractionLocked"
                >
                  <FontAwesomeIcon icon="fa-solid fa-xmark" />
                </button>
              </div>
              <div class="mt-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600 disabled:opacity-60"
                    @click="decrementQuantity(item)"
                    :disabled="isInteractionLocked"
                  >
                    <FontAwesomeIcon icon="fa-solid fa-minus" />
                  </button>
                  <span class="text-sm font-semibold text-slate-700">{{ item.quantity }}</span>
                  <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600 disabled:opacity-60"
                    @click="incrementQuantity(item)"
                    :disabled="isInteractionLocked"
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

          <div
            v-else
            class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 py-10 text-center text-sm text-slate-400"
          >
            <FontAwesomeIcon icon="fa-solid fa-shopping-cart" class="mb-3 text-2xl" />
            Panier vide
            <p class="mt-1 text-xs">Sélectionnez une table et ajoutez des produits.</p>
          </div>
        </div>

        <div class="mt-3 space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-3">
          <div class="flex items-center justify-between text-sm font-semibold text-slate-700">
            <span>Total</span>
            <span class="text-indigo-600">{{ formatPrice(totalPrice) }}</span>
          </div>

          <div class="flex flex-col gap-2 pt-2">
            <button
              v-if="cart.length > 0 && !currentPendingOrder"
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-100 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-200 disabled:cursor-not-allowed disabled:opacity-60"
              @click="holdOrder"
              :disabled="!selectedTable || isInteractionLocked"
            >
              <FontAwesomeIcon icon="fa-solid fa-receipt" />
              Envoyer & imprimer
            </button>

            <button
              v-if="currentPendingOrder && isInteractionLocked"
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
              @click="beginAddToPending"
            >
              <FontAwesomeIcon icon="fa-solid fa-plus" />
              Ajouter de nouveaux articles
            </button>

            <button
              v-if="currentPendingOrder && isAddingToPending"
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600 disabled:opacity-60"
              @click="confirmAddToPending"
              :disabled="cart.length === 0"
            >
              <FontAwesomeIcon icon="fa-solid fa-check" />
              Commander & imprimer
            </button>

            <button
              v-if="cart.length > 0 || currentPendingOrder"
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-indigo-400"
              @click="handleCheckout"
              :disabled="!selectedTable"
            >
              <FontAwesomeIcon icon="fa-solid fa-check-circle" />
              {{ currentPendingOrder ? 'Valider commande' : 'Valider la commande' }}
            </button>
          </div>
        </div>
      </aside>
    </div>

    <TableSelectorModal
      :is-open="showTableSelector"
      @close="closeTableSelector"
      @table-selected="onTableSelected"
    />

    <PaymentModal
      :is-open="isPaymentModalOpen"
      :total-amount="totalPrice"
      @close-modal="handleCloseModal"
      @confirm-payment="handlePaymentConfirmation"
    />

    <InvoiceModal
      :is-open="isInvoiceModalOpen"
      :items="cart"
      :total="totalPrice"
      :client-name="selectedTable ? `Table ${selectedTable.table_number}` : 'Client'"
      :invoice-number="currentInvoiceNumber"
      :payment-method="currentPaymentMethod"
      @close-modal="closeInvoiceModal"
      @openPaymentModal="openPaymentModal"
    />
  </div>
</template>


<script>
import axios from 'axios'
import TableSelectorModal from '../components/TableSelectorModal.vue'
import PaymentModal from './PaymentModal.vue'
import InvoiceModal from './InvoiceModal.vue'
import Profile from './Profile.vue'
import placeholderImage from '../assets/avatar.png'
import { useAuth } from '@/composables/useAuth'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { API_BASE_URL, API_URL } from '@/utils/api'

export default {
  name: 'TableSale',
  components: {
    TableSelectorModal,
    PaymentModal,
    InvoiceModal,
    Profile,
    FontAwesomeIcon
  },
  props: {
    tableId: {
      type: [Number, String],
      default: null
    },
    embedded: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      categories: [],
      products: [],
      filteredProducts: [],
      activeCategory: null,
      cart: [],
      searchQuery: '',
      selectedTable: null,
      showTableSelector: false,
      isPaymentModalOpen: false,
      isInvoiceModalOpen: false,
      currentInvoiceNumber: '',
      currentPaymentMethod: '',
      currentPendingOrder: null,
      existingPendingLines: [],
      lastAdditionLines: [],
      categoryPrinterTypes: {},
      productCatalog: {},
      printersCache: [],
      isAddingToPending: false,
      user: { name: '', email: '', point_of_sale_name: '' }
    }
  },
  computed: {
    totalPrice() {
      return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)
    },

    isInteractionLocked() {
      return this.currentPendingOrder && !this.isAddingToPending
    },

    isAdmin() {
      const { isAdmin } = useAuth()
      return isAdmin.value
    }
  },
  watch: {
    tableId: {
      immediate: true,
      handler(newVal) {
        this.handleTableIdChange(newVal)
      }
    }
  },
  methods: {
    getCurrentUserPointOfSaleId() {
      try {
        const user = JSON.parse(localStorage.getItem('user') || '{}')
        const pointOfSaleId = Number(user?.point_of_sale_id)
        return Number.isFinite(pointOfSaleId) && pointOfSaleId > 0 ? pointOfSaleId : null
      } catch (error) {
        return null
      }
    },

    resolveTablePointOfSaleId(table) {
      const pointOfSaleId = Number(
        table?.point_of_sale_id ??
        table?.pointOfSaleId ??
        table?.point_of_sale?.id ??
        table?.pointOfSale?.id ??
        null
      )

      return Number.isFinite(pointOfSaleId) && pointOfSaleId > 0 ? pointOfSaleId : null
    },

    normalizeStatus(status) {
      const normalized = String(status || 'available').trim().toLowerCase()
      const aliases = {
        disponible: 'available',
        available: 'available',
        libre: 'available',
        occupee: 'occupied',
        occupée: 'occupied',
        occupied: 'occupied',
        reservee: 'reserved',
        réservée: 'reserved',
        reserved: 'reserved',
        hors_service: 'out_of_order',
        horsservice: 'out_of_order',
        out_of_order: 'out_of_order',
        outoforder: 'out_of_order'
      }

      return aliases[normalized] || normalized
    },

    showAllProducts() {
      this.activeCategory = null
      if (Array.isArray(this.products)) {
        this.filteredProducts = this.products.map((product) => ({
          ...product,
          category_name:
            product.category_name ??
            product?.category?.name ??
            this.categories.find((cat) => cat.id === (product.category_id ?? product?.category?.id))?.name ??
            '—',
        }))
      } else {
        this.filteredProducts = []
      }
      if (this.searchQuery) {
        this.filterProducts()
      }
    },

    statusBadgeClass(status) {
      switch (status) {
        case 'available':
          return 'border-emerald-200 bg-emerald-50 text-emerald-600'
        case 'occupied':
          return 'border-amber-200 bg-amber-50 text-amber-700'
        case 'reserved':
          return 'border-indigo-200 bg-indigo-50 text-indigo-600'
        case 'out_of_order':
          return 'border-rose-200 bg-rose-50 text-rose-600'
        default:
          return 'border-slate-200 bg-slate-50 text-slate-500'
      }
    },

    productCardClasses(product) {
      const base =
        'product-card group flex flex-col rounded-3xl border bg-white p-3 text-left shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200'
      if (this.isInteractionLocked || !this.canSelectProduct(product)) {
        return `${base} border-rose-200 opacity-60 cursor-not-allowed`
      }
      return `${base} border-slate-100 hover:border-indigo-200`
    },

    getProductImageUrl(product) {
      const raw = product?.image || product?.product?.image
      if (!raw) return placeholderImage
      if (/^https?:\/\//i.test(raw)) return raw
      if (raw.startsWith('storage/')) return `${API_URL}/${raw}`
      if (raw.startsWith('products/')) return `${API_URL}/storage/${raw}`
      return `${API_URL}/storage/products/${raw}`
    },

    async handleTableIdChange(newVal) {
        if (newVal === undefined || newVal === null || newVal === '') {
          return
        }

        const numericId = Number(newVal)

      if (!Number.isFinite(numericId) || numericId <= 0) {
        console.warn('Identifiant de table invalide reçu:', newVal)
        return
      }

      if (this.selectedTable && Number(this.selectedTable.id) === numericId) {
        return
      }

      await this.loadTableFromRoute(numericId)
    },



    async loadTableFromRoute(tableId) {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(`${API_BASE_URL}/tables/${tableId}`, {
          params: {
            with_sales: 1,
            with_point_of_sale: 1
          },
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        })

        const table = this.normalizeTableResponse(response.data)
        const userPointOfSaleId = this.getCurrentUserPointOfSaleId()

        if (!table) {
          console.warn('Impossible de charger la table demandée')
          this.showNotification('Table introuvable', 'error')
          return
        }

        if (userPointOfSaleId && this.resolveTablePointOfSaleId(table) !== userPointOfSaleId) {
          console.warn('Table hors du point de vente de l’utilisateur:', table)
          this.showNotification('Cette table n\'appartient pas a votre point de vente', 'error')
          return
        }

        await this.onTableSelected({
          ...table,
          status: this.normalizeStatus(table.status)
        })
      } catch (error) {
        console.error('Erreur lors du chargement de la table:', error.response?.data || error.message)
        this.showNotification('Erreur lors du chargement de la table', 'error')
      }
    },

    normalizeTableResponse(payload) {
      if (!payload) return null

      if (Array.isArray(payload)) {
        return payload[0] || null
      }

      if (typeof payload === 'object' && payload !== null && Object.prototype.hasOwnProperty.call(payload, 'data')) {
        return this.normalizeTableResponse(payload.data)
      }

      return payload
    },

    syncRouteWithTable(tableId) {
      if (!this.$route || this.$route.name !== 'dashboard-table-order') {
        return
      }

      const currentParam = this.$route.params?.tableId

      if (tableId === null || tableId === undefined || tableId === '') {
        if (currentParam === undefined) {
          return
        }
        this.$router.replace({ name: 'dashboard-table-order', params: {} })
        return
      }

      if (String(currentParam) === String(tableId)) {
        return
      }

      this.$router.replace({
        name: 'dashboard-table-order',
        params: { tableId }
      })
    },

    formatPrice(price) {
      const value = Number.parseFloat(price)
      if (!Number.isFinite(value)) return '—'
      return `${value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Ar`
    },

    resolveCategoryPrinterTypeId(category) {
      if (!category) return null
      if (category.printer_type_id) return category.printer_type_id
      if (typeof category.printer_type === 'number') return category.printer_type
      if (category.printer_type && typeof category.printer_type === 'object') {
        return category.printer_type.id ?? category.printer_type.printer_type_id ?? null
      }
      if (category.id && this.categoryPrinterTypes[category.id]) {
        return this.categoryPrinterTypes[category.id]
      }
      return null
    },

    resolveProductPrinterTypeId(product, fallbackCategory = null) {
      if (!product) return null
      if (product.printer_type_id) return product.printer_type_id
      if (typeof product.printer_type === 'number') return product.printer_type
      if (product.printer_type && typeof product.printer_type === 'object') {
        return product.printer_type.id ?? product.printer_type.printer_type_id ?? null
      }
      const resolvedCategory = product.category || fallbackCategory || (product.category_id ? { id: product.category_id } : null)
      if (resolvedCategory && resolvedCategory.printer_type_id) {
        return resolvedCategory.printer_type_id
      }
      if (resolvedCategory && resolvedCategory.printer_type && typeof resolvedCategory.printer_type === 'object') {
        return resolvedCategory.printer_type.id ?? resolvedCategory.printer_type.printer_type_id ?? null
      }
      if (resolvedCategory && resolvedCategory.id && this.categoryPrinterTypes[resolvedCategory.id]) {
        return this.categoryPrinterTypes[resolvedCategory.id]
      }
      if (product.category_id && this.categoryPrinterTypes[product.category_id]) {
        return this.categoryPrinterTypes[product.category_id]
      }
      return null
    },

    registerProduct(product, overrides = {}) {
      if (!product || !product.id) return

      const category = overrides.category || product.category || null
      const categoryId = overrides.category_id ?? product.category_id ?? category?.id ?? null
      const resolvedCategoryPrinterType = overrides.printer_type_id ?? this.resolveProductPrinterTypeId(product, category) ?? (categoryId ? this.categoryPrinterTypes[categoryId] : null)
      const price = Number(overrides.price ?? product.price ?? (product.pricing?.[0]?.price ? parseFloat(product.pricing[0].price) : 0)) || 0

      if (categoryId && resolvedCategoryPrinterType && this.categoryPrinterTypes[categoryId] !== resolvedCategoryPrinterType) {
        this.categoryPrinterTypes = {
          ...this.categoryPrinterTypes,
          [categoryId]: resolvedCategoryPrinterType
        }
      }

      this.productCatalog = {
        ...this.productCatalog,
        [product.id]: {
          id: product.id,
          name: product.name,
          category_id: categoryId,
          printer_type_id: resolvedCategoryPrinterType,
          price,
          product: { ...product }
        }
      }
    },

    aggregateLineItems(lines = []) {
      if (!Array.isArray(lines) || lines.length === 0) {
        return []
      }

      const aggregated = new Map()

      lines.forEach(line => {
        if (!line) return

        const product = line.product || null
        const productId = line.product_id ?? line.id ?? product?.id ?? null
        if (!productId) return

        const price = Number(line.price ?? line.unit_price ?? line.unitPrice ?? product?.price ?? 0) || 0
        const categoryId = line.category_id ?? product?.category_id ?? null
        const printerTypeId = line.printer_type_id ?? product?.printer_type_id ?? null
        const key = `${productId}|${price}|${categoryId ?? ''}|${printerTypeId ?? ''}`

        const name = line.name || product?.name || 'Produit'
        const quantity = Number(line.quantity ?? line.qty ?? 0) || 0
        const total = Number(line.total ?? line.total_amount ?? line.amount ?? quantity * price) || 0

        if (aggregated.has(key)) {
          const existing = aggregated.get(key)
          existing.quantity += quantity
          existing.total += total
        } else {
          aggregated.set(key, {
            ...line,
            product_id: productId,
            name,
            quantity,
            price,
            total,
            category_id: categoryId,
            printer_type_id: printerTypeId,
            product: product || line.product || null
          })
        }
      })

      return Array.from(aggregated.values()).map(item => ({
        ...item,
        quantity: Number(item.quantity.toFixed(3)),
        total: Number((Number(item.price || 0) * Number(item.quantity || 0)).toFixed(2))
      }))
    },

    prepareCartLines(cartItems = []) {
      if (!Array.isArray(cartItems) || cartItems.length === 0) {
        return []
      }

      const rawLines = cartItems.map(item => ({
        product_id: item.id,
        name: item.name,
        quantity: Number(item.quantity) || 0,
        price: Number(item.price) || 0,
        category_id: item.category_id ?? null,
        printer_type_id: item.printer_type_id ?? null,
        product: item
      }))

      return this.aggregateLineItems(rawLines)
    },

    normalizeProduct(product, category, fallbackPrinterTypeId = null) {
      if (!product) return null

      const normalizedCategoryId = product.category_id ?? category?.id ?? null
      const normalizedPrice = Number(
        product.price ??
        (Array.isArray(product.pricing) && product.pricing.length ? parseFloat(product.pricing[0].price) : 0)
      ) || 0
      const normalizedPrinterTypeId = this.resolveProductPrinterTypeId(product, category) ?? fallbackPrinterTypeId ?? (normalizedCategoryId ? this.categoryPrinterTypes[normalizedCategoryId] : null)

      const baseProduct = {
        ...product,
        category_id: normalizedCategoryId,
        price: normalizedPrice,
        printer_type_id: normalizedPrinterTypeId
      }

      const stock = this.resolveProductStock(baseProduct)
      const normalizedProduct = {
        ...baseProduct,
        stock: stock,
      }

      normalizedProduct.isAvailable = this.checkProductAvailability(normalizedProduct)

      this.registerProduct(normalizedProduct, {
        category,
        category_id: normalizedCategoryId,
        printer_type_id: normalizedPrinterTypeId,
        price: normalizedPrice
      })

      return normalizedProduct
    },

    getStatusIcon(status) {
      const icons = {
        'available': 'fas fa-check-circle',
        'occupied': 'fas fa-users',
        'reserved': 'fas fa-calendar-check',
        'out_of_order': 'fas fa-wrench'
      }
      return icons[status] || 'fas fa-question-circle'
    },

    getStatusText(status) {
      const texts = {
        'available': 'Disponible',
        'occupied': 'Occupée',
        'reserved': 'Réservée',
        'out_of_order': 'Hors service'
      }
      return texts[status] || 'Inconnu'
    },

    async loadCategories() {
      console.log('loadCategories: Starting function execution')

      try {
        const token = localStorage.getItem('token')
        console.log('loadCategories: Token retrieved:', token ? 'Present' : 'Missing')
        if (!token) {
          console.log('loadCategories: No token found, returning early')
          return
        }

        console.log('loadCategories: Making /me API call')
        const userResponse = await axios.get(`${API_BASE_URL}/me`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        })

        const user = userResponse.data.user
        console.log('loadCategories: User data received:', user)

        if (!user.point_of_sale_id) {
          console.log('loadCategories: No point_of_sale_id in user data, returning early')
          return
        }

        console.log('loadCategories: Making /categories API call with point_of_sale_id:', user.point_of_sale_id)
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

        const rawCategories = Array.isArray(response.data) ? response.data : response.data.data || []
        console.log('loadCategories: Raw categories received:', rawCategories.length, 'categories')

        const categoryPrinterMap = { ...this.categoryPrinterTypes }
        const aggregatedProducts = []

        console.log('loadCategories: Processing categories for printer types')
        rawCategories.forEach(category => {
          if (!category || !category.id) return
          const printerTypeId = this.resolveCategoryPrinterTypeId(category)
          if (printerTypeId) {
            categoryPrinterMap[category.id] = printerTypeId
          }
        })
        console.log('loadCategories: Category printer map after processing:', categoryPrinterMap)

        console.log('loadCategories: Normalizing products')
        rawCategories.forEach(category => {
          const fallbackPrinterTypeId = this.resolveCategoryPrinterTypeId(category) ?? (category?.id ? categoryPrinterMap[category.id] : null)
          if (Array.isArray(category?.products)) {
            category.products = category.products.map(product => {
              const normalized = this.normalizeProduct(product, category, fallbackPrinterTypeId)
              aggregatedProducts.push(normalized)
              return normalized
            })
          }
        })
        console.log('loadCategories: Aggregated products count:', aggregatedProducts.length)

        this.categories = rawCategories
        this.categoryPrinterTypes = categoryPrinterMap
        this.products = aggregatedProducts
        this.filteredProducts = aggregatedProducts

        console.log('loadCategories: Function completed successfully')
      } catch (error) {
        console.error('loadCategories: Error occurred:', error)
        console.error('loadCategories: Error response:', error.response?.data)
        console.error('loadCategories: Error status:', error.response?.status)
        console.error('loadCategories: Error message:', error.message)
        this.showNotification('Erreur lors du chargement des catégories', 'error')
      }
    },

    loadProducts(category) {
      this.activeCategory = category
      const fallbackPrinterTypeId = this.resolveCategoryPrinterTypeId(category)
      const products = category?.products || []

      this.filteredProducts = products.map(product => {
        const categoryId = product.category_id ?? category?.id ?? null
        const printerTypeId = product.printer_type_id ?? this.resolveProductPrinterTypeId(product, category) ?? fallbackPrinterTypeId
        const price = Number(
          product.price ??
          (Array.isArray(product.pricing) && product.pricing.length ? parseFloat(product.pricing[0].price) : 0)
        ) || 0
        const stock = this.resolveProductStock(product)

        const normalized = {
          ...product,
          category_id: categoryId,
          printer_type_id: printerTypeId,
          price,
          stock,
          category_name: category?.name ?? product.category_name ?? product?.category?.name ?? '—',
        }

        normalized.isAvailable = this.checkProductAvailability(normalized)

        this.registerProduct(normalized, {
          category,
          category_id: categoryId,
          printer_type_id: printerTypeId,
          price
        })

        return normalized
      })
    },

    resolveProductStock(product) {
      if (!product) return null

      const candidates = [
        product.stock,
        product.available_stock,
        product.quantity,
        product.available_quantity,
        product.inventory?.quantity,
        product.inventory?.stock
      ]

      for (const value of candidates) {
        if (typeof value === 'number' && Number.isFinite(value)) {
          return value
        }

        if (typeof value === 'string') {
          const trimmed = value.trim()
          if (trimmed !== '') {
            const parsed = Number(trimmed)
            if (Number.isFinite(parsed)) {
              return parsed
            }
          }
        }
      }

      return null
    },

    checkProductAvailability(product) {
      if (!product) {
        return false
      }

      if (Object.prototype.hasOwnProperty.call(product, 'is_available')) {
        return Boolean(product.is_available)
      }

      if (Object.prototype.hasOwnProperty.call(product, 'isAvailable')) {
        return Boolean(product.isAvailable)
      }

      const stock = this.resolveProductStock(product)
      const hasStock = stock === null || stock === undefined ? true : Number(stock) > 0

      const rawPrice = product.price ?? (Array.isArray(product.pricing) && product.pricing.length ? product.pricing[0].price : 0)
      const numericPrice = Number(rawPrice)
      const hasValidPrice = !Number.isNaN(numericPrice) && numericPrice > 0

      return hasStock && hasValidPrice
    },

    canSelectProduct(product) {
      if (this.isInteractionLocked) {
        return false
      }

      if (!product) {
        return false
      }

      if (product.isAvailable === false) {
        return false
      }

      const stockValue = this.resolveProductStock(product)
      if (stockValue === null || stockValue === undefined) {
        return true
      }

      return Number(stockValue) > 0
    },

    getProductClass(product) {
      const classes = []

      if (this.canSelectProduct(product)) {
        classes.push('available')
      } else {
        classes.push('out-of-stock')
      }

      // Ajouter les indicateurs spéciaux
      if (this.isProductPopular(product)) {
        classes.push('has-popular-badge')
      }

      if (this.isProductOnPromotion(product)) {
        classes.push('has-promotion-badge')
      }

      return classes.join(' ')
    },

    isProductPopular(product) {
      // Simulation : un produit est populaire s'il a été vendu plus de 10 fois
      // Dans un vrai système, cela viendrait de la base de données
      return product.sales_count > 10 || Math.random() > 0.8
    },

    isProductOnPromotion(product) {
      // Simulation : un produit est en promotion s'il a un prix réduit
      // Dans un vrai système, cela viendrait de la base de données
      return product.original_price && product.original_price > product.price
    },

    filterProducts() {
      if (!this.searchQuery) {
        if (this.activeCategory) {
          this.loadProducts(this.activeCategory)
        } else {
          this.filteredProducts = [...this.products]
        }
        return
      }

      const query = this.searchQuery.toLowerCase()
      this.filteredProducts = this.products.filter(p =>
        p.name.toLowerCase().includes(query) ||
        (p.description && p.description.toLowerCase().includes(query))
      )
    },

    addToCart(product, evt) {
      if (!this.selectedTable) {
        alert('Veuillez d\'abord sélectionner une table')
        return
      }

      if (!this.canSelectProduct(product)) {
        alert('Ce produit n\'est pas disponible ou en rupture de stock')
        return
      }

      const categoryId = product.category_id ?? this.activeCategory?.id ?? product.category?.id ?? null
      const printerTypeId = product.printer_type_id ?? this.resolveProductPrinterTypeId(product, this.activeCategory) ?? (categoryId ? this.categoryPrinterTypes[categoryId] : null)
      const availableStock = this.resolveProductStock(product)

      const existing = this.cart.find(p => p.id === product.id)
      if (existing) {
        if (availableStock !== null && availableStock !== undefined && existing.quantity >= availableStock) {
          alert(`Stock insuffisant. Plus que ${availableStock} unités disponibles.`)
          return
        }
        existing.quantity++
        if (!existing.printer_type_id && printerTypeId) {
          existing.printer_type_id = printerTypeId
        }
      } else {
        const price = product.price ?? (product.pricing?.[0]?.price ? parseFloat(product.pricing[0].price) : 0)
        this.cart.push({
          ...product,
          category_id: categoryId,
          printer_type_id: printerTypeId,
          quantity: 1,
          price: Number(price) || 0,
          stock: availableStock
        })
      }

      this.registerProduct(product, {
        category: this.activeCategory,
        category_id: categoryId,
        printer_type_id: printerTypeId,
        price: product.price ?? (product.pricing?.[0]?.price ? parseFloat(product.pricing[0].price) : 0)
      })

      // Animation de feedback
      this.showAddToCartAnimation(product)

      // Animation sur la carte produit
      let productCard = null
      if (evt?.currentTarget && typeof evt.currentTarget.closest === 'function') {
        productCard = evt.currentTarget.closest('.product-card')
      } else if (evt?.target && typeof evt.target.closest === 'function') {
        productCard = evt.target.closest('.product-card')
      }
      if (productCard) {
        productCard.classList.add('adding-to-cart')
        setTimeout(() => {
          productCard.classList.remove('adding-to-cart')
        }, 600)
      }
    },

    showAddToCartAnimation(product) {
      // Créer un élément de notification temporaire
      const notification = document.createElement('div')
      notification.className = 'add-to-cart-notification'
      notification.innerHTML = `
        <div class="notification-content">
          <i class="fas fa-check"></i>
          <span>${product.name} ajouté au panier</span>
        </div>
      `
      document.body.appendChild(notification)

      // Styliser la notification
      Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        background: '#10b981',
        color: 'white',
        padding: '1rem 1.5rem',
        borderRadius: '8px',
        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
        zIndex: '10000',
        display: 'flex',
        alignItems: 'center',
        gap: '0.5rem',
        fontWeight: '600',
        animation: 'slideInFromRight 0.3s ease-out'
      })

      // Supprimer la notification après 2 secondes
      setTimeout(() => {
        notification.style.animation = 'slideOutToRight 0.3s ease-in'
        setTimeout(() => {
          document.body.removeChild(notification)
        }, 300)
      }, 2000)
    },

    incrementQuantity(item) {
      item.quantity++
    },

    decrementQuantity(item) {
      if (item.quantity > 1) {
        item.quantity--
      } else {
        this.removeItem(item)
      }
    },

    removeItem(item) {
      this.cart = this.cart.filter(i => i.id !== item.id)
    },

    clearCart() {
      this.cart = []
    },

    openTableSelector() {
      this.showTableSelector = true
    },

    closeTableSelector() {
      this.showTableSelector = false
    },

    async beginAddToPending() {
      if (!this.currentPendingOrder) {
        return
      }

      this.isAddingToPending = true
      this.clearCart()
      this.lastAdditionLines = []

      if (this.selectedTable) {
        await this.loadPendingOrdersForTable(this.selectedTable.id, { showToast: false, syncCart: false })
      }
    },

    async confirmAddToPending() {
      await this.addProductsToPending()
    },

    async onTableSelected(table) {
      // Vider le panier et réinitialiser les commandes en attente lors du changement de table
      this.clearCart()
      this.currentPendingOrder = null
      this.existingPendingLines = []
      this.lastAdditionLines = []
      this.isAddingToPending = false

      this.selectedTable = table
      this.syncRouteWithTable(table?.id ?? null)
      this.showTableSelector = false

      if (table.status === 'occupied') {
        await this.loadPendingOrdersForTable(table.id, { syncCart: true })
      }
    },

    async updateTableStatus(tableId, status) {
      try {
        const token = localStorage.getItem('token')
        await axios.patch(
          `${API_BASE_URL}/tables/${tableId}/status`,
          { status },
          {
            headers: {
              'Content-Type': 'application/json',
              Authorization: `Bearer ${token}`
            }
          }
        )
      } catch (error) {
        console.error('Erreur lors de la mise à jour du statut de la table :', error.response?.data || error.message)
      }
    },

    async handleCheckout() {
      if (!this.selectedTable) {
        alert('Veuillez sélectionner une table avant de valider')
        return
      }

      if (this.currentPendingOrder) {
        const baselineQuantities = new Map(
          this.existingPendingLines.map(line => [line.product_id, line.quantity])
        )

        const hasUnsyncedItems = this.cart.some(item => {
          const baseQuantity = baselineQuantities.get(item.id) || 0
          return item.quantity > baseQuantity
        })

        if (hasUnsyncedItems) {
          const { success } = await this.addToPendingOrder({
            showNotification: false,
            recordAddition: false
          })
          if (!success) {
            this.showNotification('Impossible d\'ajouter les nouveaux produits à la commande', 'error')
            return
          }
        } else {
          await this.loadPendingOrdersForTable(this.selectedTable.id, {
            showToast: false,
            syncCart: false
          })
        }

        const pendingOrder = this.currentPendingOrder

        const invoiceNumber = pendingOrder?.invoice_number || pendingOrder?.reference
        this.currentInvoiceNumber = invoiceNumber || this.generateInvoiceNumber()
        this.currentPaymentMethod = pendingOrder?.payment?.name || 'En attente'
        this.isInvoiceModalOpen = true
        return
      }

      if (this.cart.length === 0) return

      this.currentInvoiceNumber = this.generateInvoiceNumber()
      this.currentPaymentMethod = 'En attente'
      this.isInvoiceModalOpen = true
    },

    generateInvoiceNumber() {
      return 'TABLE-' + Date.now()
    },

    openPaymentModal() {
      this.isInvoiceModalOpen = false
      this.isPaymentModalOpen = true
    },

    handleCloseModal() {
      this.isPaymentModalOpen = false
    },

    closeInvoiceModal() {
      this.isInvoiceModalOpen = false
    },

    async handlePaymentConfirmation(paymentData) {
      const token = localStorage.getItem('token')
      const user = JSON.parse(localStorage.getItem('user'))

      if (!user) {
        console.error('Utilisateur non authentifié')
        return
      }

      this.currentPaymentMethod = paymentData.method || 'Inconnu'

      try {
        const sessionData = await this.fetchCurrentSession()

        const totalAmount = this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)
        const discount = Number(paymentData.discount_percentage ?? 0)
        const computedFinalAmount = Number(
          paymentData.final_total !== undefined && paymentData.final_total !== null
            ? paymentData.final_total
            : (totalAmount * (1 - discount / 100)).toFixed(2)
        )
        const finalAmount = Number.isFinite(computedFinalAmount) ? computedFinalAmount : totalAmount
        const paymentMethodId = this.getPaymentMethodId(paymentData.method)

        const headers = {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`
        }

        let saleId = null
        let invoiceNumber = this.currentInvoiceNumber

        if (this.currentPendingOrder) {
          const payload = {
            payment_id: paymentMethodId,
            discount_percentage: discount,
            final_total: Number(finalAmount.toFixed(2)),
            cash_register_session_id: sessionData?.id || null
          }

          const response = await axios.post(
            `${API_BASE_URL}/sales/${this.currentPendingOrder.id}/validate`,
            payload,
            { headers }
          )

          const validatedSale = response.data || {}
          saleId = validatedSale.id || this.currentPendingOrder.id
          invoiceNumber = validatedSale.invoice_number || invoiceNumber
          this.currentPendingOrder = null
          this.existingPendingLines = []
          this.isAddingToPending = false
        } else {
          const saleData = {
            user_id: user.id,
            point_of_sale_id: user.point_of_sale_id,
            table_id: this.selectedTable.id,
            cash_register_session_id: sessionData?.id || null,
            total_amount: Number(finalAmount.toFixed(2)),
            discount_percentage: discount,
            status: paymentData.status || 'completed',
            payment_id: paymentMethodId,
            ticket_number: Number(Date.now().toString())
          }

          const response = await axios.post(`${API_BASE_URL}/sales`, saleData, { headers })
          saleId = response.data.id
          invoiceNumber = response.data.invoice_number || invoiceNumber

          const aggregatedCart = this.prepareCartLines(this.cart)

          for (const line of aggregatedCart) {
            const orderLineData = {
              sale_id: saleId,
              product_id: line.product_id,
              quantity: line.quantity,
              price: Number(line.price),
              total: Number((line.price * line.quantity).toFixed(2))
            }

            await axios.post(`${API_BASE_URL}/orderlines`, orderLineData, { headers })
          }
        }

        if (saleId) {
          try {
            await axios.post(`${API_BASE_URL}/printers/invoice/${saleId}`, {}, {
              headers: { Authorization: `Bearer ${token}` }
            })
          } catch (printError) {
            console.error('Erreur lors de l\'impression de la facture:', printError.response?.data || printError.message)
          }
        } else {
          console.warn('Impossible d\'imprimer la facture : identifiant de vente introuvable')
        }

        if (this.selectedTable) {
          await this.updateTableStatus(this.selectedTable.id, 'available')
        }

        this.currentInvoiceNumber = invoiceNumber || this.generateInvoiceNumber()
        this.showNotification('Commande validée et ticket imprimé', 'success')

        this.currentPendingOrder = null
        this.clearCart()
        this.selectedTable = null
        this.existingPendingLines = []
        this.lastAdditionLines = []
        this.isAddingToPending = false

        this.isPaymentModalOpen = false
        this.isInvoiceModalOpen = false
      } catch (error) {
        console.error('Erreur lors du traitement du paiement:', error.response?.data || error.message)
        this.showNotification('Paiement impossible, veuillez réessayer', 'error')
      } finally {
        this.handleCloseModal()
      }
    },

    getPaymentMethodId(method) {
      const paymentMethodMap = {
        'TPE': 1,
        'Orange Money': 2,
        'MVola': 3,
        'Espèce': 4,
        'Airtel Money': 5
      }
      return paymentMethodMap[method] || null
    },

    async fetchCurrentSession() {
      try {
        const token = localStorage.getItem('token')
        const { data } = await axios.get(`${API_BASE_URL}/cash-register-session/my-active-session`, {
          headers: { Authorization: `Bearer ${token}` }
        })
        return data?.data || data || null
      } catch (error) {
        console.error('Impossible de récupérer la session de caisse:', error.response?.data || error.message)
        return null
      }
    },

    handleImageError(event) {
      const target = event?.target
      if (!target) return
      if (target.dataset.fallbackApplied === 'true') return
      target.dataset.fallbackApplied = 'true'
      target.onerror = null
      target.src = placeholderImage
    },

    // Méthodes pour les commandes en attente
    async holdOrder() {
      if (this.cart.length === 0) return
      if (!this.selectedTable) {
        alert('Veuillez sélectionner une table avant d\'envoyer la commande')
        return
      }

      try {
        const token = localStorage.getItem('token')
        const user = JSON.parse(localStorage.getItem('user'))
        const sessionData = await this.fetchCurrentSession()

        const cartLines = this.prepareCartLines(this.cart)

        cartLines.forEach(line => {
          const productRef = line.product || { id: line.product_id, name: line.name, category_id: line.category_id }
          const categoryRef = productRef?.category || (line.category_id ? { id: line.category_id } : this.activeCategory || null)

          this.registerProduct(productRef, {
            category: categoryRef,
            category_id: line.category_id ?? categoryRef?.id ?? null,
            printer_type_id: line.printer_type_id ?? this.resolveProductPrinterTypeId(productRef, categoryRef) ?? null,
            price: line.price
          })
        })

        const orderData = {
          table_id: Number(this.selectedTable.id),
          user_id: user.id,
          point_of_sale_id: user.point_of_sale_id,
          cash_register_session_id: sessionData?.id || null,
          order_lines: cartLines.map(line => this.stripLineForApi(line))
        }

        const response = await axios.post(`${API_BASE_URL}/sales/pending-order`, orderData, {
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`
          }
        })

        this.currentPendingOrder = response.data?.data || response.data || null

        await this.printPendingOrder({ lines: cartLines })

        await this.updateTableStatus(this.selectedTable.id, 'occupied')
        if (this.selectedTable) {
          this.selectedTable.status = 'occupied'
        }

        await this.loadPendingOrdersForTable(this.selectedTable.id, { showToast: false, syncCart: false })

        this.cart = []
        this.lastAdditionLines = []
        this.isAddingToPending = false
        this.showNotification('Commande envoyée et imprimée', 'success')
      } catch (error) {
        console.error('Erreur lors de l\'envoi de la commande:', error.response?.data || error.message)
        this.showNotification('Impossible d\'envoyer la commande', 'error')
      }
    },

    async addToPendingOrder(options = {}) {
      const {
        showNotification = true,
        recordAddition = true,
        lines = null
      } = options

      if (!this.currentPendingOrder) {
        return { success: false, additions: [] }
      }

      const manualLines = Array.isArray(lines) ? lines : null

      if ((!manualLines || manualLines.length === 0) && this.cart.length === 0) {
        return { success: false, additions: [] }
      }

      try {
        const token = localStorage.getItem('token')

        const baselineQuantities = new Map(
          this.existingPendingLines.map(line => [line.product_id, line.quantity])
        )

        const sourceLines = manualLines && manualLines.length > 0
          ? manualLines
          : this.cart.map(item => ({
              product_id: item.id,
              quantity: item.quantity,
              price: item.price
            }))

        const additions = sourceLines.reduce((acc, line) => {
          const productId = line.product_id ?? line.id
          const catalogEntry = this.productCatalog[productId] || {}
          const desiredQuantity = Number(line.quantity) || 0
          const price = Number(line.price) || 0

          if (desiredQuantity <= 0) {
            return acc
          }

          const baseQuantity = baselineQuantities.get(productId) || 0
          const quantityToAdd = manualLines && manualLines.length > 0
            ? desiredQuantity
            : desiredQuantity - baseQuantity

          if (quantityToAdd <= 0) {
            return acc
          }

          const categoryId = line.category_id ?? catalogEntry.category_id ?? null
          const printerTypeId = line.printer_type_id ?? catalogEntry.printer_type_id ?? (categoryId ? this.categoryPrinterTypes[categoryId] : null)

          acc.push({
            product_id: productId,
            quantity: quantityToAdd,
            price,
            category_id: categoryId,
            printer_type_id: printerTypeId
          })

          return acc
        }, [])

        if (additions.length === 0) {
          if (showNotification) {
            this.showNotification('Aucun nouveau produit à ajouter', 'info')
          }
          return { success: false, additions: [] }
        }

        await axios.post(`${API_BASE_URL}/sales/${this.currentPendingOrder.id}/add-products`, {
          order_lines: additions
        }, {
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`
          }
        })

        await this.loadPendingOrdersForTable(this.selectedTable.id, { showToast: false, syncCart: false })

        if (recordAddition) {
          this.lastAdditionLines = additions
        }

        if (showNotification) {
          this.showNotification('Produits ajoutés à la commande', 'success')
        }
        return { success: true, additions }
      } catch (error) {
        console.error('Erreur lors de l\'ajout:', error.response?.data || error.message)
        if (showNotification) {
          this.showNotification('Erreur lors de l\'ajout à la commande', 'error')
        }
        return { success: false, additions: [] }
      }
    },

    async addProductsToPending() {
      if (!this.currentPendingOrder) return

      const cartSnapshot = this.cart.map(item => ({ ...item }))

      if (cartSnapshot.length === 0) {
        this.showNotification('Panier vide, rien à ajouter', 'info')
        this.isAddingToPending = false
        return
      }
      
      const payloadLines = this.prepareCartLines(cartSnapshot)

      this.clearCart()

      const { success, additions } = await this.addToPendingOrder({
        showNotification: false,
        recordAddition: true,
        lines: payloadLines
      })

      if (!success) {
        this.showNotification('Échec de l\'ajout, veuillez réessayer', 'error')
        this.cart = cartSnapshot.map(item => ({ ...item }))
        return
      }

      this.showNotification('Produits ajoutés à la commande', 'success')

      try {
        await this.printPendingOrder({ showSuccess: false, lines: additions })
        this.showNotification('Ticket additionnel imprimé', 'success')
      } catch (error) {
        console.error('Erreur lors de l\'impression du ticket additionnel:', error)
        this.showNotification('Impression du ticket additionnel impossible', 'error')
      }
    },

    normalizeLinesForPrinting(inputLines = []) {
      const baseLines = Array.isArray(inputLines) && inputLines.length > 0
        ? inputLines
        : this.existingPendingLines

      const normalized = baseLines
        .map(line => {
          const productId = line.product_id ?? line.id
          const catalogEntry = this.productCatalog[productId] || {}
          const categoryId = line.category_id ?? catalogEntry.category_id ?? null
          const printerTypeId = line.printer_type_id ?? catalogEntry.printer_type_id ?? (categoryId ? this.categoryPrinterTypes[categoryId] : null)
          const price = Number(line.price ?? catalogEntry.price ?? 0) || 0
          const quantity = Number(line.quantity) || 0

          return {
            ...line,
            product_id: productId,
            quantity,
            price,
            category_id: categoryId,
            printer_type_id: printerTypeId
          }
        })
        .filter(line => line.quantity > 0)

      return this.aggregateLineItems(normalized)
    },

    stripLineForApi(line) {
      return {
        product_id: line.product_id,
        quantity: line.quantity,
        price: line.price
      }
    },

    getPrinterTypeId(printer) {
      if (!printer) return null
      return printer.printer_type_id ?? printer.printer_type?.id ?? printer.printer_type ?? null
    },

    getPrinterTypeCode(printer) {
      if (!printer) return null
      return (printer.printer_type?.code || printer.printer_type?.name || '').toString().toLowerCase()
    },

    isPrinterActive(printer) {
      if (!printer) return false
      if (printer.is_active === true || printer.is_active === 'true' || printer.is_active === 1) return true
      if (printer.is_active === false || printer.is_active === 'false' || printer.is_active === 0) return false
      return true
    },

    isCashPrinter(printer) {
      if (!printer) return false
      const code = this.getPrinterTypeCode(printer)
      return Boolean(printer.is_default) || code === 'cash' || code === 'invoice'
    },

    async ensurePrintersLoaded(force = false) {
      if (!force && Array.isArray(this.printersCache) && this.printersCache.length > 0) {
        return this.printersCache
      }

      try {
        const token = localStorage.getItem('token')
        const response = await axios.get(`${API_BASE_URL}/printers`, {
          headers: { Authorization: `Bearer ${token}` }
        })
        const data = Array.isArray(response.data?.data) ? response.data.data : Array.isArray(response.data) ? response.data : []
        this.printersCache = Array.isArray(data) ? data : []
      } catch (error) {
        console.error('Erreur lors du chargement des imprimantes:', error.response?.data || error.message)
        this.printersCache = []
      }

      return this.printersCache
    },

    async sendPrintJob(printerId, lines) {
      if (!printerId || !Array.isArray(lines) || lines.length === 0) return

      const token = localStorage.getItem('token')
      await axios.post(
        `${API_BASE_URL}/sales/${this.currentPendingOrder.id}/print-pending/${printerId}`,
        { order_lines: lines.map(line => this.stripLineForApi(line)) },
        {
          headers: { Authorization: `Bearer ${token}` }
        }
      )
    },

    async printPendingOrder(options = {}) {
      if (!this.currentPendingOrder) return

      const {
        showSuccess = true,
        lines = []
      } = options

      const normalizedLines = this.normalizeLinesForPrinting(lines)
      if (!normalizedLines.length) {
        this.showNotification('Aucune ligne à imprimer', 'info')
        return
      }

      const printers = (await this.ensurePrintersLoaded()).filter(printer => this.isPrinterActive(printer))

      if (!printers.length) {
        this.showNotification('Aucune imprimante disponible', 'warning')
        return
      }

      const errors = []
      const printedPrinterIds = new Set()

      const cashPrinters = printers.filter(printer => this.isCashPrinter(printer))
      for (const printer of cashPrinters) {
        if (printedPrinterIds.has(printer.id)) continue
        try {
          await this.sendPrintJob(printer.id, normalizedLines)
        } catch (error) {
          errors.push({ printer, error })
        }
        printedPrinterIds.add(printer.id)
      }

      const printersByType = printers.reduce((acc, printer) => {
        const typeId = this.getPrinterTypeId(printer)
        if (!typeId) return acc
        if (!acc[typeId]) acc[typeId] = []
        acc[typeId].push(printer)
        return acc
      }, {})

      const linesByType = normalizedLines.reduce((acc, line) => {
        if (!line.printer_type_id) return acc
        const key = line.printer_type_id
        if (!acc[key]) acc[key] = []
        acc[key].push(line)
        return acc
      }, {})

      for (const [typeId, typeLines] of Object.entries(linesByType)) {
        const targetPrinters = printersByType[typeId] || []
        if (!targetPrinters.length) continue
        for (const printer of targetPrinters) {
          if (printedPrinterIds.has(printer.id)) continue
          try {
            await this.sendPrintJob(printer.id, typeLines)
          } catch (error) {
            errors.push({ printer, error })
          }
          printedPrinterIds.add(printer.id)
        }
      }

      if (errors.length) {
        console.error('Certaines impressions ont échoué:', errors)
        this.showNotification('Certaines impressions ont échoué. Consultez la console.', 'error')
      } else if (showSuccess) {
        this.showNotification('Commande imprimée avec succès!', 'success')
      }
    },

    async loadPendingOrdersForTable(tableId, options = {}) {
      const {
        showToast = true,
        notifyWhenEmpty = false,
        syncCart = false
      } = options

      try {
        const token = localStorage.getItem('token')

        const response = await axios.get(`${API_BASE_URL}/tables/${tableId}/pending-orders`, {
          headers: { Authorization: `Bearer ${token}` }
        })

        const pendingOrders = Array.isArray(response.data)
          ? response.data
          : Array.isArray(response.data?.data)
            ? response.data.data
            : []

        if (pendingOrders.length > 0) {
          // Prendre la première commande en attente
          const pendingOrder = pendingOrders[0]
          this.currentPendingOrder = pendingOrder

          const orderLines = Array.isArray(pendingOrder.order_lines)
            ? pendingOrder.order_lines
            : Array.isArray(pendingOrder.orderLines)
              ? pendingOrder.orderLines
              : []

          if (orderLines.length > 0) {
            const mappedLines = orderLines.map(line => {
              const productData = line.product || {}
              const categoryData = productData.category || (productData.category_id ? { id: productData.category_id } : null)
              const price = Number(line.price || line.unit_price || productData.price || 0) || 0
              const quantity = line.quantity || 1
              const categoryId = productData.category_id ?? categoryData?.id ?? null
              const printerTypeId = this.resolveProductPrinterTypeId(productData, categoryData)

              if (line.product || Object.keys(productData).length > 0) {
                this.registerProduct({ ...productData, id: line.product_id, price }, {
                  category: categoryData,
                  category_id: categoryId,
                  printer_type_id: printerTypeId,
                  price
                })
              } else if (!this.productCatalog[line.product_id]) {
                const fallbackName = productData.name || 'Produit inconnu'
                this.registerProduct({ id: line.product_id, name: fallbackName, category_id: categoryId, price }, {
                  category_id: categoryId,
                  printer_type_id: printerTypeId,
                  price
                })
              }

              return {
                product_id: line.product_id,
                name: productData.name || 'Produit inconnu',
                price,
                quantity,
                category_id: categoryId,
                printer_type_id: printerTypeId,
                product: productData
              }
            })

            this.existingPendingLines = this.aggregateLineItems(mappedLines)

            if (syncCart) {
              this.cart = this.existingPendingLines.map(line => ({
                id: line.product_id,
                name: line.name,
                price: line.price,
                quantity: line.quantity,
                category_id: line.category_id,
                printer_type_id: line.printer_type_id,
                product: line.product || null
              }))
              this.lastAdditionLines = []
              this.isAddingToPending = false
            }

            if (!syncCart) {
              this.lastAdditionLines = []
            }

            if (showToast) {
              this.showNotification('Commande en attente chargée', 'success')
            }
          } else {
            console.warn('Aucune ligne de commande trouvée pour cette commande en attente')
            this.existingPendingLines = []
            if (syncCart) {
              this.cart = []
              this.lastAdditionLines = []
            }
            if (showToast) {
              this.showNotification('Commande en attente trouvée mais vide', 'warning')
            }
          }

          return pendingOrder
        }

        this.currentPendingOrder = null
        this.existingPendingLines = []
        this.lastAdditionLines = []
        if (syncCart) {
          this.cart = []
        }
        this.isAddingToPending = false
        if (showToast && notifyWhenEmpty) {
          this.showNotification('Aucune commande en attente pour cette table', 'info')
        }
        return null
      } catch (error) {
        console.error('Erreur lors du chargement des commandes en attente:', error.response?.data || error.message)
        if (showToast) {
          this.showNotification('Erreur lors du chargement de la commande', 'error')
        }
        this.existingPendingLines = []
        this.lastAdditionLines = []
        if (syncCart) {
          this.cart = []
        }
        this.isAddingToPending = false
        return null
      }
    },

    showNotification(message, type = 'info') {
      const notification = document.createElement('div')
      notification.className = `notification notification-${type}`
      notification.innerHTML = `
        <div class="notification-content">
          <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
          <span>${message}</span>
        </div>
      `
      document.body.appendChild(notification)

      // Styliser la notification
      Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        background: type === 'success' ? '#10b981' : type === 'error' ? '#dc2626' : '#3b82f6',
        color: 'white',
        padding: '1rem 1.5rem',
        borderRadius: '8px',
        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
        zIndex: '10000',
        display: 'flex',
        alignItems: 'center',
        gap: '0.5rem',
        fontWeight: '600',
        animation: 'slideInFromRight 0.3s ease-out'
      })

      // Supprimer la notification après 3 secondes
      setTimeout(() => {
        notification.style.animation = 'slideOutToRight 0.3s ease-in'
        setTimeout(() => {
          document.body.removeChild(notification)
        }, 300)
      }, 3000)
    }
  },

  async mounted() {
    const { loadUserData } = useAuth()
    await loadUserData()
    await this.loadCategories()
}
}
</script>

<style scoped>
.product-card.adding-to-cart {
  animation: cartPulse 0.4s ease;
}

@keyframes cartPulse {
  0% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.2);
  }
  50% {
    transform: scale(1.02);
    box-shadow: 0 12px 30px rgba(99, 102, 241, 0.25);
  }
  100% {
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.2);
  }
}

.add-to-cart-notification {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 9999;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.95);
  color: #fff;
  padding: 0.6rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  box-shadow: 0 18px 40px rgba(99, 102, 241, 0.35);
  animation: slideIn 0.3s ease, fadeOut 0.3s ease 1.8s forwards;
}

.add-to-cart-notification i {
  font-size: 0.9rem;
}

@keyframes slideIn {
  from {
    transform: translateY(-20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

@keyframes fadeOut {
  to {
    opacity: 0;
    transform: translateY(-10px);
  }
}

@keyframes slideInFromRight {
  from {
    transform: translateX(20px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes slideOutToRight {
  to {
    transform: translateX(20px);
    opacity: 0;
  }
}
</style>
