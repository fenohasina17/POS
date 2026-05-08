<template>
  <div v-if="isOpen" class="modal-overlay" @click.self="close">
    <div class="modal-container">
      <!-- Header -->
      <div class="modal-header">
        <div>
          <h2 class="modal-title">
            <font-awesome-icon icon="fa-regular fa-pen-to-square" class="title-icon" />
            Modifier la vente
          </h2>
          <div class="modal-subtitle">
            <span class="badge">Vente #{{ sale?.sale_number || sale?.ticket_number || 'N/A' }}</span>
            <span class="date">{{ formatDate(sale?.created_at) }}</span>
          </div>
        </div>
        <div class="total-badge">
          <span class="total-label">Total actuel</span>
          <span class="total-amount">{{ formatPrice(calculateTotal()) }}</span>
        </div>
        <button class="close-btn" @click="close">&times;</button>
      </div>

      <!-- Form -->
      <form @submit.prevent="save" class="modal-form">
        <!-- Informations générales -->
        <div class="info-card">
          <h3 class="section-title">
            <font-awesome-icon icon="fa-regular fa-circle-info" />
            Informations générales
          </h3>
          <div class="info-grid">
            <div class="field">
              <label>Statut</label>
              <select v-model="editableSale.status" class="input">
                <option value="pending">En attente</option>
                <option value="completed">Terminée</option>
                <option value="cancelled">Annulée</option>
              </select>
            </div>
            <div class="field">
              <label>Remise (%)</label>
              <input type="number" v-model.number="editableSale.discount_percentage" class="input" step="0.01" min="0" max="100" />
            </div>
          </div>
        </div>

        <!-- Produits -->
        <div class="products-card">
          <div class="card-header">
            <h3 class="section-title">
              <font-awesome-icon icon="fa-solid fa-cart-shopping" />
              Produits
            </h3>
            <span class="badge-count">{{ editableSale.orderlines?.length || 0 }} produit(s)</span>
          </div>

          <!-- Liste des produits existants -->
          <div class="products-list">
            <div v-for="(line, idx) in editableSale.orderlines" :key="line.id || idx" class="product-row">
              <div class="product-name-cell">
                <font-awesome-icon icon="fa-solid fa-box" class="item-icon" />
                {{ line.product?.name || 'Produit inconnu' }}
              </div>
              <div class="product-price-cell">
                {{ formatPrice(line.price) }}
              </div>
              <div class="quantity-control">
                <button type="button" class="qty-btn" @click="decrementQuantity(idx)">
                  <font-awesome-icon icon="fa-solid fa-minus" />
                </button>
                <span class="qty-value">{{ line.quantity }}</span>
                <button type="button" class="qty-btn" @click="incrementQuantity(idx)">
                  <font-awesome-icon icon="fa-solid fa-plus" />
                </button>
              </div>
              <div class="product-total-cell">
                {{ formatPrice(line.quantity * line.price) }}
              </div>
              <button type="button" class="remove-product-btn" @click="removeProduct(idx)">
                <font-awesome-icon icon="fa-solid fa-trash" />
              </button>
            </div>
          </div>

          <!-- Ajout de produit (admin uniquement) -->
          <div v-if="isAdmin" class="add-product-section">
            <div class="add-product-controls">
              <div class="product-select-field">
                <label>Produit</label>
                <select v-model="selectedProductId" class="input">
                  <option value="">-- Sélectionner un produit --</option>
                  <option v-for="product in products" :key="product.id" :value="product.id">
                    {{ product.name }} - {{ formatPrice(product.price) }}
                  </option>
                </select>
              </div>
              <div class="quantity-field">
                <label>Quantité</label>
                <input type="number" v-model.number="newProductQuantity" class="input" min="1" />
              </div>
              <button type="button" class="add-product-btn" @click="addSelectedProduct">
                <font-awesome-icon icon="fa-solid fa-plus" />
                Ajouter
              </button>
            </div>
          </div>
          <p v-else class="info-message">Seul un administrateur peut ajouter des produits.</p>
        </div>

        <!-- Actions -->
        <div class="modal-actions">
          <button type="button" class="btn-secondary" @click="close">Annuler</button>
          <button type="submit" class="btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { API_BASE_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'

// Props
const props = defineProps({
  sale: { type: Object, default: null }
})
const emit = defineEmits(['close', 'save'])

// État local
const isOpen = ref(false)
const editableSale = ref({
  id: null,
  ticket_number: '',
  status: 'pending',
  discount_percentage: 0,
  orderlines: []
})

// Authentification
const { isAdmin, user: currentUser } = useAuth()
console.log('🔍 [EditSaleModal] isAdmin =', isAdmin.value)
console.log('🔍 [EditSaleModal] currentUser =', currentUser.value)

