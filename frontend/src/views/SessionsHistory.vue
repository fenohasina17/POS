<template>
  <div class="p-6 md:p-10 bg-slate-50 min-h-screen">
    <header class="mb-8 flex flex-wrap items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
      <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
          <font-awesome-icon icon="fa-solid fa-history" class="text-indigo-600" />
          Historique des Sessions
        </h1>
        <p class="text-slate-500 font-medium text-sm mt-1">Gestion et clôture des sessions de caisse.</p>
      </div>
      <button
        @click="fetchSessions"
        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100 shadow-sm"
        :disabled="loading"
      >
        <font-awesome-icon icon="fa-solid fa-rotate" :class="{ 'animate-spin': loading }" />
        Actualiser
      </button>
    </header>

    <!-- Filtres -->
    <section class="mb-8 bg-white p-5 rounded-3xl border border-slate-100 shadow-sm flex flex-wrap items-center gap-4">
      <div class="flex items-center gap-3 text-sm font-bold text-slate-600">
        <font-awesome-icon icon="fa-solid fa-filter" class="text-indigo-500" />
        Statut :
      </div>

      <select v-model="filters.status" @change="fetchSessions" class="rounded-2xl border-none bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none min-w-[150px]">
        <option value="">Tous les statuts</option>
        <option value="open">Ouvertes</option>
        <option value="closed">Fermées</option>
      </select>
    </section>

    <!-- Liste des Sessions -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
      <div v-if="loading" class="py-20 text-center text-slate-400">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-100 border-t-indigo-600 mx-auto"></div>
        <p class="mt-4 font-bold uppercase text-xs tracking-widest">Chargement des données...</p>
      </div>

      <div v-else-if="sessions.length === 0" class="py-20 text-center text-slate-400">
        <font-awesome-icon icon="fa-solid fa-history" class="text-4xl mb-4" />
        <p class="font-bold uppercase text-sm tracking-widest">Aucune session trouvée pour ce site</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="bg-slate-50 text-slate-500 text-[11px] font-black uppercase tracking-widest">
              <th class="px-6 py-4">ID / Caisse</th>
              <th class="px-6 py-4">Caissier</th>
              <th class="px-6 py-4">Ouverture</th>
              <th class="px-6 py-4">Fermeture</th>
              <th class="px-6 py-4 text-right">CA Réel</th>
              <th class="px-6 py-4 text-center">Statut</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="session in sessions" :key="session.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="px-6 py-4">
                <p class="font-black text-slate-900 text-sm">#{{ session.id }}</p>
                <p class="text-[10px] text-slate-400 font-bold uppercase">{{ session.cash_register?.name }}</p>
              </td>
              <td class="px-6 py-4 font-bold text-slate-700 text-sm">{{ session.user?.name }}</td>
              <td class="px-6 py-4 text-[10px] font-semibold text-slate-500 uppercase">{{ formatDate(session.opened_at) }}</td>
              <td class="px-6 py-4 text-[10px] font-semibold text-slate-500 uppercase">{{ formatDate(session.closed_at) || '-' }}</td>
              <td class="px-6 py-4 text-right font-black text-indigo-600 text-sm">
                {{ session.actual_cash_amount !== null ? formatPrice(session.actual_cash_amount) : '-' }}
              </td>
              <td class="px-6 py-4 text-center">
                <span :class="[
                  'px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider',
                  !session.is_closed ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'
                ]">
                  {{ !session.is_closed ? 'Ouverte' : 'Fermée' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                  <button 
                    v-if="!session.is_closed"
                    @click="closeSession(session)"
                    :disabled="processingId === session.id"
                    class="h-9 w-9 flex items-center justify-center rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all disabled:opacity-50 border border-rose-100"
                    title="Clôturer la session"
                  >
                    <font-awesome-icon :icon="processingId === session.id ? 'fa-solid fa-circle-notch' : 'fa-solid fa-lock'" :class="{'animate-spin': processingId === session.id}" />
                  </button>
                  <button 
                    v-if="session.is_closed"
                    @click="reopenSession(session)"
                    :disabled="processingId === session.id"
                    class="h-9 w-9 flex items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all disabled:opacity-50 border border-indigo-100"
                    title="Réouvrir la session"
                  >
                    <font-awesome-icon :icon="processingId === session.id ? 'fa-solid fa-circle-notch' : 'fa-solid fa-unlock'" :class="{'animate-spin': processingId === session.id}" />
                  </button>
                  <router-link
                    v-if="session.is_closed"
                    :to="{ name: 'billetage-summary', params: { sessionId: session.id } }"
                    class="h-9 w-9 flex items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:bg-indigo-600 hover:text-white transition-all border border-slate-200"
                    title="Voir le rapport"
                  >
                    <font-awesome-icon icon="fa-solid fa-file-invoice" />
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive, watch } from 'vue'
import apiClient from '@/services/apiClient'
import { useAuth } from '@/composables/useAuth'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { 
  faHistory, faRotate, faFilter, faUnlock, faCircleNotch, faFileInvoice, faLock
} from '@fortawesome/free-solid-svg-icons'

library.add(faHistory, faRotate, faFilter, faUnlock, faCircleNotch, faFileInvoice, faLock)

const { activePos } = useAuth()
const sessions = ref([])
const pointsOfSale = ref([])
const loading = ref(false)
const processingId = ref(null)

const filters = reactive({
  point_of_sale_id: activePos.value?.id || null,
  status: ''
})

// Surveiller les changements du site actif global pour mettre à jour la liste
watch(() => activePos.value?.id, (newId) => {
  if (newId) {
    filters.point_of_sale_id = newId
    fetchSessions()
  }
})

const formatDate = (date) => {
  if (!date) return null
  return new Date(date).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('fr-FR').format(price) + ' Ar'
}

const fetchPointsOfSale = async () => {
  try {
    const { data } = await apiClient.get('/point-of-sales')
    pointsOfSale.value = data?.data || data || []
  } catch (err) {
    console.error('Erreur chargement POS:', err)
  }
}

const fetchSessions = async () => {
  loading.value = true
  try {
    const params = {}
    if (filters.point_of_sale_id) params.point_of_sale_id = filters.point_of_sale_id
    if (filters.status) params.status = filters.status

    const { data } = await apiClient.get('/cash-register-sessions', { params })
    sessions.value = Array.isArray(data) ? data : data?.data || []
  } catch (err) {
    console.error('Erreur chargement sessions:', err)
  } finally {
    loading.value = false
  }
}

const reopenSession = async (session) => {
  if (!confirm(`Voulez-vous vraiment réouvrir la session #${session.id} ? Le montant déjà compté sera réinitialisé.`)) return

  processingId.value = session.id
  try {
    await apiClient.post(`/cash-register-sessions/${session.id}/reopen`)
    alert('Session réouverte avec succès.')
    await fetchSessions()
  } catch (err) {
    console.error('Erreur réouverture:', err)
    alert(err.response?.data?.message || 'Erreur lors de la réouverture.')
  } finally {
    processingId.value = null
  }
}

const closeSession = async (session) => {
  const amount = prompt(`Entrez le montant compté pour fermer la session #${session.id} :`)
  if (amount === null) return
  
  const actualAmount = parseFloat(amount)
  if (isNaN(actualAmount)) {
    alert("Montant invalide.")
    return
  }

  processingId.value = session.id
  try {
    await apiClient.put(`/cash-register-sessions/${session.id}`, {
      is_closed: true,
      actual_cash_amount: actualAmount,
      closed_at: new Date().toISOString()
    })
    alert('Session fermée avec succès.')
    await fetchSessions()
  } catch (err) {
    console.error('Erreur clôture:', err)
    alert(err.response?.data?.message || 'Erreur lors de la clôture.')
  } finally {
    processingId.value = null
  }
}

onMounted(() => {
  fetchPointsOfSale()
  fetchSessions()
})
</script>

<style scoped>
/* Styles spécifiques */
</style>
