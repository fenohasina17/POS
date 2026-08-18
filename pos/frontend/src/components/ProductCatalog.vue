<template>
  <div class="product-catalog h-full flex flex-col">
    <!-- Search and Filters -->
    <div class="p-4 border-b border-slate-100">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Rechercher un produit..."
        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-indigo-500 focus:outline-none"
      />
    </div>

    <!-- Product Grid -->
    <div class="flex-grow overflow-y-auto p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <button
        v-for="product in filteredProducts"
        :key="product.id"
        @click="$emit('add', product)"
        class="flex flex-col items-center p-4 rounded-2xl border border-slate-200 bg-white hover:border-indigo-500 hover:shadow-md transition-all"
      >
        <div class="w-16 h-16 mb-3 rounded-full bg-slate-100 flex items-center justify-center">
            <img v-if="product.image" :src="product.image" class="w-full h-full object-cover rounded-full" />
            <i v-else class="fas fa-pizza-slice text-slate-400"></i>
        </div>
        <p class="text-sm font-semibold text-slate-900 text-center truncate w-full">{{ product.name }}</p>
        <p class="text-xs text-indigo-600 font-bold mt-1">{{ product.price }} Ar</p>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  products: { type: Array, required: true },
  activeCategoryId: { type: [Number, null], default: null }
})

defineEmits(['add'])

const searchQuery = ref('')

const filteredProducts = computed(() => {
  let list = props.products
  
  if (props.activeCategoryId !== null) {
    list = list.filter(p => p.category_id === props.activeCategoryId)
  }
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    list = list.filter(p => p.name.toLowerCase().includes(query))
  }
  
  return list
})
</script>
