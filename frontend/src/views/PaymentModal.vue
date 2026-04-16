<template>
  <div v-if="isOpen" class="payment-overlay" @click.self="closeModal">
    <section class="payment-card">
      <header class="payment-header">
        <div class="title">
          <font-awesome-icon icon="fa-solid fa-credit-card" />
          <span>Mode de paiement</span>
        </div>
        <button type="button" class="icon ghost" @click="closeModal" aria-label="Fermer">
          <font-awesome-icon icon="fa-solid fa-xmark" />
        </button>
      </header>

      <section class="payment-body">
        <div class="amount-panel">
          <div class="amount-field">
            <label>Total initial</label>
            <div class="field-value muted">{{ formatPrice(totalAmount) }}</div>
          </div>

          <div class="amount-field">
            <label>Remise rapide</label>
            <div class="chip-group">
              <button v-for="option in discountOptions" :key="option" type="button" class="chip"
                :class="{ active: selectedDiscount === option }" @click="selectDiscount(option)">
                {{ option }}%
              </button>
            </div>
          </div>

          <div class="amount-field">
            <label>Total après remise</label>
            <div class="field-value highlight">{{ formatPrice(discountedTotal) }}</div>
          </div>

          <!-- Affichage du reste à payer -->
          <div class="amount-field remaining-section" v-if="paymentsList.length > 0">
            <label>Reste à payer</label>
            <div class="field-value" :class="{ warning: remainingToPay > 0, success: remainingToPay === 0 }">
              {{ formatPrice(remainingToPay) }}
              <span v-if="remainingToPay === 0" class="paid-badge">
                <font-awesome-icon icon="fa-solid fa-check-circle" /> Payé
              </span>
            </div>
          </div>

          <div class="amount-field installment-section">
            <div class="installment-header">
              <label>Paiement </label>
              <button type="button" class="toggle-installment" :class="{ active: isInstallmentActive }"
                @click="toggleInstallment" :disabled="paymentsList.length > 0">
                <font-awesome-icon :icon="isInstallmentActive ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" />
                {{ isInstallmentActive ? 'Séparé' : 'En totalité' }}
              </button>
            </div>

            <div v-if="isInstallmentActive" class="installment-controls">
              <div class="installment-input-group">
                <label>Montant à payer maintenant</label>
                <input type="text" v-model="installmentAmount" @input="validateInstallmentAmount" placeholder="0"
                  class="installment-amount-input" ref="installmentAmountInput" readonly />
              </div>

              <div class="installment-details" v-if="installmentAmount > 0">
                <div class="installment-total">
                  <span>Total à payer</span>
                  <strong>{{ formatPrice(discountedTotal) }}</strong>
                </div>

                <div class="installment-item first-payment">
                  <span>Paiement immédiat</span>
                  <strong>{{ formatPrice(installmentAmountValue) }}</strong>
                </div>
                <div v-if="installmentAmountValue < discountedTotal" class="installment-item remaining-payments">
                  <span>Reste à payer</span>
                  <strong>{{ formatPrice(remainingBalance) }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Résumé des paiements et reste à payer -->
        <div v-if="paymentsList.length > 0" class="payment-summary">
          <div class="summary-header">
            <font-awesome-icon icon="fa-solid fa-receipt" />
            <span>Récapitulatif des paiements</span>
          </div>

          <div class="summary-grid">
            <div class="summary-item">
              <span>Total à payer</span>
              <strong>{{ formatPrice(discountedTotal) }}</strong>
            </div>
            <div class="summary-item">
              <span>Total payé</span>
              <strong class="text-success">{{ formatPrice(totalPaymentsAmount) }}</strong>
            </div>
            <div class="summary-item divider">
              <span>Reste à payer</span>
              <strong :class="remainingToPay === 0 ? 'text-success' : 'text-warning'">
                {{ formatPrice(remainingToPay) }}
              </strong>
            </div>
          </div>

          <!-- Barre de progression -->
          <div class="progress-bar-container">
            <div class="progress-bar" :style="{ width: paymentProgress + '%' }"
              :class="{ completed: remainingToPay === 0 }"></div>
            <span class="progress-text">{{ paymentProgress }}% payé</span>
          </div>
        </div>

        <!-- Liste des paiements multiples -->
        <div v-if="paymentsList.length > 0" class="payments-list-section">
          <div class="payments-list-header">
            <span>Paiements ajoutés</span>
            <span class="badge">{{ paymentsList.length }}</span>
          </div>
          <div class="payments-list">
            <div v-for="(payment, index) in paymentsList" :key="index" class="payment-item">
              <div class="payment-item-info">
                <font-awesome-icon :icon="getPaymentIcon(payment.method)" />
                <span class="payment-method">{{ payment.method }}</span>
                <span class="payment-amount">{{ formatPrice(payment.amount) }}</span>
              </div>
              <div class="payment-item-actions">
                <span v-if="payment.reference" class="payment-reference">{{ payment.reference }}</span>
                <button v-if="remainingToPay > 0 || index === paymentsList.length - 1" class="icon small danger"
                  @click="removePayment(index)" title="Supprimer">
                  <font-awesome-icon icon="fa-solid fa-trash" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Message de confirmation quand tout est payé -->
        <div v-if="remainingToPay === 0 && paymentsList.length > 0" class="completion-message">
          <font-awesome-icon icon="fa-solid fa-circle-check" />
          <div class="completion-text">
            <strong>Tous les paiements sont complets !</strong>
            <span>Vous pouvez maintenant confirmer la transaction</span>
          </div>
        </div>

        <!-- Ajout d'un nouveau paiement (uniquement s'il reste à payer) -->
        <div v-if="remainingToPay > 0" class="add-payment-section">
          <div class="add-payment-header">
            <span>Ajouter un paiement</span>
            <span class="remaining-badge">Reste: {{ formatPrice(remainingToPay) }}</span>
          </div>

          <template v-if="!isInstallmentActive || (isInstallmentActive && paymentsList.length === 0)">
            <div v-if="selectedPayment === 'TPE'" class="details-grid full-row">
              <label>Référence TPE</label>
              <input type="text" v-model="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19"
                ref="cardNumberInput" />
            </div>

            <div v-if="selectedPayment === 'Espèce'" class="details-grid">
              <label>Montant reçu</label>
              <input type="text" v-model="amountReceived" @input="calculateChange" placeholder="0"
                ref="amountReceivedInput" />
              <div v-if="amountReceivedValue > 0" class="field-hint">
                À payer: {{ formatPrice(remainingToPay) }} | Monnaie: {{ formatPrice(amountReceivedValue -
                remainingToPay) }}
              </div>
            </div>

            <div v-if="isMobilePayment" class="details-grid">
              <label>Numéro de téléphone</label>
              <input type="tel" v-model="phoneNumber" placeholder="034 12 345 67" ref="phoneInput" />
            </div>

            <div v-if="selectedPayment && !isMobilePayment && selectedPayment !== 'Espèce'" class="details-grid">
              <label>Référence (optionnel)</label>
              <input type="text" v-model="paymentReference" placeholder="Référence de la transaction" />
            </div>

            <div v-if="selectedPayment" class="details-grid">
              <label>Notes (optionnel)</label>
              <input type="text" v-model="paymentNotes" placeholder="Notes sur le paiement" />
            </div>
          </template>

          <div class="payment-layout">
            <div class="methods">
              <button v-for="payment in payments" :key="payment.name" type="button" class="method"
                :class="{ active: selectedPayment === payment.name }" @click="selectPaymentMethod(payment.name)">
                <font-awesome-icon :icon="getPaymentIcon(payment.name)" />
                <span>{{ payment.name }}</span>
                <font-awesome-icon v-if="selectedPayment === payment.name" icon="fa-solid fa-check-circle"
                  class="check" />
              </button>
            </div>

            <div class="keypad">
              <template v-if="isInstallmentActive && paymentsList.length === 0">
                <button class="keypad-button" @click="appendToInstallmentAmount('7')">7</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('8')">8</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('9')">9</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('4')">4</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('5')">5</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('6')">6</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('1')">1</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('2')">2</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('3')">3</button>
                <button class="keypad-button" @click="appendToInstallmentAmount('0')">0</button>
                <button class="keypad-button" disabled>•</button>
                <button class="keypad-button danger" @click="clearInstallmentAmount">
                  <font-awesome-icon icon="fa-solid fa-delete-left" />
                </button>
              </template>
              <template v-else>
                <button :class="['keypad-button', getKeypadButtonClass('7')]" @click="appendToField('7')">7</button>
                <button :class="['keypad-button', getKeypadButtonClass('8')]" @click="appendToField('8')">8</button>
                <button :class="['keypad-button', getKeypadButtonClass('9')]" @click="appendToField('9')">9</button>
                <button :class="['keypad-button', getKeypadButtonClass('4')]" @click="appendToField('4')">4</button>
                <button :class="['keypad-button', getKeypadButtonClass('5')]" @click="appendToField('5')">5</button>
                <button :class="['keypad-button', getKeypadButtonClass('6')]" @click="appendToField('6')">6</button>
                <button :class="['keypad-button', getKeypadButtonClass('1')]" @click="appendToField('1')">1</button>
                <button :class="['keypad-button', getKeypadButtonClass('2')]" @click="appendToField('2')">2</button>
                <button :class="['keypad-button', getKeypadButtonClass('3')]" @click="appendToField('3')">3</button>
                <button :class="['keypad-button', getKeypadButtonClass('0')]" @click="appendToField('0')">0</button>
                <button v-if="isMobilePayment" class="keypad-button" @click="appendToField(' ')">
                  Espace
                </button>
                <button v-else class="keypad-button" disabled>•</button>
                <button class="keypad-button danger" @click="clearField">
                  <font-awesome-icon icon="fa-solid fa-delete-left" />
                </button>
              </template>
            </div>
          </div>

          <button type="button" class="add-payment-btn"
            :class="{ 'full-payment': getSuggestedAmount() === remainingToPay }" :disabled="!canAddPayment"
            @click="addPayment">
            <font-awesome-icon icon="fa-solid fa-plus" />
            {{ getAddPaymentButtonText() }}
          </button>

          <!-- Suggestion de montant -->
          <div v-if="selectedPayment && remainingToPay > 0" class="suggestion-row">
            <button class="suggestion-btn" @click="suggestFullPayment">
              Payer la totalité ({{ formatPrice(remainingToPay) }})
            </button>
          </div>
        </div>

        <!-- Message si tout est payé -->
        <div v-if="remainingToPay === 0 && paymentsList.length > 0" class="ready-message">
          <font-awesome-icon icon="fa-solid fa-check-circle" />
          <span>Prêt à confirmer le paiement</span>
        </div>
      </section>

      <footer class="payment-footer">
        <button type="button" class="ghost" @click="closeModal">
          <font-awesome-icon icon="fa-solid fa-xmark" />
          Annuler
        </button>
        <button type="button" class="primary" :class="{ 'pulse': remainingToPay === 0 }"
          :disabled="!canConfirmPayment || isProcessing" @click="confirmPayment">
          <font-awesome-icon :icon="isProcessing ? 'fa-solid fa-spinner' : 'fa-solid fa-check'" :spin="isProcessing" />
          {{ getConfirmButtonText() }}
        </button>
      </footer>
    </section>

    <div v-if="isCreatingSale" class="loading-overlay">
      <div class="loading-spinner">
        <font-awesome-icon icon="fa-solid fa-spinner" spin />
        <span>Création de la vente en cours...</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, defineProps, defineEmits, watch, nextTick } from 'vue'
