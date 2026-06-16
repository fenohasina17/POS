<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="$emit('close')"></div>
    <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="p-6 border-b border-slate-100 flex justify-between items-center">
        <h2 class="text-xl font-black text-slate-800 tracking-tight">{{ posName }} - Détails</h2>
        <button @click="$emit('close')" class="p-2 hover:bg-slate-100 rounded-xl transition">
          <font-awesome-icon icon="fa-solid fa-xmark" />
        </button>
      </div>

      <!-- Scrollable content -->
      <div class="p-6 overflow-y-auto space-y-8">
        <!-- Graphique -->
        <div class="h-64 bg-slate-50 p-4 rounded-2xl border border-slate-100">
          <Line :data="chartData" :options="chartOptions" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Détails Produits -->
          <div class="border border-slate-100 rounded-2xl p-4">
            <h3 class="font-black text-xs uppercase text-slate-400 mb-3 tracking-widest">Top Produits</h3>
            <div class="space-y-2">
              <div v-for="prod in topProducts" :key="prod.name" class="flex justify-between items-center text-sm border-b border-slate-50 pb-1">
                <span class="font-bold text-slate-700">{{ prod.name }}</span>
                <span class="font-black text-indigo-600">{{ prod.total_qty }} / {{ formatPrice(prod.total_revenue) }}</span>
              </div>
            </div>
          </div>
          
          <!-- Détails Paiements -->
          <div class="border border-slate-100 rounded-2xl p-4">
            <h3 class="font-black text-xs uppercase text-slate-400 mb-3 tracking-widest">Détails Paiements</h3>
            <div class="space-y-2">
              <div v-for="pay in paymentSummary" :key="pay.method" class="flex justify-between items-center text-sm border-b border-slate-50 pb-1">
                <span class="font-bold text-slate-700">{{ pay.method }}</span>
                <span class="font-black text-emerald-600">{{ formatPrice(pay.total) }}</span>
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
  topProducts: Array,
  paymentSummary: Array,
  evolutionData: Array // { date: '...', total: ... }
})

const formatPrice = (price) => new Intl.NumberFormat('fr-FR').format(price) + ' Ar'

const chartData = computed(() => ({
  labels: props.evolutionData.map(d => d.date),
  datasets: [{
    label: 'Évolution CA',
    data: props.evolutionData.map(d => d.total),
    borderColor: '#4f46e5',
    backgroundColor: '#e0e7ff',
    tension: 0.4
  }]
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false
}
</script>
