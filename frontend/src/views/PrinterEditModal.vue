<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="modal-background absolute inset-0 bg-black/80" @click="closeModal"></div>
    <div class="modal-card relative z-10 rounded-lg bg-white shadow-xl">
      <header class="modal-header">
        <h2 class="modal-title">{{ isEdit ? 'Éditer l\'imprimante' : 'Ajouter une imprimante' }}</h2>
        <button class="modal-close" aria-label="Fermer" @click="closeModal">&times;</button>
      </header>
      <section class="modal-body">
        <!-- Nom de l'imprimante -->
        <div class="field">
          <label class="label">Nom de l'imprimante</label>
          <div class="control">
            <input class="input" type="text" v-model="localPrinter.name" maxlength="255" required
              placeholder="Nom de l'imprimante" />
          </div>
        </div>

        <!-- Caisse associée -->
        <div class="field">
          <label class="label">Caisse associée</label>
          <div class="control">
            <div class="select">
              <select v-model="localPrinter.cash_register_id">
                <option :value="null" disabled>Choisir une caisse</option>
                <option v-for="register in cashRegisters" :key="register.id" :value="register.id">
                  {{ register.name }} [{{ register.point_of_sale ? register.point_of_sale.name : 'N/A' }}]
                </option>
              </select>

            </div>
          </div>
        </div>

        <!-- Type d'imprimante -->
        <div class="field">
          <label class="label">Type d'imprimante</label>
          <div class="control">
            <div class="select">
              <select v-model="localPrinter.printer_type_id">
                <option :value="null" disabled>Choisir un type d'imprimante</option>
                <option v-for="type in printerTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Timeout -->
        <div class="field">
          <label class="label">Timeout (secondes)</label>
          <div class="control">
            <input class="input" type="number" v-model.number="localPrinter.timeout" min="1" />
          </div>
        </div>

        <!-- Par défaut -->
        <div class="field">
          <label class="checkbox">
            <input type="checkbox" v-model="localPrinter.is_default" />
            Imprimante par défaut
          </label>
        </div>

        <!-- Active -->
        <div class="field">
          <label class="checkbox">
            <input type="checkbox" v-model="localPrinter.is_active" />
            Active
          </label>
        </div>
      </section>
      <footer class="modal-footer">
        <button class="button button-success" :class="{ 'is-loading': isSaving }" @click="savePrinter"
          :disabled="isSaving">
          Enregistrer
        </button>
        <button class="button button-cancel" @click="closeModal" :disabled="isSaving">
          Annuler
        </button>
      </footer>
      <p v-if="saveError" class="save-error">{{ saveError }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, reactive, onMounted } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { usePrinterTypes } from '../composables/usePrinterTypes.js'

const props = defineProps({
  isOpen: Boolean,
  printer: Object,
  isEdit: Boolean,
  selectedPOS: {
    type: Object,
    default: null
  }
})

const emits = defineEmits(['close', 'save'])

const localPrinter = reactive({
  id: null,
  name: '',
  cash_register_id: null,
  printer_type_id: null,
  connection_type: 'cups',
  timeout: 30,
  is_default: false,
  is_active: true
})

const cashRegisters = ref([])
const isSaving = ref(false)
const saveError = ref('')

const { printerTypes, fetchPrinterTypes } = usePrinterTypes()



const fetchCashRegisters = async () => {
  try {
    const token = localStorage.getItem('token')
    let url = `${API_BASE_URL}/cash-registers`

    // Filter by selected POS if admin has selected one
    if (props.selectedPOS) {
      url += `?point_of_sale_id=${props.selectedPOS.id}`
    }

    const response = await axios.get(url, {
      headers: { Authorization: `Bearer ${token}` }
    })
    cashRegisters.value = response.data.data ? response.data.data : response.data
  } catch (error) {
    console.error('Erreur lors du chargement des caisses:', error)
  }
}

onMounted(() => {
  fetchCashRegisters()
  fetchPrinterTypes()
})

