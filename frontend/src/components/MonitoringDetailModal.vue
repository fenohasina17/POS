<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="$emit('close')"></div>
    <div class="relative w-full max-w-6xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
        <div>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">{{ posName }} - Intelligence Commerciale</h2>
            <p class="text-xs text-slate-500 font-medium mt-1">Tableau de bord Marketing & Ventes</p>
        </div>
        <button @click="$emit('close')" class="p-2 hover:bg-slate-200 rounded-xl transition text-slate-500">
          <font-awesome-icon icon="fa-solid fa-xmark" />
        </button>
      </div>

      <!-- Scrollable content -->
      <div class="p-6 overflow-y-auto space-y-8 bg-slate-50/50">
        
        <!-- KPI Row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Chiffre d'Affaires</p>
                <p class="text-lg font-black text-indigo-600">{{ formatPrice(kpis?.total_revenue || 0) }}</p>
            </div>
            <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Panier Moyen</p>
                <p class="text-lg font-black text-slate-700">{{ formatPrice(kpis?.average_ticket || 0) }}</p>
            </div>
            <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">Total Ventes</p>
                <p class="text-lg font-black text-slate-700">{{ kpis?.total_sales || 0 }}</p>
            </div>
            <div class="bg-red-50 border border-red-100 rounded-2xl p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase text-red-400 mb-1">Remises & Cadeaux offerts</p>
                <p class="text-lg font-black text-red-600">{{ formatPrice(kpis?.total_discounts || 0) }}</p>
            </div>
        </div>

        <!-- Graphique -->
        <div class="h-64 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
          <h3 class="font-black text-xs uppercase text-slate-400 mb-3 tracking-widest">Affluence & Tendance</h3>
          <div class="h-48">
            <Line :data="chartData" :options="chartOptions" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          
          <!-- Mix Produit (Tops / Flops) -->
          <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm col-span-1 md:col-span-2">
            <h3 class="font-black text-xs uppercase text-slate-400 mb-4 tracking-widest">Analyse du Mix Produit</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Tops -->
                <div>
                    <h4 class="text-xs font-bold text-emerald-600 mb-3 flex items-center gap-2">⭐ Best-Sellers (À promouvoir)</h4>
                    <div class="space-y-3">
                        <div v-for="prod in topProducts" :key="prod.name" class="flex justify-between items-center text-sm border-b border-slate-50 pb-1">
                            <span class="font-bold text-slate-700 truncate pr-2">{{ prod.name }}</span>
                            <span class="font-black text-emerald-600 shrink-0">{{ prod.total_qty }}x</span>
                        </div>
                    </div>
                </div>
                <!-- Flops -->
                <div>
                    <h4 class="text-xs font-bold text-red-500 mb-3 flex items-center gap-2">📉 Flops (À retirer ou revoir)</h4>
                    <div class="space-y-3">
                        <div v-for="prod in flopProducts" :key="prod.name" class="flex justify-between items-center text-sm border-b border-slate-50 pb-1">
                            <span class="font-bold text-slate-700 truncate pr-2">{{ prod.name }}</span>
                            <span class="font-black text-red-500 shrink-0">{{ prod.total_qty }}x</span>
                        </div>
                    </div>
                </div>
            </div>
          </div>

          <!-- Performances Équipe -->
          <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
            <h3 class="font-black text-xs uppercase text-slate-400 mb-4 tracking-widest">Performance Équipe</h3>
            <div class="space-y-4">
              <div v-for="cashier in cashierPerformance" :key="cashier.name" class="border-b border-slate-50 pb-2">
                <div class="flex justify-between items-center mb-1">
                    <span class="font-bold text-slate-800 text-sm">{{ cashier.name }}</span>
                    <span class="font-black text-indigo-600 text-sm">{{ formatPrice(cashier.total_revenue) }}</span>
                </div>
                <div class="text-[10px] font-bold text-slate-400 uppercase">
                    {{ cashier.total_sales }} ventes réalisées
                </div>
              </div>
            </div>
          </div>

          <!-- Catégories -->
          <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
            <h3 class="font-black text-xs uppercase text-slate-400 mb-4 tracking-widest">Parts de Marché (Catégories)</h3>
            <div class="space-y-3">
              <div v-for="cat in categorySummary" :key="cat.name" class="flex justify-between items-center text-sm border-b border-slate-50 pb-1">
                <span class="font-bold text-slate-700">{{ cat.name }}</span>
                <span class="font-black text-slate-800">{{ formatPrice(cat.total_revenue) }}</span>
              </div>
            </div>
          </div>

          <!-- Paiements -->
          <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm md:col-span-2">
            <h3 class="font-black text-xs uppercase text-slate-400 mb-4 tracking-widest">Répartition des Paiements</h3>
            <div class="flex flex-wrap gap-4">
              <div v-for="pay in paymentSummary" :key="pay.method" class="flex-1 min-w-[120px] bg-slate-50 p-3 rounded-xl border border-slate-100">
                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">{{ pay.method }}</p>
                <p class="text-sm font-black text-slate-800">{{ formatPrice(pay.total) }}</p>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faXmark } from '@fortawesome/free-solid-svg-icons'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend)

library.add(faXmark)

const props = defineProps({
  isOpen: Boolean,
  posName: String,
  kpis: Object,
  topProducts: Array,
  flopProducts: Array,
  categorySummary: Array,
  cashierPerformance: Array,
  paymentSummary: Array,
  evolutionData: Array
})

const formatPrice = (price) => new Intl.NumberFormat('fr-FR').format(price) + ' Ar'

const chartData = computed(() => ({
  labels: props.evolutionData?.map(d => d.date) || [],
  datasets: [{
    label: 'Évolution CA',
    data: props.evolutionData?.map(d => d.total) || [],
    borderColor: '#4f46e5',
    backgroundColor: '#e0e7ff',
    tension: 0.4,
    fill: true
  }]
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    y: { beginAtZero: true }
  }
}
</script>