// Données pour l'ajout de produit
const products = ref([])
const selectedProductId = ref('')
const newProductQuantity = ref(1)

// Headers API
const getAuthHeaders = () => {
  const token = localStorage.getItem('token')
  if (!token) throw new Error("Token d'authentification manquant")
  return { Authorization: `Bearer ${token}` }
}

// Récupération de tous les produits (gestion pagination + extraction prix)
const fetchAllProducts = async (url) => {
  const fullUrl = url.startsWith('http') ? url : `${API_BASE_URL}${url}`
  const { data } = await axios.get(fullUrl, { headers: getAuthHeaders() })

  let pageProducts = []
  if (Array.isArray(data)) pageProducts = data
  else if (data?.data && Array.isArray(data.data)) pageProducts = data.data
  else if (data?.items && Array.isArray(data.items)) pageProducts = data.items
  else if (data?.results && Array.isArray(data.results)) pageProducts = data.results
  else {
    for (const key in data) {
      if (Array.isArray(data[key])) {
        pageProducts = data[key]
        break
      }
    }
  }

  // Extraction du prix depuis le tableau pricing
  const userPointOfSaleId = currentUser.value?.point_of_sale_id
  pageProducts = pageProducts.map(product => {
    let price = 0
    if (product.pricing && Array.isArray(product.pricing)) {
      const pricing = product.pricing.find(p => p.point_of_sale_id === userPointOfSaleId)
      if (pricing) price = Number(pricing.price)
      else if (product.pricing[0]) price = Number(product.pricing[0].price)
    }
    return { ...product, price }
  })

  // Récupération de la page suivante (plusieurs formats possibles)
  let nextUrl = data?.links?.next || data?.next_page_url || null
  if (nextUrl) {
    const nextProducts = await fetchAllProducts(nextUrl)
    return [...pageProducts, ...nextProducts]
  }
  return pageProducts
}

const fetchProducts = async () => {
  if (!isAdmin.value) return
  try {
    console.log('🔄 Chargement des produits...')
    const allProducts = await fetchAllProducts(`${API_BASE_URL}/products?per_page=100`)
    products.value = allProducts
    console.log(`✅ ${products.value.length} produits chargés`)
    if (products.value.length) console.log('Exemple produit avec prix:', products.value[0])
  } catch (error) {
    console.error('❌ Erreur chargement produits:', error)
  }
}

// Ajouter un produit sélectionné
const addSelectedProduct = () => {
  if (!selectedProductId.value) {
    alert('Veuillez sélectionner un produit')
    return
  }
  const product = products.value.find(p => p.id === selectedProductId.value)
  if (!product) return

  const quantity = newProductQuantity.value || 1
  const price = product.price

  editableSale.value.orderlines.push({
    id: `temp_${Date.now()}_${Math.random()}`,
    product: {
      id: product.id,
      name: product.name
    },
    quantity: quantity,
    price: price,
    total: quantity * price
  })

  // Réinitialiser la sélection
  selectedProductId.value = ''
  newProductQuantity.value = 1
}

// Synchronisation avec la prop sale
watch(() => props.sale, (newSale) => {
  if (newSale) {
    const cloned = JSON.parse(JSON.stringify(newSale))
    // Convertir order_lines (API) → orderlines (modale)
    if (cloned.order_lines && !cloned.orderlines) {
      cloned.orderlines = cloned.order_lines
    }
    if (!cloned.orderlines) cloned.orderlines = []
    editableSale.value = cloned
    isOpen.value = true
    if (isAdmin.value) fetchProducts()
  } else {
    isOpen.value = false
  }
}, { immediate: true, deep: true })