import axios from 'axios'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
  faCreditCard,
  faCheckCircle,
  faXmark,
  faCheck,
  faMoneyBillWave,
  faHandHoldingDollar,
  faMobileScreen,
  faCashRegister,
  faDeleteLeft,
  faCircle,
  faToggleOn,
  faToggleOff,
  faInfoCircle,
  faPlus,
  faTrash,
  faReceipt,
  faCircleCheck,
  faSpinner
} from '@fortawesome/free-solid-svg-icons'
import { API_BASE_URL } from '@/utils/api'

library.add(
  faCreditCard,
  faCheckCircle,
  faXmark,
  faCheck,
  faMoneyBillWave,
  faHandHoldingDollar,
  faMobileScreen,
  faCashRegister,
  faDeleteLeft,
  faCircle,
  faToggleOn,
  faToggleOff,
  faInfoCircle,
  faPlus,
  faTrash,
  faReceipt,
  faCircleCheck,
  faSpinner
)

const props = defineProps({
  isOpen: Boolean,
  saleId: {
    type: [Number, String],
    required: true
  },
  totalAmount: {
    type: Number,
    default: 0
  },
  saleData: {
    type: Object,
    default: () => ({
      items: [],
      total_amount: 0,
      customer_id: null,
      point_of_sale_id: null
    })
  }
})

