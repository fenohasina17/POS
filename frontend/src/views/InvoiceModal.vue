<template>
  <div v-if="isOpen" class="invoice-overlay" @click.self="closeModal">
    <article class="invoice-card">
      <header class="invoice-header">
        <div class="brand">
          <img
            :src="companyLogo || brandLogo"
            :alt="companyName || 'Logo de l\'entreprise'"
            class="brand-logo"
          />
          <p class="brand-name">{{ companyName || 'Votre entreprise' }}</p>
        </div>
        <div class="invoice-meta">
          <h1>Facture</h1>
          <p>N° {{ invoiceNumber || 'N/A' }}</p>
          <p>Date : {{ currentDate }}</p>
          <p>Client : {{ clientName || 'Client' }}</p>
        </div>
      </header>

      <section class="invoice-body">
        <table>
          <thead>
            <tr>
              <th>Description</th>
              <th>Quantité</th>
              <th>Prix unitaire</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in items" :key="index">
              <td>{{ item.name }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ formatCurrency(item.price) }}</td>
              <td>{{ formatCurrency(item.quantity * item.price) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="invoice-summary">
        <div class="summary-row">
          <span>Sous-total</span>
          <span>{{ formatCurrency(subtotal) }}</span>
        </div>
        <div class="summary-row total">
          <span>Total</span>
          <span>{{ formatCurrency(totalAmount) }}</span>
        </div>
      </section>

      <footer class="invoice-footer">
        <button type="button" class="ghost" @click="closeModal">
          <font-awesome-icon icon="fa-solid fa-xmark" />
          Fermer
        </button>
        <button type="button" class="primary" @click="openPaymentModal">
          <font-awesome-icon icon="fa-solid fa-credit-card" />
          Procéder au paiement
        </button>
      </footer>
    </article>
  </div>
</template>

<script>
import brandLogo from '@/assets/logoigp.jpg'

export default {
  name: 'InvoiceModal',
  props: {
    isOpen: {
      type: Boolean,
      required: true
    },
    items: {
      type: Array,
      default: () => []
    },
    clientName: {
      type: String,
      default: 'Client non spécifié'
    },
    invoiceNumber: {
      type: [Number, String],
      default: 'N/A'
    },
    companyLogo: {
      type: String,
      default: ''
    },
    companyName: {
      type: String,
      default: 'Votre entreprise'
    }
  },
  data() {
    return {
      currentDate: new Date().toLocaleDateString('fr-FR'),
      brandLogo
    }
  },
  computed: {
    subtotal() {
      if (!this.items?.length) return 0
      return this.items.reduce((sum, item) => sum + item.quantity * item.price, 0)
    },
    totalAmount() {
      return this.subtotal
    }
  },
  methods: {
    formatCurrency(value) {
      const amount = Number(value) || 0
      return `${new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(amount)} Ar`
    },
    closeModal() {
      this.$emit('close-modal')
    },
    openPaymentModal() {
      this.$emit('openPaymentModal')
    }
  }
}
</script>

<style scoped>
.invoice-overlay {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  z-index: 1100;
}

.invoice-card {
  width: 100%;
  max-width: 760px;
  background: #fff;
  border-radius: 1.5rem;
  box-shadow: 0 35px 80px rgba(15, 23, 42, 0.25);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.invoice-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1.5rem;
  padding: 2rem 2.25rem;
  background: linear-gradient(135deg, rgba(216, 31, 51, 0.08) 0%, #f8fafc 100%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
}

.brand {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  align-items: flex-start;
}

.brand-logo {
  height: 60px;
  max-width: 180px;
  object-fit: contain;
}

.brand-name {
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.invoice-meta {
  text-align: right;
  color: #1e293b;
}

.invoice-meta h1 {
  margin: 0 0 0.75rem;
  font-size: 2rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.invoice-body {
  padding: 0 2.25rem;
}

.invoice-body table {
  width: 100%;
  border-collapse: collapse;
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: inset 0 0 0 1px rgba(226, 232, 240, 0.6);
}

.invoice-body th {
  background: rgba(248, 250, 252, 0.9);
  color: #475569;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.9rem 1rem;
}

.invoice-body td {
  padding: 0.9rem 1rem;
  color: #1f2937;
  font-weight: 600;
  border-top: 1px solid rgba(226, 232, 240, 0.7);
}

.invoice-body tbody tr:nth-child(2n) {
  background: rgba(248, 250, 252, 0.6);
}

.invoice-summary {
  padding: 0 2.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
  color: #475569;
}

.summary-row.total {
  padding: 1rem;
  border-radius: 1rem;
  background: rgba(216, 31, 51, 0.12);
  color: #d81f33;
  font-size: 1.1rem;
}

.invoice-footer {
  border-top: 1px solid rgba(226, 232, 240, 0.6);
  padding: 1.5rem 2.25rem 2rem;
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
}

.invoice-footer button {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  border: none;
  border-radius: 9999px;
  padding: 0.7rem 1.4rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.invoice-footer button:hover {
  transform: translateY(-1px);
}

.invoice-footer .ghost {
  background: rgba(148, 163, 184, 0.16);
  color: #475569;
}

.invoice-footer .ghost:hover {
  box-shadow: 0 12px 25px rgba(148, 163, 184, 0.25);
}

.invoice-footer .primary {
  background: rgba(216, 31, 51, 0.95);
  color: #fff;
  box-shadow: 0 18px 40px rgba(216, 31, 51, 0.35);
}

.invoice-footer .primary:hover {
  box-shadow: 0 22px 50px rgba(216, 31, 51, 0.4);
}

@media (max-width: 768px) {
  .invoice-card {
    border-radius: 1rem;
  }

  .invoice-header {
    flex-direction: column;
    align-items: stretch;
    text-align: left;
  }

  .invoice-meta {
    text-align: left;
  }
}
</style>
