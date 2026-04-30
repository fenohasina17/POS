<template>
  <div class="cash-transactions">
    <Profile />

    <section class="page-section">
      <div class="page-header">
        <div>
          <h1>Transactions de caisse</h1>
          <p class="subtitle">Consultez et corrigez les mouvements enregistrés pour chaque session.</p>
        </div>
      </div>

      <div class="content-grid">
        <form class="card" @submit.prevent="submitForm">
          <h2>Modifier une transaction</h2>

          <p v-if="!form.id" class="form-hint">Sélectionnez une transaction dans la liste pour activer l'édition.</p>

          <label class="field">
            <span>ID session</span>
            <input v-model.number="form.session_id" type="number" min="1" :disabled="!form.id" required />
          </label>

          <label class="field">
            <span>Type</span>
            <select v-model="form.type" :disabled="!form.id" required>
              <option disabled value="">Choisir…</option>
              <option value="sale">Vente</option>
              <option value="refund">Remboursement</option>
              <option value="adjustment">Ajustement</option>
            </select>
          </label>

          <label class="field">
            <span>Montant (Ar)</span>
            <input v-model.number="form.amount" type="number" step="0.01" :disabled="!form.id" required />
          </label>

          <label class="field">
            <span>Description</span>
            <textarea v-model="form.description" rows="3" placeholder="Commentaire (optionnel)" :disabled="!form.id"></textarea>
          </label>

          <div class="form-actions">
            <button type="submit" class="btn primary" :disabled="isSaving || !form.id">
              <span v-if="isSaving" class="loading">Enregistrement…</span>
              <span v-else>Mettre à jour</span>
            </button>
            <button type="button" class="btn" @click="resetForm" :disabled="isSaving">Annuler</button>
          </div>

          <p v-if="formError" class="form-error">{{ formError }}</p>
          <p v-if="formSuccess" class="form-success">{{ formSuccess }}</p>
        </form>

        <div class="card list-card">
          <div class="list-header">
            <h2>Historique</h2>
            <button type="button" class="btn" @click="fetchTransactions" :disabled="isLoading">
              Recharger
            </button>
          </div>

          <div v-if="cashTransactionStore.loading" class="list-empty">Chargement des transactions…</div>
          <div v-else-if="cashTransactionStore.transactions.length === 0" class="list-empty">
            Aucune transaction enregistrée pour le moment.
          </div>

          <table v-else class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Session</th>
                <th>Type</th>
                <th>Montant</th>
                <th>Description</th>
                <th>Créée le</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in cashTransactionStore.transactions" :key="transaction.id">
                <td>{{ transaction.id }}</td>
                <td>{{ transaction.session_id }}</td>
                <td class="badge" :data-type="transaction.type">{{ typeLabel(transaction.type) }}</td>
                <td>{{ formatCurrency(transaction.amount) }}</td>
                <td>{{ transaction.description || '—' }}</td>
                <td>{{ formatDate(transaction.created_at) }}</td>
                <td class="table-actions">
                  <button type="button" class="action" @click="editTransaction(transaction)">
                    Modifier
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import Profile from './Profile.vue'
import { useCashTransactionStore } from '@/stores/cashTransactionStore'
import { API_BASE_URL } from '@/utils/api'

const cashTransactionStore = useCashTransactionStore()

const isSaving = ref(false)
const formError = ref('')
const formSuccess = ref('')

const form = reactive({
  id: null,
  session_id: '',
  type: '',
  amount: '',
  description: ''
})

const apiBase = `${API_BASE_URL}/cash-transactions`

const getHeaders = () => {
  const token = localStorage.getItem('token')
  return token ? { Authorization: `Bearer ${token}` } : {}
}

const fetchTransactions = async () => {
  await cashTransactionStore.fetchTransactions()
}

const resetForm = () => {
  form.id = null
  form.session_id = ''
  form.type = ''
  form.amount = ''
  form.description = ''
  formError.value = ''
  formSuccess.value = ''
}

