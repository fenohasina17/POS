<template>
  <div class="p-4 md:p-6 bg-slate-50 min-h-screen">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-100 shadow-sm">
      <div>
        <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-chart-pie" class="text-indigo-600" />
          Monitoring Global
        </h1>
      </div>
      <button
        @click="fetchData"
        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-100 shadow-sm"
        :disabled="loading"
      >
        <font-awesome-icon icon="fa-solid fa-rotate" :class="{ 'animate-spin': loading }" />
        Actualiser
      </button>
    </header>

    <div v-if="loading" class="text-center py-20 text-slate-400">Chargement...</div>

    <!-- Grille adaptative : affiche plusieurs POS par ligne -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div v-for="section in monitoringData" :key="section.pos_name || section.label" 
           class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col gap-3">
        
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

        <!-- Paiements -->
        <div class="space-y-1">
          <p class="text-[8px] font-black uppercase text-slate-400">Paiements</p>
          <div class="grid grid-cols-2 gap-1">
            <div v-for="pay in section.data.payment_summary" :key="pay.method" class="flex justify-between bg-slate-50 px-2 py-1 rounded-lg">
                <span class="font-bold text-slate-500 text-[9px]">{{ pay.method }}</span>
                <span class="font-black text-indigo-600 text-[9px]">{{ formatPrice(pay.total) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import apiClient from '@/services/apiClient'
import echo from '@/services/echo' // Import de Echo
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faRotate, faChartPie } from '@fortawesome/free-solid-svg-icons'

library.add(faRotate, faChartPie)

const loading = ref(false)
const monitoringData = ref([])

const formatPrice = (price) => new Intl.NumberFormat('fr-FR').format(price) + ' Ar'

const fetchData = async () => {
  try {
    const { data } = await apiClient.get('/admin/monitoring')
    // Le backend renvoie soit un tableau de POS (vue globale), soit un objet avec les données (vue filtrée)
    if (Array.isArray(data)) {
        monitoringData.value = data
    } else {
        monitoringData.value = [{ label: data.label, data: data }]
    }
  } catch (err) { console.error(err) }
}

const initData = async () => {
  loading.value = true
  await fetchData()
  loading.value = false
}

onMounted(() => {
  initData()

  // Écoute des événements de vente en temps réel
  window.Echo.channel('sales')
    .listen('SaleCreated', (e) => {
        console.log('📡 Nouvelle vente reçue en temps réel:', e);
        fetchData();
    })
    .listen('SaleUpdated', (e) => {
        console.log('📡 Vente mise à jour en temps réel:', e);
        fetchData();
    });
})

onBeforeUnmount(() => {
  window.Echo.leaveChannel('sales')
})
</script>