const token = localStorage.getItem('token')

const emits = defineEmits(['close-modal', 'payment-success', 'payment-error'])

const payments = ref([
  { id: 1, name: 'Espèce', icon: 'fa-solid fa-hand-holding-dollar' },
  { id: 2, name: 'TPE', icon: 'fa-solid fa-credit-card' },
  { id: 3, name: 'Orange Money', icon: 'fa-solid fa-mobile-screen' },
  { id: 4, name: 'MVola', icon: 'fa-solid fa-mobile-screen' },
  { id: 5, name: 'Airtel Money', icon: 'fa-solid fa-mobile-screen' }
])

// États
const amountReceivedInput = ref(null)
const cardNumberInput = ref(null)
const phoneInput = ref(null)
const installmentAmountInput = ref(null)
const selectedPayment = ref('')
const amountReceived = ref('')
const phoneNumber = ref('')
const cardNumber = ref('')
const paymentReference = ref('')
const paymentNotes = ref('')
const isProcessing = ref(false)
const mobilePayments = ['Orange Money', 'MVola', 'Airtel Money']
const discountOptions = [0, 50, 100]
const selectedDiscount = ref(0)
const isCreatingSale = ref(false)
const localSaleId = ref(null)

// Liste des paiements ajoutés
const paymentsList = ref([])

// Mode paiement séparé
const isInstallmentActive = ref(false)
const installmentAmount = ref('')

// Computed properties
const isMobilePayment = computed(() => mobilePayments.includes(selectedPayment.value))

const amountReceivedValue = computed(() => {
  return parseInt(amountReceived.value, 10) || 0
})

const effectiveSaleId = computed(() => {
  return props.saleId || localSaleId.value
})

// Vérifie si la vente est prête
const isSaleReady = computed(() => {
  return effectiveSaleId.value !== null
})

const isValidPhoneNumber = computed(() => {
  if (!isMobilePayment.value) return true
  return phoneNumber.value.replace(/\D/g, '').length >= 10
})

const discountedTotal = computed(() => {
  const base = Math.round(props.totalAmount || 0)
  const discount = Math.max(0, Math.min(100, selectedDiscount.value))
  return Math.round(base * (100 - discount) / 100)
})

const installmentAmountValue = computed(() => {
  return parseInt(installmentAmount.value, 10) || 0
})

const remainingBalance = computed(() => {
  return Math.max(0, discountedTotal.value - installmentAmountValue.value)
})

const totalPaymentsAmount = computed(() => {
  return paymentsList.value.reduce((sum, payment) => sum + payment.amount, 0)
})

const remainingToPay = computed(() => {
  return Math.max(0, discountedTotal.value - totalPaymentsAmount.value)
})

const paymentProgress = computed(() => {
  if (discountedTotal.value === 0) return 0
  return Math.round((totalPaymentsAmount.value / discountedTotal.value) * 100)
})

