<template>
  <div class="p-4 md:p-6">
    <section class="mx-auto w-full max-w-6xl space-y-6 rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-lg backdrop-blur-sm">
      <header class="border-b border-slate-100 pb-4">
        <h1 class="text-3xl font-bold text-slate-900">Exporter les ventes</h1>
      </header>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- POS -->
        <label class="flex flex-col">
          <span class="text-xs font-semibold text-slate-400 mb-1 uppercase">Point de vente</span>
          <select v-model="filters.pointOfSaleId" class="rounded-xl border p-2 text-sm border-slate-200">
            <option value="">Tous</option>
            <option v-for="pos in pointOfSales" :key="pos.id" :value="String(pos.id)">{{ pos.name }}</option>
          </select>
        </label>

        <!-- Produit -->
        <label class="flex flex-col">
          <span class="text-xs font-semibold text-slate-400 mb-1 uppercase">Produit</span>
          <select v-model="filters.productId" class="rounded-xl border p-2 text-sm border-slate-200">
            <option value="">Tous les produits</option>
            <option v-for="prod in products" :key="prod.id" :value="String(prod.id)">{{ prod.name }}</option>
          </select>
        </label>

        <!-- Période -->
        <div class="col-span-1 md:col-span-2 grid grid-cols-2 gap-4">
            <label class="flex flex-col">
                <span class="text-xs font-semibold text-slate-400 mb-1 uppercase">Type de période</span>
                <select v-model="periodType" class="rounded-xl border p-2 text-sm border-slate-200">
                    <option value="day">Jour</option>
                    <option value="week">Semaine</option>
                    <option value="month">Mois</option>
                    <option value="year">Année</option>
                    <option value="range">Plage de dates</option>
                </select>
            </label>
            <label class="flex flex-col">
                <span class="text-xs font-semibold text-slate-400 mb-1 uppercase">Valeur/Dates</span>
                <template v-if="periodType === 'range'">
                    <div class="flex gap-2">
                        <input type="date" v-model="filters.startDate" class="w-1/2 rounded-xl border p-2 text-sm border-slate-200" />
                        <input type="date" v-model="filters.endDate" class="w-1/2 rounded-xl border p-2 text-sm border-slate-200" />
                    </div>
                </template>
                <template v-else>
                    <input :type="inputType" v-model="periodValue" class="rounded-xl border p-2 text-sm border-slate-200" />
                </template>
            </label>
        </div>
      </div>

      <div v-if="errorMessage" class="text-red-500 text-sm">{{ errorMessage }}</div>

      <div class="flex justify-center pt-6">
        <button @click="exportSales" :disabled="isExporting" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
          {{ isExporting ? 'Traitement...' : 'Exporter en CSV' }}
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { useAuth } from '@/composables/useAuth'

const { isAdmin, loadUserData } = useAuth()
const pointOfSales = ref([])
const products = ref([])
const isExporting = ref(false)
const errorMessage = ref('')

const filters = ref({ pointOfSaleId: '', productId: '', startDate: '', endDate: '' })
const periodType = ref('day')
const periodValue = ref('')

const inputType = computed(() => {
    switch (periodType.value) {
        case 'week': return 'week';
        case 'month': return 'month';
        case 'year': return 'number';
        default: return 'date';
    }
})

const fetchData = async () => {
  try {
    const token = localStorage.getItem('token')
    const [posRes, prodRes] = await Promise.all([
      axios.get(`${API_BASE_URL}/point-of-sales`, { headers: { Authorization: `Bearer ${token}` } }),
      axios.get(`${API_BASE_URL}/products`, { headers: { Authorization: `Bearer ${token}` } })
    ])
    pointOfSales.value = posRes.data?.data || posRes.data
    products.value = prodRes.data?.data || prodRes.data
  } catch (err) { errorMessage.value = "Erreur de chargement." }
}

const exportSales = async () => {
  isExporting.value = true
  errorMessage.value = ''

  try {
    const token = localStorage.getItem('token')
    const params = {
        pointOfSaleId: filters.value.pointOfSaleId,
        productId: filters.value.productId
    }

    // Ajout temporel
    if (periodType.value === 'range') {
        params.startDate = filters.value.startDate
        params.endDate = filters.value.endDate
    } else if (periodValue.value) {
        params[periodType.value] = periodValue.value
    }

    const response = await apiClient.get('/sales/export', {
      params,
      responseType: 'blob'
    })

    const link = document.createElement('a')
    link.href = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }))
    link.setAttribute('download', `export_${new Date().toISOString().slice(0, 10)}.csv`)
    link.click()
  } catch (error) {
    errorMessage.value = "Erreur lors de l'export."
  } finally {
    isExporting.value = false
  }
}

onMounted(async () => {
  await loadUserData()
  await fetchData()
})
</script>
