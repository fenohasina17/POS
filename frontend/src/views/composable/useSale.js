// composables/useSale.js
import { ref } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

export function useSale() {
  const token = localStorage.getItem('token')
  const isCreatingSale = ref(false)
  const localSaleId = ref(null)
  
  const createSale = async (saleData, firstPayment, selectedDiscount, discountedTotal) => {
    if (isCreatingSale.value) return
    isCreatingSale.value = true
    
    try {
      const user = JSON.parse(localStorage.getItem('user') || '{}')
      const session = JSON.parse(localStorage.getItem('cash_register_session') || 'null')
      
      if (!firstPayment) {
        throw new Error('Aucun paiement à enregistrer')
      }
      
      const payload = {
        items: saleData.items,
        total_amount: saleData.total_amount,
        discount_percentage: selectedDiscount,
        final_amount: discountedTotal,
        customer_id: saleData.customer_id || null,
        point_of_sale_id: saleData.point_of_sale_id,
        user_id: user.id,
        cash_register_session_id: session?.id,
        status: 'pending',
        payment_id: firstPayment.payment_id,
        amount_received: firstPayment.amount
      }
      
      const response = await axios.post(`${API_BASE_URL}/sales`, payload, {
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' }
      })
      
      const id = response.data.data?.sale?.id || response.data.sale?.id || response.data.id
      localSaleId.value = id
      
      return response.data.data?.sale || response.data.sale || response.data
    } finally {
      isCreatingSale.value = false
    }
  }
  
  const confirmPayment = async (saleId, paymentsList, changeAmount) => {
    const validPayments = paymentsList.filter(p => p.amount > 0)
    if (validPayments.length === 0) {
      throw new Error('Aucun paiement valide')
    }
    
    const payload = {
      payments: validPayments.map(p => ({
        payment_id: p.payment_id,
        amount: p.amount,
        reference: p.reference || null,
        notes: p.notes || null
      })),
      change_amount: changeAmount || 0
    }
    
    const response = await axios.post(`${API_BASE_URL}/sales/${saleId}/payments`, payload, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      }
    })
    
    return response.data
  }
  
  return {
    isCreatingSale,
    localSaleId,
    createSale,
    confirmPayment
  }
}