watch(
  () => props.printer,
  (newPrinter) => {
    if (newPrinter) {
      // Ensure proper type conversion and handle null values correctly
      const processedPrinter = {
        id: newPrinter.id || null,
        name: newPrinter.name || '',
        cash_register_id: newPrinter.cash_register_id || null,
        printer_type_id: newPrinter.printer_type_id || null,
        connection_type: 'cups',
        timeout: newPrinter.timeout !== null && newPrinter.timeout !== undefined ? Number(newPrinter.timeout) : null,
        is_default: newPrinter.is_default === true || newPrinter.is_default === 'true' || newPrinter.is_default === 1,
        is_active: newPrinter.is_active === true || newPrinter.is_active === 'true' || newPrinter.is_active === 1
      }
      Object.assign(localPrinter, processedPrinter)
    } else {
      // Reset for new printer
      Object.assign(localPrinter, {
        id: null,
        name: '',
        cash_register_id: null,
        printer_type_id: null,
        connection_type: 'cups',
        timeout: 30,
        is_default: false,
        is_active: true
      })
    }
  },
  { immediate: true }
)

const savePrinter = async () => {
  try {
    isSaving.value = true
    saveError.value = ''
    const token = localStorage.getItem('token')

    // Ensure all required fields are included and have correct format
    const printerData = {
      name: localPrinter.name,
      cash_register_id: localPrinter.cash_register_id,
      connection_type: 'cups',
      ip_address: null,
      port: null,
      timeout: localPrinter.timeout,
      is_default: localPrinter.is_default,
      is_active: localPrinter.is_active
    }

    let response
    if (props.isEdit) {
      response = await axios.put(
        `${API_BASE_URL}/printers/${localPrinter.id}`,
        printerData,
        { headers: { Authorization: `Bearer ${token}` } }
      )
    } else {
      response = await axios.post(
        `${API_BASE_URL}/printers`,
        printerData,
        { headers: { Authorization: `Bearer ${token}` } }
      )
    }

    emits('save', response.data.data ? response.data.data : response.data)
    emits('close')
  } catch (error) {
    console.error('Erreur:', error.response?.data || error)
    saveError.value = error.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    isSaving.value = false
  }
}

const closeModal = () => {
  // Reset form to initial state
  Object.assign(localPrinter, {
    id: null,
    name: '',
    cash_register_id: null,
    connection_type: 'cups',
    timeout: 30,
    is_default: false,
    is_active: true
  })
  saveError.value = ''
  emits('close')
}
</script>

<style scoped>
/* Bulma removed; using Tailwind utilities in template */

.modal {
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-background {
  background-color: rgba(10, 10, 10, 0.86);
}

.modal-card {
  margin: 0;
  width: 100%;
  max-width: 640px;
  max-height: calc(100vh - 40px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 8px;
}

.modal-header {
  background-color: #fff;
  padding: 1.5rem;
  border-bottom: 1px solid #dbdbdb;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
}

.modal-title {
  font-size: 1.5rem;
  font-weight: bold;
  color: #333;
}

.modal-close {
  background: none;
  border: none;
  font-size: 2rem;
  cursor: pointer;
  line-height: 1;
  color: #888;
  position: absolute;
  top: 1rem;
  right: 1.5rem;
}

.modal-body {
  flex-grow: 1;
  overflow-y: auto;
  background-color: #f5f5f5;
  padding: 1.5rem;
}

.field {
  margin-bottom: 1.5rem;
}

.label {
  font-weight: bold;
  color: #4a4a4a;
  margin-bottom: 0.5rem;
}

.input,
.select select {
  width: 100%;
  border-radius: 4px;
  border: 1px solid #dbdbdb;
  box-shadow: none;
  transition: border-color 0.2s;
}

.input:focus,
.select select:focus {
  border-color: #00d1b2;
  box-shadow: 0 0 0 0.125em rgba(0, 209, 178, 0.25);
}

.select {
  width: 100%;
}

.checkbox {
  font-weight: normal;
  color: #4a4a4a;
}

.modal-footer {
  background-color: #fff;
  padding: 1rem 1.5rem;
  border-top: 1px solid #dbdbdb;
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.button {
  padding: 0.75rem 1.5rem;
  font-weight: bold;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.button-success {
  background-color: #00d1b2;
  color: white;
  border: none;
}

.button-success:hover {
  background-color: #00b89c;
}

.button-cancel {
  background-color: #f5f5f5;
  color: #4a4a4a;
  border: 1px solid #dbdbdb;
}

.button-cancel:hover {
  background-color: #e0e0e0;
}

.button.is-loading::after {
  animation: spinAround 0.5s infinite linear;
  border: 2px solid #fff;
  border-right-color: transparent;
  border-top-color: transparent;
  content: "";
  display: block;
  height: 1em;
  width: 1em;
  position: relative;
  top: 0;
  left: 0;
}

.save-error {
  color: #ff3860;
  text-align: center;
  margin-top: 1rem;
}

@keyframes spinAround {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}
</style>
