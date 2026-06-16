<template>
  <div class="p-4 md:p-6 bg-slate-50 min-h-screen">
    <header class="mb-6 bg-white p-5 rounded-3xl border border-slate-100 shadow-sm space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-chart-pie" class="text-indigo-600" />
          Monitoring Global
        </h1>
        <button
          @click="fetchData"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-100 shadow-sm"
          :disabled="loading"
        >
          <font-awesome-icon icon="fa-solid fa-rotate" :class="{ 'animate-spin': loading }" />
          Actualiser
        </button>
      </div>

      <!-- Filtres -->
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="filters.pos_id" @change="fetchData" class="rounded-2xl border-none bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 outline-none">
          <option :value="null">Tous les sites</option>
          <option v-for="pos in pointsOfSale" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
        </select>
        <select v-model="filters.status" @change="fetchData" class="rounded-2xl border-none bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 outline-none">
          <option value="">Tous les statuts</option>
          <option value="open">Ouvertes</option>
          <option value="closed">Fermées</option>
        </select>
        <input type="date" v-model="filters.start_date" @change="fetchData" class="rounded-2xl border-none bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 outline-none" />
        <input type="date" v-model="filters.end_date" @change="fetchData" class="rounded-2xl border-none bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 outline-none" />
      </div>
    </header>

    <div v-if="loading" class="text-center py-20 text-slate-400">Chargement...</div>

    <!-- Grille adaptative -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="section in monitoringData" :key="section.pos_name || section.label" 
           @click="openDetails(section)"
           class="cursor-pointer bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col gap-3 hover:border-indigo-200 transition">
        
        <h2 class="text-xs font-black text-slate-800 border-b border-slate-50 pb-2 truncate">{{ section.pos_name || section.label }}</h2>
        
        <!-- KPIs compacts -->
        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="bg-slate-50 p-2 rounded-xl">
            <p class="text-[8px] font-black uppercase text-slate-400">CA</p>
            <p class="text-xs font-black text-indigo-600 truncate">{{ formatPrice(section.data.kpis.total_revenue) }}</p>
          </div>
          <div class="bg-slate-50 p-2 rounded-xl">
            <p class="text-[8px] font-black uppercase text-slate-400">Ventes</p>
            <p class="text-xs font-black text-slate-800">{{ section.data.kpis.total_sales }}</p>
          </div>
          <div class="bg-slate-50 p-2 rounded-xl">
            <p class="text-[8px] font-black uppercase text-slate-400">Ticket</p>
            <p class="text-xs font-black text-emerald-600 truncate">{{ formatPrice(section.data.kpis.average_ticket) }}</p>
          </div>
        </div>
      </div>
    </div>
    
    <MonitoringDetailModal 
        v-if="selectedSection"
        :is-open="showModal"
        :pos-name="selectedSection.pos_name || selectedSection.label"
        :top-products="selectedSection.data.top_products"
        :payment-summary="selectedSection.data.payment_summary"
        :evolution-data="selectedSection.data.sales_evolution"
        @close="showModal = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, reactive } from 'vue'
import apiClient from '@/services/apiClient'
import echo from '@/services/echo'
import MonitoringDetailModal from '@/components/MonitoringDetailModal.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faRotate, faChartPie } from '@fortawesome/free-solid-svg-icons'

library.add(faRotate, faChartPie)

const loading = ref(false)
const monitoringData = ref([])
const pointsOfSale = ref([])
const showModal = ref(false)
const selectedSection = ref(null)

const filters = reactive({
  pos_id: null,
  status: '',
  start_date: '',
  end_date: ''
})

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
    const params = { ...filters }
    const { data } = await apiClient.get('/admin/monitoring', { params })
    if (Array.isArray(data)) {
        monitoringData.value = data
    } else {
        monitoringData.value = [{ label: data.label, data: data }]
    }
  } catch (err) { console.error(err) } finally { loading.value = false }
}

const openDetails = (section) => {
    selectedSection.value = section
    showModal.value = true
}

onMounted(() => {
  fetchPointsOfSale()
  fetchData()

  // Écoute des événements de vente en temps réel
  window.Echo.channel('sales')
    .listen('SaleCreated', fetchData)
    .listen('SaleUpdated', fetchData);
})

onBeforeUnmount(() => {
  window.Echo.leaveChannel('sales')
})
</script>
