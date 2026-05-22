<template>
  <div v-if="isOpen" class="payment-overlay" @click.self="closeModal">
    <section class="payment-card">
      <header class="payment-header">
        <div class="title">
          <FontAwesomeIcon icon="fa-solid fa-credit-card" />
          <span>Finaliser le paiement</span>
        </div>
        <button type="button" class="btn-icon ghost" @click="closeModal">
          <FontAwesomeIcon icon="fa-solid fa-xmark" />
        </button>
      </header>

      <div class="payment-body">
        <!-- NOUVELLE COLONNE : Détails des articles (Style Reçu Moderne) -->
        <div class="payment-col col-0 hidden md:flex">
          <div class="panel h-full flex flex-col bg-slate-50/50 border-dashed border-slate-200">
            <div class="flex items-center justify-between mb-4 px-1">
              <div class="section-title !mb-0">Détails commande</div>
              <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-black text-slate-600">
                {{ saleData.items?.length || 0 }} réf.
              </span>
            </div>

            <div class="flex-1 overflow-y-auto space-y-1 pr-1 scrollbar-hide">
              <div v-for="(item, idx) in groupedItems" :key="idx" class="group flex items-center justify-between rounded-xl px-2 py-2.5 transition-colors hover:bg-white hover:shadow-sm">
                <div class="flex-1 min-w-0">
                  <p class="text-[11px] font-black text-slate-700 truncate leading-tight uppercase tracking-tight">{{ item.name || 'Produit' }}</p>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[10px] font-bold text-indigo-500 bg-indigo-50 px-1.5 rounded-md">x{{ item.quantity }}</span>
                    <span class="text-[9px] font-medium text-slate-400">{{ formatPrice(item.unit_price) }} / u</span>
                  </div>
                </div>
                <div class="text-right ml-2">
                  <p class="text-[11px] font-black text-slate-900">
                    {{ formatPrice(item.unit_price * item.quantity) }}
                  </p>
                </div>
              </div>
            </div>

            <div class="pt-4 border-t border-dashed border-slate-200 mt-4 px-1 space-y-2">
              <div class="flex justify-between text-[11px] font-bold text-slate-500 uppercase tracking-widest">
                <span>Sous-total HT</span>
                <span>{{ formatPrice(totalAmount) }}</span>
              </div>
              <div class="flex justify-between text-sm font-black text-indigo-600 pt-1">
                <span>TOTAL NET</span>
                <span class="text-base">{{ formatPrice(totalAmount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- COL 1 : Montants + détails paiement -->
        <div class="payment-col col-1">
          <div class="panel">
            <div class="solde-row">
              <label>Total de la vente</label>
              <span class="value">{{ formatPrice(totalAmount) }}</span>
            </div>
            <div class="solde-row">
              <label>Remise</label>
              <div class="chip-group">
                <button
                  v-for="opt in discountOptions"
                  :key="opt"
                  type="button"
                  class="chip"
                  :class="{ active: selectedDiscount === opt }"
                  @click="selectDiscount(opt)"
                >
                  {{ opt }}%
                </button>
              </div>
            </div>
            <div class="solde-row">
              <label>Total après remise</label>
              <span class="value highlight">{{ formatPrice(discountedTotal) }}</span>
            </div>
            <template v-if="paymentsList.length">
              <div class="solde-row">
                <label>Déjà payé</label>
                <span class="value success">{{ formatPrice(totalPaymentsAmount) }}</span>
              </div>
              <div class="solde-row">
                <label>Reste à payer</label>
                <span class="value warning">{{ formatPrice(remainingToPay) }}</span>
              </div>
            </template>
          </div>

          <!-- Section Espèces -->
          <div v-if="selectedPayment === 'Espèce'" class="panel mt">
            <label class="field-label">💵 Montant reçu (espèces)</label>
            <div class="input-wrapper">
              <input
                type="text"
                v-model="amountReceived"
                placeholder="0"
                class="field-input"
                @focus="activeInput = 'amountReceived'"
                @input="sanitizeAmount"
              />
              <span class="input-suffix">Ar</span>
            </div>
            <div v-if="amountReceivedValue > 0" class="cash-info">
              <div class="info-row">
                <span>💰 À payer</span>
                <strong>{{ formatPrice(remainingToPay) }}</strong>
              </div>
              <div class="info-row" :class="amountReceivedValue >= remainingToPay ? 'success' : 'warning'">
                <span>✅ Reçu</span>
                <strong>{{ formatPrice(amountReceivedValue) }}</strong>
              </div>
              <div v-if="amountReceivedValue > remainingToPay" class="info-row change">
                <span>🔄 Monnaie à rendre</span>
                <strong class="change-amount">{{ formatPrice(amountReceivedValue - remainingToPay) }}</strong>
              </div>
              <div v-else-if="amountReceivedValue < remainingToPay" class="info-row warning">
                <span>⚠️ Il manque</span>
                <strong>{{ formatPrice(remainingToPay - amountReceivedValue) }}</strong>
              </div>
            </div>
          </div>

          <!-- Section TPE -->
          <div v-if="selectedPayment === 'TPE'" class="panel mt">
            <div class="fixed-amount-row">
              <label>Montant à payer</label>
              <span class="value highlight">{{ formatPrice(remainingToPay) }}</span>
            </div>
            <label class="field-label">Numéro de carte (16 chiffres)</label>
            <input
              type="text"
              v-model="cardNumber"
              placeholder="1234 5678 9012 3456"
              maxlength="19"
              class="field-input"
              @focus="activeInput = 'cardNumber'"
            />
            <div v-if="cardNumber.replace(/\D/g, '').length > 0 && !isTpeComplete" class="text-xs font-semibold text-rose-500 mt-1">
              Le numéro de carte doit comporter 16 chiffres.
            </div>
          </div>

          <!-- Section Mobile Money -->
          <div v-if="isMobilePayment" class="panel mt">
            <div class="fixed-amount-row">
              <label>Montant à payer</label>
              <span class="value highlight">{{ formatPrice(remainingToPay) }}</span>
            </div>
            <label class="field-label">Numéro téléphone (10 chiffres)</label>
            <div class="input-wrapper">
              <input
                type="tel"
                v-model="phoneNumber"
                placeholder="03X XX XXX XX"
                class="field-input"
                :class="{
                  'border-red-500': phoneNumber.replace(/\D/g, '').length >= 3 && !phoneNumber.replace(/\D/g, '').startsWith(selectedPayment === 'Airtel Money' ? '033' : selectedPayment === 'MVola' ? '034' : '032'),
                  'border-rose-400': phoneNumber.replace(/\D/g, '').length > 0 && phoneNumber.replace(/\D/g, '').length < 10 && phoneNumber.replace(/\D/g, '').startsWith(selectedPayment === 'Airtel Money' ? '033' : selectedPayment === 'MVola' ? '034' : '032'),
                  'border-emerald-500': isPhoneNumberComplete
                }"
                @focus="activeInput = 'phoneNumber'"
              />
            </div>
          </div>
          <!-- Notes -->
          <div v-if="selectedPayment" class="panel mt">
            <label class="field-label">Notes (optionnel)</label>
            <input
              type="text"
              v-model="paymentNotes"
              placeholder="Notes sur le paiement"
              class="field-input"
            />
          </div>

          <!-- Liste des paiements enregistrés -->
          <div v-if="paymentsList.length" class="panel mt">
            <div class="payments-header">
              <span>💳 Paiements enregistrés</span>
              <span class="badge">{{ paymentsList.length }} transaction(s)</span>
            </div>
            <div class="payments-list">
              <div v-for="(p, idx) in paymentsList" :key="idx" class="payment-item">
                <div class="payment-item-info">
                  <FontAwesomeIcon :icon="getPaymentIcon(p.method)" />
                  <div>
                    <div class="payment-method-name">{{ p.method }}</div>
                    <div v-if="p.reference" class="payment-reference">{{ p.reference }}</div>
                  </div>
                </div>
                <div class="payment-item-right">
                  <span class="payment-amount">{{ formatPrice(p.amount) }}</span>
                  <button type="button" class="btn-icon danger" @click="removePayment(idx)">
                    <FontAwesomeIcon icon="fa-solid fa-trash" />
                  </button>
                </div>
              </div>
            </div>
            <div v-if="discountedTotal > 0" class="progress-bar">
              <div class="progress-fill" :style="{ width: paymentProgress + '%' }"></div>
            </div>
          </div>
        </div>

        <!-- COL 2 : Moyens de paiement -->
        <div class="payment-col col-2">
          <div class="panel">
            <div class="section-title">Moyens de paiement</div>
            <div v-if="loadingPayments" class="loading">
              <FontAwesomeIcon icon="fa-solid fa-spinner" spin /> Chargement...
            </div>
            <div v-else class="methods-grid">
              <button
                v-for="p in paymentsListApi"
                :key="p.id"
                type="button"
                class="method-btn"
                :class="{ active: selectedPayment === p.name }"
                @click="selectPaymentMethod(p.name)"
              >
                <FontAwesomeIcon :icon="getPaymentIcon(p.name)" />
                <span>{{ p.name }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- COL 3 : Clavier numérique -->
        <div class="payment-col col-3">
          <div class="panel">
            <div class="section-title">Clavier numérique</div>
            <NumericKeypad
              :disabled="isKeypadDisabled"
              @press="onKeypadPress"
              @delete="onKeypadDelete"
            />
          </div>

          <button
            type="button"
            class="btn-add-payment"&
            :disabled="!canAddPayment"
            @click="addPayment"
          >
            <FontAwesomeIcon icon="fa-solid fa-plus" />
            {{ addButtonText }}
          </button>
        </div>
      </div>

      <footer class="payment-footer">
        <button type="button" class="btn ghost" @click="closeModal">
          <FontAwesomeIcon icon="fa-solid fa-xmark" /> Annuler
        </button>
        <button
          type="button"
          class="btn primary"
          :disabled="!canConfirmPayment || isProcessing"
          @click="confirmPayment"
        >
          <FontAwesomeIcon
            :icon="isProcessing ? 'fa-solid fa-spinner' : 'fa-solid fa-check'"
            :spin="isProcessing"
          />
          {{ isProcessing ? 'Traitement...' : 'Confirmer le paiement' }}
        </button>
      </footer>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import NumericKeypad from '@/components/NumericKeypad.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faDeleteLeft, faCreditCard, faXmark, faHandHoldingDollar, faMobileScreenButton, faSpinner, faCheck } from '@fortawesome/free-solid-svg-icons'
import { saleService } from '@/services/saleService'
import { storage } from '@/utils/storage'
import apiClient from '@/services/apiClient'
import { useAuth } from '@/composables/useAuth'

library.add(faDeleteLeft, faCreditCard, faXmark, faHandHoldingDollar, faMobileScreenButton, faSpinner, faCheck)

const { activePos } = useAuth()

const props = defineProps({
  saleId: { type: [Number, String], default: null },
  isOpen: { type: Boolean, default: false },
  totalAmount: { type: Number, default: 0 },
  saleData: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close-modal', 'payment-success', 'payment-error'])

const mobilePayments = ['Orange Money', 'MVola', 'Airtel Money', 'Telma']
const discountOptions = [0, 25, 50, 75, 100]

const paymentsListApi = ref([])
const loadingPayments = ref(false)
const selectedPayment = ref('')
const amountReceived = ref('')
const phoneNumber = ref('')
const cardNumber = ref('')
const paymentNotes = ref('')
const selectedDiscount = ref(0)
const paymentsList = ref([])
const activeInput = ref('amountReceived')
const isProcessing = ref(false)

// ... rest of computed properties ...

const groupedItems = computed(() => {
  const map = new Map()
  const items = props.saleData?.items || []
  items.forEach(item => {
    const key = `${item.product_id}-${item.unit_price}`
    if (map.has(key)) {
      map.get(key).quantity += Number(item.quantity)
    } else {
      map.set(key, { ...item, quantity: Number(item.quantity) })
    }
  })
  return Array.from(map.values())
})

const isMobilePayment = computed(() => mobilePayments.includes(selectedPayment.value))

const mobileRegex = {
  'Airtel Money': /^033\d{7}$/,
  'MVola': /^034\d{7}$/,
  'Telma': /^034\d{7}$/,
  'Orange Money': /^032\d{7}$/
}

const isPhoneNumberComplete = computed(() => {
  if (!isMobilePayment.value) return false
  const cleaned = phoneNumber.value.replace(/\D/g, '')
  const regex = mobileRegex[selectedPayment.value]
  return regex ? regex.test(cleaned) : cleaned.length >= 10
})

const isTpeComplete = computed(() => {
  if (selectedPayment.value !== 'TPE') return false
  const cleaned = cardNumber.value.replace(/\D/g, '')
  return /^\d{16}$/.test(cleaned)
})

const amountReceivedValue = computed(() => parseInt(amountReceived.value.replace(/\D/g, '')) || 0)

const isKeypadDisabled = computed(() => {
  return (selectedPayment.value === 'Espèce' && amountReceivedValue.value >= remainingToPay.value) ||
         (isMobilePayment.value && isPhoneNumberComplete.value) ||
         (isMobilePayment.value && phoneNumber.value.replace(/\D/g, '').length >= 3 && !phoneNumber.value.replace(/\D/g, '').startsWith(selectedPayment.value === 'Airtel Money' ? '033' : selectedPayment.value === 'MVola' ? '034' : '032')) ||
         (selectedPayment.value === 'TPE' && isTpeComplete.value)
})

const discountedTotal = computed(() => {
  const discount = (selectedDiscount.value / 100) * props.totalAmount
  return Math.max(0, props.totalAmount - discount)
})

const totalPaymentsAmount = computed(() =>
  paymentsList.value.reduce((sum, p) => sum + Number(p.amount || 0), 0)
)

const remainingToPay = computed(() => Math.max(0, discountedTotal.value - totalPaymentsAmount.value))

const paymentProgress = computed(() =>
  discountedTotal.value === 0 ? 0 : Math.min(100, (totalPaymentsAmount.value / discountedTotal.value) * 100)
)

const canAddPayment = computed(() => {
  // On autorise l'ajout tant qu'il reste à payer
  if (!selectedPayment.value || remainingToPay.value <= 0) return false

  if (selectedPayment.value === 'Espèce') return amountReceivedValue.value > 0
  if (selectedPayment.value === 'TPE') {
    const cleaned = cardNumber.value.replace(/\s/g, '')
    return cleaned.length === 16 && /^\d+$/.test(cleaned)
  }
  if (isMobilePayment.value) {
    const cleaned = phoneNumber.value.replace(/\D/g, '')
    return cleaned.length >= 9 && cleaned.length <= 10
  }
  return true
})

const addButtonText = computed(() => {
  const amount = selectedPayment.value === 'Espèce'
    ? Math.min(amountReceivedValue.value, remainingToPay.value)
    : remainingToPay.value
  return `Ajouter ${formatPrice(amount)}`
})

const canConfirmPayment = computed(() => {
  if (discountedTotal.value === 0) return true;
  return paymentsList.value.length > 0 && remainingToPay.value <= 0;
});
const formatPrice = (p) => `${new Intl.NumberFormat('fr-FR').format(Math.round(Number(p) || 0))} Ar`

const getPaymentIcon = (name) => {
  const icons = {
    'Espèce': 'fa-solid fa-hand-holding-dollar',
    'TPE': 'fa-solid fa-credit-card',
    'Orange Money': 'fa-solid fa-mobile-screen-button',
    'MVola': 'fa-solid fa-mobile-screen-button',
    'Airtel Money': 'fa-solid fa-mobile-screen-button',
  }
  return icons[name] || 'fa-solid fa-credit-card'
}

const fetchPayments = async () => {
  loadingPayments.value = true
  try {
    const res = await apiClient.get('/payments')
    paymentsListApi.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Erreur chargement moyens de paiement:', err)
  } finally {
    loadingPayments.value = false
  }
}

const sanitizeAmount = () => {
  amountReceived.value = amountReceived.value.replace(/[^0-9]/g, '')
}

const onKeypadPress = (digit) => {
  if (selectedPayment.value === 'Espèce') {
    amountReceived.value = (amountReceived.value + digit).replace(/^0+/, '') || '0'
  } else if (selectedPayment.value === 'TPE') {
    const raw = (cardNumber.value.replace(/\s/g, '') + digit).slice(0, 16)
    cardNumber.value = raw.replace(/(\d{4})(?=\d)/g, '$1 ')
  } else if (isMobilePayment.value) {
    phoneNumber.value = (phoneNumber.value.replace(/\D/g, '') + digit).slice(0, 10)
  }
}

const onKeypadDelete = () => {
  if (selectedPayment.value === 'Espèce') {
    amountReceived.value = amountReceived.value.slice(0, -1) || ''
  } else if (selectedPayment.value === 'TPE') {
    const raw = cardNumber.value.replace(/\s/g, '').slice(0, -1)
    cardNumber.value = raw.replace(/(\d{4})(?=\d)/g, '$1 ')
  } else if (isMobilePayment.value) {
    phoneNumber.value = phoneNumber.value.replace(/\D/g, '').slice(0, -1)
  }
}

const selectPaymentMethod = (method) => {
  selectedPayment.value = method
  amountReceived.value = ''
  phoneNumber.value = ''
  cardNumber.value = ''
  paymentNotes.value = ''
  activeInput.value = 'amountReceived'
}

const selectDiscount = (value) => {
  selectedDiscount.value = value
}

const addPayment = () => {
  if (!canAddPayment.value) return

  let amount = 0
  let reference = null

  if (selectedPayment.value === 'Espèce') {
    // Permet d'ajouter une partie du montant total en espèces
    amount = Math.min(amountReceivedValue.value, remainingToPay.value)
    if (amount <= 0) return
  } else {
    // Pour TPE/Mobile, on prend le montant restant à payer
    // Si l'utilisateur saisit un montant spécifique, on pourrait l'ajouter ici
    // Pour l'instant, on prend le solde restant
    amount = remainingToPay.value
  }

  const found = paymentsListApi.value.find((p) => p.name === selectedPayment.value)
  if (!found) {
    alert('Erreur : moyen de paiement non reconnu')
    return
  }

  paymentsList.value.push({
    payment_id: found.id,
    method: selectedPayment.value,
    amount,
    reference,
    notes: paymentNotes.value || null,
  })

  // Réinitialisation des champs pour le prochain paiement
  amountReceived.value = ''
  phoneNumber.value = ''
  cardNumber.value = ''
  paymentNotes.value = ''
}

const removePayment = (idx) => {
  paymentsList.value.splice(idx, 1)
}


const confirmPayment = async () => {
  if (!canConfirmPayment.value || isProcessing.value) return
  isProcessing.value = true

  try {
    const auth = storage.getAuth()
    const session = storage.getSession()

    const items = (props.saleData?.items || []).map((item) => {
      const unitPrice = item.unit_price ?? item.price ?? 0
      return {
        product_id: item.product_id || item.id,
        quantity: Number(item.quantity) || 0,
        unit_price: Number(unitPrice),
        price: Number(unitPrice),
        total: Number(unitPrice) * (Number(item.quantity) || 0),
      }
    })

    const payload = {
      point_of_sale_id: props.saleData?.point_of_sale_id || activePos.value?.id,
      cash_register_session_id: session?.id || null,
      table_id: props.saleData?.table_id || null,
      // Utiliser l'original_user_id (le caissier) s'il existe, sinon l'utilisateur authentifié
      user_id: session?.original_user_id || auth.user?.id,
      total_amount: props.totalAmount,
      discount_percentage: selectedDiscount.value,
      final_amount: discountedTotal.value,
      status: 'completed',
      items,
      payments: paymentsList.value.map((p) => ({
        payment_id: p.payment_id,
        amount: Number(p.amount),
        reference: p.reference || null,
        notes: p.notes || null,
      })),
    }

    const existingSaleId = props.saleId || props.saleData?.id
    let response

    if (existingSaleId) {
      const validatePayload = {
        payment_id: paymentsList.value[0]?.payment_id,
        discount_percentage: selectedDiscount.value,
        amount_received: totalPaymentsAmount.value,
        change_amount: Math.max(0, totalPaymentsAmount.value - discountedTotal.value),
        payments: payload.payments
      }
      response = await saleService.validatePending(existingSaleId, validatePayload)
    } else {
      response = await saleService.create(payload)
    }

    emit('payment-success', response.data.data)
    closeModal()
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Erreur lors de la vente'
    emit('payment-error', message)
    alert(message)
  } finally {
    isProcessing.value = false
  }
}

const closeModal = () => {
  paymentsList.value = []
  selectedPayment.value = ''
  selectedDiscount.value = 0
  amountReceived.value = ''
  phoneNumber.value = ''
  cardNumber.value = ''
  paymentNotes.value = ''
  isProcessing.value = false
  emit('close-modal')
}

onMounted(() => {
  fetchPayments()
  console.log('🟣 PaymentModal mounted - saleId =', props.saleId)
})

watch(
  () => props.saleId,
  (newVal) => {
    console.log('🟣 PaymentModal watch saleId :', newVal)
  },
  { immediate: true }
)
</script>

<style scoped>
/* ── Overlay ── */
.payment-overlay {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(8px);
  z-index: 1100;
}

/* ── Card ── */
.payment-card {
  width: 100%;
  max-width: 1200px;
  max-height: 92vh;
  background: #fff;
  border-radius: 1.5rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
}

/* ── Header ── */
.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background: linear-gradient(135deg, #1e1b4b, #312e81);
  color: #fff;
  flex-shrink: 0;
}

.payment-header .title {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  font-size: 1.05rem;
  font-weight: 600;
}

/* ── Body ── */
.payment-body {
  display: flex;
  gap: 1rem;
  padding: 1.25rem;
  flex: 1;
  overflow-y: auto;
  background: #f8fafc;
  min-height: 0;
}

.payment-col {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  overflow-y: auto;
}

.col-0 {
  width: 260px;
  flex-shrink: 0;
}
.col-1 {
  flex: 1.2;
  min-width: 0;
}

/* ── Item rows ── */
.item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  background: #f8fafc;
  border-radius: 0.75rem;
  border: 1px solid #f1f5f9;
}

.item-info {
  display: flex;
  flex-direction: column;
}

.item-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: #334155;
  line-height: 1.2;
}

.item-qty {
  font-size: 0.7rem;
  font-weight: 500;
  color: #64748b;
}

.item-price {
  font-size: 0.8rem;
  font-weight: 800;
  color: #4f46e5;
}

.panel {
  background: #fff;
  border-radius: 1rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
}

.panel.mt {
  margin-top: 0;
}

/* ── Solde rows ── */
.solde-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.45rem 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.875rem;
}
.solde-row:last-child {
  border-bottom: none;
}

.solde-row label {
  color: #64748b;
}
.value {
  font-weight: 600;
  color: #0f172a;
}
.value.highlight {
  color: #4f46e5;
}
.value.success {
  color: #059669;
}
.value.warning {
  color: #d97706;
}

/* ── Chip remise ── */
.chip-group {
  display: flex;
  gap: 0.375rem;
}

.chip {
  border-radius: 9999px;
  padding: 0.2rem 0.6rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}

.chip.active {
  background: #4f46e5;
  color: #fff;
  border-color: #4f46e5;
}

/* ── Cash info ── */
.cash-info {
  margin-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.82rem;
  padding: 0.3rem 0.5rem;
  border-radius: 0.5rem;
  background: #f8fafc;
}

.info-row.success {
  background: #ecfdf5;
  color: #065f46;
}
.info-row.warning {
  background: #fffbeb;
  color: #92400e;
}
.info-row.change {
  background: #eef2ff;
}
.change-amount {
  color: #4f46e5;
}

/* ── Fixed amount ── */
.fixed-amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  font-size: 0.875rem;
}

/* ── Fields ── */
.field-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 500;
  color: #64748b;
  margin-bottom: 0.35rem;
}