const submitForm = async () => {
  formError.value = ''
  formSuccess.value = ''
  isSaving.value = true

  if (!form.id) {
    formError.value = 'Sélectionnez une transaction à modifier.'
    isSaving.value = false
    return
  }

  const payload = {
    session_id: form.session_id,
    type: form.type,
    amount: form.amount,
    description: form.description
  }

  try {
    await axios.put(`${apiBase}/${form.id}`, payload, { headers: getHeaders() })
    formSuccess.value = 'Transaction mise à jour.'
    await fetchTransactions()
    resetForm()
  } catch (error) {
    console.error('Erreur enregistrement transaction:', error)
    formError.value = error.response?.data?.message || 'Impossible d\'enregistrer la transaction.'
  } finally {
    isSaving.value = false
  }
}

const editTransaction = (transaction) => {
  form.id = transaction.id
  form.session_id = transaction.session_id
  form.type = transaction.type
  form.amount = transaction.amount
  form.description = transaction.description || ''
  formError.value = ''
  formSuccess.value = ''
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const typeLabel = (type) => {
  switch (type) {
    case 'sale':
      return 'Vente'
    case 'refund':
      return 'Remboursement'
    case 'adjustment':
      return 'Ajustement'
    default:
      return type
  }
}

const formatCurrency = (value) => {
  const number = Number(value)
  if (!Number.isFinite(number)) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(number)
}

const formatDate = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString('fr-FR')
}

onMounted(fetchTransactions)
</script>

<style scoped>
.cash-transactions {
  min-height: 100vh;
  background: #f8fafc;
  color: #0f172a;
}

.page-section {
  max-width: 1100px;
  margin: 0 auto;
  padding: 2rem 1.5rem 3rem;
  display: grid;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
}

.page-header h1 {
  font-size: 1.8rem;
  font-weight: 700;
}

.subtitle {
  margin-top: 0.3rem;
  color: #64748b;
  font-size: 0.95rem;
}

.content-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}

.card {
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 15px 40px rgba(15, 23, 42, 0.08);
  padding: 1.5rem;
  display: grid;
  gap: 1rem;
}

.card h2 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
}

.field {
  display: grid;
  gap: 0.35rem;
  font-size: 0.9rem;
  color: #475569;
}

.field input,
.field select,
.field textarea {
  padding: 0.55rem 0.65rem;
  border-radius: 0.65rem;
  border: 1px solid #cbd5f5;
  background: #f8fafc;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
}

.form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.btn {
  border: none;
  padding: 0.55rem 1rem;
  border-radius: 0.75rem;
  background: rgba(148, 163, 184, 0.18);
  color: #1e293b;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn:hover {
  background: rgba(148, 163, 184, 0.28);
}

.btn.primary {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
}

.btn.primary:hover {
  background: linear-gradient(135deg, #1d4ed8, #1e3a8a);
}

.loading {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.form-error {
  color: #dc2626;
  font-size: 0.85rem;
}

.form-success {
  color: #16a34a;
  font-size: 0.85rem;
}

.list-card {
  overflow: hidden;
}

.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.data-table th,
.data-table td {
  padding: 0.65rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

.data-table thead {
  background: rgba(148, 163, 184, 0.12);
  color: #0f172a;
}

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem 0.6rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.badge[data-type='sale'] {
  background: rgba(34, 197, 94, 0.18);
  color: #15803d;
}

.badge[data-type='refund'] {
  background: rgba(239, 68, 68, 0.18);
  color: #b91c1c;
}

.badge[data-type='adjustment'] {
  background: rgba(14, 165, 233, 0.18);
  color: #0369a1;
}

.table-actions {
  display: flex;
  gap: 0.5rem;
}

.action {
  border: none;
  background: transparent;
  color: #2563eb;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.2s ease;
}

.action:hover {
  color: #1d4ed8;
}

.action.danger {
  color: #dc2626;
}

.action.danger:hover {
  color: #b91c1c;
}

.list-empty {
  text-align: center;
  padding: 2rem 1rem;
  color: #64748b;
}

@media (max-width: 768px) {
  .table-actions {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
