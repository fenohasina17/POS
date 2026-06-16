<template>
  <div class="p-6 md:p-10 bg-slate-50 min-h-screen">
    <header class="mb-8 flex flex-wrap items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
      <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
          <font-awesome-icon icon="fa-solid fa-chart-pie" class="text-indigo-600" />
          Monitoring des Ventes
        </h1>
        <p class="text-slate-500 font-medium text-sm mt-1">Vue globale de la performance du réseau.</p>
      </div>
      <div class="flex gap-3">
        <select v-model="posId" @change="fetchData" class="rounded-2xl border-none bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
          <option :value="null">Tous les sites</option>
          <option v-for="pos in pointsOfSale" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
        </select>
        <button
          @click="fetchData"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100 shadow-sm"
        >
          <font-awesome-icon icon="fa-solid fa-rotate" />
        </button>
      </div>
    </header>

    <div v-if="loading" class="text-center py-20 text-slate-400">Chargement...</div>

    <div v-else class="grid gap-6">
      <!-- KPIs -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
          <p class="text-[10px] font-black uppercase text-slate-400">Chiffre d'affaires total</p>
          <p class="text-3xl font-black text-indigo-600 mt-2">{{ formatPrice(kpis.total_revenue) }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
          <p class="text-[10px] font-black uppercase text-slate-400">Nombre de ventes</p>
          <p class="text-3xl font-black text-slate-800 mt-2">{{ kpis.total_sales }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
          <p class="text-[10px] font-black uppercase text-slate-400">Ticket moyen</p>
          <p class="text-3xl font-black text-emerald-600 mt-2">{{ formatPrice(kpis.average_ticket) }}</p>
        </div>
      </div>

      <!-- Payment & Products -->
      <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
          <h3 class="text-sm font-black text-slate-800 uppercase mb-4">Paiements</h3>
          <div class="space-y-3">
            <div v-for="pay in paymentSummary" :key="pay.method" class="flex justify-between p-3 bg-slate-50 rounded-xl">
              <span class="font-bold text-slate-600">{{ pay.method }}</span>
              <span class="font-black text-indigo-600">{{ formatPrice(pay.total) }}</span>
            </div>
          </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
          <h3 class="text-sm font-black text-slate-800 uppercase mb-4">Top Produits</h3>
          <div class="space-y-3">
            <div v-for="prod in topProducts" :key="prod.name" class="flex justify-between p-3 bg-slate-50 rounded-xl">
              <span class="font-bold text-slate-600">{{ prod.name }} (x{{ prod.total_qty }})</span>
              <span class="font-black text-indigo-600">{{ formatPrice(prod.total_revenue) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import apiClient from '@/services/apiClient'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faHistory, faRotate, faChartPie } from '@fortawesome/free-solid-svg-icons'

library.add(faHistory, faRotate, faChartPie)

const loading = ref(false)
const posId = ref(null)
const pointsOfSale = ref([])
const kpis = ref({ total_revenue: 0, total_sales: 0, average_ticket: 0 })
const paymentSummary = ref([])
const topProducts = ref([])

const formatPrice = (price) => new Intl.NumberFormat('fr-FR').format(price) + ' Ar'

const fetchPointsOfSale = async () => {
  try {
    const { data } = await apiClient.get('/point-of-sales')
    pointsOfSale.value = data?.data || data || []
  } catch (err) { console.error(err) }
}

const fetchData = async () => {
  loading.value = true
  try {
    const params = {}
    if (posId.value) params.pos_id = posId.value
    const { data } = await apiClient.get('/admin/monitoring', { params })
    kpis.value = data.kpis
    paymentSummary.value = data.payment_summary
    topProducts.value = data.top_products
  } catch (err) { console.error(err) } finally { loading.value = false }
}

onMounted(() => {
  fetchPointsOfSale()
  fetchData()
})
</script>