.field-input {
  width: 100%;
  padding: 0.55rem 0.75rem;
  border-radius: 0.625rem;
  border: 1px solid #e2e8f0;
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.field-input:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.input-wrapper {
  position: relative;
}

.input-wrapper .field-input {
  padding-right: 2.5rem;
}

.input-suffix {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.78rem;
  color: #94a3b8;
  pointer-events: none;
}

/* ── Payments list ── */
.payments-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
  font-size: 0.82rem;
  font-weight: 600;
}

.badge {
  background: #eef2ff;
  color: #4f46e5;
  border-radius: 9999px;
  padding: 0.15rem 0.5rem;
  font-size: 0.75rem;
}

.payments-list {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.payment-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  border-radius: 0.625rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.payment-item-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.82rem;
}

.payment-method-name {
  font-weight: 600;
  color: #1e293b;
}
.payment-reference {
  color: #64748b;
  font-size: 0.75rem;
}

.payment-item-right {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.payment-amount {
  font-weight: 600;
  color: #4f46e5;
  font-size: 0.875rem;
}

/* ── Progress bar ── */
.progress-bar {
  margin-top: 0.625rem;
  height: 6px;
  background: #e2e8f0;
  border-radius: 9999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #4f46e5);
  border-radius: 9999px;
  transition: width 0.3s ease;
}

/* ── Section title ── */
.section-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
}

