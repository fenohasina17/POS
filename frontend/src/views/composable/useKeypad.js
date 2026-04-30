// composables/useKeypad.js
import { ref, watch, nextTick } from 'vue'

export function useKeypad() {
  const amountReceivedInput = ref(null)
  const cardNumberInput = ref(null)
  const phoneInput = ref(null)
  const customAmountInput = ref(null)
  const activeInput = ref('customAmount')
  
  // Watchers pour détecter le focus
  const setupFocusWatchers = (customAmountRef, amountReceivedRef, cardNumberRef, phoneRef) => {
    watch(customAmountRef, (input) => {
      if (input) input.addEventListener('focus', () => { activeInput.value = 'customAmount' })
    })
    watch(amountReceivedRef, (input) => {
      if (input) input.addEventListener('focus', () => { activeInput.value = 'amountReceived' })
    })
    watch(cardNumberRef, (input) => {
      if (input) input.addEventListener('focus', () => { activeInput.value = 'cardNumber' })
    })
    watch(phoneRef, (input) => {
      if (input) input.addEventListener('focus', () => { activeInput.value = 'phoneNumber' })
    })
  }
  
  const setFocus = (field, element) => {
    activeInput.value = field
    nextTick(() => element?.value?.focus())
  }
  
  const handleKeypadPress = (digit, state, remainingToPay) => {
    const { activeInput: currentActive, customAmount, amountReceived, cardNumber, phoneNumber } = state
    
    // Vérifications limites
    if (currentActive === 'cardNumber') {
      const currentLength = cardNumber.value.replace(/\D/g, '').length
      if (currentLength >= 16) return
    }
    if (currentActive === 'phoneNumber') {
      const currentLength = phoneNumber.value.replace(/\D/g, '').length
      if (currentLength >= 10) return
    }
    if (currentActive === 'amountReceived') {
      if (amountReceived.value >= remainingToPay && remainingToPay > 0) return
    }
    if (currentActive === 'customAmount') {
      if (customAmount.value >= remainingToPay && remainingToPay > 0) return
    }
    
    let newValue, currentValue, parsedValue
    
    switch (currentActive) {
      case 'customAmount':
        newValue = (customAmount.value === '0' ? '' : customAmount.value) + digit
        parsedValue = parseInt(newValue, 10) || 0
        if (parsedValue <= remainingToPay) {
          customAmount.value = newValue.replace(/[^0-9]/g, '')
        }
        break
      case 'amountReceived':
        newValue = (amountReceived.value === '0' ? '' : amountReceived.value) + digit
        amountReceived.value = newValue.replace(/[^0-9]/g, '')
        break
      case 'cardNumber':
        currentValue = cardNumber.value.replace(/\D/g, '')
        if (currentValue.length < 16) {
          const newCard = currentValue + digit
          cardNumber.value = newCard.match(/.{1,4}/g)?.join(' ').slice(0, 19) || ''
        }
        break
      case 'phoneNumber':
        currentValue = phoneNumber.value.replace(/\D/g, '')
        if (currentValue.length < 10) {
          const newPhone = currentValue + digit
          phoneNumber.value = newPhone.replace(/(\d{3})(\d{2})(\d{3})(\d{2})/, '$1 $2 $3 $4').slice(0, 14)
        }
        break
    }
  }
  
  const handleKeypadDelete = (state) => {
    const { activeInput: currentActive, customAmount, amountReceived, cardNumber, phoneNumber } = state
    let currentCard
    
    switch (currentActive) {
      case 'customAmount':
        customAmount.value = customAmount.value.slice(0, -1)
        break
      case 'amountReceived':
        amountReceived.value = amountReceived.value.slice(0, -1) || '0'
        break
      case 'cardNumber':
        currentCard = cardNumber.value.replace(/\D/g, '').slice(0, -1)
        cardNumber.value = currentCard.match(/.{1,4}/g)?.join(' ') || ''
        break
      case 'phoneNumber':
        phoneNumber.value = phoneNumber.value.slice(0, -1).trim()
        break
    }
  }
  
  return {
    amountReceivedInput, cardNumberInput, phoneInput, customAmountInput,
    activeInput, setupFocusWatchers, setFocus, handleKeypadPress, handleKeypadDelete
  }
}