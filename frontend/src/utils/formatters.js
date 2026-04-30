// utils/formatters.js

export const formatPrice = (price) => {
  const amount = Math.round(Number(price) || 0)
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(amount)} Ar`
}

export const formatCardNumber = (value) => {
  const cleaned = value.replace(/\D/g, '')
  if (cleaned.length <= 16) {
    return cleaned.match(/.{1,4}/g)?.join(' ').slice(0, 19) || ''
  }
  return value
}

export const formatPhoneNumber = (value) => {
  const cleaned = value.replace(/\D/g, '')
  if (cleaned.length <= 10) {
    return cleaned.replace(/(\d{3})(\d{2})(\d{3})(\d{2})/, '$1 $2 $3 $4').slice(0, 14)
  }
  return value
}

export const getPaymentIcon = (name) => {
  const icons = {
    'Espèce': 'fa-solid fa-hand-holding-dollar',
    'TPE': 'fa-solid fa-credit-card',
    'Orange Money': 'fa-solid fa-mobile-screen',
    'MVola': 'fa-solid fa-mobile-screen',
    'Airtel Money': 'fa-solid fa-mobile-screen'
  }
  return icons[name] || 'fa-solid fa-credit-card'
}

export const calculateChange = (amountReceived, amountToPay) => {
  return Math.max(0, amountReceived - amountToPay)
}