/* ── Methods grid ── */
.methods-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
}

.method-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  padding: 0.75rem 0.5rem;
  border-radius: 0.75rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  color: #475569;
}

.method-btn:hover {
  border-color: #a5b4fc;
  background: #eef2ff;
  color: #4f46e5;
}

.method-btn.active {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #fff;
}

/* ── Loading ── */
.loading {
  padding: 1rem;
  text-align: center;
  color: #94a3b8;
  font-size: 0.875rem;
}

/* ── Add payment button ── */
.btn-add-payment {
  width: 100%;
  margin-top: 0.75rem;
  padding: 0.75rem;
  background: #4f46e5;
  color: #fff;
  border-radius: 0.875rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border: none;
}

.btn-add-payment:hover:not(:disabled) {
  background: #4338ca;
}
.btn-add-payment:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* ── Footer ── */
.payment-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  background: #fff;
  flex-shrink: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  border-radius: 0.75rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  border: none;
}

.btn.ghost {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}
.btn.ghost:hover {
  background: #e2e8f0;
}

.btn.primary {
  background: #4f46e5;
  color: #fff;
}
.btn.primary:hover:not(:disabled) {
  background: #4338ca;
}
.btn.primary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* ── Icon buttons ── */
.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
  color: #64748b;
}

.btn-icon.ghost:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
}
.btn-icon.danger:hover {
  background: #fff1f2;
  color: #e11d48;
}
</style>
