// composables/usePaymentValidation.js
import { computed } from 'vue'

export function usePaymentValidation(state, getPaymentId) {
  const canAddPayment = computed(() => {
    const { selectedPayment, remainingToPay, isInstallmentActive, customAmountValue,
            amountReceivedValue, cardNumber, isValidPhone } = state
    
    if (!selectedPayment.value || remainingToPay.value === 0) return false
    
    if (isInstallmentActive.value) {
      const amount = customAmountValue.value
      if (amount <= 0 || amount > remainingToPay.value) return false
      if (selectedPayment.value === 'Espèce') return amountReceivedValue.value >= amount
      if (selectedPayment.value === 'TPE') return cardNumber.value.replace(/\D/g, '').length === 16
      if (state.isMobilePayment.value) return isValidPhone.value
      return true
    }
    
    if (selectedPayment.value === 'TPE') return cardNumber.value.replace(/\D/g, '').length === 16
    if (state.isMobilePayment.value) return isValidPhone.value
    if (selectedPayment.value === 'Espèce') return amountReceivedValue.value >= remainingToPay.value
    return true
  })
  
  const canConfirmPayment = computed(() => {
    return state.paymentsList.value.length > 0 && 
           state.remainingToPay.value === 0 && 
           !state.isProcessing.value
  })
  
  const addButtonText = computed(() => {
    const { selectedPayment, isInstallmentActive, customAmountValue, 
            remainingToPay, amountReceivedValue, formatPrice } = state
    
    if (!selectedPayment.value) return 'Ajouter'
    const amount = isInstallmentActive.value ? customAmountValue.value : remainingToPay.value
    if (selectedPayment.value === 'Espèce' && amountReceivedValue.value > 0) {
      return `Ajouter ${formatPrice(Math.min(amountReceivedValue.value, amount))}`
    }
    return `Ajouter ${formatPrice(amount)}`
  })
  
  return {
    canAddPayment,
    canConfirmPayment,
    addButtonText
  }
}