const canAddPayment = computed(() => {
  if (!selectedPayment.value || remainingToPay.value === 0) return false

  if (isInstallmentActive.value && paymentsList.value.length === 0) {
    return installmentAmountValue.value > 0 && installmentAmountValue.value <= discountedTotal.value
  }

  if (selectedPayment.value === 'TPE') {
    return cardNumber.value.replace(/\D/g, '').length === 16
  }
  if (isMobilePayment.value) {
    return isValidPhoneNumber.value
  }
  if (selectedPayment.value === 'Espèce') {
    return amountReceivedValue.value > 0
  }
  return true
})

// const canConfirmPayment = computed(() => {
//   return paymentsList.value.length > 0 && remainingToPay.value === 0 && !isProcessing.value
// })
const canConfirmPayment = computed(() => {
  return paymentsList.value.length > 0 &&
    remainingToPay.value === 0 &&
    !isProcessing.value &&
    props.saleId !== null // Vérification directe
})

const debug = ref(false)



const createSale = async () => {
  if (isCreatingSale.value) return

  isCreatingSale.value = true

  console.log("--- ----- +++ ++++ : ", props.saleData.items, props.saleData.total_amount)

  try {
    const response = await axios.post(API_BASE_URL + '/sales', {
      items: props.saleData.items,
      total_amount: props.saleData.total_amount,
      discount_percentage: selectedDiscount.value,
      final_amount: discountedTotal.value,
      customer_id: props.saleData.customer_id,
      point_of_sale_id: props.saleData.point_of_sale_id,
      status: 'pending'
    }, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })

    localSaleId.value = response.data.sale.id

    // Émettre l'événement pour informer le parent
    emits('sale-created', response.data.sale)

    return response.data.sale
  } catch (error) {
    console.error('Erreur lors de la création de la vente:', error)
    throw error
  } finally {
    isCreatingSale.value = false
  }
}

const isCashPadLocked = computed(() => {
  if (selectedPayment.value !== 'Espèce') return false
  return amountReceivedValue.value >= discountedTotal.value && discountedTotal.value > 0
})

const changeAmount = computed(() => {
  if (selectedPayment.value === 'Espèce') {
    return amountReceivedValue.value - remainingToPay.value
  }
  return totalPaymentsAmount.value - discountedTotal.value
})

