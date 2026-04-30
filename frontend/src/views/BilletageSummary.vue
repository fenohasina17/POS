<template>
  <div class="min-h-screen bg-slate-50 p-4 md:p-8">
    <Profile />

    <div class="mx-auto max-w-2xl bg-white shadow-xl rounded-2xl p-16 print-container" ref="summaryRef">
      <!-- Header Imprimable -->
      <div class="text-center border-b-2 border-dashed border-slate-300 pb-6 mb-6">
        <h1 class="text-2xl font-black text-slate-900 uppercase">Récapitulatif Session</h1>
        <p class="text-sm font-bold text-slate-500 mt-2">{{ sessionLabel }}</p>
      </div>

      <!-- Corps du résumé -->
      <div class="space-y-6">
        <!-- Infos clés -->
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
            <p class="text-[10px] font-black uppercase text-slate-400">Ouverture</p>
            <p class="text-sm font-bold">{{ formatDate(sessionInfo?.opened_at) }}</p>
          </div>
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
            <p class="text-[10px] font-black uppercase text-slate-400">Fond de caisse</p>
            <p class="text-sm font-bold">{{ formatPrice(sessionInfo?.starting_amount || 0) }}</p>
          </div>
        </div>

        <!-- Produits vendus par catégorie -->
        <div class="space-y-4">
          <div v-for="cat in categorySummary" :key="cat.category_id" class="space-y-1">
            <h3 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest border-b border-indigo-100 pb-1">{{ cat.category_name }}</h3>
            <div v-for="item in cat.products" :key="item.product_id" class="flex justify-between py-1 border-b border-dotted border-slate-100">
              <div class="text-sm font-bold">{{ item.product_name }} <span class="text-[10px] text-slate-400">x{{ item.quantity }}</span></div>
              <span class="text-sm font-black">{{ formatPrice(item.amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Paiements -->
        <div class="space-y-2">
          <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b pb-1">Détails paiements</h3>
          <div v-for="pay in paymentSummary" :key="pay.payment_name" class="flex justify-between py-1">
            <span class="text-sm font-medium">{{ pay.payment_name }}</span>
            <span class="text-sm font-black">{{ formatPrice(pay.total) }}</span>
          </div>

          <!-- Calcul Écart -->
          <div class="pt-4 border-t-2 border-slate-900 mt-4 space-y-1">
            <div class="flex justify-between text-sm">
              <span class="font-bold">Total Ventes</span>
              <span class="font-black">{{ formatPrice(totalPaymentsAmount) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="font-bold">Montant compté (Billetage)</span>
              <span class="font-black">{{ formatPrice(sessionInfo?.actual_cash_amount || 0) }}</span>
            </div>
            <div class="flex justify-between text-base font-black pt-2 mt-2" :class="variance >= 0 ? 'text-emerald-700' : 'text-rose-700'">
              <span>ÉCART</span>
              <span>{{ formatPrice(variance) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="text-center mt-10 text-[10px] font-bold text-slate-400 border-t pt-4">
        <p>Gastronomie Pizza - Merci de votre confiance</p>
        <p>{{ currentDateTime }}</p>
      </div>
    </div>

    <!-- Actions -->
    <div class="mx-auto max-w-2xl mt-8 flex gap-4 print:hidden">
      <button @click="goBack" class="flex-1 rounded-xl bg-slate-200 py-3 font-black text-slate-700">Retour</button>
      <button @click="printSummary" class="flex-1 rounded-xl bg-indigo-600 py-3 font-black text-white shadow-lg">Imprimer (XPrinter)</button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Profile from './Profile.vue'
import { API_BASE_URL } from '@/utils/api'
import { printingService } from '@/services/printing/PrintingService'

const route = useRoute()
const router = useRouter()

const summaryRef = ref(null)
const loading = ref(true)
const summaryData = ref(null)
const categorySummary = ref([])
const paymentSummary = ref([])

const sessionId = computed(() => Number(route.params.sessionId || route.query.sessionId))
const sessionInfo = computed(() => summaryData.value?.session ?? null)
const sessionLabel = computed(() => sessionInfo.value ? `Session #${sessionInfo.value.id} - ${sessionInfo.value.cash_register?.name || 'Caisse'}` : '')
const totalPaymentsAmount = computed(() => paymentSummary.value.reduce((sum, p) => sum + Number(p.total || 0), 0))
const currentDateTime = new Date().toLocaleString('fr-FR')

// Regroupement produits
const groupedProducts = computed(() => {
  const map = new Map()
  // Sécurité renforcée pour éviter les plantages si summaryData ou ses enfants sont undefined
  if (!summaryData.value || !summaryData.value.categories || !Array.isArray(summaryData.value.categories)) {
    return []
  }

  summaryData.value.categories.forEach(cat => {
    // Vérification que cat.items existe et est bien un tableau
    if (cat && cat.items && Array.isArray(cat.items)) {
      cat.items.forEach(item => {
        // Sécurité pour le nom de l'item
        const name = item.name || 'Produit inconnu'
        const price = Number(item.price || 0)
        const quantity = Number(item.quantity || 0)
        const key = `${name}-${price}`

        if (map.has(key)) {
          const existing = map.get(key)
          existing.quantity += quantity
          existing.total += (quantity * price)
        } else {
          map.set(key, {
            name: name,
            price: price,
            quantity: quantity,
            total: (quantity * price)
          })
        }
      })
    }
  })
  return Array.from(map.values())
})

// Calcul écart
const variance = computed(() => {
  const actual = Number(sessionInfo.value?.actual_cash_amount || 0)
  const expected = Number(sessionInfo.value?.starting_amount || 0) + totalPaymentsAmount.value
  return actual - expected
})

const formatDate = (date) => date ? new Date(date).toLocaleString('fr-FR') : '-'
const formatPrice = (price) => {
  const value = Number(price);
  if (isNaN(value)) return '0';
  return value.toLocaleString('fr-FR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });
}

const fetchSummary = async () => {
  if (!sessionId.value) return
  try {
    const { data } = await axios.get(`${API_BASE_URL}/cash-register-sessions/${sessionId.value}/summary`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
    })
    console.log("DEBUG: Structure complète du résumé :", data)

    summaryData.value = data?.data || data
    categorySummary.value = summaryData.value?.categories || []
    paymentSummary.value = summaryData.value?.payments || []

    console.log("DEBUG: summaryData.value.categories :", summaryData.value?.categories)
  } catch (err) { console.error(err) } finally { loading.value = false }
}

const goBack = () => router.push({ name: 'billetage' })

const printSummary = async () => {
  if (!summaryData.value) return
  await printingService.printSessionSummary(summaryData.value)
  // Force l'avance et la coupe via une commande brute supplémentaire pour XPrinter
  await printingService.sendRawCommands(['\n\n\n\x1D\x56\x41\x03'])
}

onMounted(fetchSummary)
</script>

<style scoped>
@media print {
  body * { visibility: hidden; }
  .print-container, .print-container * { visibility: visible; }
  .print-container { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
