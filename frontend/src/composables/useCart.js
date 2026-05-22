import { ref, computed } from 'vue'

export function useCart() {
  const cart = ref([])

  const totalPrice = computed(() => {
    return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0)
  })

  const addToCart = (product) => {
    const existingItem = cart.value.find(item => item.id === product.id)
    if (existingItem) {
      existingItem.quantity++
    } else {
      cart.value.push({ ...product, quantity: 1 })
    }
  }

  const removeFromCart = (productId) => {
    cart.value = cart.value.filter(item => item.id !== productId)
  }

  const clearCart = () => {
    cart.value = []
  }

  return {
    cart,
    totalPrice,
    addToCart,
    removeFromCart,
    clearCart
  }
}