// Méthodes
const formatPrice = (price) => {
  const amount = Math.round(Number(price) || 0)
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(amount)} Ar`
}

const getPaymentIcon = (paymentName) => {
  return payments.value.find(p => p.name === paymentName)?.icon || 'fa-solid fa-credit-card'
}

const getPaymentId = (paymentName) => {
  return payments.value.find(p => p.name === paymentName)?.id || 1
}

const getKeypadButtonClass = (value) => {
  if (selectedPayment.value === 'Espèce') {
    const newValue = parseInt((amountReceived.value === '0' ? '' : amountReceived.value) + value, 10) || 0
    if (newValue > remainingToPay.value) {
      return 'warning'
    }
  }
  return 'active'
}

const getAddPaymentButtonText = () => {
  if (!selectedPayment.value) return 'Ajouter ce paiement'

  const suggestedAmount = getSuggestedAmount()
  if (suggestedAmount > 0) {
    return `Ajouter ${formatPrice(suggestedAmount)}`
  }
  return 'Ajouter ce paiement'
}

const getConfirmButtonText = () => {
  if (isProcessing.value) return 'Traitement...'
  if (remainingToPay.value === 0) return 'Confirmer le paiement total'
  return 'Complétez le paiement'
}

const getSuggestedAmount = () => {
  if (!selectedPayment.value) return 0

  if (selectedPayment.value === 'Espèce') {
    return amountReceivedValue.value
  }

  if (isInstallmentActive.value && paymentsList.value.length === 0) {
    return installmentAmountValue.value
  }

  return remainingToPay.value
}

const suggestFullPayment = () => {
  if (selectedPayment.value === 'Espèce') {
    amountReceived.value = remainingToPay.value.toString()
  } else if (selectedPayment.value === 'TPE') {
    // Pour TPE, on garde le montant du reste à payer
    // La référence sera gérée par l'utilisateur
  } else if (isMobilePayment.value) {
    // Pour mobile money, même chose
  }
}

const toggleInstallment = () => {
  if (paymentsList.value.length > 0) return

  isInstallmentActive.value = !isInstallmentActive.value
  if (!isInstallmentActive.value) {
    installmentAmount.value = ''
    paymentsList.value = []
  } else {
    paymentsList.value = []
    nextTick(() => {
      installmentAmountInput.value?.focus()
    })
  }
}

const validateInstallmentAmount = () => {
  installmentAmount.value = installmentAmount.value.replace(/[^0-9]/g, '')
  const amount = parseInt(installmentAmount.value, 10) || 0
  if (amount > discountedTotal.value) {
    installmentAmount.value = discountedTotal.value.toString()
  }
}

const appendToInstallmentAmount = (value) => {
  if (value === ' ') return
  const current = installmentAmount.value === '0' ? '' : installmentAmount.value
  const newValue = current + value
  const amount = parseInt(newValue, 10) || 0

  if (amount <= discountedTotal.value) {
    installmentAmount.value = newValue
  } else {
    installmentAmount.value = discountedTotal.value.toString()
  }
}

const clearInstallmentAmount = () => {
  installmentAmount.value = installmentAmount.value.slice(0, -1)
}

const selectPaymentMethod = (method) => {
  selectedPayment.value = method
  resetPaymentFields()
  nextTick(() => {
    if (method === 'TPE') cardNumberInput.value?.focus()
    else if (isMobilePayment.value) phoneInput.value?.focus()
    else if (method === 'Espèce') amountReceivedInput.value?.focus()
  })
}

const resetPaymentFields = () => {
  amountReceived.value = ''
  phoneNumber.value = ''
  cardNumber.value = ''
  paymentReference.value = ''
  paymentNotes.value = ''
}

const appendToField = (value) => {
  if (selectedPayment.value === 'TPE') {
    const current = cardNumber.value.replace(/\D/g, '')
    if (current.length >= 16 && value !== ' ') return
    const newValue = current + (value === ' ' ? '' : value)
    cardNumber.value = newValue.match(/.{1,4}/g)?.join(' ').slice(0, 19) || ''
  } else if (isMobilePayment.value) {
    const current = phoneNumber.value.replace(/\D/g, '')
    if (current.length >= 10 && value !== ' ') return
    phoneNumber.value = (current + value)
      .replace(/(\d{3})(\d{2})(\d{3})(\d{2})/, '$1 $2 $3 $4')
      .slice(0, 14)
  } else if (selectedPayment.value === 'Espèce') {
    if (value === ' ') return
    const newValue = amountReceived.value === '0' ? value : amountReceived.value + value
    amountReceived.value = newValue.replace(/[^0-9]/g, '')
  }
}

const clearField = () => {
  if (selectedPayment.value === 'TPE') {
    const current = cardNumber.value.replace(/\D/g, '').slice(0, -1)
    cardNumber.value = current.match(/.{1,4}/g)?.join(' ') || ''
  } else if (isMobilePayment.value) {
    phoneNumber.value = phoneNumber.value.slice(0, -1).trim()
  } else if (selectedPayment.value === 'Espèce') {
    amountReceived.value = amountReceived.value.slice(0, -1) || '0'
  }
}

const calculateChange = () => {
  // Géré par le computed changeAmount
}

const selectDiscount = (value) => {
  selectedDiscount.value = value

  if (totalPaymentsAmount.value > discountedTotal.value) {
    // Ajuster les paiements si nécessaire
    const excess = totalPaymentsAmount.value - discountedTotal.value
    if (excess > 0) {
      // On peut soit réduire le dernier paiement, soit garder l'excédent
    }
  }

  if (isInstallmentActive.value && installmentAmountValue.value > discountedTotal.value) {
    installmentAmount.value = discountedTotal.value.toString()
  }
}

const addPayment = () => {
  let amount = 0
  let reference = paymentReference.value

  if (selectedPayment.value === 'Espèce') {
    amount = Math.min(amountReceivedValue.value, remainingToPay.value)
    reference = reference || `ESP-${Date.now()}`
  } else if (selectedPayment.value === 'TPE') {
    amount = isInstallmentActive.value && paymentsList.value.length === 0
      ? installmentAmountValue.value
      : remainingToPay.value
    reference = reference || cardNumber.value.replace(/\s/g, '') || `TPE-${Date.now()}`
  } else if (isMobilePayment.value) {
    amount = isInstallmentActive.value && paymentsList.value.length === 0
      ? installmentAmountValue.value
      : remainingToPay.value
    reference = reference || phoneNumber.value.replace(/\s/g, '') || `MOB-${Date.now()}`
  }

  if (amount <= 0) {
    alert('Le montant doit être supérieur à 0')
    return
  }

  paymentsList.value.push({
    payment_id: getPaymentId(selectedPayment.value),
    method: selectedPayment.value,
    amount: amount,
    reference: reference,
    notes: paymentNotes.value || `Paiement par ${selectedPayment.value}`
  })

  resetPaymentFields()
  selectedPayment.value = ''

  if (isInstallmentActive.value) {
    isInstallmentActive.value = false
    installmentAmount.value = ''
  }

  paymentsList.value = [...paymentsList.value]

  // Si après ajout, tout est payé, vérifions l'état
  if (remainingToPay.value === 0) {
    console.log('Paiement complet! Vérification:', {
      paymentsList: paymentsList.value,
      remainingToPay: remainingToPay.value,
      isSaleReady: isSaleReady.value
    })
  }
}

const removePayment = (index) => {
  paymentsList.value.splice(index, 1)
}

const closeModal = () => {
  selectedPayment.value = ''
  amountReceived.value = ''
  phoneNumber.value = ''
  cardNumber.value = ''
  paymentReference.value = ''
  paymentNotes.value = ''
  selectedDiscount.value = 0
  isProcessing.value = false
  isInstallmentActive.value = false
  installmentAmount.value = ''
  paymentsList.value = []
  emits('close-modal')
}

// const confirmPayment = async () => {
//   if (!canConfirmPayment.value) return

//   isProcessing.value = true

//   try {
//     const paymentData = {
//       payments: paymentsList.value.map(p => ({
//         payment_id: p.payment_id,
//         amount: p.amount,
//         reference: p.reference || null,
//         notes: p.notes || null
//       })),
//       change_amount: changeAmount.value > 0 ? changeAmount.value : 0
//     }

//     const response = await axios.post(API_BASE_URL +
//       `/sales/${props.saleId}/payments`,
//       paymentData,
//       {
//         headers: {
//           'Authorization': `Bearer ${localStorage.getItem('token')}`,
//           'Content-Type': 'application/json'
//         }
//       }
//     )

//     if (response.status === 201) {
//       emits('payment-success', {
//         message: response.data.message,
//         sale: response.data.sale,
//         total_paid: response.data.amount_received,
//         remaining: response.data.remaining,
//         change: response.data.change
//       })
//       closeModal()
//     }
//   } catch (error) {
//     console.error('Erreur lors du paiement:', error)

//     let errorMessage = 'Erreur lors du traitement du paiement'
//     if (error.response) {
//       errorMessage = error.response.data.message || errorMessage
//     }

//     emits('payment-error', {
//       message: errorMessage,
//       error: error.response?.data || error
//     })

//     alert(errorMessage)
//   } finally {
//     isProcessing.value = false
//   }
// }

const confirmPayment = async () => {
  if (!canConfirmPayment.value) return

  isProcessing.value = true

  try {
    // Si pas d'ID de vente, créer d'abord la vente
    if (!effectiveSaleId.value) {
      await createSale()
    }

    // Maintenant on a un ID de vente
    if (!effectiveSaleId.value) {
      throw new Error('Impossible de créer la vente')
    }

    const paymentData = {
      payments: paymentsList.value.map(p => ({
        payment_id: p.payment_id,
        amount: p.amount,
        reference: p.reference || null,
        notes: p.notes || null
      })),
      change_amount: changeAmount.value > 0 ? changeAmount.value : 0
    }

    const response = await axios.post(
      `/api/sales/${effectiveSaleId.value}/payments`,
      paymentData,
      {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        }
      }
    )

    if (response.status === 201) {
      emits('payment-success', {
        message: response.data.message,
        sale: response.data.sale,
        sale_id: effectiveSaleId.value,
        total_paid: response.data.amount_received,
        remaining: response.data.remaining,
        change: response.data.change
      })
      closeModal()
    }
  } catch (error) {
    console.error('Erreur lors du paiement:', error)

    let errorMessage = 'Erreur lors du traitement du paiement'
    if (error.response) {
      errorMessage = error.response.data.message || errorMessage
    }

    emits('payment-error', {
      message: errorMessage,
      error: error.response?.data || error
    })

    alert(errorMessage)
  } finally {
    isProcessing.value = false
  }
}

// Watchers
// watch(() => props.isOpen, (newVal) => {
//   if (newVal) {
//     amountReceived.value = ''
//     phoneNumber.value = ''
//     cardNumber.value = ''
//     paymentReference.value = ''
//     paymentNotes.value = ''
//     selectedDiscount.value = 0
//     isInstallmentActive.value = false
//     installmentAmount.value = ''
//     paymentsList.value = []
//   }
// })

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    localSaleId.value = null
    amountReceived.value = ''
    phoneNumber.value = ''
    cardNumber.value = ''
    paymentReference.value = ''
    paymentNotes.value = ''
    selectedDiscount.value = 0
    isInstallmentActive.value = false
    installmentAmount.value = ''
    paymentsList.value = []
  }
})

watch(discountedTotal, (newTotal) => {
  if (isInstallmentActive.value && installmentAmountValue.value > newTotal) {
    installmentAmount.value = newTotal.toString()
  }
})

watch(remainingToPay, (newVal) => {
  if (newVal === 0 && selectedPayment.value) {
    // Si tout est payé, on vide la sélection
    selectedPayment.value = ''
  }
})
</script>

<style scoped>
.payment-overlay {
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

.payment-card {
  width: 100%;
  max-width: 820px;
  max-height: 90vh;
  background: #fff;
  border-radius: 1.5rem;
  box-shadow: 0 35px 70px rgba(15, 23, 42, 0.22);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem 2rem;
  background: linear-gradient(140deg, #eef2ff 0%, #f8fafc 100%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
}

.payment-header .title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 700;
  color: #1e293b;
  font-size: 1.1rem;
}

.payment-header .icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 9999px;
  cursor: pointer;
  transition: background 0.2s ease;
  border: none;
}

.payment-header .icon.ghost {
  background: rgba(148, 163, 184, 0.18);
  color: #475569;
}

.payment-header .icon.ghost:hover {
  background: rgba(148, 163, 184, 0.28);
}

.payment-body {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.75rem;
  background: rgba(248, 250, 252, 0.9);
  flex: 1;
  overflow-y: auto;
}

.amount-panel {
  display: grid;
  gap: 1.25rem;
  grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr);
}

.amount-field {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.amount-field label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 700;
  color: #64748b;
}

.field-value {
  border-radius: 1rem;
  padding: 0.8rem 1rem;
  font-weight: 700;
  color: #1e293b;
  background: #fff;
  border: 1px solid rgba(226, 232, 240, 0.7);
}

.field-value.muted {
  background: rgba(248, 250, 252, 0.9);
  color: #64748b;
}

.field-value.highlight {
  background: rgba(129, 140, 248, 0.12);
  color: #4338ca;
}

.chip-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.chip {
  border: none;
  border-radius: 9999px;
  padding: 0.45rem 0.9rem;
  font-weight: 600;
  background: rgba(148, 163, 184, 0.16);
  color: #475569;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.2s ease;
  font-size: 0.9rem;
}

.chip:hover {
  transform: translateY(-1px);
  background: rgba(148, 163, 184, 0.24);
}

.chip.active {
  background: rgba(79, 70, 229, 0.9);
  color: #fff;
  box-shadow: 0 12px 25px rgba(79, 70, 229, 0.28);
}

.installment-section {
  grid-column: 1 / -1;
  background: #f8fafc;
  border-radius: 1rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.installment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.toggle-installment {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.toggle-installment:hover {
  background: #f1f5f9;
}

.toggle-installment.active {
  background: #818cf8;
  color: white;
  border-color: #818cf8;
}

.installment-controls {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.installment-input-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.installment-input-group label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.installment-amount-input {
  border-radius: 0.9rem;
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: #fff;
  padding: 0.75rem 1rem;
  font-weight: 600;
  color: #1f2937;
  outline: none;
  transition: border 0.2s ease, box-shadow 0.2s ease;
  font-size: 1.1rem;
  background-color: #f9fafb;
}

.installment-amount-input:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
  background-color: #fff;
}

.installment-details {
  background: white;
  border-radius: 0.75rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.installment-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px dashed #e2e8f0;
}

.installment-item:last-child {
  border-bottom: none;
}

.installment-item.first-payment {
  color: #059669;
}

.installment-item.remaining-payments {
  color: #7c3aed;
}

.installment-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0 0 0;
  margin-top: 0.5rem;
  border-top: 2px solid #e2e8f0;
  font-weight: 700;
  color: #1f2937;
}

.payments-list-section {
  background: #f8fafc;
  border-radius: 1rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.payments-list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  font-weight: 600;
  color: #1e293b;
}

.badge {
  background: #818cf8;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
}

.payments-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
  max-height: 200px;
  overflow-y: auto;
}

.payment-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: white;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
}

.payment-item-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.payment-method {
  font-weight: 600;
  color: #1e293b;
}

.payment-amount {
  font-weight: 700;
  color: #059669;
}

.payment-item-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.payment-reference {
  font-size: 0.75rem;
  color: #64748b;
  background: #f1f5f9;
  padding: 0.25rem 0.5rem;
  border-radius: 0.5rem;
}

.icon.small {
  width: 2rem;
  height: 2rem;
  border-radius: 9999px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.icon.small.danger {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.icon.small.danger:hover {
  background: rgba(239, 68, 68, 0.2);
}

.payments-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 0;
  border-top: 2px solid #e2e8f0;
  font-weight: 600;
  color: #1e293b;
}

.payments-total strong {
  color: #059669;
  font-size: 1.1rem;
}

.excess-message {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  background: #fef3c7;
  border-radius: 0.5rem;
  color: #92400e;
  font-size: 0.875rem;
}

.add-payment-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  background: white;
  border-radius: 1rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.add-payment-header {
  font-weight: 600;
  color: #1e293b;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.details-grid {
  width: 100%;
  display: grid;
  gap: 0.6rem;
}

.details-grid.full-row {
  grid-column: 1 / -1;
}

.details-grid label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.details-grid input {
  border-radius: 0.9rem;
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: #fff;
  padding: 0.75rem 1rem;
  font-weight: 600;
  color: #1f2937;
  outline: none;
  transition: border 0.2s ease, box-shadow 0.2s ease;
  width: 100%;
}

.details-grid input:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}

.payment-layout {
  display: grid;
  gap: 1.5rem;
  border-radius: 1.25rem;
  padding: 1.5rem;
  background: #fff;
  border: 1px solid rgba(226, 232, 240, 0.7);
}

@media (min-width: 900px) {
  .payment-layout {
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    align-items: stretch;
  }
}

.methods {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.75rem;
}

.method {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  padding: 0.9rem 0.6rem;
  border-radius: 1rem;
  border: 1px solid rgba(226, 232, 240, 0.7);
  background: rgba(248, 250, 252, 0.7);
  color: #475569;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  font-size: 0.9rem;
}

.method:hover {
  transform: translateY(-1px);
  box-shadow: 0 15px 35px rgba(79, 70, 229, 0.15);
}

.method.active {
  background: rgba(79, 70, 229, 0.95);
  color: #fff;
  border-color: transparent;
  box-shadow: 0 20px 45px rgba(79, 70, 229, 0.28);
}

.method .check {
  position: absolute;
  top: 0.45rem;
  right: 0.55rem;
}

.keypad {
  display: grid;
  grid-template-columns: repeat(3, minmax(70px, 1fr));
  gap: 0.6rem;
  max-width: 320px;
  margin: 0 auto;
}

.keypad-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.85rem;
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: rgba(248, 250, 252, 0.8);
  font-weight: 700;
  color: #1f2937;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.2s ease;
  padding: 1rem;
  font-size: 1.1rem;
}

.keypad-button.active:hover {
  transform: translateY(-1px);
  background: rgba(226, 232, 240, 0.9);
}

.keypad-button.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.keypad-button.danger {
  background: rgba(248, 113, 113, 0.16);
  color: #dc2626;
  border-color: rgba(248, 113, 113, 0.3);
}

.keypad-button.danger:hover {
  background: rgba(248, 113, 113, 0.24);
}

.add-payment-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem;
  border-radius: 0.75rem;
  border: none;
  background: #818cf8;
  color: white;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
  font-size: 1rem;
}

.add-payment-btn:hover:not(:disabled) {
  background: #6366f1;
}

.add-payment-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.payment-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1rem 2rem;
  border-top: 1px solid rgba(226, 232, 240, 0.6);
  background: #fff;
}

.payment-footer button {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  border-radius: 9999px;
  padding: 0.75rem 1.6rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  font-size: 0.95rem;
}

.payment-footer .ghost {
  background: rgba(148, 163, 184, 0.2);
  color: #475569;
}

.payment-footer .ghost:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 25px rgba(148, 163, 184, 0.2);
}

.payment-footer .primary {
  background: rgba(79, 70, 229, 0.95);
  color: #fff;
  box-shadow: 0 18px 40px rgba(79, 70, 229, 0.3);
}

.payment-footer .primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

.payment-footer .primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 22px 50px rgba(79, 70, 229, 0.35);
}

@media (max-width: 768px) {
  .payment-card {
    border-radius: 1rem;
  }

  .payment-header {
    padding: 1rem 1.5rem;
  }

  .payment-body {
    padding: 1.5rem;
  }

  .amount-panel {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .payment-layout {
    flex-direction: column;
    padding: 1rem;
  }

  .keypad {
    max-width: none;
  }

  .payment-footer {
    padding: 1rem 1.5rem;
    flex-direction: column-reverse;
  }

  .payment-footer button {
    width: 100%;
    justify-content: center;
  }
}

.remaining-section {
  grid-column: 1 / -1;
}

.remaining-section .field-value {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 1.2rem;
}

.remaining-section .field-value.warning {
  background: #fef3c7;
  color: #92400e;
  border-color: #fcd34d;
}

.remaining-section .field-value.success {
  background: #d1fae5;
  color: #065f46;
  border-color: #6ee7b7;
}

.paid-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: #059669;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
}

.payment-summary {
  background: white;
  border-radius: 1rem;
  padding: 1.25rem;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.summary-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: #1e293b;
  font-weight: 600;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.summary-grid {
  display: grid;
  gap: 0.75rem;
  padding: 1rem 0;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.summary-item strong {
  font-size: 1.1rem;
}

.summary-item.divider {
  padding-top: 0.75rem;
  border-top: 2px dashed #e2e8f0;
  font-weight: 700;
}

.text-success {
  color: #059669;
}

.text-warning {
  color: #b45309;
}

.progress-bar-container {
  margin-top: 0.5rem;
  height: 0.75rem;
  background: #e2e8f0;
  border-radius: 9999px;
  overflow: hidden;
  position: relative;
}

.progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #818cf8, #6366f1);
  border-radius: 9999px;
  transition: width 0.3s ease;
}

.progress-bar.completed {
  background: linear-gradient(90deg, #059669, #10b981);
}

.progress-text {
  position: absolute;
  right: 0.5rem;
  top: -1.5rem;
  font-size: 0.7rem;
  color: #64748b;
}

.completion-message {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: #d1fae5;
  border-radius: 1rem;
  border: 1px solid #6ee7b7;
  color: #065f46;
}

.completion-message svg {
  font-size: 1.5rem;
  color: #059669;
}

.completion-text {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.completion-text strong {
  font-size: 1rem;
}

.completion-text span {
  font-size: 0.875rem;
  opacity: 0.9;
}

.remaining-badge {
  background: #fef3c7;
  color: #92400e;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.field-hint {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.25rem;
  padding-left: 0.5rem;
}

.keypad-button.warning {
  background: #fef3c7;
  color: #92400e;
  border-color: #fcd34d;
}

.keypad-button.warning:hover {
  background: #fde68a;
}

.add-payment-btn.full-payment {
  background: #059669;
}

.suggestion-row {
  display: flex;
  justify-content: center;
  margin-top: 0.5rem;
}

.suggestion-btn {
  background: none;
  border: 1px dashed #818cf8;
  color: #4f46e5;
  padding: 0.5rem 1rem;
  border-radius: 9999px;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.suggestion-btn:hover {
  background: #eef2ff;
  border-style: solid;
}

.ready-message {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 1rem;
  background: #d1fae5;
  border-radius: 1rem;
  color: #065f46;
  font-weight: 600;
}

.ready-message svg {
  font-size: 1.25rem;
  color: #059669;
}

.payment-footer .primary.pulse {
  animation: pulse 2s infinite;
  background: #059669;
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.7);
  }

  70% {
    box-shadow: 0 0 0 10px rgba(5, 150, 105, 0);
  }

  100% {
    box-shadow: 0 0 0 0 rgba(5, 150, 105, 0);
  }
}

.fa-spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}

.loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
  border-radius: 1.5rem;
}

.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 2rem;
  background: white;
  border-radius: 1rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.loading-spinner svg {
  font-size: 2rem;
  color: #4f46e5;
}

.loading-spinner span {
  color: #1e293b;
  font-weight: 600;
}
</style>