// Utilitaires
const formatPrice = (price) => {
  const value = Math.round(Number(price) || 0)
  return `${value.toLocaleString('fr-FR')} Ar`
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const calculateTotal = () => {
  const subtotal = editableSale.value.orderlines.reduce((sum, line) => sum + (line.quantity * line.price), 0)
  const discount = (subtotal * (editableSale.value.discount_percentage || 0)) / 100
  return Math.round(subtotal - discount)
}

const incrementQuantity = (index) => {
  editableSale.value.orderlines[index].quantity++
}

const decrementQuantity = (index) => {
  if (editableSale.value.orderlines[index].quantity > 1) {
    editableSale.value.orderlines[index].quantity--
  }
}

const removeProduct = (index) => {
  if (editableSale.value.orderlines.length > 1) {
    editableSale.value.orderlines.splice(index, 1)
  }
}

const save = () => {
  editableSale.value.orderlines.forEach(line => {
    line.total = Math.round(line.quantity * line.price)
    line.price = Math.round(line.price)
  })
  editableSale.value.total_amount = calculateTotal()
  emit('save', editableSale.value)
  close()
}

const close = () => {
  isOpen.value = false
  emit('close')
}
</script>

<!-- Les styles CSS ont été volontairement omis comme demandé -->
<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-container {
  background: white;
  border-radius: 1.5rem;
  width: 100%;
  max-width: 950px;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  font-family: 'Inter', system-ui, sans-serif;
}

.modal-header {
  position: sticky;
  top: 10px;
  background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
  color: white;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  border-radius: 1.5rem 1.5rem 0 0;
  z-index: 10;
}

.modal-title {
  font-size: 1.3rem;
  font-weight: 700;
  margin: 0 0 0.2rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-subtitle {
  display: flex;
  gap: 0.75rem;
  font-size: 0.75rem;
  opacity: 0.9;
}

.total-badge {
  text-align: right;
}

.total-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  opacity: 0.8;
  display: block;
}

.total-amount {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1;
}

.close-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  font-size: 1.5rem;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  cursor: pointer;
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-form {
  padding: 1rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-card,
.products-card {
  background: #f8fafc;
  border-radius: 1rem;
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
}

.section-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 0.75rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.field label {
  font-size: 0.65rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.input {
  padding: 0.4rem 0.6rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.6rem;
  font-size: 0.85rem;
  background: white;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.badge-count {
  background: #e2e8f0;
  color: #475569;
  padding: 0.15rem 0.5rem;
  border-radius: 20px;
  font-size: 0.65rem;
  font-weight: 600;
}

.products-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.product-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: white;
  padding: 0.5rem 0.75rem;
  border-radius: 0.6rem;
  border: 1px solid #e2e8f0;
  flex-wrap: wrap;
}

.product-name-cell {
  flex: 2;
  min-width: 130px;
  font-weight: 600;
  color: #1e293b;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.item-icon {
  color: #4f46e5;
  font-size: 0.7rem;
}

.product-price-cell {
  min-width: 70px;
  font-size: 0.85rem;
  color: #475569;
  text-align: center;
}

.quantity-control {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  background: #f1f5f9;
  border-radius: 2rem;
  padding: 0.2rem 0.4rem;
}

.qty-btn {
  background: none;
  border: none;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  transition: all 0.2s;
  color: #4f46e5;
}

.qty-btn:hover {
  background: #e2e8f0;
}

.qty-value {
  min-width: 30px;
  text-align: center;
  font-weight: 600;
  font-size: 0.85rem;
}

.product-total-cell {
  min-width: 80px;
  text-align: center;
  font-weight: 700;
  color: #4f46e5;
  background: #f1f5f9;
  padding: 0.2rem 0.5rem;
  border-radius: 1rem;
  font-size: 0.85rem;
}

.remove-product-btn {
  background: none;
  border: none;
  color: #ef4444;
  cursor: pointer;
  padding: 0.2rem;
  border-radius: 0.5rem;
  transition: all 0.2s;
  margin-left: auto;
}

.remove-product-btn:hover {
  background: #fee2e2;
}

.add-product-section {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px dashed #cbd5e1;
}

.add-product-controls {
  display: flex;
  gap: 0.75rem;
  align-items: flex-end;
  flex-wrap: wrap;
}

.product-select-field,
.quantity-field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  flex: 1;
  min-width: 150px;
}

.product-select-field label,
.quantity-field label {
  font-size: 0.65rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
}

.add-product-btn {
  background: #22c55e;
  color: white;
  border: none;
  border-radius: 2rem;
  padding: 0.5rem 1rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: background 0.2s;
  font-size: 0.8rem;
  height: fit-content;
}

.add-product-btn:hover {
  background: #16a34a;
}

.info-message {
  font-size: 0.75rem;
  color: #64748b;
  font-style: italic;
  text-align: center;
  margin-top: 0.5rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 0.5rem;
}

.btn-primary,
.btn-secondary {
  padding: 0.5rem 1rem;
  border-radius: 2rem;
  font-weight: 600;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.btn-primary {
  background: #4f46e5;
  color: white;
}

.btn-primary:hover {
  background: #4338ca;
}

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
}

.btn-secondary:hover {
  background: #e2e8f0;
}

.modal-container::-webkit-scrollbar {
  width: 5px;
}

.modal-container::-webkit-scrollbar-track {
  background: #f1f5f9;
}

.modal-container::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

@media (max-width: 768px) {
  .product-row {
    flex-wrap: wrap;
  }
  .product-name-cell {
    width: 100%;
    margin-bottom: 0.3rem;
  }
  .quantity-control,
  .product-price-cell,
  .product-total-cell {
    margin-top: 0.2rem;
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
  .add-product-controls {
    flex-direction: column;
    align-items: stretch;
  }
  .add-product-btn {
    justify-content: center;
  }
}
</style>
