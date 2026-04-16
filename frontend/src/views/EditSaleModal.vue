<template>
  <div class="modern-edit-modal-overlay" @click.self="close">
    <div class="modern-edit-modal-content">
      <!-- Header Section -->
      <div class="edit-modal-header">
        <div class="edit-modal-title-section">
          <h2 class="edit-modal-title">
            <i class="fas fa-edit"></i>
            Modifier la vente
          </h2>
          <div class="edit-modal-subtitle">
            <span class="edit-modal-ticket">
              <i class="fas fa-hashtag"></i>
              Ticket #{{ sale.ticket_number }}
            </span>
            <span class="edit-modal-date">
              <i class="fas fa-calendar-alt"></i>
              {{ formatDate(sale.created_at) }}
            </span>
          </div>
        </div>
        <div class="edit-modal-total">
          <div class="total-amount">
            {{ formatPrice(calculateTotal()) }}
          </div>
          <div class="total-label">Total actuel</div>
        </div>
      </div>

      <!-- Content Section -->
      <form @submit.prevent="save" class="edit-modal-form">
        <!-- Basic Information -->
        <div class="edit-info-section">
          <h3 class="section-title">
            <i class="fas fa-info-circle"></i>
            Informations générales
          </h3>
          <div class="info-grid">
            <div class="info-field">
              <label for="ticketNumber">
                <i class="fas fa-hashtag"></i>
                Numéro de ticket
              </label>
              <input id="ticketNumber" type="number" min="0" step="1" v-model.number="editableSale.ticket_number" required />
            </div>
            <div class="info-field">
              <label for="status">
                <i class="fas fa-flag"></i>
                Statut
              </label>
              <select id="status" v-model="editableSale.status" required>
                <option value="pending">En attente</option>
                <option value="completed">Terminée</option>
                <option value="cancelled">Annulée</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Order Lines Section -->
        <div class="edit-products-section">
          <div class="section-header">
            <h3 class="section-title">
              <i class="fas fa-shopping-cart"></i>
              Produits
            </h3>
            <div class="section-badge">
              {{ editableSale.order_lines?.length || 0 }} produit{{ (editableSale.order_lines?.length || 0) > 1 ? 's' : '' }}
            </div>
          </div>

          <div class="products-edit-list">
            <div v-for="(line, index) in editableSale.order_lines" :key="line.id" class="product-edit-item">
              <div class="product-edit-header">
                <div class="product-name">
                  <i class="fas fa-box"></i>
                  {{ line.product?.name || 'N/A' }}
                </div>
                <button type="button" @click="removeProduct(index)" class="remove-product-btn">
                  <i class="fas fa-trash"></i>
                </button>
              </div>

              <div class="product-edit-fields">
                <div class="field-group">
                  <label>Quantité</label>
                  <input type="number" min="1" v-model.number="editableSale.order_lines[index].quantity" required />
                </div>
                <div class="field-group">
                  <label>Prix unitaire</label>
                  <input type="number" min="0" step="0.01" v-model.number="editableSale.order_lines[index].price" required />
                </div>
                <div class="field-group total-field">
                  <label>Total</label>
                  <div class="calculated-total">
                    {{ formatPrice(calculateLineTotal(line)) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Add Product Button -->
          <button type="button" @click="addProduct" class="add-product-btn">
            <i class="fas fa-plus"></i>
            Ajouter un produit
          </button>
        </div>

        <!-- Actions -->
        <div class="edit-modal-actions">
          <button type="submit" class="save-btn">
            <i class="fas fa-save"></i>
            Enregistrer
          </button>
          <button type="button" @click="close" class="cancel-btn">
            <i class="fas fa-times"></i>
            Annuler
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch, defineEmits, defineProps } from 'vue'

const props = defineProps({
  sale: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['save', 'close'])

const editableSale = reactive(JSON.parse(JSON.stringify(props.sale)))

watch(() => props.sale, (newSale) => {
  Object.assign(editableSale, JSON.parse(JSON.stringify(newSale)))
})

const close = () => {
  emit('close')
}

const formatPrice = (price) => {
  return `${parseFloat(price).toFixed(2)} Ar`
}

const formatDate = (dateString) => {
  const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }
  return new Date(dateString).toLocaleDateString('fr-FR', options)
}

const calculateLineTotal = (line) => {
  return (line.quantity || 0) * (line.price || 0)
}

const calculateTotal = () => {
  if (!editableSale.order_lines || !Array.isArray(editableSale.order_lines)) return 0
  return editableSale.order_lines.reduce((total, line) => total + calculateLineTotal(line), 0)
}

const addProduct = () => {
  editableSale.order_lines.push({
    id: Date.now(), // Temporary ID
    product: { name: 'Nouveau produit' },
    quantity: 1,
    price: 0,
    total: 0
  })
}

const removeProduct = (index) => {
  if (editableSale.order_lines.length > 1) {
    editableSale.order_lines.splice(index, 1)
  }
}

const save = () => {
  console.log('Save method called with editableSale:', editableSale)

  // Calculate total for each order line and convert to integer
  editableSale.order_lines.forEach(line => {
    line.total = Math.round(calculateLineTotal(line)) // Convert to integer
    line.price = Math.round(line.price) // Ensure price is integer
  })

  // Calculate total amount for sale and convert to integer
  editableSale.total_amount = Math.round(calculateTotal())

  emit('save', editableSale)
}
</script>

<style scoped>
/* Modern Edit Modal Styles */
.modern-edit-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modern-edit-modal-content {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #333;
}

/* Header Section */
.edit-modal-header {
  background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
  color: white;
  padding: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.edit-modal-title-section {
  flex: 1;
}

.edit-modal-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.edit-modal-title i {
  font-size: 1.2rem;
  opacity: 0.9;
}

.edit-modal-subtitle {
  display: flex;
  align-items: center;
  gap: 1rem;
  font-size: 0.9rem;
  opacity: 0.9;
}

.edit-modal-ticket,
.edit-modal-date {
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.edit-modal-total {
  text-align: right;
}

.total-amount {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 0.2rem;
}

.total-label {
  font-size: 0.8rem;
  opacity: 0.8;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Content Section */
.edit-modal-form {
  padding: 1.5rem;
  max-height: calc(90vh - 200px);
  overflow-y: auto;
}

/* Info Section */
.edit-info-section {
  background: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 1rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-title i {
  color: #d32f2f;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.info-field {
  display: flex;
  flex-direction: column;
}

.info-field label {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #444;
  font-size: 0.9rem;
}

.info-field input,
.info-field select {
  padding: 0.6rem 0.8rem;
  border: 1.5px solid #ddd;
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.info-field input:focus,
.info-field select:focus {
  outline: none;
  border-color: #d32f2f;
  box-shadow: 0 0 8px rgba(211, 47, 47, 0.3);
}

/* Products Section */
.edit-products-section {
  background: #f8fafc;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.section-badge {
  background: #d32f2f;
  color: white;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.products-edit-list {
  margin-bottom: 1rem;
}

.product-edit-item {
  background: white;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1rem;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.product-edit-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.product-name {
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.product-name i {
  color: #d32f2f;
}

.remove-product-btn {
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 6px;
  padding: 0.5rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.remove-product-btn:hover {
  background: #dc2626;
}

.product-edit-fields {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1rem;
}

.field-group {
  display: flex;
  flex-direction: column;
}

.field-group label {
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #444;
  font-size: 0.9rem;
}

.field-group input {
  padding: 0.6rem 0.8rem;
  border: 1.5px solid #ddd;
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.field-group input:focus {
  outline: none;
  border-color: #d32f2f;
  box-shadow: 0 0 8px rgba(211, 47, 47, 0.3);
}

.calculated-total {
  padding: 0.6rem 0.8rem;
  background: #f8fafc;
  border: 1.5px solid #ddd;
  border-radius: 8px;
  font-weight: 600;
  color: #d32f2f;
  text-align: center;
}

.add-product-btn {
  background: #22c55e;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0 auto;
  transition: background-color 0.3s ease;
}

.add-product-btn:hover {
  background: #16a34a;
}

/* Actions */
.edit-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e2e8f0;
}

.save-btn {
  background: #d32f2f;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 0.75rem 1.5rem;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: background-color 0.3s ease;
}

.save-btn:hover {
  background: #b71c1c;
}

.cancel-btn {
  background: #6b7280;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 0.75rem 1.5rem;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: background-color 0.3s ease;
}

.cancel-btn:hover {
  background: #4b5563;
}

/* Responsive Styles */
@media (max-width: 768px) {
  .modern-edit-modal-content {
    max-width: 95vw;
    margin: 1rem;
  }

  .edit-modal-header {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
    padding: 1rem;
  }

  .edit-modal-title {
    font-size: 1.3rem;
    justify-content: center;
  }

  .edit-modal-subtitle {
    justify-content: center;
    flex-wrap: wrap;
  }

  .edit-modal-total {
    text-align: center;
  }

  .total-amount {
    font-size: 1.5rem;
  }

  .edit-modal-form {
    padding: 1rem;
  }

  .info-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .product-edit-fields {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .edit-modal-actions {
    flex-direction: column;
  }

  .save-btn,
  .cancel-btn {
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .modern-edit-modal-content {
    max-width: 98vw;
    margin: 0.5rem;
  }

  .edit-modal-header {
    padding: 0.75rem;
  }

  .edit-modal-title {
    font-size: 1.1rem;
  }

  .edit-modal-subtitle {
    font-size: 0.8rem;
    gap: 0.5rem;
  }

  .total-amount {
    font-size: 1.3rem;
  }

  .edit-modal-form {
    padding: 0.75rem;
  }

  .edit-info-section,
  .edit-products-section {
    padding: 0.75rem;
  }

  .section-title {
    font-size: 0.9rem;
  }

  .product-edit-item {
    padding: 0.75rem;
  }

  .product-name {
    font-size: 0.9rem;
  }
}
</style>
